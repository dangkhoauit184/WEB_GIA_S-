'use strict';
(() => {
  const states = {sap_bat_dau:'Sắp bắt đầu',dang_hoc:'Đang học',tam_dung:'Tạm dừng',ket_thuc:'Đã kết thúc',huy:'Đã hủy'};
  StaffUI.register('staff-classes','Danh sách Lớp & Sĩ số',async()=>{
    const classes=await Admin.api('/api/academic/classes');
    return classes;
  },classes=>{
    StaffUI.rows('classBody',7,classes.map(row=>`<tr><td><b>${Admin.esc(row.maLop)}</b></td><td>${Admin.esc(row.tenLop)}<br><small>${Admin.esc(row.tenKhoa || '')}</small></td><td>${Admin.esc(row.lichTuan || 'Chưa có lịch tuần')}<br><small>${Admin.esc(row.phongHoc || 'Chưa chọn phòng')}</small><br>${Admin.tag(states[row.trangThai] || row.trangThai)}</td><td>${Admin.esc(row.tenNguoiDayChinh || '—')}</td><td>${Admin.esc(row.tenTroGiang || 'Chưa phân công')}</td><td>${row.siSoHienTai}/${row.siSoToiDa}</td><td>${Admin.button('Chi tiết / Danh sách lớp','class-detail',row.id)}</td></tr>`));
    Admin.$('classBody').querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>showClass(Number(button.dataset.id))));
    const adminButton=document.querySelector('#staff-classes [onclick*="setPortal"]');
    adminButton.hidden=Admin.user.role!=='ADMIN';
    const scheduleButton=document.querySelector('#staff-classes [onclick="openSessionForm()"]');
    scheduleButton.onclick=async()=>{try{await loadAdminCalendar();openSessionForm();}catch(error){toast(error.message,'err');}};
    scheduleButton.disabled=!StaffUI.can('VanHanhBuoiHoc','add');
  });
  async function showClass(id) {
    try {
      const row=await Admin.api('/api/academic/classes/'+id);
      Admin.dialog(row.maLop+' · '+row.tenLop,`<div class="dialog-body"><p>${Admin.esc(row.tenMonHoc)} · ${Admin.esc(row.tenCapLop)} · ${Admin.esc(row.tenNguoiDayChinh || '')}</p><h3>Học viên trong lớp</h3>${Admin.table([{key:'maHv',label:'Mã học viên'},{key:'hoTen',label:'Học viên'},{key:'trangThai',label:'Trạng thái'}],row.hocViens)}<h3>Lịch tuần</h3>${Admin.table([{key:'thu',label:'Thứ'},{key:'gioBatDau',label:'Bắt đầu'},{key:'gioKetThuc',label:'Kết thúc'},{key:'phongHoc',label:'Phòng'}],row.lichHocs)}<button class="btn p" id="staff-class-calendar">Mở thời khóa biểu</button></div>`);
      Admin.$('staff-class-calendar').onclick=()=>{Admin.$('admin-dialog').close();calFilters.classCode=row.maLop;showS('staff-schedule');};
    }catch(error){toast(error.message,'err');}
  }
})();
