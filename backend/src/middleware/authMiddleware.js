/**
 * RBAC Auth Middleware (Kiểm tra Token & Phân quyền 6 vai trò)
 */

exports.verifyRole = (allowedRoles = []) => {
  return (req, res, next) => {
    const userRole = req.headers['x-user-role'];
    if (!userRole) {
      return res.status(401).json({ success: false, message: 'Chưa xác thực danh tính!' });
    }
    if (allowedRoles.length && !allowedRoles.includes(userRole)) {
      return res.status(403).json({ success: false, message: 'Bạn không có quyền truy cập chức năng này!' });
    }
    next();
  };
};
