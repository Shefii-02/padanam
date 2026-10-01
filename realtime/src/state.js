/**
 * Presence + counters. Uses Redis when REDIS_URL is set (needed for more than one Node process),
 * otherwise an in-memory fallback for single-process / local use.
 */
const { createClient } = require('redis');
const config = require('./config');

let redis = null;
const mem = { presence: new Map(), keys: new Map() };

async function init() {
  if (!config.redisUrl) return null;
  redis = createClient({ url: config.redisUrl });
  redis.on('error', (e) => console.error('[redis]', e.message));
  await redis.connect();
  return redis;
}

async function online(userId) {
  const id = String(userId);
  if (redis) return redis.hIncrBy('rt:presence', id, 1);
  mem.presence.set(id, (mem.presence.get(id) || 0) + 1);
  return mem.presence.get(id);
}

async function offline(userId) {
  const id = String(userId);
  if (redis) {
    const n = await redis.hIncrBy('rt:presence', id, -1);
    if (n <= 0) await redis.hDel('rt:presence', id);
    return Math.max(0, n);
  }
  const n = Math.max(0, (mem.presence.get(id) || 0) - 1);
  if (n === 0) mem.presence.delete(id); else mem.presence.set(id, n);
  return n;
}

async function onlineMap(userIds) {
  const ids = userIds.map(String);
  if (!ids.length) return {};
  if (redis) {
    const vals = await redis.hmGet('rt:presence', ids);
    return Object.fromEntries(ids.map((id, i) => [id, parseInt(vals[i] || '0', 10) > 0]));
  }
  return Object.fromEntries(ids.map((id) => [id, (mem.presence.get(id) || 0) > 0]));
}

/** Returns true if the key was set (i.e. NOT limited). */
async function setOnce(key, ttlSec) {
  if (redis) return (await redis.set(key, '1', { NX: true, EX: ttlSec })) === 'OK';
  const exp = mem.keys.get(key);
  if (exp && exp > Date.now()) return false;
  mem.keys.set(key, Date.now() + ttlSec * 1000);
  return true;
}

/** Fixed-window counter; returns the count in this window. */
async function hit(key, ttlSec) {
  if (redis) {
    const n = await redis.incr(key);
    if (n === 1) await redis.expire(key, ttlSec);
    return n;
  }
  const now = Date.now();
  const cur = mem.keys.get(key);
  if (!cur || cur.exp < now) { mem.keys.set(key, { n: 1, exp: now + ttlSec * 1000 }); return 1; }
  cur.n += 1;
  return cur.n;
}

module.exports = { init, online, offline, onlineMap, setOnce, hit, get redis() { return redis; } };
