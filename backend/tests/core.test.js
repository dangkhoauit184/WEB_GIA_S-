const assert = require('assert');
const fs = require('fs');
const path = require('path');
const express = require('express');
const bcrypt = require('bcryptjs');

const app = require('../src/app');
const { pool, withTransaction } = require('../src/config/database.config');
const { HttpError } = require('../src/shared/errors/http-error');
const { notFoundHandler, errorHandler } = require('../src/shared/middleware/error.middleware');
const { signToken, authenticate } = require('../src/shared/middleware/auth.middleware');
const {
  APP_ROLES,
  FUNCTIONS,
  resolveAppRole,
  authorizeRoles,
  authorizePermission,
} = require('../src/shared/permissions/rbac');
const { asyncHandler, sendSuccess } = require('../src/shared/utils/helpers');

async function runCoreTests() {
  const testApp = express();
  testApp.use(express.json());

  testApp.get('/test/auth', authenticate, (req, res) => {
    sendSuccess(res, { user: req.user });
  });

  testApp.get(
    '/test/role-staff',
    authenticate,
    authorizeRoles(APP_ROLES.GIAO_VU),
    (req, res) => {
      sendSuccess(res, { role: req.user.role });
    }
  );

  testApp.post(
    '/test/sqlstate-45000',
    asyncHandler(async () => {
      const err = new Error('Phòng đã có buổi học cùng khung giờ.');
      err.sqlState = '45000';
      err.sqlMessage = 'Phòng đã có buổi học cùng khung giờ.';
      throw err;
    })
  );

  testApp.post(
    '/test/dup-entry',
    asyncHandler(async () => {
      const err = new Error('Duplicate entry');
      err.code = 'ER_DUP_ENTRY';
      throw err;
    })
  );

  testApp.get(
    '/test/db-perm-edit',
    authenticate,
    authorizePermission(FUNCTIONS.VAN_HANH_BUOI_HOC, 'edit'),
    (req, res) => {
      sendSuccess(res, { allowed: true });
    }
  );

  testApp.get(
    '/test/db-perm-add',
    authenticate,
    authorizePermission(FUNCTIONS.VAN_HANH_BUOI_HOC, 'add'),
    (req, res) => {
      sendSuccess(res, { allowed: true });
    }
  );

  testApp.post(
    '/test/db-trigger-45000',
    asyncHandler(async (req, res) => {
      await withTransaction(async (conn) => {
        await conn.query('CALL sp_kiem_tra_hoc_vu(1, 999999, NULL)');
      });
      sendSuccess(res);
    })
  );

  testApp.use('/main', app);
  testApp.use(notFoundHandler);
  testApp.use(errorHandler);

  const server = await new Promise((resolve) => {
    const s = testApp.listen(0, '127.0.0.1', () => resolve(s));
  });
  const baseUrl = `http://127.0.0.1:${server.address().port}`;

  try {
    // 1. Kiểm tra 6 Modules & 21 Features
    const modulesPath = path.resolve(__dirname, '../../shared/modules.json');
    const modulesMap = JSON.parse(fs.readFileSync(modulesPath, 'utf8'));
    let checkedFeatures = 0;
    for (const [moduleName, features] of Object.entries(modulesMap)) {
      for (const featureName of features) {
        const res = await fetch(`${baseUrl}/main/api/${moduleName}/${featureName}/status`);
        const body = await res.json();
        assert.strictEqual(res.status, 200);
        assert.strictEqual(body.success, true);
        assert.strictEqual(body.module, moduleName);
        assert.strictEqual(body.feature, featureName);
        checkedFeatures++;
      }
    }
    assert.strictEqual(checkedFeatures, 21);

    // 2. Kiểm tra 404 Not Found
    const res404 = await fetch(`${baseUrl}/main/api/not-found-endpoint`);
    const body404 = await res404.json();
    assert.strictEqual(res404.status, 404);
    assert.strictEqual(body404.code, 'NOT_FOUND');

    // 3. Kiểm tra JWT Auth middleware
    const res401Missing = await fetch(`${baseUrl}/test/auth`);
    assert.strictEqual(res401Missing.status, 401);

    const res401Invalid = await fetch(`${baseUrl}/test/auth`, {
      headers: { Authorization: 'Bearer invalid.jwt.token' },
    });
    assert.strictEqual(res401Invalid.status, 401);

    const adminToken = signToken({
      accountId: 1,
      userId: 1,
      groupId: 1,
      groupName: 'Admin',
      role: APP_ROLES.ADMIN,
      username: 'admin',
    });
    const res200Auth = await fetch(`${baseUrl}/test/auth`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    assert.strictEqual(res200Auth.status, 200);

    // 4. Kiểm tra ánh xạ 6 vai trò & authorizeRoles
    assert.strictEqual(resolveAppRole({ groupName: 'Admin' }), APP_ROLES.ADMIN);
    assert.strictEqual(resolveAppRole({ groupName: 'NhanVien' }), APP_ROLES.GIAO_VU);
    assert.strictEqual(resolveAppRole({ groupName: 'GiangVien', phanLoai: 'giao_vien' }), APP_ROLES.GIAO_VIEN);
    assert.strictEqual(resolveAppRole({ groupName: 'GiangVien', phanLoai: 'gia_su' }), APP_ROLES.GIA_SU);
    assert.strictEqual(resolveAppRole({ groupName: 'TroGiang', phanLoai: 'tro_giang' }), APP_ROLES.TRO_GIANG);
    assert.strictEqual(resolveAppRole({ groupName: 'PhuHuynh' }), APP_ROLES.PHU_HUYNH);

    const parentToken = signToken({
      accountId: 6,
      userId: 6,
      groupId: 5,
      groupName: 'PhuHuynh',
      role: APP_ROLES.PHU_HUYNH,
    });
    const staffToken = signToken({
      accountId: 2,
      userId: 2,
      groupId: 2,
      groupName: 'NhanVien',
      role: APP_ROLES.GIAO_VU,
    });

    const parentForbidden = await fetch(`${baseUrl}/test/role-staff`, {
      headers: { Authorization: `Bearer ${parentToken}` },
    });
    assert.strictEqual(parentForbidden.status, 403);

    const staffAllowed = await fetch(`${baseUrl}/test/role-staff`, {
      headers: { Authorization: `Bearer ${staffToken}` },
    });
    assert.strictEqual(staffAllowed.status, 200);

    const adminBypass = await fetch(`${baseUrl}/test/role-staff`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    assert.strictEqual(adminBypass.status, 200);

    // 5. Kiểm tra bắt lỗi SIGNAL SQLSTATE '45000' & ER_DUP_ENTRY
    const res45000 = await fetch(`${baseUrl}/test/sqlstate-45000`, { method: 'POST' });
    const body45000 = await res45000.json();
    assert.strictEqual(res45000.status, 400);
    assert.strictEqual(body45000.code, 'BUSINESS_RULE_VIOLATION');
    assert.strictEqual(body45000.message, 'Phòng đã có buổi học cùng khung giờ.');

    const resDup = await fetch(`${baseUrl}/test/dup-entry`, { method: 'POST' });
    const bodyDup = await resDup.json();
    assert.strictEqual(resDup.status, 409);
    assert.strictEqual(bodyDup.code, 'DUPLICATE_ENTRY');

    // 6. Kiểm tra bcryptjs
    const hash = await bcrypt.hash('123456', 8);
    assert.strictEqual(await bcrypt.compare('123456', hash), true);
    assert.strictEqual(await bcrypt.compare('wrong', hash), false);

    // 7. Nếu có kết nối MySQL (TEST_WITH_DB=1), kiểm tra trực tiếp trên DB thật
    if (process.env.TEST_WITH_DB === '1') {
      const healthRes = await fetch(`${baseUrl}/main/api/health`);
      const healthBody = await healthRes.json();
      assert.strictEqual(healthRes.status, 200);
      assert.strictEqual(healthBody.db.timeZone, '+07:00');
      assert.strictEqual(healthBody.db.isolationLevel, 'READ-COMMITTED');

      const taToken = signToken({
        accountId: 5,
        userId: 5,
        groupId: 4,
        groupName: 'TroGiang',
        role: APP_ROLES.TRO_GIANG,
      });
      const taEdit = await fetch(`${baseUrl}/test/db-perm-edit`, {
        headers: { Authorization: `Bearer ${taToken}` },
      });
      assert.strictEqual(taEdit.status, 200);

      const taAdd = await fetch(`${baseUrl}/test/db-perm-add`, {
        headers: { Authorization: `Bearer ${taToken}` },
      });
      assert.strictEqual(taAdd.status, 403);

      const dbTriggerRes = await fetch(`${baseUrl}/test/db-trigger-45000`, { method: 'POST' });
      const dbTriggerBody = await dbTriggerRes.json();
      assert.strictEqual(dbTriggerRes.status, 400);
      assert.strictEqual(dbTriggerBody.code, 'BUSINESS_RULE_VIOLATION');
    }

    console.log('PASS: Backend Core test suite passed (6 modules, 21 features, JWT, RBAC 6 roles, SQLSTATE 45000, bcryptjs).');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    try {
      await pool.end();
    } catch (_) {
      // Ignore pool shutdown error if DB was not started
    }
  }
}

runCoreTests().catch((err) => {
  console.error('Backend Core test failed:', err);
  process.exit(1);
});
