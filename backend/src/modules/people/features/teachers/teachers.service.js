const bcrypt = require('bcryptjs');
const { HttpError } = require('../../../../shared/errors/http-error');
const teachersRepo = require('./teachers.repository');
const { mapTeacherRow } = require('./teachers.model');

async function listTeachers(filters = {}) {
  const rows = await teachersRepo.findAllTeachers(filters);
  return rows.map((row) => mapTeacherRow(row));
}

async function matchTeachers(filters = {}) {
  if (!filters.monHocId || !filters.capLopId) {
    throw new HttpError(400, 'Cần cung cấp monHocId và capLopId để lọc người dạy (BR02).', 'VALIDATION_ERROR');
  }
  const rows = await teachersRepo.matchTeachersBR02(filters);
  return rows.map((row) => ({
    ...mapTeacherRow(row),
    hocPhiBuoiDeXuat: Number(row.hoc_phi_buoi_de_xuat || 0),
  }));
}

async function getTeacherDetail(id) {
  const row = await teachersRepo.findTeacherById(id);
  if (!row) {
    throw new HttpError(404, 'Không tìm thấy hồ sơ người dạy.', 'TEACHER_NOT_FOUND');
  }
  const extras = await teachersRepo.findTeacherExtras(id);
  return mapTeacherRow(row, extras);
}

async function createTeacher(input) {
  const passwordHash = input.username
    ? await bcrypt.hash(input.password || '123456', 10)
    : null;
  const id = await teachersRepo.createTeacher(input, passwordHash);
  return getTeacherDetail(id);
}

async function updateTeacher(id, input) {
  const current = await teachersRepo.findTeacherById(id);
  if (!current) {
    throw new HttpError(404, 'Không tìm thấy hồ sơ người dạy.', 'TEACHER_NOT_FOUND');
  }
  await teachersRepo.updateTeacher(id, current.user_id, input);
  return getTeacherDetail(id);
}

module.exports = {
  listTeachers,
  matchTeachers,
  getTeacherDetail,
  createTeacher,
  updateTeacher,
};
