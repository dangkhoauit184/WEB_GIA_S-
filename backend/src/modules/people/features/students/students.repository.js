const { pool } = require('../../../../config/database.config');

const BASE_STUDENT_SELECT = `
  SELECT
    hv.*,
    cl.ten AS ten_cap_lop,
    kv.ten AS ten_khu_vuc,
    u.hoten AS ten_phu_huynh,
    u.sdt AS sdt_phu_huynh,
    u.email AS email_phu_huynh
  FROM hoc_vien hv
  JOIN users u ON u.user_id = hv.phu_huynh_user_id
  LEFT JOIN cap_lop cl ON cl.id = hv.cap_lop_id
  LEFT JOIN khu_vuc kv ON kv.id = hv.khu_vuc_id
  WHERE hv.deleted_at IS NULL
`;

async function findAllStudents({ q = '', phuHuynhUserId = null, capLopId = null, trangThai = null } = {}) {
  let sql = BASE_STUDENT_SELECT;
  const params = [];

  if (q) {
    sql += ' AND (hv.ho_ten LIKE ? OR hv.ma_hv LIKE ? OR u.hoten LIKE ? OR u.sdt LIKE ?)';
    const like = `%${q}%`;
    params.push(like, like, like, like);
  }

  if (phuHuynhUserId) {
    sql += ' AND hv.phu_huynh_user_id = ?';
    params.push(Number(phuHuynhUserId));
  }

  if (capLopId) {
    sql += ' AND hv.cap_lop_id = ?';
    params.push(Number(capLopId));
  }

  if (trangThai) {
    sql += ' AND hv.trang_thai = ?';
    params.push(trangThai);
  }

  sql += ' ORDER BY hv.id DESC';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function findStudentById(id) {
  const [rows] = await pool.query(`${BASE_STUDENT_SELECT} AND hv.id = ? LIMIT 1`, [id]);
  return rows[0] || null;
}

async function findClassesByStudentId(hocVienId) {
  const [rows] = await pool.query(
    `SELECT
       lhv.id AS ghiDanhId,
       lhv.lop_id AS lopId,
       l.ma_lop AS maLop,
       l.ten_lop AS tenLop,
       l.loai_lop AS loaiLop,
       m.ten AS tenMonHoc,
       lhv.ngay_vao AS ngayVao,
       lhv.ngay_roi AS ngayRoi,
       lhv.trang_thai AS trangThaiGhiDanh
     FROM lop_hoc_vien lhv
     JOIN lop_hoc l ON l.id = lhv.lop_id
     JOIN mon_hoc m ON m.id = l.mon_hoc_id
     WHERE lhv.hoc_vien_id = ? AND l.deleted_at IS NULL
     ORDER BY lhv.id DESC`,
    [hocVienId]
  );
  return rows;
}

async function createStudent(data) {
  const [res] = await pool.query(
    `INSERT INTO hoc_vien (
       phu_huynh_user_id, ma_hv, ho_ten, ngay_sinh, gioi_tinh,
       truong, cap_lop_id, dia_chi, khu_vuc_id, ghi_chu, trang_thai
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      data.phuHuynhUserId,
      data.maHv,
      data.hoTen,
      data.ngaySinh,
      data.gioiTinh,
      data.truong,
      data.capLopId,
      data.diaChi,
      data.khuVucId,
      data.ghiChu,
      data.trangThai,
    ]
  );
  return res.insertId;
}

async function updateStudent(id, fields) {
  const columnMap = {
    phuHuynhUserId: 'phu_huynh_user_id',
    hoTen: 'ho_ten',
    ngaySinh: 'ngay_sinh',
    gioiTinh: 'gioi_tinh',
    truong: 'truong',
    capLopId: 'cap_lop_id',
    diaChi: 'dia_chi',
    khuVucId: 'khu_vuc_id',
    ghiChu: 'ghi_chu',
    trangThai: 'trang_thai',
  };

  const updates = [];
  const params = [];

  for (const [key, col] of Object.entries(columnMap)) {
    if (fields[key] !== undefined) {
      updates.push(`${col} = ?`);
      params.push(fields[key]);
    }
  }

  if (updates.length) {
    params.push(id);
    await pool.query(`UPDATE hoc_vien SET ${updates.join(', ')} WHERE id = ? AND deleted_at IS NULL`, params);
  }
}

async function softDeleteStudent(id) {
  await pool.query('UPDATE hoc_vien SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL', [id]);
}

module.exports = {
  findAllStudents,
  findStudentById,
  findClassesByStudentId,
  createStudent,
  updateStudent,
  softDeleteStudent,
};
