import { useEffect, useRef, useState } from 'react';
import { useMyRoomsQuery, useLazyMessagesQuery, useUploadMutation } from './api';
import useSocket from './useSocket';
import { Avatar, Badge, Button, Card, Empty, fmtDate, useMe } from '../../components/ui';

export default function LiveChat() {
  const { data: rooms = [], refetch } = useMyRoomsQuery();
  const [active, setActive] = useState(null);
  const [msgs, setMsgs] = useState([]);
  const [text, setText] = useState('');
  const [typing, setTyping] = useState(null);
  const [load] = useLazyMessagesQuery();
  const [upload] = useUploadMutation();
  const { socket, connected } = useSocket();
  const me = useMe();
  const bottom = useRef(null);
  const room = rooms.find((r) => r.id === active);

  useEffect(() => {
    if (!active) return;
    load({ id: active }).unwrap().then((m) => setMsgs(m.slice().reverse()));
  }, [active, load]);

  useEffect(() => {
    if (!socket) return undefined;
    const onNew = (m) => { if (m.room_id === active) setMsgs((x) => (x.some((y) => y.id === m.id) ? x : [...x, m])); else refetch(); };
    const onDel = ({ id }) => setMsgs((x) => x.map((m) => (m.id === id ? { ...m, deleted: true, body: null } : m)));
    const onType = (t) => { if (t.room_id === active) { setTyping(t.typing ? t.name : null); } };
    socket.on('message:new', onNew);
    socket.on('message:deleted', onDel);
    socket.on('typing', onType);
    socket.on('room:added', refetch);
    return () => { socket.off('message:new', onNew); socket.off('message:deleted', onDel); socket.off('typing', onType); socket.off('room:added', refetch); };
  }, [socket, active, refetch]);

  useEffect(() => { bottom.current?.scrollIntoView?.({ block: 'end' }); }, [msgs]);
  useEffect(() => { if (active && msgs.length) socket?.emit('message:read', { room_id: active, message_id: msgs[msgs.length - 1].id }); }, [msgs, active, socket]);

  const send = (payload) => new Promise((resolve) => {
    const client_id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    socket.emit('message:send', { room_id: active, client_id, ...payload }, (ack) => {
      if (ack?.ok) setMsgs((x) => [...x, ack.message]);
      else alert(ack?.message || 'Could not send');
      resolve();
    });
  });

  return (
    <div className="grid" style={{ gridTemplateColumns: 'minmax(220px,300px) 1fr', alignItems: 'start' }}>
      <Card flush title={<span>Chats {connected ? <Badge tone="green">online</Badge> : <Badge tone="grey">connecting…</Badge>}</span>}>
        <div style={{ maxHeight: 560, overflow: 'auto', marginTop: 8 }}>
          {rooms.map((r) => (
            <div key={r.id} className={`row node ${active === r.id ? 'on' : ''}`} style={{ padding: '10px 14px', cursor: 'pointer', background: active === r.id ? 'var(--lav)' : undefined }} onClick={() => setActive(r.id)}>
              <Avatar name={r.name} emoji={r.avatar} />
              <div className="grow" style={{ minWidth: 0 }}>
                <div className="b" style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{r.name}</div>
                <div className="small muted" style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{r.last_message ? `${r.last_message.by ? `${r.last_message.by}: ` : ''}${r.last_message.text}` : 'No messages yet'}</div>
              </div>
              {r.unread > 0 && <Badge tone="red">{r.unread}</Badge>}
            </div>
          ))}
          {!rooms.length && <Empty icon="💬" title="No chats yet" />}
        </div>
      </Card>
      <Card flush>
        {!room ? <Empty icon="👈" title="Pick a chat" /> : (
          <div style={{ display: 'flex', flexDirection: 'column', height: 600 }}>
            <div className="row" style={{ padding: 14, borderBottom: '1px solid var(--line)' }}><Avatar name={room.name} emoji={room.avatar} /><div><b>{room.name}</b><div className="small muted">{typing ? `${typing} is typing…` : `${room.members_count} members`}</div></div></div>
            <div style={{ flex: 1, overflow: 'auto', padding: 14 }}>
              {msgs.map((m) => (
                m.type === 'system' ? <div key={m.id} className="small muted" style={{ textAlign: 'center', margin: 8 }}>{m.body}</div> : (
                  <div key={m.id} className={`bubble ${m.user?.id === me.id ? 'me' : ''}`}>
                    {m.user?.id !== me.id && <div className="small b" style={{ color: 'var(--brand2)' }}>{m.user?.name}</div>}
                    {m.deleted ? <i className="muted">message deleted</i> : m.type === 'image' ? <img src={m.meta?.url} alt="" style={{ maxWidth: 240, borderRadius: 8 }} /> : m.type === 'file' || m.type === 'audio' ? <a href={m.meta?.url} target="_blank" rel="noreferrer">📎 {m.meta?.name}</a> : <span style={{ whiteSpace: 'pre-wrap' }}>{m.body}</span>}
                    <div className="small muted" style={{ textAlign: 'right' }}>{fmtDate(m.created_at, true)}{!m.deleted && (m.user?.id === me.id || ['owner', 'admin', 'moderator'].includes(room.my_role)) && <button className="btn ghost sm" onClick={() => socket.emit('message:delete', { id: m.id })} aria-label="Delete message">🗑️</button>}</div>
                  </div>
                )
              ))}
              <div ref={bottom} />
            </div>
            {room.can_send ? (
              <form className="row" style={{ padding: 12, borderTop: '1px solid var(--line)' }} onSubmit={async (e) => { e.preventDefault(); if (!text.trim()) return; await send({ type: 'text', body: text }); setText(''); }}>
                <label className="btn ghost" aria-label="Attach">📎<input type="file" hidden onChange={async (e) => { const f = e.target.files[0]; if (!f) return; const up = await upload({ id: active, file: f }).unwrap(); await send({ type: up.type, meta: up.meta }); }} /></label>
                <input className="input grow" placeholder="Message" value={text} onChange={(e) => { setText(e.target.value); socket?.emit('typing', { room_id: active, typing: !!e.target.value }); }} aria-label="Message" />
                <Button variant="primary" type="submit" disabled={!connected}>Send</Button>
              </form>
            ) : <div className="small muted" style={{ padding: 14, textAlign: 'center' }}>Only admins can send messages here.</div>}
          </div>
        )}
      </Card>
    </div>
  );
}
