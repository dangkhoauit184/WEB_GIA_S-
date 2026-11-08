const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const { APP_ROLES } = require('../../../../shared/permissions/rbac');
const { validateCreateStudent, validateUpdateStudent } = require('./students.validator');
const studentsService = require('./students.service');

const listStudents = asyncHandler(async (req, res) => {
  const phuHuynhUserId =
    req.user && req.user.role === APP_ROLES.PHU_HUYNH
      ? req.user.userId
      : req.query.phuHuynhUserId || null;

  const data = await studentsService.listStudents({
    q: req.query.q || '',
    phuHuynhUserId,
    capLopId: req.query.capLopId || null,
    trangThai: req.query.trangThai || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách học viên thành công.');
});

const getStudentDetail = asyncHandler(async (req, res) => {
  const data = await studentsService.getStudentDetail(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy chi tiết học viên thành công.');
});

const createStudent = asyncHandler(async (req, res) => {
  const payload = validateCreateStudent(req.body);
  const data = await studentsService.createStudent(payload);
  return sendSuccess(res, data, 'Thêm học viên thành công.', 201);
});

const updateStudent = asyncHandler(async (req, res) => {
  const payload = validateUpdateStudent(req.body);
  const data = await studentsService.updateStudent(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Cập nhật hồ sơ học viên thành công.');
});

const deleteStudent = asyncHandler(async (req, res) => {
  const data = await studentsService.deleteStudent(Number(req.params.id));
  return sendSuccess(res, data, 'Xóa hồ sơ học viên thành công.');
});

module.exports = {
  listStudents,
  getStudentDetail,
  createStudent,
  updateStudent,
  deleteStudent,
};
