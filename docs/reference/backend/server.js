/**
 * EDUCENTER ERP - BACKEND API SERVER SKELETON
 * Node.js / Express
 */

const express = require('express');
const cors = require('cors');
const apiRoutes = require('./src/routes/api');

const app = express();
const PORT = process.env.PORT || 5000;

// Middlewares
app.use(cors());
app.use(express.json());

// API Routes
app.use('/api', apiRoutes);

// Root Health Check
app.get('/', (req, res) => {
  res.json({
    status: 'online',
    message: 'EduCenter ERP API Core is operational',
    timestamp: new Date().toISOString(),
    version: '2.0.0'
  });
});

// Start Server
app.listen(PORT, () => {
  console.log(`[EduCenter ERP] Server is running on port ${PORT}`);
  console.log(`[EduCenter ERP] API endpoints available at http://localhost:${PORT}/api`);
});

