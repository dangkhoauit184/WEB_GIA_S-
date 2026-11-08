const { HttpError } = require('../../../../shared/errors/http-error');

function validateCreateParent(body = {}) {
  const hoTen = String(body.hoTen || '').trim();
  const email = String(body.email || '').trim();
  const sdt = body.sdt ? String(body.sdt).trim() : null;

  if (!hoTen || !email) {
    throw new HttpError(400, 'Họ tên và email của phụ huynh là bắt buộc.', 'VALIDATION_ERROR');
  }

  return {
    hoTen,
    email,
    sdt,
    ngaySinh: body.ngaySinh || null,
    diaChi: body.diaChi ? String(body.diaChi).trim() : null,
    ngheNghiep: body.ngheNghiep ? String(body.ngheNghiep).trim() : null,
    khuVucId: body.khuVucId ? Number(body.khuVucId) : null,
    username: body.username ? String(body.username).trim() : null,
    password: body.password ? String(body.password) : null,
  };
}

function validateUpdateParent(body = {}) {
  const hoTen = body.hoTen !== undefined ? String(body.hoTen).trim() : undefined;
  const email = body.email !== undefined ? String(body.email).trim() : undefined;

  if (hoTen !== undefined && !hoTen) {
    throw new HttpError(400, 'Họ tên phụ huynh không được để trống.', 'VALIDATION_ERROR');
  }
  if (email !== undefined && !email) {
    throw new HttpError(400, 'Email phụ huynh không được để trống.', 'VALIDATION_ERROR');
  }

  return {
    hoTen,
    email,
    sdt: body.sdt !== undefined ? (body.sdt ? String(body.sdt).trim() : null) : undefined,
    ngaySinh: body.ngaySinh !== undefined ? body.ngaySinh : undefined,
    diaChi: body.diaChi !== undefined ? (body.diaChi ? String(body.diaChi).trim() : null) : undefined,
    ngheNghiep: body.ngheNghiep !== undefined ? (body.ngheNghiep ? String(body.ngheNghiep).trim() : null) : undefined,
    khuVucId: body.khuVucId !== undefined ? (body.khuVucId ? Number(body.khuVucId) : null) : undefined,
  };
}

module.exports = {
  validateCreateParent,
  validateUpdateParent,
};
