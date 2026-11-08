const express = require('express');

const router = express.Router();

router.use('/auth', require('./features/auth/auth.routes'));
router.use('/permissions', require('./features/permissions/permissions.routes'));
router.use('/staff', require('./features/staff/staff.routes'));
router.use('/catalog', require('./features/catalog/catalog.routes'));
router.use('/audit', require('./features/audit/audit.routes'));

module.exports = router;
