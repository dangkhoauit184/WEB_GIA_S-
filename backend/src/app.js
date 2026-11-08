const express = require('express');
const cors = require('cors');
const modulesRouter = require('./modules');
const { checkDatabaseConnection } = require('./config/database.config');
const { notFoundHandler, errorHandler } = require('./shared/middleware/error.middleware');

const app = express();

app.use(cors());
app.use(express.json({ limit: '5mb' }));
app.use(express.urlencoded({ extended: true }));

app.get('/api/health', async (req, res, next) => {
  try {
    const dbSession = await checkDatabaseConnection();
    res.json({
      success: true,
      message: 'EduCenter ERP API & MySQL đang hoạt động bình thường.',
      db: dbSession,
      timestamp: new Date().toISOString(),
    });
  } catch (error) {
    next(error);
  }
});

app.use('/api', modulesRouter);

app.use(notFoundHandler);
app.use(errorHandler);

module.exports = app;
