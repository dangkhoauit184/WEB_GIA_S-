<!doctype html><html lang="vi"><head><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EduCenter ERP · Quản trị viên</title><link rel="stylesheet" href="/admin-assets/base.css"><link rel="stylesheet" href="/admin-assets/administration.css"><script src="/admin-assets/administration.js" defer></script>
<script src="/admin-assets/views.js" defer></script>
<script src="/admin-assets/catalog.js" defer></script>
<script src="/admin-assets/permissions.js" defer></script>
<script src="/admin-assets/audit.js" defer></script>
<script src="/admin-assets/dashboard.js" defer></script>
<script src="/admin-assets/tuition.js" defer></script>
<script src="/admin-assets/payroll.js" defer></script>
<script src="/admin-assets/leave-requests.js" defer></script>
<script src="/admin-assets/timekeeping.js" defer></script>
<script src="/admin-assets/staff.js" defer></script>
<script src="/admin-assets/schedule.js" defer></script></head><body class="administration"><section id="admin-login" class="login-wrap"><form id="admin-login-form" class="card login-card">
<div class="brand-icon">E</div><h1>EduCenter ERP</h1><p class="sub">Đăng nhập cổng Quản trị viên</p>
<label>Tên đăng nhập<input name="username" autocomplete="username" required></label>
<label>Mật khẩu<input name="password" type="password" autocomplete="current-password" required></label>
<p id="login-error" class="form-error" role="alert"></p><button class="btn p" type="submit">Đăng nhập hệ thống</button><p><a href="/">Website Trung tâm</a></p>
</form></section><div class="app" id="admin-workspace" hidden>

<!-- ═══════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════ -->
<aside class="side" id="admin-sidebar">
  <div class="brand" style="cursor:pointer" onclick="showPublicLanding()" title="Xem Website công khai của Trung tâm"><div class="brand-icon">E</div><div><b>EduCenter ERP</b><small>Quản trị Trung tâm Gia sư</small></div></div>
  <div class="side-user">
    <span class="su-role" id="sRole">Quản trị viên</span>
    <div class="su-name" id="admin-name">Trần Quang Huy</div>
    <div class="su-route" id="sRoute" style="display:none"></div>
  </div>
  <div class="nav-group">Chức năng phân hệ</div>
  <nav id="admin-nav"><button type="button" data-screen="admin-dash" onclick="showS('admin-dash')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span><span>Tổng quan & Thống kê</span></button><button type="button" data-screen="admin-courses" onclick="showS('admin-courses')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span><span>Khóa học & Xếp lớp</span></button><button type="button" data-screen="admin-payments" onclick="showS('admin-payments')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span><span>Đối soát Thanh toán Web</span></button><button type="button" data-screen="admin-payroll" onclick="showS('admin-payroll')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span>Công, Đơn giá giờ & Lương</span></button><button type="button" data-screen="admin-exceptions" onclick="showS('admin-exceptions')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></span><span>Duyệt Nghỉ lớp, Công & Mở khóa</span></button><button type="button" data-screen="admin-staff" onclick="showS('admin-staff')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Nhân sự & Hồ sơ chuyên môn</span></button><button type="button" data-screen="admin-perms" onclick="showS('admin-perms')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span><span>Phân quyền hệ thống</span></button><button type="button" data-screen="admin-catalog" onclick="showS('admin-catalog')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Danh mục & Cấu hình</span></button><button type="button" data-screen="admin-audit" onclick="showS('admin-audit')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span>Kiểm toán & Giám sát</span></button><button type="button" data-screen="admin-schedule" onclick="showS('admin-schedule')"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span>Giám sát Lịch toàn chuỗi</span></button></nav>
  <div class="side-foot">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
      <button class="btn sm" style="background:rgba(20,184,166,.16);border-color:rgba(94,234,212,.25);color:#5eead4;padding:5px 10px;font-size:11.5px;box-shadow:none" onclick="showPublicLanding()">Website Công khai</button>
      <button class="btn sm" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1);color:var(--g300);padding:5px 10px;font-size:11.5px;box-shadow:none" id="admin-logout">Đăng xuất</button>
    </div>
  </div>
</aside>

<!-- ═══════════════════════════════════════
     MAIN WRAPPER & TOPBAR
     ═══════════════════════════════════════ -->
