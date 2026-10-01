const jwt = require('jsonwebtoken');
const config = require('./config');
const db = require('./db');

const PANEL_ROLES = ['super_admin', 'admin', 'staff', 'teacher'];

/**
 * Socket.IO middleware. The Flutter app / React panel connect with the same JWT they use for the Laravel API:
 *   io(url, { auth: { token } })
 */
async function authenticate(socket, next) {
  try {
    const raw = socket.handshake.auth?.token || (socket.handshake.headers.authorization || '').replace(/^Bearer\s+/i, '');
    if (!raw) return next(new Error('unauthorized'));
    const claims = jwt.verify(raw, config.jwtSecret, { algorithms: ['HS256'], clockTolerance: 5 });
    const userId = parseInt(claims.sub, 10);
    const user = await db.one('SELECT id, name, avatar, status FROM users WHERE id = ? AND deleted_at IS NULL', [userId]);
    if (!user || user.status === 'blocked') return next(new Error('unauthorized'));
    const roles = (await db.q(
      `SELECT r.name FROM model_has_roles m JOIN roles r ON r.id = m.role_id WHERE m.model_type IN ('user','App\\\\Models\\\\User') AND m.model_id = ?`,
      [userId],
    )).map((r) => r.name);
    socket.data.user = { id: user.id, name: user.name || 'User', avatar: user.avatar, roles, isPanel: roles.some((r) => PANEL_ROLES.includes(r)) };
    return next();
  } catch (e) {
    return next(new Error('unauthorized'));
  }
}

function verifyInternal(req, res, next) {
  const key = req.get('X-Internal-Key') || '';
  if (!config.internalKey || key.length !== config.internalKey.length
    || !require('crypto').timingSafeEqual(Buffer.from(key), Buffer.from(config.internalKey))) {
    return res.status(401).json({ ok: false });
  }
  return next();
}

module.exports = { authenticate, verifyInternal, PANEL_ROLES };
