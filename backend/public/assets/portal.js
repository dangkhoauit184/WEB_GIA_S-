'use strict';
// Bearer token stays in memory; a refresh requires login again.
let token = null;
let resource = 'students';
const byId = id => document.getElementById(id);
const notice = text => { byId('notice').textContent = text; };
const definitions = {
  students: { path: '/api/people/students', label: 'học viên', fields: [['maHv','Mã'],['hoTen','Họ tên'],['tenCapLop','Cấp lớp'],['tenPhuHuynh','Phụ huynh'],['trangThai','Trạng thái']] },
  classes: { path: '/api/academic/classes', label: 'lớp học', fields: [['maLop','Mã lớp'],['tenLop','Tên lớp'],['tenMonHoc','Môn học'],['tenNguoiDayChinh','Người dạy'],['trangThai','Trạng thái']] },
  sessions: { path: '/api/academic/scheduling/sessions', label: 'buổi học', fields: [['tenLop','Lớp'],['ngay','Ngày'],['gioBatDau','Bắt đầu'],['gioKetThuc','Kết thúc'],['tenNguoiDayChinh','Người dạy']] }
};
async function api(path, method = 'GET', body) {
  const response = await fetch(path, { method, headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
  const result = await response.json();
  if (!response.ok) {
    if (response.status === 401 && token) reset();
    throw new Error(result.message || 'Không tải được dữ liệu.');
  }
  return result.data;
}
function reset() {
  token = null;
  byId('workspace').hidden = true;
  byId('login-panel').hidden = false;
  byId('rows').replaceChildren();
  byId('password-form').reset();
}
async function load() {
  const definition = definitions[resource];
  const q = byId('search').value.trim();
  const data = await api(definition.path + (q && resource !== 'sessions' ? `?q=${encodeURIComponent(q)}` : ''));
  const headings = document.createElement('tr');
  for (const [, label] of definition.fields) { const cell = document.createElement('th'); cell.textContent = label; headings.append(cell); }
  byId('columns').replaceChildren(headings);
  byId('rows').replaceChildren();
  for (const row of data) {
    const tr = document.createElement('tr');
    for (const [key] of definition.fields) { const td = document.createElement('td'); td.textContent = row[key] ?? '—'; tr.append(td); }
    byId('rows').append(tr);
  }
  byId('result-title').textContent = `${data.length} ${definition.label}`;
  document.querySelectorAll('[data-resource]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.resource === resource)));
}
byId('login-form').addEventListener('submit', async event => {
  event.preventDefault(); notice('');
  const button = event.target.querySelector('button'); button.disabled = true;
  try {
    const data = await api('/api/auth/login', 'POST', Object.fromEntries(new FormData(event.target)));
    token = data.token; event.target.reset();
    byId('login-panel').hidden = true; byId('workspace').hidden = false;
    byId('welcome').textContent = `Xin chào, ${data.user.hoTen}`;
    byId('role').textContent = data.user.role;
    resource = 'sessions'; await load();
  } catch (error) { notice(error.message); }
  finally { button.disabled = false; }
});
document.querySelectorAll('[data-resource]').forEach(button => button.addEventListener('click', async () => {
  resource = button.dataset.resource; notice('');
  try { await load(); } catch (error) { notice(error.message); }
}));
byId('search-form').addEventListener('submit', async event => { event.preventDefault(); notice(''); try { await load(); } catch (error) { notice(error.message); } });
byId('logout').addEventListener('click', async () => { try { await api('/api/auth/logout', 'POST', {}); reset(); notice('Đã đăng xuất.'); } catch (error) { notice(error.message); } });
byId('password-form').addEventListener('submit', async event => {
  event.preventDefault();
  try { await api('/api/auth/change-password', 'PUT', Object.fromEntries(new FormData(event.target))); reset(); notice('Đã đổi mật khẩu. Vui lòng đăng nhập lại.'); }
  catch (error) { notice(error.message); }
});
