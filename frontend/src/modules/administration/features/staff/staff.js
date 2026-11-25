'use strict';
(() => {
  const Admin = window.Admin;
  const money = Admin.money, esc = Admin.esc, empty = Admin.emptyRow, kpi = Admin.kpi;
  const roleLabel = type => type?.includes('giao_vien') ? 'Giáo viên' : (type?.includes('gia_su') ? 'Gia sư' : 'Trợ giảng');
  const confirmation = Admin.confirmDialog;

  let employees = [], currentProfile = null;
  const staffRoleName = row => row.groupName === 'Admin' ? 'Admin' : (row.groupName === 'NhanVien' ? 'Nhân viên' : roleLabel(row.phanLoai));

  Admin.register('admin-staff', async () => {
    const data = await Admin.api('/api/administration/staff');
    employees = data || [];
    return {
      title: 'Nhân sự & Hồ sơ chuyên môn',
      html: Admin.view('admin-staff'),
      bind() {
        const teaching = employees.filter(r => r.nhanSuId);
        const activeCount = employees.filter(r => r.status === 'active').length;
        const missingRate = teaching.filter(r => Number(r.donGiaGioChuan || 0) <= 0).length;
        Admin.$('adminStaffKpis').innerHTML = [
          kpi('Tổng nhân sự', String(employees.length), `${activeCount} tài khoản đang hoạt động`, '', true),
          kpi('Đội ngũ giảng dạy', String(teaching.length), `${teaching.filter(r => roleLabel(r.phanLoai) === 'Giáo viên').length} GV · ${teaching.filter(r => roleLabel(r.phanLoai) === 'Gia sư').length} Gia sư · ${teaching.filter(r => roleLabel(r.phanLoai) === 'Trợ giảng').length} TG`, 'i'),
          kpi('Chưa đặt đơn giá giờ', String(missingRate), missingRate ? 'Cần Admin thiết lập đơn giá' : 'Đủ đơn giá giờ', missingRate ? 'w' : 'g'),
          kpi('Quản trị & Giáo vụ', String(employees.length - teaching.length), 'Admin & Nhân viên điều phối', 'p'),
        ].join('');

        Admin.$('staffRolePills')?.querySelectorAll('[data-sr]').forEach(btn => btn.addEventListener('click', () => {
          Admin.$('staffRoleFilter').value = btn.dataset.sr;
          Admin.$('staffRolePills').querySelectorAll('[data-sr]').forEach(b => b.classList.toggle('on', b === btn));
          renderStaff();
        }));
        renderStaff();
      }
    };
  });

  window.renderStaff = () => {
    const query = (Admin.$('staffSearchInput')?.value || '').toLowerCase();
    const role = Admin.$('staffRoleFilter')?.value || '';
    const rows = employees.filter(row =>
      (!query || [row.hoTen, row.email, row.sdt].join(' ').toLowerCase().includes(query)) &&
      (!role || staffRoleName(row) === role)
    );
    Admin.$('staffRows').innerHTML = rows.map(row => `<tr>
      <td><b>${esc(row.hoTen)}</b></td>
      <td>${esc(row.email)}<br><small>${esc(row.sdt || '—')}</small></td>
      <td>${Admin.tag(staffRoleName(row), 'i')}</td>
      <td style="font-size:12px">${esc(row.chuyenMon || row.gioiThieu || 'Chưa nhập chuyên môn')}<br><small style="color:var(--g500)">${esc(row.lichRanh || 'Chưa nhập lịch rảnh')}</small></td>
      <td>${row.nhanSuId ? `<b class="mono">${money(row.donGiaGioChuan)}/giờ</b>` : '<small style="color:var(--g500)">Quản trị / Văn phòng</small>'}</td>
      <td>${Admin.tag(row.status === 'active' ? 'Hoạt động' : (row.status === 'pending' ? 'Chờ kích hoạt' : 'Khóa'), row.status === 'active' ? 'ok' : 'r')}</td>
      <td><div class="row">${row.nhanSuId ? `<button type="button" class="btn sm p" data-act="open-teacher-profile" data-id="${row.userId}">Đơn giá &amp; Hồ sơ</button>` : ''}<button type="button" class="btn sm" data-act="toggle-staff" data-id="${row.userId}">${row.status === 'active' ? 'Khóa' : 'Kích hoạt'}</button></div></td>
    </tr>`).join('') || empty(7);
    Admin.$('staffCount').textContent = `Hiển thị ${rows.length} / ${employees.length} tài khoản`;
  };

  window.toggleStaff = userId => {
    const row = employees.find(item => item.userId === userId), status = row.status === 'active' ? 'locked' : 'active';
    confirmation(status === 'locked' ? 'Khóa tài khoản' : 'Kích hoạt tài khoản', row.hoTen, async () => {
      await Admin.api(`/api/administration/permissions/accounts/${row.accountId}`, 'PUT', { status });
      toast('Đã cập nhật trạng thái.', 'ok'); Admin.render();
    });
  };
  window.onStaffRoleChanged = () => {
    const professional = ['Giáo viên', 'Gia sư', 'Trợ giảng'].includes(Admin.$('sfStaffRole').value);
    Admin.$('sfStaffHourlyRate').disabled = !professional;
    if (!professional) Admin.$('sfStaffHourlyRate').value = '0';
  };
  window.openStaffForm = () => { Admin.$('staffAddForm').reset(); onStaffRoleChanged(); Admin.$('modalStaffForm').classList.add('show'); };
  window.closeModalStaffForm = () => Admin.$('modalStaffForm').classList.remove('show');
  window.handleStaffAddSubmit = async event => {
    event.preventDefault();
    const roles = { 'Giáo viên':'giao_vien', 'Gia sư':'gia_su', 'Trợ giảng':'tro_giang', 'Nhân viên':'NhanVien', 'Admin':'Admin' };
    const body = {
      hoTen: Admin.$('sfStaffName').value, email: Admin.$('sfStaffEmail').value, sdt: Admin.$('sfStaffPhone').value,
      role: roles[Admin.$('sfStaffRole').value], donGiaGio: Number(Admin.$('sfStaffHourlyRate').value),
      gioiThieu: Admin.$('sfStaffSpec').value || null, username: Admin.$('sfStaffUsername').value, password: Admin.$('sfStaffPassword').value
    };
    await Admin.busy(event.target.querySelector('button[type=submit]'), async () => {
      await Admin.api('/api/administration/staff', 'POST', body);
      closeModalStaffForm(); toast('Đã tạo hồ sơ và tài khoản nhân sự.', 'ok'); Admin.render();
    });
  };

  let profileCatalog = { subjects:[], regions:[] };
  function subjectRow(value = {}) {
    return `<div class="admin-profile-row" data-subject data-fee="${Number(value.hocPhiBuoiDeXuat || 0)}"><label>Môn<select data-subject-id>${Admin.options(profileCatalog.subjects, 'id', 'ten', value.monHocId)}</select></label><label>Từ lớp<input data-grade-from type="number" min="1" max="12" value="${value.capLopTu || 1}" required></label><label>Đến lớp<input data-grade-to type="number" min="1" max="12" value="${value.capLopDen || 12}" required></label><button type="button" class="btn sm danger" data-act="remove-profile-row" data-sel="[data-subject]">Bỏ</button></div>`;
  }
  function availableRow(value = {}) {
    return `<div class="admin-profile-row" data-available><label>Thứ<select data-day>${Array.from({ length:7 }, (_, i) => `<option value="${i + 2}" ${i + 2 === (value.thu || 2) ? 'selected' : ''}>${i === 6 ? 'Chủ nhật' : 'Thứ ' + (i + 2)}</option>`).join('')}</select></label><label>Từ giờ<input data-from type="time" value="${(value.gioBatDau || '18:00').slice(0, 5)}" required></label><label>Đến giờ<input data-to type="time" value="${(value.gioKetThuc || '20:00').slice(0, 5)}" required></label><button type="button" class="btn sm danger" data-act="remove-profile-row" data-sel="[data-available]">Bỏ</button></div>`;
  }
  window.addProfileSubject = () => Admin.$('profileSubjects').insertAdjacentHTML('beforeend', subjectRow());
  window.addProfileAvailability = () => Admin.$('profileAvailability').insertAdjacentHTML('beforeend', availableRow());
  window.switchProfileTab = tab => {
    document.querySelectorAll('#modalTeacherProfile [data-tp-pane]').forEach(el => { el.hidden = el.dataset.tpPane !== tab; });
    document.querySelectorAll('#modalTeacherProfile [data-tp-tab]').forEach(b => b.classList.toggle('on', b.dataset.tpTab === tab));
    const saveBar = document.querySelector('#modalTeacherProfile [data-tp-save-bar]');
    if (saveBar) saveBar.hidden = tab === 'rate';
  };

  window.openTeacherProfileModal = async userId => {
    try {
      const [data, subjects, regions] = await Promise.all([
        Admin.api(`/api/administration/staff/${userId}/profile`),
        Admin.api('/api/administration/catalog/subjects'),
        Admin.api('/api/administration/catalog/regions')
      ]);
      currentProfile = { ...data, userId }; profileCatalog = { subjects, regions };
      const p = data.professional;
      if (!p) throw new Error('Hồ sơ người dạy không còn hoạt động. Vui lòng tải lại danh sách.');
      Admin.$('tpModalTitle').textContent = 'Hồ sơ Chuyên môn & Đơn giá — ' + data.user.hoTen;
      Admin.$('tpModalBody').innerHTML = `
        <div class="ec-sub-tabs">
          <button type="button" class="ec-pill on" data-tp-tab="info" data-act="switch-profile-tab">Thông tin &amp; Ngân hàng</button>
          <button type="button" class="ec-pill" data-tp-tab="spec" data-act="switch-profile-tab">Môn &amp; Khu vực (${p.monDays.length})</button>
          <button type="button" class="ec-pill" data-tp-tab="avail" data-act="switch-profile-tab">Lịch rảnh (${p.lichRanhs.length})</button>
          <button type="button" class="ec-pill" data-tp-tab="rate" data-act="switch-profile-tab">Đơn giá giờ</button>
        </div>
        <form id="profileEditForm" data-on-submit="save-staff-profile">
          <div data-tp-pane="info">
            <div class="ec-form-grid">
              <label>Họ tên *<input name="hoTen" value="${esc(p.hoTen || '')}" required></label>
              <label>Email *<input name="email" type="email" value="${esc(p.email || '')}" required></label>
              <label>Điện thoại<input name="sdt" value="${esc(p.sdt || '')}"></label>
              <label>Ngân hàng<input name="nganHang" value="${esc(p.nganHang || '')}"></label>
              <label>Số tài khoản<input name="soTaiKhoan" value="${esc(p.soTaiKhoan || '')}"></label>
              <label>Chủ tài khoản<input name="chuTaiKhoan" value="${esc(p.chuTaiKhoan || '')}"></label>
            </div>
          </div>
          <div data-tp-pane="spec" hidden>
            <h4 style="margin:0 0 8px">Môn &amp; Khối lớp nhận dạy</h4>
            <div id="profileSubjects">${p.monDays.map(subjectRow).join('')}</div>
            <button type="button" class="btn sm" style="margin:6px 0 14px" data-act="add-profile-subject">+ Thêm môn</button>
            <h4 style="margin:0 0 8px">Khu vực nhận dạy</h4>
            <div class="chips">${regions.map(row => `<label class="chip"><input type="checkbox" data-region="${row.id}" ${p.khuVucs.some(item => item.khuVucId === row.id) ? 'checked' : ''}>${esc(row.ten)}</label>`).join('')}</div>
          </div>
          <div data-tp-pane="avail" hidden>
            <h4 style="margin:0 0 8px">Khung giờ rảnh lặp hàng tuần</h4>
            <div id="profileAvailability">${p.lichRanhs.map(availableRow).join('')}</div>
            <button type="button" class="btn sm" style="margin-top:6px" data-act="add-profile-availability">+ Thêm khung giờ rảnh</button>
          </div>
          <div class="modal-act" data-tp-save-bar>
            <button type="button" class="btn sm" data-act="close-teacher-profile">Đóng</button>
            <button class="btn p sm" type="submit">Lưu hồ sơ chuyên môn</button>
          </div>
        </form>
        <div data-tp-pane="rate" hidden>
          <div class="ec-info-box pri">Đơn giá tiền/giờ mới áp dụng từ thời điểm lưu cho các ca tương lai chưa bắt đầu; giữ nguyên đơn giá snapshot của ca đã/đang dạy.</div>
          <div class="ec-form-grid">
            <label>Đơn giá hiện hành<input value="${money(p.donGiaGioChuan)}/giờ" disabled></label>
            <label>Đơn giá mới (VNĐ/giờ)<input id="tpNewRate" type="number" min="0" max="999999999999" step="1000" value="${Number(p.donGiaGioChuan)}"></label>
          </div>
          <div class="modal-act">
            <button type="button" class="btn sm" data-act="close-teacher-profile">Đóng</button>
            <button type="button" class="btn p sm" data-act="save-admin-rate">Cập nhật đơn giá giờ</button>
          </div>
        </div>
      `;
      Admin.$('modalTeacherProfile').classList.add('show');
    } catch (error) { toast(error.message, 'err'); }
  };

  window.closeTeacherProfileModal = () => Admin.$('modalTeacherProfile').classList.remove('show');
  window.saveStaffProfile = async event => {
    event.preventDefault();
    const body = Object.fromEntries(new FormData(event.target));
    for (const key of ['sdt', 'nganHang', 'soTaiKhoan', 'chuTaiKhoan']) if (!body[key]) body[key] = null;
    body.monDays = [...document.querySelectorAll('[data-subject]')].map(row => ({
      monHocId: Number(row.querySelector('[data-subject-id]').value),
      capLopTu: Number(row.querySelector('[data-grade-from]').value),
      capLopDen: Number(row.querySelector('[data-grade-to]').value),
      hocPhiBuoiDeXuat: Number(row.dataset.fee || 0)
    }));
    body.lichRanhs = [...document.querySelectorAll('[data-available]')].map(row => ({
      thu: Number(row.querySelector('[data-day]').value),
      gioBatDau: row.querySelector('[data-from]').value,
      gioKetThuc: row.querySelector('[data-to]').value
    }));
    body.khuVucIds = [...document.querySelectorAll('[data-region]:checked')].map(row => Number(row.dataset.region));
    await Admin.busy(event.target.querySelector('button[type=submit]'), async () => {
      await Admin.api(`/api/administration/staff/${currentProfile.userId}/profile`, 'PUT', body);
      toast('Đã lưu hồ sơ chuyên môn.', 'ok'); closeTeacherProfileModal(); Admin.render();
    });
  };
  window.saveAdminRate = button => Admin.busy(button, async () => {
    const value = Number(Admin.$('tpNewRate').value);
    if (!Number.isSafeInteger(value) || value < 0) throw new Error('Đơn giá cần là số nguyên VND không âm.');
    await Admin.api(`/api/finance/payroll/rates/${currentProfile.professional.id}`, 'PUT', { donGiaGio: value });
    toast('Đã cập nhật đơn giá giờ và lưu lịch sử.', 'ok'); closeTeacherProfileModal(); Admin.render();
  });
  Admin.actions({
    'open-staff-form': () => window.openStaffForm(),
    'close-staff-form': () => window.closeModalStaffForm(),
    'submit-staff-add': (_form, ev) => window.handleStaffAddSubmit(ev),
    'staff-role-changed': () => window.onStaffRoleChanged(),
    'render-staff': () => window.renderStaff(),
    'open-teacher-profile': el => window.openTeacherProfileModal(Number(el.dataset.id)),
    'close-teacher-profile': () => window.closeTeacherProfileModal(),
    'toggle-staff': el => window.toggleStaff(Number(el.dataset.id)),
    'switch-profile-tab': el => window.switchProfileTab(el.dataset.tpTab),
    'save-staff-profile': (_form, ev) => window.saveStaffProfile(ev),
    'add-profile-subject': () => window.addProfileSubject(),
    'add-profile-availability': () => window.addProfileAvailability(),
    'remove-profile-row': el => el.closest(el.dataset.sel)?.remove(),
    'save-admin-rate': el => window.saveAdminRate(el),
  });
})();
