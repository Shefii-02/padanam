const mysql = require('mysql2/promise');
const config = require('./config');

const pool = mysql.createPool({
  ...config.db,
  waitForConnections: true,
  connectionLimit: 20,
  charset: 'utf8mb4',
  timezone: 'Z',
  dateStrings: false,
});

/** rows */
async function q(sql, params = []) {
  const [rows] = await pool.query(sql, params);
  return rows;
}

/** first row or null */
async function one(sql, params = []) {
  const rows = await q(sql, params);
  return rows[0] || null;
}

module.exports = { pool, q, one };
