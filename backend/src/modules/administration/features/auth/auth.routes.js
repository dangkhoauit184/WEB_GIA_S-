const express = require('express');
const { requireAuth } = require('./auth.middleware');
const authController = require('./auth.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'administration',
    feature: 'auth',
    status: 'ready',
  });
});

router.post('/login', authController.login);
router.get('/me', requireAuth, authController.getMe);
router.put('/change-password', requireAuth, authController.changePassword);
router.post('/change-password', requireAuth, authController.changePassword);
router.post('/logout', requireAuth, authController.logout);

module.exports = router;
