const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'attendance',
    status: 'ready',
  });
});

module.exports = router;
