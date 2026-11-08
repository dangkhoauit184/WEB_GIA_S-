const { asyncHandler, sendSuccess } = require('../../../../shared/utils/helpers');
const { validateCreateParent, validateUpdateParent } = require('./parents.validator');
const parentsService = require('./parents.service');

const listParents = asyncHandler(async (req, res) => {
  const data = await parentsService.listParents({
    q: req.query.q || '',
    khuVucId: req.query.khuVucId || null,
  });
  return sendSuccess(res, data, 'Lấy danh sách phụ huynh thành công.');
});

const getParentDetail = asyncHandler(async (req, res) => {
  const data = await parentsService.getParentDetail(Number(req.params.id));
  return sendSuccess(res, data, 'Lấy chi tiết phụ huynh thành công.');
});

const createParent = asyncHandler(async (req, res) => {
  const payload = validateCreateParent(req.body);
  const data = await parentsService.createParent(payload);
  return sendSuccess(res, data, 'Tạo hồ sơ phụ huynh thành công.', 201);
});

const updateParent = asyncHandler(async (req, res) => {
  const payload = validateUpdateParent(req.body);
  const data = await parentsService.updateParent(Number(req.params.id), payload);
  return sendSuccess(res, data, 'Cập nhật hồ sơ phụ huynh thành công.');
});

module.exports = {
  listParents,
  getParentDetail,
  createParent,
  updateParent,
};
