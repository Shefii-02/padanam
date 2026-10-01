/**
 * SFU adapter placeholder for 1:n and n:n (live classes, large group voice rooms).
 * Recommended: LiveKit (self-hosted or cloud). Implement joinInfo() to return { url, token } using the LiveKit
 * server SDK; the Flutter app then connects with livekit_client. Until configured, returns null and
 * groups larger than the mesh limit should keep calls turned off.
 */
async function joinInfo(/* callId, user, role */) {
  return null;
}

module.exports = { joinInfo };
