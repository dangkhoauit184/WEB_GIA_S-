const { HttpError } = require('../../../../shared/errors/http-error');
const studentsRepo = require('./students.repository');
const { mapStudentRow } = require('./students.model');

async function listStudents(filters = {}) {
  const rows = await studentsRepo.findAllStudents(filters);
  return rows.map((row) => mapStudentRow(row));
}

async function getStudentDetail(id) {
  const row = await studentsRepo.findStudentById(id);
  if (!row) {
    throw new HttpError(404, 'Không tìm thấy hồ sơ học viên.', 'STUDENT_NOT_FOUND');
  }
  const classes = await studentsRepo.findClassesByStudentId(id);
  return mapStudentRow(row, classes);
}

async function createStudent(input) {
  const id = await studentsRepo.createStudent(input);
  return getStudentDetail(id);
}

async function updateStudent(id, input) {
  await getStudentDetail(id);
  await studentsRepo.updateStudent(id, input);
  return getStudentDetail(id);
}

async function deleteStudent(id) {
  await getStudentDetail(id);
  await studentsRepo.softDeleteStudent(id);
  return { deleted: true, id };
}

module.exports = {
  listStudents,
  getStudentDetail,
  createStudent,
  updateStudent,
  deleteStudent,
};
