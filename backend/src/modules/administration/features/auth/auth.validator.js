const { HttpError } = require('../../../../shared/errors/http-error');

function validateLoginInput(body = {}) {
  const username = String(body.username || '').trim();
  const password = String(body.password || '');

  if (!username || !password) {
    throw new HttpError(400, 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.', 'VALIDATION_ERROR');
  }

  return { username, password };
}

function validateChangePasswordInput(body = {}) {
  const currentPassword = String(body.currentPassword || '');
  const newPassword = String(body.newPassword || '');

  if (!currentPassword || !newPassword) {
    throw new HttpError(400, 'Vui lòng nhập mật khẩu hiện tại và mật khẩu mới.', 'VALIDATION_ERROR');
  }

  if (newPassword.length < 6) {
    throw new HttpError(400, 'Mật khẩu mới phải có ít nhất 6 ký tự.', 'VALIDATION_ERROR');
  }

  if (currentPassword === newPassword) {
    throw new HttpError(400, 'Mật khẩu mới phải khác mật khẩu hiện tại.', 'VALIDATION_ERROR');
  }

  return { currentPassword, newPassword };
}

module.exports = {
  validateLoginInput,
  validateChangePasswordInput,
};
