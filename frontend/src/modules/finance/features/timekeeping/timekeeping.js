'use strict';
(() => {
  const Admin = window.Admin;
  const esc = Admin.esc;
  const empty = columns => `<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu phù hợp.</td></tr>`;
  const confirmation = Admin.confirmDialog;
  const decisionLabel = value => ({ cho_duyet:'Chờ Admin duyệt', da_duyet:'Đã duyệt', tu_choi:'Đã từ chối' }[value] || value);
  const kpi = (l, v, s, tone = '', primary = false) =>
    `<div class="ec-kpi ${primary ? 'primary' : ''} ${tone ? 'tone-' + tone : ''}"><span class="ec-kpi-l">${esc(l)}</span><b class="ec-kpi-v">${esc(v)}</b><span class="ec-kpi-s">${esc(s)}</span></div>`;

  let classLeaves = [], corrections = [], closedPayrolls = [];
  let activeTab = 'class-leave', leaveFilter = 'cho_duyet', corrFilter = 'cho_duyet';

  function renderClassLeave() {
    const list = leaveFilter === 'all' ? classLeaves : classLeaves.filter(r => r.trangThai === 'cho_duyet');
    Admin.$('adminClassLeaveBody').innerHTML = list.map(row => `<tr>
      <td><b>NL-${row.id}</b></td>
      <td><b>${esc(row.maLop)}</b> (${esc(row.ngay)})</td>
      <td>${esc(row.nguoiGui)}</td>
      <td>${esc(row.lyDo)}</td>
      <td>${Admin.tag(decisionLabel(row.trangThai), row.trangThai === 'cho_duyet' ? 'w' : (row.trangThai === 'da_duyet' ? 'ok' : 'r'))}</td>
      <td>${row.trangThai === 'cho_duyet'
        ? `<div class="row"><button class="btn p sm" onclick="adminApproveLeaveOrAdj('class',${row.id},'da_duyet')">Duyệt nghỉ lớp</button><button class="btn sm danger" onclick="adminApproveLeaveOrAdj('class',${row.id},'tu_choi')">Từ chối</button></div>`
        : 'Đã xử lý'}</td>
    </tr>`).join('') || empty(6);
  }

  function renderCorrections() {
    const list = corrFilter === 'all' ? corrections : corrections.filter(r => r.trangThai === 'cho_duyet');
    Admin.$('adminTimeAdjBody').innerHTML = list.map(row => `<tr>
      <td><b>DC-${row.id}</b></td>
      <td><b>${esc(row.hoTen)}</b></td>
      <td>${esc(row.maLop)} (${esc(row.ngay)})</td>
      <td>${esc(Admin.date(row.checkinMoi))} → ${esc(Admin.date(row.checkoutMoi))}<br><small>${esc(row.lyDo)}</small></td>
      <td><b>${row.loai === 'huy_cong' ? '0 giờ' : (row.phutDeXuat != null ? Number((Number(row.phutDeXuat) / 60).toFixed(2)) + ' giờ' : 'Chưa đủ giờ')}</b></td>
      <td>${Admin.tag(decisionLabel(row.trangThai), row.trangThai === 'cho_duyet' ? 'w' : (row.trangThai === 'da_duyet' ? 'ok' : 'r'))}</td>
      <td>${row.trangThai === 'cho_duyet'
        ? `<div class="row"><button class="btn p sm" onclick="adminApproveLeaveOrAdj('correction',${row.id},'da_duyet')">Duyệt chỉnh công</button><button class="btn sm danger" onclick="adminApproveLeaveOrAdj('correction',${row.id},'tu_choi')">Từ chối</button></div>`
        : 'Đã xử lý'}</td>
    </tr>`).join('') || empty(7);
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
          kpi('Bảng công đã chốt chưa trả', String(closedPayrolls.length), `Kỳ ${Admin.period}`, 'p'),
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
          <td><b class="mono">BC-${row.id}</b></td>
          <td><b>${esc(row.hoTen)}</b></td>
          <td>${esc(Admin.period)}</td>
          <td>${esc(row.ghiChu || '—')}</td>
          <td>${Admin.tag('Đã chốt, chưa trả', 'purple')}</td>
          <td><button class="btn purple sm" onclick="adminUnlockPayroll(${row.id})">Mở khóa bảng công</button></td>
        </tr>`).join('') || empty(6);

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
