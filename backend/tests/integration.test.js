const assert = require('assert');
const app = require('../src/app');
const { pool } = require('../src/config/database.config');
const { bootstrapAdmin } = require('../src/modules/administration/features/auth/auth.service');

async function runIntegrationTests() {
  const server = await new Promise((resolve) => {
    const s = app.listen(0, '127.0.0.1', () => resolve(s));
  });
  const baseUrl = `http://127.0.0.1:${server.address().port}`;
  const suffix = Date.now().toString().slice(-6);

  try {
    // 1. Kiểm tra /api/health
    console.log('[1/6] Kiểm tra /api/health & MySQL session...');
    const healthRes = await fetch(`${baseUrl}/api/health`);
    const healthBody = await healthRes.json();
    assert.strictEqual(healthRes.status, 200);
    assert.strictEqual(healthBody.success, true);
    assert.strictEqual(healthBody.db.timeZone, '+07:00');
    assert.strictEqual(healthBody.db.isolationLevel, 'READ-COMMITTED');

    // 2. Bootstrap Admin & kiểm tra luồng Đăng nhập -> Me -> Đổi mật khẩu -> Đăng xuất
    console.log('[2/6] Kiểm tra Bootstrap Admin, Login, Me, Change Password, Logout...');
    const adminUsername = `admin_test_${suffix}`;
    await bootstrapAdmin({
      username: adminUsername,
      password: 'InitPassword@123',
      hoTen: 'Admin Kiểm Thử',
      email: `admin.${suffix}@educenter.local`,
      sdt: `091${suffix}1`,
    });

    const loginRes = await fetch(`${baseUrl}/api/administration/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: adminUsername, password: 'InitPassword@123' }),
    });
    const loginBody = await loginRes.json();
    assert.strictEqual(loginRes.status, 200);
    assert.ok(loginBody.data.token);
    assert.strictEqual(loginBody.data.user.role, 'ADMIN');

    const token = loginBody.data.token;
    const authHeaders = {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    };

    const meRes = await fetch(`${baseUrl}/api/administration/auth/me`, { headers: authHeaders });
    const meBody = await meRes.json();
    assert.strictEqual(meRes.status, 200);
    assert.strictEqual(meBody.data.user.username, adminUsername);

    const changePwdRes = await fetch(`${baseUrl}/api/administration/auth/change-password`, {
      method: 'PUT',
      headers: authHeaders,
      body: JSON.stringify({
        currentPassword: 'InitPassword@123',
        newPassword: 'NewPassword@456',
      }),
    });
    assert.strictEqual(changePwdRes.status, 200);

    const reloginRes = await fetch(`${baseUrl}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: adminUsername, password: 'NewPassword@456' }),
    });
    assert.strictEqual(reloginRes.status, 200);

    const logoutRes = await fetch(`${baseUrl}/api/administration/auth/logout`, {
      method: 'POST',
      headers: authHeaders,
    });
    assert.strictEqual(logoutRes.status, 200);

    // 3. Kiểm tra API Phụ huynh & Học viên
    console.log('[3/6] Kiểm tra API Quản lý Phụ huynh & Học viên...');
    const parentRes = await fetch(`${baseUrl}/api/people/parents`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        hoTen: `Phụ Huynh ${suffix}`,
        email: `ph.${suffix}@educenter.local`,
        sdt: `092${suffix}2`,
        diaChi: 'Thủ Đức, TP.HCM',
        khuVucId: 1,
        username: `ph_${suffix}`,
        password: 'Parent@123',
      }),
    });
    const parentBody = await parentRes.json();
    assert.strictEqual(parentRes.status, 201);
    const parentUserId = parentBody.data.userId;

    const student1Res = await fetch(`${baseUrl}/api/people/students`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        phuHuynhUserId: parentUserId,
        maHv: `HV-${suffix}-1`,
        hoTen: `Học Viên Một ${suffix}`,
        gioiTinh: 'nam',
        capLopId: 9,
        khuVucId: 1,
      }),
    });
    const student1Body = await student1Res.json();
    assert.strictEqual(student1Res.status, 201);
    const student1Id = student1Body.data.id;

    const student2Res = await fetch(`${baseUrl}/api/people/students`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        phuHuynhUserId: parentUserId,
        maHv: `HV-${suffix}-2`,
        hoTen: `Học Viên Hai ${suffix}`,
        gioiTinh: 'nu',
        capLopId: 9,
        khuVucId: 1,
      }),
    });
    const student2Body = await student2Res.json();
    assert.strictEqual(student2Res.status, 201);
    const student2Id = student2Body.data.id;

    // 4. Kiểm tra API Người dạy & Gợi ý ghép BR02
    console.log('[4/6] Kiểm tra API Quản lý Người dạy & Gợi ý ghép (BR02)...');
    const teacherRes = await fetch(`${baseUrl}/api/people/teachers`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        maNs: `NS-${suffix}`,
        hoTen: `Giáo Viên Toán ${suffix}`,
        email: `gv.${suffix}@educenter.local`,
        sdt: `093${suffix}3`,
        phanLoai: ['giao_vien', 'gia_su'],
        hinhThucDay: ['online', 'offline'],
        donGiaGioChuan: 150000,
        kinhNghiemNam: 4,
        monDays: [
          { monHocId: 1, capLopTu: 6, capLopDen: 9, hocPhiBuoiDeXuat: 180000 },
        ],
        khuVucIds: [1],
        lichRanhs: [
          { thu: 3, gioBatDau: '18:00:00', gioKetThuc: '21:00:00' },
          { thu: 5, gioBatDau: '18:00:00', gioKetThuc: '21:00:00' },
        ],
      }),
    });
    const teacherBody = await teacherRes.json();
    assert.strictEqual(teacherRes.status, 201);
    const teacherId = teacherBody.data.id;

    const matchRes = await fetch(
      `${baseUrl}/api/people/teachers/match?monHocId=1&capLopId=9&khuVucId=1&nganSachMax=200000&thu=3&gioBatDau=18:00:00&gioKetThuc=20:00:00`,
      { headers: authHeaders }
    );
    const matchBody = await matchRes.json();
    assert.strictEqual(matchRes.status, 200);
    assert.ok(matchBody.data.some((t) => t.id === teacherId));

    // 5. Kiểm tra API Tạo lớp & Ghi danh (kèm bắt lỗi vượt sĩ số tối đa)
    console.log('[5/6] Kiểm tra API Tạo lớp, Ghi danh & Chặn vượt sĩ số tối đa...');
    const classRes = await fetch(`${baseUrl}/api/academic/classes`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        maLop: `LOP-${suffix}`,
        tenLop: `Lớp Toán 9 Kèm 1:1 (${suffix})`,
        loaiLop: 'kem_1_1',
        monHocId: 1,
        capLopId: 9,
        nguoiDayChinhId: teacherId,
        hinhThuc: 'offline',
        siSoToiThieu: 1,
        siSoToiDa: 1,
        cachThuPhi: 'theo_buoi',
        hocPhiBuoi: 180000,
        mucTraChinh: 140000,
        cachTinhLuong: 'theo_buoi',
        soBuoiDuKien: 8,
        ngayBatDau: '2026-11-01',
        trangThai: 'dang_hoc',
      }),
    });
    const classBody = await classRes.json();
    assert.strictEqual(classRes.status, 201);
    const lopId = classBody.data.id;

    const enroll1Res = await fetch(`${baseUrl}/api/academic/classes/${lopId}/enrollments`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        hocVienId: student1Id,
        ngayVao: '2026-11-01',
        trangThai: 'dang_hoc',
      }),
    });
    assert.strictEqual(enroll1Res.status, 201);

    // Học viên thứ 2 ghi danh vào lớp kèm 1:1 (siSoToiDa = 1) phải bị chặn bởi Trigger MySQL (400 BUSINESS_RULE_VIOLATION)
    const enroll2Res = await fetch(`${baseUrl}/api/academic/classes/${lopId}/enrollments`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        hocVienId: student2Id,
        ngayVao: '2026-11-01',
        trangThai: 'dang_hoc',
      }),
    });
    const enroll2Body = await enroll2Res.json();
    assert.strictEqual(enroll2Res.status, 400);
    assert.strictEqual(enroll2Body.code, 'BUSINESS_RULE_VIOLATION');

    // 6. Kiểm tra API Xếp lịch tuần cố định & Sinh chuỗi buổi học tự động (sp_sinh_buoi_hoc - BR03)
    console.log('[6/6] Kiểm tra API Xếp lịch tuần & Sinh chuỗi buổi học (sp_sinh_buoi_hoc)...');
    const weeklyRes = await fetch(`${baseUrl}/api/academic/scheduling/weekly`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        lopId,
        thu: 3, // Thứ 3
        gioBatDau: '18:00:00',
        gioKetThuc: '20:00:00',
        hieuLucTu: '2026-11-01',
      }),
    });
    assert.strictEqual(weeklyRes.status, 201);

    const genRes = await fetch(`${baseUrl}/api/academic/scheduling/generate-sessions`, {
      method: 'POST',
      headers: authHeaders,
      body: JSON.stringify({
        lopId,
        tuNgay: '2026-11-01',
        denNgay: '2026-11-30',
      }),
    });
    const genBody = await genRes.json();
    assert.strictEqual(genRes.status, 201);
    assert.ok(genBody.data.length >= 4);
    assert.strictEqual(genBody.data[0].lopId, lopId);
    assert.strictEqual(genBody.data[0].nguoiDayChinhUserId, teacherBody.data.userId);

    console.log('PASS: Toàn bộ 6/6 luồng tích hợp MySQL (Health, Auth, Phụ huynh/Học viên, Người dạy BR02, Lớp/Ghi danh, Xếp lịch/sp_sinh_buoi_hoc BR03) đều đạt 100%!');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    await pool.end();
  }
}

runIntegrationTests().catch((err) => {
  console.error('Integration test failed:', err);
  process.exit(1);
});
