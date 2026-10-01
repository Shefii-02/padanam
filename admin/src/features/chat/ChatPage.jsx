import { useEffect, useState } from 'react';
import {
  useAdminRoomsQuery, useRoomQuery, useMembersQuery, useRequestsQuery, useCreateGroupMutation, useUpdateGroupMutation, useResetInviteMutation,
  useAddMembersMutation, useRemoveMemberMutation, useMemberRoleMutation, useMuteMemberMutation, useHandleRequestMutation, useDeleteRoomMutation,
  useReportsQuery, useHandleReportMutation, usePoliciesQuery, useSavePoliciesMutation,
} from './api';
import { useCourseOptionsQuery } from '../courses/api';
import { Badge, Button, Can, Card, Confirm, Modal, PageHead, Tabs, Toggle, useCan, ago } from '../../components/ui';
import DataTable from '../../components/DataTable';
import Filters from '../../components/Filters';
import { Field, SchemaForm } from '../../components/Form';
import LiveChat from './LiveChat';

const SETTINGS = [
  ['who_can_send', 'Only admins can send (announcement mode)'], ['send_media', 'Members can send photos & files'], ['send_links', 'Members can send links'],
  ['members_can_invite', 'Members can share the invite link'], ['voice_enabled', 'Voice calls (coming soon)'],
];

