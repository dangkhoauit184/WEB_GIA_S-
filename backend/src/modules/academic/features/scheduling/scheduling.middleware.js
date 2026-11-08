const { authenticate } = require('../../../../shared/middleware/auth.middleware');
const { authorizeRoles, APP_ROLES } = require('../../../../shared/permissions/rbac');

const requireStaffOrAdmin = [
  authenticate,
  authorizeRoles(APP_ROLES.ADMIN, APP_ROLES.GIAO_VU),
];

module.exports = {
  authenticate,
  requireStaffOrAdmin,
};
