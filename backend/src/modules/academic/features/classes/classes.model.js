function mapClassRow(row, extras = {}) {
  if (!row) return null;
  const mapped = {
    id: row.id,
    maLop: row.ma_lop,
    tenLop: row.ten_lop,
    loaiLop: row.loai_lop,
    deXuatId: row.de_xuat_id,
    monHocId: row.mon_hoc_id,
    tenMonHoc: row.ten_mon_hoc || null,
    capLopId: row.cap_lop_id,
    tenCapLop: row.ten_cap_lop || null,
    nguoiDayChinhId: row.nguoi_day_chinh_id,
    tenNguoiDayChinh: row.ten_nguoi_day_chinh || null,
    coSoId: row.co_so_id,
    tenCoSo: row.ten_co_so || null,
    phongHoc: row.phong_hoc,
    hinhThuc: row.hinh_thuc,
    diaDiem: row.dia_diem,
    linkHoc: row.link_hoc,
    khuVucId: row.khu_vuc_id,
    siSoToiThieu: Number(row.si_so_toi_thieu),
    siSoToiDa: Number(row.si_so_toi_da),
    siSoHienTai: row.si_so_hien_tai !== undefined ? Number(row.si_so_hien_tai) : undefined,
    cachThuPhi: row.cach_thu_phi,
    hocPhiBuoi: Number(row.hoc_phi_buoi || 0),
    hocPhiThang: row.hoc_phi_thang !== null && row.hoc_phi_thang !== undefined ? Number(row.hoc_phi_thang) : null,
    hocPhiKhoa: Number(row.hoc_phi_khoa || 0),
    mucTraChinh: Number(row.muc_tra_chinh || 0),
    mucTraTroGiang: Number(row.muc_tra_tro_giang || 0),
    cachTinhLuong: row.cach_tinh_luong,
    soBuoiDuKien: row.so_buoi_du_kien !== null ? Number(row.so_buoi_du_kien) : null,
    ngayBatDau: row.ngay_bat_dau,
    ngayKetThuc: row.ngay_ket_thuc,
    trangThai: row.trang_thai,
    nhanVienId: row.nhan_vien_id,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };

  if (extras.hocViens !== undefined) mapped.hocViens = extras.hocViens;
  if (extras.lichHocs !== undefined) mapped.lichHocs = extras.lichHocs;

  return mapped;
}

module.exports = {
  mapClassRow,
};
