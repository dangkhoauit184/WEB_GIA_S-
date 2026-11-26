'use strict';
(() => {
  let invoices = [], selected = null, keepSelectedId = null, invFilter = 'all', invQuery = '';
  const invStatusLabel = { nhap:'Nháp', chua_thanh_toan:'Chưa thu', mot_phan:'Thu 1 phần', da_thanh_toan:'Đã thu đủ', huy:'Đã hủy' };
  const invStatusTone = s => s === 'da_thanh_toan' ? 'ok' : (s === 'nhap' ? 'i' : (s === 'huy' ? 'r' : 'w'));
  const kpi = Admin.kpi;

  function renderBillingKpis() {
    const el = Admin.$('staffBillingKpis');
    if (!el) return;
    const draft = invoices.filter(r => r.trangThai === 'nhap');
    const unpaid = invoices.filter(r => ['chua_thanh_toan', 'mot_phan'].includes(r.trangThai));
    const paid = invoices.filter(r => r.trangThai === 'da_thanh_toan');
    const totalDebt = unpaid.reduce((acc, r) => acc + Math.max(0, Number(r.phaiThu) - Number(r.daThu)), 0);
    el.innerHTML = [
      kpi('Tổng hóa đơn', String(invoices.length), `${paid.length} đã thu đủ`, '', true),
      kpi('Hóa đơn nháp', String(draft.length), 'Chờ phát hành', 'i'),
      kpi('Chưa thu đủ', String(unpaid.length), `Còn ${Admin.money(totalDebt)}`, unpaid.length ? 'r' : 'g'),
      kpi('Đã thu đủ', String(paid.length), Admin.money(paid.reduce((a, r) => a + Number(r.daThu || 0), 0)), 'g'),
    ].join('');
  }

  function renderInvoiceTable() {
    const q = invQuery.trim().toLowerCase();
    const list = invoices.filter(row => {
      if (invFilter === 'nhap' && row.trangThai !== 'nhap') return false;
      if (invFilter === 'unpaid' && !['chua_thanh_toan', 'mot_phan'].includes(row.trangThai)) return false;
      if (invFilter === 'da_thanh_toan' && row.trangThai !== 'da_thanh_toan') return false;
      if (q && ![row.maHd, row.tenHocVien, row.tenKhoa].join(' ').toLowerCase().includes(q)) return false;
      return true;
    });
    StaffUI.rows('invBody', 4, list.map(row => {
      const rem = Math.max(0, Number(row.phaiThu) - Number(row.daThu));
      const isSel = selected && Number(selected.id) === Number(row.id);
      return `<tr class="${isSel ? 'ec-row-selected' : ''}">
        <td><b>${Admin.esc(row.tenHocVien)}</b><br><small><span class="code-short">${Admin.esc(row.maHd)}</span> · ${Admin.esc(row.tenKhoa || 'HĐ cũ')}</small></td>
        <td><b style="white-space:nowrap">${Admin.money(row.phaiThu)}</b><br><small style="color:${rem > 0 ? 'var(--err)' : 'var(--ok)'};font-weight:600">${rem > 0 ? 'Còn: ' + Admin.money(rem) : 'Đã thu đủ'}</small></td>
        <td>${Admin.tag(invStatusLabel[row.trangThai] || row.trangThai, invStatusTone(row.trangThai))}</td>
        <td class="act-col">${Admin.button(isSel ? 'Đang xem' : 'Chi tiết', 'select-invoice', row.id, isSel ? 'p' : '')}</td>
      </tr>`;
    }), 'Chưa có hóa đơn phù hợp bộ lọc.');
    Admin.$('invBody').querySelectorAll('[data-action]').forEach(btn => btn.addEventListener('click', () => selectInvoice(Number(btn.dataset.id))));
  }

  StaffUI.register('staff-billing', 'Hóa đơn trọn khóa & Thu phí', () => Admin.api('/api/finance/tuition/invoices'), data => {
    invoices = data || [];
    const restoreId = keepSelectedId && invoices.some(r => Number(r.id) === Number(keepSelectedId)) ? keepSelectedId : null;
    keepSelectedId = null;
    selected = null;
    invFilter = 'all';
    invQuery = '';
    renderBillingKpis();
    renderInvoiceTable();

    Admin.$('invSearch')?.addEventListener('input', e => { invQuery = e.target.value; renderInvoiceTable(); });
    Admin.$('invFilterPills')?.querySelectorAll('[data-if]').forEach(btn => btn.addEventListener('click', () => {
      invFilter = btn.dataset.if;
      Admin.$('invFilterPills').querySelectorAll('[data-if]').forEach(b => b.classList.toggle('on', b === btn));
      renderInvoiceTable();
    }));

    const isAdmin = Admin.user.role === 'ADMIN';
    if (Admin.$('invAdminPayBox')) Admin.$('invAdminPayBox').hidden = !isAdmin;
    if (Admin.$('invStaffPayNote')) Admin.$('invStaffPayNote').hidden = isAdmin;
    if (restoreId) selectInvoice(restoreId);
  });

  async function selectInvoice(id) {
    try {
      selected = await Admin.api('/api/finance/tuition/invoices/' + id);
      renderInvoiceTable();
      const rem = Math.max(0, Number(selected.phaiThu) - Number(selected.daThu));
      const isAdmin = Admin.user.role === 'ADMIN';
      Admin.$('invBanner').className = 'ec-info-box pri';
      Admin.$('invBanner').innerHTML = `<b>${Admin.esc(selected.maHd)} · ${Admin.esc(selected.tenHocVien)}</b><br><small>${Admin.esc(selected.tenKhoa || 'Hóa đơn lịch sử')} · ${selected.phatHanhLuc ? 'Phát hành lúc ' + Admin.esc(Admin.date(selected.phatHanhLuc)) : 'Đang ở trạng thái Nháp'}</small>`;
      Admin.$('invDetailPanel').hidden = false;
      Admin.$('invMetaGrid').innerHTML = `<div class="ec-inv-meta">
        <div><span>Phụ huynh</span><b>${Admin.esc(selected.tenPhuHuynh || '—')}</b></div>
        <div><span>Trạng thái</span>${Admin.tag(invStatusLabel[selected.trangThai] || selected.trangThai, invStatusTone(selected.trangThai))}</div>
        <div><span>Học phí trọn khóa</span><b>${Admin.money(selected.phaiThu)}</b></div>
        <div><span>Đã thu / Còn lại</span><b>${Admin.money(selected.daThu)}</b> / <b style="color:${rem > 0 ? 'var(--err)' : 'var(--ok)'}">${Admin.money(rem)}</b></div>
      </div>`;
      const pubBtn = Admin.$('btnPublishInv');
      if (pubBtn) pubBtn.hidden = selected.trangThai !== 'nhap';

      const receipts = selected.receipts || [];
      Admin.$('invReceiptsBox').innerHTML = receipts.length
        ? `<b style="font-size:12px;display:block;margin-bottom:6px">Lịch sử phiếu thu (${receipts.length})</b>` + Admin.table([
            { label:'Ngày thu', render:r => Admin.esc(Admin.date(r.ngayThanhToan)) },
            { key:'maGiaoDich', label:'Mã chứng từ' },
            { label:'Số tiền', render:r => `<b>${Admin.money(r.soTien)}</b>` },
            { key:'tenNguoiThu', label:'Xác nhận' },
          ], receipts)
        : '<p style="font-size:12px;color:var(--g500);margin:0">Chưa có phiếu thu nào được ghi nhận.</p>';

      if (Admin.$('invAdminPayBox')) {
        Admin.$('invAdminPayBox').hidden = !isAdmin || rem <= 0 || selected.trangThai === 'nhap' || selected.trangThai === 'huy';
        if (Admin.$('payAmt')) Admin.$('payAmt').value = rem;
      }
      if (Admin.$('invStaffPayNote')) Admin.$('invStaffPayNote').hidden = isAdmin || rem <= 0;
    } catch (error) { toast(error.message, 'err'); }
  }

  window.createCourseInvoicePrompt = async () => {
    try {
      const students = await Admin.api('/api/people/students');
      if (!students.length) {
        Admin.dialog('Chưa có học viên', '<div class="dialog-body"><p>Chưa có hồ sơ học viên nào. Hãy thêm học viên và đăng ký khóa học trước khi lập hóa đơn.</p></div>');
        return;
      }
      // Fetch registrations across students so Staff can pick an unbilled registration in 1 step
      const allRegs = await Promise.all(students.map(async s => {
        const list = await Admin.api(`${StaffUI.prefix}/students/${s.id}/registrations`).catch(() => []);
        return list.map(r => ({ ...r, tenHocVien: s.hoTen, maHv: s.maHv }));
      }));
      const billedIds = new Set(invoices.map(i => Number(i.dangKyKhoaHocId)).filter(Boolean));
      const available = allRegs.flat().filter(r => r.trangThai !== 'thoi_hoc' && !billedIds.has(Number(r.id)));
      if (!available.length) {
        Admin.dialog('Hóa đơn trọn khóa', '<div class="dialog-body"><p>Tất cả đăng ký khóa học hiện tại đều đã được lập hóa đơn, hoặc chưa có học viên nào đăng ký khóa.</p></div>');
        return;
      }
      Admin.formDialog({
        title: 'Tạo hóa đơn trọn khóa',
        fields: [
          { name:'dangKyKhoaHocId', label:'Đăng ký khóa học chưa lập hóa đơn', type:'select', required:true,
            options: available.map(r => ({ value:r.id, label:`DK-${r.id} · ${r.hoTen || r.tenHocVien} (${r.maHv}) · ${r.tenKhoa} · ${Admin.money(r.hocPhiSnapshot)}` })) },
          { name:'hanThanhToan', label:'Hạn thanh toán (tùy chọn)', type:'date', nullable:true }
        ],
        submit: 'Tạo hóa đơn nháp',
        onSubmit: async body => {
          await Admin.api('/api/finance/tuition/invoices', 'POST', { ...body, dangKyKhoaHocId: Number(body.dangKyKhoaHocId) });
          toast('Đã tạo hóa đơn nháp. Chọn hóa đơn để phát hành gửi phụ huynh.', 'ok');
          Admin.render();
        }
      });
    } catch (error) { toast(error.message, 'err'); }
  };

  window.publishSelectedInvoice = async () => {
    if (!selected) { toast('Chọn hóa đơn cần phát hành.', 'err'); return; }
    const targetId = selected.id;
    Admin.confirmDialog('Phát hành hóa đơn', `${selected.maHd} · ${Admin.money(selected.phaiThu)} · Gửi thông báo phụ huynh`, async () => {
      await Admin.api(`/api/finance/tuition/invoices/${targetId}/publish`, 'POST', {});
      keepSelectedId = targetId;
      toast('Đã phát hành hóa đơn và lưu thông báo phụ huynh.', 'ok');
      Admin.render();
    });
  };

  window.doPayment = () => {
    if (!selected) { toast('Chọn hóa đơn trước khi thu tiền.', 'err'); return; }
    const targetId = selected.id;
    Admin.formDialog({
      title: 'Admin xác nhận chứng từ thu tiền',
      fields: [{ name:'maGiaoDich', label:'Mã chứng từ (chống thu trùng)', required:true, maxLength:60 }],
      submit: 'Xác nhận thu',
      onSubmit: async body => {
        await Admin.api(`/api/finance/tuition/invoices/${targetId}/receipts`, 'POST', {
          ...body,
          soTien: Number(Admin.$('payAmt').value),
          phuongThuc: Admin.$('payMethodSel').value,
          ghiChu: Admin.$('payNote').value || null
        });
        keepSelectedId = targetId;
        toast('Đã ghi nhận phiếu thu và cập nhật công nợ.', 'ok');
        Admin.render();
      }
    });
  };

  window.printSelectedInvoice = async () => {
    if (!selected) { toast('Chọn hóa đơn cần in.', 'err'); return; }
    try {
      const row = await Admin.api('/api/finance/tuition/invoices/' + selected.id);
      StaffUI.print('Hóa đơn / Phiếu thu', `<h3>${Admin.esc(row.maHd)}</h3><p>${Admin.esc(row.tenHocVien)} · ${Admin.esc(row.tenPhuHuynh)} · ${Admin.esc(row.tenKhoa || '')}</p><p>Học phí trọn khóa ${Admin.money(row.phaiThu)} · Đã thu ${Admin.money(row.daThu)} · Còn lại ${Admin.money(Number(row.phaiThu) - Number(row.daThu))}</p>${Admin.table([{ key:'ngayThanhToan', label:'Ngày thu' }, { key:'maGiaoDich', label:'Mã chứng từ' }, { label:'Số tiền', render:r => Admin.money(r.soTien) }, { key:'tenNguoiThu', label:'Người xác nhận' }], row.receipts)}`);
    } catch (error) { toast(error.message, 'err'); }
  };
})();

