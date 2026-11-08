const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const { validateCreateTeacher, validateUpdateTeacher } = require('./teachers.validator');
const teachersService = require('./teachers.service');

const listTeachers = asyncHandler(async (req, res) => {
  const data = await teachersService.listTeachers({
    q: req.query.q || '',
    phanLoai: req.query.phanLoai || null,
    trangThai: req.query.trangThai || null,
    monHocId: req.query.monHocId || null,
    capLopId: req.query.capLopId || null,
    khuVucId: req.query.khuVucId || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách người dạy thành công.');
});

const matchTeachers = asyncHandler(async (req, res) => {
  const data = await teachersService.matchTeachers({
    monHocId: req.query.monHocId,
    capLopId: req.query.capLopId,
    khuVucId: req.query.khuVucId || null,
    nganSachMax: req.query.nganSachMax || null,
    thu: req.query.thu || null,
    gioBatDau: req.query.gioBatDau || null,
    gioKetThuc: req.query.gioKetThuc || null,
  });
  return sendSuccess(res, data, 'Gợi ý người dạy phù hợp (BR02) thành công.');
});

const getTeacherDetail = asyncHandler(async (req, res) => {
  const data = await teachersService.getTeacherDetail(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy chi tiết người dạy thành công.');
});

const createTeacher = asyncHandler(async (req, res) => {
  const payload = validateCreateTeacher(req.body, 'giao_vien');
  const data = await teachersService.createTeacher(payload);
  return sendSuccess(res, data, 'Tạo hồ sơ người dạy thành công.', 201);
});

const updateTeacher = asyncHandler(async (req, res) => {
  const payload = validateUpdateTeacher(req.body);
  const data = await teachersService.updateTeacher(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Cập nhật hồ sơ người dạy thành công.');
});

module.exports = {
  listTeachers,
  matchTeachers,
  getTeacherDetail,
  createTeacher,
  updateTeacher,
};
