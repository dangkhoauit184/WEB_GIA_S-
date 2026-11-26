<?php $isAdmissions = ($portal ?? 'admin') === 'admissions'; $portalTitle = $isAdmissions ? 'Giáo vụ / Điều phối' : 'Quản trị viên'; ?>
<!doctype html><html lang="vi"><head><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EduCenter ERP · <?= $portalTitle ?></title><link rel="stylesheet" href="/admin-assets/base.css"><link rel="stylesheet" href="/admin-assets/administration.css"><link rel="stylesheet" href="/admin-assets/dashboard.css"><script src="/admin-assets/administration.js" defer></script><script src="/admin-assets/academic-affairs.js" defer></script><link rel="stylesheet" href="/admin-assets/academic-affairs.css">
<?php if (!$isAdmissions): ?><script src="/admin-assets/views.js" defer></script>
<script src="/admin-assets/catalog.js" defer></script>
<script src="/admin-assets/permissions.js" defer></script>
<script src="/admin-assets/audit.js" defer></script>
<script src="/admin-assets/tuition.js" defer></script>
<script src="/admin-assets/payroll.js" defer></script>
<script src="/admin-assets/leave-requests.js" defer></script>
<script src="/admin-assets/timekeeping.js" defer></script>
<script src="/admin-assets/staff.js" defer></script>
<?php endif; ?><script src="/admin-assets/dashboard.js" defer></script><script src="/admin-assets/schedule.js" defer></script><script src="/admin-assets/shift-recruitment.js" defer></script><script src="/admin-assets/admissions.js" defer></script><script src="/admin-assets/students.js" defer></script><script src="/admin-assets/parents.js" defer></script><script src="/admin-assets/classes.js" defer></script><link rel="stylesheet" href="/admin-assets/admissions.css"><?php if ($isAdmissions): ?><script src="/admin-assets/leave-requests.js" defer></script><script src="/admin-assets/tuition.js" defer></script><?php endif; ?></head><body class="administration" data-portal="<?= $isAdmissions ? 'admissions' : 'admin' ?>"><section id="admin-login" class="login-wrap" role="status"><div class="card login-card">
<div class="brand-icon">E</div><h1>EduCenter ERP</h1><p>Đang mở không gian làm việc…</p>
</div></section><div class="app" id="admin-workspace" hidden>

<!-- ═══════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════ -->
<aside class="side" id="admin-sidebar">
  <div class="brand" style="cursor:pointer" data-act="public-landing" title="Xem Website công khai của Trung tâm"><div class="brand-icon">E</div><div><b>EduCenter ERP</b><small>Quản trị Trung tâm Gia sư</small></div></div>
  <div class="side-user">
    <span class="su-role" id="sRole">Quản trị viên</span>
    <div class="su-name" id="admin-name">Trần Quang Huy</div>
    <div class="su-route" id="sRoute" style="display:none"></div>
  </div>
  <nav id="admin-nav"><?php if (!$isAdmissions): ?>
    <div class="nav-group">Tổng quan &amp; Báo cáo</div>
    <button type="button" data-screen="admin-dash" data-nav="admin-dash"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span><span>Tổng quan &amp; Thống kê</span></button>
    <button type="button" data-screen="admin-reports" data-nav="admin-reports"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></span><span>Báo cáo &amp; Xuất dữ liệu</span></button>
    <div class="nav-group">Đào tạo &amp; Điều phối</div>
    <button type="button" data-screen="admin-courses" data-nav="admin-courses"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span><span>Khóa học, Lớp &amp; Xếp lớp</span></button>
    <button type="button" data-screen="admin-schedule" data-nav="admin-schedule"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span>Giám sát Lịch toàn chuỗi</span></button>
    <button type="button" data-screen="staff-intake" data-nav="staff-intake"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></span><span>Tuyển sinh &amp; Ghép người dạy</span></button>
    <button type="button" data-screen="staff-recruitment" data-nav="staff-recruitment"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg></span><span>Đề xuất &amp; Duyệt tuyển ca</span></button>
    <button type="button" data-screen="staff-registrations" data-nav="staff-registrations"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span><span>Tra cứu Đăng ký khóa</span></button>
    <div class="nav-group">Tài chính, Công &amp; Duyệt</div>
    <button type="button" data-screen="admin-payments" data-nav="admin-payments"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span><span>Đối soát Thanh toán Web</span></button>
    <button type="button" data-screen="admin-payroll" data-nav="admin-payroll"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span>Công, Đơn giá giờ &amp; Lương</span></button>
    <button type="button" data-screen="admin-exceptions" data-nav="admin-exceptions"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></span><span>Phê duyệt &amp; Mở khóa công</span></button>
    <div class="nav-group">Nhân sự &amp; Hệ thống</div>
    <button type="button" data-screen="admin-staff" data-nav="admin-staff"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Nhân sự &amp; Tài khoản</span></button>
    <button type="button" data-screen="admin-perms" data-nav="admin-perms"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span><span>Phân quyền Hệ thống</span></button>
    <button type="button" data-screen="admin-catalog" data-nav="admin-catalog"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Danh mục &amp; Cấu hình</span></button>
    <button type="button" data-screen="admin-audit" data-nav="admin-audit"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span>Nhật ký Kiểm toán</span></button>
  <?php else: ?>
    <div class="nav-group">Tổng quan &amp; Báo cáo</div>
    <button type="button" data-screen="staff-dash" data-nav="staff-dash"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span><span>Tổng quan công việc</span></button>
    <button type="button" data-screen="staff-reports" data-nav="staff-reports"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg></span><span>Báo cáo &amp; Thống kê</span></button>
    <div class="nav-group">Tuyển sinh &amp; Đào tạo</div>
    <button type="button" data-screen="staff-intake" data-nav="staff-intake"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></span><span>Tuyển sinh &amp; Ghép người dạy</span></button>
    <button type="button" data-screen="staff-schedule" data-nav="staff-schedule"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span>Thời khóa biểu &amp; Học bù</span></button>
    <button type="button" data-screen="staff-recruitment" data-nav="staff-recruitment"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg></span><span>Đề xuất &amp; Duyệt tuyển ca</span></button>
    <button type="button" data-screen="staff-classes" data-nav="staff-classes"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span><span>Lớp học &amp; Sĩ số</span></button>
    <button type="button" data-screen="staff-students" data-nav="staff-students"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span><span>Học viên &amp; Đăng ký khóa</span></button>
    <button type="button" data-screen="staff-parents" data-nav="staff-parents"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Hồ sơ Phụ huynh</span></button>
    <div class="nav-group">Đơn từ &amp; Học phí</div>
    <button type="button" data-screen="staff-approve" data-nav="staff-approve"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span><span>Duyệt nghỉ cá nhân &amp; Học bù</span></button>
    <button type="button" data-screen="staff-billing" data-nav="staff-billing"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span>Hóa đơn trọn khóa &amp; Thu phí</span></button>
  <?php endif; ?></nav>
