/*
 * End-to-end smoke test against a real MySQL (and Redis if REDIS_URL is set).
 *   DB_DATABASE=padanam_rt_test JWT_SECRET=test INTERNAL_KEY=k npm test
 * Loads test/schema.sql, starts the server on a random port, connects 3 users and checks the rules.
 */
process.env.JWT_SECRET = process.env.JWT_SECRET || 'test-secret';
process.env.INTERNAL_KEY = process.env.INTERNAL_KEY || 'internal-test-key';
const fs = require('fs');
const http = require('http');
const path = require('path');
const assert = require('assert/strict');
const jwt = require('jsonwebtoken');
const mysql = require('mysql2/promise');
const { io: connect } = require('socket.io-client');

// fake Laravel to capture offline push requests
const pushes = [];
const laravel = http.createServer((req, res) => {
  let b = '';
  req.on('data', (c) => { b += c; });
  req.on('end', () => { pushes.push({ url: req.url, key: req.headers['x-internal-key'], body: JSON.parse(b || '{}') }); res.end('{}'); });
});

const results = [];
async function check(name, fn) {
  try { await fn(); results.push(['PASS', name]); } catch (e) { results.push(['FAIL', name, e.message]); }
}
const token = (id, exp = '1h') => jwt.sign({ sub: String(id), roles: [] }, process.env.JWT_SECRET, { algorithm: 'HS256', expiresIn: exp });
const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const once = (s, ev, ms = 1500) => new Promise((res, rej) => { const t = setTimeout(() => rej(new Error('timeout ' + ev)), ms); s.once(ev, (d) => { clearTimeout(t); res(d); }); });
const emit = (s, ev, data) => new Promise((res) => s.emit(ev, data, res));

