const { HttpError } = require('../errors/http-error');

function notFoundHandler(req, res, next) {
  next(new HttpError(404, `Không tìm thấy đường dẫn: ${req.method} ${req.originalUrl}`, 'NOT_FOUND'));
}

function errorHandler(err, req, res, next) {
  // 1. Bắt lỗi nghiệp vụ từ MySQL Triggers / Stored Procedures (SIGNAL SQLSTATE '45000')
  if (err && err.sqlState === '45000') {
    return res.status(400).json({
      success: false,
      code: 'BUSINESS_RULE_VIOLATION',
      message: err.sqlMessage || err.message,
    });
  }

  // 2. Bắt lỗi trùng dữ liệu (UNIQUE KEY) trong MySQL
  if (err && err.code === 'ER_DUP_ENTRY') {
    return res.status(409).json({
      success: false,
      code: 'DUPLICATE_ENTRY',
      message: 'Dữ liệu đã tồn tại trong hệ thống.',
    });
  }

  // 3. Bắt lỗi ràng buộc khóa ngoại (FOREIGN KEY) hoặc CHECK constraint trong MySQL
  if (err && (err.code === 'ER_NO_REFERENCED_ROW_2' || err.code === 'ER_ROW_IS_REFERENCED_2')) {
    return res.status(400).json({
      success: false,
      code: 'FOREIGN_KEY_CONSTRAINT',
      message: 'Dữ liệu liên kết không hợp lệ hoặc đang được sử dụng.',
    });
  }

  if (err && err.code === 'ER_CHECK_CONSTRAINT_VIOLATED') {
    return res.status(400).json({
      success: false,
      code: 'CHECK_CONSTRAINT_VIOLATED',
      message: err.sqlMessage || 'Dữ liệu vi phạm ràng buộc kiểm tra của hệ thống.',
    });
  }

  // 4. Lỗi chủ động ném ra bằng HttpError hoặc lỗi hệ thống
  const statusCode = err && err.statusCode ? Number(err.statusCode) : 500;
  return res.status(statusCode).json({
    success: false,
    code: (err && err.code) || 'INTERNAL_SERVER_ERROR',
    message: statusCode === 500 ? 'Lỗi máy chủ nội bộ.' : err.message,
    details: (err && err.details) || undefined,
  });
}

module.exports = {
  notFoundHandler,
  errorHandler,
};
