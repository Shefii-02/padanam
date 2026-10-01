/** Mirrors ChatService::canSend + room settings in Laravel. */
const DEFAULT_SETTINGS = {
  who_can_send: 'all', send_media: true, send_links: false, members_can_invite: true, slow_mode_sec: 0, voice_enabled: false,
};
const STAFF_ROLES = ['owner', 'admin', 'moderator'];
const URL_RE = /(https?:\/\/|www\.)\S+|\b[a-z0-9-]+\.(com|in|net|org|io|app|me|ly|link)\b/i;

function settingsOf(room) {
  let s = room.settings;
  if (typeof s === 'string') { try { s = JSON.parse(s); } catch { s = {}; } }
  return { ...DEFAULT_SETTINGS, ...(s || {}) };
}

/** @returns {string|null} reason when NOT allowed */
function sendBlockReason(room, member, message) {
  if (!member || member.left_at) return 'You are not in this chat.';
  if (member.muted_until && new Date(member.muted_until) > new Date()) return 'You are muted in this group.';
  const s = settingsOf(room);
  const isRoomStaff = STAFF_ROLES.includes(member.role);
  if (member.can_send === 0) return 'Sending is turned off for you in this group.';
  if (member.can_send !== 1 && s.who_can_send === 'admins' && !isRoomStaff) return 'Only admins can send messages here.';
  if (['image', 'file', 'audio'].includes(message.type) && !s.send_media && !isRoomStaff) return 'Photos and files are turned off in this group.';
  if (message.type === 'text' && !s.send_links && !isRoomStaff && room.type !== 'direct' && URL_RE.test(message.body || '')) return 'Links are not allowed in this group.';
  return null;
}

module.exports = { DEFAULT_SETTINGS, STAFF_ROLES, settingsOf, sendBlockReason };
