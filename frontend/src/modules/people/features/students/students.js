'use strict';
(() => {
  const states = { dang_hoc:'Hoạt động', tam_nghi:'Tạm nghỉ', hoan_thanh:'Hoàn thành', nghi_han:'Nghỉ hẳn' };
  const learningStates = { ...states, dang_hoc:'Đang học', da_xep_lop:'Đã xếp lớp', cho_xep_lop:'Chờ xếp lớp', chua_xep_lop:'Chưa xếp lớp' };
  function render(rows) {
    StaffUI.rows('stuBody', 7, rows.map(row => {
      const status = row.trangThaiHocTap;
      return `<tr><td><b>${Admin.esc(row.maHv)}</b></td><td><b>${Admin.esc(row.hoTen)}</b></td><td>${Admin.esc(row.tenCapLop || '—')}<br><small>${Admin.esc(row.truong || '')}</small></td><td>${Admin.esc(row.tenPhuHuynh)}<br><small>${Admin.esc(row.sdtPhuHuynh || '')}</small></td><td>${Admin.esc(row.tenKhoa || 'Chưa đăng ký')}</td><td>${Admin.tag(learningStates[status] || 'Chưa xếp lớp', status === 'dang_hoc' ? 'ok' : 'w')}</td><td><div class="row">${Admin.button('Chi tiết / Sửa', 'edit-student', row.id)}${StaffUI.can('QuanLyLopHoc','add') ? Admin.button('Đăng ký khóa', 'register-course', row.id,'p') : ''}</div></td></tr>`;
    }));
    Admin.$('stuBody').querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
      if (button.dataset.action === 'edit-student') openStudentForm(Number(button.dataset.id));
      else registerCourse(Number(button.dataset.id));
    }));
  }
  StaffUI.register('staff-students', 'Học viên & Đăng ký khóa', () => Admin.api('/api/people/students'), rows => {
    render(rows);
    StaffUI.search('stuBody', async q => render(await Admin.api('/api/people/students?'+Admin.query({q}))));
    document.querySelector('#staff-students [onclick="openStudentForm()"]').hidden = !StaffUI.can('QuanLyLopHoc','add');
  });
  window.openStudentForm = async (id, parentId) => {
    try {
      const [lookups, parents, student] = await Promise.all([StaffUI.lookups(), Admin.api('/api/people/parents'), id ? Admin.api('/api/people/students/'+id) : Promise.resolve(null)]);
      const fields = [
        {name:'maHv', label:'Mã học viên', required:true, maxLength:30, disabled:Boolean(student)},
        {name:'hoTen', label:'Họ tên học viên', required:true, maxLength:150},
        StaffUI.select('phuHuynhUserId','Phụ huynh',parents,'userId','hoTen',true),
        {name:'ngaySinh',label:'Ngày sinh',type:'date',nullable:true},
        StaffUI.select('capLopId','Khối lớp',lookups['grade-levels']),
        {name:'truong',label:'Trường đang học',maxLength:150,nullable:true},
        StaffUI.select('khuVucId','Khu vực',lookups.regions),
        {name:'diaChi',label:'Địa chỉ',maxLength:255,nullable:true},
        {name:'ghiChu',label:'Ghi chú',type:'textarea',nullable:true},
        {name:'trangThai',label:'Trạng thái hồ sơ',type:'select',value:'dang_hoc',options:Object.entries(states).map(([value,label])=>({value,label}))}
      ];
      if (!student) fields.push(StaffUI.select('khoaHocId','Khóa đăng ký',lookups.courses));
      fields.forEach(field => { if (student) field.value=student[field.name]; else if(field.name==='phuHuynhUserId') field.value=parentId; });
      Admin.formDialog({ title:student?'Hồ sơ học viên':'Thêm học viên & Đăng ký khóa', fields, onSubmit:async body => {
        for (const name of ['phuHuynhUserId','capLopId','khuVucId','khoaHocId']) if (body[name] != null && body[name] !== '') body[name]=Number(body[name]);
        await Admin.api(student?'/api/people/students/'+id:StaffUI.prefix+'/students',student?'PUT':'POST',body);
        toast('Đã lưu hồ sơ học viên.','ok'); Admin.render();
      }});
      if (!StaffUI.can('QuanLyLopHoc',student?'edit':'add')) Admin.$('dialog-submit').disabled=true;
    } catch(error) { toast(error.message,'err'); }
  };
  async function registerCourse(id) {
    try {
      const courses = await Admin.api(StaffUI.prefix+'/courses');
      const previous = await Admin.api(`${StaffUI.prefix}/students/${id}/registrations`);
      const active = new Set(previous.filter(row=>['cho_xep_lop','dang_hoc'].includes(row.trangThai)).map(row=>row.khoaHocId));
      Admin.formDialog({ title:'Đăng ký khóa học trọn khóa', fields:[StaffUI.select('khoaHocId','Khóa học',courses.filter(row=>!active.has(row.id)),'id','ten',true)], submit:'Đăng ký khóa', onSubmit:async body => {
        await Admin.api(`${StaffUI.prefix}/students/${id}/registrations`,'POST',{khoaHocId:Number(body.khoaHocId)});
        toast('Đã đăng ký khóa, chờ Admin xếp lớp.','ok'); Admin.render();
      }});
    } catch(error) { toast(error.message,'err'); }
  }
})();
