'use strict';
(() => {
  const Admin = window.Admin;
  const catalog = '/api/administration/catalog';
  let courses = [], registrations = [], classes = [], currentCatalog = 'rooms', catalogData = {};
  const empty = Admin.emptyRow, kpi = Admin.kpi;
  const activeOptions = [{ value:1,label:'Đang mở đăng ký' },{ value:0,label:'Ngừng nhận đăng ký' }];
  const classTypeLabels = { kem_1_1:'Kèm 1:1', kem_1_2:'Kèm 1:2', nhom:'Lớp nhóm' };
  const classStatusLabels = { sap_bat_dau:'Sắp bắt đầu', dang_hoc:'Đang học', tam_dung:'Tạm dừng', hoan_thanh:'Hoàn thành', huy:'Đã hủy' };

  let acTab = 'enrollments', acEnrollFilter = 'all', acEnrollQ = '', acClassFilter = 'all', acClassQ = '', acCourseQ = '';

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

  window.openAdminClassModal = async function(id) {
    try {
      const [cat, staffList, existing] = await Promise.all([
        Admin.api(catalog),
        Admin.api('/api/administration/staff'),
        id ? Admin.api('/api/academic/classes/' + id) : Promise.resolve(null),
      ]);
      const teachers = staffList.filter(s => s.nhanSuId && s.status === 'active' && (s.phanLoai?.includes('giao_vien') || s.phanLoai?.includes('gia_su')));
      if (!teachers.length) {
        toast('Chưa có Giáo viên / Gia sư đang hoạt động. Hãy tạo hoặc kích hoạt hồ sơ nhân sự trước.', 'err');
        return;
      }
      const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' });
      const val = (k, def = '') => Admin.esc(existing?.[k] ?? def);
      const selOpt = (rows, vk, lk, cur, emptyLbl = '') =>
        (emptyLbl ? `<option value="">${Admin.esc(emptyLbl)}</option>` : '') +
        rows.map(r => `<option value="${Admin.esc(r[vk])}" ${String(r[vk]) === String(cur ?? '') ? 'selected' : ''}>${Admin.esc(r[lk])}</option>`).join('');

      Admin.dialog(existing ? `Sửa lớp ${existing.maLop}` : 'Tạo Lớp học mới', `
        <form id="adminClassForm">
          <div class="dialog-body">
            <div class="ec-form-grid">
              <label>Mã lớp <span style="color:var(--err)">*</span><input name="maLop" required maxlength="30" placeholder="VD: EC26-LOP01" value="${val('maLop')}" ${existing ? 'disabled' : ''}></label>
              <label>Tên lớp học <span style="color:var(--err)">*</span><input name="tenLop" required maxlength="150" placeholder="VD: Toán 9 — Luyện thi A1" value="${val('tenLop')}"></label>
              <label>Thuộc Khóa học<select name="khoaHocId">${selOpt(courses, 'id', 'ten', existing?.khoaHocId, 'Chưa gắn khóa học')}</select></label>
              <label>Loại lớp <span style="color:var(--err)">*</span><select name="loaiLop">${Object.entries(classTypeLabels).map(([k, l]) => `<option value="${k}" ${(existing?.loaiLop || 'nhom') === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
              <label>Môn học <span style="color:var(--err)">*</span><select name="monHocId" required>${selOpt(cat.subjects || [], 'id', 'ten', existing?.monHocId, 'Chọn môn học')}</select></label>
              <label>Cấp lớp <span style="color:var(--err)">*</span><select name="capLopId" required>${selOpt(cat['grade-levels'] || [], 'id', 'ten', existing?.capLopId, 'Chọn cấp lớp')}</select></label>
              <label>Người dạy chính <span style="color:var(--err)">*</span><select name="nguoiDayChinhId" required>${selOpt(teachers.map(t => ({ id:t.nhanSuId, label:`${t.hoTen} (${t.phanLoai?.includes('giao_vien') ? 'GV' : 'Gia sư'})` })), 'id', 'label', existing?.nguoiDayChinhId, 'Chọn người dạy chính')}</select></label>
              <label>Hình thức <span style="color:var(--err)">*</span><select name="hinhThuc" id="acClassMode"><option value="offline" ${(existing?.hinhThuc || 'offline') === 'offline' ? 'selected' : ''}>Offline tại cơ sở / tại nhà</option><option value="online" ${existing?.hinhThuc === 'online' ? 'selected' : ''}>Online trực tuyến</option></select></label>
              <label id="acWrapCampus">Cơ sở đào tạo<select name="coSoId">${selOpt(cat.campuses || [], 'id', 'tenCoSo', existing?.coSoId, 'Không gắn cơ sở / Tại nhà')}</select></label>
              <label id="acWrapRoom">Phòng học<input name="phongHoc" maxlength="50" placeholder="VD: P201" value="${val('phongHoc')}"></label>
              <label id="acWrapLink" class="full" style="display:${existing?.hinhThuc === 'online' ? '' : 'none'}">Link phòng học Online<input name="linkHoc" maxlength="255" placeholder="https://meet.google.com/…" value="${val('linkHoc')}"></label>
              <label>Sĩ số tối thiểu <span style="color:var(--err)">*</span><input name="siSoToiThieu" type="number" min="1" max="100" required value="${val('siSoToiThieu', 1)}"></label>
              <label>Sĩ số tối đa <span style="color:var(--err)">*</span><input name="siSoToiDa" type="number" min="1" max="100" required value="${val('siSoToiDa', 12)}"></label>
              <label>Ngày bắt đầu <span style="color:var(--err)">*</span><input name="ngayBatDau" type="date" required value="${val('ngayBatDau', today)}"></label>
              <label>Ngày kết thúc (tùy chọn)<input name="ngayKetThuc" type="date" value="${val('ngayKetThuc')}"></label>
              <label class="full">Trạng thái lớp<select name="trangThai">${Object.entries(classStatusLabels).map(([k, l]) => `<option value="${k}" ${(existing?.trangThai || 'dang_hoc') === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
            </div>
            <p id="dialog-error" class="form-error" role="alert"></p>
          </div>
          <div class="dialog-foot">
            <button type="button" class="btn sm" data-close-dialog="1">Hủy</button>
            <button type="submit" class="btn p sm" id="dialog-submit">${existing ? 'Lưu thay đổi' : 'Tạo lớp học'}</button>
          </div>
        </form>
      `);
      const form = Admin.$('adminClassForm');
      const syncMode = () => {
        const isOn = form.elements.hinhThuc.value === 'online';
        Admin.$('acWrapLink').style.display = isOn ? '' : 'none';
        Admin.$('acWrapCampus').style.display = isOn ? 'none' : '';
        Admin.$('acWrapRoom').style.display = isOn ? 'none' : '';
      };
      form.elements.hinhThuc.addEventListener('change', syncMode);
      syncMode();
      form.addEventListener('submit', async ev => {
        ev.preventDefault();
        const raw = Object.fromEntries(new FormData(form));
        const body = {
          tenLop: raw.tenLop,
          loaiLop: raw.loaiLop,
          khoaHocId: raw.khoaHocId ? Number(raw.khoaHocId) : null,
          monHocId: Number(raw.monHocId),
          capLopId: Number(raw.capLopId),
          nguoiDayChinhId: Number(raw.nguoiDayChinhId),
          hinhThuc: raw.hinhThuc,
          coSoId: raw.hinhThuc === 'offline' && raw.coSoId ? Number(raw.coSoId) : null,
          phongHoc: raw.hinhThuc === 'offline' && raw.phongHoc ? raw.phongHoc : null,
          linkHoc: raw.hinhThuc === 'online' && raw.linkHoc ? raw.linkHoc : null,
          siSoToiThieu: Number(raw.siSoToiThieu),
          siSoToiDa: Number(raw.siSoToiDa),
          ngayBatDau: raw.ngayBatDau,
          ngayKetThuc: raw.ngayKetThuc || null,
          trangThai: raw.trangThai,
        };
        if (!existing) {
          body.maLop = raw.maLop;
          body.cachThuPhi = 'theo_khoa';
          body.cachTinhLuong = 'theo_gio';
        }
        Admin.$('dialog-error').textContent = '';
        await Admin.busy(Admin.$('dialog-submit'), async () => {
          await Admin.api('/api/academic/classes' + (existing ? '/' + existing.id : ''), existing ? 'PUT' : 'POST', body);
          Admin.$('admin-dialog').close();
          toast(existing ? 'Đã cập nhật lớp học.' : 'Đã tạo lớp học mới.', 'ok');
          acTab = 'classes';
          Admin.render();
        });
      });
    } catch (error) { toast(error.message, 'err'); }
  };

  window.openAdminClassDetail = async function(id) {
    try {
      const row = await Admin.api('/api/academic/classes/' + id);
      Admin.dialog(`${row.maLop} · ${row.tenLop}`, `<div class="dialog-body">
        <div class="ec-info-box">${Admin.esc(row.tenMonHoc)} · ${Admin.esc(row.tenCapLop)} · Dạy chính: <b>${Admin.esc(row.tenNguoiDayChinh || '—')}</b> · Sĩ số: <b>${row.siSoHienTai}/${row.siSoToiDa}</b></div>
        <h3 style="margin:8px 0 6px;font-size:13.5px">Học viên trong lớp (${(row.hocViens || []).length})</h3>
        ${Admin.table([{ key:'maHv', label:'Mã HV' }, { key:'hoTen', label:'Học viên' }, { key:'ngayVao', label:'Ngày vào' }, { key:'trangThai', label:'Trạng thái' }], row.hocViens || [], 'Chưa có học viên nào được xếp vào lớp.')}
        <h3 style="margin:12px 0 6px;font-size:13.5px">Lịch học định kỳ (${(row.lichHocs || []).length})</h3>
        ${Admin.table([{ label:'Thứ', render:r => Number(r.thu) === 8 ? 'CN' : 'Thứ ' + r.thu }, { key:'gioBatDau', label:'Bắt đầu' }, { key:'gioKetThuc', label:'Kết thúc' }, { key:'phongHoc', label:'Phòng' }], row.lichHocs || [], 'Chưa có lịch tuần.')}
      </div><div class="dialog-foot"><button type="button" class="btn sm" data-close-dialog="1">Đóng</button><button type="button" class="btn p sm" data-act="edit-class-from-detail" data-id="${row.id}">Sửa lớp học</button></div>`);
    } catch (error) { toast(error.message, 'err'); }
  };

  function fillEnrollmentClasses() {
    const selected = registrations.find(row => row.id === Number(Admin.$('aeEnrollmentSelect').value));
    const valid = classes.filter(row => row.khoaHocId === selected?.khoaHocId && ['sap_bat_dau','dang_hoc'].includes(row.trangThai));
    Admin.$('aeClassSelect').innerHTML = '<option value="">' + (valid.length ? 'Chọn lớp đúng khóa' : 'Chưa có lớp đang mở cho khóa này — tạo lớp trước') + '</option>' +
      valid.map(row => `<option value="${row.id}">${Admin.esc(row.maLop+' · '+row.tenLop)} (${row.siSoHienTai}/${row.siSoToiDa} HV)</option>`).join('');
  }
  window.openAdminEnrollModal = function(id) {
    const available = registrations.filter(row => ['cho_xep_lop','dang_hoc'].includes(row.trangThai));
    if (!available.length) { toast('Chưa có đăng ký khóa nào chờ xếp lớp.', 'info'); return; }
    Admin.$('aeEnrollmentSelect').innerHTML = available.map(row => `<option value="${row.id}" ${row.id===Number(id)?'selected':''}>DK-${row.id} · ${Admin.esc(row.hoTen+' · '+row.tenKhoa)}</option>`).join('');
    Admin.$('aeEnrollmentSelect').removeEventListener('change', fillEnrollmentClasses);
    Admin.$('aeEnrollmentSelect').addEventListener('change', fillEnrollmentClasses);
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

  function renderAdminCoursesTabContent() {
    const eq = acEnrollQ.trim().toLowerCase();
    const eRows = registrations.filter(r => {
      if (acEnrollFilter === 'cho_xep_lop' && r.trangThai !== 'cho_xep_lop') return false;
      if (acEnrollFilter === 'dang_hoc' && r.trangThai !== 'dang_hoc') return false;
      if (acEnrollFilter === 'other' && ['cho_xep_lop', 'dang_hoc'].includes(r.trangThai)) return false;
      if (eq && ![`DK-${r.id}`, r.hoTen, r.maHv, r.maKhoa, r.tenKhoa, r.maLop].join(' ').toLowerCase().includes(eq)) return false;
      return true;
    });
    Admin.$('adminEnrollmentsBody').innerHTML = eRows.map(row => `<tr>
      <td><b>DK-${row.id}</b></td>
      <td><b>${Admin.esc(row.hoTen)}</b><br><small>${Admin.esc(row.maHv)}</small></td>
      <td><span class="mono">${Admin.esc(row.maKhoa)}</span><br><small>${Admin.esc(row.tenKhoa)}</small></td>
      <td>${Admin.money(row.hocPhiSnapshot)}</td>
      <td>${Admin.tag(row.maHd ? ({ da_thanh_toan:'Đã thu đủ', mot_phan:'Thu một phần', chua_thanh_toan:'Chưa thu', nhap:'HĐ nháp' }[row.tinhTrangThu] || row.tinhTrangThu) : 'Chưa lập HĐ', row.tinhTrangThu === 'da_thanh_toan' ? 'ok' : 'w')}</td>
      <td>${row.maLop ? Admin.tag(row.maLop, 'purple') : Admin.tag('Chờ xếp lớp', 'r')}</td>
      <td>${Admin.esc(row.nguoiXep || '—')}<br><small>${Admin.esc(Admin.date(row.xepLuc))}</small></td>
      <td>${['cho_xep_lop','dang_hoc'].includes(row.trangThai) ? `<button type="button" class="btn sm p" data-act="open-enroll-modal" data-id="${row.id}">${row.maLop ? 'Đổi lớp' : 'Xếp lớp ngay'}</button>` : Admin.tag(row.trangThai)}</td>
    </tr>`).join('') || empty(8);

    const cq = acClassQ.trim().toLowerCase();
    const cRows = classes.filter(r => {
      if (acClassFilter === 'dang_hoc' && r.trangThai !== 'dang_hoc') return false;
      if (acClassFilter === 'sap_bat_dau' && r.trangThai !== 'sap_bat_dau') return false;
      if (acClassFilter === 'closed' && ['dang_hoc', 'sap_bat_dau'].includes(r.trangThai)) return false;
      if (cq && ![r.maLop, r.tenLop, r.tenKhoa, r.tenMonHoc, r.tenNguoiDayChinh].join(' ').toLowerCase().includes(cq)) return false;
      return true;
    });
    Admin.$('adminClassesBody').innerHTML = cRows.map(row => `<tr>
      <td><b>${Admin.esc(row.maLop)}</b><br><small>${Admin.esc(row.tenLop)}</small></td>
      <td>${Admin.esc(row.tenKhoa || 'Chưa gắn khóa')}<br><small>${Admin.esc(row.tenMonHoc)} · ${Admin.esc(row.tenCapLop)}</small></td>
      <td>${Admin.tag(classTypeLabels[row.loaiLop] || row.loaiLop, 'i')}<br><small>${row.hinhThuc === 'online' ? 'Online' : Admin.esc(row.tenCoSo || 'Offline')}</small></td>
      <td>${Admin.esc(row.tenNguoiDayChinh || '—')}</td>
      <td><b>${row.siSoHienTai}/${row.siSoToiDa}</b></td>
      <td>${Admin.tag(classStatusLabels[row.trangThai] || row.trangThai, row.trangThai === 'dang_hoc' ? 'ok' : (row.trangThai === 'huy' ? 'r' : 'w'))}</td>
      <td><div class="row"><button type="button" class="btn sm" data-act="open-class-detail" data-id="${row.id}">Chi tiết</button><button type="button" class="btn sm p" data-act="open-class-modal" data-id="${row.id}">Sửa</button></div></td>
    </tr>`).join('') || empty(7);

    const kq = acCourseQ.trim().toLowerCase();
    const kRows = courses.filter(r => !kq || [r.ma, r.ten, r.moTa].join(' ').toLowerCase().includes(kq));
    Admin.$('adminCoursesBody').innerHTML = kRows.map(row => `<tr>
      <td><b class="mono">${Admin.esc(row.ma)}</b></td>
      <td><b>${Admin.esc(row.ten)}</b></td>
      <td>${Admin.esc(row.moTa || '—')}</td>
      <td><b style="color:var(--ok)">${Admin.money(row.hocPhi)}</b></td>
      <td>${Admin.tag(row.isActive ? 'Đang mở ĐK' : 'Tạm đóng', row.isActive ? 'ok' : 'r')}</td>
      <td><button type="button" class="btn sm" data-act="open-course-modal" data-id="${row.id}">Chi tiết / Sửa</button></td>
    </tr>`).join('') || empty(6);
  }

  function switchAcTab(tab) {
    acTab = tab;
    Admin.$('acPaneEnrollments').hidden = tab !== 'enrollments';
    Admin.$('acPaneClasses').hidden = tab !== 'classes';
    Admin.$('acPaneCourses').hidden = tab !== 'courses';
    Admin.$('adminCoursesTabs')?.querySelectorAll('[data-ac-tab]').forEach(b => b.classList.toggle('on', b.dataset.acTab === tab));
  }

  Admin.register('admin-courses', async () => {
    const data = await Promise.all([
      Admin.api(catalog + '/courses'),
      Admin.api('/api/academic/classes/registrations'),
      Admin.api('/api/academic/classes')
    ]);
    [courses, registrations, classes] = data;
    return {
      title: 'Khóa học, Lớp học & Xếp lớp',
      html: Admin.view('admin-courses'),
      bind() {
        const pendingRegs = registrations.filter(r => r.trangThai === 'cho_xep_lop').length;
        const activeCourses = courses.filter(r => r.isActive).length;
        const activeClasses = classes.filter(r => ['dang_hoc', 'sap_bat_dau'].includes(r.trangThai)).length;
        const totalStudents = classes.reduce((a, r) => a + Number(r.siSoHienTai || 0), 0);

        Admin.$('adminCoursesKpis').innerHTML = [
          kpi('Đăng ký chờ xếp lớp', String(pendingRegs), `Tổng ${registrations.length} lượt đăng ký`, pendingRegs ? 'w' : 'g', true),
          kpi('Lớp học hoạt động', String(activeClasses), `Tổng ${classes.length} lớp học`, 'i'),
          kpi('Khóa học đang mở', String(activeCourses), `Tổng ${courses.length} khóa trọn gói`, 'g'),
          kpi('Tổng sĩ số đang học', String(totalStudents), 'Trên các lớp đang vận hành', 'p'),
        ].join('');

        Admin.$('acPendingCount').textContent = String(pendingRegs);
        Admin.$('acClassCount').textContent = String(classes.length);
        Admin.$('acCourseCount').textContent = String(courses.length);

        acEnrollQ = ''; acClassQ = ''; acCourseQ = '';
        acEnrollFilter = pendingRegs > 0 ? 'cho_xep_lop' : 'all';
        acClassFilter = 'all';
        Admin.$('acEnrollPills')?.querySelectorAll('[data-ef]').forEach(b => b.classList.toggle('on', b.dataset.ef === acEnrollFilter));

        Admin.$('adminCoursesTabs')?.querySelectorAll('[data-ac-tab]').forEach(b => b.addEventListener('click', () => switchAcTab(b.dataset.acTab)));
        Admin.$('acEnrollSearch')?.addEventListener('input', e => { acEnrollQ = e.target.value; renderAdminCoursesTabContent(); });
        Admin.$('acClassSearch')?.addEventListener('input', e => { acClassQ = e.target.value; renderAdminCoursesTabContent(); });
        Admin.$('acCourseSearch')?.addEventListener('input', e => { acCourseQ = e.target.value; renderAdminCoursesTabContent(); });
        Admin.$('acEnrollPills')?.querySelectorAll('[data-ef]').forEach(btn => btn.addEventListener('click', () => {
          acEnrollFilter = btn.dataset.ef;
          Admin.$('acEnrollPills').querySelectorAll('[data-ef]').forEach(b => b.classList.toggle('on', b === btn));
          renderAdminCoursesTabContent();
        }));
        Admin.$('acClassPills')?.querySelectorAll('[data-cf]').forEach(btn => btn.addEventListener('click', () => {
          acClassFilter = btn.dataset.cf;
          Admin.$('acClassPills').querySelectorAll('[data-cf]').forEach(b => b.classList.toggle('on', b === btn));
          renderAdminCoursesTabContent();
        }));

        renderAdminCoursesTabContent();
        switchAcTab(acTab);
      }
    };
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
      <form id="catUnifiedForm" class="cat-crud-bar" data-on-submit="submit-unified-catalog">
        <div class="cat-crud-head">
          <div class="row" style="gap:8px">
            <span class="tag ${editRow ? 'purple' : 'ok'}">${editRow ? 'Chế độ chỉnh sửa' : 'Biểu mẫu dùng chung'}</span>
            <b style="font-size:13.5px;color:var(--g800)">${editRow ? `Cập nhật ${Admin.esc(activeDef.title)}: ${Admin.esc(editRow.tenCoSo || editRow.ten || '')}` : 'Thêm mới hoặc cập nhật nhanh mọi thuộc tính danh mục tại một nơi'}</b>
          </div>
          ${editRow ? `<button type="button" class="btn sm" data-act="cancel-unified-catalog">Hủy chỉnh sửa</button>` : ''}
        </div>
        <div class="cat-crud-grid">
          <div>
            <label>Phân loại danh mục</label>
            <select id="catUnifiedTypeSelect" data-on-change="unified-type-change" ${editRow ? 'disabled' : ''}>
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
          <button type="button" class="chip ${currentFilter === 'all' ? 'on' : ''}" data-act="switch-cat" data-cat="all">Tất cả (${totalCount})</button>
          <button type="button" class="chip ${currentFilter === 'subjects' ? 'on' : ''}" data-act="switch-cat" data-cat="subjects">Môn học (${counts.subjects})</button>
          <button type="button" class="chip ${currentFilter === 'rooms' ? 'on' : ''}" data-act="switch-cat" data-cat="rooms">Phòng học (${counts.rooms})</button>
          <button type="button" class="chip ${currentFilter === 'branches' ? 'on' : ''}" data-act="switch-cat" data-cat="branches">Cơ sở đào tạo (${counts.branches})</button>
          <button type="button" class="chip ${currentFilter === 'grades' ? 'on' : ''}" data-act="switch-cat" data-cat="grades">Cấp lớp (${counts.grades})</button>
        </div>
        <div style="width:260px">
          <input type="search" id="catUnifiedSearchInput" value="${Admin.esc(searchQuery)}" placeholder="Tìm mã, tên môn, phòng, cơ sở..." data-on-input="unified-catalog-search">
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
                      ${row.key !== 'rooms' ? `<button type="button" class="btn sm" data-act="start-unified-edit" data-key="${Admin.esc(row.key)}" data-id="${row.id}">Sửa</button>` : ''}
                      ${!['rooms','grades'].includes(row.key) ? `<button type="button" class="btn sm ${row.isActive ? 'danger' : 'p'}" data-act="toggle-unified-active" data-key="${Admin.esc(row.key)}" data-id="${row.id}">${row.isActive ? 'Ngừng dùng' : 'Kích hoạt'}</button>` : ''}
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
                    <button type="button" class="btn sm p" style="margin-left:auto" data-act="save-inline-setting" data-key="${Admin.esc(item.khoa)}">Lưu cấu hình</button>
                  </div>
                ` : `
                  <div class="row-b" style="margin-top:4px;padding-top:6px;border-top:1px dashed var(--g200)">
                    <span style="font-size:12px;font-weight:600;color:var(--pri-d)">${Admin.esc(displayVal)}</span>
                    ${String(item.giaTri) !== fixed[item.khoa] && (item.khoa in fixed) ? `<button type="button" class="btn sm" data-act="edit-admin-setting" data-key="${Admin.esc(item.khoa)}">Đồng bộ chuẩn</button>` : ''}
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
  Admin.actions({
    'open-course-modal': el => window.openCourseModal(el.dataset.id ? Number(el.dataset.id) : undefined),
    'open-class-modal': el => window.openAdminClassModal(el.dataset.id ? Number(el.dataset.id) : undefined),
    'open-class-detail': el => window.openAdminClassDetail(Number(el.dataset.id)),
    'edit-class-from-detail': el => { Admin.$('admin-dialog')?.close(); window.openAdminClassModal(Number(el.dataset.id)); },
    'open-enroll-modal': el => window.openAdminEnrollModal(el.dataset.id ? Number(el.dataset.id) : undefined),
    'close-admin-enroll': () => window.closeAdminEnrollModal(),
    'submit-admin-enroll': (_form, ev) => window.handleAdminEnrollSubmit(ev),
    'submit-unified-catalog': (_form, ev) => window.submitUnifiedCatalogForm(ev),
    'cancel-unified-catalog': () => window.cancelUnifiedCatalogEdit(),
    'unified-type-change': el => window.onUnifiedTypeChange(el.value),
    'switch-cat': el => window.switchCat(el.dataset.cat),
    'unified-catalog-search': el => window.onUnifiedCatalogSearch(el.value),
    'start-unified-edit': el => window.startUnifiedCatalogEdit(el.dataset.key, Number(el.dataset.id)),
    'toggle-unified-active': el => window.toggleUnifiedCatalogActive(el.dataset.key, Number(el.dataset.id)),
    'save-inline-setting': el => window.saveInlineAdminSetting(el.dataset.key, el),
    'edit-admin-setting': el => window.editAdminSetting(el.dataset.key),
  });
})();
