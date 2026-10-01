const config = require('./config');

/** Ask Laravel to push FCM to members who are offline (fire and forget). */
async function pushOffline(payload) {
  if (!config.internalKey) return;
  try {
    await fetch(`${config.laravelUrl}/api/v1/internal/chat/notify`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Internal-Key': config.internalKey },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(4000),
    });
  } catch (e) {
    console.warn('[notify] push failed:', e.message);
  }
}

module.exports = { pushOffline };
