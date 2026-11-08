function mapParentRow(row, children = undefined) {
  if (!row) return null;
  const mapped = {
    userId: row.user_id,
    hoTen: row.hoten,
    sdt: row.sdt,
    email: row.email,
    ngaySinh: row.ngaysinh,
    diaChi: row.diachi,
    ngheNghiep: row.nghe_nghiep,
    khuVucId: row.khu_vuc_id,
    tenKhuVuc: row.ten_khu_vuc || null,
    accountId: row.account_id || null,
    username: row.username || null,
    accountStatus: row.account_status || null,
    soCon: row.so_con !== undefined ? Number(row.so_con) : undefined,
    createdAt: row.created_at,
  };
  if (children !== undefined) {
    mapped.hocViens = children;
  }
  return mapped;
}

module.exports = {
  mapParentRow,
};
