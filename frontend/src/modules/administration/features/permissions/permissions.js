'use strict';
(() => {
  const A = window.Admin;
  const base = '/api/administration/permissions';
  const groupNames = { Admin:'Quản trị viên', NhanVien:'Giáo vụ', GiangVien:'Giáo viên / Gia sư', TroGiang:'Trợ giảng', PhuHuynh:'Phụ huynh' };
  const statuses = { active:'Hoạt động', locked:'Đã khóa', pending:'Chờ kích hoạt' };
  const statusOptions = Object.entries(statuses).map(([value,label]) => ({ value,label }));
  const functions = { QuanLyNhanSu:'Quản lý nhân sự', TiepNhanYeuCau:'Tiếp nhận yêu cầu học', QuanLyLopHoc:'Quản lý lớp học', QuanLyTaiKhoan:'Quản lý tài khoản', HocPhiThanhToan:'Học phí và thanh toán', ChamCongLuong:'Chấm công / lương', VanHanhBuoiHoc:'Vận hành buổi học', BaoCaoThongKe:'Báo cáo / thống kê' };
  const actions = { view:'Xem', add:'Thêm', edit:'Sửa', delete:'Xóa', download:'Tải xuống' };
  let filters = { q:'', groupId:'', status:'', page:1, perPage:25 };
  let selectedGroup = null;

  function groupOptions(groups) { return groups.map(group => ({ value:group.groupId, label:groupNames[group.groupName] || group.groupName })); }

  async function createAccount(groups) {
    A.formDialog({ title:'Tìm hồ sơ để cấp tài khoản', submit:'Tìm hồ sơ', fields:[{ name:'q', label:'Tên hoặc email', help:'Chọn hồ sơ người dùng đã có trong hệ thống.' }], onSubmit:async values => {
      const candidates = await A.api(`${base}/account-candidates?${A.query(values)}`);
      if (!candidates.length) throw new Error('Chưa có hồ sơ phù hợp chưa được cấp tài khoản. Hãy kiểm tra tên/email hoặc tạo hồ sơ trước.');
      // Open the next dialog after the current submit handler closes its dialog.
      setTimeout(() => A.formDialog({ title:'Cấp tài khoản', submit:'Tạo tài khoản', fields:[
        { name:'userId', label:'Hồ sơ', type:'select', required:true, options:candidates.map(row => ({ value:row.userId, label:`${row.hoTen} · ${row.email}` })) },
        { name:'username', label:'Tên đăng nhập', required:true, maxLength:100 },
        { name:'password', label:'Mật khẩu ban đầu', type:'password', required:true },
        { name:'groupId', label:'Nhóm quyền', type:'select', required:true, options:groupOptions(groups) },
        { name:'status', label:'Trạng thái', type:'select', value:'active', options:statusOptions }
      ], onSubmit:async data => {
        data.userId = Number(data.userId);
        data.groupId = Number(data.groupId);
        await A.api(`${base}/accounts`, 'POST', data);
        A.notify('Đã cấp tài khoản cho hồ sơ.');
        if (A.currentScreen() === 'accounts') A.render();
      } }), 0);
    } });
  }

  A.register('accounts', async () => {
    const [data, groups] = await Promise.all([A.api(`${base}/accounts?${A.query(filters)}`), A.api(`${base}/groups`)]);
    return {
      title:'Tài khoản',
      html:A.heading('Quản lý tài khoản', 'Cấp tài khoản, quản lý trạng thái và nhóm quyền truy cập.', '<button class="btn p" id="account-add">+ Cấp tài khoản</button>') + `<div class="card"><form class="admin-filter" id="accounts-filter"><label>Tìm kiếm<input type="search" name="q" value="${A.esc(filters.q)}" placeholder="Tên hoặc tên đăng nhập"></label><label>Nhóm quyền<select name="groupId"><option value="">Tất cả nhóm</option>${A.options(groups, 'groupId', 'groupName', filters.groupId)}</select></label><label>Trạng thái<select name="status"><option value="">Tất cả</option>${Object.entries(statuses).map(([key,label]) => `<option value="${key}" ${key === filters.status ? 'selected' : ''}>${label}</option>`).join('')}</select></label><button class="btn">Lọc</button></form>${A.table([
        { label:'Người dùng', render:row => `<b>${A.esc(row.hoTen)}</b><small>User #${row.userId}</small>` },
        { key:'username', label:'Tên đăng nhập' },
        { label:'Nhóm quyền', render:row => A.tag(groupNames[row.groupName] || row.groupName, row.groupName === 'Admin' ? 'i' : '') },
        { label:'Trạng thái', render:row => A.tag(statuses[row.status], row.status === 'active' ? 'ok' : 'w') },
        { label:'Thao tác', render:row => `<div class="row">${A.button('Sửa', 'edit', row.accountId)}${Number(row.accountId) !== Number(A.user.accountId) ? A.button('Đổi nhóm', 'group', row.accountId) : ''}${A.button('Đặt lại mật khẩu', 'password', row.accountId)}</div>` }
      ], data.items)}${A.pager(data)}</div>`,
      bind() {
        A.$('accounts-filter').addEventListener('submit', event => {
          event.preventDefault();
          filters = { ...filters, ...Object.fromEntries(new FormData(event.target)), page:1 };
          A.render();
        });
        A.bindPager(page => { filters.page = page; A.render(); });
        A.$('account-add').addEventListener('click', () => createAccount(groups));
        document.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
          const item = data.items.find(row => Number(row.accountId) === Number(button.dataset.id));
          if (button.dataset.action === 'group') {
            A.formDialog({ title:`Nhóm quyền · ${item.username}`, fields:[{ name:'groupId', label:'Nhóm quyền', type:'select', required:true, value:item.groupId, options:groupOptions(groups), help:'Nhóm Giáo vụ/người dạy cần hồ sơ nhân viên/chuyên môn phù hợp.' }], onSubmit:async values => {
              await A.api(`${base}/accounts/${item.accountId}/group`, 'PUT', { groupId:Number(values.groupId) });
              A.notify('Đã cập nhật nhóm quyền.');
              if (A.currentScreen() === 'accounts') A.render();
            } });
          } else if (button.dataset.action === 'password') {
            A.formDialog({ title:`Đặt lại mật khẩu · ${item.username}`, submit:'Đặt lại mật khẩu', fields:[{ name:'password', label:'Mật khẩu mới', type:'password', required:true, help:'Người dùng đăng nhập lại bằng mật khẩu mới.' }, { name:'confirmPassword', label:'Nhập lại mật khẩu', type:'password', required:true }], onSubmit:async values => {
              if (values.password !== values.confirmPassword) throw new Error('Hai mật khẩu chưa trùng nhau.');
              await A.api(`${base}/accounts/${item.accountId}/password`, 'PUT', { password:values.password });
              A.notify('Đã đặt lại mật khẩu.');
              if (Number(item.accountId) === Number(A.user.accountId)) A.render();
            } });
          } else {
            A.formDialog({ title:`Chỉnh sửa · ${item.username}`, fields:[{ name:'username', label:'Tên đăng nhập', required:true, maxLength:100, value:item.username }, { name:'status', label:'Trạng thái', type:'select', options:statusOptions, value:item.status, disabled:Number(item.accountId) === Number(A.user.accountId) }], onSubmit:async values => {
              await A.api(`${base}/accounts/${item.accountId}`, 'PUT', values);
              A.notify('Đã cập nhật tài khoản.');
              if (A.currentScreen() === 'accounts') A.render();
            } });
          }
        }));
      }
    };
  });

  let currentMatrix = null;
  let permissionGroups = [];
  let groupRequestVersion = 0;
  let savingMatrix = false;
  async function selectGroup(id) {
    if (savingMatrix) return;
    const version = ++groupRequestVersion;
    const matrix = await A.api(`${base}/groups/${id}/permissions`);
    if (version !== groupRequestVersion || A.currentScreen() !== 'admin-perms') return;
    selectedGroup = id;
    currentMatrix = matrix;
    renderPerms();
  }
  window.setPermRole = id => selectGroup(Number(id)).catch(error => A.notify(error.message,true));
  window.renderPerms = function() {
    if (!A.$('permCards')) return;
    const isAdmin = currentMatrix.group.groupName === 'Admin';
    A.$('permRoleTabs').innerHTML = permissionGroups.map(group => `<button class="chip ${group.groupId===selectedGroup?'on':''}" ${savingMatrix?'disabled':''} onclick="setPermRole(${group.groupId})">${A.esc(groupNames[group.groupName]||group.groupName)}</button>`).join('');
    A.$('permVersionBadge').textContent = 'version '+currentMatrix.version.slice(0,10)+'…'+currentMatrix.version.slice(-6);
    A.$('permRoleLabel').textContent = 'Cấu hình phân quyền cho nhóm '+(groupNames[currentMatrix.group.groupName]||currentMatrix.group.groupName)+(isAdmin?' · Nhóm Admin gốc được bảo vệ cố định':'');
    A.$('permCards').innerHTML = currentMatrix.permissions.map(row => `<div class="card" style="padding:10px 14px;margin-bottom:8px"><div class="row-b"><b>${A.esc(functions[row.functionName]||row.functionName)}</b><div class="row" style="gap:12px">${Object.entries(actions).map(([flag,label]) => `<label style="margin:0;font-size:12px;display:flex;align-items:center;gap:4px"><input style="width:14px;height:14px" type="checkbox" ${row[flag]?'checked':''} ${savingMatrix||isAdmin||row.functionName==='ChamCongLuong'||flag!=='view'&&!row.view?'disabled':''} onchange="changeAdminPermission(${row.functionId},'${flag}',this.checked)">${label}</label>`).join('')}</div></div></div>`).join('');
    A.$('btnPermAllOn').disabled = savingMatrix || isAdmin;
    A.$('btnPermAllOff').disabled = savingMatrix || isAdmin;
    document.querySelector('[onclick="savePermissionsMatrix()"]').disabled = savingMatrix || isAdmin;
  };
  window.changeAdminPermission = (id,flag,value) => {
    const row = currentMatrix.permissions.find(item => item.functionId===id);
    row[flag] = value;
    if (flag==='view'&&!value) for(const action of Object.keys(actions)) row[action]=false;
    renderPerms();
  };
  window.permBatch = value => {
    if(currentMatrix.group.groupName==='Admin') return;
    for(const row of currentMatrix.permissions) for(const flag of Object.keys(actions)) row[flag]=row.functionName==='ChamCongLuong'?false:Boolean(value);
    renderPerms();
  };
  window.savePermissionsMatrix = async () => {
    const button = document.querySelector('[onclick="savePermissionsMatrix()"]');
    await A.busy(button, async () => {
      savingMatrix = true;
      renderPerms();
      try {
        const permissions = currentMatrix.permissions.map(row =>
          Object.fromEntries(['functionId', ...Object.keys(actions)].map(key => [key, row[key]])));
        currentMatrix = await A.api(`${base}/groups/${currentMatrix.group.groupId}/permissions`, 'PUT', {
          version:currentMatrix.version, permissions
        });
        A.notify('Đã lưu ma trận phân quyền và cập nhật version.');
      } catch (error) {
        if (error.code === 'PERMISSIONS_VERSION_CONFLICT') {
          currentMatrix = await A.api(`${base}/groups/${selectedGroup}/permissions`);
          throw new Error('Ma trận đã được người khác cập nhật. Đã tải bản mới, hãy kiểm tra rồi lưu lại.');
        }
        throw error;
      } finally {
        savingMatrix = false;
        renderPerms();
      }
    });
  };
  A.register('admin-perms',async () => {
    groupRequestVersion++;
    permissionGroups=await A.api(base+'/groups');
    selectedGroup=selectedGroup||permissionGroups[0].groupId;
    currentMatrix=await A.api(`${base}/groups/${selectedGroup}/permissions`);
    return {title:'Phân quyền 8 phân hệ',html:A.view('admin-perms'),bind:renderPerms};
  });
})();
