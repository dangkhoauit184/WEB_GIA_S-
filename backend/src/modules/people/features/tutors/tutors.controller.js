const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const { validateCreateTeacher, validateUpdateTeacher } = require('./tutors.validator');
const tutorsService = require('./tutors.service');

const listTutors = asyncHandler(async (req, res) => {
  const data = await tutorsService.listTeachers({
    q: req.query.q || '',
    phanLoai: req.query.phanLoai || 'gia_su',
    trangThai: req.query.trangThai || null,
    monHocId: req.query.monHocId || null,
    capLopId: req.query.capLopId || null,
    khuVucId: req.query.khuVucId || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách gia sư thành công.');
});

const matchTutors = asyncHandler(async (req, res) => {
  const data = await tutorsService.matchTeachers({
    monHocId: req.query.monHocId,
    capLopId: req.query.capLopId,
    khuVucId: req.query.khuVucId || null,
    nganSachMax: req.query.nganSachMax || null,
    thu: req.query.thu || null,
    gioBatDau: req.query.gioBatDau || null,
    gioKetThuc: req.query.gioKetThuc || null,
  });
  return sendSuccess(res, data, 'Gợi ý gia sư phù hợp (BR02) thành công.');
});

const getTutorDetail = asyncHandler(async (req, res) => {
  const data = await tutorsService.getTeacherDetail(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy chi tiết gia sư thành công.');
});

const createTutor = asyncHandler(async (req, res) => {
  const payload = validateCreateTeacher(req.body, 'gia_su');
  const data = await tutorsService.createTeacher(payload);
  return sendSuccess(res, data, 'Tạo hồ sơ gia sư thành công.', 201);
});

const updateTutor = asyncHandler(async (req, res) => {
  const payload = validateUpdateTeacher(req.body);
  const data = await tutorsService.updateTeacher(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Cập nhật hồ sơ gia sư thành công.');
});

module.exports = {
  listTutors,
  matchTutors,
  getTutorDetail,
  createTutor,
  updateTutor,
};
