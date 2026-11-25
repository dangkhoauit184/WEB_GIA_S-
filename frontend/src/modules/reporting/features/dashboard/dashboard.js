'use strict';
(() => {
  const Admin = window.Admin;

  const todayVN = () => new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' });
  const currentMonthVN = () => todayVN().slice(0, 7);

  const fmtDate = ymd => {
    if (!ymd || String(ymd).length < 10) return '—';
    const [y, m, d] = String(ymd).slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  };
  const fmtRate = v => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`);
  const fmtHours = mins => `${Number((Number(mins || 0) / 60).toFixed(2))} giờ`;
  const fmtNum = v => new Intl.NumberFormat('vi-VN').format(Number(v || 0));
  const fmtCompactMoney = v => {
    const n = Number(v || 0);
    const abs = Math.abs(n);
    const sign = n < 0 ? '-' : '';
    if (abs >= 1e9) return `${sign}${(abs / 1e9).toFixed(1)}tỷ`;
    if (abs >= 1e6) return `${sign}${(abs / 1e6).toFixed(1)}tr`;
    if (abs >= 1e3) return `${sign}${Math.round(abs / 1e3)}k`;
    return `${n}đ`;
  };
  const timeRange = r => `${String(r.gioBatDau || '').slice(0, 5)}–${String(r.gioKetThuc || '').slice(0, 5)}`;

  // ── 1. Admin Dashboard (6-month revenue, MoM delta, debt, Admin-only payroll, month picker) ──
  const dashState = { month: currentMonthVN(), version: 0, email: null };

  function appendEmailAlert() {
    const box = Admin.$('adminPendingWork');
    const s = dashState.email;
    if (!box || !s) return;
    const issues = Number(s.failedPermanent || 0) + Number(s.retrying || 0);
    if (issues <= 0) return;
    box.insertAdjacentHTML('beforeend', `<div class="item"><span><b>${issues}</b> Email thông báo lỗi / đang thử lại (Worker CLI)</span><span class="tag r">Kiểm tra SMTP</span></div>`);
  }

  function renderDashboardPayload(data) {
    const statsEl = Admin.$('adminDashStats');
    if (!statsEl) return;
    const [yy, mm] = String(data.month || dashState.month).split('-');
    const mLabel = `T${Number(mm)}/${yy}`;

    let revSub = `Doanh thu trọn khóa ${mLabel}`;
    if (data.revenueChangePct !== null && data.revenueChangePct !== undefined) {
      const sign = data.revenueChangePct > 0 ? '+' : '';
      revSub += ` (${sign}${data.revenueChangePct}% so với T${Number(String(data.previousMonth || '').slice(-2))})`;
    } else if (Number(data.previousReceived || 0) === 0) {
      revSub += ' (Tháng trước: 0 ₫)';
    }

    const debtSub = Number(data.counts?.overdueInvoices || 0) > 0
      ? `Tổng công nợ học phí (${data.counts.overdueInvoices} HĐ quá hạn)`
      : 'Tổng công nợ học phí toàn hệ thống';

    const cards = [
      [String(data.counts.students), 'Học viên đang theo học', ''],
      [`${data.counts.courses} Khóa`, 'Khóa học đang mở', 'g'],
      [String(data.counts.classes), 'Lớp đang vận hành', 'i'],
      [Admin.money(data.received), revSub, 'w'],
      [Admin.money(data.counts.debt || 0), debtSub, Number(data.counts.debt || 0) > 0 ? 'r' : 'g'],
    ];
    if (Admin.user?.role === 'ADMIN' && data.payroll) {
      const p = data.payroll;
      cards.push([Admin.money(p.total || 0), `Quỹ lương ${mLabel} (${p.sessions} ca · ${fmtHours(p.minutes)} · Đã trả ${Admin.money(p.paid)})`, 'p']);
    }
    statsEl.innerHTML = cards
      .map(([n, label, cls]) => `<div class="st ${cls}"><div class="n">${Admin.esc(n)}</div><span>${Admin.esc(label)}</span></div>`)
      .join('');

    const titleEl = Admin.$('adminRevenueChartTitle');
    if (titleEl) titleEl.textContent = `Doanh thu thực thu ${data.revenue.length} tháng đến ${mLabel}`;

    const deltaEl = Admin.$('adminRevenueDelta');
    if (deltaEl) {
      if (data.revenueChangePct !== null && data.revenueChangePct !== undefined) {
        const up = data.revenueChangePct >= 0;
        deltaEl.className = `dash-delta ${data.revenueChangePct === 0 ? 'flat' : (up ? 'up' : 'down')}`;
        deltaEl.textContent = `${up && data.revenueChangePct > 0 ? '▲ +' : (data.revenueChangePct < 0 ? '▼ ' : '')}${data.revenueChangePct}% so với tháng trước (${Admin.money(data.previousReceived)})`;
      } else {
        deltaEl.className = 'dash-delta flat';
        deltaEl.textContent = `Tháng trước: ${Admin.money(data.previousReceived || 0)}`;
      }
    }

    const max = Math.max(1, ...data.revenue.map(row => Math.abs(row.amount)));
    Admin.$('adminRevenueBars').innerHTML = data.revenue.map(row => {
      const pct = row.amount === 0 ? 2 : Math.max(4, Math.round(Math.abs(row.amount) / max * 100));
      const [ry, rm] = String(row.month).split('-');
      const negStyle = row.amount < 0 ? 'background:var(--err);' : (row.amount === 0 ? 'background:var(--g200);' : '');
      return `<i style="height:${pct}%;${negStyle}" title="${Admin.esc(`Tháng ${Number(rm)}/${ry}: ${Admin.money(row.amount)}`)}" data-m="T${Number(rm)}/${ry.slice(-2)}" data-v="${Admin.esc(fmtCompactMoney(row.amount))}"></i>`;
    }).join('');

    const pending = [
      [data.pending.payments, 'Giao dịch chờ Admin đối soát và lập phiếu thu', 'Đối soát tiền', 'admin-payments', 'p'],
      [data.pending.enrollments, 'Đăng ký khóa chờ Admin xếp lớp', 'Xếp lớp ngay', 'admin-courses', ''],
      [data.pending.corrections + data.pending.classLeave, 'Điều chỉnh giờ công và nghỉ nguyên buổi cả lớp chờ Admin duyệt', 'Phê duyệt', 'admin-exceptions', 'warn']
    ];
    Admin.$('adminPendingWork').innerHTML = pending
      .map(([count, label, button, screen, style]) => `<div class="item"><span><b>${count}</b> ${Admin.esc(label)}</span><button type="button" class="btn sm ${style}" data-nav="${Admin.esc(screen)}">${button}</button></div>`)
      .join('')
      + `<div class="item"><span>Mở khóa kỳ công đã chốt chưa chi trả</span><button type="button" class="btn sm purple" data-nav="admin-exceptions">Xem xét</button></div>`
      + `<div class="item"><span>Báo cáo chuyên cần, doanh thu, công/lương, công nợ &amp; xuất Excel/CSV</span><button type="button" class="btn sm" data-nav="admin-reports">Mở báo cáo</button></div>`;
    appendEmailAlert();

    Admin.$('adminRecentAudit').innerHTML = data.recent
      .map(row => `<div class="item"><div><b>${Admin.esc(row.hoTen || 'Hệ thống')}</b><br><span style="font-size:12px;color:var(--g500)">${Admin.esc(row.hanhDong + ' · ' + row.bang + ' · ' + Admin.date(row.createdAt))}</span></div>${Admin.tag(row.hanhDong, 'i')}</div>`)
      .join('') || '<div class="item">Chưa có hoạt động.</div>';

    const totalNotif = Number(data.pending.payments || 0) + Number(data.pending.enrollments || 0) + Number(data.pending.corrections || 0) + Number(data.pending.classLeave || 0);
    if (Admin.$('nCount')) {
      Admin.$('nCount').textContent = String(totalNotif);
      Admin.$('nCount').style.display = totalNotif > 0 ? 'grid' : 'none';
    }
  }

  async function reloadDashboardMonth(month) {
    const version = ++dashState.version;
    dashState.month = month;
    try {
      const data = await Admin.api(`/api/reporting/dashboard?${Admin.query({ month })}`);
      if (version !== dashState.version || !Admin.$('adminDashStats')) return;
      renderDashboardPayload(data);
    } catch (error) {
      if (version === dashState.version) Admin.notify(error.message, true);
    }
  }

  if (document.body.dataset.portal !== 'admissions') {
    Admin.register('admin-dash', async () => {
      const data = await Admin.api(`/api/reporting/dashboard?${Admin.query({ month: dashState.month })}`);
      return {
        title: 'Tổng quan Quản trị cấp cao',
        html: Admin.view('admin-dash'),
        bind() {
          const monthInput = Admin.$('adminDashMonth');
          if (monthInput) {
            monthInput.value = data.month || dashState.month;
            monthInput.addEventListener('change', () => {
              if (/^\d{4}-\d{2}$/.test(monthInput.value)) reloadDashboardMonth(monthInput.value);
            });
          }
          renderDashboardPayload(data);
          if (Admin.user?.role === 'ADMIN') {
            Admin.api('/api/communication/notifications/email-stats')
              .then(res => { dashState.email = res.summary; appendEmailAlert(); })
              .catch(() => { dashState.email = null; });
          }
        }
      };
    });
  }

  // ── 2. Reports (Admin + Giáo vụ): compact layout — 1 toolbar, 4 KPI, 1 overview, 1 key table, collapsible detail ──
  function dateRangePreset(key) {
    const t = todayVN();
    const [y, m, d] = t.split('-').map(Number);
    const pad = n => String(n).padStart(2, '0');
    const ymd = (yy, mm, dd) => `${yy}-${pad(mm)}-${pad(dd)}`;
    const lastDayOf = (yy, mm) => new Date(Date.UTC(yy, mm, 0)).getUTCDate();
    if (key === 'week') {
      const cur = new Date(Date.UTC(y, m - 1, d));
      const dow = cur.getUTCDay();
      const mon = new Date(cur.getTime() + (dow === 0 ? -6 : 1 - dow) * 86400000);
      const sun = new Date(mon.getTime() + 6 * 86400000);
      return { tuNgay: ymd(mon.getUTCFullYear(), mon.getUTCMonth() + 1, mon.getUTCDate()), denNgay: ymd(sun.getUTCFullYear(), sun.getUTCMonth() + 1, sun.getUTCDate()) };
    }
    if (key === 'prev-month') {
      const pm = m === 1 ? 12 : m - 1;
      const py = m === 1 ? y - 1 : y;
      return { tuNgay: ymd(py, pm, 1), denNgay: ymd(py, pm, lastDayOf(py, pm)) };
    }
    if (key === 'quarter') {
      const qs = Math.floor((m - 1) / 3) * 3 + 1;
      return { tuNgay: ymd(y, qs, 1), denNgay: ymd(y, qs + 2, lastDayOf(y, qs + 2)) };
    }
    if (key === 'ytd') return { tuNgay: ymd(y, 1, 1), denNgay: t };
    return { tuNgay: ymd(y, m, 1), denNgay: ymd(y, m, lastDayOf(y, m)) };
  }

  const PRESETS = [['week', 'Tuần này'], ['this-month', 'Tháng này'], ['prev-month', 'Tháng trước'], ['quarter', 'Quý này'], ['ytd', 'Từ đầu năm']];

  const TAB_META = {
    attendance: { label: 'Chuyên cần', title: 'Báo cáo Chuyên cần học viên' },
    revenue: { label: 'Doanh thu', title: 'Báo cáo Doanh thu theo khóa học & cơ sở' },
    'class-performance': { label: 'Lớp học', title: 'Báo cáo Vận hành lớp học' },
    payroll: { label: 'Công & Lương', title: 'Báo cáo Công dạy & Quỹ lương' },
    debt: { label: 'Công nợ', title: 'Báo cáo Công nợ học phí tồn' },
    admissions: { label: 'Tuyển sinh', title: 'Thống kê Yêu cầu học & Tuyển sinh' },
  };

  const STATUS_OPTIONS = {
    attendance: [['', 'Mọi trạng thái'], ['co_mat', 'Có mặt'], ['di_tre', 'Đi trễ'], ['vang_co_phep', 'Vắng có phép'], ['vang_khong_phep', 'Vắng không phép'], ['chua_diem_danh', 'Chưa điểm danh'], ['chua_dien_ra', 'Chưa diễn ra']],
    revenue: [['', 'Mọi trạng thái HĐ'], ['da_thanh_toan', 'Đã thanh toán đủ'], ['mot_phan', 'Thanh toán một phần'], ['chua_thanh_toan', 'Chưa thanh toán']],
    'class-performance': [['', 'Mọi trạng thái lớp'], ['dang_hoc', 'Đang học'], ['sap_bat_dau', 'Sắp bắt đầu'], ['tam_dung', 'Tạm dừng'], ['hoan_thanh', 'Hoàn thành'], ['huy', 'Đã hủy']],
    payroll: [['', 'Mọi trạng thái kỳ công'], ['mo', 'Mở (chưa chốt)'], ['da_chot', 'Đã chốt'], ['da_tra', 'Đã trả']],
    debt: [['', 'Mọi hóa đơn còn nợ'], ['qua_han', 'Quá hạn'], ['chua_den_han', 'Chưa đến hạn'], ['khong_co_han', 'Chưa đặt hạn'], ['chua_thanh_toan', 'Chưa thanh toán'], ['mot_phan', 'Thanh toán một phần']],
    admissions: [['', 'Mọi trạng thái'], ['moi', 'Mới tiếp nhận'], ['dang_tim', 'Đang tìm người dạy'], ['da_de_xuat', 'Đã đề xuất'], ['da_chot', 'Đã chốt'], ['huy', 'Đã hủy']],
  };

  const ATT_TAG = { co_mat: ['Có mặt', 'ok'], di_tre: ['Đi trễ', 'w'], vang_co_phep: ['Vắng có phép', 'i'], vang_khong_phep: ['Vắng không phép', 'r'], chua_diem_danh: ['Chưa điểm danh', 'w'], chua_dien_ra: ['Chưa diễn ra', ''] };
  const SES_ST_TAG = { du_kien: ['Sắp diễn ra', 'i'], dang_day: ['Đang dạy', 'w'], da_day: ['Đã dạy', 'ok'], huy: ['Đã hủy', 'r'], doi: ['Đã đổi lịch', 'w'] };
  const PAY_METHODS = { tien_mat: 'Tiền mặt', chuyen_khoan: 'Chuyển khoản', vi_dien_tu: 'Ví điện tử', the: 'Thẻ' };
  const ROLE_GROUP_TAG = { giao_vien: ['Giáo viên', 'i'], gia_su: ['Gia sư', 'purple'], tro_giang: ['Trợ giảng', 'ok'] };
  const PAYROLL_ST_TAG = { mo: ['Mở', 'w'], da_chot: ['Đã chốt', 'i'], da_tra: ['Đã trả', 'ok'], hon_hop: ['Nhiều trạng thái', ''] };
  const DEBT_AGING_TAG = { qua_han: ['Quá hạn', 'r'], chua_den_han: ['Chưa đến hạn', 'ok'], khong_co_han: ['Chưa đặt hạn', ''] };
  const ADM_ST_TAG = { moi: ['Mới', 'i'], dang_tim: ['Đang tìm', 'w'], da_de_xuat: ['Đã đề xuất', 'purple'], da_chot: ['Đã chốt', 'ok'], huy: ['Đã hủy', 'r'] };
  const ADM_SOURCE_LABEL = { website: 'Website', dien_thoai: 'Điện thoại', truc_tiep: 'Trực tiếp', gioi_thieu: 'Giới thiệu' };
  const tagOf = (map, key) => { const [t, c] = map[key] || [key || '—', '']; return Admin.tag(t, c); };

  const rptState = {
    tab: 'attendance',
    preset: 'this-month',
    meta: null,
    lastData: null,
    lastView: null,
    filters: { ...dateRangePreset('this-month'), coSoId: '', khoaHocId: '', lopId: '', monHocId: '', nhomNhanSu: '', trangThai: '' },
    detail: { query: '', sortKey: '', sortDir: 'desc', page: 1, pageSize: 25, columns: [], rows: [], empty: '' },
    version: 0,
    loadTimer: null,
  };

  // ── Small render helpers ──
  function kpiCard(k) {
    return `<div class="rpt-kpi ${k.primary ? 'primary' : ''} ${k.tone ? 'tone-' + k.tone : ''}">
      <span class="rpt-kpi-label">${Admin.esc(k.l)}</span>
      <b class="rpt-kpi-value">${Admin.esc(k.v)}</b>
      ${k.sub ? `<span class="rpt-kpi-sub">${Admin.esc(k.sub)}</span>` : ''}
    </div>`;
  }

  function stackBar(segs, fmt = v => fmtNum(v)) {
    const total = segs.reduce((acc, s) => acc + Math.max(0, Number(s.value || 0)), 0);
    if (total <= 0) return '<div class="rpt-empty">Chưa có dữ liệu trong khoảng lọc.</div>';
    const bar = segs.filter(s => Number(s.value || 0) > 0).map(s => {
      const pct = Number(s.value) / total * 100;
      return `<i style="width:${pct.toFixed(2)}%;background:${s.color}" title="${Admin.esc(`${s.label}: ${fmt(s.value)} (${pct.toFixed(1)}%)`)}"></i>`;
    }).join('');
    const legend = segs.map(s => {
      const pct = (Number(s.value || 0) / total * 100).toFixed(1);
      return `<span class="rpt-leg"><i style="background:${s.color}"></i>${Admin.esc(s.label)} <b>${Admin.esc(fmt(s.value))}</b><small>${pct}%</small></span>`;
    }).join('');
    return `<div class="rpt-stack" role="img" aria-label="Biểu đồ phân bổ">${bar}</div><div class="rpt-legend">${legend}</div>`;
  }

  function rateBar(v, { text = null, color = null } = {}) {
    if (v === null || v === undefined) return '<span class="rpt-muted">—</span>';
    const c = color || (v >= 85 ? 'var(--ok)' : v >= 70 ? 'var(--warn)' : 'var(--err)');
    return `<div class="rpt-rate"><span class="rpt-rate-track"><i style="width:${Math.min(100, Math.max(0, v))}%;background:${c}"></i></span><b>${Admin.esc(text ?? fmtRate(v))}</b></div>`;
  }

  function twoLine(main, sub) {
    return `<b>${Admin.esc(main)}</b>${sub ? `<small class="rpt-sub">${Admin.esc(sub)}</small>` : ''}`;
  }

  function simpleTable(columns, rows, empty) {
    const head = columns.map(c => `<th class="${c.num ? 'num' : ''}">${Admin.esc(c.label)}</th>`).join('');
    const body = rows.length
      ? rows.map(r => `<tr>${columns.map(c => `<td class="${c.num ? 'num' : ''}">${c.render ? c.render(r) : Admin.esc(r[c.key] ?? '—')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${columns.length}" class="admin-empty">${Admin.esc(empty)}</td></tr>`;
    return `<div class="tw rpt-table"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function renderTrendSvgChart(series, granularity) {
    if (!series.length || series.every(p => Number(p.thucThuRong || 0) === 0)) {
      return '<div class="rpt-empty">Chưa có phiếu thu hợp lệ trong khoảng lọc.</div>';
    }
    const maxVal = Math.max(1, ...series.map(p => Math.abs(Number(p.thucThuRong || 0))));
    const barW = Math.max(10, Math.min(34, Math.floor(620 / Math.max(1, series.length)) - 6));
    const gap = Math.max(3, Math.min(10, Math.floor(barW * 0.3)));
    const padL = 46, padR = 8, padT = 10, padB = 24, chartH = 120;
    const totalW = Math.max(360, padL + padR + series.length * (barW + gap));
    const totalH = padT + chartH + padB;
    const labelStep = series.length > 16 ? Math.ceil(series.length / 8) : 1;
    const grid = [0, 0.5, 1].map(f => {
      const y = padT + chartH * (1 - f);
      return `<line x1="${padL}" y1="${y.toFixed(1)}" x2="${totalW - padR}" y2="${y.toFixed(1)}" stroke="#e2e8f0" stroke-dasharray="3 3"/><text x="${padL - 6}" y="${(y + 3).toFixed(1)}" text-anchor="end" font-size="9.5" fill="#94a3b8">${Admin.esc(fmtCompactMoney(maxVal * f))}</text>`;
    }).join('');
    const bars = series.map((pt, idx) => {
      const val = Number(pt.thucThuRong || 0);
      const h = val === 0 ? 2 : Math.max(3, Math.round(Math.abs(val) / maxVal * chartH));
      const x = padL + idx * (barW + gap);
      const fill = val < 0 ? '#e11d48' : (val === 0 ? '#e2e8f0' : '#0d9488');
      const lbl = granularity === 'day' ? `${pt.period.slice(8, 10)}/${pt.period.slice(5, 7)}` : `T${Number(pt.period.slice(5, 7))}/${pt.period.slice(2, 4)}`;
      const showLbl = idx % labelStep === 0 || idx === series.length - 1;
      return `<rect x="${x}" y="${padT + chartH - h}" width="${barW}" height="${h}" rx="3" fill="${fill}"><title>${Admin.esc(`${lbl}: ${Admin.money(val)} (${pt.soPhieu} phiếu)`)}</title></rect>${showLbl ? `<text x="${x + barW / 2}" y="${padT + chartH + 15}" text-anchor="middle" font-size="9.5" fill="#64748b">${Admin.esc(lbl)}</text>` : ''}`;
    }).join('');
    return `<div class="rpt-svg-wrap"><svg class="rpt-svg-chart" viewBox="0 0 ${totalW} ${totalH}" role="img" aria-label="Biểu đồ thực thu ròng theo thời gian">${grid}${bars}</svg></div>`;
  }

  // ── Per-report view models: only key information ──
  const VIEWS = {
    attendance(data) {
      const s = data.summary;
      const absent = s.vangCoPhep + s.vangKhongPhep;
      return {
        kpis: [
          { l: 'Tỷ lệ chuyên cần', v: fmtRate(s.tyLeChuyenCan), sub: `${s.coMat + s.diTre}/${s.daDiemDanh} lượt có mặt`, primary: true },
          { l: 'Có mặt', v: fmtNum(s.coMat), sub: `Đi trễ ${s.diTre} lượt`, tone: 'g' },
          { l: 'Vắng', v: fmtNum(absent), sub: `Có phép ${s.vangCoPhep} · Không phép ${s.vangKhongPhep}`, tone: s.vangKhongPhep > 0 ? 'r' : 'w' },
          { l: 'Chưa điểm danh', v: fmtNum(s.chuaDiemDanh), sub: `Chưa diễn ra ${s.chuaDienRa} lượt`, tone: 'i' },
        ],
        overview: {
          title: 'Phân bổ điểm danh',
          sub: `${s.tongLuot} lượt · không tính ${s.soBuoiHuyDoi} buổi hủy/đổi`,
          html: stackBar([
            { label: 'Có mặt', value: s.coMat, color: '#0d9488' },
            { label: 'Đi trễ', value: s.diTre, color: '#f59e0b' },
            { label: 'Vắng có phép', value: s.vangCoPhep, color: '#3b82f6' },
            { label: 'Vắng không phép', value: s.vangKhongPhep, color: '#e11d48' },
            { label: 'Chưa điểm danh', value: s.chuaDiemDanh, color: '#94a3b8' },
            { label: 'Chưa diễn ra', value: s.chuaDienRa, color: '#e2e8f0' },
          ]),
        },
        table: {
          title: 'Chuyên cần theo lớp', meta: `${data.byClass.length} lớp`,
          columns: [
            { label: 'Lớp học', render: r => twoLine(r.maLop, r.tenLop) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Đã điểm danh', num: true, render: r => `${r.daDiemDanh}/${r.tongLuot}` },
            { label: 'Vắng', num: true, render: r => String(r.vangCoPhep + r.vangKhongPhep) },
            { label: 'Chuyên cần', render: r => rateBar(r.tyLeChuyenCan) },
          ],
          rows: data.byClass, empty: 'Không có dữ liệu chuyên cần trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết điểm danh',
          columns: [
            { label: 'Ngày', sortKey: 'ngay', sort: r => `${r.ngay} ${r.gioBatDau}`, search: r => fmtDate(r.ngay), render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
            { label: 'Lớp', sortKey: 'maLop', search: r => `${r.maLop} ${r.tenLop}`, render: r => Admin.esc(r.maLop) },
            { label: 'Học viên', sortKey: 'hoTen', search: r => `${r.hoTen} ${r.maHv}`, render: r => twoLine(r.hoTen, r.maHv) },
            { label: 'Điểm danh', sortKey: 'trangThaiDiemDanh', search: r => ATT_TAG[r.trangThaiDiemDanh]?.[0] || '', render: r => tagOf(ATT_TAG, r.trangThaiDiemDanh) },
            { label: 'Điểm BT', num: true, sortKey: 'diemBaiTap', sort: r => r.diemBaiTap != null ? Number(r.diemBaiTap) : null, render: r => r.diemBaiTap != null ? Admin.esc(r.diemBaiTap) : '—' },
          ],
          rows: data.details, empty: 'Chưa có lượt điểm danh phù hợp.',
        },
      };
    },

    revenue(data) {
      const s = data.summary;
      return {
        kpis: [
          { l: 'Thực thu ròng', v: Admin.money(s.thucThuRong), sub: `${s.soPhieuXacNhan} phiếu hợp lệ · hoàn ${Admin.money(s.tongHoan)}`, primary: true },
          { l: 'Phải thu trong kỳ', v: Admin.money(s.phaiThu), sub: `${s.soHoaDon} hóa đơn phát hành · còn nợ ${Admin.money(s.conNo)}`, tone: 'i' },
          { l: 'Công nợ tồn hiện hành', v: Admin.money(s.tongCongNoTonHienHanh || 0), sub: `${s.soHoaDonNoTonHienHanh || 0} hóa đơn chưa thu đủ (mọi kỳ)`, tone: Number(s.tongCongNoTonHienHanh || 0) > 0 ? 'r' : 'g' },
          { l: 'Chờ đối soát online', v: Admin.money(s.tienChoDoiSoat), sub: `${s.giaoDichChoDoiSoat} giao dịch · chưa tính doanh thu`, tone: 'w' },
        ],
        overview: {
          title: `Thực thu ròng theo ${data.trendGranularity === 'day' ? 'ngày' : 'tháng'}`,
          sub: 'Phiếu thu hợp lệ đã trừ phiếu hoàn',
          html: renderTrendSvgChart(data.trend || [], data.trendGranularity),
        },
        table: {
          title: 'Doanh thu theo khóa học & cơ sở', meta: `${data.byGroup.length} nhóm`,
          columns: [
            { label: 'Khóa học', render: r => twoLine(r.tenKhoa, r.maKhoa) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Phải thu', num: true, render: r => Admin.money(r.phaiThu) },
            { label: 'Thực thu ròng', num: true, render: r => `<b>${Admin.money(r.thucThuRong)}</b>` },
            { label: 'Còn nợ (HĐ trong kỳ)', num: true, render: r => Admin.money(r.conNo) },
          ],
          rows: data.byGroup, empty: 'Không có hóa đơn hoặc phiếu thu trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết phiếu thu / hoàn',
          columns: [
            { label: 'Ngày', sortKey: 'ngayThanhToan', search: r => fmtDate(r.ngayThanhToan), render: r => twoLine(fmtDate(r.ngayThanhToan), `PT-${r.phieuId}`) },
            { label: 'Hóa đơn · Học viên', sortKey: 'tenHocVien', search: r => `${r.maHd} ${r.tenHocVien}`, render: r => twoLine(r.tenHocVien, r.maHd) },
            { label: 'Khóa học', sortKey: 'tenKhoa', search: r => `${r.tenKhoa} ${r.tenCoSo}`, render: r => Admin.esc(r.tenKhoa) },
            { label: 'Phương thức', sortKey: 'phuongThuc', search: r => PAY_METHODS[r.phuongThuc] || r.phuongThuc, render: r => `${Admin.esc(PAY_METHODS[r.phuongThuc] || r.phuongThuc)}${r.loai === 'hoan' ? ' ' + Admin.tag('Hoàn', 'r') : ''}` },
            { label: 'Số tiền', num: true, sortKey: 'soTien', sort: r => r.loai === 'thu' ? Number(r.soTien) : -Number(r.soTien), render: r => `<b>${Admin.money(r.loai === 'thu' ? r.soTien : -r.soTien)}</b>` },
          ],
          rows: data.details, empty: 'Chưa có phiếu thu hợp lệ trong khoảng lọc.',
        },
      };
    },

    'class-performance'(data) {
      const s = data.summary;
      return {
        kpis: [
          { l: 'Lấp đầy sĩ số', v: fmtRate(s.tyLeLapDayTrungBinh), sub: `${s.tongSiSoDangHoc}/${s.tongSucChua} chỗ · ${s.soLop} lớp`, primary: true },
          { l: 'Chuyên cần chung', v: fmtRate(s.tyLeChuyenCan), sub: 'Không tính buổi hủy/đổi', tone: 'g' },
          { l: 'Buổi đã dạy', v: `${s.soBuoiDaDienRa}/${s.tongBuoiTrongKy}`, sub: `Sắp diễn ra ${s.soBuoiSapDienRa || 0} buổi`, tone: 'i' },
          { l: 'Buổi bị hủy', v: fmtNum(s.soBuoiBiHuy), sub: `Đổi lịch ${s.soBuoiDoiLich || 0} · Học bù ${s.soBuoiHocBu}`, tone: s.soBuoiBiHuy > 0 ? 'r' : 'w' },
        ],
        overview: {
          title: 'Trạng thái buổi học trong kỳ',
          sub: `${s.tongBuoiTrongKy} buổi`,
          html: stackBar([
            { label: 'Đã dạy', value: s.soBuoiDaDienRa, color: '#0d9488' },
            { label: 'Đang dạy', value: s.soBuoiDangDay || 0, color: '#f59e0b' },
            { label: 'Sắp diễn ra', value: s.soBuoiSapDienRa || 0, color: '#3b82f6' },
            { label: 'Đổi lịch', value: s.soBuoiDoiLich || 0, color: '#8b5cf6' },
            { label: 'Bị hủy', value: s.soBuoiBiHuy, color: '#e11d48' },
          ]),
        },
        table: {
          title: 'Chỉ số theo lớp', meta: `${data.classes.length} lớp`,
          columns: [
            { label: 'Lớp học', render: r => twoLine(r.maLop, `${r.tenMon} · ${r.tenCapLop}`) },
            { label: 'Người dạy', key: 'nguoiDayChinh' },
            { label: 'Sĩ số', render: r => rateBar(r.tyLeLapDay, { text: `${r.siSoDangHoc}/${r.siSoToiDa}`, color: 'var(--pri)' }) },
            { label: 'Buổi đã dạy', num: true, render: r => `${r.soBuoiDaDienRa}/${r.tongBuoiTrongKy}` },
            { label: 'Chuyên cần', render: r => rateBar(r.tyLeChuyenCan) },
          ],
          rows: data.classes, empty: 'Không có lớp học phù hợp bộ lọc.',
        },
        detail: {
          title: 'Chi tiết buổi học',
          columns: [
            { label: 'Ngày', sortKey: 'ngay', sort: r => `${r.ngay} ${r.gioBatDau}`, search: r => fmtDate(r.ngay), render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
            { label: 'Lớp', sortKey: 'maLop', search: r => `${r.maLop} ${r.tenLop}`, render: r => twoLine(r.maLop, r.tenLop) },
            { label: 'Trạng thái', sortKey: 'trangThaiBuoi', search: r => `${SES_ST_TAG[r.trangThaiBuoi]?.[0] || ''} ${r.loaiBuoi === 'bu' ? 'Học bù' : ''}`, render: r => tagOf(SES_ST_TAG, r.trangThaiBuoi) + (r.loaiBuoi === 'bu' ? ' ' + Admin.tag('Học bù', 'purple') : '') },
            { label: 'Có mặt', num: true, sortKey: 'soCoMatHoacTre', sort: r => Number(r.soCoMatHoacTre || 0), render: r => `${r.soCoMatHoacTre}/${r.soLuotDiemDanh}` },
          ],
          rows: data.details, empty: 'Chưa có buổi học trong khoảng lọc.',
        },
      };
    },

    payroll(data) {
      const s = data.summary;
      const roles = (data.byRole || []).filter(r => r.soCa > 0)
        .map(r => `<span class="rpt-chip">${Admin.esc(r.tenNhom)} <b>${Admin.esc(Admin.money(r.tienCongCa))}</b><small>${r.soCa} ca · ${r.tongGio} giờ</small></span>`).join('');
      return {
        kpis: [
          { l: 'Quỹ lương giảng dạy', v: Admin.money(s.tongTienCongCa), sub: `${s.soCa} ca hợp lệ · ${s.soNhanSu} nhân sự`, primary: true },
          { l: 'Giờ công', v: `${s.tongGio} giờ`, sub: `Đi trễ ${s.soLanTre} lần (${s.soPhutTre} phút)`, tone: 'i' },
          { l: 'Đã trả', v: Admin.money(s.tienDaTra), sub: `Đã chốt ${Admin.money(s.tienDaChot)} · Mở ${Admin.money(s.tienMo)}`, tone: 'g' },
          { l: 'Thực thu − Quỹ lương', v: Admin.money(s.chenhLechThuLuong), sub: `Thu ${Admin.money(s.thucThuRong)} · chưa tính chi phí khác`, tone: s.chenhLechThuLuong >= 0 ? 'g' : 'r' },
        ],
        overview: {
          title: 'Trạng thái quyết toán',
          sub: 'Tách theo bảng công tháng',
          html: stackBar([
            { label: 'Mở', value: s.tienMo, color: '#f59e0b' },
            { label: 'Đã chốt', value: s.tienDaChot, color: '#3b82f6' },
            { label: 'Đã trả', value: s.tienDaTra, color: '#0d9488' },
          ], v => fmtCompactMoney(v)) + (roles ? `<div class="rpt-chips">${roles}</div>` : ''),
        },
        table: {
          title: 'Công dạy theo nhân sự', meta: `${data.byPerson.length} nhân sự`,
          columns: [
            { label: 'Nhân sự', render: r => twoLine(r.hoTen, r.maNs) },
            { label: 'Nhóm', render: r => tagOf(ROLE_GROUP_TAG, r.nhomNhanSu) },
            { label: 'Ca', num: true, render: r => String(r.soCa) },
            { label: 'Giờ công', num: true, render: r => `${r.tongGio}` },
            { label: 'Tiền công', num: true, render: r => `<b>${Admin.money(r.tienCongCa)}</b>` },
            { label: 'Kỳ công', render: r => tagOf(PAYROLL_ST_TAG, r.trangThaiKy) },
          ],
          rows: data.byPerson, empty: 'Chưa có ca dạy hợp lệ trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết ca dạy',
          columns: [
            { label: 'Ngày', sortKey: 'ngay', sort: r => `${r.ngay} ${r.gioBatDau}`, search: r => fmtDate(r.ngay), render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
            { label: 'Nhân sự', sortKey: 'hoTen', search: r => `${r.hoTen} ${r.maNs}`, render: r => twoLine(r.hoTen, ROLE_GROUP_TAG[r.nhomNhanSu]?.[0] || '') },
            { label: 'Lớp', sortKey: 'maLop', search: r => `${r.maLop} ${r.tenCoSo}`, render: r => Admin.esc(r.maLop) },
            { label: 'Phút công', num: true, sortKey: 'soPhutTinhCong', sort: r => Number(r.soPhutTinhCong || 0), render: r => `${r.soPhutTinhCong}${Number(r.soPhutTre) > 0 ? ` <small class="rpt-warn">trễ ${r.soPhutTre}p</small>` : ''}` },
            { label: 'Tiền công', num: true, sortKey: 'tienCong', sort: r => Number(r.tienCong || 0), render: r => `<b>${Admin.money(r.tienCong)}</b>` },
          ],
          rows: data.details, empty: 'Chưa có ca dạy hợp lệ.',
        },
      };
    },

    debt(data) {
      const s = data.summary;
      return {
        kpis: [
          { l: 'Tổng còn nợ', v: Admin.money(s.tongConNo), sub: `${s.soHoaDonNo} hóa đơn chưa thu đủ`, primary: true },
          { l: 'Quá hạn', v: Admin.money(s.tienQuaHan), sub: `${s.soHoaDonQuaHan} hóa đơn`, tone: s.soHoaDonQuaHan > 0 ? 'r' : 'g' },
          { l: 'Chưa đến hạn', v: Admin.money(s.tienChuaDenHan), sub: `${s.soHoaDonChuaDenHan} hóa đơn`, tone: 'w' },
          { l: 'Chưa đặt hạn', v: Admin.money(s.tienKhongCoHan), sub: `${s.soHoaDonKhongCoHan} hóa đơn`, tone: 'i' },
        ],
        overview: {
          title: 'Công nợ theo hạn thanh toán',
          sub: 'Mọi hóa đơn còn nợ tại thời điểm xem',
          html: stackBar([
            { label: 'Quá hạn', value: s.tienQuaHan, color: '#e11d48' },
            { label: 'Chưa đến hạn', value: s.tienChuaDenHan, color: '#f59e0b' },
            { label: 'Chưa đặt hạn', value: s.tienKhongCoHan, color: '#94a3b8' },
          ], v => fmtCompactMoney(v)),
        },
        table: {
          title: 'Công nợ theo khóa học & cơ sở', meta: `${data.byGroup.length} nhóm`,
          columns: [
            { label: 'Khóa học', render: r => twoLine(r.tenKhoa, r.maKhoa) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Số HĐ', num: true, render: r => String(r.soHoaDonNo) },
            { label: 'Quá hạn', num: true, render: r => r.tienQuaHan > 0 ? `<span class="rpt-danger">${Admin.money(r.tienQuaHan)}</span>` : '—' },
            { label: 'Còn nợ', num: true, render: r => `<b>${Admin.money(r.tongConNo)}</b>` },
          ],
          rows: data.byGroup, empty: 'Hiện không có hóa đơn nào còn nợ.',
        },
        detail: {
          title: 'Hóa đơn còn nợ (quá hạn lên đầu)',
          columns: [
            { label: 'Hóa đơn', sortKey: 'maHd', search: r => r.maHd, render: r => twoLine(r.maHd, `Phát hành ${fmtDate(r.ngayPhatHanh)}`) },
            { label: 'Học viên · Phụ huynh', sortKey: 'tenHocVien', search: r => `${r.tenHocVien} ${r.tenPhuHuynh} ${r.sdtPhuHuynh}`, render: r => twoLine(r.tenHocVien, [r.tenPhuHuynh, r.sdtPhuHuynh].filter(v => v && v !== '—').join(' · ')) },
            { label: 'Hạn thanh toán', sortKey: 'soNgayQuaHan', sort: r => Number(r.soNgayQuaHan || 0), search: r => fmtDate(r.hanThanhToan), render: r => `${fmtDate(r.hanThanhToan)} ${r.soNgayQuaHan > 0 ? Admin.tag(`Quá ${r.soNgayQuaHan} ngày`, 'r') : tagOf(DEBT_AGING_TAG, r.nhomHan)}` },
            { label: 'Còn nợ', num: true, sortKey: 'conNo', sort: r => Number(r.conNo || 0), render: r => `<b>${Admin.money(r.conNo)}</b>` },
          ],
          rows: data.details, empty: 'Không có hóa đơn còn nợ.',
        },
      };
    },

    admissions(data) {
      const s = data.summary;
      const inProgress = s.moi + s.dangTim + s.daDeXuat;
      const sources = (data.bySource || [])
        .map(r => `<span class="rpt-chip">${Admin.esc(ADM_SOURCE_LABEL[r.nguon] || r.nguon)} <b>${r.tong}</b><small>chốt ${fmtRate(r.tyLeChot)}</small></span>`).join('');
      return {
        kpis: [
          { l: 'Tỷ lệ chốt', v: fmtRate(s.tyLeChot), sub: `${s.daChot}/${s.tongYeuCau} yêu cầu · đã mở lớp ${s.daMoLop}`, primary: true },
          { l: 'Tổng yêu cầu', v: fmtNum(s.tongYeuCau), sub: 'Tạo mới trong kỳ', tone: 'i' },
          { l: 'Đang xử lý', v: fmtNum(inProgress), sub: `Mới ${s.moi} · Đang tìm ${s.dangTim} · Đề xuất ${s.daDeXuat}`, tone: 'w' },
          { l: 'Đã hủy', v: fmtNum(s.huy), sub: 'Theo trạng thái hiện tại', tone: s.huy > 0 ? 'r' : 'g' },
        ],
        overview: {
          title: 'Trạng thái yêu cầu học',
          sub: 'Theo trạng thái hiện tại',
          html: stackBar([
            { label: 'Mới', value: s.moi, color: '#3b82f6' },
            { label: 'Đang tìm', value: s.dangTim, color: '#f59e0b' },
            { label: 'Đã đề xuất', value: s.daDeXuat, color: '#8b5cf6' },
            { label: 'Đã chốt', value: s.daChot, color: '#0d9488' },
            { label: 'Đã hủy', value: s.huy, color: '#e11d48' },
          ]) + (sources ? `<div class="rpt-chips">${sources}</div>` : ''),
        },
        table: {
          title: 'Theo cơ sở & môn học', meta: `${data.byGroup.length} nhóm`,
          columns: [
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Môn học', key: 'tenMon' },
            { label: 'Yêu cầu', num: true, render: r => String(r.tongYeuCau) },
            { label: 'Đã chốt', num: true, render: r => String(r.daChot) },
            { label: 'Hủy', num: true, render: r => String(r.huy) },
            { label: 'Tỷ lệ chốt', render: r => rateBar(r.tyLeChot, { color: 'var(--pri)' }) },
          ],
          rows: data.byGroup, empty: 'Chưa có yêu cầu học trong khoảng lọc.',
        },
        detail: {
          title: 'Danh sách yêu cầu học',
          columns: [
            { label: 'Mã YC', sortKey: 'createdAt', search: r => `${r.maYc} ${fmtDate(r.createdAt)}`, render: r => twoLine(r.maYc, fmtDate(r.createdAt)) },
            { label: 'Khách hàng', sortKey: 'khachHang', search: r => `${r.khachHang} ${r.sdt}`, render: r => twoLine(r.khachHang, r.sdt) },
            { label: 'Môn · Lớp', sortKey: 'tenMon', search: r => `${r.tenMon} ${r.tenCapLop}`, render: r => Admin.esc(`${r.tenMon} · ${r.tenCapLop}`) },
            { label: 'Nguồn', sortKey: 'nguon', search: r => ADM_SOURCE_LABEL[r.nguon] || r.nguon, render: r => Admin.esc(ADM_SOURCE_LABEL[r.nguon] || r.nguon) },
            { label: 'Trạng thái', sortKey: 'trangThai', search: r => ADM_ST_TAG[r.trangThai]?.[0] || '', render: r => tagOf(ADM_ST_TAG, r.trangThai) },
          ],
          rows: data.details, empty: 'Chưa có yêu cầu học phù hợp.',
        },
      };
    },
  };

  // ── Markup ──
  function buildReportsMarkup() {
    return `<section class="screen active rpt-page" id="admin-reports">
      <div class="pg-head">
        <div>
          <h1>Báo cáo &amp; Xuất dữ liệu</h1>
          <p class="sub" id="rptSubtitle">Số liệu trực tiếp từ hệ thống</p>
        </div>
        <div class="row rpt-actions">
          <button type="button" class="btn sm" id="rptPrintBtn">In báo cáo</button>
          <div class="rpt-export" id="rptExportWrap">
            <button type="button" class="btn p sm" id="rptExportBtn" aria-haspopup="true" aria-expanded="false">Xuất dữ liệu ▾</button>
            <div class="rpt-export-menu" id="rptExportMenu" hidden>
              <button type="button" id="rptExportXlsx">Excel (.xlsx)</button>
              <button type="button" id="rptExportCsv">CSV (UTF-8)</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card rpt-toolbar" id="rptFilterCard">
        <div class="rpt-tabs" id="rptTabs" role="tablist"></div>
        <form id="rptFilterForm" class="rpt-filter-row" autocomplete="off">
          <div class="rpt-presets" id="rptQuickDates">${PRESETS.map(([k, l]) => `<button type="button" class="rpt-qbtn" data-preset="${k}">${l}</button>`).join('')}</div>
          <div class="rpt-dates" id="rptDates">
            <input type="date" name="tuNgay" aria-label="Từ ngày">
            <span>→</span>
            <input type="date" name="denNgay" aria-label="Đến ngày">
          </div>
          <span class="rpt-debt-note" id="rptDebtNote" hidden>Công nợ tính tại thời điểm hiện tại, không phụ thuộc khoảng ngày</span>
          <div class="rpt-filter-actions">
            <span id="rptScopeBadge" class="rpt-scope" hidden></span>
            <button type="button" class="btn sm" id="rptMoreBtn" aria-expanded="false">Bộ lọc <span class="rpt-count" id="rptFilterCount" hidden></span></button>
            <button type="button" class="rpt-link" id="rptResetBtn">Đặt lại</button>
          </div>
          <div class="rpt-more" id="rptMorePanel" hidden>
            <label id="rptWrapCampus">Cơ sở<select name="coSoId"></select></label>
            <label id="rptWrapCourse">Khóa học<select name="khoaHocId"></select></label>
            <label id="rptWrapClass">Lớp học<select name="lopId"></select></label>
            <label id="rptWrapSubject">Môn học<select name="monHocId"></select></label>
            <label id="rptWrapRoleGroup">Nhóm nhân sự<select name="nhomNhanSu"><option value="">Tất cả nhóm</option><option value="giao_vien">Giáo viên</option><option value="gia_su">Gia sư</option><option value="tro_giang">Trợ giảng</option></select></label>
            <label id="rptWrapStatus">Trạng thái<select name="trangThai"></select></label>
          </div>
        </form>
      </div>

      <div id="rptStatusBanner" class="rpt-banner" hidden></div>
      <div class="rpt-kpis" id="rptStats"></div>

      <div class="card rpt-section" id="rptOverviewCard">
        <div class="rpt-section-head"><h2 id="rptOverviewTitle">Tổng quan</h2><span id="rptOverviewSub"></span></div>
        <div id="rptOverviewBody"></div>
        <details class="rpt-how" id="rptFormulaBox"><summary>Cách tính số liệu</summary><ul id="rptFormulaList"></ul></details>
      </div>

      <div class="card rpt-section" id="rptSummaryCard">
        <div class="rpt-section-head"><h2 id="rptSummaryTitle">Tổng hợp</h2><span id="rptSummaryMeta"></span></div>
        <div id="rptSummaryTable"></div>
      </div>

      <details class="card rpt-section rpt-detail" id="rptDetailWrap">
        <summary class="rpt-section-head"><h2 id="rptDetailTitle">Chi tiết</h2><span id="rptDetailMeta"></span></summary>
        <div class="rpt-table-toolbar">
          <input type="search" id="rptDetailSearch" class="rpt-search-input" placeholder="Tìm nhanh…">
          <select id="rptDetailPageSize" aria-label="Số dòng mỗi trang"><option value="25">25 dòng</option><option value="50">50 dòng</option><option value="100">100 dòng</option></select>
        </div>
        <div id="rptDetailTable"></div>
        <div id="rptDetailPager"></div>
      </details>
    </section>`;
  }

  // ── Filters ──
  const ADV_FIELDS = ['coSoId', 'khoaHocId', 'lopId', 'monHocId', 'nhomNhanSu', 'trangThai'];
  function fieldVisible(name) {
    const tab = rptState.tab;
    if (name === 'khoaHocId' || name === 'lopId') return tab !== 'admissions';
    if (name === 'monHocId') return tab === 'admissions';
    if (name === 'nhomNhanSu') return tab === 'payroll';
    return true;
  }

  function syncClassOptions() {
    const form = Admin.$('rptFilterForm');
    if (!form || !rptState.meta) return;
    const coSoId = String(rptState.filters.coSoId || '');
    const khoaHocId = String(rptState.filters.khoaHocId || '');
    const matching = rptState.meta.classes.filter(l =>
      (!coSoId || String(l.coSoId ?? '') === coSoId) && (!khoaHocId || String(l.khoaHocId ?? '') === khoaHocId));
    if (!matching.some(l => String(l.id) === String(rptState.filters.lopId))) rptState.filters.lopId = '';
    form.elements.lopId.innerHTML = '<option value="">Tất cả lớp</option>' +
      matching.map(l => `<option value="${l.id}" ${String(l.id) === String(rptState.filters.lopId) ? 'selected' : ''}>${Admin.esc(l.maLop + ' · ' + l.tenLop)}</option>`).join('');
  }

  function syncStatusDropdown() {
    const form = Admin.$('rptFilterForm');
    if (!form) return;
    const opts = STATUS_OPTIONS[rptState.tab] || [];
    if (!opts.some(([v]) => v === rptState.filters.trangThai)) rptState.filters.trangThai = '';
    form.elements.trangThai.innerHTML = opts.map(([v, l]) => `<option value="${Admin.esc(v)}" ${v === rptState.filters.trangThai ? 'selected' : ''}>${Admin.esc(l)}</option>`).join('');
  }

  function syncFilterCount() {
    const scoped = !!rptState.meta?.scopedCampusId;
    const n = ADV_FIELDS.filter(f => fieldVisible(f) && rptState.filters[f] && !(f === 'coSoId' && scoped)).length;
    const el = Admin.$('rptFilterCount');
    if (el) { el.hidden = n === 0; el.textContent = String(n); }
  }

  function syncTabVisibility() {
    const isDebt = rptState.tab === 'debt';
    Admin.$('rptQuickDates').hidden = isDebt;
    Admin.$('rptDates').hidden = isDebt;
    Admin.$('rptDebtNote').hidden = !isDebt;
    Admin.$('rptWrapCourse').hidden = !fieldVisible('khoaHocId');
    Admin.$('rptWrapClass').hidden = !fieldVisible('lopId');
    Admin.$('rptWrapSubject').hidden = !fieldVisible('monHocId');
    Admin.$('rptWrapRoleGroup').hidden = !fieldVisible('nhomNhanSu');
    syncFilterCount();
  }

  function syncPresetButtons() {
    document.querySelectorAll('#rptQuickDates [data-preset]').forEach(b => b.classList.toggle('on', b.dataset.preset === rptState.preset));
  }

  function syncFilterControls() {
    const form = Admin.$('rptFilterForm');
    if (!form || !rptState.meta) return;
    const scopedId = rptState.meta.scopedCampusId;
    if (scopedId) rptState.filters.coSoId = String(scopedId);
    form.elements.tuNgay.value = rptState.filters.tuNgay;
    form.elements.denNgay.value = rptState.filters.denNgay;

    if (scopedId) {
      form.elements.coSoId.innerHTML = rptState.meta.campuses.map(c => `<option value="${c.id}" selected>${Admin.esc(c.tenCoSo)}</option>`).join('');
      form.elements.coSoId.disabled = true;
      const badge = Admin.$('rptScopeBadge');
      if (badge && rptState.meta.campuses[0]) { badge.hidden = false; badge.textContent = rptState.meta.campuses[0].tenCoSo; badge.title = 'Phạm vi cơ sở được phân quyền'; }
    } else {
      form.elements.coSoId.disabled = false;
      form.elements.coSoId.innerHTML = '<option value="">Tất cả cơ sở</option>' +
        rptState.meta.campuses.map(c => `<option value="${c.id}" ${String(c.id) === String(rptState.filters.coSoId) ? 'selected' : ''}>${Admin.esc(c.tenCoSo)}</option>`).join('');
    }
    form.elements.khoaHocId.innerHTML = '<option value="">Tất cả khóa học</option>' +
      rptState.meta.courses.map(k => `<option value="${k.id}" ${String(k.id) === String(rptState.filters.khoaHocId) ? 'selected' : ''}>${Admin.esc(k.ma + ' · ' + k.ten)}</option>`).join('');
    form.elements.monHocId.innerHTML = '<option value="">Tất cả môn học</option>' +
      (rptState.meta.subjects || []).map(s => `<option value="${s.id}" ${String(s.id) === String(rptState.filters.monHocId) ? 'selected' : ''}>${Admin.esc(s.ten)}</option>`).join('');
    form.elements.nhomNhanSu.value = rptState.filters.nhomNhanSu || '';
    syncClassOptions();
    syncStatusDropdown();
    syncTabVisibility();
    syncPresetButtons();
  }

  function currentQueryParams(extra = {}) {
    const tab = rptState.tab;
    const p = {
      tuNgay: rptState.filters.tuNgay,
      denNgay: rptState.filters.denNgay,
      coSoId: rptState.meta?.scopedCampusId ? String(rptState.meta.scopedCampusId) : rptState.filters.coSoId,
      trangThai: rptState.filters.trangThai,
      ...extra,
    };
    if (tab !== 'admissions') { p.khoaHocId = rptState.filters.khoaHocId; p.lopId = rptState.filters.lopId; } else { p.monHocId = rptState.filters.monHocId; }
    if (tab === 'payroll') p.nhomNhanSu = rptState.filters.nhomNhanSu;
    return Admin.query(p);
  }

  function scheduleLoad(delay = 200) {
    clearTimeout(rptState.loadTimer);
    rptState.loadTimer = setTimeout(loadReport, delay);
  }

  // ── Detail table: search, sort, pagination ──
  function filteredDetailRows() {
    const d = rptState.detail;
    const q = d.query.trim().toLowerCase();
    let list = d.rows;
    if (q) list = list.filter(row => d.columns.some(col => String(col.search ? col.search(row) : (col.key ? row[col.key] : '')).toLowerCase().includes(q)));
    if (d.sortKey) {
      const col = d.columns.find(c => c.sortKey === d.sortKey);
      if (col) {
        const dir = d.sortDir === 'asc' ? 1 : -1;
        list = [...list].sort((a, b) => {
          const va = col.sort ? col.sort(a) : a[col.sortKey];
          const vb = col.sort ? col.sort(b) : b[col.sortKey];
          if (va == null && vb == null) return 0;
          if (va == null) return 1;
          if (vb == null) return -1;
          if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
          return String(va).localeCompare(String(vb), 'vi', { numeric: true }) * dir;
        });
      }
    }
    return list;
  }

  function renderDetailTablePage() {
    const table = Admin.$('rptDetailTable');
    const pager = Admin.$('rptDetailPager');
    if (!table || !pager) return;
    const d = rptState.detail;
    const list = filteredDetailRows();
    const total = list.length;
    const pages = Math.max(1, Math.ceil(total / d.pageSize));
    d.page = Math.min(Math.max(1, d.page), pages);
    const start = (d.page - 1) * d.pageSize;
    const slice = list.slice(start, start + d.pageSize);

    const head = d.columns.map(col => {
      if (!col.sortKey) return `<th class="${col.num ? 'num' : ''}">${Admin.esc(col.label)}</th>`;
      const active = d.sortKey === col.sortKey;
      return `<th class="rpt-sort ${col.num ? 'num' : ''} ${active ? 'active' : ''}" data-rpt-sort="${Admin.esc(col.sortKey)}">${Admin.esc(col.label)}<span class="rpt-sort-ind">${active ? (d.sortDir === 'asc' ? '▲' : '▼') : '↕'}</span></th>`;
    }).join('');
    const body = slice.length
      ? slice.map(r => `<tr>${d.columns.map(c => `<td class="${c.num ? 'num' : ''}">${c.render ? c.render(r) : Admin.esc(r[c.key] ?? '—')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${d.columns.length}" class="admin-empty">${Admin.esc(d.empty)}</td></tr>`;
    table.innerHTML = `<div class="tw rpt-table"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
    table.querySelectorAll('th[data-rpt-sort]').forEach(th => th.addEventListener('click', () => {
      const key = th.dataset.rptSort;
      if (d.sortKey === key) d.sortDir = d.sortDir === 'asc' ? 'desc' : 'asc'; else { d.sortKey = key; d.sortDir = 'desc'; }
      d.page = 1;
      renderDetailTablePage();
    }));

    pager.innerHTML = total > d.pageSize
      ? `<div class="admin-pager"><span>${start + 1}–${Math.min(total, start + d.pageSize)} / ${total}</span><div class="row"><button type="button" class="btn sm" data-rpt-page="${d.page - 1}" ${d.page <= 1 ? 'disabled' : ''}>Trước</button><button type="button" class="btn sm" data-rpt-page="${d.page + 1}" ${d.page >= pages ? 'disabled' : ''}>Sau</button></div></div>`
      : (total ? `<div class="admin-pager"><span>${total} bản ghi</span></div>` : '');
    pager.querySelectorAll('[data-rpt-page]').forEach(b => b.addEventListener('click', () => { d.page = Number(b.dataset.rptPage); renderDetailTablePage(); }));
  }

  function setBanner(html, isError = false) {
    const box = Admin.$('rptStatusBanner');
    if (!box) return;
    box.hidden = !html;
    box.className = `rpt-banner ${isError ? 'error' : ''}`;
    box.innerHTML = html || '';
  }

  // ── Load & render ──
  async function loadReport() {
    const f = rptState.filters;
    if (rptState.tab !== 'debt' && (!f.tuNgay || !f.denNgay || f.tuNgay > f.denNgay)) {
      setBanner('Khoảng ngày chưa hợp lệ: “Từ ngày” phải trước hoặc bằng “Đến ngày”.', true);
      return;
    }
    const version = ++rptState.version;
    setBanner('');
    document.querySelector('.rpt-page')?.classList.add('is-loading');
    try {
      const data = await Admin.api(`/api/reporting/dashboard/reports/${rptState.tab}?${currentQueryParams()}`);
      if (version !== rptState.version || !Admin.$('rptSummaryTable')) return;
      rptState.lastData = data;
      renderReportData(data);
    } catch (error) {
      if (version !== rptState.version || !Admin.$('rptSummaryTable')) return;
      rptState.lastData = null;
      rptState.lastView = null;
      setBanner(`${Admin.esc(error.message)} <button type="button" class="btn sm" id="rptRetryBtn">Thử lại</button>`, true);
      Admin.$('rptRetryBtn')?.addEventListener('click', loadReport);
      Admin.$('rptStats').innerHTML = '';
      Admin.$('rptOverviewBody').innerHTML = '';
      Admin.$('rptSummaryTable').innerHTML = '<div class="admin-empty">Không tải được dữ liệu báo cáo.</div>';
      Admin.$('rptDetailTable').innerHTML = '';
      Admin.$('rptDetailPager').innerHTML = '';
    } finally {
      if (version === rptState.version) document.querySelector('.rpt-page')?.classList.remove('is-loading');
    }
  }

  function periodLabel() {
    if (rptState.tab === 'debt') return 'Tại thời điểm hiện tại';
    return `${fmtDate(rptState.filters.tuNgay)} – ${fmtDate(rptState.filters.denNgay)}`;
  }

  function renderReportData(data) {
    const view = VIEWS[data.report](data);
    rptState.lastView = view;

    Admin.$('rptSubtitle').textContent = `${TAB_META[data.report]?.title || 'Báo cáo'} · ${periodLabel()}`;
    Admin.$('rptStats').innerHTML = view.kpis.map(kpiCard).join('');

    Admin.$('rptOverviewTitle').textContent = view.overview.title;
    Admin.$('rptOverviewSub').textContent = view.overview.sub || '';
    Admin.$('rptOverviewBody').innerHTML = view.overview.html;
    Admin.$('rptFormulaList').innerHTML = (data.congThuc || []).map(l => `<li>${Admin.esc(l)}</li>`).join('');

    Admin.$('rptSummaryTitle').textContent = view.table.title;
    Admin.$('rptSummaryMeta').textContent = view.table.meta || '';
    Admin.$('rptSummaryTable').innerHTML = simpleTable(view.table.columns, view.table.rows, view.table.empty);

    const d = rptState.detail;
    d.columns = view.detail.columns;
    d.rows = view.detail.rows || [];
    d.empty = view.detail.empty;
    d.page = 1; d.sortKey = ''; d.sortDir = 'desc'; d.query = '';
    if (Admin.$('rptDetailSearch')) Admin.$('rptDetailSearch').value = '';
    Admin.$('rptDetailTitle').textContent = view.detail.title;
    const limitNote = d.rows.length >= (data.detailLimit || 5000) ? ` (giới hạn ${data.detailLimit} dòng — xuất Excel để xem đủ)` : '';
    Admin.$('rptDetailMeta').textContent = `${d.rows.length} bản ghi${limitNote}`;
    if (Admin.$('rptDetailWrap')?.open) renderDetailTablePage();
  }

  // ── Print ──
  function openPrintReportDialog() {
    const data = rptState.lastData;
    const view = rptState.lastView;
    if (!data || !view) { Admin.notify('Chưa có dữ liệu báo cáo để in.', true); return; }
    const form = Admin.$('rptFilterForm');
    const textOf = name => { const s = form?.elements?.[name]; return s && s.selectedIndex >= 0 ? s.options[s.selectedIndex].textContent : 'Tất cả'; };
    const filters = [`Cơ sở: ${textOf('coSoId')}`];
    if (fieldVisible('khoaHocId')) filters.push(`Khóa: ${textOf('khoaHocId')}`, `Lớp: ${textOf('lopId')}`);
    if (fieldVisible('monHocId')) filters.push(`Môn: ${textOf('monHocId')}`);
    if (fieldVisible('nhomNhanSu')) filters.push(`Nhóm: ${textOf('nhomNhanSu')}`);
    filters.push(`Trạng thái: ${textOf('trangThai')}`);

    const d = rptState.detail;
    const rows = filteredDetailRows();
    const maxRows = 150;
    const detailHtml = simpleTable(d.columns, rows.slice(0, maxRows), d.empty)
      + (rows.length > maxRows ? `<p class="rpt-print-note">Bản in hiển thị ${maxRows}/${rows.length} dòng chi tiết. Dùng “Xuất dữ liệu” để lấy đầy đủ.</p>` : '');

    Admin.dialog('Xem trước bản in', `
      <div class="dialog-body rpt-print-doc">
        <h2>EduCenter ERP · ${Admin.esc(TAB_META[data.report]?.title || 'Báo cáo')}</h2>
        <p class="rpt-print-meta"><b>Kỳ:</b> ${Admin.esc(periodLabel())} · <b>Bộ lọc:</b> ${Admin.esc(filters.join(' · '))}<br><b>Người lập:</b> ${Admin.esc(Admin.user?.hoTen || '—')} · <b>In lúc:</b> ${Admin.esc(new Date().toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' }))}</p>
        <div class="rpt-kpis rpt-print-kpis">${view.kpis.map(kpiCard).join('')}</div>
        <h3>${Admin.esc(view.overview.title)}</h3>
        ${view.overview.html}
        <h3>${Admin.esc(view.table.title)}</h3>
        ${simpleTable(view.table.columns, view.table.rows, view.table.empty)}
        <h3>${Admin.esc(view.detail.title)}</h3>
        ${detailHtml}
      </div>
      <div class="dialog-foot">
        <button type="button" class="btn sm" data-close-dialog="1">Đóng</button>
        <button type="button" class="btn p sm" data-act="print-window">In / Lưu PDF</button>
      </div>`);
  }

  async function downloadReport(format, btn) {
    await Admin.busy(btn, async () => {
      const res = await fetch(`/api/reporting/dashboard/reports/${rptState.tab}/export?${currentQueryParams({ format })}`, { method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) {
        const err = await res.json().catch(() => null);
        throw new Error(err?.message || 'Không xuất được tệp báo cáo.');
      }
      const blob = await res.blob();
      const match = (res.headers.get('Content-Disposition') || '').match(/filename="?([^"]+)"?/i);
      const filename = match ? match[1] : `bao-cao-${rptState.tab}-${rptState.filters.tuNgay}.${format}`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      Admin.notify(`Đã tải xuống ${filename}`);
    });
  }

  // ── Screen ──
  async function reportsScreenHandler() {
    rptState.meta = await Admin.api('/api/reporting/dashboard/reports/meta');
    const allowed = rptState.meta.allowedReports || ['attendance'];
    if (!allowed.includes(rptState.tab)) rptState.tab = allowed[0];

    return {
      title: 'Báo cáo & Xuất dữ liệu',
      html: buildReportsMarkup(),
      bind() {
        const tabsEl = Admin.$('rptTabs');
        tabsEl.innerHTML = allowed.map(key => `<button type="button" class="rpt-tab ${key === rptState.tab ? 'on' : ''}" role="tab" aria-selected="${key === rptState.tab}" data-rpt-tab="${Admin.esc(key)}">${Admin.esc(TAB_META[key]?.label || key)}</button>`).join('');
        if (!rptState.meta.canDownload) Admin.$('rptExportWrap').hidden = true;

        syncFilterControls();

        tabsEl.querySelectorAll('[data-rpt-tab]').forEach(btn => btn.addEventListener('click', () => {
          if (rptState.tab === btn.dataset.rptTab) return;
          rptState.tab = btn.dataset.rptTab;
          tabsEl.querySelectorAll('[data-rpt-tab]').forEach(b => { const on = b === btn; b.classList.toggle('on', on); b.setAttribute('aria-selected', String(on)); });
          syncStatusDropdown();
          syncTabVisibility();
          loadReport();
        }));

        const form = Admin.$('rptFilterForm');
        form.addEventListener('submit', e => e.preventDefault());

        document.querySelectorAll('#rptQuickDates [data-preset]').forEach(btn => btn.addEventListener('click', () => {
          rptState.preset = btn.dataset.preset;
          Object.assign(rptState.filters, dateRangePreset(rptState.preset));
          form.elements.tuNgay.value = rptState.filters.tuNgay;
          form.elements.denNgay.value = rptState.filters.denNgay;
          syncPresetButtons();
          loadReport();
        }));

        ['tuNgay', 'denNgay'].forEach(name => form.elements[name].addEventListener('change', () => {
          rptState.filters[name] = form.elements[name].value;
          rptState.preset = '';
          syncPresetButtons();
          scheduleLoad(350);
        }));

        ADV_FIELDS.forEach(name => form.elements[name].addEventListener('change', () => {
          if (name === 'coSoId' && rptState.meta.scopedCampusId) return;
          rptState.filters[name] = form.elements[name].value;
          if (name === 'coSoId' || name === 'khoaHocId') syncClassOptions();
          syncFilterCount();
          scheduleLoad();
        }));

        const moreBtn = Admin.$('rptMoreBtn');
        moreBtn.addEventListener('click', () => {
          const panel = Admin.$('rptMorePanel');
          panel.hidden = !panel.hidden;
          moreBtn.setAttribute('aria-expanded', String(!panel.hidden));
          moreBtn.classList.toggle('on', !panel.hidden);
        });

        Admin.$('rptResetBtn').addEventListener('click', () => {
          rptState.preset = 'this-month';
          rptState.filters = { ...dateRangePreset('this-month'), coSoId: rptState.meta.scopedCampusId ? String(rptState.meta.scopedCampusId) : '', khoaHocId: '', lopId: '', monHocId: '', nhomNhanSu: '', trangThai: '' };
          syncFilterControls();
          loadReport();
        });

        Admin.$('rptDetailWrap').addEventListener('toggle', e => { if (e.currentTarget.open) renderDetailTablePage(); });
        Admin.$('rptDetailSearch').addEventListener('input', e => { rptState.detail.query = String(e.target.value || ''); rptState.detail.page = 1; renderDetailTablePage(); });
        Admin.$('rptDetailPageSize').addEventListener('change', e => { rptState.detail.pageSize = Number(e.target.value) || 25; rptState.detail.page = 1; renderDetailTablePage(); });
        Admin.$('rptDetailPageSize').value = String(rptState.detail.pageSize);

        const exportBtn = Admin.$('rptExportBtn');
        const exportMenu = Admin.$('rptExportMenu');
        const closeMenu = () => { exportMenu.hidden = true; exportBtn.setAttribute('aria-expanded', 'false'); };
        exportBtn.addEventListener('click', e => { e.stopPropagation(); exportMenu.hidden = !exportMenu.hidden; exportBtn.setAttribute('aria-expanded', String(!exportMenu.hidden)); });
        document.addEventListener('click', ev => { if (!Admin.$('rptExportWrap')?.contains(ev.target)) closeMenu(); });
        Admin.$('rptExportXlsx').addEventListener('click', e => { closeMenu(); downloadReport('xlsx', e.currentTarget); });
        Admin.$('rptExportCsv').addEventListener('click', e => { closeMenu(); downloadReport('csv', e.currentTarget); });
        Admin.$('rptPrintBtn').addEventListener('click', openPrintReportDialog);

        loadReport();
      }
    };
  }

  Admin.register('admin-reports', reportsScreenHandler);
  Admin.register('staff-reports', reportsScreenHandler);

  // Hide the Giáo vụ sidebar entry when the account lacks BaoCaoThongKe view permission (API still enforces 403).
  if (document.body.dataset.portal === 'admissions') {
    window.addEventListener('DOMContentLoaded', () => {
      const btn = document.querySelector('#admin-nav [data-screen="staff-reports"]');
      if (!btn) return;
      const account = window.AccessService?.getCurrentUser?.();
      const allowed = account?.role === 'ADMIN' || (account?.permissions || []).some(p => p.functionName === 'BaoCaoThongKe' && p.view);
      if (!allowed) btn.style.display = 'none';
    });
  }
})();