</aside>

<!-- ═══════════════════════════════════════
     MAIN WRAPPER & TOPBAR
     ═══════════════════════════════════════ -->
<div class="main-wrap">
<header class="topbar">
  <div class="row">
    <button type="button" class="btn sm mob-toggle" style="display:none" id="admin-menu" aria-label="Mở menu">☰</button>
    <div class="seg" id="seg" style="display:none"></div>
  </div>
  <div class="top-r">
    <span class="perm" id="permLbl">
      <svg class="ui-ico sm" style="color:var(--g500)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="topDateText">Thứ Tư, 11/11/2026</span>
    </span>
    <button type="button" class="bell-btn" id="bellBtn" data-act="toggle-notif" title="Thông báo hệ thống">
      <svg class="ui-ico" style="width:16px;height:16px" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <b id="nCount" style="display:none">—</b>
    </button>
    <div style="position:relative">
      <button type="button" class="acct-btn" id="admin-avatar" data-act="toggle-user-dropdown" title="Menu tài khoản" aria-label="Menu tài khoản" aria-controls="userDropdown" aria-expanded="false">H</button>
      <div class="user-dropdown" id="userDropdown">
        <div class="ud-info">
          <b id="udName">Trần Quang Huy</b>
          <span id="udRole" style="font-size:11px;color:var(--pri-d);font-weight:600;display:block;margin-top:2px">Quản trị viên</span>
        </div>
        <button type="button" class="ud-item" data-account-action="view">Hồ sơ cá nhân</button>
        <button type="button" class="ud-item" data-account-action="edit">Chỉnh sửa hồ sơ cá nhân</button>
        <button type="button" class="ud-item" data-account-action="password">Đổi mật khẩu</button>
        <?php if (!$isAdmissions): ?><button type="button" class="ud-item" data-act="open-account-mgmt">Quản lý tài khoản</button><?php endif; ?>
        <div style="border-top:1px solid var(--g200);margin:4px 0"></div>
        <button type="button" class="ud-item danger" data-act="logout">Đăng xuất</button>
      </div>
    </div>
  </div>
</header>

