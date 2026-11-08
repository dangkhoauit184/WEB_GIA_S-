const { HttpError } = require('../../../../shared/errors/http-error');

const VALID_TYPES = ['giao_vien', 'gia_su', 'tro_giang'];
const VALID_MODES = ['online', 'offline'];
const VALID_STATUSES = ['hoat_dong', 'tam_nghi', 'ngung_hop_tac'];

function normalizeSetField(value, allowedValues, defaultVal) {
  if (!value) return defaultVal;
  const items = Array.isArray(value)
    ? value
    : String(value)
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
  for (const item of items) {
    if (!allowedValues.includes(item)) {
      throw new HttpError(400, `Giá trị '${item}' không hợp lệ.`, 'VALIDATION_ERROR');
    }
  }
  return items.length ? items.join(',') : defaultVal;
}

function validateCreateTeacher(body = {}, defaultType = 'giao_vien') {
  const maNs = String(body.maNs || '').trim();
  const hoTen = String(body.hoTen || '').trim();
  const email = String(body.email || '').trim();

  if (!maNs || !hoTen || !email) {
    throw new HttpError(
      400,
      'Vui lòng nhập đầy đủ mã nhân sự (maNs), họ tên (hoTen) và email.',
      'VALIDATION_ERROR'
    );
  }

  const phanLoai = normalizeSetField(body.phanLoai, VALID_TYPES, defaultType);
  const hinhThucDay = normalizeSetField(body.hinhThucDay, VALID_MODES, 'offline');
  const donGiaGioChuan = Number(body.donGiaGioChuan || 0);

  if (donGiaGioChuan < 0) {
    throw new HttpError(400, 'Đơn giá giờ chuẩn không được âm.', 'VALIDATION_ERROR');
  }

  if (body.trangThai && !VALID_STATUSES.includes(body.trangThai)) {
    throw new HttpError(400, 'Trạng thái nhân sự không hợp lệ.', 'VALIDATION_ERROR');
  }

  return {
    maNs,
    hoTen,
    email,
    sdt: body.sdt ? String(body.sdt).trim() : null,
    ngaySinh: body.ngaySinh || null,
    diaChi: body.diaChi ? String(body.diaChi).trim() : null,
    phanLoai,
    gioiTinh: body.gioiTinh || null,
    cccd: body.cccd ? String(body.cccd).trim() : null,
    ngheNghiep: body.ngheNghiep || 'giao_vien',
    donViCongTac: body.donViCongTac ? String(body.donViCongTac).trim() : null,
    gioiThieu: body.gioiThieu || null,
    kinhNghiemNam: Number(body.kinhNghiemNam || 0),
    hinhThucDay,
    donGiaGioChuan,
    nganHang: body.nganHang || null,
    soTaiKhoan: body.soTaiKhoan || null,
    chuTaiKhoan: body.chuTaiKhoan || null,
    trangThai: body.trangThai || 'hoat_dong',
    username: body.username ? String(body.username).trim() : null,
    password: body.password ? String(body.password) : null,
    monDays: Array.isArray(body.monDays) ? body.monDays : [],
    khuVucIds: Array.isArray(body.khuVucIds) ? body.khuVucIds.map(Number) : [],
    lichRanhs: Array.isArray(body.lichRanhs) ? body.lichRanhs : [],
  };
}

function validateUpdateTeacher(body = {}) {
  const result = {};

  if (body.hoTen !== undefined) result.hoTen = String(body.hoTen).trim();
  if (body.email !== undefined) result.email = String(body.email).trim();
  if (body.sdt !== undefined) result.sdt = body.sdt ? String(body.sdt).trim() : null;
  if (body.ngaySinh !== undefined) result.ngaySinh = body.ngaySinh;
  if (body.diaChi !== undefined) result.diaChi = body.diaChi;
  if (body.phanLoai !== undefined) {
    result.phanLoai = normalizeSetField(body.phanLoai, VALID_TYPES, 'gia_su');
  }
  if (body.gioiTinh !== undefined) result.gioiTinh = body.gioiTinh;
  if (body.cccd !== undefined) result.cccd = body.cccd;
  if (body.ngheNghiep !== undefined) result.ngheNghiep = body.ngheNghiep;
  if (body.donViCongTac !== undefined) result.donViCongTac = body.donViCongTac;
  if (body.gioiThieu !== undefined) result.gioiThieu = body.gioiThieu;
  if (body.kinhNghiemNam !== undefined) result.kinhNghiemNam = Number(body.kinhNghiemNam);
  if (body.hinhThucDay !== undefined) {
    result.hinhThucDay = normalizeSetField(body.hinhThucDay, VALID_MODES, 'offline');
  }
  if (body.donGiaGioChuan !== undefined) {
    result.donGiaGioChuan = Number(body.donGiaGioChuan);
    if (result.donGiaGioChuan < 0) {
      throw new HttpError(400, 'Đơn giá giờ chuẩn không được âm.', 'VALIDATION_ERROR');
    }
  }
  if (body.nganHang !== undefined) result.nganHang = body.nganHang;
  if (body.soTaiKhoan !== undefined) result.soTaiKhoan = body.soTaiKhoan;
  if (body.chuTaiKhoan !== undefined) result.chuTaiKhoan = body.chuTaiKhoan;
  if (body.trangThai !== undefined) {
    if (!VALID_STATUSES.includes(body.trangThai)) {
      throw new HttpError(400, 'Trạng thái nhân sự không hợp lệ.', 'VALIDATION_ERROR');
    }
    result.trangThai = body.trangThai;
  }
  if (Array.isArray(body.monDays)) result.monDays = body.monDays;
  if (Array.isArray(body.khuVucIds)) result.khuVucIds = body.khuVucIds.map(Number);
  if (Array.isArray(body.lichRanhs)) result.lichRanhs = body.lichRanhs;

  return result;
}

module.exports = {
  validateCreateTeacher,
  validateUpdateTeacher,
};
