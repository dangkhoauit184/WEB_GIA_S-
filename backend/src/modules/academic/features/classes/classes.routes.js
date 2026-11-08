const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./classes.middleware');
const classesController = require('./classes.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'classes',
    status: 'ready',
  });
});

router.get('/', authenticate, classesController.listClasses);
router.get('/:id', authenticate, classesController.getClassDetail);
router.post('/', ...requireStaffOrAdmin, classesController.createClass);
router.put('/:id', ...requireStaffOrAdmin, classesController.updateClass);

router.get('/:id/enrollments', authenticate, classesController.listEnrollments);
router.post('/:id/enrollments', ...requireStaffOrAdmin, classesController.enrollStudent);
router.put('/:id/enrollments/:enrollmentId', ...requireStaffOrAdmin, classesController.updateEnrollment);

module.exports = router;
