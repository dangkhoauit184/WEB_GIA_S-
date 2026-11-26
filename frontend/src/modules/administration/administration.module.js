/* Administration shell. Feature modules register their own screens below. */
'use strict';
window.Admin = (() => {
  const screens = {};
  const admissionsPortal = document.body.dataset.portal === 'admissions';
  const defaultScreen = admissionsPortal ? 'staff-dash' : 'admin-dash';
  let token = null;
  let user = null;
  let renderVersion = 0;
  let toastTimer;
  let period = new Date().toLocaleDateString('sv-SE').slice(0, 7);
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
  const money = value => new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND', maximumFractionDigits:0 }).format(Number(value || 0));
  const date = value => window.ECDate ? window.ECDate.dateTime(value) : (value ? String(value).replace('T', ' ').slice(0, 19) : '—');
  const query = values => new URLSearchParams(Object.entries(values).filter(([,value]) => value !== '' && value != null)).toString();

  function notify(message, error = false) {
    clearTimeout(toastTimer);
    $('admin-toast').textContent = message;
    $('admin-toast').classList.toggle('error', error);
    $('admin-toast').hidden = false;
    toastTimer = setTimeout(() => { $('admin-toast').hidden = true; }, 5500);
  }

  function reset(loggedOut = false) {
    token = null;
    user = null;
    renderVersion++;
    $('admin-workspace').hidden = true;
    $('admin-content').replaceChildren();
    document.querySelectorAll('.modal-ov.show').forEach(modal => modal.classList.remove('show'));
    if ($('admin-dialog').open) $('admin-dialog').close();
    if (loggedOut) AccessService.markLoggedOut();
    else AccessService.clearSession();
    window.location.replace(AppConfig.AUTH_PAGE + (loggedOut ? '?loggedOut=1' : ''));
  }

  async function api(path, method = 'GET', body) {
    let response;
    try {
      response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          ...(body instanceof FormData ? {} : {'Content-Type':'application/json'}),
        },
        ...(body === undefined ? {} : { body:body instanceof FormData ? body : JSON.stringify(body) })
      });
    } catch {
      throw new Error('Không kết nối được máy chủ. Vui lòng kiểm tra kết nối rồi thử lại.');
    }
    const result = await response.json().catch(() => null);
    if (response.status === 401) {
      reset();
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
  function kpi(label, value, sub, tone = '', primary = false) {
    return `<div class="ec-kpi ${primary ? 'primary' : ''} ${tone ? 'tone-' + esc(tone) : ''}"><span class="ec-kpi-l">${esc(label)}</span><b class="ec-kpi-v">${esc(value)}</b><span class="ec-kpi-s">${esc(sub)}</span></div>`;
  }
  function emptyRow(columns, message = 'Chưa có dữ liệu.') {
    return `<tr><td colspan="${columns}" class="admin-empty">${esc(message)}</td></tr>`;
  }
  const actionHandlers = {};
  function actions(map) { Object.assign(actionHandlers, map); }
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
      if (field.type === 'summary') {
        return `<p>${esc(field.label)}</p>`;
      } else if (field.type === 'select') {
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
    formDialog({ title, fields:[{ name:'summary', type:'summary', label:message, disabled:true }, ...fields], submit:'Xác nhận', onSubmit:work });
  }

  function formatPeriodLabel(ym) {
    const [y, m] = String(ym || period).split('-');
    return `Tháng ${Number(m || 1)} / ${y || '2026'}`;
  }

  function mountPeriodPicker(inputId = 'payrollMonth') {
    const input = $(inputId);
    const wrap = document.querySelector(`[data-period-picker="${inputId}"]`);
    if (!input || !wrap) return;
    if (!input.value) input.value = period;
    const renderTrigger = () => {
      const activeYm = input.value || period;
      wrap.innerHTML = `
        <button type="button" class="period-trigger" data-toggle-pop="1" aria-haspopup="dialog" aria-expanded="false">
          <span>${esc(formatPeriodLabel(activeYm))}</span>
          <svg class="svg-ico sm" style="color:var(--g500)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </button>`;
      wrap.querySelector('[data-toggle-pop]')?.addEventListener('click', ev => {
        ev.stopPropagation();
        const trigger = ev.currentTarget;
        window.ECPicker?.openMonthMenu(trigger, input.value || period, nextYm => {
          if (!nextYm) return;
          input.value = nextYm;
          renderTrigger();
          window.changePayrollMonth?.(nextYm);
        }, false);
      });
    };
    renderTrigger();
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
    const backdrop = ev.target.dataset?.backdropClose;
    if (backdrop && actionHandlers[backdrop]) {
      actionHandlers[backdrop](ev.target, ev);
      return;
    }
    const closeDlg = ev.target.closest('[data-close-dialog]');
    if (closeDlg) {
      $('admin-dialog')?.close();
      return;
    }
    const navEl = ev.target.closest('[data-nav]');
    if (navEl) {
      window.showS(navEl.dataset.nav);
      $('notifPanel')?.classList.remove('open');
      return;
    }
    const actEl = ev.target.closest('[data-act]');
    if (actEl) {
      const fn = actionHandlers[actEl.dataset.act];
      if (fn) fn(actEl, ev);
    }
  });
  document.addEventListener('change', ev => {
    const el = ev.target.closest('[data-on-change]');
    if (el && actionHandlers[el.dataset.onChange]) {
      actionHandlers[el.dataset.onChange](el, ev);
    }
  });
  document.addEventListener('input', ev => {
    const el = ev.target.closest('[data-on-input]');
    if (el && actionHandlers[el.dataset.onInput]) {
      actionHandlers[el.dataset.onInput](el, ev);
    }
  });
  document.addEventListener('submit', ev => {
    const form = ev.target.closest('[data-on-submit]');
    if (form && actionHandlers[form.dataset.onSubmit]) {
      actionHandlers[form.dataset.onSubmit](form, ev);
    }
  });

  function currentScreen() {
    const name = location.hash.replace(/^#\/?/, '').split('/').pop() || defaultScreen;
    return { dashboard:'admin-dash', courses:'admin-courses', catalog:'admin-catalog', settings:'admin-catalog', permissions:'admin-perms', audit:'admin-audit' }[name] || name;
  }
  async function render() {
    if (!user) return;
    const name = currentScreen();
    const handler = screens[name];
    if (!handler) { location.hash = `/${admissionsPortal ? 'staff' : 'admin'}/${defaultScreen}`; return; }
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
    const roleLabel = account.role === 'ADMIN' ? 'Quản trị viên' : 'Giáo vụ / Điều phối';
    $('sRole').textContent = roleLabel;
    if ($('udRole')) $('udRole').textContent = roleLabel;
    if ($('udName')) $('udName').textContent = account.hoTen;
    if ($('admin-avatar')) $('admin-avatar').textContent = AccountUI.initial(account.hoTen);
    if ($('topDateText')) {
      const daysVi = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
      const now = new Date();
      $('topDateText').textContent = `${daysVi[now.getDay()]}, ${String(now.getDate()).padStart(2, '0')}/${String(now.getMonth() + 1).padStart(2, '0')}/${now.getFullYear()}`;
    }
    render();
    if (admissionsPortal) window.loadStaffNotifications?.();
    else window.loadNotifications?.();
  }

  window.addEventListener('DOMContentLoaded', () => {
    $('admin-logout').addEventListener('click', event => busy(event.currentTarget, async () => {
      await api('/api/auth/logout', 'POST', {});
      reset(true);
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
    const account = AccessService.getCurrentUser();
    if (account) openWorkspace(account);
    else reset();
  });

  return { $, esc, money, date, query, api, heading, table, tag, button, kpi, emptyRow, actions, options, pager, bindPager, busy,
    notify, dialog, formDialog, confirmDialog, mountPeriodPicker, render, currentScreen, view:name => window.AdminViews[name],
    get period() { return period; }, set period(value) { period = value; },
    register:(name, handler) => { screens[name] = handler; }, get user() { return user; } };
})();

window.showS = id => {
  if (Admin.currentScreen() === id) Admin.render();
  else location.hash = `/${document.body.dataset.portal === 'admissions' ? 'staff' : 'admin'}/${id}`;
};
window.toast = (text, type) => Admin.notify(text, type === 'err');
window.showPublicLanding = () => { location.href = '/'; };
window.setPortal = (role, focus, screen) => {
  if (role === 'admin' && Admin.user?.role === 'ADMIN' && document.body.dataset.portal !== 'admissions') showS(screen || 'admin-dash');
  else if (role === 'staff' && Admin.user?.role === 'ADMIN') showS('staff-intake');
  else if (role === 'admin' && Admin.user?.role === 'ADMIN') location.href = `/admin#/admin/${screen || 'admin-dash'}`;
  else toast('Chọn cổng bằng tài khoản đúng vai trò khi đăng nhập. Các cổng khác do thành viên phụ trách triển khai.', 'info');
};
window.toggleUserDropdown = () => {
  const open = document.getElementById('userDropdown').classList.toggle('show');
  document.getElementById('admin-avatar').setAttribute('aria-expanded', String(open));
};
window.openAccountManagement = () => showS('accounts');
window.handleLogout = async () => {
  document.getElementById('admin-logout').click();
};
window.openChangePwdModal = () => AccountUI.password();
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
  if (document.body.dataset.portal === 'admissions') return window.loadStaffNotifications();
  const listEl = Admin.$('notifList');
  if (!listEl) return;
  try {
    const data = await Admin.api('/api/communication/notifications/admin-summary');
    const parseTs = raw => {
      const str = String(raw || '');
      const m = str.match(/(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?/);
      if (m) return { dateStr: m[1], timeStr: m[2] || '08:00', sortKey: `${m[1]} ${m[2] || '08:00'}` };
      const dStr = new Date().toLocaleDateString('sv-SE');
      return { dateStr: dStr, timeStr: 'Mới', sortKey: `${dStr} 23:59` };
    };
    const items = (data?.items || []).map(item => ({
      ...item,
      ...parseTs(item.timestamp),
    }));
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
        <div class="np-item ${readNotifIds.has(item.id) ? '' : 'unread'}" data-notif-id="${Admin.esc(item.id)}" data-act="open-notif" data-screen="${Admin.esc(item.screen)}">
          <div class="np-item-top"><b>${Admin.esc(window.ECDate ? window.ECDate.replaceInlineDates(item.title) : item.title)}</b><time>${Admin.esc(item.timeStr)}</time></div>
          <p>${Admin.esc(window.ECDate ? window.ECDate.replaceInlineDates(item.desc) : item.desc)}</p>
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

Admin.actions({
  'public-landing': () => window.showPublicLanding(),
  'toggle-notif': (_el, ev) => window.toggleNotif(ev),
  'mark-all-notifs': () => window.markAllNotifsRead(),
  'open-notif': el => {
    window.markNotifItemRead(el);
    if (el.dataset.screen) window.showS(el.dataset.screen);
    Admin.$('notifPanel')?.classList.remove('open');
  },
  'toggle-user-dropdown': () => window.toggleUserDropdown(),
  'open-account-mgmt': () => {
    window.openAccountManagement();
    Admin.$('userDropdown')?.classList.remove('show');
    Admin.$('admin-avatar')?.setAttribute('aria-expanded', 'false');
  },
  'logout': () => {
    window.handleLogout();
    Admin.$('userDropdown')?.classList.remove('show');
  },
  'print-window': () => window.print(),
  'set-portal': el => window.setPortal(el.dataset.portal, true, el.dataset.screen),
  'change-payroll-month': el => window.changePayrollMonth?.(el.value),
  'show-toast': el => window.toast(el.dataset.msg || '', el.dataset.tone || 'info'),
});
