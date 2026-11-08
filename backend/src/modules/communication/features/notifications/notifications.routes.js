const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'communication',
    feature: 'notifications',
    status: 'ready',
  });
});

module.exports = router;
