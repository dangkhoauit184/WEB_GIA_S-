const { HttpError } = require('../../../../shared/errors/http-error');

function validateCreateClass(body = {}) {
  const maLop = String(body.maLop || '').trim();
  const tenLop = String(body.tenLop || '').trim();
  const monHocId = Number(body.monHocId);
  const capLopId = Number(body.capLopId);
  const nguoiDayChinhId = Number(body.nguoiDayChinhId);
  const ngayBatDau = body.ngayBatDau ? String(body.ngayBatDau).trim() : null;

  if (!maLop || !tenLop || !monHocId || !capLopId || !nguoiDayChinhId || !ngayBatDau) {
    throw new HttpError(
      400,
      'Vui lòng nhập đầy đủ mã lớp, tên lớp, môn học, cấp lớp, người dạy chính và ngày bắt đầu.',
      'VALIDATION_ERROR'
    );
  }

  const siSoToiThieu = Number(body.siSoToiThieu || 1);
  const siSoToiDa = Number(body.siSoToiDa || 1);
  if (siSoToiThieu < 1 || siSoToiDa < siSoToiThieu) {
    throw new HttpError(400, 'Sĩ số tối đa phải lớn hơn hoặc bằng sĩ số tối thiểu (>= 1).', 'VALIDATION_ERROR');
  }

  return {
    maLop,
    tenLop,
    loaiLop: body.loaiLop || 'kem_1_1',
    deXuatId: body.deXuatId ? Number(body.deXuatId) : null,
    monHocId,
    capLopId,
    nguoiDayChinhId,
    coSoId: body.coSoId ? Number(body.coSoId) : null,
    phongHoc: body.phongHoc ? String(body.phongHoc).trim() : null,
    hinhThuc: body.hinhThuc || 'offline',
    diaDiem: body.diaDiem || null,
    linkHoc: body.linkHoc || null,
    khuVucId: body.khuVucId ? Number(body.khuVucId) : null,
    siSoToiThieu,
    siSoToiDa,
    cachThuPhi: body.cachThuPhi || 'theo_buoi',
    hocPhiBuoi: Number(body.hocPhiBuoi || 0),
    hocPhiThang: body.hocPhiThang !== undefined && body.hocPhiThang !== null ? Number(body.hocPhiThang) : null,
    hocPhiKhoa: Number(body.hocPhiKhoa || 0),
    mucTraChinh: Number(body.mucTraChinh || 0),
    mucTraTroGiang: Number(body.mucTraTroGiang || 0),
    cachTinhLuong: body.cachTinhLuong || 'theo_buoi',
    soBuoiDuKien: body.soBuoiDuKien ? Number(body.soBuoiDuKien) : null,
    ngayBatDau,
    ngayKetThuc: body.ngayKetThuc || null,
    trangThai: body.trangThai || 'sap_bat_dau',
    nhanVienId: body.nhanVienId ? Number(body.nhanVienId) : null,
  };
}

function validateUpdateClass(body = {}) {
  const fields = {};
  const allowed = [
    'tenLop',
    'loaiLop',
    'monHocId',
    'capLopId',
    'nguoiDayChinhId',
    'coSoId',
    'phongHoc',
    'hinhThuc',
    'diaDiem',
    'linkHoc',
    'khuVucId',
    'siSoToiThieu',
    'siSoToiDa',
    'cachThuPhi',
    'hocPhiBuoi',
    'hocPhiThang',
    'hocPhiKhoa',
    'mucTraChinh',
    'mucTraTroGiang',
    'cachTinhLuong',
    'soBuoiDuKien',
    'ngayBatDau',
    'ngayKetThuc',
    'trangThai',
  ];

  for (const key of allowed) {
    if (body[key] !== undefined) {
      fields[key] = body[key];
    }
  }
  return fields;
}

function validateEnrollment(body = {}) {
  const hocVienId = Number(body.hocVienId);
  const ngayVao = body.ngayVao || new Date().toISOString().slice(0, 10);
  const trangThai = body.trangThai || 'dang_hoc';

  if (!hocVienId) {
    throw new HttpError(400, 'Vui lòng cung cấp hocVienId để ghi danh vào lớp.', 'VALIDATION_ERROR');
  }

  return {
    hocVienId,
    ngayVao,
    ngayRoi: body.ngayRoi || null,
    trangThai,
  };
}

module.exports = {
  validateCreateClass,
  validateUpdateClass,
  validateEnrollment,
};
