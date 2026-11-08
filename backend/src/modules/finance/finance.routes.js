const express = require('express');

const router = express.Router();

router.use('/tuition', require('./features/tuition/tuition.routes'));
router.use('/timekeeping', require('./features/timekeeping/timekeeping.routes'));
router.use('/payroll', require('./features/payroll/payroll.routes'));

module.exports = router;
