'use strict';

(() => {
  const Admin = window.Admin;
  const base = '/api/academic/admissions';
  const requestStates = { moi:'Mới tiếp nhận', dang_tim:'Đang tìm người dạy', da_de_xuat:'Đã gửi đề xuất', da_chot:'Hai bên đã đồng ý', huy:'Đã hủy' };
  const registrationStates = { cho_xep_lop:'Chờ Admin xếp lớp', dang_hoc:'Đang học', hoan_thanh:'Hoàn thành', thoi_hoc:'Thôi học' };
  const responseStates = { cho:'Chờ phản hồi', dong_y:'Đồng ý', tu_choi:'Từ chối' };
  const units = { theo_buoi:'buổi', theo_gio:'giờ', theo_khoa:'khóa', theo_thang:'tháng' };
  let template;
  let selectedRequest = null;
  let currentStep = 0;
  let requestFilters = { q:'', trangThai:'', page:1 };
  let registrationFilters = { q:'', trangThai:'', page:1 };
  let requestRows = [];
  let candidates = [];
  let matchVersion = 0;
  let stepVersion = 0;

  function can(action) {
    return Admin.user?.role === 'ADMIN' || Admin.user?.permissions.some(permission => permission.functionName === 'TiepNhanYeuCau' && permission[action]);
  }

  function stateTag(value, labels) {
    return Admin.tag(labels[value] || value, ['dong_y', 'da_chot', 'dang_hoc', 'hoan_thanh'].includes(value) ? 'ok' : ['tu_choi', 'huy', 'thoi_hoc'].includes(value) ? 'r' : 'w');
  }

  function budget(request) {
    if (request.nganSachMin == null && request.nganSachMax == null) return 'Chưa xác định';
    const range = `${request.nganSachMin == null ? 'Đến' : Admin.money(request.nganSachMin)}${request.nganSachMin != null && request.nganSachMax != null ? ' –' : ''} ${request.nganSachMax == null ? 'trở lên' : Admin.money(request.nganSachMax)}`;
    return `${range} / ${units[request.donViNganSach] || 'chưa rõ đơn vị'}`;
  }

  function schedule(slots) {
    return slots.map(slot => `${Number(slot.thu) === 8 ? 'CN' : `T${slot.thu}`} ${slot.gioBatDau.slice(0, 5)}–${slot.gioKetThuc.slice(0, 5)}`).join(', ') || 'Chưa có khung giờ';
  }

  function filterForm(id, filters, labels) {
    return `<form id="${id}" class="admissions-filters"><label>Tìm kiếm<input name="q" value="${Admin.esc(filters.q)}" placeholder="Mã, học viên hoặc phụ huynh" maxlength="150"></label>
      <label>Trạng thái<select name="trangThai"><option value="">Tất cả</option>${Object.entries(labels).map(([value, label]) => `<option value="${value}" ${filters.trangThai === value ? 'selected' : ''}>${Admin.esc(label)}</option>`).join('')}</select></label><button class="btn" type="submit">Lọc danh sách</button></form>`;
  }

  function bindFilter(id, filters, reload) {
    Admin.$(id).addEventListener('submit', event => {
      event.preventDefault();
      Object.assign(filters, Object.fromEntries(new FormData(event.target)), { page:1 });
      Admin.busy(event.submitter, reload);
    });
  }

  function bindPage(container, filters, reload) {
    container.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => Admin.busy(button, async () => {
      filters.page = Number(button.dataset.page);
      await reload();
    })));
  }

  async function loadRequests() {
    const data = await Admin.api(`${base}/requests?${Admin.query(requestFilters)}`);
    if (!Admin.$('reqRows')) return;
    requestRows = data.items;
    Admin.$('reqRows').innerHTML = data.items.map(row => `<tr>
      <td><b>${Admin.esc(row.maYc)}</b></td><td><b>${Admin.esc(row.tenHocVien || row.hoTenLienHe || 'Khách tư vấn')}</b>${row.maHv ? `<br><small>${Admin.esc(row.maHv)}</small>` : ''}</td>
      <td>${Admin.esc(row.tenPhuHuynh || row.hoTenLienHe || '—')}<br><small>${Admin.esc(row.sdtLienHe || row.sdtPhuHuynh || '—')}</small></td>
      <td>${Admin.esc(row.tenMonHoc)} · ${Admin.esc(row.tenCapLop)}</td><td>${Admin.esc(schedule(row.lichYeuCau))}</td>
      <td>${Admin.esc(row.tenKhuVuc || 'Chưa chọn')}</td><td>${stateTag(row.trangThai, requestStates)}</td>
      <td><div class="row">${!['huy', 'da_chot'].includes(row.trangThai) ? Admin.button('Tìm người dạy', 'match-request', row.id, 'p') : ''}${Admin.button('Chi tiết', 'request-detail', row.id)}</div></td></tr>`).join('') || '<tr><td colspan="8" class="admin-empty">Chưa có yêu cầu học. Bấm “Tiếp nhận yêu cầu mới” để nhập nhu cầu.</td></tr>';
    const pager = Admin.$('admissionsRequestPager');
    pager.innerHTML = Admin.pager(data);
    bindPage(pager, requestFilters, loadRequests);
    Admin.$('reqRows').querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => Admin.busy(button, async () => {
      if (button.dataset.action === 'request-detail') await requestDetail(Number(button.dataset.id));
      else {
        selectedRequest = can('edit')
          ? await Admin.api(`${base}/requests/${button.dataset.id}/receive`, 'POST', {})
          : await Admin.api(`${base}/requests/${button.dataset.id}`);
        await intakeStep(1);
      }
    })));
  }

  async function intakeStep(index) {
    if (!Admin.$('staff-intake')) return;
    currentStep = index;
    stepVersion++;
    document.querySelectorAll('#staff-intake .intake-step').forEach((element, position) => { element.style.display = position === index ? 'block' : 'none'; });
    document.querySelectorAll('#intakeWF .ws').forEach((element, position) => {
      element.classList.toggle('on', position === index);
      element.setAttribute('aria-current', position === index ? 'step' : 'false');
    });
    try {
      if (index === 0) await loadRequests();
      if (index === 1) {
        const rows = requestRows.filter(row => !['huy', 'da_chot'].includes(row.trangThai));
        if (selectedRequest && !rows.some(row => row.id === selectedRequest.id)) rows.unshift(selectedRequest);
        Admin.$('matchReqSel').innerHTML = '<option value="">Chọn yêu cầu học</option>' + rows.map(row =>
          `<option value="${row.id}" ${row.id === selectedRequest?.id ? 'selected' : ''}>${Admin.esc(row.maYc)} · ${Admin.esc(row.tenHocVien || row.hoTenLienHe || 'Khách tư vấn')}</option>`).join('');
        await runMatch();
      }
      if (index === 2 || index === 3) await loadProposals(1);
    } catch (error) { Admin.notify(error.message, true); }
  }

  async function runMatch() {
    const version = ++matchVersion;
    const id = Number(Admin.$('matchReqSel').value);
    const body = Admin.$('matchBody');
    body.innerHTML = '<tr><td colspan="5" class="admin-empty">Đang đối chiếu chuyên môn và lịch…</td></tr>';
    if (!id) {
      selectedRequest = null;
      Admin.$('matchRegion').value = '';
      Admin.$('budgetSel').value = '';
      body.innerHTML = '<tr><td colspan="5" class="admin-empty">Chọn yêu cầu từ bước 1 để tìm người dạy.</td></tr>';
      return;
    }
    try {
      const [request, rows] = await Promise.all([Admin.api(`${base}/requests/${id}`), Admin.api(`${base}/requests/${id}/candidates`)]);
      if (version !== matchVersion || !body.isConnected) return;
      selectedRequest = request;
      candidates = rows;
      Admin.$('matchRegion').value = request.tenKhuVuc || 'Không giới hạn khu vực';
      Admin.$('budgetSel').value = budget(request);
      Admin.$('matchTitle').textContent = `Ứng viên phù hợp · ${request.tenMonHoc} · ${request.tenCapLop}`;
      body.innerHTML = rows.map(row => `<tr><td><b>${Admin.esc(row.hoTen)}</b><br><small>${Admin.esc(row.donViCongTac || '')} · ${row.kinhNghiemNam} năm kinh nghiệm</small></td>
        <td>${Admin.esc(request.tenMonHoc)} · ${Admin.esc(request.tenCapLop)}<br><small>${Admin.esc(row.gioiThieu || '')}</small></td>
        <td>${Admin.esc(row.khuVucs.map(region => region.tenKhuVuc).join(', ') || 'Chưa khai báo')}</td>
        <td>${Admin.tag('Khớp tất cả khung giờ', 'ok')}<br><small>${Admin.esc(schedule(request.lichYeuCau))}</small>${row.nganSachCanThoaThuan ? '<br><small>Ngân sách cần đối chiếu theo đúng đơn vị khi tư vấn.</small>' : ''}</td>
        <td>${can('add') ? Admin.button('Đề xuất', 'propose', row.id, 'p') : 'Chỉ xem'}</td></tr>`).join('') || '<tr><td colspan="5" class="admin-empty">Chưa có người dạy khớp đầy đủ môn, khối, hình thức, khu vực và lịch yêu cầu.</td></tr>';
      body.querySelectorAll('[data-action="propose"]').forEach(button => button.addEventListener('click', () => propose(Number(button.dataset.id))));
    } catch (error) {
      if (version !== matchVersion || !body.isConnected) return;
      body.innerHTML = `<tr><td colspan="5" class="form-error">${Admin.esc(error.message)}</td></tr>`;
    }
  }

  function propose(id) {
    const candidate = candidates.find(row => row.id === id);
    const request = selectedRequest;
    if (!candidate || !request) return;
    Admin.formDialog({ title:`Đề xuất ${candidate.hoTen}`, submit:'Gửi đề xuất cho hai bên', fields:[
      { name:'request', label:'Yêu cầu', value:`${request.maYc} · ${request.tenHocVien || request.hoTenLienHe}`, disabled:true },
      { name:'hocPhiDeXuat', label:'Học phí đề xuất trọn khóa (đ)', type:'number', min:0, max:999999999999, nullable:true },
      { name:'ghiChu', label:'Ghi chú đề xuất', type:'textarea', maxLength:255, nullable:true }
    ], onSubmit:async data => {
      await Admin.api(`${base}/requests/${request.id}/proposals`, 'POST', { nhanSuId:id, hocPhiDeXuat:data.hocPhiDeXuat, ghiChu:data.ghiChu });
      Admin.notify('Đã gửi đề xuất. Đang chờ người dạy và phụ huynh phản hồi.');
      await intakeStep(2);
    }});
  }

  async function loadProposals(page) {
    const step = currentStep;
    const version = stepVersion;
    const handoff = step === 3;
    const data = await Admin.api(`${base}/proposals?${Admin.query({ page, ...(handoff ? { trangThai:'san_sang' } : {}) })}`);
    const body = Admin.$(handoff ? 'createClassBody' : 'proposalBody');
    if (!body || version !== stepVersion) return;
    body.innerHTML = data.items.map(row => {
      const request = `${Admin.esc(row.maYc)}<br><small>${Admin.esc(row.tenHocVien || row.hoTenLienHe || '')}</small>`;
      const ready = row.ttNhanSu === 'dong_y' && row.ttPhuHuynh === 'dong_y' && row.trangThaiYeuCau === 'da_chot';
      const detail = Admin.button('Chi tiết', 'proposal-detail', row.id);
      if (handoff) return `<tr><td><b>DX-${row.id}</b></td><td>${request}</td><td><b>${Admin.esc(row.tenNguoiDay)}</b></td>
        <td>${Admin.tag(row.maLop ? `Đã có lớp ${row.maLop}` : '2 bên đã đồng ý', 'ok')}</td><td><div class="row">${detail}${Admin.user.role === 'ADMIN' ? Admin.button('Chuyển Admin xếp lớp', 'handoff-admin', row.id, 'purple') : '<span class="tag i">Chờ Admin xếp lớp</span>'}</div></td></tr>`;
      return `<tr><td><b>DX-${row.id}</b></td><td>${request}</td><td><b>${Admin.esc(row.tenNguoiDay)}</b></td>
        <td>${stateTag(row.ttNhanSu, responseStates)}<br><small>${Admin.esc(row.lyDoTuChoiNhanSu || row.nhanSuPhanHoiLuc || '')}</small></td>
        <td>${stateTag(row.ttPhuHuynh, responseStates)}<br><small>${Admin.esc(row.lyDoTuChoiPhuHuynh || row.phuHuynhPhanHoiLuc || '')}</small></td>
        <td><div class="row">${detail}${ready ? Admin.button('Chuyển bước 4', 'handoff', row.id, 'p') : ''}</div></td></tr>`;
    }).join('') || `<tr><td colspan="${handoff ? 5 : 6}" class="admin-empty">${handoff ? 'Chưa có đề xuất được cả hai bên đồng ý.' : 'Chưa có đề xuất người dạy.'}</td></tr>`;
    const pager = Admin.$(handoff ? 'admissionsHandoffPager' : 'admissionsProposalPager');
    pager.innerHTML = Admin.pager(data);
    pager.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => Admin.busy(button, () => loadProposals(Number(button.dataset.page)))));
    body.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
      const row = data.items.find(item => String(item.id) === button.dataset.id);
      if (button.dataset.action === 'handoff') intakeStep(3);
      else if (button.dataset.action === 'handoff-admin') window.setPortal('admin', true, 'admin-courses');
      else Admin.dialog(`Chi tiết đề xuất DX-${row.id}`, `<div class="dialog-body"><dl class="admissions-detail">
        <dt>Yêu cầu</dt><dd>${Admin.esc(row.maYc)}</dd><dt>Người dạy</dt><dd>${Admin.esc(row.tenNguoiDay)}</dd>
        <dt>Học phí đề xuất</dt><dd>${row.hocPhiDeXuat == null ? 'Chưa chốt' : `${Admin.money(row.hocPhiDeXuat)} / ${Admin.esc(units[row.cachThuPhiDeXuat] || 'chưa rõ đơn vị')}`}</dd>
        <dt>Người dạy phản hồi</dt><dd>${stateTag(row.ttNhanSu, responseStates)} ${Admin.esc(row.nhanSuPhanHoiLuc || '')}<br>${Admin.esc(row.lyDoTuChoiNhanSu || '')}</dd>
        <dt>Phụ huynh phản hồi</dt><dd>${stateTag(row.ttPhuHuynh, responseStates)} ${Admin.esc(row.phuHuynhPhanHoiLuc || '')}<br>${Admin.esc(row.lyDoTuChoiPhuHuynh || '')}</dd>
        <dt>Ghi chú</dt><dd>${Admin.esc(row.ghiChu || '—')}</dd></dl></div>`);
    }));
  }

  async function requestDetail(id) {
    const request = await Admin.api(`${base}/requests/${id}`);
    Admin.dialog(`Yêu cầu ${request.maYc}`, `<div class="dialog-body"><dl class="admissions-detail">
      <dt>Học viên</dt><dd>${Admin.esc(request.tenHocVien || 'Chưa liên kết hồ sơ')}</dd>
      <dt>Liên hệ</dt><dd>${Admin.esc(request.hoTenLienHe || request.tenPhuHuynh || '')} · ${Admin.esc(request.sdtLienHe || request.sdtPhuHuynh || '')}</dd>
      <dt>Môn / Khối</dt><dd>${Admin.esc(request.tenMonHoc)} · ${Admin.esc(request.tenCapLop)}</dd>
      <dt>Lịch yêu cầu</dt><dd>${Admin.esc(schedule(request.lichYeuCau))}</dd>
      <dt>Ngân sách</dt><dd>${Admin.esc(budget(request))}</dd><dt>Mục tiêu</dt><dd>${Admin.esc(request.mucTieu || '—')}</dd>
      <dt>Ghi chú</dt><dd>${Admin.esc(request.ghiChu || '—')}</dd><dt>Nhân viên tiếp nhận</dt><dd>${Admin.esc(request.tenNhanVien || 'Chưa phân công')}</dd>
      <dt>Trạng thái</dt><dd>${stateTag(request.trangThai, requestStates)}</dd></dl>
      ${can('edit') && ['moi', 'dang_tim'].includes(request.trangThai) ? '<button class="btn p" id="admissions-edit-request">Cập nhật nhu cầu / Liên kết học viên</button>' : ''}</div>`);
    Admin.$('admissions-edit-request')?.addEventListener('click', () => openRequestForm(request));
  }

  function slotControl(slot = {}) {
    const row = document.createElement('div');
    row.className = 'admissions-slot';
    row.innerHTML = `<label>Thứ<select name="slotThu">${[2, 3, 4, 5, 6, 7, 8].map(day => `<option value="${day}" ${Number(slot.thu) === day ? 'selected' : ''}>${day === 8 ? 'Chủ nhật' : `Thứ ${day}`}</option>`).join('')}</select></label>
      <label>Bắt đầu<input name="slotStart" type="time" required value="${Admin.esc(slot.gioBatDau?.slice(0, 5) || '')}"></label>
      <label>Kết thúc<input name="slotEnd" type="time" required value="${Admin.esc(slot.gioKetThuc?.slice(0, 5) || '')}"></label>
      <button type="button" class="btn sm danger" aria-label="Xóa khung giờ">Xóa</button>`;
    row.querySelector('button').addEventListener('click', () => {
      if (row.parentElement.children.length > 1) row.remove();
      else Admin.$('dialog-error').textContent = 'Cần ít nhất một khung giờ yêu cầu.';
    });
    return row;
  }

  async function openRequestForm(request = null) {
    try {
      const lookups = await Admin.api(`${base}/lookups`);
      const value = key => Admin.esc(request?.[key] ?? '');
      const select = (name, label, rows, required = false) => `<label>${label}<select name="${name}" ${required ? 'required' : ''}><option value="">Chọn ${label.toLowerCase()}</option>${Admin.options(rows, 'id', 'ten', request?.[name])}</select></label>`;
      Admin.dialog(request ? `Cập nhật ${request.maYc}` : 'Tiếp nhận yêu cầu học mới', `<form id="admissions-request-form"><div class="dialog-body admissions-form">
        <div class="admissions-student-search"><label>Tìm hồ sơ học viên<input id="admissions-student-query" placeholder="Nhập tên hoặc mã học viên"></label><button type="button" class="btn" id="admissions-find-student">Tìm hồ sơ</button></div>
        <label>Học viên đã có hồ sơ<select name="hocVienId" id="admissions-student-select"></select><small>Khách tư vấn có thể để trống và nhập thông tin liên hệ. Liên kết học viên trước khi gửi đề xuất.</small></label>
        <div class="admissions-fields"><label>Người liên hệ<input name="hoTenLienHe" maxlength="150" value="${value('hoTenLienHe')}"></label><label>Số điện thoại<input name="sdtLienHe" maxlength="20" value="${value('sdtLienHe')}"></label></div>
        <label>Email liên hệ<input name="emailLienHe" type="email" maxlength="150" value="${value('emailLienHe')}"></label>
        <div class="admissions-fields">${select('monHocId', 'Môn học', lookups.subjects, true)}${select('capLopId', 'Cấp lớp', lookups.grades, true)}
        ${select('khuVucId', 'Khu vực', lookups.regions)}${select('coSoMongMuonId', 'Cơ sở mong muốn', lookups.campuses)}</div>
        <div class="admissions-fields"><label>Loại lớp<select name="loaiLopMongMuon">${[['kem_1_1', 'Kèm 1:1'], ['kem_1_2', 'Kèm 1:2'], ['nhom', 'Lớp nhóm']].map(([id, ten]) => `<option value="${id}" ${request?.loaiLopMongMuon === id ? 'selected' : ''}>${ten}</option>`).join('')}</select></label>
        <label>Hình thức<select name="hinhThuc">${[['offline', 'Trực tiếp'], ['online', 'Trực tuyến'], ['ca_hai', 'Cả hai']].map(([id, ten]) => `<option value="${id}" ${request?.hinhThuc === id ? 'selected' : ''}>${ten}</option>`).join('')}</select></label></div>
        <label>Địa chỉ học<input name="diaChiHoc" maxlength="255" value="${value('diaChiHoc')}"></label>
        <div class="admissions-fields"><label>Ngân sách từ (đ)<input name="nganSachMin" type="number" min="0" max="999999999999" step="1" value="${value('nganSachMin')}"></label><label>Ngân sách đến (đ)<input name="nganSachMax" type="number" min="0" max="999999999999" step="1" value="${value('nganSachMax')}"></label></div>
        <label>Đơn vị ngân sách<select name="donViNganSach">${[['theo_khoa', 'Trọn khóa'], ['theo_buoi', 'Mỗi buổi để tư vấn'], ['theo_gio', 'Mỗi giờ để tư vấn']].map(([id, ten]) => `<option value="${id}" ${request?.donViNganSach === id ? 'selected' : ''}>${ten}</option>`).join('')}</select></label>
        <fieldset><legend>Khung giờ yêu cầu</legend><div id="admissions-slots"></div><button type="button" class="btn sm" id="admissions-add-slot">+ Thêm khung giờ</button></fieldset>
        <label>Mục tiêu học tập<textarea name="mucTieu" maxlength="5000" rows="3">${value('mucTieu')}</textarea></label>
        <label>Ghi chú<textarea name="ghiChu" maxlength="5000" rows="3">${value('ghiChu')}</textarea></label>
        <label>Nguồn tiếp nhận<select name="nguon">${[['truc_tiep', 'Trực tiếp'], ['dien_thoai', 'Điện thoại'], ['website', 'Website'], ['gioi_thieu', 'Giới thiệu']].map(([id, ten]) => `<option value="${id}" ${request?.nguon === id ? 'selected' : ''}>${ten}</option>`).join('')}</select></label>
        <p id="dialog-error" class="form-error" role="alert"></p></div><div class="dialog-foot"><button type="button" class="btn" id="admissions-cancel">Hủy</button><button class="btn p" id="admissions-save">${request ? 'Lưu nhu cầu' : 'Tiếp nhận yêu cầu'}</button></div></form>`);
      const form = Admin.$('admissions-request-form');
      let students = lookups.students;
      const fillStudents = () => {
        const chosen = form.elements.hocVienId.value || String(request?.hocVienId || '');
        const options = students.map(student => ({ id:student.id, ten:`${student.maHv} · ${student.hoTen} · ${student.tenPhuHuynh}` }));
        if (chosen && !options.some(student => String(student.id) === chosen)) options.unshift({ id:chosen, ten:request?.tenHocVien || 'Học viên đang chọn' });
        form.elements.hocVienId.innerHTML = '<option value="">Khách tư vấn chưa liên kết hồ sơ</option>' + Admin.options(options, 'id', 'ten', chosen);
      };
      fillStudents();
      form.elements.hocVienId.addEventListener('change', () => {
        const student = students.find(row => String(row.id) === form.elements.hocVienId.value);
        if (!student) return;
        form.elements.hoTenLienHe.value = student.tenPhuHuynh || '';
        form.elements.sdtLienHe.value = student.sdtPhuHuynh || '';
        form.elements.emailLienHe.value = student.emailPhuHuynh || '';
        form.elements.capLopId.value = student.capLopId || '';
        form.elements.khuVucId.value = student.khuVucId || '';
      });
      Admin.$('admissions-find-student').addEventListener('click', event => Admin.busy(event.currentTarget, async () => {
        const result = await Admin.api(`${base}/lookups?${Admin.query({ q:Admin.$('admissions-student-query').value.trim() })}`);
        if (!form.isConnected) return;
        students = result.students;
        fillStudents();
      }));
      const slots = Admin.$('admissions-slots');
      (request?.lichYeuCau.length ? request.lichYeuCau : [{}]).forEach(slot => slots.append(slotControl(slot)));
      Admin.$('admissions-add-slot').addEventListener('click', () => {
        if (slots.children.length < 21) slots.append(slotControl());
      });
      Admin.$('admissions-cancel').addEventListener('click', () => { if (Admin.$('admin-dialog').dataset.busy !== 'true') Admin.$('admin-dialog').close(); });
      form.addEventListener('submit', async event => {
        event.preventDefault();
        const dialog = Admin.$('admin-dialog');
        if (dialog.dataset.busy === 'true') return;
        const body = Object.fromEntries(new FormData(form));
        delete body.slotThu; delete body.slotStart; delete body.slotEnd;
        for (const key of ['hocVienId', 'monHocId', 'capLopId', 'khuVucId', 'coSoMongMuonId', 'nganSachMin', 'nganSachMax']) body[key] = body[key] === '' ? null : Number(body[key]);
        for (const key of ['hoTenLienHe', 'sdtLienHe', 'emailLienHe', 'diaChiHoc', 'mucTieu', 'ghiChu']) body[key] = body[key].trim() || null;
        body.lichYeuCau = [...slots.children].map(row => ({ thu:Number(row.querySelector('[name="slotThu"]').value), gioBatDau:row.querySelector('[name="slotStart"]').value, gioKetThuc:row.querySelector('[name="slotEnd"]').value }));
        dialog.dataset.busy = 'true';
        Admin.$('admissions-save').disabled = true;
        Admin.$('dialog-error').textContent = '';
        try {
          await Admin.api(`${base}/requests${request ? `/${request.id}` : ''}`, request ? 'PUT' : 'POST', body);
          dialog.close();
          selectedRequest = null;
          Admin.notify(request ? 'Đã cập nhật nhu cầu học.' : 'Đã tiếp nhận yêu cầu học mới.');
          await intakeStep(0);
        } catch (error) { if (form.isConnected) Admin.$('dialog-error').textContent = error.message; }
        finally { dialog.dataset.busy = 'false'; if (form.isConnected) Admin.$('admissions-save').disabled = false; }
      });
    } catch (error) { Admin.notify(error.message, true); }
  }

  Admin.register('staff-intake', async () => {
    if (!template) {
      const response = await fetch('/admin-assets/admissions.html');
      if (!response.ok) throw new Error('Không tải được giao diện tuyển sinh.');
      template = await response.text();
    }
    selectedRequest = null;
    requestRows = [];
    candidates = [];
    matchVersion++;
    return { title:'Tuyển sinh & Ghép người dạy', html:template, bind() {
      Admin.$('admissionsRequestFilters').innerHTML = filterForm('admissions-request-filter', requestFilters, requestStates);
      bindFilter('admissions-request-filter', requestFilters, loadRequests);
      document.querySelector('[onclick="openRequestForm()"]').hidden = !can('add');
      document.querySelectorAll('#intakeWF .ws').forEach((element, index) => {
        element.setAttribute('role', 'button'); element.tabIndex = 0;
        element.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); intakeStep(index); } });
      });
      intakeStep(0);
    }};
  });

  Admin.register('staff-registrations', async () => {
    const data = await Admin.api(`${base}/registrations?${Admin.query(registrationFilters)}`);
    const columns = [
      { label:'Mã đăng ký', render:row => `<b>DK-${row.id}</b>` },
      { label:'Học viên', render:row => `<b>${Admin.esc(row.tenHocVien)}</b><br><small>${Admin.esc(row.maHv)}</small>` },
      { label:'Phụ huynh · SĐT', render:row => `${Admin.esc(row.tenPhuHuynh)}<br><small>${Admin.esc(row.sdtPhuHuynh || '—')}</small>` },
      { label:'Khóa học', key:'tenKhoa' }, { label:'Học phí trọn khóa', render:row => Admin.money(row.hocPhiSnapshot) },
      { label:'Ngày đăng ký', render:row => Admin.esc(Admin.date(row.ngayDangKy)) },
      { label:'Trạng thái', render:row => stateTag(row.trangThai, registrationStates) }, { label:'Lớp', render:row => Admin.esc(row.maLop || 'Chờ Admin xếp lớp') }
    ];
    return { title:'Đăng ký khóa học', html:Admin.heading('Đăng ký khóa học', '') +
      `<div class="card">${filterForm('admissions-registration-filter', registrationFilters, registrationStates)}${Admin.table(columns, data.items, 'Chưa có đăng ký khóa học.')}${Admin.pager(data)}</div>`, bind() {
        bindFilter('admissions-registration-filter', registrationFilters, Admin.render);
        Admin.bindPager(page => { registrationFilters.page = page; Admin.render(); });
      }};
  });

  window.intakeStep = intakeStep;
  window.openRequestForm = () => openRequestForm();
  window.runMatch = runMatch;
})();