<div class="main-wrap">
<header class="topbar">
  <div class="row">
    <button class="btn sm mob-toggle" style="display:none" id="admin-menu" aria-label="Mở menu">☰</button>
    <button class="btn sm" style="border-color:#99f6e4;background:var(--pri-ll);color:var(--pri-d);font-weight:600" onclick="showPublicLanding()" title="Mở trang Landing Page giới thiệu Trung tâm">
      <svg class="ui-ico" style="width:14px;height:14px" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
      Website Trung tâm
    </button>
    <div class="seg" id="seg" style="display:none"></div>
  </div>
  <div class="top-r">
    <span class="perm" id="permLbl">
      <svg class="ui-ico sm" style="color:var(--g500)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="topDateText">Thứ Tư, 11/11/2026</span>
    </span>
    <button class="bell-btn" id="bellBtn" onclick="toggleNotif(event)" title="Thông báo hệ thống">
      <svg class="ui-ico" style="width:16px;height:16px" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <b id="nCount" style="display:none">—</b>
    </button>
    <div style="position:relative">
      <button class="acct-btn" id="admin-avatar" onclick="toggleUserDropdown()" title="Menu tài khoản">H</button>
      <div class="user-dropdown" id="userDropdown">
        <div class="ud-info">
          <b id="udName">Trần Quang Huy</b>
          <span id="udRole" style="font-size:11px;color:var(--pri-d);font-weight:600;display:block;margin-top:2px">Quản trị viên</span>
        </div>
        <button class="ud-item" onclick="openChangePwdModal();toggleUserDropdown()">Đổi mật khẩu &amp; Hồ sơ</button>
        <button class="ud-item" onclick="openAccountManagement();toggleUserDropdown()">Quản lý tài khoản</button>
        <div style="border-top:1px solid var(--g200);margin:4px 0"></div>
        <button class="ud-item danger" onclick="handleLogout();toggleUserDropdown()">Đăng xuất</button>
      </div>
    </div>
  </div>
</header>

<main class="main" id="admin-content" tabindex="-1" aria-live="polite"></main></div></div>
<div class="modal-ov" id="modalStaffForm" onclick="if(event.target===this) closeModalStaffForm()">
  <div class="modal-box" style="max-width:520px">
    <h3>
      <span>Thêm tài khoản nhân sự &amp; Đơn giá giờ</span>
      <button style="border:0;background:0;font-size:16px;color:var(--g500);cursor:pointer" onclick="closeModalStaffForm()">✕</button>
    </h3>
    <form id="staffAddForm" onsubmit="handleStaffAddSubmit(event)">
      <div class="form-group">
        <label>Họ và tên nhân sự <span style="color:var(--err)">*</span></label>
        <input type="text" id="sfStaffName" placeholder="VD: Lê Thị Lan Hương" required>
        <span class="field-error" id="err-sfStaffName"></span>
      </div>

      <div class="row" style="gap:10px">
        <div class="form-group" style="flex:1">
          <label>Email liên hệ <span style="color:var(--err)">*</span></label>
          <input type="email" id="sfStaffEmail" placeholder="VD: huong.ltl@edu.vn" required>
          <span class="field-error" id="err-sfStaffEmail"></span>
        </div>
        <div class="form-group" style="flex:1">
          <label>Số điện thoại (10 số) <span style="color:var(--err)">*</span></label>
          <input type="tel" id="sfStaffPhone" placeholder="VD: 0912345678" required>
          <span class="field-error" id="err-sfStaffPhone"></span>
        </div>
      </div>

      <div class="row" style="gap:10px">
        <div class="form-group" style="flex:1">
          <label>Vai trò phân quyền <span style="color:var(--err)">*</span></label>
          <select onchange="onStaffRoleChanged()" id="sfStaffRole" style="font-weight:600">
            <option value="Giáo viên">Giáo viên cơ hữu / Thỉnh giảng</option>
            <option value="Gia sư">Gia sư 1:1</option>
            <option value="Trợ giảng">Trợ giảng (TA)</option>
            <option value="Nhân viên">Nhân viên (Giáo vụ / Điều phối)</option>
            <option value="Admin">Quản trị viên (Admin)</option>
          </select>
        </div>
        <div class="form-group" style="flex:1">
          <label>Đơn giá tiền/giờ (VNĐ — Admin đặt)</label>
          <input type="number" id="sfStaffHourlyRate" value="180000" step="10000">
        </div>
      </div>

      <div class="form-group">
        <label>Chuyên môn &amp; Khung giờ rảnh</label>
        <input type="text" id="sfStaffSpec" placeholder="Giới thiệu chuyên môn; lịch rảnh được nhập trong hồ sơ">
      </div>

      <div class="row"><div class="form-group" style="flex:1"><label>Tên đăng nhập</label><input id="sfStaffUsername" required maxlength="100"></div><div class="form-group" style="flex:1"><label>Mật khẩu ban đầu</label><input id="sfStaffPassword" type="password" required minlength="6" maxlength="72" autocomplete="new-password"></div></div><div class="modal-act">
        <button type="button" class="btn sm" onclick="closeModalStaffForm()">Hủy</button>
        <button type="submit" class="btn p sm">Lưu tài khoản</button>
      </div>
    </form>
  </div>
