/**
 * Voice/video calls – ready for the next phase.
 *  1:1 (and small groups up to ~4) : peer-to-peer WebRTC; this server only relays offer/answer/ICE.
 *  1:n / n:n (live rooms, big groups): plug an SFU (LiveKit or mediasoup) in sfu.js – events stay the same,
 *   the server returns an SFU join token in call:join instead of relaying peer signals.
 */
const db = require('../db');
const config = require('../config');
const sfu = require('./sfu');

const MESH_LIMIT = 4;

module.exports = function registerCalls(io, socket) {
  const me = socket.data.user;
  const reply = (ack, d) => (typeof ack === 'function' ? ack(d) : undefined);

  socket.on('call:start', async ({ room_id, type = 'voice' } = {}, ack) => {
    const member = await db.one(
      `SELECT m.role, r.type AS room_type, r.settings, r.members_count FROM chat_members m JOIN chat_rooms r ON r.id = m.room_id
        WHERE m.room_id = ? AND m.user_id = ? AND m.left_at IS NULL`, [room_id, me.id]);
    if (!member) return reply(ack, { ok: false, message: 'You are not in this chat.' });
    const settings = typeof member.settings === 'string' ? JSON.parse(member.settings || '{}') : (member.settings || {});
    if (member.room_type !== 'direct' && !settings.voice_enabled) return reply(ack, { ok: false, message: 'Calls are turned off in this group.' });

    const mode = member.room_type === 'direct' ? 'one_to_one' : (member.members_count <= MESH_LIMIT ? 'many_to_many' : 'one_to_many');
    const [res] = await db.pool.query(
      'INSERT INTO call_sessions (room_id, type, mode, started_by, started_at, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW(),NOW())',
      [room_id, type === 'video' ? 'video' : 'voice', mode, me.id],
    );
    await db.q("INSERT INTO call_participants (call_session_id, user_id, role, joined_at) VALUES (?,?, 'host', NOW())", [res.insertId, me.id]);
    socket.join(`call:${res.insertId}`);
    const call = { id: res.insertId, room_id, type, mode, started_by: { id: me.id, name: me.name } };
    socket.to(`room:${room_id}`).emit('call:incoming', call);
    const extra = mode === 'one_to_many' ? await sfu.joinInfo(res.insertId, me, 'host') : null;
    return reply(ack, { ok: true, call, ice_servers: config.rtc.iceServers, sfu: extra });
  });

  socket.on('call:join', async ({ call_id } = {}, ack) => {
    const call = await db.one('SELECT * FROM call_sessions WHERE id = ? AND ended_at IS NULL', [call_id]);
    if (!call) return reply(ack, { ok: false, message: 'This call has ended.' });
    const member = await db.one('SELECT id FROM chat_members WHERE room_id = ? AND user_id = ? AND left_at IS NULL', [call.room_id, me.id]);
    if (!member) return reply(ack, { ok: false, message: 'You are not in this chat.' });
    await db.q("INSERT INTO call_participants (call_session_id, user_id, role, joined_at) VALUES (?,?,?,NOW())", [call_id, me.id, call.mode === 'one_to_many' ? 'listener' : 'speaker']);
    const peers = (await io.in(`call:${call_id}`).fetchSockets()).map((s) => s.data.user.id);
    socket.join(`call:${call_id}`);
    socket.to(`call:${call_id}`).emit('call:peer-joined', { call_id, user: { id: me.id, name: me.name } });
    const extra = call.mode === 'one_to_many' ? await sfu.joinInfo(call_id, me, 'listener') : null;
    return reply(ack, { ok: true, peers: [...new Set(peers)], ice_servers: config.rtc.iceServers, sfu: extra });
  });

  /** Relay SDP offer/answer and ICE candidates between two peers of the same call. */
  socket.on('call:signal', ({ call_id, to_user_id, data } = {}) => {
    if (!socket.rooms.has(`call:${call_id}`)) return;
    io.to(`user:${to_user_id}`).emit('call:signal', { call_id, from_user_id: me.id, data });
  });

  socket.on('call:decline', ({ call_id } = {}) => {
    io.to(`call:${call_id}`).emit('call:declined', { call_id, user_id: me.id });
  });

  socket.on('call:leave', async ({ call_id } = {}, ack) => {
    await leave(io, socket, call_id);
    reply(ack, { ok: true });
  });

  socket.on('disconnecting', async () => {
    for (const r of socket.rooms) {
      if (r.startsWith('call:')) await leave(io, socket, parseInt(r.slice(5), 10));
    }
  });

  async function leave(ioRef, s, callId) {
    await db.q('UPDATE call_participants SET left_at = NOW() WHERE call_session_id = ? AND user_id = ? AND left_at IS NULL', [callId, me.id]);
    s.leave(`call:${callId}`);
    s.to(`call:${callId}`).emit('call:peer-left', { call_id: callId, user_id: me.id });
    const left = await ioRef.in(`call:${callId}`).fetchSockets();
    if (left.length === 0) {
      await db.q('UPDATE call_sessions SET ended_at = NOW(), updated_at = NOW() WHERE id = ? AND ended_at IS NULL', [callId]);
      const call = await db.one('SELECT room_id FROM call_sessions WHERE id = ?', [callId]);
      if (call) ioRef.to(`room:${call.room_id}`).emit('call:ended', { call_id: callId });
    }
  }
};
