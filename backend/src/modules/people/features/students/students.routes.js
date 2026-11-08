const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./students.middleware');
const studentsController = require('./students.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'people',
    feature: 'students',
    status: 'ready',
  });
});

router.get('/', authenticate, studentsController.listStudents);
router.get('/:id', authenticate, studentsController.getStudentDetail);
router.post('/', ...requireStaffOrAdmin, studentsController.createStudent);
router.put('/:id', ...requireStaffOrAdmin, studentsController.updateStudent);
router.delete('/:id', ...requireStaffOrAdmin, studentsController.deleteStudent);

module.exports = router;
