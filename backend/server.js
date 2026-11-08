const app = require('./src/app');
const config = require('./src/config/app.config');
const { pool, checkDatabaseConnection } = require('./src/config/database.config');

async function startServer() {
  try {
    await checkDatabaseConnection();
    console.log(`[DB] Đã kết nối MySQL (${config.db.host}:${config.db.port}/${config.db.database})`);
  } catch (err) {
    console.warn(`[DB] Cảnh báo chưa kết nối được MySQL: ${err.message}`);
  }

  const server = app.listen(config.port, config.host, () => {
    console.log(`[SERVER] EduCenter ERP Backend đang chạy tại http://${config.host}:${config.port}`);
  });

  const shutdown = async () => {
    console.log('\n[SERVER] Đang đóng kết nối...');
    server.close(async () => {
      try {
        await pool.end();
      } catch (_) {
        // Ignore pool close errors during shutdown
      }
      process.exit(0);
    });
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);

  return server;
}

if (require.main === module) {
  startServer();
}

module.exports = { startServer };
