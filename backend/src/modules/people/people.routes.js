const express = require('express');

const router = express.Router();

router.use('/students', require('./features/students/students.routes'));
router.use('/teachers', require('./features/teachers/teachers.routes'));
router.use('/tutors', require('./features/tutors/tutors.routes'));
router.use('/parents', require('./features/parents/parents.routes'));

module.exports = router;
