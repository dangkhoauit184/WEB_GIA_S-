const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./scheduling.middleware');
const schedulingController = require('./scheduling.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'scheduling',
    status: 'ready',
  });
});

router.get('/weekly', authenticate, schedulingController.listWeeklySchedules);
router.post('/weekly', ...requireStaffOrAdmin, schedulingController.createWeeklySchedule);

router.get('/sessions', authenticate, schedulingController.listSessions);
router.post('/generate-sessions', ...requireStaffOrAdmin, schedulingController.generateSessions);
router.post('/sessions/:id/assign', ...requireStaffOrAdmin, schedulingController.assignPersonnel);
router.put('/sessions/:id', ...requireStaffOrAdmin, schedulingController.updateSession);

module.exports = router;
