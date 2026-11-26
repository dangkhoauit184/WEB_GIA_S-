'use strict';
(() => {
  const states = { sap_bat_dau:'Sắp bắt đầu', dang_hoc:'Đang học', tam_dung:'Tạm dừng', hoan_thanh:'Hoàn thành', huy:'Đã hủy' };
  const kpi = Admin.kpi;

  let allClasses = [], clsFilter = 'all', clsQ = '';

  function renderClasses() {
    const q = clsQ.trim().toLowerCase();
    const list = allClasses.filter(r => {
      if (clsFilter === 'dang_hoc' && r.trangThai !== 'dang_hoc') return false;
      if (clsFilter === 'sap_bat_dau' && r.trangThai !== 'sap_bat_dau') return false;
      if (clsFilter === 'other' && ['dang_hoc', 'sap_bat_dau'].includes(r.trangThai)) return false;
      if (q && ![r.maLop, r.tenLop, r.tenKhoa, r.tenNguoiDayChinh].join(' ').toLowerCase().includes(q)) return false;
      return true;
    });
    StaffUI.rows('classBody', 5, list.map(row => `<tr>
      <td><b>${Admin.esc(row.maLop)}</b> · ${Admin.esc(row.tenLop)}<br><small>${Admin.esc(row.tenKhoa || 'Chưa gắn khóa')}</small></td>
      <td>${Admin.esc(row.lichTuan || 'Chưa có lịch')}<br><small>${Admin.esc(row.phongHoc || 'Chưa chọn phòng')}</small> ${Admin.tag(states[row.trangThai] || row.trangThai, row.trangThai === 'dang_hoc' ? 'ok' : (row.trangThai === 'huy' ? 'r' : 'w'))}</td>
      <td>${Admin.esc(row.tenNguoiDayChinh || '—')}${row.tenTroGiang ? `<br><small>TG: ${Admin.esc(row.tenTroGiang)}</small>` : ''}</td>
      <td><b>${row.siSoHienTai}/${row.siSoToiDa}</b></td>
      <td class="act-col">${Admin.button('Chi tiết', 'class-detail', row.id)}</td>
    </tr>`), 'Chưa có lớp học phù hợp bộ lọc.');
    Admin.$('classBody').querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => showClass(Number(button.dataset.id))));
  }

  StaffUI.register('staff-classes', 'Lớp học & Sĩ số', () => Admin.api('/api/academic/classes'), classes => {
    allClasses = classes || [];
    clsFilter = 'all'; clsQ = '';
    const active = allClasses.filter(r => r.trangThai === 'dang_hoc').length;
    const upcoming = allClasses.filter(r => r.trangThai === 'sap_bat_dau').length;
    const totalStudents = allClasses.reduce((a, r) => a + Number(r.siSoHienTai || 0), 0);
    const totalCap = allClasses.reduce((a, r) => a + Number(r.siSoToiDa || 0), 0);
    if (Admin.$('staffClassesKpis')) {
      Admin.$('staffClassesKpis').innerHTML = [
        kpi('Tổng số lớp', String(allClasses.length), `${active} lớp đang học`, '', true),
        kpi('Sắp bắt đầu', String(upcoming), 'Chờ khai giảng / xếp lịch', 'i'),
        kpi('Tổng sĩ số đang học', `${totalStudents}/${totalCap}`, totalCap ? `Lấp đầy ${Math.round(totalStudents * 100 / totalCap)}%` : 'Chưa có chỗ', 'g'),
        kpi('Lớp chưa có lịch tuần', String(allClasses.filter(r => !r.lichTuan).length), 'Cần xếp lịch trên Thời khóa biểu', 'w'),
      ].join('');
    }
    renderClasses();
    Admin.$('staffClassSearch')?.addEventListener('input', e => { clsQ = e.target.value; renderClasses(); });
    Admin.$('staffClassPills')?.querySelectorAll('[data-scf]').forEach(btn => btn.addEventListener('click', () => {
      clsFilter = btn.dataset.scf;
      Admin.$('staffClassPills').querySelectorAll('[data-scf]').forEach(b => b.classList.toggle('on', b === btn));
      renderClasses();
    }));

    const adminButton = document.querySelector('#staff-classes [data-act="set-portal"]');
    if (adminButton) adminButton.hidden = Admin.user.role !== 'ADMIN';
    const scheduleButton = document.querySelector('#staff-classes [data-act="open-session-form"]');
    if (scheduleButton) {
      scheduleButton.removeAttribute('data-act');
      scheduleButton.addEventListener('click', async () => { try { await loadAdminCalendar(); openSessionForm(); } catch (error) { toast(error.message, 'err'); } });
      scheduleButton.disabled = !StaffUI.can('VanHanhBuoiHoc', 'add');
    }
  });

  async function showClass(id) {
    try {
      const row = await Admin.api('/api/academic/classes/' + id);
      Admin.dialog(row.maLop + ' · ' + row.tenLop, `<div class="dialog-body">
        <div class="ec-info-box">${Admin.esc(row.tenMonHoc)} · ${Admin.esc(row.tenCapLop)} · Dạy chính: <b>${Admin.esc(row.tenNguoiDayChinh || '—')}</b> · Sĩ số: <b>${row.siSoHienTai}/${row.siSoToiDa}</b></div>
        <h3 style="margin:8px 0 6px;font-size:13.5px">Học viên trong lớp</h3>
        ${Admin.table([{ key:'maHv', label:'Mã học viên' }, { key:'hoTen', label:'Học viên' }, { key:'trangThai', label:'Trạng thái' }], row.hocViens || [], 'Chưa có học viên.')}
        <h3 style="margin:12px 0 6px;font-size:13.5px">Lịch tuần</h3>
        ${Admin.table([{ label:'Thứ', render:r => Number(r.thu) === 8 ? 'CN' : 'Thứ ' + r.thu }, { key:'gioBatDau', label:'Bắt đầu' }, { key:'gioKetThuc', label:'Kết thúc' }, { key:'phongHoc', label:'Phòng' }], row.lichHocs || [], 'Chưa có lịch tuần.')}
      </div><div class="dialog-foot"><button type="button" class="btn sm" data-close-dialog="1">Đóng</button><button type="button" class="btn p sm" id="staff-class-calendar">Mở thời khóa biểu</button></div>`);
      Admin.$('staff-class-calendar').addEventListener('click', () => { Admin.$('admin-dialog').close(); calFilters.classCode = row.maLop; showS('staff-schedule'); });
    } catch (error) { toast(error.message, 'err'); }
  }
})();
