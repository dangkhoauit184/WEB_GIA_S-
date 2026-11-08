const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./parents.middleware');
const parentsController = require('./parents.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'people',
    feature: 'parents',
    status: 'ready',
  });
});

router.get('/', authenticate, parentsController.listParents);
router.get('/:id', authenticate, parentsController.getParentDetail);
router.post('/', ...requireStaffOrAdmin, parentsController.createParent);
router.put('/:id', ...requireStaffOrAdmin, parentsController.updateParent);

module.exports = router;
