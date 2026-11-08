const mysql = require('mysql2/promise');
const config = require('./app.config');

const pool = mysql.createPool({
  host: config.db.host,
  port: config.db.port,
  database: config.db.database,
  user: config.db.user,
  password: config.db.password,
  charset: config.db.charset,
  timezone: config.db.timezone,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  decimalNumbers: true,
});

// Đảm bảo mọi connection trong pool đều thiết lập time_zone = '+07:00' và READ COMMITTED
pool.on('connection', (conn) => {
  conn.query("SET SESSION time_zone = '+07:00'");
  conn.query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
});

async function checkDatabaseConnection() {
  const conn = await pool.getConnection();
  try {
    await conn.ping();
    const [[sessionInfo]] = await conn.query(
      'SELECT @@session.time_zone AS timeZone, @@session.transaction_isolation AS isolationLevel'
    );
    return sessionInfo;
  } finally {
    conn.release();
  }
}

async function withTransaction(callback) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const result = await callback(conn);
    await conn.commit();
    return result;
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

module.exports = {
  pool,
  checkDatabaseConnection,
  withTransaction,
};
