const express = require('express');

const router = express.Router();

router.use('/dashboard', require('./features/dashboard/dashboard.routes'));

module.exports = router;
