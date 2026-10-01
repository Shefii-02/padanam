const express = require('express');
const db = require('./db');
const { verifyInternal } = require('./auth');

/**
 * Laravel → Node events (POST /internal/emit {event, payload}).
 * Laravel stays the source of truth; Node only fans events out to connected apps.
 */
module.exports = function internalRouter(io) {
  const r = express.Router();
  r.use(express.json({ limit: '256kb' }));

  r.post('/internal/emit', verifyInternal, async (req, res) => {
    const { event, payload = {} } = req.body || {};
    try {
      await handle(io, event, payload);
      res.json({ ok: true });
    } catch (e) {
      console.error('[internal]', event, e);
      res.status(500).json({ ok: false });
    }
  });

  return r;
};

async function handle(io, event, p) {
  const room = (id) => `room:${id}`;
  const adapter = io.of('/').adapter;
  // This server runs as a single Node process (redis is only used for presence/pub-sub
  // headroom, not a live cluster). The redis adapter's addSockets/delSockets never apply
  // locally except by publishing to redis and reacting to its own subscription, so without
  // flags.local the room change isn't visible yet when the events right after are emitted.
  // If this ever runs as multiple instances behind a load balancer, member add/remove will
  // need a real cross-node story (e.g. Laravel calling every instance).
  const moveSockets = (fn, filterRoom, targetRoom) => {
    adapter[fn]({ rooms: new Set([filterRoom]), except: new Set(), flags: { local: true } }, [targetRoom]);
  };
  switch (event) {
    case 'message.new':
      io.to(room(p.room_id)).emit('message:new', { room_id: p.room_id, ...p.message });
      break;
    case 'message.deleted':
      io.to(room(p.room_id)).emit('message:deleted', { id: p.message_id, room_id: p.room_id, by: p.by });
      break;
    case 'room.updated':
      io.to(room(p.room_id)).emit('room:updated', p.room);
      break;
    case 'room.deleted':
      moveSockets('delSockets', room(p.room_id), room(p.room_id));
      io.to(room(p.room_id)).emit('room:removed', { room_id: p.room_id });
      break;
    case 'member.added':
      for (const uid of p.user_ids || []) {
        moveSockets('addSockets', `user:${uid}`, room(p.room_id));
        io.to(`user:${uid}`).emit('room:added', { room_id: p.room_id });
      }
      break;
    case 'member.removed':
      moveSockets('delSockets', `user:${p.user_id}`, room(p.room_id));
      io.to(`user:${p.user_id}`).emit('room:removed', { room_id: p.room_id });
      io.to(room(p.room_id)).emit('member:left', { room_id: p.room_id, user_id: p.user_id });
      break;
    case 'member.updated':
      io.to(room(p.room_id)).emit('member:updated', p);
      break;
    case 'join.requested': {
      const admins = await db.q("SELECT user_id FROM chat_members WHERE room_id = ? AND role IN ('owner','admin') AND left_at IS NULL", [p.room_id]);
      admins.forEach((a) => io.to(`user:${a.user_id}`).emit('join:requested', p));
      break;
    }
    case 'live.started':
    case 'live.ended': {
      // tell the batch group + everyone enrolled in the batch who is online
      const r = await db.one("SELECT id FROM chat_rooms WHERE batch_id = ? AND type = 'batch_group' AND deleted_at IS NULL", [p.batch_id]);
      const name = event === 'live.started' ? 'live:started' : 'live:ended';
      if (r) io.to(room(r.id)).emit(name, p);
      const users = await db.q("SELECT user_id FROM enrollments WHERE batch_id = ? AND status = 'active'", [p.batch_id]);
      users.forEach((u) => io.to(`user:${u.user_id}`).emit(name, p));
      break;
    }
    default:
      // unknown events are ignored so Laravel can add new ones safely
      break;
  }
}
