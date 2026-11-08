function mapWeeklyScheduleRow(row) {
  if (!row) return null;
  return {
    id: row.id,
    lopId: row.lop_id,
    maLop: row.ma_lop || null,
    tenLop: row.ten_lop || null,
    thu: Number(row.thu),
    gioBatDau: row.gio_bat_dau,
    gioKetThuc: row.gio_ket_thuc,
    phongHoc: row.phong_hoc,
    hieuLucTu: row.hieu_luc_tu,
    hieuLucDen: row.hieu_luc_den,
    isActive: Boolean(row.is_active),
  };
}

function mapSessionRow(row, assignments = undefined) {
  if (!row) return null;
  const mapped = {
    id: row.id,
    lopId: row.lop_id,
    maLop: row.ma_lop || null,
    tenLop: row.ten_lop || null,
    lichHocId: row.lich_hoc_id,
    loai: row.loai,
    buoiGocId: row.buoi_goc_id,
    ngay: row.ngay,
    gioBatDau: row.gio_bat_dau,
    gioKetThuc: row.gio_ket_thuc,
    coSoId: row.co_so_id,
    phongHoc: row.phong_hoc,
    chuDeBaiHoc: row.chu_de_bai_hoc,
    noiDungBaiHoc: row.noi_dung_bai_hoc,
    fileBaiGiangUrl: row.file_bai_giang_url,
    trangThai: row.trang_thai,
    huyBoi: row.huy_boi,
    lyDoHuyDoi: row.ly_do_huy_doi,
    nguoiHuyId: row.nguoi_huy_id,
    huyLuc: row.huy_luc,
    nguoiDayChinhUserId: row.day_chinh_user_id || null,
    tenNguoiDayChinh: row.ten_nguoi_day_chinh || null,
  };
  if (assignments !== undefined) {
    mapped.phanCongs = assignments;
  }
  return mapped;
}

module.exports = {
  mapWeeklyScheduleRow,
  mapSessionRow,
};
