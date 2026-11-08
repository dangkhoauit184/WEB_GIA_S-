const path = require('path');
const fs = require('fs');
const dotenv = require('dotenv');

const serverEnvPath = path.resolve(__dirname, '../../../config/server.env');
const fallbackEnvPath = path.resolve(__dirname, '../../../config/server.env.example');

if (fs.existsSync(serverEnvPath)) {
  dotenv.config({ path: serverEnvPath });
} else if (fs.existsSync(fallbackEnvPath)) {
  dotenv.config({ path: fallbackEnvPath });
}

module.exports = {
  env: process.env.NODE_ENV || 'development',
  host: process.env.HOST || '127.0.0.1',
  port: Number(process.env.PORT) || 5000,
  jwt: {
    secret: process.env.JWT_SECRET || 'educenter_dev_secret_key_2026',
    expiresIn: process.env.JWT_EXPIRES_IN || '8h',
  },
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT) || 3306,
    database: process.env.DB_NAME || 'trung_tam_gia_su',
    user: process.env.DB_USER || 'educenter_api',
    password: process.env.DB_PASSWORD || '',
    charset: process.env.DB_CHARSET || 'utf8mb4',
    timezone: process.env.DB_TIMEZONE || '+07:00',
  },
};