</div>
<div class="modal-ov" id="modalTeacherProfile" onclick="if(event.target===this) closeTeacherProfileModal()">
  <div class="modal-box" style="max-width:580px">
    <h3>
      <span id="tpModalTitle">Hồ sơ Chuyên môn &amp; Lịch rảnh Người dạy</span>
      <button style="border:0;background:0;font-size:16px;color:var(--g500)" onclick="closeTeacherProfileModal()">✕</button>
    </h3>
    <div id="tpModalBody"></div>
  </div>
</div>
<div class="modal-ov" id="modalAdminEnroll" onclick="if(event.target===this) closeAdminEnrollModal()">
  <div class="modal-box" style="max-width:520px">
    <h3>
      <span>Xếp học viên vào lớp</span>
      <button style="border:0;background:0;font-size:16px;color:var(--g500)" onclick="closeAdminEnrollModal()">✕</button>
    </h3>
    <form onsubmit="handleAdminEnrollSubmit(event)">
      <label>Chọn Hồ sơ Đăng ký Khóa học</label>
      <select id="aeEnrollmentSelect"></select>
      <label>Chọn Lớp học tương ứng với Khóa</label>
      <select id="aeClassSelect"></select>
      <div class="modal-act">
        <button type="button" class="btn sm" onclick="closeAdminEnrollModal()">Hủy</button>
        <button type="submit" class="btn p sm">✓ Xác nhận Xếp vào lớp</button>
      </div>
    </form>
  </div>
</div>
<div class="modal-ov" id="modalSessionDetail" onclick="if(event.target===this) closeModalSessionDetail()">
  <div class="modal-box" style="max-width:640px">
    <h3>
      <span id="dtModalTitle">Chi tiết Buổi học</span>
      <button style="border:0;background:0;font-size:16px;color:var(--g500)" onclick="closeModalSessionDetail()">✕</button>
    </h3>
    <div id="dtModalContent"></div>
    <div class="modal-act" id="dtModalActions"></div>
  </div>
