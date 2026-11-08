const asyncHandler = (fn) => (req, res, next) => {
  Promise.resolve(fn(req, res, next)).catch(next);
};

function sendSuccess(res, data = null, message = 'Thành công', statusCode = 200) {
  return res.status(statusCode).json({
    success: true,
    message,
    data,
  });
}

module.exports = {
  asyncHandler,
  sendSuccess,
};
