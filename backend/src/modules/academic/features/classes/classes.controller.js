const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const {
  validateCreateClass,
  validateUpdateClass,
  validateEnrollment,
} = require('./classes.validator');
const classesService = require('./classes.service');

const listClasses = asyncHandler(async (req, res) => {
  const data = await classesService.listClasses({
    q: req.query.q || '',
    loaiLop: req.query.loaiLop || null,
    trangThai: req.query.trangThai || null,
    monHocId: req.query.monHocId || null,
    nguoiDayChinhId: req.query.nguoiDayChinhId || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách lớp học thành công.');
});

const getClassDetail = asyncHandler(async (req, res) => {
  const data = await classesService.getClassDetail(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy chi tiết lớp học thành công.');
});

const createClass = asyncHandler(async (req, res) => {
  const payload = validateCreateClass(req.body);
  const data = await classesService.createClass(payload, req.user.userId);
  return sendSuccess(res, data, 'Tạo lớp học thành công.', 201);
});

const updateClass = asyncHandler(async (req, res) => {
  const payload = validateUpdateClass(req.body);
  const data = await classesService.updateClass(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Cập nhật lớp học thành công.');
});

const listEnrollments = asyncHandler(async (req, res) => {
  const data = await classesService.listEnrollments(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy danh sách học viên ghi danh thành công.');
});

const enrollStudent = asyncHandler(async (req, res) => {
  const payload = validateEnrollment(req.body);
  const data = await classesService.enrollStudent(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Ghi danh học viên vào lớp thành công.', 201);
});

const updateEnrollment = asyncHandler(async (req, res) => {
  const data = await classesService.updateEnrollment(
    Number(req.params.id),
    Number(req.params.enrollmentId),
    req.body
  );
  return sendSuccess(res, data, 'Cập nhật trạng thái ghi danh thành công.');
});

module.exports = {
  listClasses,
  getClassDetail,
  createClass,
  updateClass,
  listEnrollments,
  enrollStudent,
  updateEnrollment,
};
