const { authenticate } = require('../../../../shared/middleware/auth.middleware');

module.exports = {
  requireAuth: authenticate,
};
