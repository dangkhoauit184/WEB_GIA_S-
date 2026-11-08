const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'leave-requests',
    status: 'ready',
  });
});

module.exports = router;
