const bcrypt = require('bcryptjs');
const { HttpError } = require('../../../../shared/errors/http-error');
const { signToken } = require('../../../../shared/middleware/auth.middleware');
const { resolveAppRole } = require('../../../../shared/permissions/rbac');
const authRepo = require('./auth.repository');
const { mapAuthUserRow } = require('./auth.model');

function normalizeBcryptHash(hash) {
  const str = String(hash || '');
  if (str.startsWith('$2y$')) {
    return `$2a$${str.slice(4)}`;
  }
  return str;
}

async function login({ username, password, ip = null }) {
  const account = await authRepo.findAccountByUsername(username);
  if (!account) {
    throw new HttpError(401, 'Tên đăng nhập hoặc mật khẩu không chính xác.', 'INVALID_CREDENTIALS');
  }

  if (account.status === 'locked') {
    throw new HttpError(403, 'Tài khoản đang bị khóa. Vui lòng liên hệ Quản trị viên.', 'ACCOUNT_LOCKED');
  }

  if (account.status !== 'active') {
    throw new HttpError(403, 'Tài khoản chưa được kích hoạt.', 'ACCOUNT_INACTIVE');
  }

  const isMatch = await bcrypt.compare(password, normalizeBcryptHash(account.password_hash));
  if (!isMatch) {
    throw new HttpError(401, 'Tên đăng nhập hoặc mật khẩu không chính xác.', 'INVALID_CREDENTIALS');
  }

  const role = resolveAppRole({
    groupName: account.group_name,
    phanLoai: account.phan_loai,
  });
  account.role = role;

  const permissions = await authRepo.findPermissionsByGroupId(account.group_id);
  const userProfile = mapAuthUserRow(account, permissions);

  const token = signToken({
    accountId: account.account_id,
    userId: account.user_id,
    groupId: account.group_id,
    groupName: account.group_name,
    role,
    username: account.username,
    nhanVienId: account.nhan_vien_id || null,
    nhanSuId: account.nhan_su_id || null,
    phanLoai: account.phan_loai || null,
  });

  await authRepo.writeAuditLog({
    userId: account.user_id,
    hanhDong: 'dang_nhap',
    bang: 'account',
    banGhiId: account.account_id,
    duLieuMoi: { username: account.username, role },
    ip,
  });

  return {
    token,
    user: userProfile,
  };
}

async function getCurrentUser(accountId) {
  const account = await authRepo.findAccountById(accountId);
  if (!account || account.status !== 'active') {
    throw new HttpError(401, 'Tài khoản không tồn tại hoặc đã bị khóa.', 'ACCOUNT_UNAVAILABLE');
  }

  account.role = resolveAppRole({
    groupName: account.group_name,
    phanLoai: account.phan_loai,
  });
  const permissions = await authRepo.findPermissionsByGroupId(account.group_id);
  return mapAuthUserRow(account, permissions);
}

async function changePassword({ accountId, currentPassword, newPassword, ip = null }) {
  const account = await authRepo.findAccountById(accountId);
  if (!account) {
    throw new HttpError(404, 'Không tìm thấy tài khoản.', 'ACCOUNT_NOT_FOUND');
  }

  const isMatch = await bcrypt.compare(currentPassword, normalizeBcryptHash(account.password_hash));
  if (!isMatch) {
    throw new HttpError(400, 'Mật khẩu hiện tại không chính xác.', 'INVALID_CURRENT_PASSWORD');
  }

  const newHash = await bcrypt.hash(newPassword, 10);
  await authRepo.updatePasswordHash(accountId, newHash);

  await authRepo.writeAuditLog({
    userId: account.user_id,
    hanhDong: 'sua',
    bang: 'account',
    banGhiId: account.account_id,
    duLieuMoi: { action: 'doi_mat_khau' },
    ip,
  });

  return { updated: true };
}

async function logout({ accountId, userId, ip = null }) {
  if (userId) {
    await authRepo.writeAuditLog({
      userId,
      hanhDong: 'khac',
      bang: 'account',
      banGhiId: accountId,
      duLieuMoi: { action: 'dang_xuat' },
      ip,
    });
  }
  return { loggedOut: true };
}

async function bootstrapAdmin({
  username = 'admin',
  password = process.env.INITIAL_ADMIN_PASSWORD || 'Admin@123456',
  hoTen = 'Quản Trị Viên',
  email = 'admin@educenter.local',
  sdt = '0900000001',
} = {}) {
  const passwordHash = await bcrypt.hash(password, 10);
  const result = await authRepo.upsertInitialAdmin({
    username,
    passwordHash,
    hoTen,
    email,
    sdt,
  });
  return {
    ...result,
    username,
  };
}

module.exports = {
  login,
  getCurrentUser,
  changePassword,
  logout,
  bootstrapAdmin,
};
