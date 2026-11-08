const { HttpError } = require('../../../../shared/errors/http-error');

const VALID_GENDERS = ['nam', 'nu', 'khac'];
const VALID_STATUSES = ['dang_hoc', 'tam_nghi', 'hoan_thanh', 'nghi_han'];

function validateCreateStudent(body = {}) {
  const phuHuynhUserId = Number(body.phuHuynhUserId);
  const maHv = String(body.maHv || '').trim();
  const hoTen = String(body.hoTen || '').trim();

  if (!phuHuynhUserId || !maHv || !hoTen) {
    throw new HttpError(
      400,
      'Vui lòng nhập đầy đủ mã học viên (maHv), họ tên (hoTen) và phụ huynh (phuHuynhUserId).',
      'VALIDATION_ERROR'
    );
  }

  if (body.gioiTinh && !VALID_GENDERS.includes(body.gioiTinh)) {
    throw new HttpError(400, 'Giới tính không hợp lệ (nam, nu, khac).', 'VALIDATION_ERROR');
  }

  if (body.trangThai && !VALID_STATUSES.includes(body.trangThai)) {
    throw new HttpError(400, 'Trạng thái học viên không hợp lệ.', 'VALIDATION_ERROR');
  }

  return {
    phuHuynhUserId,
    maHv,
    hoTen,
    ngaySinh: body.ngaySinh || null,
    gioiTinh: body.gioiTinh || null,
    truong: body.truong ? String(body.truong).trim() : null,
    capLopId: body.capLopId ? Number(body.capLopId) : null,
    diaChi: body.diaChi ? String(body.diaChi).trim() : null,
    khuVucId: body.khuVucId ? Number(body.khuVucId) : null,
    ghiChu: body.ghiChu ? String(body.ghiChu).trim() : null,
    trangThai: body.trangThai || 'dang_hoc',
  };
}

function validateUpdateStudent(body = {}) {
  if (body.gioiTinh !== undefined && body.gioiTinh !== null && !VALID_GENDERS.includes(body.gioiTinh)) {
    throw new HttpError(400, 'Giới tính không hợp lệ (nam, nu, khac).', 'VALIDATION_ERROR');
  }

  if (body.trangThai !== undefined && !VALID_STATUSES.includes(body.trangThai)) {
    throw new HttpError(400, 'Trạng thái học viên không hợp lệ.', 'VALIDATION_ERROR');
  }

  return {
    phuHuynhUserId: body.phuHuynhUserId !== undefined ? Number(body.phuHuynhUserId) : undefined,
    hoTen: body.hoTen !== undefined ? String(body.hoTen).trim() : undefined,
    ngaySinh: body.ngaySinh !== undefined ? body.ngaySinh : undefined,
    gioiTinh: body.gioiTinh !== undefined ? body.gioiTinh : undefined,
    truong: body.truong !== undefined ? (body.truong ? String(body.truong).trim() : null) : undefined,
    capLopId: body.capLopId !== undefined ? (body.capLopId ? Number(body.capLopId) : null) : undefined,
    diaChi: body.diaChi !== undefined ? (body.diaChi ? String(body.diaChi).trim() : null) : undefined,
    khuVucId: body.khuVucId !== undefined ? (body.khuVucId ? Number(body.khuVucId) : null) : undefined,
    ghiChu: body.ghiChu !== undefined ? (body.ghiChu ? String(body.ghiChu).trim() : null) : undefined,
    trangThai: body.trangThai !== undefined ? body.trangThai : undefined,
  };
}

module.exports = {
  validateCreateStudent,
  validateUpdateStudent,
};
