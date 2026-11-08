const { pool } = require('../../../../config/database.config');

async function findWeeklySchedules({ lopId = null } = {}) {
  let sql = `
    SELECT
      lh.*,
      l.ma_lop,
      l.ten_lop
    FROM lich_hoc lh
    JOIN lop_hoc l ON l.id = lh.lop_id
    WHERE l.deleted_at IS NULL
  `;
  const params = [];

  if (lopId) {
    sql += ' AND lh.lop_id = ?';
    params.push(Number(lopId));
  }

  sql += ' ORDER BY lh.lop_id DESC, lh.thu ASC, lh.gio_bat_dau ASC';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function createWeeklySchedule(data) {
  const [res] = await pool.query(
    `INSERT INTO lich_hoc (lop_id, thu, gio_bat_dau, gio_ket_thuc, phong_hoc, hieu_luc_tu, hieu_luc_den, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      data.lopId,
      data.thu,
      data.gioBatDau,
      data.gioKetThuc,
      data.phongHoc,
      data.hieuLucTu,
      data.hieuLucDen,
      data.isActive,
    ]
  );
  const [rows] = await pool.query(
    `SELECT lh.*, l.ma_lop, l.ten_lop
     FROM lich_hoc lh
     JOIN lop_hoc l ON l.id = lh.lop_id
     WHERE lh.id = ? LIMIT 1`,
    [res.insertId]
  );
  return rows[0] || null;
}

async function findSessions({ lopId = null, tuNgay = null, denNgay = null, userId = null, trangThai = null } = {}) {
  let sql = `
    SELECT
      b.*,
      l.ma_lop,
      l.ten_lop,
      pc_main.user_id AS day_chinh_user_id,
      u_main.hoten AS ten_nguoi_day_chinh
    FROM buoi_hoc b
    JOIN lop_hoc l ON l.id = b.lop_id
    LEFT JOIN phan_cong_buoi_hoc pc_main
      ON pc_main.buoi_hoc_id = b.id AND pc_main.vai_tro = 'day_chinh' AND pc_main.trang_thai <> 'da_go'
    LEFT JOIN users u_main ON u_main.user_id = pc_main.user_id
    WHERE l.deleted_at IS NULL
  `;
  const params = [];

  if (lopId) {
    sql += ' AND b.lop_id = ?';
    params.push(Number(lopId));
  }
  if (tuNgay) {
    sql += ' AND b.ngay >= ?';
    params.push(tuNgay);
  }
  if (denNgay) {
    sql += ' AND b.ngay <= ?';
    params.push(denNgay);
  }
  if (trangThai) {
    sql += ' AND b.trang_thai = ?';
    params.push(trangThai);
  }
  if (userId) {
    sql += ` AND EXISTS (
      SELECT 1 FROM phan_cong_buoi_hoc pc
      WHERE pc.buoi_hoc_id = b.id AND pc.user_id = ? AND pc.trang_thai <> 'da_go'
    )`;
    params.push(Number(userId));
  }

  sql += ' ORDER BY b.ngay ASC, b.gio_bat_dau ASC, b.id ASC';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function generateSessionsByProcedure({ lopId, tuNgay, denNgay }) {
  await pool.query('CALL sp_sinh_buoi_hoc(?, ?, ?)', [lopId, tuNgay, denNgay]);
  return findSessions({ lopId, tuNgay, denNgay });
}

async function assignPersonnelToSession(buoiHocId, { userId, vaiTro, donGia, cachTinhLuong }, assignedByUserId) {
  await pool.query(
    `INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia, cach_tinh_luong, phan_cong_boi)
     VALUES (?, ?, ?, ?, ?, ?)`,
    [buoiHocId, userId, vaiTro, donGia, cachTinhLuong, assignedByUserId || null]
  );

  const [rows] = await pool.query(
    `SELECT
       pc.id,
       pc.buoi_hoc_id AS buoiHocId,
       pc.user_id AS userId,
       u.hoten AS hoTen,
       pc.vai_tro AS vaiTro,
       pc.trang_thai AS trangThai,
       pc.don_gia AS donGia,
       pc.cach_tinh_luong AS cachTinhLuong
     FROM phan_cong_buoi_hoc pc
     JOIN users u ON u.user_id = pc.user_id
     WHERE pc.buoi_hoc_id = ? AND pc.trang_thai <> 'da_go'
     ORDER BY pc.id ASC`,
    [buoiHocId]
  );
  return rows;
}

async function updateSession(buoiHocId, fields, callerUserId) {
  const columnMap = {
    ngay: 'ngay',
    gioBatDau: 'gio_bat_dau',
    gioKetThuc: 'gio_ket_thuc',
    phongHoc: 'phong_hoc',
    coSoId: 'co_so_id',
    chuDeBaiHoc: 'chu_de_bai_hoc',
    noiDungBaiHoc: 'noi_dung_bai_hoc',
    fileBaiGiangUrl: 'file_bai_giang_url',
    trangThai: 'trang_thai',
    huyBoi: 'huy_boi',
    lyDoHuyDoi: 'ly_do_huy_doi',
  };

  const updates = [];
  const params = [];

  for (const [key, col] of Object.entries(columnMap)) {
    if (fields[key] !== undefined) {
      updates.push(`${col} = ?`);
      params.push(fields[key]);
    }
  }

  if (fields.trangThai === 'huy' || fields.trangThai === 'doi') {
    updates.push('nguoi_huy_id = ?', 'huy_luc = NOW()');
    params.push(callerUserId || null);
  }

  if (updates.length) {
    params.push(buoiHocId);
    await pool.query(`UPDATE buoi_hoc SET ${updates.join(', ')} WHERE id = ?`, params);
  }

  const [rows] = await pool.query(
    `SELECT b.*, l.ma_lop, l.ten_lop
     FROM buoi_hoc b
     JOIN lop_hoc l ON l.id = b.lop_id
     WHERE b.id = ? LIMIT 1`,
    [buoiHocId]
  );
  return rows[0] || null;
}

module.exports = {
  findWeeklySchedules,
  createWeeklySchedule,
  findSessions,
  generateSessionsByProcedure,
  assignPersonnelToSession,
  updateSession,
};
