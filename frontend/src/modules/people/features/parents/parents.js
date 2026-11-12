'use strict';

// Parent portal: use the authenticated parent's own profile and server-scoped schedule.
(() => {
  if (!document.getElementById('studentSelector')) return;

  document.addEventListener('DOMContentLoaded', () => {
    if (!AccessService.requireAuth(['PHU_HUYNH'])) return;

    const $ = id => document.getElementById(id);
    const state = { children: [], sessions: [], selectedId: null };
    const currentUser = AccessService.getCurrentUser();
    const storageKey = AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID;

    if (currentUser) $('currentUserName').textContent = currentUser.hoTen || currentUser.username || 'Phụ huynh';

    function dataOf(response) {
      return response && Object.prototype.hasOwnProperty.call(response, 'data') ? response.data : response;
    }

    function showNotice(message, isError = false) {
      const notice = $('portalNotice');
      notice.textContent = message;
      notice.hidden = !message;
      notice.classList.toggle('error', isError);
    }

    function setEmptyRow(id, colspan, message) {
      const body = $(id);
      if (!body) return;
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = colspan;
      cell.className = 'text-center text-muted';
      cell.style.padding = '24px';
      cell.textContent = message;
      row.append(cell);
      body.replaceChildren(row);
    }

    function renderUnavailableFeatures() {
      setEmptyRow('learningTableBody', 6, 'Chưa có API học vụ dành cho phụ huynh.');
      setEmptyRow('leaveHistoryTableBody', 5, 'Chưa có API gửi và tra cứu đơn nghỉ dành cho phụ huynh.');
      setEmptyRow('invoiceTableBody', 8, 'Chưa có API đọc hóa đơn trong cổng phụ huynh.');
      setEmptyRow('transactionHistoryTableBody', 7, 'Chưa có API giao dịch/thanh toán dành cho phụ huynh.');
      const notifications = $('notificationsListContainer');
      if (notifications) {
        const message = document.createElement('p');
        message.className = 'text-muted';
        message.textContent = 'API thông báo dành cho phụ huynh chưa được triển khai.';
        notifications.replaceChildren(message);
      }
    }

    function renderChildren() {
      const selector = $('studentSelector');
      const chips = $('kidChipsContainer');
      selector.replaceChildren();
      chips.replaceChildren();

      for (const child of state.children) {
        const option = document.createElement('option');
        option.value = String(child.id);
        option.textContent = `${child.hoTen || child.maHv}${child.tenCapLop ? ` (${child.tenCapLop})` : ''}`;
        selector.append(option);

        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'chip';
        chip.dataset.id = String(child.id);
        chip.textContent = option.textContent;
        chip.addEventListener('click', () => selectChild(child.id));
        chips.append(chip);
      }

      if (!state.children.length) {
        $('selectedStudentName').textContent = 'Chưa có học viên được liên kết';
        selector.disabled = true;
        setEmptyRow('scheduleTableBody', 6, 'Tài khoản chưa có học viên liên kết. Vui lòng liên hệ Giáo vụ.');
        chips.textContent = 'Chưa có hồ sơ con để chọn.';
        return;
      }

      const remembered = Number(sessionStorage.getItem(storageKey) || localStorage.getItem(storageKey));
      const initial = state.children.find(child => child.id === remembered) || state.children[0];
      selector.disabled = false;
      selector.addEventListener('change', () => selectChild(Number(selector.value)));
      selectChild(initial.id);
    }

    function selectChild(id) {
      const child = state.children.find(item => item.id === Number(id));
      if (!child) return;
      state.selectedId = child.id;
      $('studentSelector').value = String(child.id);
      localStorage.setItem(storageKey, String(child.id));
      $('selectedStudentName').textContent = `${child.hoTen || child.maHv}${child.tenCapLop ? ` (${child.tenCapLop})` : ''}`;
      document.querySelectorAll('#kidChipsContainer .chip').forEach(button => {
        button.classList.toggle('active', Number(button.dataset.id) === child.id);
      });
      renderSchedule();
    }

    function renderSchedule() {
      const body = $('scheduleTableBody');
      const child = state.children.find(item => item.id === state.selectedId);
      if (!child) return;
      const childClasses = new Set((child.lopIds || []).map(Number));
      const sessions = state.sessions.filter(session => {
        if (!childClasses.has(Number(session.lopId))) return false;
        if (session.loai === 'bu' && session.phamViBu === 'ca_nhan') {
          const childIds = String(session.hocBuIds || '').split(',').map(Number);
          if (!childIds.includes(child.id)) return false;
        }
        return true;
      });

      if (!sessions.length) {
        setEmptyRow('scheduleTableBody', 6, 'Chưa có lịch học cho học viên này.');
        return;
      }

      const labels = { du_kien: 'Sắp diễn ra', dang_day: 'Đang diễn ra', da_day: 'Đã học', huy: 'Đã hủy', doi: 'Đã đổi' };
      const rows = sessions.map(session => {
        const row = document.createElement('tr');
        const date = new Date(`${session.ngay}T00:00:00`);
        const dateText = Number.isNaN(date.getTime()) ? session.ngay : date.toLocaleDateString('vi-VN');
        const times = `${String(session.gioBatDau || '').slice(0, 5)} – ${String(session.gioKetThuc || '').slice(0, 5)}`;
        const values = [
          dateText,
          times,
          session.tenLop || session.maLop || '—',
          session.phongHoc || 'Theo thỏa thuận với trung tâm',
          session.tenNguoiDayChinh || 'Chưa phân công',
          labels[session.trangThai] || session.trangThai || '—',
        ];
        for (const value of values) {
          const cell = document.createElement('td');
          cell.textContent = value || '—';
          row.append(cell);
        }
        return row;
      });
      body.replaceChildren(...rows);
    }

    function wireTabs() {
      const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
      const panes = document.querySelectorAll('.tab-pane');
      const titles = {
        schedule: 'Thời khóa biểu học tập',
        learning: 'Sổ theo dõi & Điểm số buổi học',
        leave: 'Đơn xin nghỉ phép của học sinh',
        tuition: 'Học phí & Hóa đơn',
        history: 'Lịch sử giao dịch',
        notifications: 'Thông báo hệ thống',
      };
      navItems.forEach(item => item.addEventListener('click', () => {
        navItems.forEach(nav => nav.classList.toggle('active', nav === item));
        panes.forEach(pane => pane.classList.toggle('active', pane.id === `tab-${item.dataset.tab}`));
        $('tabHeading').textContent = titles[item.dataset.tab] || 'Cổng phụ huynh';
      }));
    }

    function wirePasswordChange() {
      const modal = $('modalChangePassword');
      const form = $('changePasswordForm');
      const close = () => { modal.classList.remove('show'); form.reset(); };
      $('btnOpenChangePwd').addEventListener('click', () => modal.classList.add('show'));
      $('btnCloseChangePwdModal').addEventListener('click', close);
      $('btnCancelChangePwd').addEventListener('click', close);
      form.addEventListener('submit', async event => {
        event.preventDefault();
        const newPassword = $('newPassword').value;
        if (newPassword !== $('confirmNewPassword').value) {
          showNotice('Mật khẩu mới và phần xác nhận không khớp.', true);
          return;
        }
        const button = form.querySelector('[type="submit"]');
        button.disabled = true;
        try {
          await HttpService.put('/auth/change-password', {
            currentPassword: $('currentPassword').value,
            newPassword,
          });
          close();
          AccessService.clearSession();
          window.location.assign(AppConfig.AUTH_PAGE);
        } catch (error) {
          showNotice(error.message || 'Không đổi được mật khẩu.', true);
        } finally {
          button.disabled = false;
        }
      });
    }

    function wireLogout() {
      $('btnLogout').addEventListener('click', async () => {
        if (!window.confirm('Bạn có chắc chắn muốn đăng xuất không?')) return;
        try {
          await HttpService.post('/auth/logout', {});
        } catch (error) {
          window.alert(`Không xác nhận được việc thu hồi phiên trên máy chủ: ${error.message}`);
        } finally {
          AccessService.clearSession();
          window.location.assign(AppConfig.AUTH_PAGE);
        }
      });
    }

    async function loadPortal() {
      renderUnavailableFeatures();
      setEmptyRow('scheduleTableBody', 6, 'Đang tải hồ sơ và lịch học…');
      wireTabs();
      wirePasswordChange();
      wireLogout();
      try {
        const profileResponse = await HttpService.get(`/people/parents/${encodeURIComponent(currentUser.userId)}`);
        const profile = dataOf(profileResponse);
        state.children = Array.isArray(profile?.hocViens) ? profile.hocViens : [];
        renderChildren();
        if (!state.children.length) return;

        try {
          const sessionResponse = await HttpService.get('/academic/scheduling/sessions');
          const sessions = dataOf(sessionResponse);
          state.sessions = Array.isArray(sessions) ? sessions : [];
          renderSchedule();
        } catch (error) {
          setEmptyRow('scheduleTableBody', 6, 'Không tải được lịch học từ máy chủ. Vui lòng thử tải lại trang.');
          showNotice(error.message || 'Không tải được lịch học.', true);
        }
      } catch (error) {
        showNotice(error.message || 'Không tải được hồ sơ phụ huynh.', true);
        setEmptyRow('scheduleTableBody', 6, 'Không tải được hồ sơ học viên.');
      }
    }

    loadPortal();
  });
})();

