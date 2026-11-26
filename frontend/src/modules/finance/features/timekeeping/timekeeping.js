'use strict';
(() => {
  const Admin = window.Admin;
  const esc = Admin.esc, kpi = Admin.kpi;
  const empty = columns => Admin.emptyRow(columns, 'Chưa có dữ liệu phù hợp.');
  const confirmation = Admin.confirmDialog;
  const decisionLabel = value => ({ cho_duyet:'Chờ duyệt', da_duyet:'Đã duyệt', tu_choi:'Từ chối' }[value] || value);

  let classLeaves = [], corrections = [], closedPayrolls = [];
  let activeTab = 'class-leave', leaveFilter = 'cho_duyet', corrFilter = 'cho_duyet';

  function renderClassLeave() {
    const list = leaveFilter === 'all' ? classLeaves : classLeaves.filter(r => r.trangThai === 'cho_duyet');
    Admin.$('adminClassLeaveBody').innerHTML = list.map(row => `<tr>
      <td><b>${esc(row.maLop)}</b> · <span class="code-short">NL-${row.id}</span><br><small>${esc(Admin.date(row.ngay))}</small></td>
      <td>${esc(row.nguoiGui)}</td>
      <td><span class="clamp-2" title="${esc(row.lyDo || '')}">${esc(row.lyDo)}</span></td>
      <td>${Admin.tag(decisionLabel(row.trangThai), row.trangThai === 'cho_duyet' ? 'w' : (row.trangThai === 'da_duyet' ? 'ok' : 'r'))}</td>
      <td class="act-col">${row.trangThai === 'cho_duyet'
        ? `<div class="act-row"><button type="button" class="btn p sm" data-act="approve-leave-or-adj" data-type="class" data-id="${row.id}" data-status="da_duyet">Duyệt</button><button type="button" class="btn sm danger" data-act="approve-leave-or-adj" data-type="class" data-id="${row.id}" data-status="tu_choi">Từ chối</button></div>`
        : '<small>Đã xử lý</small>'}</td>
    </tr>`).join('') || empty(5);
  }

  function renderCorrections() {
    const list = corrFilter === 'all' ? corrections : corrections.filter(r => r.trangThai === 'cho_duyet');
    Admin.$('adminTimeAdjBody').innerHTML = list.map(row => `<tr>
      <td><b>${esc(row.hoTen)}</b><br><small class="code-short">DC-${row.id}</small></td>
      <td><b>${esc(row.maLop)}</b><br><small>${esc(Admin.date(row.ngay))}</small></td>
      <td><span style="font-size:12px">${esc(Admin.date(row.checkinMoi))} → ${esc(Admin.date(row.checkoutMoi))}</span><br><small class="clamp-2" title="${esc(row.lyDo || '')}">${esc(row.lyDo)}</small></td>
      <td><b style="white-space:nowrap">${row.loai === 'huy_cong' ? '0 giờ' : (row.phutDeXuat != null ? Number((Number(row.phutDeXuat) / 60).toFixed(2)) + ' giờ' : 'Chưa đủ')}</b></td>
      <td>${Admin.tag(decisionLabel(row.trangThai), row.trangThai === 'cho_duyet' ? 'w' : (row.trangThai === 'da_duyet' ? 'ok' : 'r'))}</td>
      <td class="act-col">${row.trangThai === 'cho_duyet'
        ? `<div class="act-row"><button type="button" class="btn p sm" data-act="approve-leave-or-adj" data-type="correction" data-id="${row.id}" data-status="da_duyet">Duyệt</button><button type="button" class="btn sm danger" data-act="approve-leave-or-adj" data-type="correction" data-id="${row.id}" data-status="tu_choi">Từ chối</button></div>`
        : '<small>Đã xử lý</small>'}</td>
    </tr>`).join('') || empty(6);
  }

  function switchTab(tab) {
    activeTab = tab;
    Admin.$('exPaneLeave').hidden = tab !== 'class-leave';
    Admin.$('exPaneCorr').hidden = tab !== 'corrections';
    Admin.$('exPaneUnlock').hidden = tab !== 'unlock';
    Admin.$('adminExcTabs')?.querySelectorAll('[data-ex-tab]').forEach(b => b.classList.toggle('on', b.dataset.exTab === tab));
  }

  Admin.register('admin-exceptions', async () => {
    const results = await Promise.all([
      AdminClassLeave.list(),
      Admin.api('/api/finance/timekeeping/corrections'),
      Admin.api('/api/finance/payroll?month=' + Admin.period)
    ]);
    classLeaves = results[0] || [];
    corrections = results[1] || [];
    closedPayrolls = (results[2]?.items || []).filter(r => r.trangThai === 'da_chot');

    return {
      title: 'Phê duyệt Ngoại lệ & Mở khóa kỳ công',
      html: Admin.view('admin-exceptions'),
      bind() {
        const pendingLeaves = classLeaves.filter(r => r.trangThai === 'cho_duyet').length;
        const pendingCorr = corrections.filter(r => r.trangThai === 'cho_duyet').length;
        const totalPending = pendingLeaves + pendingCorr;

        Admin.$('adminExcKpis').innerHTML = [
          kpi('Tổng đơn chờ Admin duyệt', String(totalPending), totalPending ? 'Cần xem xét ngay' : 'Đã xử lý hết', totalPending ? 'w' : 'g', true),
          kpi('Nghỉ nguyên buổi cả lớp', String(pendingLeaves), `Tổng ${classLeaves.length} đơn`, pendingLeaves ? 'r' : 'g'),
          kpi('Điều chỉnh giờ công', String(pendingCorr), `Tổng ${corrections.length} yêu cầu`, pendingCorr ? 'w' : 'i'),
          kpi('Bảng công đã chốt chưa trả', String(closedPayrolls.length), `Kỳ ${Admin.date(Admin.period)}`, 'p'),
        ].join('');

        Admin.$('exLeaveCount').textContent = String(pendingLeaves);
        Admin.$('exCorrCount').textContent = String(pendingCorr);
        Admin.$('exUnlockCount').textContent = String(closedPayrolls.length);

        leaveFilter = pendingLeaves > 0 ? 'cho_duyet' : 'all';
        corrFilter = pendingCorr > 0 ? 'cho_duyet' : 'all';
        Admin.$('exLeavePills')?.querySelectorAll('[data-lf]').forEach(b => b.classList.toggle('on', b.dataset.lf === leaveFilter));
        Admin.$('exCorrPills')?.querySelectorAll('[data-cf]').forEach(b => b.classList.toggle('on', b.dataset.cf === corrFilter));

        Admin.$('exceptionsMonth').value = Admin.period;
        Admin.$('adminUnlockBody').innerHTML = closedPayrolls.map(row => `<tr>
          <td><b>${esc(row.hoTen)}</b><br><small class="code-short">BC-${row.id}</small></td>
          <td>${esc(Admin.date(Admin.period))}</td>
          <td><span class="clamp-2">${esc(row.ghiChu || '—')}</span></td>
          <td>${Admin.tag('Đã chốt', 'purple')}</td>
          <td class="act-col"><button type="button" class="btn purple sm" data-act="unlock-payroll" data-id="${row.id}">Mở khóa</button></td>
        </tr>`).join('') || empty(5);

        Admin.$('adminExcTabs')?.querySelectorAll('[data-ex-tab]').forEach(b => b.addEventListener('click', () => switchTab(b.dataset.exTab)));
        Admin.$('exLeavePills')?.querySelectorAll('[data-lf]').forEach(btn => btn.addEventListener('click', () => {
          leaveFilter = btn.dataset.lf;
          Admin.$('exLeavePills').querySelectorAll('[data-lf]').forEach(b => b.classList.toggle('on', b === btn));
          renderClassLeave();
        }));
        Admin.$('exCorrPills')?.querySelectorAll('[data-cf]').forEach(btn => btn.addEventListener('click', () => {
          corrFilter = btn.dataset.cf;
          Admin.$('exCorrPills').querySelectorAll('[data-cf]').forEach(b => b.classList.toggle('on', b === btn));
          renderCorrections();
        }));

        renderClassLeave();
        renderCorrections();
        switchTab(activeTab);
      }
    };
  });

  Admin.actions({
    'approve-leave-or-adj': el => window.adminApproveLeaveOrAdj(el.dataset.type, Number(el.dataset.id), el.dataset.status),
  });
  window.adminApproveLeaveOrAdj = (type, id, status) => confirmation(
    status === 'da_duyet' ? 'Admin phê duyệt' : 'Admin từ chối',
    type === 'class' ? 'Yêu cầu nghỉ cả lớp' : 'Yêu cầu điều chỉnh giờ công',
    async body => {
      const decision = { trangThai: status, ghiChu: body.ghiChu || null };
      if (type === 'class') await AdminClassLeave.decide(id, decision);
      else await Admin.api(`/api/finance/timekeeping/corrections/${id}/decision`, 'POST', decision);
      toast('Đã xử lý và cập nhật dữ liệu gốc.', 'ok');
      Admin.render();
    },
    [{ name:'ghiChu', label:'Ghi chú xử lý', type:'textarea', maxLength:255 }]
  );
})();
