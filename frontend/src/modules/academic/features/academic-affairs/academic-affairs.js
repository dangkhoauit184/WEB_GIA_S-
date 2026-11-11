'use strict';
window.StaffUI = (() => {
  const prefix = '/api/academic/academic-affairs';
  let templates;
  function can(feature, action = 'view') {
    return Admin.user?.role === 'ADMIN' || Admin.user?.permissions.some(row => row.functionName === feature && row[action]);
  }
  async function view(name) {
    if (!templates) {
      const response = await fetch('/admin-assets/academic-affairs.html');
      if (!response.ok) throw new Error('Không tải được giao diện Giáo vụ.');
      templates = new DOMParser().parseFromString(await response.text(), 'text/html');
    }
    return templates.getElementById(`view-${name}`).innerHTML;
  }
  function register(name, title, load, bind) {
    Admin.register(name, async () => {
      const [html, data] = await Promise.all([view(name), load()]);
      return { title, html, bind:() => bind(data) };
    });
  }
  function rows(id, columns, data, empty = 'Chưa có dữ liệu.') {
    Admin.$(id).innerHTML = data.length ? data.join('') : `<tr><td colspan="${columns}" class="admin-empty">${Admin.esc(empty)}</td></tr>`;
  }
  async function lookups() {
    const [catalog, courses] = await Promise.all([Admin.api('/api/administration/catalog'), Admin.api(`${prefix}/courses`)]);
    return { ...catalog, courses };
  }
  function select(name, label, data, key = 'id', text = 'ten', required = false) {
    return { name, label, type:'select', required, options:[{value:'', label:'Chọn '+label.toLowerCase()}, ...data.map(row => ({ value:row[key], label:row[text] }))], nullable:!required };
  }
  function search(id, callback) {
    const container = document.createElement('form');
    container.className = 'staff-search';
    container.innerHTML = '<label>Tìm kiếm<input name="q" placeholder="Nhập mã, tên hoặc số điện thoại" maxlength="150"></label><button class="btn">Tìm kiếm</button>';
    Admin.$(id).closest('.tw').before(container);
    container.addEventListener('submit', event => { event.preventDefault(); Admin.busy(event.submitter, () => callback(new FormData(container).get('q').trim())); });
  }
  function print(title, html) {
    Admin.dialog(title, `<div class="dialog-body staff-print"><h2>EDUCENTER ERP</h2>${html}</div><div class="dialog-foot"><button class="btn p" onclick="window.print()">In / Lưu PDF</button></div>`);
  }
  return { prefix, can, view, register, rows, lookups, select, search, print };
})();

StaffUI.register('staff-dash', 'Tổng quan công việc Giáo vụ', () => Admin.api(`${StaffUI.prefix}/dashboard`), data => {
  Admin.$('stNewReqCount').textContent = data.requests.total;
  Admin.$('stPendingLeaveCount').textContent = data.pendingLeave;
  Admin.$('stMakeupCount').textContent = data.makeups;
  Admin.$('stInvoiceCount').textContent = data.invoices.length;
  const tasks = [
    ...data.requests.items.map(row => [row.maYc+' · '+(row.tenHocVien || row.hoTenLienHe || 'Khách tư vấn'), 'Ghép người dạy', 'staff-intake']),
    ...data.leave.map(row => [`Đơn nghỉ #${row.id} · ${row.hoTen} · ${row.maLop}`, 'Duyệt đơn', 'staff-approve']),
    ...data.invoices.slice(0, 5).map(row => [`${row.maHd} · ${row.tenHocVien} · ${row.trangThai === 'nhap' ? 'Chờ phát hành' : 'Còn '+Admin.money(Number(row.phaiThu)-Number(row.daThu))}`, 'Xem hóa đơn', 'staff-billing'])
  ];
  Admin.$('staffTaskList').innerHTML = tasks.map(([label, button, screen]) => `<div class="item"><span>${Admin.esc(label)}</span><button class="btn sm p" onclick="showS('${screen}')">${button}</button></div>`).join('') || '<div class="admin-empty">Chưa có công việc chờ xử lý.</div>';
  document.querySelectorAll('#staff-dash [onclick]').forEach(button => {
    const feature = button.getAttribute('onclick').includes('staff-billing') ? 'HocPhiThanhToan' : button.getAttribute('onclick').includes('staff-intake') ? 'TiepNhanYeuCau' : 'VanHanhBuoiHoc';
    button.disabled = !StaffUI.can(feature);
  });
});

StaffUI.register('staff-schedule', 'Thời khóa biểu', async () => { currentPortal = 'staff'; await loadAdminCalendar(); }, () => renderSharedCalendar('staffCalContainer', 'staff'));

window.loadStaffNotifications = async () => {
  try {
    const rows = await Admin.api(`${StaffUI.prefix}/notifications`);
    Admin.$('notifList').innerHTML = rows.map(row => `<div class="np-item ${row.docLuc ? '' : 'unread'}" data-notif-id="${row.id}" onclick="readStaffNotification(${row.id},'${row.doiTuongLoai === 'hoa_don' ? 'staff-billing' : row.loai === 'de_xuat' ? 'staff-intake' : row.loai === 'duyet_don' ? 'staff-approve' : 'staff-schedule'}')"><div><b>${Admin.esc(row.tieuDe)}</b><p>${Admin.esc(row.noiDung || '')}</p><small>${Admin.esc(Admin.date(row.createdAt))}</small></div></div>`).join('') || '<div class="admin-empty">Chưa có thông báo.</div>';
    window.syncNotifBadge();
  } catch (error) { Admin.notify(error.message, true); }
};
window.readStaffNotification = async (id, screen) => {
  try { await Admin.api(`${StaffUI.prefix}/notifications/${id}/read`, 'POST', {}); await loadStaffNotifications(); showS(screen); Admin.$('notifPanel').classList.remove('open'); }
  catch (error) { Admin.notify(error.message, true); }
};
if (document.body.dataset.portal === 'admissions') {
  window.markAllNotifsRead = async () => {
    try {
      const rows = await Admin.api(`${StaffUI.prefix}/notifications`);
      await Promise.all(rows.filter(row => !row.docLuc).map(row => Admin.api(`${StaffUI.prefix}/notifications/${row.id}/read`, 'POST', {})));
      await loadStaffNotifications();
    } catch (error) { Admin.notify(error.message, true); }
  };
}
