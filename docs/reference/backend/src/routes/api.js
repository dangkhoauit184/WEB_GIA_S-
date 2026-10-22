const express = require('express');
const router = express.Router();
const scheduleController = require('../controllers/scheduleController');

// Schedule & Calendar Endpoints
router.get('/schedule/sessions', scheduleController.getSessions);
router.post('/schedule/sessions', scheduleController.createSession);

// Leave Requests
router.get('/leaves', (req, res) => {
  res.json({ success: true, data: [] });
});

// Payroll
router.get('/payroll/summary', (req, res) => {
  res.json({ success: true, month: '10/2026', total: '11.700.000đ' });
});

module.exports = router;

