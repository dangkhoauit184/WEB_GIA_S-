const express = require('express');

const router = express.Router();

router.use('/admissions', require('./features/admissions/admissions.routes'));
router.use('/academic-affairs', require('./features/academic-affairs/academic-affairs.routes'));
router.use('/classes', require('./features/classes/classes.routes'));
router.use('/scheduling', require('./features/scheduling/scheduling.routes'));
router.use('/attendance', require('./features/attendance/attendance.routes'));
router.use('/leave-requests', require('./features/leave-requests/leave-requests.routes'));
router.use('/makeup-classes', require('./features/makeup-classes/makeup-classes.routes'));

module.exports = router;
