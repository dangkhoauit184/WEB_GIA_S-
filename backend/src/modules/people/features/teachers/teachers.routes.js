const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./teachers.middleware');
const teachersController = require('./teachers.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'people',
    feature: 'teachers',
    status: 'ready',
  });
});

router.get('/match', authenticate, teachersController.matchTeachers);
router.get('/', authenticate, teachersController.listTeachers);
router.get('/:id', authenticate, teachersController.getTeacherDetail);
router.post('/', ...requireStaffOrAdmin, teachersController.createTeacher);
router.put('/:id', ...requireStaffOrAdmin, teachersController.updateTeacher);

module.exports = router;
