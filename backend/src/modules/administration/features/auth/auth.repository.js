const { pool, withTransaction } = require('../../../../config/database.config');

const BASE_ACCOUNT_SELECT = `
  SELECT
    a.account_id,
    a.user_id,
    a.group_id,
    a.username,
    a.password_hash,
    a.status,
    g.group_name,
    u.hoten,
    u.sdt,
    u.email,
    u.ngaysinh,
    u.diachi,
    u.nghe_nghiep,
    u.khu_vuc_id,
    nv.id AS nhan_vien_id,
    nv.ma_nv,
    nv.chuc_vu,
    nv.co_so_id,
    ns.id AS nhan_su_id,
    ns.ma_ns,
    ns.phan_loai
  FROM account a
  JOIN users u ON u.user_id = a.user_id
  JOIN role_group g ON g.group_id = a.group_id
  LEFT JOIN nhan_vien nv ON nv.user_id = a.user_id
  LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id = a.user_id AND ns.deleted_at IS NULL
  WHERE a.is_deleted = 0 AND u.is_deleted = 0 AND g.is_deleted = 0
`;

async function findAccountByUsername(username) {
  const [rows] = await pool.query(
    `${BASE_ACCOUNT_SELECT} AND a.username = ? LIMIT 1`,
    [username]
  );
  return rows[0] || null;
}

async function findAccountById(accountId) {
  const [rows] = await pool.query(
    `${BASE_ACCOUNT_SELECT} AND a.account_id = ? LIMIT 1`,
    [accountId]
  );
  return rows[0] || null;
}

async function findPermissionsByGroupId(groupId) {
  const [rows] = await pool.query(
    `SELECT
       f.function_id AS functionId,
       f.name_function AS functionName,
       rgf.view,
       rgf.\`add\`,
       rgf.edit,
       rgf.\`delete\`,
       rgf.download
     FROM role_group_function rgf
     JOIN \`function\` f ON f.function_id = rgf.function_id
     WHERE rgf.group_id = ? AND rgf.is_deleted = 0 AND f.is_deleted = 0
     ORDER BY f.function_id ASC`,
    [groupId]
  );
  return rows.map((r) => ({
    functionId: r.functionId,
    functionName: r.functionName,
    view: Boolean(r.view),
    add: Boolean(r.add),
    edit: Boolean(r.edit),
    delete: Boolean(r.delete),
    download: Boolean(r.download),
  }));
}

async function updatePasswordHash(accountId, passwordHash) {
  await pool.query(
    "UPDATE account SET password_hash = ?, status = 'active' WHERE account_id = ?",
    [passwordHash, accountId]
  );
}

async function writeAuditLog({ userId, hanhDong, bang, banGhiId, duLieuCu = null, duLieuMoi = null, ip = null }) {
  await pool.query(
    `INSERT INTO audit_log (user_id, hanh_dong, bang, ban_ghi_id, du_lieu_cu, du_lieu_moi, ip)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [
      userId || null,
      hanhDong,
      bang || null,
      banGhiId || null,
      duLieuCu ? JSON.stringify(duLieuCu) : null,
      duLieuMoi ? JSON.stringify(duLieuMoi) : null,
      ip || null,
    ]
  );
}

async function upsertInitialAdmin({ username, passwordHash, hoTen, email, sdt }) {
  return withTransaction(async (conn) => {
    const [existingAcc] = await conn.query(
      'SELECT account_id, user_id, status FROM account WHERE username = ? LIMIT 1 FOR UPDATE',
      [username]
    );

    const [adminGroupRows] = await conn.query(
      "SELECT group_id FROM role_group WHERE group_name = 'Admin' AND is_deleted = 0 LIMIT 1"
    );
    const adminGroupId = adminGroupRows.length ? adminGroupRows[0].group_id : 1;

    if (existingAcc.length) {
      const acc = existingAcc[0];
      await conn.query(
        "UPDATE account SET password_hash = ?, group_id = ?, status = 'active', is_deleted = 0 WHERE account_id = ?",
        [passwordHash, adminGroupId, acc.account_id]
      );
      return { accountId: acc.account_id, userId: acc.user_id, created: false };
    }

    const [userResult] = await conn.query(
      `INSERT INTO users (hoten, sdt, email, nghe_nghiep)
       VALUES (?, ?, ?, 'Quản trị hệ thống')`,
      [hoTen, sdt, email]
    );
    const userId = userResult.insertId;

    const [accResult] = await conn.query(
      `INSERT INTO account (user_id, group_id, username, password_hash, status)
       VALUES (?, ?, ?, ?, 'active')`,
      [userId, adminGroupId, username, passwordHash]
    );

    return { accountId: accResult.insertId, userId, created: true };
  });
}

module.exports = {
  findAccountByUsername,
  findAccountById,
  findPermissionsByGroupId,
  updatePasswordHash,
  writeAuditLog,
  upsertInitialAdmin,
};