<main class="main" id="admin-content" tabindex="-1" aria-live="polite"></main></div></div>
<?php if (!$isAdmissions): ?><div class="modal-ov" id="modalStaffForm" data-backdrop-close="close-staff-form">
  <div class="modal-box" role="dialog" aria-modal="true" style="max-width:520px">
    <h3>
      <span>Thêm tài khoản nhân sự &amp; Đơn giá giờ</span>
      <button type="button" style="border:0;background:0;font-size:16px;color:var(--g500);cursor:pointer" data-act="close-staff-form">✕</button>
    </h3>
    <form id="staffAddForm" data-on-submit="submit-staff-add">
      <div class="form-group">
        <label>Họ và tên nhân sự <span style="color:var(--err)">*</span></label>
        <input type="text" id="sfStaffName" placeholder="VD: Lê Thị Lan Hương" required>
        <span class="field-error" id="err-sfStaffName"></span>
      </div>

      <div class="row" style="gap:10px;align-items:flex-start">
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

      <div class="row" style="gap:10px;align-items:flex-start">
        <div class="form-group" style="flex:1">
          <label>Vai trò phân quyền <span style="color:var(--err)">*</span></label>
          <select data-on-change="staff-role-changed" id="sfStaffRole" style="font-weight:600">
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
        <button type="button" class="btn sm" data-act="close-staff-form">Hủy</button>
        <button type="submit" class="btn p sm">Lưu tài khoản</button>
      </div>
    </form>
  </div>
</div>
<div class="modal-ov" id="modalTeacherProfile" data-backdrop-close="close-teacher-profile">
  <div class="modal-box" role="dialog" aria-modal="true" style="max-width:580px">
    <h3>
      <span id="tpModalTitle">Hồ sơ Chuyên môn &amp; Lịch rảnh Người dạy</span>
      <button type="button" style="border:0;background:0;font-size:16px;color:var(--g500)" data-act="close-teacher-profile">✕</button>
    </h3>
    <div id="tpModalBody"></div>
  </div>
</div>
<div class="modal-ov" id="modalAdminEnroll" data-backdrop-close="close-admin-enroll">
  <div class="modal-box" role="dialog" aria-modal="true" style="max-width:520px">
    <h3>
      <span>Xếp học viên vào lớp</span>
      <button type="button" style="border:0;background:0;font-size:16px;color:var(--g500)" data-act="close-admin-enroll">✕</button>
    </h3>
    <form data-on-submit="submit-admin-enroll">
      <label>Chọn Hồ sơ Đăng ký Khóa học</label>
      <select id="aeEnrollmentSelect"></select>
      <label>Chọn Lớp học tương ứng với Khóa</label>
      <select id="aeClassSelect"></select>
      <div class="modal-act">
        <button type="button" class="btn sm" data-act="close-admin-enroll">Hủy</button>
        <button type="submit" class="btn p sm">✓ Xác nhận Xếp vào lớp</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?><div class="modal-ov" id="modalSessionDetail" data-backdrop-close="close-session-detail">
  <div class="modal-box" role="dialog" aria-modal="true" style="max-width:640px">
    <h3>
      <span id="dtModalTitle">Chi tiết Buổi học</span>
      <button type="button" style="border:0;background:0;font-size:16px;color:var(--g500)" data-act="close-session-detail">✕</button>
    </h3>
    <div id="dtModalContent"></div>
    <div class="modal-act" id="dtModalActions"></div>
  </div>
