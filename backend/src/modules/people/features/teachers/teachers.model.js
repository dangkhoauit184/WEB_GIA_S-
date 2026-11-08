function mapTeacherRow(row, extras = {}) {
  if (!row) return null;
  const mapped = {
    id: row.id,
    userId: row.user_id,
    maNs: row.ma_ns,
    phanLoai: row.phan_loai ? String(row.phan_loai).split(',').filter(Boolean) : [],
    hoTen: row.hoten,
    sdt: row.sdt,
    email: row.email,
    ngaySinh: row.ngaysinh,
    diaChi: row.diachi,
    gioiTinh: row.gioi_tinh,
    cccd: row.cccd,
    ngheNghiep: row.nghe_nghiep_ns || row.nghe_nghiep,
    donViCongTac: row.don_vi_cong_tac,
    gioiThieu: row.gioi_thieu,
    kinhNghiemNam: Number(row.kinh_nghiem_nam || 0),
    hinhThucDay: row.hinh_thuc_day ? String(row.hinh_thuc_day).split(',').filter(Boolean) : [],
    donGiaGioChuan: Number(row.don_gia_gio_chuan || 0),
    nganHang: row.ngan_hang,
    soTaiKhoan: row.so_tai_khoan,
    chuTaiKhoan: row.chu_tai_khoan,
    trangThai: row.trang_thai,
    username: row.username || null,
    accountStatus: row.account_status || null,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };

  if (extras.monDays !== undefined) mapped.monDays = extras.monDays;
  if (extras.khuVucs !== undefined) mapped.khuVucs = extras.khuVucs;
  if (extras.lichRanhs !== undefined) mapped.lichRanhs = extras.lichRanhs;

  return mapped;
}

module.exports = {
  mapTeacherRow,
};