// Parent profile management used by the staff portal.
(() => {
  if (!window.Admin || !window.StaffUI) return;
  function render(rows) {
    StaffUI.rows('parentsTableBody',7,rows.map(row=>`<tr><td><b>PH-${row.userId}</b></td><td><b>${Admin.esc(row.hoTen)}</b></td><td>${Admin.esc(row.sdt || '—')}<br><small>${Admin.esc(row.email)}</small></td><td>${Admin.esc(row.tenKhuVuc || '—')}<br><small>${Admin.esc(row.diaChi || '')}</small></td><td>${row.soCon} học viên</td><td>${Admin.tag(row.username || 'Chưa cấp tài khoản',row.accountStatus==='active'?'ok':'w')}</td><td>${Admin.button('Chi tiết & Liên kết con','parent-detail',row.userId)}</td></tr>`));
    Admin.$('parentsTableBody').querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>parentDetail(Number(button.dataset.id))));
  }
  StaffUI.register('staff-parents','Hồ sơ Phụ huynh',()=>Admin.api('/api/people/parents'),rows=>{
    render(rows);
    StaffUI.search('parentsTableBody',async q=>render(await Admin.api('/api/people/parents?'+Admin.query({q}))));
    const createButton=document.querySelector('#staff-parents [onclick="openParentFormModal()"]');
    if(createButton) createButton.hidden=!StaffUI.can('TiepNhanYeuCau','add');
  });
  window.openParentFormModal = async id => {
    try {
      const [catalog,parent]=await Promise.all([Admin.api('/api/administration/catalog'),id?Admin.api('/api/people/parents/'+id):Promise.resolve(null)]);
      const fields=[{name:'hoTen',label:'Họ tên phụ huynh',required:true,maxLength:150},
        {name:'sdt',label:'Số điện thoại',maxLength:20,nullable:true},{name:'email',label:'Email nhận thông báo',type:'email',required:true,maxLength:150},
        StaffUI.select('khuVucId','Khu vực',catalog.regions),{name:'diaChi',label:'Địa chỉ',maxLength:255,nullable:true},
        {name:'ngheNghiep',label:'Nghề nghiệp',maxLength:150,nullable:true}];
      fields.forEach(field=>{if(parent)field.value=parent[field.name];});
      Admin.formDialog({title:parent?'Sửa hồ sơ phụ huynh':'Thêm hồ sơ phụ huynh',fields,onSubmit:async body=>{
        if(body.khuVucId!=null)body.khuVucId=Number(body.khuVucId);
        await Admin.api('/api/people/parents'+(id?'/'+id:''),id?'PUT':'POST',body);
        toast('Đã lưu hồ sơ phụ huynh.','ok');Admin.render();
      }});
    }catch(error){toast(error.message,'err');}
  };
  async function parentDetail(id) {
    try {
      const parent=await Admin.api('/api/people/parents/'+id);
      Admin.dialog('Hồ sơ phụ huynh · '+parent.hoTen,`<div class="dialog-body"><dl class="staff-detail-list"><dt>Họ tên</dt><dd>${Admin.esc(parent.hoTen)}</dd><dt>Liên hệ</dt><dd>${Admin.esc(parent.sdt || '—')} · ${Admin.esc(parent.email)}</dd><dt>Địa chỉ</dt><dd>${Admin.esc(parent.diaChi || '—')}</dd><dt>Tài khoản web</dt><dd>${Admin.esc(parent.username || 'Chờ Admin cấp tài khoản')}</dd></dl><h3>Các con đã liên kết</h3>${Admin.table([{key:'maHv',label:'Mã học viên'},{key:'hoTen',label:'Học viên'},{key:'tenCapLop',label:'Khối lớp'}],parent.hocViens)}
        <div class="btn-group">${StaffUI.can('TiepNhanYeuCau','edit')?'<button class="btn" id="staff-edit-parent">Sửa hồ sơ</button>':''}${StaffUI.can('QuanLyLopHoc','add')?'<button class="btn p" id="staff-add-child">Thêm học viên cho phụ huynh</button>':''}</div></div>`);
      Admin.$('staff-edit-parent')?.addEventListener('click',()=>openParentFormModal(id));
      Admin.$('staff-add-child')?.addEventListener('click',()=>openStudentForm(null,id));
    }catch(error){toast(error.message,'err');}
  }
})();
