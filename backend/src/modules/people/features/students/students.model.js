function mapStudentRow(row, classes = undefined) {
  if (!row) return null;
  const mapped = {
    id: row.id,
    maHv: row.ma_hv,
    hoTen: row.ho_ten,
    ngaySinh: row.ngay_sinh,
    gioiTinh: row.gioi_tinh,
    truong: row.truong,
    capLopId: row.cap_lop_id,
    tenCapLop: row.ten_cap_lop || null,
    diaChi: row.dia_chi,
    khuVucId: row.khu_vuc_id,
    tenKhuVuc: row.ten_khu_vuc || null,
    ghiChu: row.ghi_chu,
    trangThai: row.trang_thai,
    phuHuynhUserId: row.phu_huynh_user_id,
    tenPhuHuynh: row.ten_phu_huynh || null,
    sdtPhuHuynh: row.sdt_phu_huynh || null,
    emailPhuHuynh: row.email_phu_huynh || null,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
  if (classes !== undefined) {
    mapped.lopHocs = classes;
  }
  return mapped;
}

module.exports = {
  mapStudentRow,
};
