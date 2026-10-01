const db = require('../db');
const state = require('../state');
const config = require('../config');
const { pushOffline } = require('../notify');
const { sendBlockReason, settingsOf, STAFF_ROLES } = require('./rules');

const TYPES = ['text', 'image', 'file', 'audio', 'poll'];

function format(m, user) {
  let meta = m.meta;
  if (typeof meta === 'string') { try { meta = JSON.parse(meta); } catch { meta = null; } }
  return {
    id: m.id, room_id: m.room_id, type: m.type, body: m.deleted_at ? null : m.body, meta: m.deleted_at ? null : meta,
    client_id: m.client_id, reply_to_id: m.reply_to_id, deleted: !!m.deleted_at, edited: !!m.edited_at,
    user: user ? { id: user.id, name: user.name, avatar: user.avatar } : null,
    created_at: new Date(m.created_at).toISOString(),
  };
}

async function memberOf(roomId, userId) {
  return db.one(
    `SELECT m.*, r.type AS room_type, r.settings, r.name AS room_name
       FROM chat_members m JOIN chat_rooms r ON r.id = m.room_id AND r.deleted_at IS NULL
      WHERE m.room_id = ? AND m.user_id = ? AND m.left_at IS NULL`,
    [roomId, userId],
  );
}

/** ack helper: every client event can pass a callback */
const reply = (ack, data) => (typeof ack === 'function' ? ack(data) : undefined);
const fail = (ack, message, extra = {}) => reply(ack, { ok: false, message, ...extra });

