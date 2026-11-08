const { HttpError } = require('../../../../shared/errors/http-error');

function validateCreateWeeklySchedule(body = {}) {
  const lopId = Number(body.lopId);
  const thu = Number(body.thu);
  const gioBatDau = String(body.gioBatDau || '').trim();
  const gioKetThuc = String(body.gioKetThuc || '').trim();
  const hieuLucTu = String(body.hieuLucTu || '').trim();

  if (!lopId || !thu || !gioBatDau || !gioKetThuc || !hieuLucTu) {
    throw new HttpError(
      400,
      'Vui lòng nhập đầy đủ lopId, thu (2..8), gioBatDau, gioKetThuc và hieuLucTu.',
      'VALIDATION_ERROR'
    );
  }

  if (thu < 2 || thu > 8) {
    throw new HttpError(400, 'Thứ trong tuần phải từ 2 (Thứ 2) đến 8 (Chủ nhật).', 'VALIDATION_ERROR');
  }

  return {
    lopId,
    thu,
    gioBatDau,
    gioKetThuc,
    phongHoc: body.phongHoc ? String(body.phongHoc).trim() : null,
    hieuLucTu,
    hieuLucDen: body.hieuLucDen || null,
    isActive: body.isActive !== undefined ? (body.isActive ? 1 : 0) : 1,
  };
}

function validateGenerateSessions(body = {}) {
  const lopId = Number(body.lopId);
  const tuNgay = String(body.tuNgay || '').trim();
  const denNgay = String(body.denNgay || '').trim();

  if (!lopId || !tuNgay || !denNgay) {
    throw new HttpError(
      400,
      'Vui lòng nhập đầy đủ lopId, tuNgay và denNgay để sinh chuỗi buổi học (BR03).',
      'VALIDATION_ERROR'
    );
  }

  return { lopId, tuNgay, denNgay };
}

function validateAssignSession(body = {}) {
  const userId = Number(body.userId);
  const vaiTro = body.vaiTro || 'tro_giang';
  const donGia = Number(body.donGia || 0);
  const cachTinhLuong = body.cachTinhLuong || 'theo_buoi';

  if (!userId || !['day_chinh', 'tro_giang'].includes(vaiTro)) {
    throw new HttpError(
      400,
      'Vui lòng cung cấp userId và vaiTro hợp lệ (day_chinh hoặc tro_giang).',
      'VALIDATION_ERROR'
    );
  }

  return { userId, vaiTro, donGia, cachTinhLuong };
}

module.exports = {
  validateCreateWeeklySchedule,
  validateGenerateSessions,
  validateAssignSession,
};
