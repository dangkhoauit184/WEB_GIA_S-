const express = require('express');

const router = express.Router();

// Alias trực tiếp /api/auth -> /api/administration/auth để tiện sử dụng
router.use('/auth', require('./administration/features/auth/auth.routes'));

router.use('/administration', require('./administration/administration.routes'));
router.use('/people', require('./people/people.routes'));
router.use('/academic', require('./academic/academic.routes'));
router.use('/finance', require('./finance/finance.routes'));
router.use('/communication', require('./communication/communication.routes'));
router.use('/reporting', require('./reporting/reporting.routes'));

module.exports = router;
