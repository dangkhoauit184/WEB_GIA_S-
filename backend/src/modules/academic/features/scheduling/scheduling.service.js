const { HttpError } = require('../../../../shared/errors/http-error');
const schedulingRepo = require('./scheduling.repository');
const { mapWeeklyScheduleRow, mapSessionRow } = require('./scheduling.model');

async function listWeeklySchedules(filters = {}) {
  const rows = await schedulingRepo.findWeeklySchedules(filters);
  return rows.map((row) => mapWeeklyScheduleRow(row));
}

async function createWeeklySchedule(input) {
  const row = await schedulingRepo.createWeeklySchedule(input);
  return mapWeeklyScheduleRow(row);
}

async function listSessions(filters = {}) {
  const rows = await schedulingRepo.findSessions(filters);
  return rows.map((row) => mapSessionRow(row));
}

async function generateSessions(input) {
  const rows = await schedulingRepo.generateSessionsByProcedure(input);
  return rows.map((row) => mapSessionRow(row));
}

async function assignPersonnel(buoiHocId, input, assignedByUserId) {
  return schedulingRepo.assignPersonnelToSession(buoiHocId, input, assignedByUserId);
}

async function updateSession(buoiHocId, input, callerUserId) {
  const row = await schedulingRepo.updateSession(buoiHocId, input, callerUserId);
  if (!row) {
    throw new HttpError(404, 'Không tìm thấy buổi học.', 'SESSION_NOT_FOUND');
  }
  return mapSessionRow(row);
}

module.exports = {
  listWeeklySchedules,
  createWeeklySchedule,
  listSessions,
  generateSessions,
  assignPersonnel,
  updateSession,
};
