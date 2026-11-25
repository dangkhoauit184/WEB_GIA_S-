'use strict';
(() => {
  const Admin = window.Admin;
  const money = Admin.money, esc = Admin.esc, empty = Admin.emptyRow, kpi = Admin.kpi;
  const roleLabel = type => type?.includes('giao_vien') ? 'Giáo viên' : (type?.includes('gia_su') ? 'Gia sư' : 'Trợ giảng');
  const confirmation = Admin.confirmDialog;

  let payroll = [], rateHistory = [], activePrTab = 'sheet', prFilter = 'all', prQuery = '', rateQuery = '';

  window.changePayrollMonth = value => { if (value) { Admin.period = value; Admin.render(); } };

  function renderPayrollTable() {
    const q = prQuery.trim().toLowerCase();
    const list = payroll.filter(row => {
      if (prFilter !== 'all' && row.trangThai !== prFilter) return false;
      if (q && ![row.hoTen, roleLabel(row.phanLoai), row.id ? 'BC-' + row.id : ''].join(' ').toLowerCase().includes(q)) return false;
      return true;
    });
    const body = Admin.$('adminPayrollBody');
    if (!body) return;
    body.innerHTML = list.map(row => `<tr>
      <td><b class="mono">${row.id ? 'BC-' + row.id : 'Chưa chốt'}</b></td>
      <td><b>${esc(row.hoTen)}</b></td>
      <td>${Admin.tag(roleLabel(row.phanLoai), 'i')}</td>
      <td><b class="mono">${money(row.donGiaGioChuan)}/giờ</b></td>
      <td>${row.soBuoi} buổi <small>(${row.soLanTre} trễ)</small></td>
      <td><b>${Number((Number(row.tongPhut || 0) / 60).toFixed(2))} giờ</b></td>
      <td><b style="color:var(--ok)">${money(row.thucNhan)}</b></td>
      <td>${Admin.tag({ mo:'Tạm tính', da_chot:'Đã chốt', da_tra:'Đã chi trả' }[row.trangThai], row.trangThai === 'mo' ? 'w' : (row.trangThai === 'da_chot' ? 'purple' : 'ok'))}</td>
      <td><div class="row">
        ${row.trangThai === 'mo' ? `<button type="button" class="btn p sm" data-act="close-one-payroll" data-id="${row.userId}">Chốt công</button>`
          : row.trangThai === 'da_chot' ? `<button type="button" class="btn p sm" data-act="mark-payroll-paid" data-id="${row.userId}">Xác nhận trả</button>` : ''}
        <button type="button" class="btn sm" data-act="print-payslip" data-id="${row.userId}">In phiếu</button>
      </div></td>
    </tr>`).join('') || empty(9);
  }

  function renderRateHistory() {
    const q = rateQuery.trim().toLowerCase();
    const list = rateHistory.filter(row => !q || [row.hoTen, row.tenAdmin].join(' ').toLowerCase().includes(q));
    const body = Admin.$('rateHistoryBody');
    if (!body) return;
    body.innerHTML = list.map(row => `<tr>
      <td><b>${esc(row.hoTen)}</b></td>
      <td><b class="mono">${money(row.donGiaGio)}/giờ</b></td>
      <td class="mono">${esc(Admin.date(row.hieuLucTu))}</td>
      <td>${esc(row.tenAdmin)}</td>
      <td>Cập nhật đơn giá giờ</td>
    </tr>`).join('') || empty(5);
  }

  function switchPrTab(tab) {
    activePrTab = tab;
    Admin.$('prPaneSheet').hidden = tab !== 'sheet';
    Admin.$('prPaneRates').hidden = tab !== 'rates';
    Admin.$('adminPayrollTabs')?.querySelectorAll('[data-pr-tab]').forEach(b => b.classList.toggle('on', b.dataset.prTab === tab));
  }

  Admin.register('admin-payroll', async () => {
    const data = await Admin.api('/api/finance/payroll?month=' + Admin.period);
    payroll = data.items || [];
    rateHistory = data.history || [];
    return {
      title: 'Công, Đơn giá giờ & Lương',
      html: Admin.view('admin-payroll'),
      bind() {
        Admin.$('payrollMonth').value = Admin.period;
        const [py, pm] = String(Admin.period).split('-');
        const tabBtn = Admin.$('prTabSheetTitle');
        if (tabBtn) tabBtn.innerHTML = `Bảng công &amp; Lương tháng ${esc(pm)}/${esc(py)} <span class="ec-count" id="prSheetCount">${payroll.length}</span>`;
        if (Admin.$('prRateCount')) Admin.$('prRateCount').textContent = String(rateHistory.length);

        const totalPay = payroll.reduce((a, r) => a + Number(r.thucNhan || 0), 0);
        const totalMins = payroll.reduce((a, r) => a + Number(r.tongPhut || 0), 0);
        const totalSessions = payroll.reduce((a, r) => a + Number(r.soBuoi || 0), 0);
        const paidRows = payroll.filter(r => r.trangThai === 'da_tra');
        const closedRows = payroll.filter(r => r.trangThai === 'da_chot');
        const openRows = payroll.filter(r => r.trangThai === 'mo');
        Admin.$('adminPayrollKpis').innerHTML = [
          kpi(`Tổng quỹ lương T${Number(pm)}/${py}`, money(totalPay), `${payroll.length} nhân sự giảng dạy`, '', true),
          kpi('Tổng giờ công hợp lệ', `${Number((totalMins / 60).toFixed(2))} giờ`, `${totalSessions} buổi đã dạy`, 'i'),
          kpi('Đã chi trả', money(paidRows.reduce((a, r) => a + Number(r.thucNhan || 0), 0)), `${paidRows.length} bảng công đã trả`, 'g'),
          kpi('Chờ chốt / Chờ trả', `${openRows.length} mở · ${closedRows.length} chốt`, money(closedRows.reduce((a, r) => a + Number(r.thucNhan || 0), 0)) + ' chờ chi trả', 'w'),
        ].join('');

        prFilter = 'all'; prQuery = ''; rateQuery = '';
        Admin.$('adminPayrollTabs')?.querySelectorAll('[data-pr-tab]').forEach(b => b.addEventListener('click', () => switchPrTab(b.dataset.prTab)));
        Admin.$('prSearch')?.addEventListener('input', e => { prQuery = e.target.value; renderPayrollTable(); });
        Admin.$('prRateSearch')?.addEventListener('input', e => { rateQuery = e.target.value; renderRateHistory(); });
        Admin.$('prStatusPills')?.querySelectorAll('[data-pf]').forEach(btn => btn.addEventListener('click', () => {
          prFilter = btn.dataset.pf;
          Admin.$('prStatusPills').querySelectorAll('[data-pf]').forEach(b => b.classList.toggle('on', b === btn));
          renderPayrollTable();
        }));
        switchPrTab(activePrTab);
        renderPayrollTable();
        renderRateHistory();
      }
    };
  });

  window.closeOnePayroll = userId => confirmation('Admin chốt công tháng', Admin.period, async () => {
    await Admin.api('/api/finance/payroll/close', 'POST', { userId, month: Admin.period });
    toast('Đã chốt công.', 'ok'); Admin.render();
  });
  window.staffClosePayrollMonth = () => confirmation('Admin chốt công tháng', Admin.period + ' · Chốt các bảng đang mở', async () => {
    const candidates = payroll.filter(row => row.trangThai === 'mo');
    const errors = []; let count = 0;
    for (const row of candidates) {
      try { await Admin.api('/api/finance/payroll/close', 'POST', { userId: row.userId, month: Admin.period }); count++; }
      catch (error) { errors.push(row.hoTen + ' · ' + error.message); }
    }
    Admin.render();
    if (errors.length) throw new Error(`Đã chốt ${count}/${candidates.length} bảng.\n` + errors.join('\n'));
    toast(`Đã chốt ${count} bảng công.`, 'ok');
  });
  window.adminMarkPayrollPaid = userId => {
    const row = payroll.find(item => item.userId === userId);
    confirmation('Xác nhận chi trả lương', `${row.hoTen} · ${Admin.period} · ${money(row.thucNhan)}`, async body => {
      await Admin.api(`/api/finance/payroll/${row.id}/pay`, 'POST', { maGiaoDich: body.maGiaoDich || null });
      toast('Đã ghi nhận chi trả lương.', 'ok'); Admin.render();
    }, [{ name:'maGiaoDich', label:'Mã đối chiếu chi trả', maxLength:60 }]);
  };
  window.adminUnlockPayroll = id => confirmation('Admin mở khóa bảng công', 'Kỳ đã chốt chưa trả', async body => {
    await Admin.api(`/api/finance/payroll/${id}/unlock`, 'POST', { lyDo: body.lyDo });
    toast('Đã mở khóa bảng công.', 'ok'); Admin.render();
  }, [{ name:'lyDo', label:'Lý do mở khóa', required:true, maxLength:255 }]);
  window.printPayslip = async userId => {
    try {
      const person = payroll.find(row => row.userId === userId);
      const rows = await Admin.api(`/api/finance/payroll/${userId}/sessions?month=${Admin.period}`);
      const statusLabel = { mo:'Tạm tính', da_chot:'Đã chốt', da_tra:'Đã chi trả' }[person.trangThai] || person.trangThai;
      Admin.dialog('PHIẾU LƯƠNG & BẢNG ĐỐI SOÁT CÔNG', `<div class="dialog-body"><h2>TRUNG TÂM EDUCENTER ERP</h2><p>Kỳ công ${esc(Admin.period)} · ${esc(person.hoTen)}</p>${Admin.table([{ key:'ngay', label:'Ngày' }, { key:'maLop', label:'Lớp' }, { label:'Số giờ công', render:row => row.soPhutTinhCong != null ? Number((Number(row.soPhutTinhCong) / 60).toFixed(2)) + ' giờ' : 'Chưa đủ giờ' }, { label:'Đơn giá giờ', render:row => money(row.donGia) }, { label:'Tiền công', render:row => money(row.tien) }], rows)}<div class="item"><span>Thưởng</span><b>${money(person.thuong)}</b></div><div class="item"><span>Khấu trừ</span><b>${money(person.khauTru)}</b></div><div class="item"><span>Thực nhận</span><b>${money(person.thucNhan)}</b></div><p>Người xác nhận ${esc(Admin.user.hoTen)} · ${esc(statusLabel)}</p></div><div class="dialog-foot"><button type="button" class="btn p" data-act="print-window">In / Lưu PDF</button></div>`);
    } catch (error) { toast(error.message, 'err'); }
  };
  Admin.actions({
    'close-payroll-month': () => window.staffClosePayrollMonth(),
    'close-one-payroll': el => window.closeOnePayroll(Number(el.dataset.id)),
    'mark-payroll-paid': el => window.adminMarkPayrollPaid(Number(el.dataset.id)),
    'unlock-payroll': el => window.adminUnlockPayroll(Number(el.dataset.id)),
    'print-payslip': el => window.printPayslip(Number(el.dataset.id)),
  });
})();