module.exports = function registerChat(io, socket) {
  const me = socket.data.user;

  // join all my rooms on connect
  (async () => {
    const rooms = await db.q('SELECT room_id FROM chat_members WHERE user_id = ? AND left_at IS NULL', [me.id]);
    socket.join(`user:${me.id}`);
    rooms.forEach((r) => socket.join(`room:${r.room_id}`));
    socket.emit('ready', { user_id: me.id, rooms: rooms.map((r) => r.room_id), ice_servers: config.rtc.iceServers });
  })().catch((e) => console.error('[join]', e));

  socket.on('room:subscribe', async ({ room_id } = {}, ack) => {
    const m = await memberOf(room_id, me.id);
    if (!m) return fail(ack, 'You are not in this chat.');
    socket.join(`room:${room_id}`);
    return reply(ack, { ok: true });
  });

  /**
   * message:send { room_id, client_id, type, body, meta, reply_to_id }
   * ack → { ok, message } | { ok:false, message }
   */
  socket.on('message:send', async (p = {}, ack) => {
    try {
      const roomId = parseInt(p.room_id, 10);
      const type = TYPES.includes(p.type) ? p.type : 'text';
      const body = typeof p.body === 'string' ? p.body.trim().slice(0, config.limits.maxLength) : null;
      if (type === 'text' && !body) return fail(ack, 'Message is empty.');
      if (type !== 'text' && !(p.meta && p.meta.url) && type !== 'poll') return fail(ack, 'Attachment is missing.');

      const count = await state.hit(`rt:rate:${me.id}`, config.limits.windowSec);
      if (count > config.limits.rate) return fail(ack, 'You are sending too fast. Please wait a moment.');

      const member = await memberOf(roomId, me.id);
      const room = member ? { type: member.room_type, settings: member.settings } : null;
      const reason = sendBlockReason(room || {}, member, { type, body });
      if (reason) return fail(ack, reason);

      const s = settingsOf(room);
      if (s.slow_mode_sec > 0 && !STAFF_ROLES.includes(member.role)) {
        if (!(await state.setOnce(`rt:slow:${roomId}:${me.id}`, s.slow_mode_sec))) return fail(ack, `Slow mode is on – one message every ${s.slow_mode_sec} seconds.`);
      }

      // retries from a flaky connection: same client_id → same message
      if (p.client_id) {
        const dup = await db.one('SELECT * FROM chat_messages WHERE room_id = ? AND user_id = ? AND client_id = ?', [roomId, me.id, String(p.client_id).slice(0, 40)]);
        if (dup) return reply(ack, { ok: true, message: format(dup, me) });
      }
      let replyTo = null;
      if (p.reply_to_id) {
        replyTo = await db.one('SELECT id FROM chat_messages WHERE id = ? AND room_id = ?', [p.reply_to_id, roomId]);
      }
      const meta = p.meta ? JSON.stringify({
        url: p.meta.url, name: p.meta.name, size: p.meta.size, mime: p.meta.mime, duration: p.meta.duration,
        options: Array.isArray(p.meta.options) ? p.meta.options.slice(0, 10).map(String) : undefined,
      }) : null;
      const now = new Date();
      const [res] = await db.pool.query(
        'INSERT INTO chat_messages (room_id, user_id, type, body, meta, reply_to_id, client_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [roomId, me.id, type, body, meta, replyTo ? replyTo.id : null, p.client_id ? String(p.client_id).slice(0, 40) : null, now, now],
      );
      await db.q('UPDATE chat_rooms SET last_message_id = ?, last_message_at = ? WHERE id = ?', [res.insertId, now, roomId]);
      await db.q('UPDATE chat_members SET last_read_message_id = ? WHERE room_id = ? AND user_id = ?', [res.insertId, roomId, me.id]);

      const message = format({ id: res.insertId, room_id: roomId, type, body, meta, client_id: p.client_id || null, reply_to_id: replyTo ? replyTo.id : null, created_at: now }, me);
      socket.to(`room:${roomId}`).emit('message:new', message);
      reply(ack, { ok: true, message });

      // offline members → push notification via Laravel/FCM
      const members = await db.q('SELECT user_id FROM chat_members WHERE room_id = ? AND left_at IS NULL AND notifications = 1 AND user_id <> ?', [roomId, me.id]);
      const ids = members.map((m) => m.user_id);
      if (ids.length) {
        const online = await state.onlineMap(ids);
        const offline = ids.filter((id) => !online[String(id)]);
        if (offline.length) {
          pushOffline({
            room_id: roomId, message_id: res.insertId, sender_name: me.name,
            text: type === 'text' ? body : ({ image: '📷 Photo', file: '📎 File', audio: '🎤 Voice note', poll: '📊 Poll' }[type]),
            offline_user_ids: offline,
          });
        }
      }
      return undefined;
    } catch (e) {
      console.error('[message:send]', e);
      return fail(ack, 'Could not send. Please try again.');
    }
  });

  socket.on('message:edit', async ({ id, body } = {}, ack) => {
    const msg = await db.one('SELECT * FROM chat_messages WHERE id = ? AND deleted_at IS NULL', [id]);
    if (!msg || msg.user_id !== me.id || msg.type !== 'text') return fail(ack, 'You can only edit your own messages.');
    if (Date.now() - new Date(msg.created_at).getTime() > 15 * 60 * 1000) return fail(ack, 'Messages can be edited for 15 minutes.');
    const text = String(body || '').trim().slice(0, config.limits.maxLength);
    if (!text) return fail(ack, 'Message is empty.');
    await db.q('UPDATE chat_messages SET body = ?, edited_at = NOW(), updated_at = NOW() WHERE id = ?', [text, id]);
    io.to(`room:${msg.room_id}`).emit('message:edited', { id, room_id: msg.room_id, body: text });
    return reply(ack, { ok: true });
  });

  socket.on('message:delete', async ({ id } = {}, ack) => {
    const msg = await db.one('SELECT * FROM chat_messages WHERE id = ? AND deleted_at IS NULL', [id]);
    if (!msg) return fail(ack, 'Message not found.');
    const member = await memberOf(msg.room_id, me.id);
    const own = msg.user_id === me.id && Date.now() - new Date(msg.created_at).getTime() < 60 * 60 * 1000;
    const mod = (member && STAFF_ROLES.includes(member.role)) || me.roles.some((r) => ['super_admin', 'admin', 'staff'].includes(r));
    if (!own && !mod) return fail(ack, 'You cannot delete this message.');
    await db.q('UPDATE chat_messages SET deleted_at = NOW() WHERE id = ?', [id]);
    io.to(`room:${msg.room_id}`).emit('message:deleted', { id, room_id: msg.room_id, by: me.id });
    return reply(ack, { ok: true });
  });

  socket.on('message:read', async ({ room_id, message_id } = {}, ack) => {
    await db.q('UPDATE chat_members SET last_read_message_id = GREATEST(COALESCE(last_read_message_id,0), ?) WHERE room_id = ? AND user_id = ?', [message_id, room_id, me.id]);
    socket.to(`room:${room_id}`).emit('message:read', { room_id, user_id: me.id, message_id });
    return reply(ack, { ok: true });
  });

  socket.on('typing', ({ room_id, typing } = {}) => {
    if (!socket.rooms.has(`room:${room_id}`)) return;
    socket.to(`room:${room_id}`).emit('typing', { room_id, user_id: me.id, name: me.name, typing: !!typing });
  });

  socket.on('poll:vote', async ({ message_id, option } = {}, ack) => {
    const msg = await db.one("SELECT * FROM chat_messages WHERE id = ? AND type = 'poll' AND deleted_at IS NULL", [message_id]);
    if (!msg || !(await memberOf(msg.room_id, me.id))) return fail(ack, 'Poll not found.');
    const meta = typeof msg.meta === 'string' ? JSON.parse(msg.meta) : (msg.meta || {});
    if (!Array.isArray(meta.options) || option < 0 || option >= meta.options.length) return fail(ack, 'Invalid option.');
    meta.votes = meta.votes || {};
    meta.votes[String(me.id)] = option;
    await db.q('UPDATE chat_messages SET meta = ? WHERE id = ?', [JSON.stringify(meta), message_id]);
    const counts = meta.options.map((_, i) => Object.values(meta.votes).filter((v) => v === i).length);
    io.to(`room:${msg.room_id}`).emit('poll:updated', { message_id, room_id: msg.room_id, counts });
    return reply(ack, { ok: true, counts });
  });

  socket.on('presence:query', async ({ user_ids } = {}, ack) => {
    const ids = (Array.isArray(user_ids) ? user_ids : []).slice(0, 200);
    return reply(ack, { ok: true, online: await state.onlineMap(ids) });
  });
};

module.exports.format = format;
