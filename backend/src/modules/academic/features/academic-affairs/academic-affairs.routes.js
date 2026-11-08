const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'academic-affairs',
    status: 'ready',
  });
});

module.exports = router;
