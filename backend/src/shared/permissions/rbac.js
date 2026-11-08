const { pool } = require('../../config/database.config');
const { HttpError } = require('../errors/http-error');
const {
  ROLE_GROUPS,
  APP_ROLES,
  GROUP_TO_DEFAULT_ROLE,
  FUNCTIONS,
  PERMISSION_ACTIONS,
} = require('../../../../shared/constants/roles');

function resolveAppRole({ groupName, phanLoai = '' } = {}) {
  if (groupName === ROLE_GROUPS.ADMIN || groupName === APP_ROLES.ADMIN) {
    return APP_ROLES.ADMIN;
  }
  if (groupName === ROLE_GROUPS.NHAN_VIEN || groupName === APP_ROLES.GIAO_VU) {
    return APP_ROLES.GIAO_VU;
  }
  if (groupName === ROLE_GROUPS.TRO_GIANG || groupName === APP_ROLES.TRO_GIANG) {
    return APP_ROLES.TRO_GIANG;
  }
  if (groupName === ROLE_GROUPS.PHU_HUYNH || groupName === APP_ROLES.PHU_HUYNH) {
    return APP_ROLES.PHU_HUYNH;
  }
  if (groupName === ROLE_GROUPS.GIANG_VIEN) {
    const types = String(phanLoai)
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);
    if (types.includes('giao_vien')) return APP_ROLES.GIAO_VIEN;
    if (types.includes('gia_su')) return APP_ROLES.GIA_SU;
    if (types.includes('tro_giang')) return APP_ROLES.TRO_GIANG;
    return APP_ROLES.GIAO_VIEN;
  }
  return GROUP_TO_DEFAULT_ROLE[groupName] || groupName || null;
}

function authorizeRoles(...allowedRoles) {
  const normalizedAllowed = new Set(allowedRoles.flat());

  return (req, res, next) => {
    if (!req.user) {
      return next(new HttpError(401, 'Chưa xác thực người dùng.', 'UNAUTHORIZED'));
    }

    const userRole = resolveAppRole({
      groupName: req.user.role || req.user.groupName,
      phanLoai: req.user.phanLoai,
    });

    if (userRole === APP_ROLES.ADMIN || req.user.groupName === ROLE_GROUPS.ADMIN) {
      return next();
    }

    if (
      normalizedAllowed.has(userRole) ||
      normalizedAllowed.has(req.user.role) ||
      normalizedAllowed.has(req.user.groupName)
    ) {
      return next();
    }

    return next(new HttpError(403, 'Bạn không có quyền thực hiện thao tác này.', 'FORBIDDEN'));
  };
}

function authorizePermission(functionName, action = 'view') {
  if (!PERMISSION_ACTIONS.includes(action)) {
    throw new Error(`Hành động phân quyền không hợp lệ: ${action}`);
  }

  return async (req, res, next) => {
    try {
      if (!req.user) {
        throw new HttpError(401, 'Chưa xác thực người dùng.', 'UNAUTHORIZED');
      }

      if (req.user.role === APP_ROLES.ADMIN || req.user.groupName === ROLE_GROUPS.ADMIN) {
        return next();
      }

      if (!req.user.groupId) {
        throw new HttpError(403, 'Tài khoản chưa được gán nhóm quyền.', 'FORBIDDEN');
      }

      const [rows] = await pool.query(
        `SELECT rgf.\`${action}\` AS allowed
         FROM role_group_function rgf
         JOIN \`function\` f ON f.function_id = rgf.function_id
         WHERE rgf.group_id = ?
           AND f.name_function = ?
           AND rgf.is_deleted = 0
           AND f.is_deleted = 0
         LIMIT 1`,
        [req.user.groupId, functionName]
      );

      if (!rows.length || Number(rows[0].allowed) !== 1) {
        throw new HttpError(
          403,
          `Bạn không có quyền '${action}' trên phân hệ '${functionName}'.`,
          'FORBIDDEN'
        );
      }

      return next();
    } catch (error) {
      return next(error);
    }
  };
}

module.exports = {
  ROLE_GROUPS,
  APP_ROLES,
  FUNCTIONS,
  PERMISSION_ACTIONS,
  resolveAppRole,
  authorizeRoles,
  authorizePermission,
};
