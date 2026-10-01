const http = require('http');
const express = require('express');
const { Server } = require('socket.io');
const config = require('./config');
const state = require('./state');
const db = require('./db');
const { authenticate } = require('./auth');
const registerChat = require('./chat/handlers');
const registerCalls = require('./calls/signaling');
const internalRouter = require('./internal');

async function start({ port = config.port } = {}) {
  if (!config.jwtSecret) throw new Error('JWT_SECRET is missing (copy it from the Laravel .env)');
  await state.init();

  const app = express();
  app.get('/health', async (_req, res) => {
    try {
      await db.q('SELECT 1');
      res.json({ ok: true, redis: !!state.redis });
    } catch (e) {
      res.status(503).json({ ok: false });
    }
  });

  const server = http.createServer(app);
  const io = new Server(server, {
    cors: { origin: config.corsOrigins.includes('*') ? true : config.corsOrigins },
    pingInterval: 25000,
    pingTimeout: 20000,
    maxHttpBufferSize: 1e6,
    connectionStateRecovery: { maxDisconnectionDuration: 2 * 60 * 1000 },   // short network drops don't lose events
  });
  app.use(internalRouter(io));

  if (state.redis) {
    // several Node processes behind a load balancer share events through Redis
    const { createAdapter } = require('@socket.io/redis-adapter');
    const pub = state.redis.duplicate();
    const sub = state.redis.duplicate();
    await Promise.all([pub.connect(), sub.connect()]);
    io.adapter(createAdapter(pub, sub));
  }

  const handleDisconnect = async (socket) => {
    if (socket.data.cleanedUp) return;
    socket.data.cleanedUp = true;
    const left = await state.offline(socket.data.user.id);
    if (left === 0) {
      await db.q('UPDATE users SET last_seen_at = NOW() WHERE id = ?', [socket.data.user.id]).catch(() => {});
    }
  };

  io.use(authenticate);
  io.on('connection', async (socket) => {
    const me = socket.data.user;
    await state.online(me.id);
    registerChat(io, socket);
    registerCalls(io, socket);
    socket.on('disconnect', () => { handleDisconnect(socket).catch((e) => console.error('[disconnect]', e.message)); });
  });

  await new Promise((resolve) => server.listen(port, resolve));
  console.log(`[realtime] listening on :${server.address().port}`);

  const stop = async () => {
    // io.close() disconnects any still-connected sockets, but doesn't wait for our
    // async disconnect handling — run it ourselves first so redis/db aren't torn
    // down while presence updates for those sockets are still in flight.
    const cleanups = [...io.sockets.sockets.values()].map((s) => handleDisconnect(s).catch(() => {}));
    io.close();
    await Promise.all(cleanups);
    await new Promise((r) => server.close(r));
    await db.pool.end();
    if (state.redis) await state.redis.quit();
  };
  return { io, server, stop, port: server.address().port };
}

if (require.main === module) {
  start().catch((e) => {
    console.error(e);
    process.exit(1);
  });
  process.on('SIGTERM', () => process.exit(0));
}

module.exports = { start };