function RoomModal({ id, onClose }) {
  const { data: room } = useRoomQuery(id);
  const { data: members = [] } = useMembersQuery(id);
  const { data: requests = [] } = useRequestsQuery(id);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [update] = useUpdateGroupMutation();
  const [reset] = useResetInviteMutation();
  const [add] = useAddMembersMutation();
  const [remove] = useRemoveMemberMutation();
  const [role] = useMemberRoleMutation();
  const [mute] = useMuteMemberMutation();
  const [handle] = useHandleRequestMutation();
  const [tab, setTab] = useState('settings');
  const [batch, setBatch] = useState('');
  if (!room) return null;
  const s = room.settings;
  const setS = (k, v) => update({ id, settings: { [k]: k === 'who_can_send' ? (v ? 'admins' : 'all') : v } });

  return (
    <Modal wide title={`${room.avatar || '💬'} ${room.name}`} onClose={onClose}>
      <Tabs value={tab} onChange={setTab} tabs={[{ key: 'settings', label: 'Settings' }, { key: 'members', label: `Members (${members.length})` }, { key: 'requests', label: `Join requests (${requests.length})` }]} />
      {tab === 'settings' && (
        <div className="grid g2">
          <div className="col">
            <SchemaForm initial={room} submitText="Save" fields={[{ name: 'name', label: 'Name', required: true }, { name: 'avatar', label: 'Emoji' }, { name: 'description', label: 'Description', type: 'textarea', cols: 2 },
              { name: 'join_mode', label: 'Joining with the link', type: 'select', required: true, cols: 2, options: [{ value: 'open', label: 'Anyone with the link joins' }, { value: 'approval', label: 'Admin approves each request' }, { value: 'invite_only', label: 'Link only (no search)' }] }]}
              onSubmit={(v) => update({ id, name: v.name, avatar: v.avatar, description: v.description, join_mode: v.join_mode })} />
            <div className="card" style={{ background: 'var(--bg)' }}>
              <div className="small b muted">Invite link (opens the app, or the store if not installed)</div>
              <div className="row" style={{ marginTop: 6 }}>
                <input className="input grow" readOnly value={room.invite_url || 'Invite link is off'} aria-label="Invite link" />
                <Button size="sm" onClick={() => navigator.clipboard.writeText(room.invite_url)}>Copy</Button>
                <a className="btn sm" href={`https://wa.me/?text=${encodeURIComponent(`Join ${room.name} on Padanam: ${room.invite_url}`)}`} target="_blank" rel="noreferrer">WhatsApp</a>
              </div>
              <div className="row" style={{ marginTop: 8 }}>
                <Toggle on={room.invite_enabled} onChange={(v) => update({ id, invite_enabled: v })} label="Invite link on" /><span className="small">Link {room.invite_enabled ? 'on' : 'off'}</span>
                <span className="grow" /><Button size="sm" onClick={() => reset(id)}>Reset link</Button>
              </div>
            </div>
          </div>
          <div>
            {SETTINGS.map(([k, label]) => (
              <div key={k} className="row between" style={{ padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
                <span>{label}</span><Toggle on={k === 'who_can_send' ? s.who_can_send === 'admins' : !!s[k]} onChange={(v) => setS(k, v)} label={label} />
              </div>
            ))}
            <div className="row between" style={{ padding: '10px 0' }}>
              <span>Slow mode (seconds between messages)</span>
              <select className="input" style={{ width: 110 }} value={s.slow_mode_sec} onChange={(e) => setS('slow_mode_sec', Number(e.target.value))} aria-label="Slow mode">
                {[0, 10, 30, 60, 300, 900].map((x) => <option key={x} value={x}>{x ? `${x}s` : 'Off'}</option>)}
              </select>
            </div>
          </div>
        </div>
      )}
      {tab === 'members' && (
        <>
          <div className="row" style={{ marginBottom: 10 }}>
            <select className="input" value={batch} onChange={(e) => setBatch(e.target.value)} aria-label="Batch">
              <option value="">Add all students of a batch…</option>
              {courses.flatMap((c) => c.batches.map((b) => <option key={b.id} value={b.id}>{c.title} – {b.name}</option>))}
            </select>
            <Button disabled={!batch} onClick={() => { add({ id, batch_id: Number(batch) }); setBatch(''); }}>Add</Button>
          </div>
          <table className="t"><tbody>{members.map((m) => (
            <tr key={m.user_id}>
              <td><b>{m.name}</b><div className="small muted">{m.phone}</div></td>
              <td>
                {m.role === 'owner' ? <Badge>owner</Badge> : (
                  <select className="input" style={{ width: 'auto' }} value={m.role} onChange={(e) => role({ id, userId: m.user_id, role: e.target.value })} aria-label="Role">
                    {['member', 'moderator', 'admin'].map((r) => <option key={r}>{r}</option>)}
                  </select>
                )}
              </td>
              <td>{m.muted_until && new Date(m.muted_until) > new Date() ? <Button size="sm" onClick={() => mute({ id, userId: m.user_id, minutes: null })}>Unmute</Button> : (
                <select className="input" style={{ width: 'auto' }} value="" onChange={(e) => mute({ id, userId: m.user_id, minutes: Number(e.target.value) })} aria-label="Mute">
                  <option value="">Mute…</option><option value={60}>1 hour</option><option value={1440}>1 day</option><option value={10080}>1 week</option><option value={0}>Until unmuted</option>
                </select>
              )}</td>
              <td className="right">{m.role !== 'owner' && <Button size="sm" variant="ghost" onClick={() => remove({ id, userId: m.user_id })}>Remove</Button>}</td>
            </tr>
          ))}</tbody></table>
        </>
      )}
      {tab === 'requests' && (requests.length ? requests.map((r) => (
        <div key={r.id} className="row" style={{ padding: '8px 0', borderBottom: '1px solid var(--line)' }}>
          <span className="grow"><b>{r.user?.name}</b> · {r.user?.phone} <span className="small muted">· {ago(r.created_at)}</span></span>
          <Button size="sm" variant="primary" onClick={() => handle({ id: r.id, approve: true })}>Approve</Button>
          <Button size="sm" onClick={() => handle({ id: r.id, approve: false })}>Reject</Button>
        </div>
      )) : <p className="muted">No pending requests.</p>)}
    </Modal>
  );
}

function Groups() {
  const [f, setF] = useState({ page: 1 });
  const { data, isFetching } = useAdminRoomsQuery(f);
  const { data: courses = [] } = useCourseOptionsQuery();
  const [create] = useCreateGroupMutation();
  const [del] = useDeleteRoomMutation();
  const [open, setOpen] = useState(null);
  const [adding, setAdding] = useState(false);
  const [confirm, setConfirm] = useState(null);
  return (
    <Card flush>
      <div style={{ padding: '14px 16px 0' }}>
        <Filters value={f} onChange={setF} filters={[{ name: 'type', label: 'Type', options: [['batch_group', 'Batch groups'], ['group', 'Groups'], ['broadcast', 'Broadcast channels'], ['staff', 'Staff groups']].map(([value, label]) => ({ value, label })) }]}>
          <Can perm="chat.create_group"><Button variant="primary" onClick={() => setAdding(true)}>+ Group</Button></Can>
        </Filters>
      </div>
      <DataTable loading={isFetching} rows={data?.items} meta={data?.meta} page={f.page} onPage={(page) => setF({ ...f, page })} onRow={(r) => setOpen(r.id)}
        columns={[
          { key: 'name', label: 'Group', render: (r) => <div><b>{r.avatar} {r.name}</b><div className="small muted">{r.batch?.name || r.type.replace('_', ' ')}</div></div> },
          { key: 'members_count', label: 'Members' },
          { key: 'join_mode', label: 'Joining', render: (r) => r.join_mode.replace('_', ' ') },
          { key: 'pending_requests', label: 'Requests', render: (r) => (r.pending_requests ? <Badge tone="orange">{r.pending_requests}</Badge> : '—') },
          { key: 'last', label: 'Last message', render: (r) => ago(r.last_message_at) },
          { key: 'x', label: '', render: (r) => <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); setConfirm(r.id); }} aria-label="Delete group">🗑️</Button> },
        ]} />
      {open && <RoomModal id={open} onClose={() => setOpen(null)} />}
      {adding && <Modal title="New group" onClose={() => setAdding(false)}>
        <SchemaForm initial={{ type: 'group', join_mode: 'approval', avatar: '💬' }} onCancel={() => setAdding(false)} fields={[
          { name: 'name', label: 'Name', required: true }, { name: 'avatar', label: 'Emoji' },
          { name: 'type', label: 'Type', type: 'select', required: true, options: [['group', 'Discussion group'], ['broadcast', 'Broadcast (only admins post)'], ['staff', 'Staff group']].map(([value, label]) => ({ value, label })) },
          { name: 'join_mode', label: 'Joining', type: 'select', options: [['open', 'Open'], ['approval', 'Approval'], ['invite_only', 'Link only']].map(([value, label]) => ({ value, label })) },
          { name: 'batch_id', label: 'For batch (only its students can join)', type: 'select', cols: 2, options: courses.flatMap((c) => c.batches.map((b) => ({ value: b.id, label: `${c.title} – ${b.name}` }))) },
          { name: 'description', label: 'Description', type: 'textarea', cols: 2 },
        ]} onSubmit={async (v) => { const r = await create({ ...v, batch_id: v.batch_id || null }).unwrap(); setAdding(false); setOpen(r.id); }} />
      </Modal>}
      {confirm && <Confirm text="Delete this group and its messages?" onYes={() => del(confirm)} onClose={() => setConfirm(null)} />}
    </Card>
  );
}

