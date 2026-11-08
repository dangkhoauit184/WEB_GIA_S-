const ROLE_GROUPS = {
  ADMIN: 'Admin',
  NHAN_VIEN: 'NhanVien',
  GIANG_VIEN: 'GiangVien',
  TRO_GIANG: 'TroGiang',
  PHU_HUYNH: 'PhuHuynh',
};

const APP_ROLES = {
  ADMIN: 'ADMIN',
  GIAO_VU: 'GIAO_VU',
  GIAO_VIEN: 'GIAO_VIEN',
  GIA_SU: 'GIA_SU',
  TRO_GIANG: 'TRO_GIANG',
  PHU_HUYNH: 'PHU_HUYNH',
};

const GROUP_TO_DEFAULT_ROLE = {
  Admin: APP_ROLES.ADMIN,
  NhanVien: APP_ROLES.GIAO_VU,
  GiangVien: APP_ROLES.GIAO_VIEN,
  TroGiang: APP_ROLES.TRO_GIANG,
  PhuHuynh: APP_ROLES.PHU_HUYNH,
};

const FUNCTIONS = {
  QUAN_LY_TAI_KHOAN: 'QuanLyTaiKhoan',
  QUAN_LY_NHAN_SU: 'QuanLyNhanSu',
  TIEP_NHAN_YEU_CAU: 'TiepNhanYeuCau',
  QUAN_LY_LOP_HOC: 'QuanLyLopHoc',
  VAN_HANH_BUOI_HOC: 'VanHanhBuoiHoc',
  CHAM_CONG_LUONG: 'ChamCongLuong',
  HOC_PHI_THANH_TOAN: 'HocPhiThanhToan',
  BAO_CAO_THONG_KE: 'BaoCaoThongKe',
};

const PERMISSION_ACTIONS = ['view', 'add', 'edit', 'delete', 'download'];

module.exports = {
  ROLE_GROUPS,
  APP_ROLES,
  GROUP_TO_DEFAULT_ROLE,
  FUNCTIONS,
  PERMISSION_ACTIONS,
};
