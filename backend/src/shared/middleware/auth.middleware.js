const jwt = require('jsonwebtoken');
const config = require('../../config/app.config');
const { HttpError } = require('../errors/http-error');
const { GROUP_TO_DEFAULT_ROLE } = require('../../../../shared/constants/roles');

function signToken(payload, options = {}) {
  return jwt.sign(payload, config.jwt.secret, {
    expiresIn: config.jwt.expiresIn,
    ...options,
  });
}

function verifyToken(token) {
  return jwt.verify(token, config.jwt.secret);
}

function authenticate(req, res, next) {
  const authHeader = req.headers.authorization || '';
  const token = authHeader.startsWith('Bearer ') ? authHeader.slice(7).trim() : null;

  if (!token) {
    return next(new HttpError(401, 'Vui lòng đăng nhập để tiếp tục.', 'UNAUTHORIZED'));
  }

  try {
    const decoded = verifyToken(token);
    const role = decoded.role || GROUP_TO_DEFAULT_ROLE[decoded.groupName] || decoded.groupName;
    req.user = {
      ...decoded,
      role,
    };
    return next();
  } catch (error) {
    return next(new HttpError(401, 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.', 'INVALID_TOKEN'));
  }
}

module.exports = {
  signToken,
  verifyToken,
  authenticate,
};