</div>
<div class="modal-ov" id="modalSessionForm" data-backdrop-close="close-session-form">
  <div class="modal-box" role="dialog" aria-modal="true" style="max-width:600px">
    <h3>
      <span id="sfModalTitle">Xếp ca học</span>
      <button type="button" style="border:0;background:0;font-size:16px;color:var(--g500)" data-act="close-session-form">✕</button>
    </h3>
    <form id="sessionFormElement" data-on-submit="submit-session-form">
      <div id="sfConflictBanner" style="display:none" class="warn-box">
        <b style="display:block;margin-bottom:2px">⚠ Xung đột lịch giáo viên!</b>
        <span id="sfConflictText">Giáo viên đã có ca dạy khác vào khung giờ này.</span>
      </div>

      <div class="row" style="gap:10px;align-items:flex-start">
        <div style="flex:1.2">
          <label>Lớp học <span style="color:var(--err)">*</span></label>
          <select id="sfClassSelect" data-on-change="sf-class-changed"></select>
        </div>
        <div style="flex:1">
          <label>Loại buổi học</label>
          <select id="sfSessionType" data-on-change="sf-type-changed">
            <option value="upcoming">Buổi học thường</option>
            <option value="makeup">Buổi học bù</option>
          </select>
        </div>
      </div>

      <div id="sfMakeupWrap" style="display:none;background:var(--purple-l);border:1px solid #ddd6fe;padding:10px;border-radius:var(--rs);margin-top:8px">
        <div class="row" style="gap:10px;align-items:flex-start">
          <div style="flex:1">
            <label style="margin-top:0;color:var(--purple)">Buổi gốc</label><select id="sfMakeupOrigin"></select><label style="margin-top:8px;color:var(--purple)">Phạm vi bù</label>
            <select id="sfMakeupScope" data-on-change="sf-type-changed">
              <option value="ca_nhan">Bù cá nhân</option>
              <option value="ca_lop">Bù nguyên cả lớp</option>
            </select>
          </div>
          <div style="flex:1">
            <label style="margin-top:0;color:var(--purple)">Học viên được xếp bù</label>
            <select id="sfMakeupStudent"><option value="">Chọn học viên</option></select>
          </div>
        </div>
        <label style="color:var(--purple)">Ghi chú</label>
        <input type="text" id="sfMakeupNote" placeholder="Ghi chú đã chốt với phụ huynh">
      </div>

      <div class="row" style="gap:10px;margin-top:6px;align-items:flex-start">
        <div style="flex:1;min-width:0">
          <label>Giáo viên chính <span style="color:var(--err)">*</span></label>
          <select id="sfTeacherSelect" data-on-change="sf-check-conflicts"></select>
          <small id="sfTeacherAvailability" style="display:block;color:var(--g500);margin-top:5px;line-height:1.45"></small>
        </div>
        <div style="flex:1;min-width:0">
          <label>Trợ giảng</label>
          <select id="sfTaSelect"></select>
        </div>
      </div>

      <div class="row" style="gap:8px;margin-top:6px;align-items:flex-start">
        <div style="flex:1.2;min-width:0">
          <label>Ngày học <span style="color:var(--err)">*</span></label>
          <input type="date" id="sfDate" data-on-change="sf-check-conflicts" required>
        </div>
        <div style="flex:1;min-width:0">
          <label>Giờ lịch Bắt đầu <span style="color:var(--err)">*</span></label>
          <input type="time" id="sfStartTime" data-on-change="sf-check-conflicts" value="18:00" required>
        </div>
        <div style="flex:1;min-width:0">
          <label>Giờ lịch Kết thúc <span style="color:var(--err)">*</span></label>
          <input type="time" id="sfEndTime" data-on-change="sf-check-conflicts" value="20:00" required>
        </div>
      </div>

      <div style="background:var(--g50);padding:10px;border-radius:var(--rs);border:1px solid var(--g200);margin-top:10px">
        <div class="row-b" style="margin-bottom:6px">
          <span style="font-size:12px;font-weight:600">Hình thức học:</span>
          <div class="seg" style="font-size:11px">
            <button type="button" id="sfBtnOffline" class="on" data-act="sf-online-mode" data-online="0">Offline Cơ sở</button>
            <button type="button" id="sfBtnOnline" data-act="sf-online-mode" data-online="1">Online Trực tuyến</button>
          </div>
        </div>
        <div id="sfOfflineFields" class="row" style="gap:8px;align-items:flex-start">
          <div style="flex:1">
            <label style="margin-top:0">Cơ sở</label>
            <select id="sfBranchSelect" data-on-change="sf-branch-changed"></select>
            <small id="sfCampusEmpty" style="display:none;color:var(--g500);margin-top:5px">Chưa có cơ sở hoạt động. Nhờ Admin thêm cơ sở trong Danh mục &amp; Cấu hình.</small>
          </div>
          <div style="flex:1">
            <label style="margin-top:0">Phòng học</label>
            <select id="sfRoomSelect" data-on-change="sf-check-conflicts"></select>
          </div>
        </div>
        <div id="sfOnlineFields" style="display:none">
          <label style="margin-top:0">Link Google Meet / Zoom</label>
          <input type="text" id="sfOnlineLink" placeholder="https://meet.google.com/xyz-educenter">
        </div>
      </div>

      <div id="sfRecurringWrap" style="margin-top:10px;padding:8px 10px;border:1px dashed var(--g300);border-radius:var(--rs)">
        <div class="row-b">
          <span style="font-size:12px;font-weight:600">🔁 Lặp lại hàng tuần</span>
          <input type="checkbox" id="sfIsRecurring" data-on-change="sf-toggle-recurring" style="width:16px;height:16px">
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
        <button type="button" class="btn sm" data-act="close-session-form">Hủy bỏ</button>
        <button type="submit" class="btn p sm">Lưu lịch học</button>
      </div>
    </form>
  </div>
</div>
<div class="notif-panel" id="notifPanel">
  <div class="np-head">
    <h3>Thông báo</h3>
    <button type="button" class="np-read-all" data-act="mark-all-notifs">Đọc tất cả</button>
  </div>
  <div class="np-list" id="notifList">
    <div style="padding:28px 18px;text-align:center;color:var(--g400);font-size:13px">Chưa có thông báo mới.</div>
  </div>
</div><dialog id="admin-dialog" aria-labelledby="dialog-title"><div id="dialog-content"></div></dialog>
<div id="admin-toast" role="status" hidden></div><span id="admin-page-title" hidden></span>
</body></html>