(() => {
  const Admin = window.Admin;
  const money = Admin.money, esc = Admin.esc, empty = Admin.emptyRow, kpi = Admin.kpi;
  const confirmation = Admin.confirmDialog;

  let transactions = [], bankAccounts = [], payosStatus = {}, activePayTab = 'tx', txFilter = 'pending', txQuery = '';

  function bankSectionHtml() {
    return `<div class="ec-info-box ${payosStatus.ready ? 'pri' : ''}" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <span>${payosStatus.ready
        ? 'payOS đã kết nối webhook · Hệ thống chỉ ghi nhận phiếu thu sau khi xác thực giao dịch ngân hàng.'
        : `payOS chưa kích hoạt tự động (${!payosStatus.enabled ? 'chưa bật kết nối' : esc((payosStatus.missing || []).join(' · '))}). Dùng đối soát chuyển khoản thủ công hoặc thêm tài khoản nhận học phí bên dưới.`}</span>
      <div class="row">${payosStatus.configured ? '<button type="button" class="btn sm" data-act="admin-connect-payos">Kiểm tra webhook</button>' : ''}<button type="button" class="btn p sm" data-act="admin-bank-form">+ Thêm tài khoản QR</button></div>
    </div>` + Admin.table([
      { label:'Ảnh QR', render:r => r.qrImageHash ? `<button type="button" class="btn sm" data-act="admin-qr-preview" data-id="${esc(r.id)}">Xem QR</button>` : 'Chưa tải ảnh' },
      { label:'Ngân hàng', render:r => `${esc(r.bankName)} · <span class="mono">${esc(r.bankBin)}</span>` },
      { label:'Số tài khoản', key:'accountNo' },
      { label:'Chủ tài khoản', key:'accountName' },
      { label:'Trạng thái', render:r => Admin.tag(r.isDefault ? 'Mặc định' : (r.active ? 'Đang dùng' : 'Ngừng dùng'), r.isDefault ? 'ok' : '') + (r.isDemo ? ' · DEMO' : '') },
      { label:'Thao tác', render:r => `<div class="row"><button type="button" class="btn sm" data-act="admin-bank-form" data-id="${esc(r.id)}">Sửa</button>${!r.isDefault && r.active ? `<button type="button" class="btn sm p" data-act="admin-bank-default" data-id="${esc(r.id)}">Chọn mặc định</button>` : ''}</div>` }
    ], bankAccounts, 'Chưa có tài khoản nhận học phí. Bấm “+ Thêm tài khoản QR” để cấu hình tài khoản ngân hàng.');
  }

  function transferDetails(row) {
    const details = typeof row.transferDetails === 'string' ? JSON.parse(row.transferDetails) : row.transferDetails;
    if (row.paymentReview && row.trangThai !== 'thanh_cong') return `<b class="form-error">${esc(row.paymentReview)}</b>`;
    if (details?.bank) return `${esc(details.bank.bankName)} · ${esc(details.bank.accountNo)}<br>${esc(details.bank.accountName)}<br>Nội dung: ${esc(details.description || details.memo)}`;
    return details?.receivingAccount ? `${esc(details.receivingAccount.bankName)} · ${esc(details.receivingAccount.accountNo)}<br>${esc(details.receivingAccount.accountName)}<br>Nội dung: ${esc(details.memo)}${details.receivingAccount.isDemo ? '<br>DEMO · Không có tiền thật' : ''}` : 'Đối chiếu chứng từ chuyển khoản';
  }

  function renderTxTable() {
    const q = txQuery.trim().toLowerCase();
    const list = transactions.filter(row => {
      if (txFilter === 'pending' && !['cho_xac_nhan', 'khoi_tao'].includes(row.trangThai)) return false;
      if (txFilter === 'thanh_cong' && row.trangThai !== 'thanh_cong') return false;
      if (txFilter === 'other' && ['cho_xac_nhan', 'khoi_tao', 'thanh_cong'].includes(row.trangThai)) return false;
      if (q && ![`GD-${row.id}`, row.maHd, row.tenHocVien, row.tenPhuHuynh, row.maYeuCau].join(' ').toLowerCase().includes(q)) return false;
      return true;
    });
    const body = Admin.$('adminOnlineTxBody');
    if (!body) return;
    body.innerHTML = list.map(row => `<tr>
      <td><b class="code-short">GD-${row.id}</b><br><small>${esc(Admin.date(row.createdAt))}</small></td>
      <td><b>${esc(row.maHd)}</b><br><small>${esc(row.tenHocVien || '—')} · PH ${esc(row.tenPhuHuynh || '—')}</small></td>
      <td><b style="color:var(--ok);white-space:nowrap">${money(row.soTien)}</b><br>${Admin.tag(row.nhaCungCap, 'i')}</td>
      <td><div class="clamp-2" style="font-size:11.5px" title="${esc(row.maYeuCau)}"><span class="code-short">${esc(row.maYeuCau)}</span><br>${transferDetails(row)}</div></td>
      <td>${Admin.tag(row.trangThai === 'thanh_cong' ? 'Đã xác nhận' : ({ cho_xac_nhan:'Chờ đối soát', khoi_tao:'Khởi tạo', that_bai:'Thất bại', het_han:'Hết hạn', da_huy:'Đã hủy', huy:'Đã hủy' }[row.trangThai] || row.trangThai), row.trangThai === 'thanh_cong' ? 'ok' : (['cho_xac_nhan', 'khoi_tao'].includes(row.trangThai) ? 'w' : 'r'))}${row.trangThai === 'thanh_cong' && row.tenAdmin ? `<br><small>${esc(row.tenAdmin)}</small>` : ''}</td>
      <td class="act-col">${row.nhaCungCap === 'PAYOS' && row.trangThai !== 'thanh_cong'
        ? `<div class="act-row"><button type="button" class="btn p sm" data-act="admin-check-payos" data-id="${row.id}">Kiểm tra</button>${['khoi_tao', 'cho_xac_nhan'].includes(row.trangThai) ? `<button type="button" class="btn sm" data-act="admin-cancel-payos" data-id="${row.id}">Hủy</button>` : ''}</div>`
        : row.trangThai === 'cho_xac_nhan'
          ? `<button type="button" class="btn p sm" data-act="admin-confirm-tx" data-id="${row.id}">Xác nhận</button>`
          : `<small>${esc(row.thanhToanId ? 'Phiếu #' + row.thanhToanId : '—')}</small>`}</td>
    </tr>`).join('') || empty(6);
  }

  function switchPayTab(tab) {
    activePayTab = tab;
    Admin.$('apPaneTx').hidden = tab !== 'tx';
    Admin.$('apPaneBanks').hidden = tab !== 'banks';
    Admin.$('adminPayTabs')?.querySelectorAll('[data-pay-tab]').forEach(b => b.classList.toggle('on', b.dataset.payTab === tab));
  }

  Admin.register('admin-payments', async () => {
    const [data, banks, gateway] = await Promise.all([
      Admin.api('/api/finance/tuition/transactions'),
      Admin.api('/api/finance/tuition/bank-accounts'),
      Admin.api('/api/finance/tuition/payos/status')
    ]);
    transactions = data || [];
    bankAccounts = banks || [];
    payosStatus = gateway || {};
    return {
      title: 'Đối soát Thanh toán Web',
      html: Admin.view('admin-payments'),
      bind() {
        const pending = transactions.filter(r => r.trangThai === 'cho_xac_nhan');
        const confirmed = transactions.filter(r => r.trangThai === 'thanh_cong');
        const pendingSum = pending.reduce((a, r) => a + Number(r.soTien || 0), 0);
        const confirmedSum = confirmed.reduce((a, r) => a + Number(r.soTien || 0), 0);
        const defBank = bankAccounts.find(b => b.isDefault);
        Admin.$('adminPayKpis').innerHTML = [
          kpi('Chờ Admin đối soát', String(pending.length), money(pendingSum), pending.length ? 'w' : 'g', true),
          kpi('Đã xác nhận lập phiếu', String(confirmed.length), money(confirmedSum), 'g'),
          kpi('Tổng yêu cầu thanh toán', String(transactions.length), 'Mọi kênh chuyển khoản', 'i'),
          kpi('Tài khoản QR mặc định', defBank ? defBank.bankName : 'Chưa cấu hình', defBank ? defBank.accountNo : 'Bấm tab Tài khoản để thêm', defBank ? 'p' : 'w'),
        ].join('');

        Admin.$('apPendingCount').textContent = String(pending.length);
        Admin.$('apBankCount').textContent = String(bankAccounts.length);
        Admin.$('adminBankSection').innerHTML = bankSectionHtml();

        // Default to 'all' if no pending items so user immediately sees history
        txFilter = pending.length > 0 ? 'pending' : 'all';
        txQuery = '';
        Admin.$('apTxPills')?.querySelectorAll('[data-tf]').forEach(b => b.classList.toggle('on', b.dataset.tf === txFilter));

        Admin.$('adminPayTabs')?.querySelectorAll('[data-pay-tab]').forEach(b => b.addEventListener('click', () => switchPayTab(b.dataset.payTab)));
        Admin.$('apTxSearch')?.addEventListener('input', e => { txQuery = e.target.value; renderTxTable(); });
        Admin.$('apTxPills')?.querySelectorAll('[data-tf]').forEach(btn => btn.addEventListener('click', () => {
          txFilter = btn.dataset.tf;
          Admin.$('apTxPills').querySelectorAll('[data-tf]').forEach(b => b.classList.toggle('on', b === btn));
          renderTxTable();
        }));
        switchPayTab(activePayTab);
        renderTxTable();
      }
    };
  });

  window.adminBankForm = (id = '') => {
    const row = bankAccounts.find(r => r.id === id);
    let qrImageHash = row?.qrImageHash || null;
    Admin.formDialog({
      title: row ? 'Sửa tài khoản nhận học phí' : 'Thêm tài khoản nhận học phí',
      fields: [
        { name:'bankName', label:'Tên ngân hàng', required:true, value:row?.bankName, maxLength:50 },
        { name:'bankBin', label:'Mã BIN ngân hàng', help:'6 chữ số theo chuẩn VietQR.', required:true, value:row?.bankBin, maxLength:6 },
        { name:'accountNo', label:'Số tài khoản', required:true, value:row?.accountNo, maxLength:19 },
        { name:'accountName', label:'Tên chủ tài khoản', required:true, value:row?.accountName, maxLength:70 },
        { name:'active', label:'Sử dụng', type:'select', value:row?.active === false ? '0' : '1', options:[{ value:'1', label:'Đang sử dụng' }, { value:'0', label:'Ngừng sử dụng' }] },
        { name:'isDemo', label:'Loại tài khoản', type:'select', value:row?.isDemo === false ? '0' : '1', options:[{ value:'1', label:'Minh họa demo · Không tạo mã chuyển tiền' }, { value:'0', label:'Tài khoản ngân hàng thật' }] },
      ],
      onSubmit: async body => {
        delete body.qrUpload;
        body.active = body.active === '1';
        body.isDemo = body.isDemo === '1';
        if (qrImageHash) body.qrImageHash = qrImageHash;
        await Admin.api('/api/finance/tuition/bank-accounts' + (id ? '/' + id : ''), id ? 'PUT' : 'POST', body);
        Admin.render();
      }
    });
    const form = Admin.$('dialog-form');
    form.querySelector('.dialog-body').insertAdjacentHTML('afterbegin', '<label>Ảnh QR từ ứng dụng ngân hàng<input type="file" name="qrUpload" accept="image/png,image/jpeg"><small>PNG/JPG dưới 750 KB. Hệ thống tự đọc và đối chiếu số tài khoản.</small></label><div id="bankQrPreview" aria-live="polite"></div>');
    const showImage = url => { Admin.$('bankQrPreview').innerHTML = url ? `<img src="${esc(url)}" alt="QR ngân hàng đã tải" style="display:block;max-width:220px;max-height:240px;margin:auto">` : ''; };
    if (row?.qrImageHash) Admin.api(`/api/finance/tuition/bank-accounts/${id}/qr-image`).then(r => { if (form.isConnected) showImage(r.imageUrl); }).catch(error => { if (form.isConnected) Admin.$('dialog-error').textContent = error.message; });
    form.elements.qrUpload.addEventListener('change', async event => {
      const file = event.target.files[0]; if (!file) return;
      Admin.$('dialog-submit').disabled = true;
      Admin.$('dialog-error').textContent = 'Đang kiểm tra ảnh QR…';
      try {
        if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 750000) throw new Error('Chọn ảnh PNG/JPG dưới 750 KB.');
        const payload = new FormData(); payload.append('qrImage', file);
        const result = await Admin.api('/api/finance/tuition/qr-images', 'POST', payload);
        if (!form.isConnected) return;
        qrImageHash = result.imageHash; form.elements.bankBin.value = result.bankBin; form.elements.accountNo.value = result.accountNo;
        showImage(result.imageUrl); Admin.$('dialog-error').textContent = ''; Admin.$('dialog-submit').disabled = false;
      } catch (error) { if (form.isConnected) Admin.$('dialog-error').textContent = error.message; }
    });
  };
  window.adminQrPreview = async id => { try { const r = await Admin.api(`/api/finance/tuition/bank-accounts/${id}/qr-image`); Admin.dialog('Ảnh QR tài khoản nhận học phí', `<div class="dialog-body">${r.imageUrl ? `<img alt="QR ngân hàng" src="${esc(r.imageUrl)}" style="max-width:100%;max-height:440px;display:block;margin:auto">` : 'Ảnh chưa có trên máy chủ.'}</div>`); } catch (error) { toast(error.message, 'err'); } };
  window.adminConnectPayos = async () => { try { await Admin.api('/api/finance/tuition/payos/connect', 'POST', {}); toast('payOS đã xác minh webhook.', 'ok'); Admin.render(); } catch (error) { toast(error.message, 'err'); } };
  window.adminCancelPayos = id => confirmation('Hủy yêu cầu payOS chưa nhận tiền', 'Chỉ hủy khi payOS xác minh chưa nhận tiền.', async () => { await Admin.api(`/api/finance/tuition/transactions/${id}/cancel-payos`, 'POST', {}); toast('Đã hủy yêu cầu chưa nhận tiền.', 'ok'); Admin.render(); });
  window.adminCheckPayos = async id => { try { const r = await Admin.api(`/api/finance/tuition/transactions/${id}/check-payos`, 'POST', {}); toast(r.paid ? 'Đã xác minh khoản thu với payOS.' : (r.review || 'payOS chưa nhận đủ khoản thanh toán.'), r.review ? 'err' : 'ok'); Admin.render(); } catch (error) { toast(error.message, 'err'); } };
  window.adminBankDefault = id => { const row = bankAccounts.find(r => r.id === id); confirmation('Chọn tài khoản mặc định', `${row.bankName} · ${row.accountNo} · ${row.accountName}`, async () => { await Admin.api(`/api/finance/tuition/bank-accounts/${id}/default`, 'POST', {}); Admin.render(); }); };
  window.adminConfirmOnlineTx = id => {
    const row = transactions.find(item => item.id === Number(id));
    confirmation('Xác nhận giao dịch trực tuyến', `GD-${id} · ${row.maHd} · ${money(row.soTien)}. Chỉ xác nhận sau khi đã đối chiếu tiền vào ngân hàng.`, async () => {
      await Admin.api(`/api/finance/tuition/transactions/${id}/confirm`, 'POST', {});
      toast('Đã lập phiếu thu, cập nhật công nợ và gửi thông báo.', 'ok');
      Admin.render();
    });
  };
  Admin.actions({
    'create-course-invoice': () => window.createCourseInvoicePrompt?.(),
    'publish-selected-invoice': () => window.publishSelectedInvoice?.(),
    'print-selected-invoice': () => window.printSelectedInvoice?.(),
    'do-payment': () => window.doPayment?.(),
    'admin-bank-form': el => window.adminBankForm(el.dataset.id || ''),
    'admin-qr-preview': el => window.adminQrPreview(el.dataset.id),
    'admin-connect-payos': () => window.adminConnectPayos(),
    'admin-cancel-payos': el => window.adminCancelPayos(Number(el.dataset.id)),
    'admin-check-payos': el => window.adminCheckPayos(Number(el.dataset.id)),
    'admin-bank-default': el => window.adminBankDefault(el.dataset.id),
    'admin-confirm-tx': el => window.adminConfirmOnlineTx(Number(el.dataset.id)),
  });
})();
