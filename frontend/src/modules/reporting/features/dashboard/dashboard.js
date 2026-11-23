'use strict';
(() => {
  const Admin = window.Admin;

  // ── 1. Admin Dashboard (existing) ──────────────────────────────────────────
  Admin.register('admin-dash', async () => {
    const data = await Admin.api('/api/reporting/dashboard');
    return {
      title: 'Tổng quan Quản trị cấp cao',
      html: Admin.view('admin-dash'),
      bind() {
        const figures = document.querySelectorAll('#admin-dash .stats .n');
        [data.counts.students, `${data.counts.courses} Khóa`, data.counts.classes, Admin.money(data.received)].forEach((value, index) => {
          figures[index].textContent = value;
        });
        document.querySelector('#admin-dash .stats .st:last-child span').textContent = 'Doanh thu trọn khóa T' + Number(data.month.slice(-2));
        const max = Math.max(1, ...data.revenue.map(row => Math.abs(row.amount)));
        Admin.$('adminRevenueBars').innerHTML = data.revenue.map(row => `<i style="height:${Math.max(1, Math.round(Math.abs(row.amount) / max * 100))}%;${row.amount < 0 ? 'background:var(--err)' : ''}" data-m="T${Number(row.month.slice(-2))}" data-v="${Admin.esc(Admin.money(row.amount))}"></i>`).join('');
        const pending = [
          [data.pending.payments, 'Giao dịch chờ Admin đối soát và lập phiếu thu', 'Đối soát tiền', 'admin-payments', 'p'],
          [data.pending.enrollments, 'Đăng ký khóa chờ Admin xếp lớp', 'Xếp lớp ngay', 'admin-courses', ''],
          [data.pending.corrections + data.pending.classLeave, 'Điều chỉnh giờ công và nghỉ nguyên buổi cả lớp chờ Admin duyệt', 'Phê duyệt', 'admin-exceptions', 'warn']
        ];
        Admin.$('adminPendingWork').innerHTML = pending.map(([count, label, button, screen, style]) => `<div class="item"><span><b>${count}</b> ${Admin.esc(label)}</span><button class="btn sm ${style}" onclick="showS('${screen}')">${button}</button></div>`).join('') + `<div class="item"><span>Mở khóa kỳ công đã chốt chưa chi trả</span><button class="btn sm purple" onclick="showS('admin-exceptions')">Xem xét</button></div><div class="item"><span>Xem báo cáo chuyên cần, doanh thu, hiệu suất lớp &amp; xuất Excel/CSV</span><button class="btn sm" onclick="showS('admin-reports')">Mở báo cáo</button></div>`;
        Admin.$('adminRecentAudit').innerHTML = data.recent.map(row => `<div class="item"><div><b>${Admin.esc(row.hoTen || 'Hệ thống')}</b><br><span style="font-size:12px;color:var(--g500)">${Admin.esc(row.hanhDong + ' · ' + row.bang + ' · ' + Admin.date(row.createdAt))}</span></div>${Admin.tag(row.hanhDong, 'i')}</div>`).join('') || '<div class="item">Chưa có hoạt động.</div>';
        const totalNotif = Number(data.pending.payments || 0) + Number(data.pending.enrollments || 0) + Number(data.pending.corrections || 0) + Number(data.pending.classLeave || 0);
        if (Admin.$('nCount')) {
          Admin.$('nCount').textContent = String(totalNotif);
          Admin.$('nCount').style.display = totalNotif > 0 ? 'grid' : 'none';
        }
      }
    };
  });

  // ── 2. Detailed Reports & CSV/XLSX Export ──────────────────────────────────
  const todayVN = () => new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' });
  const defaultRange = () => {
    const t = todayVN();
    const [y, m] = t.split('-').map(Number);
    const lastDay = new Date(Date.UTC(y, m, 0)).getUTCDate();
    return { tuNgay: `${y}-${String(m).padStart(2, '0')}-01`, denNgay: `${y}-${String(m).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}` };
  };

  const rptState = {
    tab: 'attendance',
    meta: null,
    filters: { ...defaultRange(), coSoId: '', khoaHocId: '', lopId: '', trangThai: '' },
    loading: false,
    version: 0,
  };

  const STATUS_OPTIONS = {
    attendance: [
      ['', 'Tất cả trạng thái điểm danh'],
      ['co_mat', 'Có mặt'],
      ['di_tre', 'Đi trễ'],
      ['vang_co_phep', 'Vắng có phép'],
      ['vang_khong_phep', 'Vắng không phép'],
      ['chua_diem_danh', 'Chưa điểm danh'],
      ['chua_dien_ra', 'Chưa diễn ra'],
    ],
    revenue: [
      ['', 'Tất cả trạng thái hóa đơn'],
      ['da_thanh_toan', 'Đã thanh toán đủ'],
      ['mot_phan', 'Thanh toán một phần'],
      ['chua_thanh_toan', 'Chưa thanh toán'],
    ],
    'class-performance': [
      ['', 'Tất cả trạng thái lớp'],
      ['dang_hoc', 'Đang học'],
      ['sap_bat_dau', 'Sắp bắt đầu'],
      ['tam_dung', 'Tạm dừng'],
      ['hoan_thanh', 'Hoàn thành'],
      ['huy', 'Đã hủy'],
    ],
  };

  const ATT_TAG = {
    co_mat: ['Có mặt', 'ok'],
    di_tre: ['Đi trễ', 'w'],
    vang_co_phep: ['Vắng có phép', 'i'],
    vang_khong_phep: ['Vắng không phép', 'r'],
    chua_diem_danh: ['Chưa điểm danh', 'w'],
    chua_dien_ra: ['Chưa diễn ra', ''],
  };

  const CLASS_ST_TAG = {
    sap_bat_dau: ['Sắp bắt đầu', 'i'],
    dang_hoc: ['Đang học', 'ok'],
    tam_dung: ['Tạm dừng', 'w'],
    hoan_thanh: ['Hoàn thành', 'ok'],
    huy: ['Đã hủy', 'r'],
  };

  const SES_ST_TAG = {
    du_kien: ['Sắp diễn ra', 'i'],
    dang_day: ['Đang dạy', 'w'],
    da_day: ['Đã dạy', 'ok'],
    huy: ['Đã hủy', 'r'],
    doi: ['Đã đổi lịch', 'w'],
  };

  const PAY_METHODS = { tien_mat: 'Tiền mặt', chuyen_khoan: 'Chuyển khoản', vi_dien_tu: 'Ví điện tử' };
  const fmtDate = ymd => {
    if (!ymd || String(ymd).length < 10) return '—';
    const [y, m, d] = String(ymd).slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  };
  const fmtRate = v => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`);

  function syncStatusDropdown() {
    const form = Admin.$('rptFilterForm');
    if (!form) return;
    const sel = form.elements.trangThai;
    const opts = STATUS_OPTIONS[rptState.tab] || [];
    const valid = opts.some(([val]) => val === rptState.filters.trangThai);
    if (!valid) rptState.filters.trangThai = '';
    sel.innerHTML = opts.map(([val, label]) => `<option value="${Admin.esc(val)}" ${val === rptState.filters.trangThai ? 'selected' : ''}>${Admin.esc(label)}</option>`).join('');
  }

  function syncFilterControls() {
    const form = Admin.$('rptFilterForm');
    if (!form || !rptState.meta) return;
    form.elements.tuNgay.value = rptState.filters.tuNgay;
    form.elements.denNgay.value = rptState.filters.denNgay;
    form.elements.coSoId.innerHTML = '<option value="">Tất cả cơ sở</option>' +
      rptState.meta.campuses.map(c => `<option value="${c.id}" ${String(c.id) === String(rptState.filters.coSoId) ? 'selected' : ''}>${Admin.esc(c.tenCoSo)}</option>`).join('');
    form.elements.khoaHocId.innerHTML = '<option value="">Tất cả khóa học</option>' +
      rptState.meta.courses.map(k => `<option value="${k.id}" ${String(k.id) === String(rptState.filters.khoaHocId) ? 'selected' : ''}>${Admin.esc(k.ma + ' · ' + k.ten)}</option>`).join('');
    form.elements.lopId.innerHTML = '<option value="">Tất cả lớp</option>' +
      rptState.meta.classes.map(l => `<option value="${l.id}" ${String(l.id) === String(rptState.filters.lopId) ? 'selected' : ''}>${Admin.esc(l.maLop + ' · ' + l.tenLop)}</option>`).join('');
    syncStatusDropdown();
  }

  function currentQueryParams(extra = {}) {
    return Admin.query({
      tuNgay: rptState.filters.tuNgay,
      denNgay: rptState.filters.denNgay,
      coSoId: rptState.filters.coSoId,
      khoaHocId: rptState.filters.khoaHocId,
      lopId: rptState.filters.lopId,
      trangThai: rptState.filters.trangThai,
      ...extra,
    });
  }

  function setStatusBanner(html, isError = false) {
    const box = Admin.$('rptStatusBanner');
    if (!box) return;
    if (!html) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }
    box.style.display = 'block';
    box.innerHTML = `<div class="${isError ? 'form-error' : ''}" style="margin:0">${html}</div>`;
  }

  async function loadReport() {
    const version = ++rptState.version;
    rptState.loading = true;
    setStatusBanner('Đang tải số liệu báo cáo trực tiếp từ cơ sở dữ liệu…');
    if (Admin.$('rptSummaryTable')) Admin.$('rptSummaryTable').innerHTML = '<div class="admin-empty">Đang tải…</div>';
    if (Admin.$('rptDetailTable')) Admin.$('rptDetailTable').innerHTML = '<div class="admin-empty">Đang tải…</div>';

    try {
      const data = await Admin.api(`/api/reporting/dashboard/reports/${rptState.tab}?${currentQueryParams()}`);
      if (version !== rptState.version || !Admin.$('rptSummaryTable')) return;
      setStatusBanner('');
      renderReportData(data);
    } catch (error) {
      if (version !== rptState.version || !Admin.$('rptSummaryTable')) return;
      setStatusBanner(`${Admin.esc(error.message)} <button type="button" class="btn sm" id="rptRetryBtn">Thử lại</button>`, true);
      Admin.$('rptRetryBtn')?.addEventListener('click', loadReport);
      Admin.$('rptStats').innerHTML = '';
      Admin.$('rptSummaryTable').innerHTML = '<div class="admin-empty">Không tải được dữ liệu báo cáo.</div>';
      Admin.$('rptDetailTable').innerHTML = '<div class="admin-empty">—</div>';
    } finally {
      if (version === rptState.version) rptState.loading = false;
    }
  }

  function renderReportData(data) {
    const statsEl = Admin.$('rptStats');
    const formulaEl = Admin.$('rptFormulaBox');
    const sumTitle = Admin.$('rptSummaryTitle');
    const sumMeta = Admin.$('rptSummaryMeta');
    const sumTable = Admin.$('rptSummaryTable');
    const detTitle = Admin.$('rptDetailTitle');
    const detMeta = Admin.$('rptDetailMeta');
    const detTable = Admin.$('rptDetailTable');

    formulaEl.innerHTML = `<b style="font-size:12.5px;display:block;margin-bottom:4px">Quy tắc &amp; Công thức tính (số liệu thực tế từ DB):</b><ul style="margin:0;padding-left:18px;font-size:12px;color:var(--g600);line-height:1.55">${(data.congThuc || []).map(line => `<li>${Admin.esc(line)}</li>`).join('')}</ul>`;

    if (data.report === 'attendance') {
      const s = data.summary;
      statsEl.innerHTML = [
        [fmtRate(s.tyLeChuyenCan), 'Tỷ lệ chuyên cần (đã điểm danh)', ''],
        [`${s.coMat} / ${s.diTre}`, 'Có mặt / Đi trễ (lượt)', 'g'],
        [`${s.vangCoPhep} / ${s.vangKhongPhep}`, 'Vắng có phép / Không phép', 'w'],
        [`${s.chuaDiemDanh} / ${s.chuaDienRa}`, `Chưa điểm danh / Chưa diễn ra (Hủy/Đổi: ${s.soBuoiHuyDoi} buổi)`, 'i'],
      ].map(([n, label, cls]) => `<div class="st ${cls}"><div class="n">${Admin.esc(n)}</div><span>${Admin.esc(label)}</span></div>`).join('');

      sumTitle.textContent = '1. Tổng hợp chuyên cần theo lớp học';
      sumMeta.textContent = `${data.byClass.length} lớp · ${s.daDiemDanh}/${s.tongLuot} lượt đã điểm danh`;
      sumTable.innerHTML = Admin.table([
        { label: 'Lớp học', render: r => `<b>${Admin.esc(r.maLop)}</b><br><small>${Admin.esc(r.tenLop)}</small>` },
        { label: 'Khóa · Cơ sở', render: r => `${Admin.esc(r.tenKhoa)}<br><small>${Admin.esc(r.tenCoSo)}</small>` },
        { key: 'soBuoi', label: 'Số buổi' },
        { key: 'daDiemDanh', label: 'Đã điểm danh' },
        { key: 'coMat', label: 'Có mặt' },
        { key: 'diTre', label: 'Đi trễ' },
        { label: 'Vắng (CP/KP)', render: r => `${r.vangCoPhep} / ${r.vangKhongPhep}` },
        { label: 'Chưa ĐD / Chưa diễn ra', render: r => `${r.chuaDiemDanh} / ${r.chuaDienRa}` },
        { label: 'Tỷ lệ chuyên cần', render: r => `<b>${fmtRate(r.tyLeChuyenCan)}</b>` },
      ], data.byClass, 'Không có dữ liệu chuyên cần trong khoảng ngày và bộ lọc đã chọn.');

      detTitle.textContent = '2. Chi tiết từng lượt điểm danh học viên';
      detMeta.textContent = `Hiển thị ${data.details.length} lượt (tối đa ${data.detailLimit} dòng)`;
      detTable.innerHTML = Admin.table([
        { label: 'Ngày · Giờ', render: r => `<b>${fmtDate(r.ngay)}</b><br><small>${Admin.esc(String(r.gioBatDau).slice(0, 5) + '–' + String(r.gioKetThuc).slice(0, 5))}</small>` },
        { label: 'Lớp · Cơ sở', render: r => `<b>${Admin.esc(r.maLop)}</b><br><small>${Admin.esc(r.tenCoSo)}</small>` },
        { label: 'Học viên', render: r => `<b>${Admin.esc(r.hoTen)}</b><br><small>${Admin.esc(r.maHv)}</small>` },
        { label: 'Trạng thái buổi', render: r => { const [t, c] = SES_ST_TAG[r.trangThaiBuoi] || [r.trangThaiBuoi, '']; return Admin.tag(t, c); } },
        { label: 'Điểm danh', render: r => { const [t, c] = ATT_TAG[r.trangThaiDiemDanh] || [r.trangThaiDiemDanh, '']; return Admin.tag(t, c); } },
        { label: 'Điểm BT', render: r => r.diemBaiTap != null ? Admin.esc(r.diemBaiTap) : '—' },
        { key: 'nhanXet', label: 'Nhận xét' },
      ], data.details, 'Chưa có bản ghi điểm danh phù hợp.');
    } else if (data.report === 'revenue') {
      const s = data.summary;
      statsEl.innerHTML = [
        [Admin.money(s.thucThuRong), `Thực thu ròng (${s.soPhieuXacNhan} phiếu hợp lệ)`, 'g'],
        [Admin.money(s.phaiThu), `Tổng phải thu (${s.soHoaDon} hóa đơn phát hành)`, 'i'],
        [Admin.money(s.conNo), `Còn nợ (Tổng hoàn: ${Admin.money(s.tongHoan)})`, 'w'],
        [`${s.giaoDichChoDoiSoat} GD`, `Chờ đối soát: ${Admin.money(s.tienChoDoiSoat)} (chưa tính doanh thu)`, ''],
      ].map(([n, label, cls]) => `<div class="st ${cls}"><div class="n">${Admin.esc(n)}</div><span>${Admin.esc(label)}</span></div>`).join('');

      sumTitle.textContent = '1. Tổng hợp doanh thu theo Khóa học và Cơ sở';
      sumMeta.textContent = `${data.byGroup.length} nhóm khóa học · cơ sở`;
      sumTable.innerHTML = Admin.table([
        { label: 'Khóa học', render: r => `<b>${Admin.esc(r.tenKhoa)}</b><br><small>${Admin.esc(r.maKhoa)}</small>` },
        { key: 'tenCoSo', label: 'Cơ sở' },
        { key: 'soHoaDon', label: 'Số HĐ' },
        { label: 'Phải thu', render: r => Admin.money(r.phaiThu) },
        { label: 'Còn nợ', render: r => Admin.money(r.conNo) },
        { key: 'soPhieu', label: 'Phiếu XN' },
        { label: 'Tổng thu', render: r => Admin.money(r.tongThu) },
        { label: 'Hoàn trả', render: r => Admin.money(r.tongHoan) },
        { label: 'Thực thu ròng', render: r => `<b>${Admin.money(r.thucThuRong)}</b>` },
      ], data.byGroup, 'Không có hóa đơn hoặc phiếu thu xác nhận trong khoảng ngày này.');

      detTitle.textContent = '2. Chi tiết phiếu thu / hoàn đã xác nhận (hợp lệ)';
      detMeta.textContent = `Hiển thị ${data.details.length} chứng từ xác nhận`;
      detTable.innerHTML = Admin.table([
        { label: 'Phiếu · Ngày', render: r => `<b>PT-${r.phieuId}</b><br><small>${fmtDate(r.ngayThanhToan)}</small>` },
        { label: 'Hóa đơn · Học viên', render: r => `<b>${Admin.esc(r.maHd)}</b><br><small>${Admin.esc(r.tenHocVien)}</small>` },
        { label: 'Khóa học · Cơ sở', render: r => `${Admin.esc(r.tenKhoa)}<br><small>${Admin.esc(r.tenCoSo)} · ${Admin.esc(r.maLop)}</small>` },
        { label: 'Loại · Phương thức', render: r => `${Admin.tag(r.loai === 'thu' ? 'Phiếu thu' : 'Phiếu hoàn', r.loai === 'thu' ? 'ok' : 'r')}<br><small>${Admin.esc(PAY_METHODS[r.phuongThuc] || r.phuongThuc)}</small>` },
        { label: 'Số tiền', render: r => `<b>${Admin.money(r.loai === 'thu' ? r.soTien : -r.soTien)}</b>` },
        { key: 'maGiaoDich', label: 'Mã giao dịch' },
        { key: 'nguoiThu', label: 'Người thu' },
      ], data.details, 'Chưa có phiếu thu hợp lệ trong khoảng ngày đã chọn.');
    } else {
      const s = data.summary;
      statsEl.innerHTML = [
        [`${s.soLop} lớp`, `Sĩ số đang học: ${s.tongSiSoDangHoc} / ${s.tongSucChua} (${fmtRate(s.tyLeLapDayTrungBinh)})`, ''],
        [String(s.soBuoiDaDienRa), `Buổi đã diễn ra / ${s.tongBuoiTrongKy} buổi trong kỳ`, 'g'],
        [fmtRate(s.tyLeChuyenCan), 'Tỷ lệ chuyên cần chung', 'i'],
        [`${s.soBuoiBiHuy} / ${s.soBuoiHocBu}`, 'Buổi bị hủy / Buổi học bù', 'w'],
      ].map(([n, label, cls]) => `<div class="st ${cls}"><div class="n">${Admin.esc(n)}</div><span>${Admin.esc(label)}</span></div>`).join('');

      sumTitle.textContent = '1. Hiệu suất vận hành từng lớp học';
      sumMeta.textContent = `${data.classes.length} lớp`;
      sumTable.innerHTML = Admin.table([
        { label: 'Lớp học', render: r => `<b>${Admin.esc(r.maLop)}</b><br><small>${Admin.esc(r.tenLop)} · ${Admin.esc(r.tenMon)} ${Admin.esc(r.tenCapLop)}</small>` },
        { label: 'Khóa · Cơ sở', render: r => `${Admin.esc(r.tenKhoa)}<br><small>${Admin.esc(r.tenCoSo)}</small>` },
        { key: 'nguoiDayChinh', label: 'Dạy chính' },
        { label: 'Trạng thái', render: r => { const [t, c] = CLASS_ST_TAG[r.trangThai] || [r.trangThai, '']; return Admin.tag(t, c); } },
        { label: 'Sĩ số / Tối đa', render: r => `<b>${r.siSoDangHoc}/${r.siSoToiDa}</b><br><small>${fmtRate(r.tyLeLapDay)}</small>` },
        { label: 'Buổi (Đã dạy / Tổng)', render: r => `${r.soBuoiDaDienRa} / ${r.tongBuoiTrongKy}<br><small>Hủy: ${r.soBuoiBiHuy} · Đổi: ${r.soBuoiDoiLich} · Bù: ${r.soBuoiHocBu}</small>` },
        { key: 'soBuoiCoSoHocVu', label: 'Buổi có sổ' },
        { label: 'Chuyên cần', render: r => `<b>${fmtRate(r.tyLeChuyenCan)}</b><br><small>${r.luotCoMatHoacTre}/${r.luotDaDiemDanh} lượt</small>` },
      ], data.classes, 'Không có lớp học phù hợp bộ lọc.');

      detTitle.textContent = '2. Danh sách buổi học chi tiết trong kỳ';
      detMeta.textContent = `Hiển thị ${data.details.length} buổi học`;
      detTable.innerHTML = Admin.table([
        { label: 'Ngày · Giờ', render: r => `<b>${fmtDate(r.ngay)}</b><br><small>${Admin.esc(String(r.gioBatDau).slice(0, 5) + '–' + String(r.gioKetThuc).slice(0, 5))}</small>` },
        { label: 'Lớp · Cơ sở', render: r => `<b>${Admin.esc(r.maLop)}</b> · ${Admin.esc(r.tenLop)}<br><small>${Admin.esc(r.tenCoSo)}</small>` },
        { label: 'Loại buổi', render: r => r.loaiBuoi === 'bu' ? Admin.tag('Học bù', 'purple') : 'Buổi thường' },
        { label: 'Trạng thái', render: r => { const [t, c] = SES_ST_TAG[r.trangThaiBuoi] || [r.trangThaiBuoi, '']; return Admin.tag(t, c); } },
        { label: 'Chuyên cần buổi', render: r => `${r.soCoMatHoacTre} / ${r.soLuotDiemDanh} lượt` },
      ], data.details, 'Chưa có buổi học nào trong khoảng ngày này.');
    }
  }

  async function downloadReport(format, btn) {
    await Admin.busy(btn, async () => {
      const url = `/api/reporting/dashboard/reports/${rptState.tab}/export?${currentQueryParams({ format })}`;
      const res = await fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) {
        const err = await res.json().catch(() => null);
        throw new Error(err?.message || 'Không xuất được tệp báo cáo.');
      }
      const blob = await res.blob();
      const cd = res.headers.get('Content-Disposition') || '';
      const match = cd.match(/filename="?([^"]+)"?/i);
      const filename = match ? match[1] : `bao-cao-${rptState.tab}-${rptState.filters.tuNgay}.${format}`;
      const objectUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = objectUrl;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
      Admin.notify(`Đã tải xuống ${filename}`);
    });
  }

  async function loadEmailQueueHealth() {
    const card = Admin.$('rptEmailHealthCard');
    if (!card || Admin.user?.role !== 'ADMIN') return;
    try {
      const data = await Admin.api('/api/communication/notifications/email-stats');
      if (!card.isConnected) return;
      const s = data.summary;
      card.style.display = 'block';
      card.innerHTML = `<div class="row-b" style="margin-bottom:8px"><h2 style="margin:0">Trạng thái Hàng đợi Email Thông báo (Worker CLI)</h2><span class="tag i">php spark educenter:email-worker</span></div>` +
        `<p style="font-size:12.5px;color:var(--g600);margin:0 0 10px">Tổng xếp hàng: <b>${s.totalQueued}</b> · Chờ gửi: <b>${s.pending}</b> · Đã gửi: <b>${s.sent}</b> · Đang retry: <b>${s.retrying}</b> · Lỗi dừng: <b>${s.failedPermanent}</b></p>` +
        (data.recentFailures.length ? Admin.table([
          { label: 'ID · Loại', render: r => `#${r.id} · ${Admin.esc(r.loai)}` },
          { key: 'tieuDe', label: 'Tiêu đề' },
          { key: 'soLanThuEmail', label: 'Số lần thử' },
          { label: 'Lần gần nhất / Thử tiếp', render: r => `${Admin.esc(Admin.date(r.thuGanNhatLuc))}<br><small>${r.thuTiepLuc ? Admin.esc(Admin.date(r.thuTiepLuc)) : 'Đã dừng retry'}</small>` },
          { key: 'loiEmail', label: 'Lỗi đã làm sạch' },
        ], data.recentFailures) : '<p style="font-size:12px;color:var(--g500);margin:0">Chưa có lỗi gửi email nào trong hàng đợi.</p>');
    } catch {
      card.style.display = 'none';
    }
  }

  Admin.register('admin-reports', async () => {
    rptState.meta = await Admin.api('/api/reporting/dashboard/reports/meta');
    return {
      title: 'Báo cáo Thống kê & Xuất dữ liệu',
      html: Admin.view('admin-reports'),
      bind() {
        syncFilterControls();
        document.querySelectorAll('#rptTabs [data-rpt-tab]').forEach(tabBtn => {
          tabBtn.classList.toggle('on', tabBtn.dataset.rptTab === rptState.tab);
          tabBtn.addEventListener('click', () => {
            rptState.tab = tabBtn.dataset.rptTab;
            document.querySelectorAll('#rptTabs [data-rpt-tab]').forEach(b => b.classList.toggle('on', b.dataset.rptTab === rptState.tab));
            syncStatusDropdown();
            loadReport();
          });
        });

        const form = Admin.$('rptFilterForm');
        form.addEventListener('submit', event => {
          event.preventDefault();
          const fd = new FormData(form);
          rptState.filters = {
            tuNgay: String(fd.get('tuNgay') || ''),
            denNgay: String(fd.get('denNgay') || ''),
            coSoId: String(fd.get('coSoId') || ''),
            khoaHocId: String(fd.get('khoaHocId') || ''),
            lopId: String(fd.get('lopId') || ''),
            trangThai: String(fd.get('trangThai') || ''),
          };
          loadReport();
        });

        Admin.$('rptResetBtn').addEventListener('click', () => {
          rptState.filters = { ...defaultRange(), coSoId: '', khoaHocId: '', lopId: '', trangThai: '' };
          syncFilterControls();
          loadReport();
        });

        Admin.$('rptExportCsv').addEventListener('click', e => downloadReport('csv', e.currentTarget));
        Admin.$('rptExportXlsx').addEventListener('click', e => downloadReport('xlsx', e.currentTarget));

        loadReport();
        loadEmailQueueHealth();
      }
    };
  });
})();
