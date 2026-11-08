const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const {
  validateCreateWeeklySchedule,
  validateGenerateSessions,
  validateAssignSession,
} = require('./scheduling.validator');
const schedulingService = require('./scheduling.service');

const listWeeklySchedules = asyncHandler(async (req, res) => {
  const data = await schedulingService.listWeeklySchedules({
    lopId: req.query.lopId || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách lịch tuần cố định thành công.');
});

const createWeeklySchedule = asyncHandler(async (req, res) => {
  const payload = validateCreateWeeklySchedule(req.body);
  const data = await schedulingService.createWeeklySchedule(payload);
  return sendSuccess(res, data, 'Thêm lịch tuần cố định thành công.', 201);
});

const listSessions = asyncHandler(async (req, res) => {
  const data = await schedulingService.listSessions({
    lopId: req.query.lopId || null,
    tuNgay: req.query.tuNgay || null,
    denNgay: req.query.denNgay || null,
    userId: req.query.userId || null,
    trangThai: req.query.trangThai || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách buổi học thành công.');
});

const generateSessions = asyncHandler(async (req, res) => {
  const payload = validateGenerateSessions(req.body);
  const data = await schedulingService.generateSessions(payload);
  return sendSuccess(res, data, 'Sinh chuỗi buổi học tự động (BR03) thành công.', 201);
});

const assignPersonnel = asyncHandler(async (req, res) => {
  const payload = validateAssignSession(req.body);
  const data = await schedulingService.assignPersonnel(
    Number(req.params.id),
    payload,
    req.user.userId
  );
  return sendSuccess(res, data, 'Phân công nhân sự buổi học thành công.', 201);
});

const updateSession = asyncHandler(async (req, res) => {
  const data = await schedulingService.updateSession(
    Number(req.params.id),
    req.body,
    req.user.userId
  );
  return sendSuccess(res, data, 'Cập nhật buổi học thành công.');
});

module.exports = {
  listWeeklySchedules,
  createWeeklySchedule,
  listSessions,
  generateSessions,
  assignPersonnel,
  updateSession,
};
