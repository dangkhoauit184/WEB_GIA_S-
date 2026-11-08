function mapAuthUserRow(row, permissions = []) {
  if (!row) return null;
  return {
    accountId: row.account_id,
    userId: row.user_id,
    username: row.username,
    status: row.status,
    groupId: row.group_id,
    groupName: row.group_name,
    role: row.role,
    hoTen: row.hoten,
    sdt: row.sdt,
    email: row.email,
    ngaySinh: row.ngaysinh,
    diaChi: row.diachi,
    ngheNghiep: row.nghe_nghiep,
    khuVucId: row.khu_vuc_id,
    nhanVienId: row.nhan_vien_id || null,
    maNv: row.ma_nv || null,
    chucVu: row.chuc_vu || null,
    coSoId: row.co_so_id || null,
    nhanSuId: row.nhan_su_id || null,
    maNs: row.ma_ns || null,
    phanLoai: row.phan_loai || null,
    permissions,
  };
}

module.exports = {
  mapAuthUserRow,
};
