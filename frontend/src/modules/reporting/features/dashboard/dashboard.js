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
  const fmtShortDate = ymd => {
    if (!ymd || String(ymd).length < 10) return '—';
    const [, m, d] = String(ymd).slice(0, 10).split('-');
    return `${d}/${m}`;
  };
  const fmtRate = v => (v === null || v === undefined ? '—' : `${Number(v).toFixed(1)}%`);
  const fmtHours = mins => `${Number((Number(mins || 0) / 60).toFixed(1))} giờ`;
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

  // ── 1. Admin Dashboard ──
  const dashState = { month: currentMonthVN(), version: 0, email: null };

  function appendEmailAlert() {
    const box = Admin.$('adminPendingWork');
    const s = dashState.email;
    if (!box || !s) return;
    const issues = Number(s.failedPermanent || 0) + Number(s.retrying || 0);
    if (issues <= 0) return;
    box.insertAdjacentHTML('beforeend', `<div class="item"><span><b>${issues}</b> Email lỗi / chờ thử lại</span><span class="tag r">Kiểm tra SMTP</span></div>`);
  }

  function renderDashboardPayload(data) {
    const statsEl = Admin.$('adminDashStats');
    if (!statsEl) return;
    const [yy, mm] = String(data.month || dashState.month).split('-');
    const mLabel = `T${Number(mm)}/${yy}`;

    let revSub = `Thực thu ${mLabel}`;
    if (data.revenueChangePct !== null && data.revenueChangePct !== undefined) {
      const sign = data.revenueChangePct > 0 ? '+' : '';
      revSub += ` · ${sign}${data.revenueChangePct}%`;
    }
    const debtSub = Number(data.counts?.overdueInvoices || 0) > 0
      ? `Công nợ (${data.counts.overdueInvoices} HĐ quá hạn)`
      : 'Tổng công nợ học phí';

    const cards = [
      [String(data.counts.students), 'Học viên đang học', ''],
      [`${data.counts.courses} khóa`, 'Khóa học đang mở', 'g'],
      [`${data.counts.classes} lớp`, 'Lớp đang vận hành', 'i'],
      [Admin.money(data.received), revSub, 'w'],
      [Admin.money(data.counts.debt || 0), debtSub, Number(data.counts.debt || 0) > 0 ? 'r' : 'g'],
    ];
    if (Admin.user?.role === 'ADMIN' && data.payroll) {
      const p = data.payroll;
      cards.push([Admin.money(p.total || 0), `Quỹ lương ${mLabel} · ${p.sessions} ca`, 'p']);
    }
    statsEl.innerHTML = cards
      .map(([n, label, cls]) => `<div class="st ${cls}"><div class="n">${Admin.esc(n)}</div><span>${Admin.esc(label)}</span></div>`)
      .join('');

    const titleEl = Admin.$('adminRevenueChartTitle');
    if (titleEl) titleEl.textContent = `Thực thu ${data.revenue.length} tháng đến ${mLabel}`;

    const deltaEl = Admin.$('adminRevenueDelta');
    if (deltaEl) {
      if (data.revenueChangePct !== null && data.revenueChangePct !== undefined) {
        const up = data.revenueChangePct >= 0;
        deltaEl.className = `dash-delta ${data.revenueChangePct === 0 ? 'flat' : (up ? 'up' : 'down')}`;
        deltaEl.textContent = `${up && data.revenueChangePct > 0 ? '▲ +' : (data.revenueChangePct < 0 ? '▼ ' : '')}${data.revenueChangePct}% so với tháng trước`;
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
      [data.pending.payments, 'Giao dịch chờ đối soát thu tiền', 'Đối soát', 'admin-payments', 'p'],
      [data.pending.enrollments, 'Đăng ký khóa chờ xếp lớp', 'Xếp lớp', 'admin-courses', ''],
      [data.pending.corrections + data.pending.classLeave, 'Đơn chỉnh công & nghỉ cả lớp chờ duyệt', 'Phê duyệt', 'admin-exceptions', 'warn']
    ];
    Admin.$('adminPendingWork').innerHTML = pending
      .map(([count, label, button, screen, style]) => `<div class="item"><span><b>${count}</b> ${Admin.esc(label)}</span><button type="button" class="btn sm ${style}" data-nav="${Admin.esc(screen)}">${button}</button></div>`)
      .join('')
      + `<div class="item"><span>Mở khóa kỳ công đã chốt</span><button type="button" class="btn sm purple" data-nav="admin-exceptions">Xem</button></div>`
      + `<div class="item"><span>Báo cáo &amp; xuất Excel / CSV</span><button type="button" class="btn sm" data-nav="admin-reports">Mở báo cáo</button></div>`;
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
        title: 'Tổng quan Quản trị',
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

  // ── 2. Reports (Admin + Giáo vụ) ──
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
  const PRESET_LABELS = Object.fromEntries(PRESETS);

  const TAB_META = {
    attendance: { label: 'Chuyên cần', title: 'Báo cáo Chuyên cần học viên' },
    revenue: { label: 'Doanh thu', title: 'Báo cáo Doanh thu' },
    'class-performance': { label: 'Lớp học', title: 'Báo cáo Vận hành lớp học' },
    payroll: { label: 'Công & Lương', title: 'Báo cáo Công dạy & Quỹ lương' },
    debt: { label: 'Công nợ', title: 'Báo cáo Công nợ tồn' },
    admissions: { label: 'Tuyển sinh', title: 'Thống kê Tuyển sinh' },
  };

  const STATUS_OPTIONS = {
    attendance: [['', 'Mọi trạng thái'], ['co_mat', 'Có mặt'], ['di_tre', 'Đi trễ'], ['vang_co_phep', 'Vắng có phép'], ['vang_khong_phep', 'Vắng không phép'], ['chua_diem_danh', 'Chưa điểm danh'], ['chua_dien_ra', 'Chưa diễn ra']],
    revenue: [['', 'Mọi trạng thái HĐ'], ['da_thanh_toan', 'Đã thu đủ'], ['mot_phan', 'Thu một phần'], ['chua_thanh_toan', 'Chưa thu']],
    'class-performance': [['', 'Mọi trạng thái lớp'], ['dang_hoc', 'Đang học'], ['sap_bat_dau', 'Sắp bắt đầu'], ['tam_dung', 'Tạm dừng'], ['hoan_thanh', 'Hoàn thành'], ['huy', 'Đã hủy']],
    payroll: [['', 'Mọi trạng thái kỳ'], ['mo', 'Tạm tính'], ['da_chot', 'Đã chốt'], ['da_tra', 'Đã trả']],
    debt: [['', 'Mọi HĐ còn nợ'], ['qua_han', 'Quá hạn'], ['chua_den_han', 'Chưa đến hạn'], ['khong_co_han', 'Chưa đặt hạn'], ['chua_thanh_toan', 'Chưa thu'], ['mot_phan', 'Thu một phần']],
    admissions: [['', 'Mọi trạng thái'], ['moi', 'Mới'], ['dang_tim', 'Đang tìm'], ['da_de_xuat', 'Đã đề xuất'], ['da_chot', 'Đã chốt'], ['huy', 'Đã hủy']],
  };

  const ATT_TAG = { co_mat: ['Có mặt', 'ok'], di_tre: ['Đi trễ', 'w'], vang_co_phep: ['Vắng CP', 'i'], vang_khong_phep: ['Vắng KP', 'r'], chua_diem_danh: ['Chưa ĐD', 'w'], chua_dien_ra: ['Chưa học', ''] };
  const SES_ST_TAG = { du_kien: ['Sắp diễn ra', 'i'], dang_day: ['Đang dạy', 'w'], da_day: ['Đã dạy', 'ok'], huy: ['Đã hủy', 'r'], doi: ['Đổi lịch', 'w'] };
  const PAY_METHODS = { tien_mat: 'Tiền mặt', chuyen_khoan: 'Chuyển khoản', vi_dien_tu: 'Ví điện tử', the: 'Thẻ' };
  const ROLE_GROUP_TAG = { giao_vien: ['Giáo viên', 'i'], gia_su: ['Gia sư', 'purple'], tro_giang: ['Trợ giảng', 'ok'] };
  const PAYROLL_ST_TAG = { mo: ['Tạm tính', 'w'], da_chot: ['Đã chốt', 'i'], da_tra: ['Đã trả', 'ok'], hon_hop: ['Hỗn hợp', ''] };
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
    detailQuery: '',
    allExpanded: false,
    version: 0,
    loadTimer: null,
  };

  // ── Visual Helpers: KPI, SVG Donut Chart, Trend Bar Chart ──
  function kpiCard(k) {
    return `<div class="rpt-kpi ${k.primary ? 'primary' : ''} ${k.tone ? 'tone-' + k.tone : ''}">
      <span class="rpt-kpi-label">${Admin.esc(k.l)}</span>
      <b class="rpt-kpi-value">${Admin.esc(k.v)}</b>
      ${k.sub ? `<span class="rpt-kpi-sub">${Admin.esc(k.sub)}</span>` : '<span class="rpt-kpi-sub">&nbsp;</span>'}
    </div>`;
  }

  function donutChart(segs, { centerValue = '—', centerLabel = '', fmt = v => fmtNum(v), extraHtml = '' } = {}) {
    const clean = segs.map(s => ({ ...s, value: Math.max(0, Number(s.value || 0)) }));
    const total = clean.reduce((acc, s) => acc + s.value, 0);
    if (total <= 0) {
      return '<div class="rpt-empty">Chưa có dữ liệu trong khoảng lọc.</div>' + extraHtml;
    }
    const r = 56;
    const cx = 80;
    const cy = 80;
    const circ = 2 * Math.PI * r;
    let offset = 0;
    const arcs = clean.filter(s => s.value > 0).map(s => {
      const frac = s.value / total;
      const len = frac * circ;
      const dash = `${Math.max(1.5, len - 1.5).toFixed(2)} ${(circ - Math.max(1.5, len - 1.5)).toFixed(2)}`;
      const el = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${s.color}" stroke-width="18" stroke-dasharray="${dash}" stroke-dashoffset="${(-offset).toFixed(2)}" transform="rotate(-90 ${cx} ${cy})"><title>${Admin.esc(`${s.label}: ${fmt(s.value)} (${(frac * 100).toFixed(1)}%)`)}</title></circle>`;
      offset += len;
      return el;
    }).join('');

    const svg = `<svg class="rpt-donut-svg" viewBox="0 0 160 160" role="img" aria-label="Biểu đồ phân bổ">
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#f1f5f9" stroke-width="18"/>
      ${arcs}
      <text x="${cx}" y="${centerLabel ? 76 : 84}" text-anchor="middle" font-size="18" font-weight="800" fill="#0f172a">${Admin.esc(centerValue)}</text>
      ${centerLabel ? `<text x="${cx}" y="95" text-anchor="middle" font-size="10.5" font-weight="600" fill="#64748b">${Admin.esc(centerLabel)}</text>` : ''}
    </svg>`;

    const legend = clean.map(s => {
      const pct = (s.value / total * 100).toFixed(1);
      return `<div class="rpt-leg-item">
        <span class="rpt-leg-left"><i style="background:${s.color}"></i><span>${Admin.esc(s.label)}</span></span>
        <span class="rpt-leg-right"><b>${Admin.esc(fmt(s.value))}</b><small>${pct}%</small></span>
      </div>`;
    }).join('');

    return `<div class="rpt-donut-layout">${svg}<div class="rpt-donut-legend">${legend}</div></div>${extraHtml}`;
  }

  function rateBar(v, { text = null, color = null } = {}) {
    if (v === null || v === undefined) return '<span class="rpt-muted">—</span>';
    const c = color || (v >= 85 ? 'var(--ok)' : v >= 70 ? 'var(--warn)' : 'var(--err)');
    return `<div class="rpt-rate"><span class="rpt-rate-track"><i style="width:${Math.min(100, Math.max(0, v))}%;background:${c}"></i></span><b>${Admin.esc(text ?? fmtRate(v))}</b></div>`;
  }

  function twoLine(main, sub) {
    return `<b>${Admin.esc(main)}</b>${sub ? `<small class="rpt-sub">${Admin.esc(sub)}</small>` : ''}`;
  }

  function simpleTable(columns, rows, empty, rowAttr = null) {
    const head = columns.map(c => `<th class="${c.num ? 'num' : ''}">${Admin.esc(c.label)}</th>`).join('');
    const body = rows.length
      ? rows.map(r => `<tr ${rowAttr ? rowAttr(r) : ''}>${columns.map(c => `<td class="${c.num ? 'num' : ''}">${c.render ? c.render(r) : Admin.esc(r[c.key] ?? '—')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${columns.length}" class="admin-empty">${Admin.esc(empty)}</td></tr>`;
    return `<div class="tw rpt-table"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function renderTrendSvgChart(series, granularity) {
    if (!series.length || series.every(p => Number(p.thucThuRong || 0) === 0)) {
      return '<div class="rpt-empty">Chưa có phiếu thu hợp lệ trong khoảng lọc.</div>';
    }
    const maxVal = Math.max(1, ...series.map(p => Math.abs(Number(p.thucThuRong || 0))));
    const barW = Math.max(12, Math.min(34, Math.floor(500 / Math.max(1, series.length)) - 6));
    const gap = Math.max(4, Math.min(10, Math.floor(barW * 0.3)));
    const padL = 46, padR = 8, padT = 12, padB = 24, chartH = 124;
    const totalW = Math.max(320, padL + padR + series.length * (barW + gap));
    const totalH = padT + chartH + padB;
    const labelStep = series.length > 14 ? Math.ceil(series.length / 7) : 1;
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

  // ── Grouped Detail Builders (Class -> Students / Sessions / Invoices) ──
  function buildAttendanceClassGroups(details) {
    const classMap = new Map();
    for (const r of details || []) {
      const cKey = r.maLop || 'KHAC';
      if (!classMap.has(cKey)) {
        classMap.set(cKey, {
          key: cKey,
          title: r.maLop || 'Chưa gắn lớp',
          sub: [r.tenLop, r.tenCoSo].filter(Boolean).join(' · '),
          students: new Map(),
          daDiemDanh: 0,
          coMatHoacTre: 0,
        });
      }
      const cls = classMap.get(cKey);
      const sKey = r.maHv || r.hoTen || String(r.hocVienId || 'hv');
      if (!cls.students.has(sKey)) {
        cls.students.set(sKey, {
          key: sKey,
          hoTen: r.hoTen || '—',
          maHv: r.maHv || '',
          coMat: 0, diTre: 0, vangCP: 0, vangKP: 0, chuaDD: 0, chuaHoc: 0,
          scores: [],
          sessions: [],
        });
      }
      const st = cls.students.get(sKey);
      st.sessions.push(r);
      const code = r.trangThaiDiemDanh;
      if (code === 'co_mat') { st.coMat++; cls.daDiemDanh++; cls.coMatHoacTre++; }
      else if (code === 'di_tre') { st.diTre++; cls.daDiemDanh++; cls.coMatHoacTre++; }
      else if (code === 'vang_co_phep') { st.vangCP++; cls.daDiemDanh++; }
      else if (code === 'vang_khong_phep') { st.vangKP++; cls.daDiemDanh++; }
      else if (code === 'chua_diem_danh') st.chuaDD++;
      else st.chuaHoc++;
      if (r.diemBaiTap != null && r.diemBaiTap !== '') st.scores.push(Number(r.diemBaiTap));
    }

    return [...classMap.values()].map(cls => {
      const stuList = [...cls.students.values()].map(st => {
        const dd = st.coMat + st.diTre + st.vangCP + st.vangKP;
        const rate = dd > 0 ? (st.coMat + st.diTre) * 100 / dd : null;
        const avgScore = st.scores.length ? (st.scores.reduce((a, b) => a + b, 0) / st.scores.length).toFixed(1) : null;
        return { ...st, daDiemDanh: dd, rate, avgScore };
      }).sort((a, b) => a.hoTen.localeCompare(b.hoTen, 'vi'));
      const classRate = cls.daDiemDanh > 0 ? cls.coMatHoacTre * 100 / cls.daDiemDanh : null;
      return {
        key: cls.key,
        title: cls.title,
        sub: cls.sub,
        badges: [
          Admin.tag(`${stuList.length} học viên`, 'i'),
          classRate !== null ? Admin.tag(`CC ${fmtRate(classRate)}`, classRate >= 85 ? 'ok' : (classRate >= 70 ? 'w' : 'r')) : Admin.tag('Chưa ĐD', ''),
        ].join(''),
        searchText: `${cls.title} ${cls.sub} ${stuList.map(s => `${s.hoTen} ${s.maHv}`).join(' ')}`.toLowerCase(),
        items: stuList,
        renderItems(list) {
          const rows = list.map((st, idx) => {
            const sessId = `rpt-stu-${cls.key.replace(/[^a-zA-Z0-9_-]/g, '')}-${idx}`;
            const pills = st.sessions.map(s => `<span class="rpt-stu-sess-pill"><b>${fmtShortDate(s.ngay)}</b> ${Admin.esc(timeRange(s))} ${tagOf(ATT_TAG, s.trangThaiDiemDanh)}${s.diemBaiTap != null ? ` · <b>${Admin.esc(s.diemBaiTap)}đ</b>` : ''}</span>`).join('');
            return `<tr>
              <td>${twoLine(st.hoTen, st.maHv)}</td>
              <td class="num"><b>${st.coMat}</b>${st.diTre ? ` <small class="rpt-warn">(+${st.diTre} trễ)</small>` : ''}</td>
              <td class="num">${st.vangCP + st.vangKP > 0 ? `<span class="${st.vangKP > 0 ? 'rpt-danger' : ''}">${st.vangCP + st.vangKP}</span> <small class="rpt-muted">(${st.vangCP}/${st.vangKP})</small>` : '0'}</td>
              <td class="num">${st.chuaDD}</td>
              <td class="num">${st.avgScore != null ? `<b>${st.avgScore}</b>` : '<span class="rpt-muted">—</span>'}</td>
              <td>${rateBar(st.rate)}</td>
              <td class="num"><button type="button" class="btn sm" data-toggle-stu="${sessId}">${st.sessions.length} buổi ▾</button></td>
            </tr>
            <tr id="${sessId}" hidden><td colspan="7" style="padding:0"><div class="rpt-stu-sessions"><small class="rpt-muted">Chi tiết từng buổi của <b>${Admin.esc(st.hoTen)}</b>:</small><div class="rpt-stu-sess-list">${pills}</div></div></td></tr>`;
          }).join('');
          return `<div class="tw rpt-table"><table>
            <thead><tr><th>Học viên</th><th class="num">Có mặt</th><th class="num">Vắng (CP/KP)</th><th class="num">Chưa ĐD</th><th class="num">Điểm TB</th><th>Chuyên cần</th><th class="num">Buổi học</th></tr></thead>
            <tbody>${rows}</tbody>
          </table></div>`;
        },
        filterItem: (st, q) => `${cls.title} ${cls.sub} ${st.hoTen} ${st.maHv}`.toLowerCase().includes(q),
      };
    });
  }

  function buildGenericGroups(rows, { groupKey, groupTitle, groupSub, groupBadges, columns, itemSearch, empty }) {
    const map = new Map();
    for (const r of rows || []) {
      const k = String(groupKey(r) || 'KHAC');
      if (!map.has(k)) map.set(k, { key: k, title: groupTitle(r), sub: groupSub ? groupSub(r) : '', rows: [] });
      map.get(k).rows.push(r);
    }
    return [...map.values()].map(g => ({
      key: g.key,
      title: g.title,
      sub: g.sub,
      badges: groupBadges ? groupBadges(g.rows) : Admin.tag(`${g.rows.length} bản ghi`, 'i'),
      items: g.rows,
      renderItems: list => simpleTable(columns, list, empty),
      filterItem: (item, q) => `${g.title} ${g.sub} ${itemSearch(item)}`.toLowerCase().includes(q),
    }));
  }

  // ── Per-report view models ──
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
          title: 'Biểu đồ phân bổ điểm danh',
          sub: `${s.tongLuot} lượt · không tính ${s.soBuoiHuyDoi} buổi hủy/đổi`,
          html: donutChart([
            { label: 'Có mặt', value: s.coMat, color: '#0d9488' },
            { label: 'Đi trễ', value: s.diTre, color: '#f59e0b' },
            { label: 'Vắng có phép', value: s.vangCoPhep, color: '#3b82f6' },
            { label: 'Vắng không phép', value: s.vangKhongPhep, color: '#e11d48' },
            { label: 'Chưa điểm danh', value: s.chuaDiemDanh, color: '#94a3b8' },
            { label: 'Chưa diễn ra', value: s.chuaDienRa, color: '#cbd5e1' },
          ], { centerValue: fmtRate(s.tyLeChuyenCan), centerLabel: 'Chuyên cần' }),
        },
        table: {
          title: 'Tổng hợp theo lớp', meta: `${data.byClass.length} lớp · bấm vào lớp để xem từng học sinh`,
          columns: [
            { label: 'Lớp học', render: r => twoLine(r.maLop, r.tenLop) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Đã ĐD', num: true, render: r => `${r.daDiemDanh}/${r.tongLuot}` },
            { label: 'Vắng', num: true, render: r => String(r.vangCoPhep + r.vangKhongPhep) },
            { label: 'Chuyên cần', render: r => rateBar(r.tyLeChuyenCan) },
          ],
          rows: data.byClass,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(r.maLop)}"`,
          empty: 'Không có dữ liệu chuyên cần trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết theo lớp & học viên',
          groups: buildAttendanceClassGroups(data.details),
          printColumns: [
            { label: 'Ngày', render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
            { label: 'Lớp', render: r => Admin.esc(r.maLop) },
            { label: 'Học viên', render: r => twoLine(r.hoTen, r.maHv) },
            { label: 'Điểm danh', render: r => tagOf(ATT_TAG, r.trangThaiDiemDanh) },
            { label: 'Điểm BT', num: true, render: r => r.diemBaiTap != null ? Admin.esc(r.diemBaiTap) : '—' },
          ],
          rows: data.details,
          empty: 'Chưa có lượt điểm danh phù hợp.',
        },
      };
    },

    revenue(data) {
      const s = data.summary;
      const revDonut = donutChart([
        { label: 'Thực thu ròng', value: Math.max(0, Number(s.thucThuRong || 0)), color: '#0d9488' },
        { label: 'Còn nợ trong kỳ', value: Math.max(0, Number(s.conNo || 0)), color: '#f59e0b' },
        { label: 'Hoàn học phí', value: Math.max(0, Number(s.tongHoan || 0)), color: '#e11d48' },
      ], { centerValue: fmtCompactMoney(s.thucThuRong), centerLabel: 'Thực thu', fmt: v => fmtCompactMoney(v) });
      const cols = [
        { label: 'Học viên · HĐ', render: r => twoLine(r.tenHocVien, r.maHd) },
        { label: 'Ngày thu', render: r => twoLine(fmtDate(r.ngayThanhToan), `PT-${r.phieuId}`) },
        { label: 'Phương thức', render: r => `${Admin.esc(PAY_METHODS[r.phuongThuc] || r.phuongThuc)}${r.loai === 'hoan' ? ' ' + Admin.tag('Hoàn', 'r') : ''}` },
        { label: 'Số tiền', num: true, render: r => `<b>${Admin.money(r.loai === 'thu' ? r.soTien : -r.soTien)}</b>` },
      ];
      return {
        kpis: [
          { l: 'Thực thu ròng', v: Admin.money(s.thucThuRong), sub: `${s.soPhieuXacNhan} phiếu · hoàn ${Admin.money(s.tongHoan)}`, primary: true },
          { l: 'Phải thu trong kỳ', v: Admin.money(s.phaiThu), sub: `${s.soHoaDon} hóa đơn phát hành`, tone: 'i' },
          { l: 'Công nợ tồn', v: Admin.money(s.tongCongNoTonHienHanh || 0), sub: `${s.soHoaDonNoTonHienHanh || 0} HĐ chưa thu đủ`, tone: Number(s.tongCongNoTonHienHanh || 0) > 0 ? 'r' : 'g' },
          { l: 'Chờ đối soát', v: Admin.money(s.tienChoDoiSoat), sub: `${s.giaoDichChoDoiSoat} GD online`, tone: 'w' },
        ],
        overview: {
          title: `Biểu đồ doanh thu theo ${data.trendGranularity === 'day' ? 'ngày' : 'tháng'}`,
          sub: 'Thực thu ròng & cơ cấu thu trong kỳ',
          html: `<div class="rpt-rev-grid"><div>${renderTrendSvgChart(data.trend || [], data.trendGranularity)}</div><div class="rpt-rev-donut">${revDonut}</div></div>`,
        },
        table: {
          title: 'Doanh thu theo khóa học', meta: `${data.byGroup.length} nhóm`,
          columns: [
            { label: 'Khóa học', render: r => twoLine(r.tenKhoa, r.maKhoa) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Phải thu', num: true, render: r => Admin.money(r.phaiThu) },
            { label: 'Thực thu', num: true, render: r => `<b>${Admin.money(r.thucThuRong)}</b>` },
            { label: 'Còn nợ', num: true, render: r => Admin.money(r.conNo) },
          ],
          rows: data.byGroup,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(`${r.tenKhoa || ''}__${r.tenCoSo || ''}`)}"`,
          empty: 'Không có hóa đơn hoặc phiếu thu trong khoảng lọc.',
        },
        detail: {
          title: 'Phiếu thu theo khóa học & học viên',
          groups: buildGenericGroups(data.details, {
            groupKey: r => `${r.tenKhoa || 'Khác'}__${r.tenCoSo || ''}`,
            groupTitle: r => r.tenKhoa || 'Hóa đơn lịch sử',
            groupSub: r => r.tenCoSo || '',
            groupBadges: list => {
              const net = list.reduce((a, x) => a + (x.loai === 'thu' ? Number(x.soTien || 0) : -Number(x.soTien || 0)), 0);
              return Admin.tag(`${list.length} phiếu`, 'i') + Admin.tag(Admin.money(net), 'ok');
            },
            columns: cols,
            itemSearch: r => `${r.tenHocVien} ${r.maHd} ${r.tenKhoa} ${r.tenCoSo} ${PAY_METHODS[r.phuongThuc] || ''}`,
            empty: 'Chưa có phiếu thu.',
          }),
          printColumns: cols,
          rows: data.details,
          empty: 'Chưa có phiếu thu hợp lệ trong khoảng lọc.',
        },
      };
    },

    'class-performance'(data) {
      const s = data.summary;
      const cols = [
        { label: 'Ngày · Giờ', render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
        { label: 'Trạng thái', render: r => tagOf(SES_ST_TAG, r.trangThaiBuoi) + (r.loaiBuoi === 'bu' ? ' ' + Admin.tag('Học bù', 'purple') : '') },
        { label: 'Có mặt / Sĩ số', num: true, render: r => `${r.soCoMatHoacTre}/${r.soLuotDiemDanh}` },
      ];
      return {
        kpis: [
          { l: 'Lấp đầy sĩ số', v: fmtRate(s.tyLeLapDayTrungBinh), sub: `${s.tongSiSoDangHoc}/${s.tongSucChua} chỗ · ${s.soLop} lớp`, primary: true },
          { l: 'Chuyên cần', v: fmtRate(s.tyLeChuyenCan), sub: 'Không tính buổi hủy/đổi', tone: 'g' },
          { l: 'Buổi đã dạy', v: `${s.soBuoiDaDienRa}/${s.tongBuoiTrongKy}`, sub: `Sắp diễn ra ${s.soBuoiSapDienRa || 0} buổi`, tone: 'i' },
          { l: 'Buổi bị hủy', v: fmtNum(s.soBuoiBiHuy), sub: `Đổi ${s.soBuoiDoiLich || 0} · Bù ${s.soBuoiHocBu}`, tone: s.soBuoiBiHuy > 0 ? 'r' : 'w' },
        ],
        overview: {
          title: 'Biểu đồ trạng thái buổi học',
          sub: `${s.tongBuoiTrongKy} buổi trong kỳ`,
          html: donutChart([
            { label: 'Đã dạy', value: s.soBuoiDaDienRa, color: '#0d9488' },
            { label: 'Đang dạy', value: s.soBuoiDangDay || 0, color: '#f59e0b' },
            { label: 'Sắp diễn ra', value: s.soBuoiSapDienRa || 0, color: '#3b82f6' },
            { label: 'Đổi lịch', value: s.soBuoiDoiLich || 0, color: '#8b5cf6' },
            { label: 'Bị hủy', value: s.soBuoiBiHuy, color: '#e11d48' },
          ], { centerValue: fmtNum(s.tongBuoiTrongKy), centerLabel: 'Tổng buổi' }),
        },
        table: {
          title: 'Chỉ số theo lớp', meta: `${data.classes.length} lớp · bấm vào lớp để xem các buổi`,
          columns: [
            { label: 'Lớp học', render: r => twoLine(r.maLop, `${r.tenMon} · ${r.tenCapLop}`) },
            { label: 'Người dạy', key: 'nguoiDayChinh' },
            { label: 'Sĩ số', render: r => rateBar(r.tyLeLapDay, { text: `${r.siSoDangHoc}/${r.siSoToiDa}`, color: 'var(--pri)' }) },
            { label: 'Đã dạy', num: true, render: r => `${r.soBuoiDaDienRa}/${r.tongBuoiTrongKy}` },
            { label: 'Chuyên cần', render: r => rateBar(r.tyLeChuyenCan) },
          ],
          rows: data.classes,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(r.maLop)}"`,
          empty: 'Không có lớp học phù hợp bộ lọc.',
        },
        detail: {
          title: 'Chi tiết buổi học theo lớp',
          groups: buildGenericGroups(data.details, {
            groupKey: r => r.maLop,
            groupTitle: r => r.maLop,
            groupSub: r => r.tenLop || '',
            groupBadges: list => {
              const taught = list.filter(x => x.trangThaiBuoi === 'da_day').length;
              return Admin.tag(`${list.length} buổi`, 'i') + Admin.tag(`${taught} đã dạy`, 'ok');
            },
            columns: cols,
            itemSearch: r => `${r.maLop} ${r.tenLop} ${fmtDate(r.ngay)} ${SES_ST_TAG[r.trangThaiBuoi]?.[0] || ''}`,
            empty: 'Chưa có buổi học.',
          }),
          printColumns: [{ label: 'Lớp', render: r => Admin.esc(r.maLop) }, ...cols],
          rows: data.details,
          empty: 'Chưa có buổi học trong khoảng lọc.',
        },
      };
    },

    payroll(data) {
      const s = data.summary;
      const roles = (data.byRole || []).filter(r => r.soCa > 0)
        .map(r => `<span class="rpt-chip">${Admin.esc(r.tenNhom)} <b>${Admin.esc(Admin.money(r.tienCongCa))}</b><small>${r.soCa} ca · ${r.tongGio}g</small></span>`).join('');
      const cols = [
        { label: 'Ngày · Giờ', render: r => twoLine(fmtDate(r.ngay), timeRange(r)) },
        { label: 'Lớp · Cơ sở', render: r => twoLine(r.maLop, r.tenCoSo) },
        { label: 'Phút công', num: true, render: r => `${r.soPhutTinhCong}p${Number(r.soPhutTre) > 0 ? ` <small class="rpt-warn">(trễ ${r.soPhutTre}p)</small>` : ''}` },
        { label: 'Tiền công', num: true, render: r => `<b>${Admin.money(r.tienCong)}</b>` },
      ];
      return {
        kpis: [
          { l: 'Quỹ lương dạy', v: Admin.money(s.tongTienCongCa), sub: `${s.soCa} ca · ${s.soNhanSu} nhân sự`, primary: true },
          { l: 'Giờ công', v: `${s.tongGio} giờ`, sub: `Đi trễ ${s.soLanTre} lần (${s.soPhutTre}p)`, tone: 'i' },
          { l: 'Đã chi trả', v: Admin.money(s.tienDaTra), sub: `Chốt ${Admin.money(s.tienDaChot)} · Mở ${Admin.money(s.tienMo)}`, tone: 'g' },
          { l: 'Thu − Lương', v: Admin.money(s.chenhLechThuLuong), sub: `Thực thu ${Admin.money(s.thucThuRong)}`, tone: s.chenhLechThuLuong >= 0 ? 'g' : 'r' },
        ],
        overview: {
          title: 'Biểu đồ quyết toán lương',
          sub: 'Phân bổ theo trạng thái bảng công',
          html: donutChart([
            { label: 'Đã chi trả', value: s.tienDaTra, color: '#0d9488' },
            { label: 'Đã chốt', value: s.tienDaChot, color: '#3b82f6' },
            { label: 'Tạm tính (Mở)', value: s.tienMo, color: '#f59e0b' },
          ], { centerValue: fmtCompactMoney(s.tongTienCongCa), centerLabel: 'Quỹ lương', fmt: v => fmtCompactMoney(v), extraHtml: roles ? `<div class="rpt-chips">${roles}</div>` : '' }),
        },
        table: {
          title: 'Công dạy theo nhân sự', meta: `${data.byPerson.length} nhân sự · bấm để xem từng ca`,
          columns: [
            { label: 'Nhân sự', render: r => twoLine(r.hoTen, r.maNs) },
            { label: 'Nhóm', render: r => tagOf(ROLE_GROUP_TAG, r.nhomNhanSu) },
            { label: 'Ca', num: true, render: r => String(r.soCa) },
            { label: 'Giờ', num: true, render: r => `${r.tongGio}` },
            { label: 'Tiền công', num: true, render: r => `<b>${Admin.money(r.tienCongCa)}</b>` },
            { label: 'Kỳ công', render: r => tagOf(PAYROLL_ST_TAG, r.trangThaiKy) },
          ],
          rows: data.byPerson,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(r.maNs || r.hoTen)}"`,
          empty: 'Chưa có ca dạy hợp lệ trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết ca dạy theo nhân sự',
          groups: buildGenericGroups(data.details, {
            groupKey: r => r.maNs || r.hoTen,
            groupTitle: r => r.hoTen,
            groupSub: r => `${r.maNs || ''} · ${ROLE_GROUP_TAG[r.nhomNhanSu]?.[0] || ''}`,
            groupBadges: list => {
              const pay = list.reduce((a, x) => a + Number(x.tienCong || 0), 0);
              const mins = list.reduce((a, x) => a + Number(x.soPhutTinhCong || 0), 0);
              return Admin.tag(`${list.length} ca · ${fmtHours(mins)}`, 'i') + Admin.tag(Admin.money(pay), 'ok');
            },
            columns: cols,
            itemSearch: r => `${r.hoTen} ${r.maNs} ${r.maLop} ${r.tenCoSo}`,
            empty: 'Chưa có ca dạy.',
          }),
          printColumns: [{ label: 'Nhân sự', render: r => twoLine(r.hoTen, r.maNs) }, ...cols],
          rows: data.details,
          empty: 'Chưa có ca dạy hợp lệ.',
        },
      };
    },

    debt(data) {
      const s = data.summary;
      const cols = [
        { label: 'Học viên · Phụ huynh', render: r => twoLine(r.tenHocVien, [r.tenPhuHuynh, r.sdtPhuHuynh].filter(v => v && v !== '—').join(' · ')) },
        { label: 'Hóa đơn', render: r => twoLine(r.maHd, `PH ${fmtDate(r.ngayPhatHanh)}`) },
        { label: 'Hạn thanh toán', render: r => `${fmtDate(r.hanThanhToan)} ${r.soNgayQuaHan > 0 ? Admin.tag(`Quá ${r.soNgayQuaHan} ngày`, 'r') : tagOf(DEBT_AGING_TAG, r.nhomHan)}` },
        { label: 'Còn nợ', num: true, render: r => `<b>${Admin.money(r.conNo)}</b>` },
      ];
      return {
        kpis: [
          { l: 'Tổng còn nợ', v: Admin.money(s.tongConNo), sub: `${s.soHoaDonNo} hóa đơn chưa thu đủ`, primary: true },
          { l: 'Quá hạn', v: Admin.money(s.tienQuaHan), sub: `${s.soHoaDonQuaHan} hóa đơn`, tone: s.soHoaDonQuaHan > 0 ? 'r' : 'g' },
          { l: 'Chưa đến hạn', v: Admin.money(s.tienChuaDenHan), sub: `${s.soHoaDonChuaDenHan} hóa đơn`, tone: 'w' },
          { l: 'Chưa đặt hạn', v: Admin.money(s.tienKhongCoHan), sub: `${s.soHoaDonKhongCoHan} hóa đơn`, tone: 'i' },
        ],
        overview: {
          title: 'Biểu đồ công nợ theo hạn thanh toán',
          sub: 'Tính tại thời điểm hiện tại',
          html: donutChart([
            { label: 'Quá hạn', value: s.tienQuaHan, color: '#e11d48' },
            { label: 'Chưa đến hạn', value: s.tienChuaDenHan, color: '#f59e0b' },
            { label: 'Chưa đặt hạn', value: s.tienKhongCoHan, color: '#94a3b8' },
          ], { centerValue: fmtCompactMoney(s.tongConNo), centerLabel: 'Còn nợ', fmt: v => fmtCompactMoney(v) }),
        },
        table: {
          title: 'Công nợ theo khóa học', meta: `${data.byGroup.length} nhóm`,
          columns: [
            { label: 'Khóa học', render: r => twoLine(r.tenKhoa, r.maKhoa) },
            { label: 'Cơ sở', key: 'tenCoSo' },
            { label: 'Số HĐ', num: true, render: r => String(r.soHoaDonNo) },
            { label: 'Quá hạn', num: true, render: r => r.tienQuaHan > 0 ? `<span class="rpt-danger">${Admin.money(r.tienQuaHan)}</span>` : '—' },
            { label: 'Còn nợ', num: true, render: r => `<b>${Admin.money(r.tongConNo)}</b>` },
          ],
          rows: data.byGroup,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(`${r.tenKhoa || ''}__${r.tenCoSo || ''}`)}"`,
          empty: 'Hiện không có hóa đơn nào còn nợ.',
        },
        detail: {
          title: 'Học viên còn nợ theo khóa học',
          groups: buildGenericGroups(data.details, {
            groupKey: r => `${r.tenKhoa || 'Khác'}__${r.tenCoSo || ''}`,
            groupTitle: r => r.tenKhoa || 'Hóa đơn lịch sử',
            groupSub: r => r.tenCoSo || '',
            groupBadges: list => {
              const sum = list.reduce((a, x) => a + Number(x.conNo || 0), 0);
              return Admin.tag(`${list.length} HĐ`, 'w') + Admin.tag(Admin.money(sum), 'r');
            },
            columns: cols,
            itemSearch: r => `${r.tenHocVien} ${r.tenPhuHuynh} ${r.sdtPhuHuynh} ${r.maHd} ${r.tenKhoa}`,
            empty: 'Không có hóa đơn còn nợ.',
          }),
          printColumns: cols,
          rows: data.details,
          empty: 'Không có hóa đơn còn nợ.',
        },
      };
    },

    admissions(data) {
      const s = data.summary;
      const inProgress = s.moi + s.dangTim + s.daDeXuat;
      const sources = (data.bySource || [])
        .map(r => `<span class="rpt-chip">${Admin.esc(ADM_SOURCE_LABEL[r.nguon] || r.nguon)} <b>${r.tong}</b><small>chốt ${fmtRate(r.tyLeChot)}</small></span>`).join('');
      const cols = [
        { label: 'Khách hàng · SĐT', render: r => twoLine(r.khachHang, r.sdt) },
        { label: 'Mã YC · Ngày', render: r => twoLine(String(r.maYc || '').slice(0, 11), fmtDate(r.createdAt)) },
        { label: 'Khối lớp', key: 'tenCapLop' },
        { label: 'Nguồn', render: r => Admin.esc(ADM_SOURCE_LABEL[r.nguon] || r.nguon) },
        { label: 'Trạng thái', render: r => tagOf(ADM_ST_TAG, r.trangThai) },
      ];
      return {
        kpis: [
          { l: 'Tỷ lệ chốt', v: fmtRate(s.tyLeChot), sub: `${s.daChot}/${s.tongYeuCau} YC · mở ${s.daMoLop} lớp`, primary: true },
          { l: 'Tổng yêu cầu', v: fmtNum(s.tongYeuCau), sub: 'Tạo mới trong kỳ', tone: 'i' },
          { l: 'Đang xử lý', v: fmtNum(inProgress), sub: `Mới ${s.moi} · Tìm ${s.dangTim} · ĐX ${s.daDeXuat}`, tone: 'w' },
          { l: 'Đã hủy', v: fmtNum(s.huy), sub: 'Trạng thái hiện tại', tone: s.huy > 0 ? 'r' : 'g' },
        ],
        overview: {
          title: 'Biểu đồ trạng thái yêu cầu học',
          sub: `${s.tongYeuCau} yêu cầu`,
          html: donutChart([
            { label: 'Đã chốt', value: s.daChot, color: '#0d9488' },
            { label: 'Đã đề xuất', value: s.daDeXuat, color: '#8b5cf6' },
            { label: 'Đang tìm', value: s.dangTim, color: '#f59e0b' },
            { label: 'Mới', value: s.moi, color: '#3b82f6' },
            { label: 'Đã hủy', value: s.huy, color: '#e11d48' },
          ], { centerValue: fmtRate(s.tyLeChot), centerLabel: 'Tỷ lệ chốt', extraHtml: sources ? `<div class="rpt-chips">${sources}</div>` : '' }),
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
          rows: data.byGroup,
          rowAttr: r => `class="rpt-row-jump" data-jump-group="${Admin.esc(`${r.tenMon || ''}__${r.tenCoSo || ''}`)}"`,
          empty: 'Chưa có yêu cầu học trong khoảng lọc.',
        },
        detail: {
          title: 'Chi tiết yêu cầu theo môn học',
          groups: buildGenericGroups(data.details, {
            groupKey: r => `${r.tenMon || 'Khác'}__${r.tenCoSo || ''}`,
            groupTitle: r => r.tenMon || 'Chưa rõ môn',
            groupSub: r => r.tenCoSo || '',
            groupBadges: list => {
              const won = list.filter(x => x.trangThai === 'da_chot').length;
              return Admin.tag(`${list.length} YC`, 'i') + Admin.tag(`${won} chốt`, 'ok');
            },
            columns: cols,
            itemSearch: r => `${r.maYc} ${r.khachHang} ${r.sdt} ${r.tenMon} ${r.tenCapLop}`,
            empty: 'Chưa có yêu cầu học.',
          }),
          printColumns: cols,
          rows: data.details,
          empty: 'Chưa có yêu cầu học phù hợp.',
        },
      };
    },
  };

  // ── Markup: single-row tabs + right compact filter popover ──
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
        <div class="rpt-bar-top">
          <div class="rpt-tabs" id="rptTabs" role="tablist"></div>
          <div class="rpt-bar-right">
            <span id="rptScopeBadge" class="rpt-scope" hidden></span>
            <div class="rpt-filter-wrap" id="rptFilterWrap">
              <button type="button" class="btn sm rpt-filter-btn" id="rptMoreBtn" aria-haspopup="true" aria-expanded="false">
                <svg class="ui-ico sm" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <span>Bộ lọc</span>
                <span class="rpt-period-chip" id="rptPeriodChip">Tháng này</span>
                <span class="rpt-count" id="rptFilterCount" hidden></span>
              </button>
              <form id="rptFilterForm" class="rpt-filter-pop" autocomplete="off" hidden>
                <div class="rpt-pop-block" id="rptDateBlock">
                  <div class="rpt-pop-title">Khoảng thời gian</div>
                  <div class="rpt-presets" id="rptQuickDates">${PRESETS.map(([k, l]) => `<button type="button" class="rpt-qbtn" data-preset="${k}">${l}</button>`).join('')}</div>
                  <div class="rpt-dates" id="rptDates">
                    <input type="date" name="tuNgay" aria-label="Từ ngày">
                    <span>→</span>
                    <input type="date" name="denNgay" aria-label="Đến ngày">
                  </div>
                  <span class="rpt-debt-note" id="rptDebtNote" hidden>Công nợ tính tại thời điểm hiện tại, không lọc theo ngày.</span>
                </div>
                <div class="rpt-pop-block">
                  <div class="rpt-pop-title">Tiêu chí lọc</div>
                  <div class="rpt-more" id="rptMorePanel">
                    <label id="rptWrapCampus">Cơ sở<select name="coSoId"></select></label>
                    <label id="rptWrapCourse">Khóa học<select name="khoaHocId"></select></label>
                    <label id="rptWrapClass">Lớp học<select name="lopId"></select></label>
                    <label id="rptWrapSubject">Môn học<select name="monHocId"></select></label>
                    <label id="rptWrapRoleGroup">Nhóm nhân sự<select name="nhomNhanSu"><option value="">Tất cả nhóm</option><option value="giao_vien">Giáo viên</option><option value="gia_su">Gia sư</option><option value="tro_giang">Trợ giảng</option></select></label>
                    <label id="rptWrapStatus">Trạng thái<select name="trangThai"></select></label>
                  </div>
                </div>
                <div class="rpt-pop-foot">
                  <button type="button" class="rpt-link" id="rptResetBtn">Đặt lại mặc định</button>
                  <button type="button" class="btn p sm" id="rptApplyCloseBtn">Xong</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div id="rptStatusBanner" class="rpt-banner" hidden></div>
      <div class="rpt-kpis" id="rptStats"></div>

      <div class="card rpt-section" id="rptOverviewCard">
        <div class="rpt-section-head"><h2 id="rptOverviewTitle">Biểu đồ phân bổ</h2><span id="rptOverviewSub"></span></div>
        <div id="rptOverviewBody"></div>
        <details class="rpt-how" id="rptFormulaBox"><summary>Cách tính số liệu</summary><ul id="rptFormulaList"></ul></details>
      </div>

      <div class="card rpt-section" id="rptSummaryCard">
        <div class="rpt-section-head"><h2 id="rptSummaryTitle">Tổng hợp</h2><span id="rptSummaryMeta"></span></div>
        <div id="rptSummaryTable"></div>
      </div>

      <div class="card rpt-section" id="rptDetailCard">
        <div class="rpt-detail-head">
          <div>
            <h2 id="rptDetailTitle" style="margin:0">Chi tiết theo nhóm</h2>
            <span id="rptDetailMeta" style="font-size:12px;color:var(--g500)"></span>
          </div>
          <div class="rpt-detail-tools">
            <input type="search" id="rptDetailSearch" class="rpt-search-input" placeholder="Tìm lớp, học viên, mã HĐ…">
            <button type="button" class="btn sm" id="rptExpandAllBtn">Mở tất cả</button>
          </div>
        </div>
        <div id="rptDetailGroups" class="rpt-groups"></div>
      </div>
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

  function syncFilterButtonBadge() {
    const scoped = !!rptState.meta?.scopedCampusId;
    const n = ADV_FIELDS.filter(f => fieldVisible(f) && rptState.filters[f] && !(f === 'coSoId' && scoped)).length;
    const el = Admin.$('rptFilterCount');
    if (el) { el.hidden = n === 0; el.textContent = String(n); }
    const chip = Admin.$('rptPeriodChip');
    if (chip) {
      if (rptState.tab === 'debt') {
        chip.textContent = 'Hiện tại';
      } else if (rptState.preset && PRESET_LABELS[rptState.preset]) {
        chip.textContent = PRESET_LABELS[rptState.preset];
      } else {
        chip.textContent = `${fmtShortDate(rptState.filters.tuNgay)}–${fmtShortDate(rptState.filters.denNgay)}`;
      }
    }
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
    syncFilterButtonBadge();
  }

  function syncPresetButtons() {
    document.querySelectorAll('#rptQuickDates [data-preset]').forEach(b => b.classList.toggle('on', b.dataset.preset === rptState.preset));
    syncFilterButtonBadge();
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
      if (badge && rptState.meta.campuses[0]) { badge.hidden = false; badge.textContent = rptState.meta.campuses[0].tenCoSo; }
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

  // ── Render Grouped Details ──
  function renderGroupedDetails() {
    const box = Admin.$('rptDetailGroups');
    const view = rptState.lastView;
    if (!box || !view) return;
    const q = rptState.detailQuery.trim().toLowerCase();
    const groups = view.detail.groups || [];
    const filtered = [];
    for (const g of groups) {
      const items = q ? g.items.filter(it => g.filterItem(it, q)) : g.items;
      if (items.length) filtered.push({ ...g, items });
    }
    if (!filtered.length) {
      box.innerHTML = `<div class="rpt-empty">${Admin.esc(view.detail.empty)}</div>`;
      return;
    }
    box.innerHTML = filtered.map((g, idx) => {
      const shouldOpen = rptState.allExpanded || Boolean(q) || idx === 0;
      return `<details class="rpt-group" data-group-key="${Admin.esc(g.key)}" ${shouldOpen ? 'open' : ''}>
        <summary>
          <div class="rpt-group-title">
            <span class="rpt-group-chev">▸</span>
            <div><b>${Admin.esc(g.title)}</b>${g.sub ? `<small>${Admin.esc(g.sub)}</small>` : ''}</div>
          </div>
          <div class="rpt-group-meta">${g.badges}</div>
        </summary>
        <div class="rpt-group-body">${g.renderItems(g.items)}</div>
      </details>`;
    }).join('');

    box.querySelectorAll('[data-toggle-stu]').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = Admin.$(btn.dataset.toggleStu);
        if (!tr) return;
        tr.hidden = !tr.hidden;
        btn.classList.toggle('p', !tr.hidden);
      });
    });
  }

  function setBanner(html, isError = false) {
    const box = Admin.$('rptStatusBanner');
    if (!box) return;
    box.hidden = !html;
    box.className = `rpt-banner ${isError ? 'error' : ''}`;
    box.innerHTML = html || '';
  }

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
      Admin.$('rptDetailGroups').innerHTML = '';
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
    Admin.$('rptSummaryTable').innerHTML = simpleTable(view.table.columns, view.table.rows, view.table.empty, view.table.rowAttr);
    Admin.$('rptSummaryTable').querySelectorAll('[data-jump-group]').forEach(tr => {
      tr.addEventListener('click', () => {
        const key = tr.dataset.jumpGroup;
        const target = document.querySelector(`#rptDetailGroups details[data-group-key="${CSS.escape(key)}"]`);
        if (target) {
          target.open = true;
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });

    rptState.detailQuery = '';
    rptState.allExpanded = false;
    if (Admin.$('rptDetailSearch')) Admin.$('rptDetailSearch').value = '';
    if (Admin.$('rptExpandAllBtn')) Admin.$('rptExpandAllBtn').textContent = 'Mở tất cả';
    Admin.$('rptDetailTitle').textContent = view.detail.title;
    const totalRows = (view.detail.rows || []).length;
    const groupCount = (view.detail.groups || []).length;
    Admin.$('rptDetailMeta').textContent = `${groupCount} nhóm · ${totalRows} bản ghi`;
    renderGroupedDetails();
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

    const rows = view.detail.rows || [];
    const maxRows = 150;
    const detailHtml = simpleTable(view.detail.printColumns, rows.slice(0, maxRows), view.detail.empty)
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

        const filterBtn = Admin.$('rptMoreBtn');
        const filterPop = Admin.$('rptFilterForm');
        const setFilterOpen = open => {
          filterPop.hidden = !open;
          filterBtn.setAttribute('aria-expanded', String(open));
          filterBtn.classList.toggle('on', open);
        };
        filterBtn.addEventListener('click', e => { e.stopPropagation(); setFilterOpen(filterPop.hidden); });
        Admin.$('rptApplyCloseBtn').addEventListener('click', () => setFilterOpen(false));
        document.addEventListener('click', ev => { if (!Admin.$('rptFilterWrap')?.contains(ev.target)) setFilterOpen(false); });

        tabsEl.querySelectorAll('[data-rpt-tab]').forEach(btn => btn.addEventListener('click', () => {
          if (rptState.tab === btn.dataset.rptTab) return;
          rptState.tab = btn.dataset.rptTab;
          tabsEl.querySelectorAll('[data-rpt-tab]').forEach(b => { const on = b === btn; b.classList.toggle('on', on); b.setAttribute('aria-selected', String(on)); });
          syncStatusDropdown();
          syncTabVisibility();
          loadReport();
        }));

        filterPop.addEventListener('submit', e => e.preventDefault());

        document.querySelectorAll('#rptQuickDates [data-preset]').forEach(btn => btn.addEventListener('click', () => {
          rptState.preset = btn.dataset.preset;
          Object.assign(rptState.filters, dateRangePreset(rptState.preset));
          filterPop.elements.tuNgay.value = rptState.filters.tuNgay;
          filterPop.elements.denNgay.value = rptState.filters.denNgay;
          syncPresetButtons();
          loadReport();
        }));

        ['tuNgay', 'denNgay'].forEach(name => filterPop.elements[name].addEventListener('change', () => {
          rptState.filters[name] = filterPop.elements[name].value;
          rptState.preset = '';
          syncPresetButtons();
          scheduleLoad(350);
        }));

        ADV_FIELDS.forEach(name => filterPop.elements[name].addEventListener('change', () => {
          if (name === 'coSoId' && rptState.meta.scopedCampusId) return;
          rptState.filters[name] = filterPop.elements[name].value;
          if (name === 'coSoId' || name === 'khoaHocId') syncClassOptions();
          syncFilterButtonBadge();
          scheduleLoad();
        }));

        Admin.$('rptResetBtn').addEventListener('click', () => {
          rptState.preset = 'this-month';
          rptState.filters = { ...dateRangePreset('this-month'), coSoId: rptState.meta.scopedCampusId ? String(rptState.meta.scopedCampusId) : '', khoaHocId: '', lopId: '', monHocId: '', nhomNhanSu: '', trangThai: '' };
          syncFilterControls();
          loadReport();
        });

        Admin.$('rptDetailSearch').addEventListener('input', e => {
          rptState.detailQuery = String(e.target.value || '');
          renderGroupedDetails();
        });
        Admin.$('rptExpandAllBtn').addEventListener('click', e => {
          rptState.allExpanded = !rptState.allExpanded;
          e.currentTarget.textContent = rptState.allExpanded ? 'Thu gọn' : 'Mở tất cả';
          Admin.$('rptDetailGroups').querySelectorAll('details.rpt-group').forEach(d => { d.open = rptState.allExpanded; });
        });

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
