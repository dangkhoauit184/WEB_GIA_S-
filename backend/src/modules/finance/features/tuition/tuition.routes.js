const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'finance',
    feature: 'tuition',
    status: 'ready',
  });
});

module.exports = router;