function Reports() {
  const { data } = useReportsQuery();
  const [act] = useHandleReportMutation();
  return (
    <Card title="Reported messages">
      {(data?.items || []).map((r) => (
        <div key={r.id} className="row wrap" style={{ padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
          <div className="grow"><div><b>{r.message?.user?.name}</b> in {r.message?.room?.name}: “{r.message?.body}”</div><div className="small muted">Reported by {r.user?.name} · {r.reason} · {ago(r.created_at)}</div></div>
          <Button size="sm" onClick={() => act({ id: r.id, action: 'dismiss' })}>Dismiss</Button>
          <Button size="sm" onClick={() => act({ id: r.id, action: 'delete_message' })}>Delete message</Button>
          <Button size="sm" variant="danger" onClick={() => act({ id: r.id, action: 'mute_user', minutes: 1440 })}>Mute 1 day</Button>
        </div>
      ))}
      {!data?.items?.length && <p className="muted">Nothing reported. 🎉</p>}
    </Card>
  );
}

const ROLES = ['admin', 'staff', 'teacher', 'student'];
const RULES = [['allow', 'Allowed'], ['deny', 'Not allowed'], ['shared_batch', 'Only same batch'], ['support_only', 'Only support staff']];

function Rules() {
  const { data } = usePoliciesQuery();
  const [save, s] = useSavePoliciesMutation();
  const [m, setM] = useState({});
  useEffect(() => { if (data) setM(Object.fromEntries(data.map((r) => [`${r.from_role}>${r.to_role}`, r.rule]))); }, [data]);
  const get = (a, b) => m[`${a}>${b}`] || m[`${a}>*`] || 'deny';
  return (
    <Card title="Who can start a private chat with whom" actions={<Can perm="chat.manage_permissions"><Button variant="primary" loading={s.isLoading} onClick={() => save(ROLES.flatMap((a) => ROLES.map((b) => ({ from_role: a, to_role: b, rule: get(a, b) }))))}>Save rules</Button></Can>}>
      <table className="t matrix">
        <thead><tr><th>From ↓ / To →</th>{ROLES.map((r) => <th key={r}>{r}</th>)}</tr></thead>
        <tbody>{ROLES.map((a) => (
          <tr key={a}><td className="b">{a}</td>{ROLES.map((b) => (
            <td key={b}><select className="input" value={get(a, b)} onChange={(e) => setM({ ...m, [`${a}>${b}`]: e.target.value })} aria-label={`${a} to ${b}`}>
              {RULES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></td>
          ))}</tr>
        ))}</tbody>
      </table>
      <p className="small muted">Groups are separate: anyone in a group can talk there, following that group's settings.</p>
    </Card>
  );
}

export default function ChatPage() {
  const can = useCan();
  const [tab, setTab] = useState(can('chat.manage_groups') ? 'groups' : 'live');
  return (
    <>
      <PageHead title="Chat & groups" sub="Batch groups are created automatically; students join when they enrol." />
      <Tabs value={tab} onChange={setTab} tabs={[
        can('chat.manage_groups') && { key: 'groups', label: '👥 Groups' }, { key: 'live', label: '💬 My chats' },
        can('chat.moderate') && { key: 'reports', label: '🚩 Reports' }, can('chat.manage_permissions') && { key: 'rules', label: '🛡️ Chat rules' },
      ]} />
      {tab === 'groups' && <Groups />}
      {tab === 'live' && <LiveChat />}
      {tab === 'reports' && <Reports />}
      {tab === 'rules' && <Rules />}
    </>
  );
}

export { Field };
