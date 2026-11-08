const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const { validateLoginInput, validateChangePasswordInput } = require('./auth.validator');
const authService = require('./auth.service');

const login = asyncHandler(async (req, res) => {
  const { username, password } = validateLoginInput(req.body);
  const result = await authService.login({
    username,
    password,
    ip: req.ip,
  });
  return sendSuccess(res, result, 'Đăng nhập thành công.');
});

const getMe = asyncHandler(async (req, res) => {
  const user = await authService.getCurrentUser(req.user.accountId);
  return sendSuccess(res, { user }, 'Lấy thông tin tài khoản thành công.');
});

const changePassword = asyncHandler(async (req, res) => {
  const { currentPassword, newPassword } = validateChangePasswordInput(req.body);
  const result = await authService.changePassword({
    accountId: req.user.accountId,
    currentPassword,
    newPassword,
    ip: req.ip,
  });
  return sendSuccess(res, result, 'Đổi mật khẩu thành công.');
});

const logout = asyncHandler(async (req, res) => {
  const result = await authService.logout({
    accountId: req.user.accountId,
    userId: req.user.userId,
    ip: req.ip,
  });
  return sendSuccess(res, result, 'Đăng xuất thành công.');
});

module.exports = {
  login,
  getMe,
  changePassword,
  logout,
};
