const bcrypt = require('bcryptjs');
const { HttpError } = require('../../../../shared/errors/http-error');
const parentsRepo = require('./parents.repository');
const { mapParentRow } = require('./parents.model');

async function listParents(filters = {}) {
  const rows = await parentsRepo.findAllParents(filters);
  return rows.map((row) => mapParentRow(row));
}

async function getParentDetail(userId) {
  const row = await parentsRepo.findParentById(userId);
  if (!row) {
    throw new HttpError(404, 'Không tìm thấy hồ sơ phụ huynh.', 'PARENT_NOT_FOUND');
  }
  const children = await parentsRepo.findChildrenByParentId(userId);
  return mapParentRow(row, children);
}

async function createParent(input) {
  const passwordHash = input.username
    ? await bcrypt.hash(input.password || '123456', 10)
    : null;

  const userId = await parentsRepo.createParent({
    ...input,
    passwordHash,
  });

  return getParentDetail(userId);
}

async function updateParent(userId, input) {
  await getParentDetail(userId);
  await parentsRepo.updateParent(userId, input);
  return getParentDetail(userId);
}

module.exports = {
  listParents,
  getParentDetail,
  createParent,
  updateParent,
};
