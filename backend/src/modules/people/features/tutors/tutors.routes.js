const express = require('express');
const { authenticate, requireStaffOrAdmin } = require('./tutors.middleware');
const tutorsController = require('./tutors.controller');

const router = express.Router();

router.get('/status', (req, res) => {
  res.json({
    success: true,
    module: 'people',
    feature: 'tutors',
    status: 'ready',
  });
});

router.get('/match', authenticate, tutorsController.matchTutors);
router.get('/', authenticate, tutorsController.listTutors);
router.get('/:id', authenticate, tutorsController.getTutorDetail);
router.post('/', ...requireStaffOrAdmin, tutorsController.createTutor);
router.put('/:id', ...requireStaffOrAdmin, tutorsController.updateTutor);

module.exports = router;
