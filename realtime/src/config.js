require('dotenv').config();

const int = (v, d) => (Number.isFinite(parseInt(v, 10)) ? parseInt(v, 10) : d);

module.exports = {
  port: int(process.env.PORT, 4000),
  corsOrigins: (process.env.CORS_ORIGINS || '*').split(',').map((s) => s.trim()),
  jwtSecret: process.env.JWT_SECRET || '',
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: int(process.env.DB_PORT, 3306),
    database: process.env.DB_DATABASE || 'padanam',
    user: process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
  },
  redisUrl: process.env.REDIS_URL || '',
  internalKey: process.env.INTERNAL_KEY || '',
  laravelUrl: (process.env.LARAVEL_URL || 'http://127.0.0.1:8000').replace(/\/$/, ''),
  rtc: {
    iceServers: [
      ...(process.env.STUN_URLS ? [{ urls: process.env.STUN_URLS.split(',') }] : []),
      ...(process.env.TURN_URL
        ? [{ urls: process.env.TURN_URL.split(','), username: process.env.TURN_USERNAME, credential: process.env.TURN_PASSWORD }]
        : []),
    ],
  },
  limits: {
    rate: int(process.env.MSG_RATE_LIMIT, 20),
    windowSec: int(process.env.MSG_RATE_WINDOW_SEC, 10),
    maxLength: int(process.env.MAX_MESSAGE_LENGTH, 4000),
  },
};