(async () => {
  await new Promise((r) => laravel.listen(0, r));
  process.env.LARAVEL_URL = `http://127.0.0.1:${laravel.address().port}`;

  const conn = await mysql.createConnection({ host: process.env.DB_HOST || '127.0.0.1', user: process.env.DB_USERNAME || 'root', password: process.env.DB_PASSWORD || '', database: process.env.DB_DATABASE, multipleStatements: true });
  await conn.query(fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8'));

  const { start } = require('../src/index');
  const srv = await start({ port: 0 });
  const url = `http://127.0.0.1:${srv.port}`;
  const mk = (id) => connect(url, { auth: { token: token(id) }, transports: ['websocket'], forceNew: true });

  const teacher = mk(1); const arjun = mk(2); const meera = mk(3);
  const ready = await Promise.all([once(teacher, 'ready'), once(arjun, 'ready'), once(meera, 'ready')]);

  await check('users join their rooms on connect', async () => {
    assert.deepEqual(ready[1].rooms.sort(), [10, 11, 12, 13]);
    assert.deepEqual(ready[0].rooms.sort(), [10, 11, 12]);
  });

  await check('bad token is rejected', async () => {
    const bad = connect(url, { auth: { token: 'nope' }, transports: ['websocket'], forceNew: true });
    const err = await once(bad, 'connect_error');
    assert.equal(err.message, 'unauthorized');
    bad.close();
  });

  await check('blocked user is rejected', async () => {
    const b = mk(4);
    const err = await once(b, 'connect_error');
    assert.equal(err.message, 'unauthorized');
    b.close();
  });

  await check('message reaches other members, sender gets ack', async () => {
    const got = once(meera, 'message:new');
    const ack = await emit(arjun, 'message:send', { room_id: 10, client_id: 'c-1', body: 'Namaskaram 🙏' });
    assert.equal(ack.ok, true);
    const m = await got;
    assert.equal(m.body, 'Namaskaram 🙏');
    assert.equal(m.user.name, 'Arjun Krishnan');
  });

  await check('retry with same client_id does not duplicate', async () => {
    const a1 = await emit(arjun, 'message:send', { room_id: 10, client_id: 'c-dup', body: 'once' });
    const a2 = await emit(arjun, 'message:send', { room_id: 10, client_id: 'c-dup', body: 'once' });
    assert.equal(a1.message.id, a2.message.id);
    const [[{ n }]] = await conn.query("SELECT COUNT(*) n FROM chat_messages WHERE client_id='c-dup'");
    assert.equal(n, 1);
  });

  await check('links blocked for members in groups', async () => {
    const a = await emit(arjun, 'message:send', { room_id: 10, body: 'join https://spam.example' });
    assert.equal(a.ok, false);
    assert.match(a.message, /Links/);
  });

  await check('group admin may post links', async () => {
    const a = await emit(teacher, 'message:send', { room_id: 10, body: 'Notes: https://padanam.app/n/1' });
    assert.equal(a.ok, true);
  });

  await check('links allowed in direct chats', async () => {
    const a = await emit(arjun, 'message:send', { room_id: 12, body: 'sir see www.keralapsc.gov.in' });
    assert.equal(a.ok, true);
  });

  await check('broadcast: only admins can send', async () => {
    const a = await emit(arjun, 'message:send', { room_id: 11, body: 'hello' });
    assert.equal(a.ok, false);
    const t = await emit(teacher, 'message:send', { room_id: 11, body: 'Class at 7 PM' });
    assert.equal(t.ok, true);
  });

  await check('non-member cannot send', async () => {
    const a = await emit(teacher, 'message:send', { room_id: 13, body: 'hi' });
    assert.equal(a.ok, false);
  });

  await check('slow mode', async () => {
    const a1 = await emit(arjun, 'message:send', { room_id: 13, body: 'one' });
    const a2 = await emit(arjun, 'message:send', { room_id: 13, body: 'two' });
    assert.equal(a1.ok, true);
    assert.equal(a2.ok, false);
    assert.match(a2.message, /Slow mode/);
  });

  await check('muted member cannot send', async () => {
    await conn.query('UPDATE chat_members SET muted_until = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE room_id=10 AND user_id=3');
    const a = await emit(meera, 'message:send', { room_id: 10, body: 'hi' });
    assert.equal(a.ok, false);
    assert.match(a.message, /muted/);
    await conn.query('UPDATE chat_members SET muted_until = NULL WHERE room_id=10 AND user_id=3');
  });

  await check('typing indicator', async () => {
    const got = once(meera, 'typing');
    arjun.emit('typing', { room_id: 10, typing: true });
    const t = await got;
    assert.equal(t.user_id, 2);
  });

  await check('read receipt stored and broadcast', async () => {
    const got = once(arjun, 'message:read');
    await emit(meera, 'message:read', { room_id: 10, message_id: 1 });
    await got;
    const [[m]] = await conn.query('SELECT last_read_message_id r FROM chat_members WHERE room_id=10 AND user_id=3');
    assert.equal(Number(m.r), 1);
  });

  await check('edit own message', async () => {
    const got = once(meera, 'message:edited');
    const a = await emit(arjun, 'message:edit', { id: 1, body: 'Namaskaram all' });
    assert.equal(a.ok, true);
    assert.equal((await got).body, 'Namaskaram all');
  });

  await check('student cannot delete others; group admin can', async () => {
    const [[own]] = await conn.query("SELECT id FROM chat_messages WHERE user_id=1 AND room_id=10 LIMIT 1");
    const a = await emit(arjun, 'message:delete', { id: own.id });
    assert.equal(a.ok, false);
    const got = once(meera, 'message:deleted');
    const t = await emit(teacher, 'message:delete', { id: 1 });
    assert.equal(t.ok, true);
    await got;
  });

  await check('poll vote counts', async () => {
    const p = await emit(teacher, 'message:send', { room_id: 10, type: 'poll', body: 'Next topic?', meta: { options: ['Maths', 'GK'] } });
    assert.equal(p.ok, true);
    const v = await emit(arjun, 'poll:vote', { message_id: p.message.id, option: 1 });
    assert.deepEqual(v.counts, [0, 1]);
  });

  await check('presence', async () => {
    const a = await emit(arjun, 'presence:query', { user_ids: [1, 3, 99] });
    assert.deepEqual(a.online, { 1: true, 3: true, 99: false });
  });

  await check('offline members get a push via Laravel', async () => {
    pushes.length = 0;
    meera.close();
    await wait(300);
    await emit(arjun, 'message:send', { room_id: 10, body: 'Meera, check the notes' });
    await wait(400);
    const p = pushes.find((x) => x.url === '/api/v1/internal/chat/notify');
    assert.ok(p, 'no push request');
    assert.equal(p.key, process.env.INTERNAL_KEY);
    assert.deepEqual(p.body.offline_user_ids, [3]);
  });

  const post = (body, key = process.env.INTERNAL_KEY) => fetch(`${url}/internal/emit`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Internal-Key': key }, body: JSON.stringify(body) });

  await check('internal endpoint needs the key', async () => {
    const r = await post({ event: 'room.updated', payload: {} }, 'wrong-key-xxxxxxxx');
    assert.equal(r.status, 401);
  });

  await check('Laravel member.added joins the user to the room live', async () => {
    await conn.query("INSERT INTO chat_members (room_id, user_id, role, joined_at) VALUES (13,1,'member',NOW())");
    const got = once(teacher, 'room:added');
    await post({ event: 'member.added', payload: { room_id: 13, user_ids: [1] } });
    await got;
    const m = once(teacher, 'message:new');
    await emit(arjun, 'message:send', { room_id: 13, body: 'x' }).catch(() => {});
    // slow mode blocks arjun; use a system message from Laravel instead
    await post({ event: 'message.new', payload: { room_id: 13, message: { id: 999, type: 'system', body: 'Suresh joined' } } });
    assert.equal((await m).body, 'Suresh joined');
  });

  await check('Laravel member.removed kicks the user out of the room', async () => {
    const got = once(teacher, 'room:removed');
    await post({ event: 'member.removed', payload: { room_id: 13, user_id: 1 } });
    await got;
    let received = false;
    teacher.once('message:new', (m) => { if (m.room_id === 13) received = true; });
    await post({ event: 'message.new', payload: { room_id: 13, message: { id: 1000, type: 'system', body: 'after' } } });
    await wait(300);
    assert.equal(received, false);
  });

  await check('live.started reaches enrolled students', async () => {
    const got = once(arjun, 'live:started');
    await post({ event: 'live.started', payload: { live_class_id: 5, batch_id: 7, youtube_id: 'abc' } });
    assert.equal((await got).youtube_id, 'abc');
  });

  await check('1:1 call signalling relays offer to the other peer', async () => {
    const incoming = once(teacher, 'call:incoming');
    const start = await emit(arjun, 'call:start', { room_id: 12, type: 'voice' });
    assert.equal(start.ok, true);
    assert.equal(start.call.mode, 'one_to_one');
    const call = await incoming;
    const joined = await emit(teacher, 'call:join', { call_id: call.id });
    assert.equal(joined.ok, true);
    assert.deepEqual(joined.peers, [2]);
    const sig = once(teacher, 'call:signal');
    arjun.emit('call:signal', { call_id: call.id, to_user_id: 1, data: { sdp: 'offer' } });
    assert.equal((await sig).data.sdp, 'offer');
    const ended = once(teacher, 'call:ended', 2000);
    await emit(arjun, 'call:leave', { call_id: call.id });
    await emit(teacher, 'call:leave', { call_id: call.id });
    await ended.catch(() => {});   // both left → ended (teacher already left the socket room; checked via DB)
    const [[c]] = await conn.query('SELECT ended_at FROM call_sessions WHERE id = ?', [call.id]);
    assert.ok(c.ended_at);
  });

  await check('calls blocked in groups where voice is off', async () => {
    const a = await emit(arjun, 'call:start', { room_id: 11 });
    assert.equal(a.ok, false);
  });

  teacher.close(); arjun.close();
  await srv.stop();
  await conn.end();
  laravel.close();

  for (const r of results) console.log(r[0] === 'PASS' ? '✓' : '✗', r[1], r[2] ? `→ ${r[2]}` : '');
  const failed = results.filter((r) => r[0] === 'FAIL').length;
  console.log(`\n${results.length - failed}/${results.length} passed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
