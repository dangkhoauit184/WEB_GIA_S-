/* Administration shell. Feature modules register their own screens below. */
'use strict';
window.Admin = (() => {
  const screens = {};
  let token = null;
  let user = null;
  let renderVersion = 0;
  let toastTimer;
  let period = new Date().toLocaleDateString('sv-SE').slice(0, 7);
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
  const money = value => new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND', maximumFractionDigits:0 }).format(Number(value || 0));
  const date = value => value ? String(value).replace('T', ' ').slice(0, 19) : '—';
  const query = values => new URLSearchParams(Object.entries(values).filter(([,value]) => value !== '' && value != null)).toString();

  function notify(message, error = false) {
    clearTimeout(toastTimer);
    $('admin-toast').textContent = message;
    $('admin-toast').classList.toggle('error', error);
    $('admin-toast').hidden = false;
    toastTimer = setTimeout(() => { $('admin-toast').hidden = true; }, 5500);
  }

  function reset() {
    token = null;
    user = null;
    renderVersion++;
    $('admin-workspace').hidden = true;
    $('admin-login').hidden = false;
    $('admin-content').replaceChildren();
    document.querySelectorAll('.modal-ov.show').forEach(modal => modal.classList.remove('show'));
    if ($('admin-dialog').open) $('admin-dialog').close();
  }

  async function api(path, method = 'GET', body) {
    let response;
    const started = performance.now();
    if ($('apiMethod')) $('apiMethod').textContent = method;
    if ($('apiUri')) $('apiUri').textContent = path;
    try {
      response = await fetch(path, {
        method,
        headers: { 'Content-Type':'application/json', ...(token ? { Authorization:`Bearer ${token}` } : {}) },
        ...(body === undefined ? {} : { body:JSON.stringify(body) })
      });
    } catch {
      throw new Error('Không kết nối được máy chủ. Vui lòng kiểm tra kết nối rồi thử lại.');
    }
    const result = await response.json().catch(() => null);
    if ($('apiStatus')) $('apiStatus').textContent = `${response.status} · ${Math.round(performance.now() - started)}ms · EduCenter ERP Core`;
    if (response.status === 401) {
      reset();
      $('login-error').textContent = 'Phiên đã hết hạn. Vui lòng đăng nhập lại.';
    }
    if (!response.ok || !result?.success) {
      const error = new Error(result?.message || 'Không xử lý được yêu cầu.');
      error.code = result?.code;
      throw error;
    }
    return result.data;
  }

  function heading(title, subtitle, actions = '') {
    return `<div class="pg-head"><div><h1>${esc(title)}</h1><p class="sub">${esc(subtitle)}</p></div><div class="row">${actions}</div></div>`;
  }

  function table(columns, rows, empty = 'Chưa có dữ liệu phù hợp.') {
    return `<div class="tw"><table><thead><tr>${columns.map(column => `<th>${esc(column.label)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map(row => `<tr>${columns.map(column => `<td>${column.render ? column.render(row) : esc(row[column.key] ?? '—')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${columns.length}" class="admin-empty">${esc(empty)}</td></tr>`}</tbody></table></div>`;
  }

  function tag(text, style = '') { return `<span class="tag ${esc(style)}">${esc(text)}</span>`; }
  function button(label, action, id, style = '') { return `<button type="button" class="btn sm ${esc(style)}" data-action="${esc(action)}" data-id="${esc(id)}">${esc(label)}</button>`; }
  function options(rows, valueKey = 'id', labelKey = 'ten', selected = '') {
    return rows.map(row => `<option value="${esc(row[valueKey])}" ${String(row[valueKey]) === String(selected) ? 'selected' : ''}>${esc(row[labelKey])}</option>`).join('');
  }
  function pager(data) {
    const pages = Math.max(1, Math.ceil(data.total / data.perPage));
    return `<div class="admin-pager"><span>${data.total} kết quả · Trang ${data.page}/${pages}</span><div class="row"><button class="btn sm" data-page="${data.page - 1}" ${data.page <= 1 ? 'disabled' : ''}>Trước</button><button class="btn sm" data-page="${data.page + 1}" ${data.page >= pages ? 'disabled' : ''}>Sau</button></div></div>`;
  }
  function bindPager(callback) {
    document.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => callback(Number(button.dataset.page))));
  }
  async function busy(button, work) {
    if (button.disabled) return;
    button.disabled = true;
    try { await work(); } catch (error) { notify(error.message, true); }
    finally { button.disabled = false; }
  }

  function closeDialog() {
    if ($('admin-dialog').dataset.busy !== 'true') $('admin-dialog').close();
  }

  function dialog(title, html) {
    const element = $('admin-dialog');
    $('dialog-content').innerHTML = `<div class="dialog-head"><h2 id="dialog-title">${esc(title)}</h2><button class="btn sm" id="dialog-close" type="button" aria-label="Đóng">✕</button></div>${html}`;
    element.dataset.busy = 'false';
    $('dialog-close').addEventListener('click', closeDialog);
    if (!element.open) element.showModal();
  }

  function formDialog({ title, fields, submit = 'Lưu thay đổi', onSubmit }) {
    const controls = fields.map(field => {
      const attrs = `name="${esc(field.name)}" ${field.required ? 'required' : ''} ${field.disabled ? 'disabled' : ''}`;
      let control;
      if (field.type === 'select') {
        control = `<select ${attrs}>${field.options.map(option => `<option value="${esc(option.value)}" ${String(option.value) === String(field.value ?? '') ? 'selected' : ''}>${esc(option.label)}</option>`).join('')}</select>`;
      } else if (field.type === 'textarea') {
        control = `<textarea ${attrs} rows="4" ${field.maxLength ? `maxlength="${field.maxLength}"` : ''}>${esc(field.value ?? '')}</textarea>`;
      } else {
        control = `<input ${attrs} type="${esc(field.type || 'text')}" value="${field.type === 'password' ? '' : esc(field.value ?? '')}" ${field.min != null ? `min="${field.min}"` : ''} ${field.max != null ? `max="${field.max}"` : ''} ${field.maxLength ? `maxlength="${field.maxLength}"` : ''} ${field.type === 'number' ? 'step="1"' : ''} ${field.type === 'password' ? 'autocomplete="new-password" minlength="6" maxlength="72"' : ''}>`;
      }
      return `<label>${esc(field.label)}${control}${field.help ? `<small>${esc(field.help)}</small>` : ''}</label>`;
    }).join('');
    dialog(title, `<form id="dialog-form"><div class="dialog-body">${controls}<p id="dialog-error" role="alert" class="form-error"></p></div><div class="dialog-foot"><button type="button" class="btn" id="dialog-cancel">Hủy</button><button class="btn p" id="dialog-submit">${esc(submit)}</button></div></form>`);
    $('dialog-cancel').addEventListener('click', closeDialog);
    $('dialog-form').addEventListener('submit', async event => {
      event.preventDefault();
      if ($('admin-dialog').dataset.busy === 'true') return;
      const data = Object.fromEntries(new FormData(event.target));
      for (const field of fields) {
        if (field.disabled) continue;
        if (field.type === 'number' && data[field.name] !== '') data[field.name] = Number(data[field.name]);
        if (field.nullable && data[field.name] === '') data[field.name] = null;
      }
      $('admin-dialog').dataset.busy = 'true';
      $('dialog-submit').disabled = true;
      $('dialog-error').textContent = '';
      try {
        await onSubmit(data);
        $('admin-dialog').close();
      } catch (error) {
        $('dialog-error').textContent = error.message;
      } finally {
        $('admin-dialog').dataset.busy = 'false';
        if ($('dialog-submit')) $('dialog-submit').disabled = false;
      }
    });
  }

  function confirmDialog(title, message, work, fields = []) {
    formDialog({ title, fields:[{ name:'summary', label:message, disabled:true }, ...fields], submit:'Xác nhận', onSubmit:work });
  }

  const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  let selectedDayNum = new Date().getDate();

  function formatPeriodLabel(ym) {
    const [y, m] = String(ym || period).split('-');
    const dayStr = String(selectedDayNum || 11).padStart(2, '0');
    return `${dayStr}/${String(m || '11').padStart(2, '0')}/${y || '2026'} (Kỳ ${String(m || '11').padStart(2, '0')}/${y || '2026'})`;
  }

  function shiftMonth(ym, delta) {
    const [y, m] = String(ym || period).split('-').map(Number);
    const d = new Date(y || 2026, (m || 1) - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  function mountPeriodPicker(inputId = 'payrollMonth') {
    const input = $(inputId);
    const wrap = document.querySelector(`[data-period-picker="${inputId}"]`);
    if (!input || !wrap) return;
    if (!input.value) input.value = period;
    let viewYm = input.value || period;
    let showMonthsGrid = false;

    const buildCalendarCells = (year, monthIdx) => {
      const firstDayDow = new Date(year, monthIdx, 1).getDay(); // 0 = Su .. 6 = Sa
      const daysInMonth = new Date(year, monthIdx + 1, 0).getDate();
      const daysInPrev = new Date(year, monthIdx, 0).getDate();
      const cells = [];
      for (let i = firstDayDow - 1; i >= 0; i--) {
        const d = daysInPrev - i;
        const pm = new Date(year, monthIdx - 1, d);
        cells.push({ day: d, ym: `${pm.getFullYear()}-${String(pm.getMonth() + 1).padStart(2, '0')}`, other: true });
      }
      const curYmStr = `${year}-${String(monthIdx + 1).padStart(2, '0')}`;
      for (let d = 1; d <= daysInMonth; d++) {
        cells.push({ day: d, ym: curYmStr, other: false });
      }
      let nextDay = 1;
      while (cells.length < 42) {
        const nm = new Date(year, monthIdx + 1, nextDay);
        cells.push({ day: nextDay++, ym: `${nm.getFullYear()}-${String(nm.getMonth() + 1).padStart(2, '0')}`, other: true });
      }
      return cells;
    };

    const renderPop = (open = false) => {
      const activeYm = input.value || period;
      const [vY, vM] = String(viewYm || activeYm).split('-').map(Number);
      const year = vY || 2026;
      const monthIdx = (vM || 11) - 1;
      const titleText = `${EN_MONTHS[monthIdx]} ${year}`;
      const cells = buildCalendarCells(year, monthIdx);
      const safeDay = Math.min(selectedDayNum, new Date(year, monthIdx + 1, 0).getDate());

      const bodyHtml = showMonthsGrid
        ? `<div class="period-months-grid">${EN_MONTHS.map((mName, idx) => {
            const mYm = `${year}-${String(idx + 1).padStart(2, '0')}`;
            return `<button type="button" class="period-m-cell ${mYm === activeYm ? 'on' : ''}" data-pick-ym="${mYm}">${mName.slice(0, 3)}</button>`;
          }).join('')}</div>`
        : `<div class="period-weekdays"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div>
           <div class="period-days">${cells.map(c => {
             const isOn = !c.other && c.ym === activeYm && c.day === safeDay;
             return `<button type="button" class="period-day ${c.other ? 'other' : ''} ${isOn ? 'on' : ''}" data-pick-day="${c.day}" data-pick-ym="${c.ym}">${c.day}</button>`;
           }).join('')}</div>`;

      wrap.innerHTML = `
        <button type="button" class="period-trigger" data-toggle-pop="1" aria-expanded="${open ? 'true' : 'false'}">
          <span>${esc(formatPeriodLabel(activeYm))}</span>
          <svg class="svg-ico sm" style="color:var(--g600)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </button>
        <div class="period-pop ${open ? 'open' : ''}">
          <div class="period-pop-head">
            <button type="button" class="period-month-title" data-toggle-grid="1">
              <span>${esc(titleText)}</span>
              <span style="font-size:10px">▾</span>
            </button>
            <div class="period-arrows">
              <button type="button" class="period-arr" data-nav-month="-1" title="Previous month" aria-label="Previous month">
                <svg class="svg-ico sm" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
              </button>
              <button type="button" class="period-arr" data-nav-month="1" title="Next month" aria-label="Next month">
                <svg class="svg-ico sm" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>
              </button>
            </div>
          </div>
          ${bodyHtml}
          <div class="period-pop-foot">
            <button type="button" class="period-foot-link" data-clear-date="1">Clear</button>
            <button type="button" class="period-foot-link" data-today-date="1">Today</button>
          </div>
        </div>
      `;

      wrap.querySelector('[data-toggle-pop]')?.addEventListener('click', ev => {
        ev.stopPropagation();
        const pop = wrap.querySelector('.period-pop');
        const nextOpen = !pop.classList.contains('open');
        viewYm = input.value || period;
        showMonthsGrid = false;
        renderPop(nextOpen);
      });
      wrap.querySelector('[data-toggle-grid]')?.addEventListener('click', ev => {
        ev.stopPropagation();
        showMonthsGrid = !showMonthsGrid;
        renderPop(true);
      });
      wrap.querySelectorAll('[data-nav-month]').forEach(btn => btn.addEventListener('click', ev => {
        ev.stopPropagation();
        const delta = Number(btn.dataset.navMonth);
        viewYm = showMonthsGrid ? shiftMonth(viewYm, delta * 12) : shiftMonth(viewYm, delta);
        renderPop(true);
      }));
      wrap.querySelectorAll('[data-pick-day]').forEach(btn => btn.addEventListener('click', ev => {
        ev.stopPropagation();
        selectedDayNum = Number(btn.dataset.pickDay) || 1;
        const nextYm = btn.dataset.pickYm;
        input.value = nextYm;
        viewYm = nextYm;
        renderPop(false);
        window.changePayrollMonth?.(nextYm);
      }));
      wrap.querySelectorAll('[data-pick-ym]').forEach(btn => btn.addEventListener('click', ev => {
        ev.stopPropagation();
        const nextYm = btn.dataset.pickYm;
        input.value = nextYm;
        viewYm = nextYm;
        showMonthsGrid = false;
        renderPop(false);
        window.changePayrollMonth?.(nextYm);
      }));
      wrap.querySelector('[data-today-date]')?.addEventListener('click', ev => {
        ev.stopPropagation();
        const now = new Date();
        selectedDayNum = now.getDate();
        const nowYm = now.toLocaleDateString('sv-SE').slice(0, 7);
        input.value = nowYm;
        viewYm = nowYm;
        renderPop(false);
        window.changePayrollMonth?.(nowYm);
      });
      wrap.querySelector('[data-clear-date]')?.addEventListener('click', ev => {
        ev.stopPropagation();
        renderPop(false);
      });
    };

    renderPop(false);
  }

  document.addEventListener('click', ev => {
    document.querySelectorAll('.period-pop.open').forEach(pop => {
      if (!pop.closest('.period-picker')?.contains(ev.target)) pop.classList.remove('open');
    });
    const np = $('notifPanel');
    const bell = $('bellBtn');
    if (np && np.classList.contains('open') && !np.contains(ev.target) && (!bell || !bell.contains(ev.target))) {
      np.classList.remove('open');
    }
  });

  function currentScreen() {
    const name = location.hash.replace(/^#\/?/, '').split('/').pop() || 'admin-dash';
    return { dashboard:'admin-dash', courses:'admin-courses', catalog:'admin-catalog', settings:'admin-catalog', permissions:'admin-perms', audit:'admin-audit' }[name] || name;
  }
  async function render() {
    if (!user) return;
    const name = currentScreen();
    const handler = screens[name];
    if (!handler) { location.hash = '/admin/admin-dash'; return; }
    const version = ++renderVersion;
    $('admin-content').innerHTML = '<div class="card admin-empty" role="status">Đang tải dữ liệu…</div>';
    document.querySelectorAll('[data-screen]').forEach(link => {
      link.classList.toggle('active', link.dataset.screen === name);
      link.classList.toggle('on', link.dataset.screen === name || name === 'accounts' && link.dataset.screen === 'admin-staff');
      if (link.dataset.screen === name) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
    });
    $('admin-sidebar').classList.remove('open');
    $('admin-menu').setAttribute('aria-expanded', 'false');
    try {
      const page = await handler();
      if (version !== renderVersion || !user) return;
      $('admin-page-title').textContent = page.title;
      document.title = `${page.title} · EduCenter`;
      $('admin-content').innerHTML = page.html;
      page.bind?.();
      document.querySelectorAll('[data-period-picker]').forEach(el => mountPeriodPicker(el.dataset.periodPicker));
    } catch (error) {
      if (version !== renderVersion || !user) return;
      $('admin-content').innerHTML = `<div class="card"><h2>Không tải được dữ liệu</h2><p class="form-error" role="alert">${esc(error.message)}</p><button class="btn" id="admin-retry">Thử lại</button></div>`;
      $('admin-retry').addEventListener('click', render);
    }
  }

  function openWorkspace(account) {
    user = account;
    $('admin-login').hidden = true;
    $('admin-workspace').hidden = false;
    $('admin-name').textContent = account.hoTen;
    if ($('udName')) $('udName').textContent = account.hoTen;
    if ($('admin-avatar')) $('admin-avatar').textContent = account.hoTen.trim().split(/\s+/).pop()[0].toUpperCase();
    if ($('topDateText')) {
      const daysVi = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
      const now = new Date();
      $('topDateText').textContent = `${daysVi[now.getDay()]}, ${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()}`;
    }
    render();
    window.loadNotifications?.();
  }

  window.addEventListener('DOMContentLoaded', () => {
    $('admin-login-form').addEventListener('submit', async event => {
      event.preventDefault();
      const button = event.target.querySelector('button');
      button.disabled = true;
      $('login-error').textContent = '';
      try {
        const result = await api('/api/auth/login', 'POST', Object.fromEntries(new FormData(event.target)));
        token = result.token;
        if (result.user.role !== 'ADMIN') {
          await api('/api/auth/logout', 'POST', {});
          token = null;
          throw new Error('Cổng này dành cho tài khoản Admin.');
        }
        event.target.reset();
        openWorkspace(result.user);
      } catch (error) { $('login-error').textContent = error.message; }
      finally { button.disabled = false; }
    });
    $('admin-logout').addEventListener('click', event => busy(event.currentTarget, async () => {
      await api('/api/auth/logout', 'POST', {});
      reset();
    }));
    $('admin-refresh')?.addEventListener('click', render);
    $('admin-menu').addEventListener('click', () => {
      const open = $('admin-sidebar').classList.toggle('open');
      $('admin-menu').setAttribute('aria-expanded', String(open));
    });
    $('admin-dialog').addEventListener('cancel', event => {
      if ($('admin-dialog').dataset.busy === 'true') event.preventDefault();
    });
    window.addEventListener('hashchange', render);
  });

  return { $, esc, money, date, query, api, heading, table, tag, button, options, pager, bindPager, busy,
    notify, dialog, formDialog, confirmDialog, mountPeriodPicker, render, currentScreen, view:name => window.AdminViews[name],
    get period() { return period; }, set period(value) { period = value; },
    register:(name, handler) => { screens[name] = handler; }, get user() { return user; } };
})();

window.showS = id => {
  if (Admin.currentScreen() === id) Admin.render();
  else location.hash = `/admin/${id}`;
};
window.toast = (text, type) => Admin.notify(text, type === 'err');
window.showPublicLanding = () => { location.href = '/'; };
window.setPortal = role => {
  if (role === 'admin') showS('admin-dash');
  else toast('Chọn cổng bằng tài khoản đúng vai trò khi đăng nhập. Các cổng khác do thành viên phụ trách triển khai.', 'info');
};
window.toggleUserDropdown = () => document.getElementById('userDropdown').classList.toggle('show');
window.openAccountManagement = () => showS('accounts');
window.handleLogout = async () => {
  document.getElementById('admin-logout').click();
};
window.openChangePwdModal = () => Admin.formDialog({ title:'Đổi mật khẩu', fields:[
  { name:'currentPassword', label:'Mật khẩu hiện tại', type:'password', required:true },
  { name:'newPassword', label:'Mật khẩu mới', type:'password', required:true }
], onSubmit:async body => { await Admin.api('/api/auth/change-password', 'PUT', body); location.reload(); } });
const readNotifIds = new Set();
window.syncNotifBadge = () => {
  const unread = document.querySelectorAll('#notifPanel .np-item.unread').length;
  const badge = Admin.$('nCount');
  if (!badge) return;
  badge.textContent = String(unread);
  badge.style.display = unread > 0 ? 'grid' : 'none';
};
window.markNotifItemRead = el => {
  if (el) {
    el.classList.remove('unread');
    if (el.dataset.notifId) readNotifIds.add(el.dataset.notifId);
  }
  window.syncNotifBadge();
};
window.markAllNotifsRead = () => {
  document.querySelectorAll('#notifPanel .np-item').forEach(el => {
    el.classList.remove('unread');
    if (el.dataset.notifId) readNotifIds.add(el.dataset.notifId);
  });
  window.syncNotifBadge();
  toast('Đã đánh dấu đọc tất cả thông báo.', 'ok');
};
window.loadNotifications = async () => {
  const listEl = Admin.$('notifList');
  if (!listEl) return;
  try {
    const [txs, regs, corrs, leaves] = await Promise.all([
      Admin.api('/api/finance/tuition/transactions').catch(() => []),
      Admin.api('/api/academic/classes/registrations').catch(() => []),
      Admin.api('/api/finance/timekeeping/corrections').catch(() => []),
      Admin.api('/api/academic/leave-requests/classes').catch(() => [])
    ]);
    const items = [];
    const parseTs = (raw, fallbackDate) => {
      const str = String(raw || fallbackDate || '');
      const m = str.match(/(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?/);
      if (m) return { dateStr: m[1], timeStr: m[2] || '08:00', sortKey: `${m[1]} ${m[2] || '08:00'}` };
      const now = new Date();
      const dStr = now.toLocaleDateString('sv-SE');
      return { dateStr: dStr, timeStr: 'Mới', sortKey: `${dStr} 23:59` };
    };
    (txs || []).filter(r => r.trangThai === 'cho_xac_nhan').forEach(r => {
      const ts = parseTs(r.createdAt);
      items.push({
        id: `tx-${r.id}`,
        ...ts,
        title: `Phụ huynh ${r.tenPhuHuynh || ''} gửi thanh toán`,
        desc: `Giao dịch GD-${r.id} (${Admin.money(r.soTien)}) cho hóa đơn ${r.maHd || ''} đang chờ đối soát.`,
        screen: 'admin-payments'
      });
    });
    (regs || []).filter(r => r.trangThai === 'cho_xep_lop').forEach(r => {
      const ts = parseTs(r.xepLuc || r.createdAt);
      items.push({
        id: `reg-${r.id}`,
        ...ts,
        title: `Đăng ký khóa học mới DK-${r.id}`,
        desc: `Học viên ${r.hoTen || ''} đăng ký khóa ${r.tenKhoa || r.maKhoa || ''} đang chờ Admin xếp lớp.`,
        screen: 'admin-courses'
      });
    });
    (corrs || []).filter(r => r.trangThai === 'cho_duyet').forEach(r => {
      const ts = parseTs(r.createdAt, r.ngay);
      const hText = r.loai === 'huy_cong' ? '0 giờ' : r.phutDeXuat != null ? `${Number((Number(r.phutDeXuat) / 60).toFixed(2))} giờ` : 'điều chỉnh giờ';
      items.push({
        id: `corr-${r.id}`,
        ...ts,
        title: `${r.hoTen || 'Nhân sự'} gửi đơn điều chỉnh giờ công`,
        desc: `Lớp ${r.maLop || ''} (${r.ngay || ''}) · Đề xuất ${hText}: ${r.lyDo || ''}`,
        screen: 'admin-exceptions'
      });
    });
    (leaves || []).filter(r => r.trangThai === 'cho_duyet').forEach(r => {
      const ts = parseTs(r.createdAt, r.ngay);
      items.push({
        id: `leave-${r.id}`,
        ...ts,
        title: `${r.nguoiGui || 'Giáo viên'} xin nghỉ buổi học ${r.maLop || ''}`,
        desc: `Ngày ${r.ngay || ''} · Lý do: ${r.lyDo || 'Chờ Admin phê duyệt'}`,
        screen: 'admin-exceptions'
      });
    });
    items.sort((a, b) => b.sortKey.localeCompare(a.sortKey));
    if (!items.length) {
      listEl.innerHTML = '<div style="padding:28px 18px;text-align:center;color:var(--g400);font-size:13px">Chưa có thông báo mới.</div>';
      window.syncNotifBadge();
      return;
    }
    const todayStr = new Date().toLocaleDateString('sv-SE');
    const yestDate = new Date(Date.now() - 86400000).toLocaleDateString('sv-SE');
    const formatDateGroup = dStr => {
      if (dStr === todayStr) return 'HÔM NAY';
      if (dStr === yestDate) return 'HÔM QUA';
      const [y, m, d] = dStr.split('-');
      return d && m && y ? `${d}/${m}/${y}` : dStr;
    };
    const groups = new Map();
    items.forEach(item => {
      const g = formatDateGroup(item.dateStr);
      if (!groups.has(g)) groups.set(g, []);
      groups.get(g).push(item);
    });
    let html = '';
    for (const [gName, gItems] of groups.entries()) {
      html += `<div class="np-date-group">${Admin.esc(gName)}</div>`;
      html += gItems.map(item => `
        <div class="np-item ${readNotifIds.has(item.id) ? '' : 'unread'}" data-notif-id="${Admin.esc(item.id)}" onclick="markNotifItemRead(this);showS('${item.screen}');document.getElementById('notifPanel').classList.remove('open')">
          <div class="np-item-top"><b>${Admin.esc(item.title)}</b><time>${Admin.esc(item.timeStr)}</time></div>
          <p>${Admin.esc(item.desc)}</p>
        </div>
      `).join('');
    }
    listEl.innerHTML = html;
    window.syncNotifBadge();
  } catch {
    window.syncNotifBadge();
  }
};
window.toggleNotif = async ev => {
  if (ev) ev.stopPropagation();
  const panel = Admin.$('notifPanel');
  if (!panel) return;
  const willOpen = !panel.classList.contains('open');
  panel.classList.toggle('open', willOpen);
  if (willOpen) await window.loadNotifications();
};
