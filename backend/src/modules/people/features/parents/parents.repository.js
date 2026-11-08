const { pool, withTransaction } = require('../../../../config/database.config');

async function findAllParents({ q = '', khuVucId = null } = {}) {
  const params = [];
  let whereSql = `
    WHERE u.is_deleted = 0
      AND (
        g.group_name = 'PhuHuynh'
        OR EXISTS (SELECT 1 FROM hoc_vien hv WHERE hv.phu_huynh_user_id = u.user_id AND hv.deleted_at IS NULL)
      )
  `;

  if (q) {
    whereSql += ' AND (u.hoten LIKE ? OR u.sdt LIKE ? OR u.email LIKE ?)';
    const like = `%${q}%`;
    params.push(like, like, like);
  }

  if (khuVucId) {
    whereSql += ' AND u.khu_vuc_id = ?';
    params.push(Number(khuVucId));
  }

  const [rows] = await pool.query(
    `SELECT
       u.*,
       kv.ten AS ten_khu_vuc,
       a.account_id,
       a.username,
       a.status AS account_status,
       (SELECT COUNT(*) FROM hoc_vien hv WHERE hv.phu_huynh_user_id = u.user_id AND hv.deleted_at IS NULL) AS so_con
     FROM users u
     LEFT JOIN account a ON a.user_id = u.user_id AND a.is_deleted = 0
     LEFT JOIN role_group g ON g.group_id = a.group_id
     LEFT JOIN khu_vuc kv ON kv.id = u.khu_vuc_id
     ${whereSql}
     ORDER BY u.user_id DESC`,
    params
  );

  return rows;
}

async function findParentById(userId) {
  const [rows] = await pool.query(
    `SELECT
       u.*,
       kv.ten AS ten_khu_vuc,
       a.account_id,
       a.username,
       a.status AS account_status
     FROM users u
     LEFT JOIN account a ON a.user_id = u.user_id AND a.is_deleted = 0
     LEFT JOIN khu_vuc kv ON kv.id = u.khu_vuc_id
     WHERE u.user_id = ? AND u.is_deleted = 0
     LIMIT 1`,
    [userId]
  );
  return rows[0] || null;
}

async function findChildrenByParentId(userId) {
  const [rows] = await pool.query(
    `SELECT
       hv.id,
       hv.ma_hv AS maHv,
       hv.ho_ten AS hoTen,
       hv.ngay_sinh AS ngaySinh,
       hv.gioi_tinh AS gioiTinh,
       hv.truong,
       hv.cap_lop_id AS capLopId,
       cl.ten AS tenCapLop,
       hv.trang_thai AS trangThai
     FROM hoc_vien hv
     LEFT JOIN cap_lop cl ON cl.id = hv.cap_lop_id
     WHERE hv.phu_huynh_user_id = ? AND hv.deleted_at IS NULL
     ORDER BY hv.id ASC`,
    [userId]
  );
  return rows;
}

async function createParent({ hoTen, email, sdt, ngaySinh, diaChi, ngheNghiep, khuVucId, username, passwordHash }) {
  return withTransaction(async (conn) => {
    const [userRes] = await conn.query(
      `INSERT INTO users (hoten, sdt, email, ngaysinh, diachi, nghe_nghiep, khu_vuc_id)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [hoTen, sdt, email, ngaySinh, diaChi, ngheNghiep, khuVucId]
    );
    const userId = userRes.insertId;

    if (username && passwordHash) {
      const [groupRows] = await conn.query(
        "SELECT group_id FROM role_group WHERE group_name = 'PhuHuynh' AND is_deleted = 0 LIMIT 1"
      );
      const groupId = groupRows.length ? groupRows[0].group_id : 5;
      await conn.query(
        `INSERT INTO account (user_id, group_id, username, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')`,
        [userId, groupId, username, passwordHash]
      );
    }

    return userId;
  });
}

async function updateParent(userId, fields) {
  const updates = [];
  const params = [];

  const columnMap = {
    hoTen: 'hoten',
    email: 'email',
    sdt: 'sdt',
    ngaySinh: 'ngaysinh',
    diaChi: 'diachi',
    ngheNghiep: 'nghe_nghiep',
    khuVucId: 'khu_vuc_id',
  };

  for (const [key, col] of Object.entries(columnMap)) {
    if (fields[key] !== undefined) {
      updates.push(`${col} = ?`);
      params.push(fields[key]);
    }
  }

  if (updates.length) {
    params.push(userId);
    await pool.query(`UPDATE users SET ${updates.join(', ')} WHERE user_id = ? AND is_deleted = 0`, params);
  }
}

module.exports = {
  findAllParents,
  findParentById,
  findChildrenByParentId,
  createParent,
  updateParent,
};
