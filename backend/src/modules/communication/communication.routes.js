const express = require('express');

const router = express.Router();

router.use('/notifications', require('./features/notifications/notifications.routes'));

module.exports = router;
