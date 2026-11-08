const { HttpError } = require('../../../../shared/errors/http-error');
const classesRepo = require('./classes.repository');
const { mapClassRow } = require('./classes.model');

async function listClasses(filters = {}) {
  const rows = await classesRepo.findAllClasses(filters);
  return rows.map((row) => mapClassRow(row));
}

async function getClassDetail(id) {
  const row = await classesRepo.findClassById(id);
  if (!row) {
    throw new HttpError(404, 'Không tìm thấy lớp học.', 'CLASS_NOT_FOUND');
  }
  const [hocViens, lichHocs] = await Promise.all([
    classesRepo.findEnrollmentsByClassId(id),
    classesRepo.findSchedulesByClassId(id),
  ]);
  return mapClassRow(row, { hocViens, lichHocs });
}

async function createClass(input, callerUserId) {
  const id = await classesRepo.createClass(input, callerUserId);
  return getClassDetail(id);
}

async function updateClass(id, input) {
  await getClassDetail(id);
  await classesRepo.updateClass(id, input);
  return getClassDetail(id);
}

async function listEnrollments(lopId) {
  await getClassDetail(lopId);
  return classesRepo.findEnrollmentsByClassId(lopId);
}

async function enrollStudent(lopId, input) {
  await getClassDetail(lopId);
  await classesRepo.enrollStudent(lopId, input);
  return classesRepo.findEnrollmentsByClassId(lopId);
}

async function updateEnrollment(lopId, enrollmentId, input) {
  await getClassDetail(lopId);
  await classesRepo.updateEnrollment(lopId, enrollmentId, input);
  return classesRepo.findEnrollmentsByClassId(lopId);
}

module.exports = {
  listClasses,
  getClassDetail,
  createClass,
  updateClass,
  listEnrollments,
  enrollStudent,
  updateEnrollment,
};
