const { pool, withTransaction } = require('../../../../config/database.config');

const BASE_TEACHER_SELECT = `
  SELECT
    ns.*,
    ns.nghe_nghiep AS nghe_nghiep_ns,
    u.hoten,
    u.sdt,
    u.email,
    u.ngaysinh,
    u.diachi,
    a.account_id,
    a.username,
    a.status AS account_status
  FROM nhan_su_chuyen_mon ns
  JOIN users u ON u.user_id = ns.user_id
  LEFT JOIN account a ON a.user_id = ns.user_id AND a.is_deleted = 0
  WHERE ns.deleted_at IS NULL AND u.is_deleted = 0
`;

async function findAllTeachers({
  q = '',
  phanLoai = null,
  trangThai = null,
  monHocId = null,
  capLopId = null,
  khuVucId = null,
} = {}) {
  let sql = BASE_TEACHER_SELECT;
  const params = [];

  if (q) {
    sql += ' AND (u.hoten LIKE ? OR ns.ma_ns LIKE ? OR u.sdt LIKE ? OR u.email LIKE ?)';
    const like = `%${q}%`;
    params.push(like, like, like, like);
  }

  if (phanLoai) {
    sql += ' AND FIND_IN_SET(?, ns.phan_loai) > 0';
    params.push(phanLoai);
  }

  if (trangThai) {
    sql += ' AND ns.trang_thai = ?';
    params.push(trangThai);
  }

  if (monHocId) {
    sql += ` AND EXISTS (
      SELECT 1 FROM nhan_su_mon nsm
      WHERE nsm.nhan_su_id = ns.id
        AND nsm.mon_hoc_id = ?
        ${capLopId ? 'AND ? BETWEEN nsm.cap_lop_tu AND nsm.cap_lop_den' : ''}
    )`;
    params.push(Number(monHocId));
    if (capLopId) params.push(Number(capLopId));
  }

  if (khuVucId) {
    sql += ' AND EXISTS (SELECT 1 FROM nhan_su_khu_vuc nskv WHERE nskv.nhan_su_id = ns.id AND nskv.khu_vuc_id = ?)';
    params.push(Number(khuVucId));
  }

  sql += ' ORDER BY ns.id DESC';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function matchTeachersBR02({
  monHocId,
  capLopId,
  khuVucId = null,
  nganSachMax = null,
  thu = null,
  gioBatDau = null,
  gioKetThuc = null,
}) {
  const params = [Number(monHocId), Number(capLopId)];
  let extraSql = '';

  if (nganSachMax) {
    extraSql += ' AND nsm.hoc_phi_buoi_de_xuat <= ?';
    params.push(Number(nganSachMax));
  }

  if (khuVucId) {
    extraSql += ' AND EXISTS (SELECT 1 FROM nhan_su_khu_vuc kv WHERE kv.nhan_su_id = ns.id AND kv.khu_vuc_id = ?)';
    params.push(Number(khuVucId));
  }

  if (thu && gioBatDau && gioKetThuc) {
    extraSql += ` AND EXISTS (
      SELECT 1 FROM nhan_su_lich_ranh lr
      WHERE lr.nhan_su_id = ns.id
        AND lr.thu = ?
        AND lr.gio_bat_dau <= ?
        AND lr.gio_ket_thuc >= ?
    )`;
    params.push(Number(thu), gioBatDau, gioKetThuc);
  }

  const [rows] = await pool.query(
    `SELECT
       ns.*,
       ns.nghe_nghiep AS nghe_nghiep_ns,
       u.hoten,
       u.sdt,
       u.email,
       nsm.hoc_phi_buoi_de_xuat
     FROM nhan_su_chuyen_mon ns
     JOIN users u ON u.user_id = ns.user_id
     JOIN nhan_su_mon nsm ON nsm.nhan_su_id = ns.id
     WHERE ns.deleted_at IS NULL
       AND u.is_deleted = 0
       AND ns.trang_thai = 'hoat_dong'
       AND nsm.mon_hoc_id = ?
       AND ? BETWEEN nsm.cap_lop_tu AND nsm.cap_lop_den
       ${extraSql}
     ORDER BY nsm.hoc_phi_buoi_de_xuat ASC, ns.kinh_nghiem_nam DESC`,
    params
  );

  return rows;
}

async function findTeacherById(id) {
  const [rows] = await pool.query(`${BASE_TEACHER_SELECT} AND ns.id = ? LIMIT 1`, [id]);
  return rows[0] || null;
}

async function findTeacherExtras(nhanSuId) {
  const [monDays] = await pool.query(
    `SELECT
       nsm.id,
       nsm.mon_hoc_id AS monHocId,
       m.ma AS maMon,
       m.ten AS tenMon,
       nsm.cap_lop_tu AS capLopTu,
       nsm.cap_lop_den AS capLopDen,
       nsm.hoc_phi_buoi_de_xuat AS hocPhiBuoiDeXuat
     FROM nhan_su_mon nsm
     JOIN mon_hoc m ON m.id = nsm.mon_hoc_id
     WHERE nsm.nhan_su_id = ?
     ORDER BY nsm.id ASC`,
    [nhanSuId]
  );

  const [khuVucs] = await pool.query(
    `SELECT kv.id AS khuVucId, kv.ten AS tenKhuVuc, kv.thanh_pho AS thanhPho
     FROM nhan_su_khu_vuc nskv
     JOIN khu_vuc kv ON kv.id = nskv.khu_vuc_id
     WHERE nskv.nhan_su_id = ?
     ORDER BY kv.id ASC`,
    [nhanSuId]
  );

  const [lichRanhs] = await pool.query(
    `SELECT id, thu, gio_bat_dau AS gioBatDau, gio_ket_thuc AS gioKetThuc
     FROM nhan_su_lich_ranh
     WHERE nhan_su_id = ?
     ORDER BY thu ASC, gio_bat_dau ASC`,
    [nhanSuId]
  );

  return { monDays, khuVucs, lichRanhs };
}

async function syncTeacherRelations(conn, nhanSuId, { monDays, khuVucIds, lichRanhs }) {
  if (Array.isArray(monDays)) {
    await conn.query('DELETE FROM nhan_su_mon WHERE nhan_su_id = ?', [nhanSuId]);
    for (const item of monDays) {
      await conn.query(
        `INSERT INTO nhan_su_mon (nhan_su_id, mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat)
         VALUES (?, ?, ?, ?, ?)`,
        [
          nhanSuId,
          Number(item.monHocId),
          Number(item.capLopTu),
          Number(item.capLopDen),
          Number(item.hocPhiBuoiDeXuat || 0),
        ]
      );
    }
  }

  if (Array.isArray(khuVucIds)) {
    await conn.query('DELETE FROM nhan_su_khu_vuc WHERE nhan_su_id = ?', [nhanSuId]);
    for (const kvId of khuVucIds) {
      await conn.query('INSERT INTO nhan_su_khu_vuc (nhan_su_id, khu_vuc_id) VALUES (?, ?)', [
        nhanSuId,
        Number(kvId),
      ]);
    }
  }

  if (Array.isArray(lichRanhs)) {
    await conn.query('DELETE FROM nhan_su_lich_ranh WHERE nhan_su_id = ?', [nhanSuId]);
    for (const slot of lichRanhs) {
      await conn.query(
        'INSERT INTO nhan_su_lich_ranh (nhan_su_id, thu, gio_bat_dau, gio_ket_thuc) VALUES (?, ?, ?, ?)',
        [nhanSuId, Number(slot.thu), slot.gioBatDau, slot.gioKetThuc]
      );
    }
  }
}

async function createTeacher(data, passwordHash = null) {
  return withTransaction(async (conn) => {
    const [userRes] = await conn.query(
      `INSERT INTO users (hoten, sdt, email, ngaysinh, diachi, nghe_nghiep)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [data.hoTen, data.sdt, data.email, data.ngaySinh, data.diaChi, data.ngheNghiep]
    );
    const userId = userRes.insertId;

    if (data.username && passwordHash) {
      const isTaOnly = data.phanLoai === 'tro_giang';
      const targetGroup = isTaOnly ? 'TroGiang' : 'GiangVien';
      const [groupRows] = await conn.query(
        'SELECT group_id FROM role_group WHERE group_name = ? AND is_deleted = 0 LIMIT 1',
        [targetGroup]
      );
      const groupId = groupRows.length ? groupRows[0].group_id : isTaOnly ? 4 : 3;

      await conn.query(
        `INSERT INTO account (user_id, group_id, username, password_hash, status)
         VALUES (?, ?, ?, ?, 'active')`,
        [userId, groupId, data.username, passwordHash]
      );
    }

    const [nsRes] = await conn.query(
      `INSERT INTO nhan_su_chuyen_mon (
         user_id, ma_ns, phan_loai, gioi_tinh, cccd, nghe_nghiep,
         don_vi_cong_tac, gioi_thieu, kinh_nghiem_nam, hinh_thuc_day,
         don_gia_gio_chuan, ngan_hang, so_tai_khoan, chu_tai_khoan, trang_thai
       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        userId,
        data.maNs,
        data.phanLoai,
        data.gioiTinh,
        data.cccd,
        data.ngheNghiep,
        data.donViCongTac,
        data.gioiThieu,
        data.kinhNghiemNam,
        data.hinhThucDay,
        data.donGiaGioChuan,
        data.nganHang,
        data.soTaiKhoan,
        data.chuTaiKhoan,
        data.trangThai,
      ]
    );
    const nhanSuId = nsRes.insertId;

    await syncTeacherRelations(conn, nhanSuId, {
      monDays: data.monDays,
      khuVucIds: data.khuVucIds,
      lichRanhs: data.lichRanhs,
    });

    return nhanSuId;
  });
}

async function updateTeacher(id, userId, fields) {
  return withTransaction(async (conn) => {
    const userMap = {
      hoTen: 'hoten',
      email: 'email',
      sdt: 'sdt',
      ngaySinh: 'ngaysinh',
      diaChi: 'diachi',
    };
    const userUpdates = [];
    const userParams = [];

    for (const [key, col] of Object.entries(userMap)) {
      if (fields[key] !== undefined) {
        userUpdates.push(`${col} = ?`);
        userParams.push(fields[key]);
      }
    }

    if (userUpdates.length) {
      userParams.push(userId);
      await conn.query(`UPDATE users SET ${userUpdates.join(', ')} WHERE user_id = ?`, userParams);
    }

    const nsMap = {
      phanLoai: 'phan_loai',
      gioiTinh: 'gioi_tinh',
      cccd: 'cccd',
      ngheNghiep: 'nghe_nghiep',
      donViCongTac: 'don_vi_cong_tac',
      gioiThieu: 'gioi_thieu',
      kinhNghiemNam: 'kinh_nghiem_nam',
      hinhThucDay: 'hinh_thuc_day',
      donGiaGioChuan: 'don_gia_gio_chuan',
      nganHang: 'ngan_hang',
      soTaiKhoan: 'so_tai_khoan',
      chuTaiKhoan: 'chu_tai_khoan',
      trangThai: 'trang_thai',
    };

    const nsUpdates = [];
    const nsParams = [];
    for (const [key, col] of Object.entries(nsMap)) {
      if (fields[key] !== undefined) {
        nsUpdates.push(`${col} = ?`);
        nsParams.push(fields[key]);
      }
    }

    if (nsUpdates.length) {
      nsParams.push(id);
      await conn.query(
        `UPDATE nhan_su_chuyen_mon SET ${nsUpdates.join(', ')} WHERE id = ? AND deleted_at IS NULL`,
        nsParams
      );
    }

    await syncTeacherRelations(conn, id, {
      monDays: fields.monDays,
      khuVucIds: fields.khuVucIds,
      lichRanhs: fields.lichRanhs,
    });
  });
}

module.exports = {
  findAllTeachers,
  matchTeachersBR02,
  findTeacherById,
  findTeacherExtras,
  createTeacher,
  updateTeacher,
};
