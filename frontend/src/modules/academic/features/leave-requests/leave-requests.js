'use strict';
window.AdminClassLeave = {
  list: () => Admin.api('/api/academic/leave-requests/classes'),
  decide: (id, body) => Admin.api(`/api/academic/leave-requests/classes/${id}/decision`, 'POST', body)
};

(() => {
  let requests = [], classRequests = [], makeups = [], filter = 'pending', activeTab = 'personal';
  const statusLbl = { cho_duyet:'Chờ duyệt', da_duyet:'Đã duyệt', tu_choi:'Từ chối', da_huy:'Đã hủy' };
  const kpi = Admin.kpi;

  function switchTab(tab) {
    activeTab = tab;
    Admin.$('saPanePersonal').hidden = tab !== 'personal';
    Admin.$('saPaneClass').hidden = tab !== 'class';
    Admin.$('saPaneMakeups').hidden = tab !== 'makeups';
    Admin.$('staffApproveTabs')?.querySelectorAll('[data-sa-tab]').forEach(b => b.classList.toggle('on', b.dataset.saTab === tab));
  }

  function renderPersonal() {
    const list = requests.filter(row => {
      if (filter === 'pending') return row.trangThai === 'cho_duyet';
      if (filter === 'nghi_hoc') return row.doiTuong === 'hoc_vien';
      if (filter === 'doi_lich') return row.doiTuong !== 'hoc_vien';
      return true;
    });
    const rows = list.map(row => `<tr>
      <td><b class="code-short">#${row.id}</b><br>${Admin.tag(row.doiTuong === 'hoc_vien' ? 'Nghỉ HV' : 'Đổi lịch GV', row.doiTuong === 'hoc_vien' ? 'i' : 'purple')}</td>
      <td><b>${Admin.esc(row.nguoiGui)}</b>${row.tenHocVien ? `<br><small>HV: ${Admin.esc(row.tenHocVien)}</small>` : ''}</td>
      <td><b>${Admin.esc(row.maLop)}</b><br><small>${Admin.esc(row.ngay + ' ' + row.gioBatDau.slice(0, 5))}</small></td>
      <td><span class="clamp-2" title="${Admin.esc(row.lyDo || '')}">${Admin.esc(row.lyDo)}</span>${row.lyDoTuChoi ? `<br><small style="color:var(--err)">Từ chối: ${Admin.esc(row.lyDoTuChoi)}</small>` : ''}</td>
      <td>${Admin.tag(statusLbl[row.trangThai] || row.trangThai, row.trangThai === 'da_duyet' ? 'ok' : (row.trangThai === 'cho_duyet' ? 'w' : 'r'))}</td>
      <td class="act-col">${row.trangThai === 'cho_duyet' && StaffUI.can('VanHanhBuoiHoc', 'edit')
        ? `<div class="act-row">${Admin.button('Duyệt', 'approve-personal', row.id, 'p')}${Admin.button('Từ chối', 'reject-personal', row.id, 'danger')}</div>`
        : '—'}</td>
    </tr>`);
    StaffUI.rows('staffApproveBody', 6, rows, 'Không có đơn phù hợp bộ lọc.');
    Admin.$('staffApproveBody').querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
      const rejected = button.dataset.action === 'reject-personal';
      Admin.formDialog({
        title: rejected ? 'Từ chối đơn nghỉ / đổi lịch' : 'Duyệt đơn nghỉ cá nhân / đổi lịch',
        fields: rejected ? [{ name:'lyDoTuChoi', label:'Lý do từ chối', required:true, maxLength:255 }] : [],
        submit: 'Xác nhận',
        onSubmit: async body => {
          await Admin.api(`/api/academic/leave-requests/personal/${button.dataset.id}/decide`, 'POST', { trangThai: rejected ? 'tu_choi' : 'da_duyet', ...body });
          toast('Đã xử lý đơn và gửi thông báo cho người gửi.', 'ok');
          Admin.render();
        }
      });
    }));
  }

  StaffUI.register('staff-approve', 'Duyệt nghỉ cá nhân, Đổi lịch & Học bù', async () => {
    const [personal, classes, makeupList] = await Promise.all([
      Admin.api('/api/academic/leave-requests/personal'),
      Admin.api('/api/academic/leave-requests/class-overview'),
      Admin.api(StaffUI.prefix + '/makeups')
    ]);
    return { personal, classes, makeups: makeupList };
  }, data => {
    requests = data.personal || [];
    classRequests = data.classes || [];
    makeups = data.makeups || [];

    const pendingPersonal = requests.filter(r => r.trangThai === 'cho_duyet').length;
    const pendingClass = classRequests.filter(r => r.trangThai === 'cho_duyet').length;

    Admin.$('staffApproveKpis').innerHTML = [
      kpi('Đơn chờ Giáo vụ duyệt', String(pendingPersonal), `Tổng ${requests.length} đơn cá nhân/đổi lịch`, pendingPersonal ? 'w' : 'g', true),
      kpi('Đơn nghỉ cả lớp (Admin duyệt)', String(pendingClass), `Tổng ${classRequests.length} đơn theo dõi`, 'p'),
      kpi('Lịch học bù đã xếp', String(makeups.length), 'Theo thỏa thuận với phụ huynh', 'i'),
      kpi('Đã duyệt / Từ chối', String(requests.length - pendingPersonal), 'Đơn cá nhân đã hoàn tất', 'g'),
    ].join('');

    Admin.$('saPendingCount').textContent = String(pendingPersonal);
    Admin.$('saClassCount').textContent = String(classRequests.length);
    Admin.$('saMakeupCount').textContent = String(makeups.length);

    filter = pendingPersonal > 0 ? 'pending' : 'all';
    Admin.$('staffApproveFilterChips')?.querySelectorAll('[data-saf]').forEach(b => b.classList.toggle('on', b.dataset.saf === filter));

    Admin.$('staffApproveTabs')?.querySelectorAll('[data-sa-tab]').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.saTab)));
    Admin.$('staffApproveFilterChips')?.querySelectorAll('[data-saf]').forEach(btn => btn.addEventListener('click', () => {
      filter = btn.dataset.saf;
      Admin.$('staffApproveFilterChips').querySelectorAll('[data-saf]').forEach(b => b.classList.toggle('on', b === btn));
      renderPersonal();
    }));

    StaffUI.rows('staffClassLeaveBody', 5, classRequests.map(row => `<tr>
      <td><b>${Admin.esc(row.maLop)}</b> · <span class="code-short">NL-${row.id}</span><br><small>${Admin.esc(row.ngay)}</small></td>
      <td>${Admin.esc(row.nguoiGui)}</td>
      <td><span class="clamp-2" title="${Admin.esc(row.lyDo || '')}">${Admin.esc(row.lyDo)}</span></td>
      <td>${Admin.tag(statusLbl[row.trangThai] || row.trangThai, row.trangThai === 'da_duyet' ? 'ok' : (row.trangThai === 'cho_duyet' ? 'w' : 'r'))}</td>
      <td class="act-col">${Admin.user.role === 'ADMIN' ? '<button type="button" class="btn sm" data-act="set-portal" data-portal="admin" data-screen="admin-exceptions">Mở Admin</button>' : `<small>${row.trangThai === 'cho_duyet' ? 'Chờ Admin' : 'Đã xử lý'}</small>`}</td>
    </tr>`), 'Chưa có đơn nghỉ cả lớp nào.');

    StaffUI.rows('makeupStudentsBody', 5, makeups.map(row => `<tr>
      <td><b>${Admin.esc(row.tenHocVien)}</b><br><small class="code-short">HB-${row.id}</small></td>
      <td><b>${Admin.esc(row.maLop)}</b><br><small>${Admin.esc(row.ngay)} ${Admin.esc(row.gioBatDau.slice(0, 5))}–${Admin.esc(row.gioKetThuc.slice(0, 5))}</small></td>
      <td>${row.donXinNghiId ? 'Đơn #' + row.donXinNghiId : 'Thỏa thuận'}</td>
      <td>${Admin.esc(row.nguoiXep)}</td>
      <td><span class="clamp-2">${Admin.esc(row.ghiChu || '—')}</span></td>
    </tr>`), 'Chưa có buổi học bù cá nhân nào được xếp.');

    renderPersonal();
    switchTab(activeTab);
  });

  window.filterStaffApprove = value => {
    filter = value;
    renderPersonal();
  };
})();
