# Padanam Realtime (Node.js + Socket.IO)

Chat, presence, live-class events and WebRTC call signalling. Uses the **same MySQL database** as the Laravel API
and the **same JWT** (HS256, `JWT_SECRET`). Laravel stays the source of truth for groups, members and permissions;
this server delivers messages instantly and fans out Laravel events.

```
Flutter / React ──socket (JWT)──▶ Node :4000 ──▶ MySQL (chat_messages, chat_members…)
        ▲                            │  ▲
        │                            │  └── POST /internal/emit   (Laravel → Node: member added/removed, live started…)
        └── FCM push ◀── Laravel ◀───┘      POST /api/v1/internal/chat/notify (Node → Laravel: push to offline members)
```

## Run
```bash
npm install
cp .env.example .env      # JWT_SECRET + DB_* from Laravel .env, INTERNAL_KEY = Laravel REALTIME_INTERNAL_KEY
npm start                 # or: pm2 start src/index.js --name padanam-rt -i max  (needs REDIS_URL for >1 process)
npm test                  # end-to-end test against MySQL (+ Redis if REDIS_URL set) – 25 checks
```
Test: `DB_DATABASE=padanam_rt_test DB_USERNAME=... DB_PASSWORD=... npm test` (uses its own throwaway database).

Nginx:
```
location /socket.io/ { proxy_pass http://127.0.0.1:4000; proxy_http_version 1.1;
  proxy_set_header Upgrade $http_upgrade; proxy_set_header Connection "upgrade"; proxy_set_header Host $host; proxy_read_timeout 120s; }
```

## Client
```js
const socket = io('https://rt.padanam.app', { auth: { token: JWT }, transports: ['websocket'] });
socket.on('ready', ({ rooms, ice_servers }) => {});
socket.emit('message:send', { room_id, client_id: uuid(), type: 'text', body: 'Hi' }, (ack) => ack.ok ? … : toast(ack.message));
```
Files: upload with `POST /api/v1/app/chat/rooms/{id}/upload` (Laravel), then send `{type: 'image'|'file'|'audio', meta}`.
History & room list come from Laravel REST (`/app/chat/rooms`, `/app/chat/rooms/{id}/messages?before_id=`).

## Events
| Client → server | Payload | Ack |
|---|---|---|
| `message:send` | room_id, client_id, type (text/image/file/audio/poll), body, meta, reply_to_id | `{ok, message}` / `{ok:false, message}` |
| `message:edit` | id, body (own, 15 min) | `{ok}` |
| `message:delete` | id (own 1 h, or group admin/moderator, or staff) | `{ok}` |
| `message:read` | room_id, message_id | `{ok}` |
| `typing` | room_id, typing | – |
| `poll:vote` | message_id, option | `{ok, counts}` |
| `presence:query` | user_ids[] | `{online: {id: bool}}` |
| `room:subscribe` | room_id | `{ok}` |
| `call:start` / `call:join` / `call:signal` / `call:leave` / `call:decline` | see src/calls | |

| Server → client | |
|---|---|
| `ready` | my room ids + ICE servers |
| `message:new`, `message:edited`, `message:deleted`, `message:read`, `typing`, `poll:updated` | |
| `room:added`, `room:removed`, `room:updated`, `member:updated`, `member:left`, `join:requested` (admins) | |
| `live:started`, `live:ended` | to the batch group + every enrolled student online |
| `call:incoming`, `call:peer-joined`, `call:signal`, `call:peer-left`, `call:ended`, `call:declined` | |

## Rules enforced here (same as Laravel)
Member of the room · not muted · per-member send override · "only admins can send" (broadcast) · media on/off ·
links blocked for members in groups (allowed in direct chats and for admins) · slow mode · 20 messages / 10 s per user ·
duplicate `client_id` returns the original message (safe retries on bad networks) · blocked users can't connect.

## Voice & video (next phase)
1:1 and up to 4 people: peer-to-peer WebRTC – this server relays offer/answer/ICE (tested).
Bigger rooms (1:n class audio, n:n group voice): add an SFU – LiveKit recommended – in `src/calls/sfu.js`; the events stay the same.
Add a TURN server (coturn) in `.env` so calls connect on Jio/Airtel mobile networks.
