const { pool, withTransaction } = require('../../../../config/database.config');

const BASE_CLASS_SELECT = `
  SELECT
    l.*,
    m.ten AS ten_mon_hoc,
    cl.ten AS ten_cap_lop,
    cs.ten_co_so,
    u.hoten AS ten_nguoi_day_chinh,
    (
      SELECT COUNT(*)
      FROM lop_hoc_vien lhv
      WHERE lhv.lop_id = l.id AND lhv.trang_thai IN ('dang_hoc', 'hoc_thu')
    ) AS si_so_hien_tai
  FROM lop_hoc l
  JOIN mon_hoc m ON m.id = l.mon_hoc_id
  JOIN cap_lop cl ON cl.id = l.cap_lop_id
  JOIN nhan_su_chuyen_mon ns ON ns.id = l.nguoi_day_chinh_id
  JOIN users u ON u.user_id = ns.user_id
  LEFT JOIN co_so cs ON cs.id = l.co_so_id
  WHERE l.deleted_at IS NULL
`;

async function findAllClasses({ q = '', loaiLop = null, trangThai = null, monHocId = null, nguoiDayChinhId = null } = {}) {
  let sql = BASE_CLASS_SELECT;
  const params = [];

  if (q) {
    sql += ' AND (l.ma_lop LIKE ? OR l.ten_lop LIKE ? OR u.hoten LIKE ?)';
    const like = `%${q}%`;
    params.push(like, like, like);
  }
  if (loaiLop) {
    sql += ' AND l.loai_lop = ?';
    params.push(loaiLop);
  }
  if (trangThai) {
    sql += ' AND l.trang_thai = ?';
    params.push(trangThai);
  }
  if (monHocId) {
    sql += ' AND l.mon_hoc_id = ?';
    params.push(Number(monHocId));
  }
  if (nguoiDayChinhId) {
    sql += ' AND l.nguoi_day_chinh_id = ?';
    params.push(Number(nguoiDayChinhId));
  }

  sql += ' ORDER BY l.id DESC';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function findClassById(id) {
  const [rows] = await pool.query(`${BASE_CLASS_SELECT} AND l.id = ? LIMIT 1`, [id]);
  return rows[0] || null;
}

async function findEnrollmentsByClassId(lopId) {
  const [rows] = await pool.query(
    `SELECT
       lhv.id,
       lhv.lop_id AS lopId,
       lhv.hoc_vien_id AS hocVienId,
       hv.ma_hv AS maHv,
       hv.ho_ten AS hoTen,
       hv.ngay_sinh AS ngaySinh,
       u.hoten AS tenPhuHuynh,
       u.sdt AS sdtPhuHuynh,
       lhv.ngay_vao AS ngayVao,
       lhv.ngay_roi AS ngayRoi,
       lhv.trang_thai AS trangThai
     FROM lop_hoc_vien lhv
     JOIN hoc_vien hv ON hv.id = lhv.hoc_vien_id
     JOIN users u ON u.user_id = hv.phu_huynh_user_id
     WHERE lhv.lop_id = ?
     ORDER BY lhv.id ASC`,
    [lopId]
  );
  return rows;
}

async function findSchedulesByClassId(lopId) {
  const [rows] = await pool.query(
    `SELECT
       id,
       lop_id AS lopId,
       thu,
       gio_bat_dau AS gioBatDau,
       gio_ket_thuc AS gioKetThuc,
       phong_hoc AS phongHoc,
       hieu_luc_tu AS hieuLucTu,
       hieu_luc_den AS hieuLucDen,
       is_active AS isActive
     FROM lich_hoc
     WHERE lop_id = ?
     ORDER BY thu ASC, gio_bat_dau ASC`,
    [lopId]
  );
  return rows;
}

async function resolveOrCreateStaffId(conn, { nhanVienId, userId }) {
  if (nhanVienId) return nhanVienId;

  if (userId) {
    const [nvRows] = await conn.query('SELECT id FROM nhan_vien WHERE user_id = ? LIMIT 1', [userId]);
    if (nvRows.length) return nvRows[0].id;
  }

  const [anyNv] = await conn.query('SELECT id FROM nhan_vien ORDER BY id ASC LIMIT 1');
  if (anyNv.length) return anyNv[0].id;

  const targetUserId = userId || 1;
  const [ins] = await conn.query(
    `INSERT INTO nhan_vien (user_id, ma_nv, chuc_vu, ngay_vao_lam, trang_thai)
     VALUES (?, ?, 'Quản lý vận hành', CURDATE(), 'dang_lam')`,
    [targetUserId, `NV-AUTO-${targetUserId}`]
  );
  return ins.insertId;
}

async function createClass(data, callerUserId) {
  return withTransaction(async (conn) => {
    const nhanVienId = await resolveOrCreateStaffId(conn, {
      nhanVienId: data.nhanVienId,
      userId: callerUserId,
    });

    const [res] = await conn.query(
      `INSERT INTO lop_hoc (
         ma_lop, ten_lop, loai_lop, de_xuat_id, mon_hoc_id, cap_lop_id,
         nguoi_day_chinh_id, co_so_id, phong_hoc, hinh_thuc, dia_diem, link_hoc,
         khu_vuc_id, si_so_toi_thieu, si_so_toi_da, cach_thu_phi,
         hoc_phi_buoi, hoc_phi_thang, hoc_phi_khoa, muc_tra_chinh, muc_tra_tro_giang,
         cach_tinh_luong, so_buoi_du_kien, ngay_bat_dau, ngay_ket_thuc, trang_thai, nhan_vien_id
       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        data.maLop,
        data.tenLop,
        data.loaiLop,
        data.deXuatId,
        data.monHocId,
        data.capLopId,
        data.nguoiDayChinhId,
        data.coSoId,
        data.phongHoc,
        data.hinhThuc,
        data.diaDiem,
        data.linkHoc,
        data.khuVucId,
        data.siSoToiThieu,
        data.siSoToiDa,
        data.cachThuPhi,
        data.hocPhiBuoi,
        data.hocPhiThang,
        data.hocPhiKhoa,
        data.mucTraChinh,
        data.mucTraTroGiang,
        data.cachTinhLuong,
        data.soBuoiDuKien,
        data.ngayBatDau,
        data.ngayKetThuc,
        data.trangThai,
        nhanVienId,
      ]
    );
    return res.insertId;
  });
}

async function updateClass(id, fields) {
  const columnMap = {
    tenLop: 'ten_lop',
    loaiLop: 'loai_lop',
    monHocId: 'mon_hoc_id',
    capLopId: 'cap_lop_id',
    nguoiDayChinhId: 'nguoi_day_chinh_id',
    coSoId: 'co_so_id',
    phongHoc: 'phong_hoc',
    hinhThuc: 'hinh_thuc',
    diaDiem: 'dia_diem',
    linkHoc: 'link_hoc',
    khuVucId: 'khu_vuc_id',
    siSoToiThieu: 'si_so_toi_thieu',
    siSoToiDa: 'si_so_toi_da',
    cachThuPhi: 'cach_thu_phi',
    hocPhiBuoi: 'hoc_phi_buoi',
    hocPhiThang: 'hoc_phi_thang',
    hocPhiKhoa: 'hoc_phi_khoa',
    mucTraChinh: 'muc_tra_chinh',
    mucTraTroGiang: 'muc_tra_tro_giang',
    cachTinhLuong: 'cach_tinh_luong',
    soBuoiDuKien: 'so_buoi_du_kien',
    ngayBatDau: 'ngay_bat_dau',
    ngayKetThuc: 'ngay_ket_thuc',
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
    await pool.query(`UPDATE lop_hoc SET ${updates.join(', ')} WHERE id = ? AND deleted_at IS NULL`, params);
  }
}

async function enrollStudent(lopId, { hocVienId, ngayVao, ngayRoi, trangThai }) {
  const [res] = await pool.query(
    `INSERT INTO lop_hoc_vien (lop_id, hoc_vien_id, ngay_vao, ngay_roi, trang_thai)
     VALUES (?, ?, ?, ?, ?)`,
    [lopId, hocVienId, ngayVao, ngayRoi, trangThai]
  );
  return res.insertId;
}

async function updateEnrollment(lopId, enrollmentId, { trangThai, ngayRoi }) {
  const updates = [];
  const params = [];
  if (trangThai !== undefined) {
    updates.push('trang_thai = ?');
    params.push(trangThai);
  }
  if (ngayRoi !== undefined) {
    updates.push('ngay_roi = ?');
    params.push(ngayRoi);
  }
  if (updates.length) {
    params.push(enrollmentId, lopId);
    await pool.query(`UPDATE lop_hoc_vien SET ${updates.join(', ')} WHERE id = ? AND lop_id = ?`, params);
  }
}

module.exports = {
  findAllClasses,
  findClassById,
  findEnrollmentsByClassId,
  findSchedulesByClassId,
  createClass,
  updateClass,
  enrollStudent,
  updateEnrollment,
};
