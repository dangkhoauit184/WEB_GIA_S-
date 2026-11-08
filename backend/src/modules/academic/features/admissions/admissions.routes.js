const express = require('express');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'academic',
    feature: 'admissions',
    status: 'ready',
  });
});

module.exports = router;
