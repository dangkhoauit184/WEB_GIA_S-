'use strict';
(() => {
  const Admin = window.Admin;
  const catalog = '/api/administration/catalog';
  let courses = [], registrations = [], classes = [], currentCatalog = 'rooms', catalogData = {};
  const empty = columns => `<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu.</td></tr>`;
  const activeOptions = [{ value:1,label:'Đang mở đăng ký' },{ value:0,label:'Ngừng nhận đăng ký' }];

  window.openCourseModal = function(id) {
    const item = courses.find(row => row.id === Number(id));
    const fields = [
      {name:'ma',label:'Mã khóa',required:true,maxLength:40},
      {name:'ten',label:'Tên khóa học',required:true,maxLength:150},
      {name:'moTa',label:'Mô tả chương trình',type:'textarea',nullable:true},
      {name:'hocPhi',label:'Học phí trọn khóa (VND)',type:'number',min:0,max:999999999999,required:true},
      {name:'isActive',label:'Trạng thái nhận đăng ký',type:'select',value:1,options:activeOptions}
    ].map(field => ({...field,value:item ? (field.name === 'isActive' ? Number(item.isActive) : item[field.name]) : field.value}));
    Admin.formDialog({title:item ? 'Chi tiết & Cấu hình khóa học' : 'Thêm khóa học',fields,onSubmit:async body => {
      body.isActive = body.isActive === '1';
      await Admin.api(`${catalog}/courses${item ? '/'+item.id : ''}`,item ? 'PUT' : 'POST',body);
      toast('Đã lưu khóa học.','ok'); Admin.render();
    }});
  };

  function fillEnrollmentClasses() {
    const selected = registrations.find(row => row.id === Number(Admin.$('aeEnrollmentSelect').value));
    const valid = classes.filter(row => row.khoaHocId === selected?.khoaHocId && ['sap_bat_dau','dang_hoc'].includes(row.trangThai));
    Admin.$('aeClassSelect').innerHTML = '<option value="">Chọn lớp đúng khóa</option>'+valid.map(row => `<option value="${row.id}">${Admin.esc(row.maLop+' · '+row.tenLop)} (${row.siSoHienTai}/${row.siSoToiDa} HV)</option>`).join('');
  }
  window.openAdminEnrollModal = function(id) {
    const available = registrations.filter(row => ['cho_xep_lop','dang_hoc'].includes(row.trangThai));
    Admin.$('aeEnrollmentSelect').innerHTML = available.map(row => `<option value="${row.id}" ${row.id===Number(id)?'selected':''}>DK-${row.id} · ${Admin.esc(row.hoTen+' · '+row.tenKhoa)}</option>`).join('');
    Admin.$('aeEnrollmentSelect').onchange = fillEnrollmentClasses;
    fillEnrollmentClasses();
    Admin.$('modalAdminEnroll').classList.add('show');
  };
  window.closeAdminEnrollModal = () => Admin.$('modalAdminEnroll').classList.remove('show');
  window.handleAdminEnrollSubmit = async event => {
    event.preventDefault();
    const registrationId = Number(Admin.$('aeEnrollmentSelect').value);
    const classId = Number(Admin.$('aeClassSelect').value);
    if (!registrationId || !classId) return toast('Chọn đăng ký và lớp thuộc đúng khóa.','err');
    await Admin.busy(event.target.querySelector('button[type=submit]'),async () => {
      await Admin.api(`/api/academic/classes/registrations/${registrationId}/assign`,'POST',{lopId:classId});
      closeAdminEnrollModal(); toast('Admin đã xếp lớp và lưu dấu vết.','ok'); Admin.render();
    });
  };
  Admin.register('admin-courses',async () => {
    const data = await Promise.all([Admin.api(catalog+'/courses'),Admin.api('/api/academic/classes/registrations'),Admin.api('/api/academic/classes')]);
    return {title:'Khóa học & Xếp lớp',html:Admin.view('admin-courses'),bind() {
      [courses,registrations,classes] = data;
      const subtitle=document.querySelector('#admin-courses .pg-head .sub');
      subtitle.textContent=subtitle.textContent.replace('5 sản phẩm',`${courses.length} sản phẩm`);
      Admin.$('adminCoursesBody').innerHTML = courses.map(row => `<tr><td><b class="mono">${Admin.esc(row.ma)}</b></td><td><b>${Admin.esc(row.ten)}</b></td><td>${Admin.esc(row.moTa||'—')}</td><td><b style="color:var(--ok)">${Admin.money(row.hocPhi)}</b></td><td>${Admin.tag(row.isActive?'Đang mở ĐK':'Tạm đóng ĐK',row.isActive?'ok':'r')}</td><td><button class="btn sm" onclick="openCourseModal(${row.id})">Chi tiết / Sửa</button></td></tr>`).join('') || empty(6);
      Admin.$('adminEnrollmentsBody').innerHTML = registrations.map(row => `<tr><td><b>DK-${row.id}</b></td><td><b>${Admin.esc(row.hoTen)}</b><small> ${Admin.esc(row.maHv)}</small></td><td><span class="mono">${Admin.esc(row.maKhoa)}</span> · ${Admin.esc(row.tenKhoa)}</td><td>${Admin.money(row.hocPhiSnapshot)}</td><td>${Admin.tag(row.maHd ? (row.tinhTrangThu==='da_thanh_toan'?'Đã thu đủ':row.tinhTrangThu) : 'Chưa phát hành hóa đơn',row.tinhTrangThu==='da_thanh_toan'?'ok':'w')}</td><td>${row.maLop?Admin.tag(row.maLop,'purple'):Admin.tag('Chờ Admin xếp lớp','r')}</td><td>${Admin.esc(row.nguoiXep||'—')}<br>${Admin.esc(Admin.date(row.xepLuc))}</td><td>${['cho_xep_lop','dang_hoc'].includes(row.trangThai)?`<button class="btn sm p" onclick="openAdminEnrollModal(${row.id})">${row.maLop?'Đổi lớp':'Xếp lớp ngay'}</button>`:Admin.tag(row.trangThai)}</td></tr>`).join('') || empty(8);
    }};
  });

  const definitions = {
    rooms:{title:'Phòng học',badge:'i',fields:[{name:'ten',label:'Tên phòng / địa điểm',placeholder:'VD: Phòng 301, Phòng Lab 2...',required:true,maxLength:50}]},
    subjects:{title:'Môn học',badge:'ok',fields:[{name:'ma',label:'Mã môn',placeholder:'VD: TOAN, ANH...',required:true,maxLength:20},{name:'ten',label:'Tên môn học',placeholder:'VD: Toán tư duy, Tiếng Anh...',required:true,maxLength:100}]},
    branches:{title:'Cơ sở đào tạo',badge:'purple',resource:'campuses',fields:[{name:'maCoSo',label:'Mã cơ sở',placeholder:'VD: CS-TD, CS-Q1...',required:true,maxLength:20},{name:'tenCoSo',label:'Tên cơ sở',placeholder:'VD: Cơ sở Thủ Đức',required:true,maxLength:150},{name:'diaChi',label:'Địa chỉ',placeholder:'Nhập địa chỉ cơ sở...',required:true,maxLength:255},{name:'soDienThoai',label:'Điện thoại',placeholder:'SĐT liên hệ',nullable:true,maxLength:20},{name:'email',label:'Email',type:'email',placeholder:'Email cơ sở',nullable:true}]},
    grades:{title:'Cấp lớp',badge:'w',resource:'grade-levels',fields:[{name:'ten',label:'Tên cấp lớp',placeholder:'VD: Lớp 9, Lớp 12...',required:true,maxLength:30}]},
    settings:{title:'Thông số Cấu hình Hệ thống'}
  };
  const settingNames = {
    han_nhap_bao_cao_gio: 'Thời hạn nộp báo cáo sau buổi học (giờ)',
    checkin_truoc_phut: 'Giới hạn Check-in sớm trước giờ học',
    checkout_sau_phut: 'Giới hạn Check-out muộn sau giờ học',
    checkin_tre_sau_phut: 'Ngưỡng tính đi trễ (phút)',
    checkin_minh_chung: 'Yêu cầu minh chứng GPS / Ảnh khi Check-in'
  };
  const fixed = {checkin_truoc_phut:'-1',checkout_sau_phut:'-1',checkin_tre_sau_phut:'0',checkin_minh_chung:'khong'};
  let currentFilter = 'all';
  let formType = 'subjects';
  let editingItem = null; // { key, id }
  let searchQuery = '';

  async function loadCatalog() {
    const result = await Promise.all(Object.entries(definitions).map(async ([key,definition]) => [key,await Admin.api(catalog+'/'+(definition.resource||key))]));
    catalogData = Object.fromEntries(result);
  }

  function buildUnifiedRows() {
    const rows = [];
    ['rooms','subjects','branches','grades'].forEach(key => {
      if (currentFilter !== 'all' && currentFilter !== key) return;
      const def = definitions[key];
      (catalogData[key] || []).forEach((item, idx) => {
        const code = key === 'subjects' ? item.ma : key === 'branches' ? item.maCoSo : key === 'grades' ? `KHOI-${item.capLop || item.id}` : `PHONG-${String(idx + 1).padStart(2, '0')}`;
        const name = key === 'branches' ? item.tenCoSo : item.ten;
        const detail = key === 'branches'
          ? [item.diaChi, item.soDienThoai, item.email].filter(Boolean).join(' · ')
          : key === 'grades'
            ? `Khối lớp chuẩn chương trình phổ thông (Cấp: ${(item.capHoc || 'PT').toUpperCase()})`
            : key === 'rooms'
              ? 'Phòng học / địa điểm xếp lịch giảng dạy'
              : 'Môn học đào tạo tại trung tâm';
        const isActive = item.isActive !== false;
        const haystack = `${def.title} ${code} ${name} ${detail}`.toLowerCase();
        if (searchQuery && !haystack.includes(searchQuery.toLowerCase())) return;
        rows.push({ key, id: item.id, code, name, detail, isActive, badge: def.badge, typeLabel: def.title, raw: item });
      });
    });
    return rows;
  }

  function renderUnifiedCatalog() {
    const counts = {
      rooms: (catalogData.rooms || []).length,
      subjects: (catalogData.subjects || []).length,
      branches: (catalogData.branches || []).length,
      grades: (catalogData.grades || []).length
    };
    const totalCount = counts.rooms + counts.subjects + counts.branches + counts.grades;
    const activeType = editingItem ? editingItem.key : formType;
    const activeDef = definitions[activeType] || definitions.subjects;
    const editRow = editingItem ? (catalogData[editingItem.key] || []).find(r => Number(r.id) === Number(editingItem.id)) : null;

    const formFieldsHtml = activeDef.fields.map(field => {
      const val = editRow ? (editRow[field.name] ?? '') : '';
      return `
        <div>
          <label>${Admin.esc(field.label)}${field.required ? ' <span style="color:var(--err)">*</span>' : ''}</label>
          <input name="${Admin.esc(field.name)}" type="${Admin.esc(field.type || 'text')}" value="${Admin.esc(val)}" placeholder="${Admin.esc(field.placeholder || '')}" ${field.required ? 'required' : ''} ${field.maxLength ? `maxlength="${field.maxLength}"` : ''}>
        </div>
      `;
    }).join('');

    const statusFieldHtml = (editRow && activeType !== 'grades' && activeType !== 'rooms') ? `
      <div>
        <label>Trạng thái</label>
        <select name="isActive">
          <option value="1" ${editRow.isActive !== false ? 'selected' : ''}>Đang sử dụng</option>
          <option value="0" ${editRow.isActive === false ? 'selected' : ''}>Ngừng sử dụng</option>
        </select>
      </div>
    ` : '';

    Admin.$('catTabs').innerHTML = `
      <form id="catUnifiedForm" class="cat-crud-bar" onsubmit="submitUnifiedCatalogForm(event)">
        <div class="cat-crud-head">
          <div class="row" style="gap:8px">
            <span class="tag ${editRow ? 'purple' : 'ok'}">${editRow ? 'Chế độ chỉnh sửa' : 'Biểu mẫu dùng chung'}</span>
            <b style="font-size:13.5px;color:var(--g800)">${editRow ? `Cập nhật ${Admin.esc(activeDef.title)}: ${Admin.esc(editRow.tenCoSo || editRow.ten || '')}` : 'Thêm mới hoặc cập nhật nhanh mọi thuộc tính danh mục tại một nơi'}</b>
          </div>
          ${editRow ? `<button type="button" class="btn sm" onclick="cancelUnifiedCatalogEdit()">Hủy chỉnh sửa</button>` : ''}
        </div>
        <div class="cat-crud-grid">
          <div>
            <label>Phân loại danh mục</label>
            <select id="catUnifiedTypeSelect" onchange="onUnifiedTypeChange(this.value)" ${editRow ? 'disabled' : ''}>
              <option value="subjects" ${activeType === 'subjects' ? 'selected' : ''}>Môn học</option>
              <option value="rooms" ${activeType === 'rooms' ? 'selected' : ''}>Phòng học</option>
              <option value="branches" ${activeType === 'branches' ? 'selected' : ''}>Cơ sở đào tạo</option>
              ${editRow && activeType === 'grades' ? `<option value="grades" selected>Cấp lớp (1–12)</option>` : ''}
            </select>
          </div>
          ${formFieldsHtml}
          ${statusFieldHtml}
          <div>
            <button type="submit" class="btn p" style="height:36px">${editRow ? 'Lưu cập nhật' : '+ Lưu vào danh mục'}</button>
          </div>
        </div>
      </form>

      <div class="row-b" style="margin-bottom:10px">
        <div class="chips" style="margin-bottom:0">
          <button type="button" class="chip ${currentFilter === 'all' ? 'on' : ''}" onclick="switchCat('all')">Tất cả (${totalCount})</button>
          <button type="button" class="chip ${currentFilter === 'subjects' ? 'on' : ''}" onclick="switchCat('subjects')">Môn học (${counts.subjects})</button>
          <button type="button" class="chip ${currentFilter === 'rooms' ? 'on' : ''}" onclick="switchCat('rooms')">Phòng học (${counts.rooms})</button>
          <button type="button" class="chip ${currentFilter === 'branches' ? 'on' : ''}" onclick="switchCat('branches')">Cơ sở đào tạo (${counts.branches})</button>
          <button type="button" class="chip ${currentFilter === 'grades' ? 'on' : ''}" onclick="switchCat('grades')">Cấp lớp (${counts.grades})</button>
        </div>
        <div style="width:260px">
          <input type="search" id="catUnifiedSearchInput" value="${Admin.esc(searchQuery)}" placeholder="Tìm mã, tên môn, phòng, cơ sở..." oninput="onUnifiedCatalogSearch(this.value)">
        </div>
      </div>
    `;

    renderUnifiedCatalogTableAndSettings();
  }

  function renderUnifiedCatalogTableAndSettings() {
    const rows = buildUnifiedRows();
    const settings = catalogData.settings || [];

    Admin.$('catContent').innerHTML = `
      <div class="card" style="padding:0;overflow:hidden">
        <div class="tw" style="border:0;border-radius:0">
          <table>
            <thead>
              <tr>
                <th>Phân loại</th>
                <th>Mã / Ký hiệu</th>
                <th>Tên danh mục</th>
                <th>Thông tin chi tiết</th>
                <th>Trạng thái</th>
                <th style="text-align:right">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              ${rows.length ? rows.map(row => `
                <tr>
                  <td>${Admin.tag(row.typeLabel, row.badge)}</td>
                  <td><b class="mono" style="color:var(--pri-d)">${Admin.esc(row.code)}</b></td>
                  <td><b>${Admin.esc(row.name)}</b></td>
                  <td style="font-size:12px;color:var(--g600)">${Admin.esc(row.detail)}</td>
                  <td>${Admin.tag(row.isActive ? 'Đang sử dụng' : 'Ngừng dùng', row.isActive ? 'ok' : 'r')}</td>
                  <td style="text-align:right">
                    <div class="row" style="justify-content:flex-end;gap:6px">
                      ${row.key !== 'rooms' ? `<button type="button" class="btn sm" onclick="startUnifiedCatalogEdit('${row.key}', ${row.id})">Sửa</button>` : ''}
                      ${!['rooms','grades'].includes(row.key) ? `<button type="button" class="btn sm ${row.isActive ? 'danger' : 'p'}" onclick="toggleUnifiedCatalogActive('${row.key}', ${row.id})">${row.isActive ? 'Ngừng dùng' : 'Kích hoạt'}</button>` : ''}
                    </div>
                  </td>
                </tr>
              `).join('') : empty(6)}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="margin-top:14px">
        <div class="row-b" style="margin-bottom:10px">
          <div>
            <h2 style="margin:0">Cấu hình Tham số Vận hành Hệ thống</h2>
            <p class="sub" style="margin-top:2px">Quản lý thời hạn nhập báo cáo buổi học và các quy tắc chấm công chuẩn của trung tâm</p>
          </div>
        </div>
        <div class="cat-settings-grid">
          ${settings.map(item => {
            const isEditable = item.khoa === 'han_nhap_bao_cao_gio';
            const friendlyTitle = settingNames[item.khoa] || item.moTa || item.khoa;
            const displayVal = item.giaTri === '-1' ? 'Không giới hạn (-1)' : item.giaTri === 'khong' ? 'Chỉ bấm nút trên hệ thống (Không GPS/Ảnh)' : item.giaTri === '0' ? '0 phút (Tính theo giao khung lịch)' : item.giaTri;
            return `
              <div class="cat-setting-card">
                <div class="row-b">
                  <b style="font-size:13px;color:var(--g800)">${Admin.esc(friendlyTitle)}</b>
                  ${isEditable ? Admin.tag('Cho phép chỉnh sửa', 'i') : Admin.tag('Quy tắc chuẩn', 'ok')}
                </div>
                <div style="font-size:11.5px;color:var(--g500)">${Admin.esc(item.moTa || '')}</div>
                ${isEditable ? `
                  <div class="row" style="gap:8px;margin-top:4px">
                    <input type="number" id="settingInput_${Admin.esc(item.khoa)}" min="1" max="8760" value="${Admin.esc(item.giaTri)}" style="width:120px;font-weight:700;color:var(--pri-d)">
                    <span style="font-size:12px;color:var(--g600)">giờ sau buổi học</span>
                    <button type="button" class="btn sm p" style="margin-left:auto" onclick="saveInlineAdminSetting('${Admin.esc(item.khoa)}', this)">Lưu cấu hình</button>
                  </div>
                ` : `
                  <div class="row-b" style="margin-top:4px;padding-top:6px;border-top:1px dashed var(--g200)">
                    <span style="font-size:12px;font-weight:600;color:var(--pri-d)">${Admin.esc(displayVal)}</span>
                    ${String(item.giaTri) !== fixed[item.khoa] && (item.khoa in fixed) ? `<button type="button" class="btn sm" onclick="editAdminSetting('${Admin.esc(item.khoa)}')">Đồng bộ chuẩn</button>` : ''}
                  </div>
                `}
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  }

  window.onUnifiedTypeChange = function(nextType) {
    formType = nextType;
    editingItem = null;
    renderUnifiedCatalog();
  };

  window.onUnifiedCatalogSearch = function(val) {
    searchQuery = val || '';
    renderUnifiedCatalogTableAndSettings();
  };

  window.startUnifiedCatalogEdit = function(key, id) {
    editingItem = { key, id: Number(id) };
    formType = key;
    renderUnifiedCatalog();
    Admin.$('catUnifiedForm')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  window.cancelUnifiedCatalogEdit = function() {
    editingItem = null;
    if (formType === 'grades') formType = 'subjects';
    renderUnifiedCatalog();
  };

  window.submitUnifiedCatalogForm = async function(event) {
    event.preventDefault();
    const form = event.target;
    const activeType = editingItem ? editingItem.key : formType;
    const def = definitions[activeType];
    const data = Object.fromEntries(new FormData(form));
    for (const field of def.fields) {
      if (field.nullable && data[field.name] === '') data[field.name] = null;
    }
    if ('isActive' in data) data.isActive = data.isActive === '1';

    const btn = form.querySelector('button[type=submit]');
    await Admin.busy(btn, async () => {
      const resource = def.resource || activeType;
      const url = `${catalog}/${resource}${editingItem ? '/' + editingItem.id : ''}`;
      await Admin.api(url, editingItem ? 'PUT' : 'POST', data);
      editingItem = null;
      if (formType === 'grades') formType = 'subjects';
      await loadCatalog();
      renderUnifiedCatalog();
      toast('Đã lưu danh mục thành công.', 'ok');
    });
  };

  window.toggleUnifiedCatalogActive = async function(key, id) {
    const def = definitions[key];
    const item = (catalogData[key] || []).find(r => Number(r.id) === Number(id));
    if (!item) return;
    const resource = def.resource || key;
    try {
      if (item.isActive !== false) {
        await Admin.api(`${catalog}/${resource}/${id}`, 'DELETE');
        toast('Đã chuyển sang trạng thái ngừng sử dụng.', 'ok');
      } else {
        await Admin.api(`${catalog}/${resource}/${id}`, 'PUT', { isActive: true });
        toast('Đã kích hoạt lại mục danh mục.', 'ok');
      }
      await loadCatalog();
      renderUnifiedCatalog();
    } catch (err) {
      toast(err.message, 'err');
    }
  };

  window.saveInlineAdminSetting = async function(key, btn) {
    const inp = Admin.$(`settingInput_${key}`);
    if (!inp) return;
    const val = Number(inp.value);
    await Admin.busy(btn, async () => {
      await Admin.api(`${catalog}/settings/${key}`, 'PUT', { giaTri: val });
      await loadCatalog();
      renderUnifiedCatalog();
      toast('Đã lưu cấu hình vận hành.', 'ok');
    });
  };

  window.switchCat = function(key) {
    if (key === 'settings') {
      currentFilter = 'all';
    } else {
      currentFilter = key || 'all';
      if (['rooms','subjects','branches'].includes(key) && !editingItem) {
        formType = key;
      }
    }
    currentCatalog = currentFilter;
    renderUnifiedCatalog();
  };
  window.editAdminSetting = key => {
    const item = catalogData.settings.find(row => row.khoa===key);
    if (key in fixed) {
      if (String(item.giaTri)===fixed[key]) return toast('Giá trị theo nghiệp vụ đã chốt.','info');
      Admin.formDialog({title:'Áp dụng chính sách đã chốt',fields:[{name:'giaTri',label:'Giá trị theo đặc tả',value:fixed[key],disabled:true}],onSubmit:async () => {
        await Admin.api(catalog+'/settings/'+key,'PUT',{giaTri:fixed[key]}); await loadCatalog(); renderUnifiedCatalog();
      }}); return;
    }
    if (key!=='han_nhap_bao_cao_gio') return toast('Cấu hình này được quản lý bởi hệ thống.','info');
    Admin.formDialog({title:'Hạn nhập báo cáo',fields:[{name:'giaTri',label:'Số giờ',type:'number',required:true,min:1,max:8760,value:item.giaTri}],onSubmit:async body => {
      await Admin.api(catalog+'/settings/'+key,'PUT',body); await loadCatalog(); renderUnifiedCatalog(); toast('Đã lưu cấu hình.','ok');
    }});
  };
  window.addCatItem = key => {
    if (['rooms','subjects','branches'].includes(key)) formType = key;
    editingItem = null;
    renderUnifiedCatalog();
  };
  window.editCatItem = (key, id) => startUnifiedCatalogEdit(key, id);
  Admin.register('admin-catalog',async () => {
    await loadCatalog();
    return {title:'Danh mục & Cấu hình',html:Admin.view('admin-catalog'),bind(){renderUnifiedCatalog();}};
  });
})();