</div>
<div class="modal-ov" id="modalSessionForm" onclick="if(event.target===this) closeModalSessionForm()">
  <div class="modal-box" style="max-width:600px">
    <h3>
      <span id="sfModalTitle">Xếp buổi học / Lưu lịch học bù</span>
      <button style="border:0;background:0;font-size:16px;color:var(--g500)" onclick="closeModalSessionForm()">✕</button>
    </h3>
    <form id="sessionFormElement" onsubmit="handleSessionFormSubmit(event)">
      <div id="sfConflictBanner" style="display:none" class="warn-box">
        <b style="display:block;margin-bottom:2px">⚠ Xung đột lịch giáo viên!</b>
        <span id="sfConflictText">Giáo viên đã có ca dạy khác vào khung giờ này.</span>
      </div>

      <div class="row" style="gap:10px">
        <div style="flex:1.2">
          <label>Lớp học <span style="color:var(--err)">*</span></label>
          <select id="sfClassSelect" onchange="onSfClassChanged()"></select>
        </div>
        <div style="flex:1">
          <label>Loại buổi học</label>
          <select id="sfSessionType" onchange="onSfTypeChanged()">
            <option value="upcoming">Buổi học thường định kỳ</option>
            <option value="makeup">Buổi học bù theo thỏa thuận</option>
          </select>
        </div>
      </div>

      <div id="sfMakeupWrap" style="display:none;background:var(--purple-l);border:1px solid #ddd6fe;padding:10px;border-radius:var(--rs);margin-top:8px">
        <div class="row" style="gap:10px">
          <div style="flex:1">
            <label style="margin-top:0;color:var(--purple)">Buổi gốc</label><select id="sfMakeupOrigin"></select><label style="margin-top:0;color:var(--purple)">Phạm vi bù</label>
            <select id="sfMakeupScope" onchange="onSfTypeChanged()">
              <option value="ca_nhan">Bù cá nhân</option>
              <option value="ca_lop">Bù nguyên cả lớp</option>
            </select>
          </div>
          <div style="flex:1">
            <label style="margin-top:0;color:var(--purple)">Học viên được xếp bù</label>
            <select id="sfMakeupStudent"><option value="">Chọn học viên</option></select>
          </div>
        </div>
        <label style="color:var(--purple)">Ghi chú thỏa thuận với Phụ huynh (Không cần đơn xin bù, không tính phí bù)</label>
        <input type="text" id="sfMakeupNote" placeholder="Ghi chú thỏa thuận đã chốt với phụ huynh">
      </div>

      <div class="row" style="gap:10px;margin-top:6px">
        <div style="flex:1">
          <label>Giáo viên chính <span style="color:var(--err)">*</span></label>
          <select id="sfTeacherSelect" onchange="checkSfConflicts()"></select>
        </div>
        <div style="flex:1">
          <label>Trợ giảng</label>
          <select id="sfTaSelect"></select>
        </div>
      </div>

      <div class="row" style="gap:8px;margin-top:6px">
        <div style="flex:1.2">
          <label>Ngày học <span style="color:var(--err)">*</span></label>
          <input type="date" id="sfDate" onchange="checkSfConflicts()" required>
        </div>
        <div style="flex:1">
          <label>Giờ lịch Bắt đầu <span style="color:var(--err)">*</span></label>
          <input type="time" id="sfStartTime" onchange="checkSfConflicts()" value="18:00" required>
        </div>
        <div style="flex:1">
          <label>Giờ lịch Kết thúc <span style="color:var(--err)">*</span></label>
          <input type="time" id="sfEndTime" onchange="checkSfConflicts()" value="20:00" required>
        </div>
      </div>

      <div style="background:var(--g50);padding:10px;border-radius:var(--rs);border:1px solid var(--g200);margin-top:10px">
        <div class="row-b" style="margin-bottom:6px">
          <span style="font-size:12px;font-weight:600">Hình thức học:</span>
          <div class="seg" style="font-size:11px">
            <button type="button" id="sfBtnOffline" class="on" onclick="setSfOnlineMode(false)">Offline Cơ sở</button>
            <button type="button" id="sfBtnOnline" onclick="setSfOnlineMode(true)">Online Trực tuyến</button>
          </div>
        </div>
        <div id="sfOfflineFields" class="row" style="gap:8px">
          <div style="flex:1">
            <label style="margin-top:0">Cơ sở</label>
            <select id="sfBranchSelect" onchange="onSfBranchChanged()"><option value="Thủ Đức">Thủ Đức</option><option value="Quận 1">Quận 1</option><option value="Quận 3">Quận 3</option></select>
          </div>
          <div style="flex:1">
            <label style="margin-top:0">Phòng học</label>
            <select id="sfRoomSelect" onchange="checkSfConflicts()"></select>
          </div>
        </div>
        <div id="sfOnlineFields" style="display:none">
          <label style="margin-top:0">Link Google Meet / Zoom</label>
          <input type="text" id="sfOnlineLink" placeholder="https://meet.google.com/xyz-educenter">
        </div>
      </div>

      <div id="sfRecurringWrap" style="margin-top:10px;padding:8px 10px;border:1px dashed var(--g300);border-radius:var(--rs)">
        <div class="row-b">
          <span style="font-size:12px;font-weight:600">🔁 Lặp lại hàng tuần (Tự lấy đơn giá giờ từng người)</span>
          <input type="checkbox" id="sfIsRecurring" onchange="document.getElementById('sfRecurOptions').style.display=this.checked?'block':'none'" style="width:16px;height:16px">
        </div>
        <div id="sfRecurOptions" style="display:none;margin-top:6px">
          <div class="row" style="gap:8px">
            <span style="font-size:12px;color:var(--g600)">Số buổi sinh tự động:</span>
            <input type="number" id="sfRepeatCount" value="8" min="1" max="24" style="width:70px;padding:3px 6px">
            <span style="font-size:12px;color:var(--g500)">buổi</span>
          </div>
        </div>
      </div>

      <label style="margin-top:10px">Chủ đề bài học (dự kiến)</label>
      <input type="text" id="sfTopic" placeholder="VD: Luyện giải hệ phương trình nâng cao...">

      <div class="modal-act">
        <button type="button" class="btn sm" onclick="closeModalSessionForm()">Hủy bỏ</button>
        <button type="submit" class="btn p sm">Lưu lịch học</button>
      </div>
    </form>
  </div>
</div>
<div class="notif-panel" id="notifPanel">
  <div class="np-head">
    <h3>Thông báo</h3>
    <button type="button" class="np-read-all" onclick="markAllNotifsRead()">Đọc tất cả</button>
  </div>
  <div class="np-list" id="notifList">
    <div style="padding:28px 18px;text-align:center;color:var(--g400);font-size:13px">Chưa có thông báo mới.</div>
  </div>
</div><dialog id="admin-dialog" aria-labelledby="dialog-title"><div id="dialog-content"></div></dialog>
<div id="admin-toast" role="status" hidden></div><span id="admin-page-title" hidden></span>
<div class="api-bar"><div><span class="m" id="apiMethod">GET</span> <span id="apiUri" class="mono">/api/reporting/dashboard</span></div><span id="apiStatus">EduCenter ERP Core</span></div>
</body></html>