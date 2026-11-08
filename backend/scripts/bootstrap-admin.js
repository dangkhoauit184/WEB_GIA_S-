const { pool } = require('../src/config/database.config');
const { bootstrapAdmin } = require('../src/modules/administration/features/auth/auth.service');

async function main() {
  const username = process.env.ADMIN_USERNAME || 'admin';
  const password = process.env.ADMIN_PASSWORD || 'Admin@123456';
  const hoTen = process.env.ADMIN_NAME || 'Quản Trị Viên';
  const email = process.env.ADMIN_EMAIL || 'admin@educenter.local';
  const sdt = process.env.ADMIN_PHONE || '0900000001';

  try {
    const result = await bootstrapAdmin({ username, password, hoTen, email, sdt });
    console.log(
      `[BOOTSTRAP ADMIN] Thành công (${result.created ? 'tạo mới' : 'cập nhật'}): username='${result.username}', accountId=${result.accountId}, userId=${result.userId}`
    );
  } finally {
    await pool.end();
  }
}

main().catch((err) => {
  console.error('[BOOTSTRAP ADMIN] Lỗi:', err.message);
  process.exit(1);
});
