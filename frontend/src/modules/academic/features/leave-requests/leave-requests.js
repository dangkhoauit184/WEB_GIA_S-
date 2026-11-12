'use strict';
window.AdminClassLeave={
  list:()=>Admin.api('/api/academic/leave-requests/classes'),
  decide:(id,body)=>Admin.api(`/api/academic/leave-requests/classes/${id}/decision`,'POST',body)
};

(() => {
  let requests=[], classRequests=[], filter='all';
  StaffUI.register('staff-approve','Duyệt nghỉ cá nhân & Học bù',async()=>{
    const [personal,classes,makeups]=await Promise.all([Admin.api('/api/academic/leave-requests/personal'),
      Admin.api('/api/academic/leave-requests/class-overview'),Admin.api(StaffUI.prefix+'/makeups')]);
    return {personal,classes,makeups};
  },data=>{
    requests=data.personal; classRequests=data.classes;
    render();
    StaffUI.rows('makeupStudentsBody',6,data.makeups.map(row=>`<tr><td><b>HB-${row.id}</b></td><td>${Admin.esc(row.tenHocVien)}</td><td>${Admin.esc(row.maLop)} · ${Admin.esc(row.ngay)} ${Admin.esc(row.gioBatDau.slice(0,5))}–${Admin.esc(row.gioKetThuc.slice(0,5))}</td><td>${row.donXinNghiId?'Đơn #'+row.donXinNghiId:'Thỏa thuận trực tiếp'}</td><td>${Admin.esc(row.nguoiXep)}</td><td>${Admin.esc(row.ghiChu || '—')}</td></tr>`));
  });
  function render() {
    const personal=requests.filter(row=>filter==='all' || filter==='nghi_hoc' && row.doiTuong==='hoc_vien' || filter==='doi_lich' && row.doiTuong!=='hoc_vien');
    const rows=personal.map(row=>`<tr><td><b>Đơn #${row.id}</b></td><td>${Admin.tag(row.doiTuong==='hoc_vien'?'Nghỉ cá nhân học viên':'Nghỉ / Đổi lịch người dạy','i')}</td><td>${Admin.esc(row.nguoiGui)}<br><small>${Admin.esc(row.tenHocVien || '')}</small></td><td>${Admin.esc(row.maLop)}<br>${Admin.esc(row.ngay+' '+row.gioBatDau.slice(0,5))}</td><td>${Admin.esc(row.lyDo)}${row.lyDoTuChoi?'<br><small>'+Admin.esc(row.lyDoTuChoi)+'</small>':''}</td><td>${Admin.tag({cho_duyet:'Chờ duyệt',da_duyet:'Đã duyệt',tu_choi:'Từ chối',da_huy:'Đã hủy'}[row.trangThai],row.trangThai==='da_duyet'?'ok':'w')}</td><td>${row.trangThai==='cho_duyet'&&StaffUI.can('VanHanhBuoiHoc','edit')?Admin.button('Duyệt','approve-personal',row.id,'p')+Admin.button('Từ chối','reject-personal',row.id,'danger'):''}</td></tr>`);
    if(filter==='all'||filter==='nghi_day') rows.push(...classRequests.map(row=>`<tr><td><b>Nghỉ lớp #${row.id}</b></td><td>${Admin.tag('Nghỉ cả lớp · Admin duyệt','purple')}</td><td>${Admin.esc(row.nguoiGui)}</td><td>${Admin.esc(row.maLop+' · '+row.ngay)}</td><td>${Admin.esc(row.lyDo)}</td><td>${Admin.tag({cho_duyet: 'Chờ duyệt', da_duyet: 'Đã duyệt', tu_choi: 'Từ chối'}[row.trangThai] || row.trangThai)}</td><td>${Admin.user.role==='ADMIN'?'<button class="btn sm" onclick="setPortal(\'admin\',true,\'admin-exceptions\')">Chuyển Admin duyệt</button>':row.trangThai==='cho_duyet'?'Chờ Admin xử lý':'Đã xử lý'}</td></tr>`));
    if(filter==='dieu_chinh_cong') rows.push('<tr><td colspan="7" class="admin-empty">Admin xử lý điều chỉnh công tại cổng Quản trị.</td></tr>');
    StaffUI.rows('staffApproveBody',7,rows);
    Admin.$('staffApproveBody').querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>{
      const rejected=button.dataset.action==='reject-personal';
      Admin.formDialog({title:rejected?'Từ chối đơn nghỉ':'Duyệt đơn nghỉ cá nhân',fields:rejected?[{name:'lyDoTuChoi',label:'Lý do từ chối',required:true,maxLength:255}]:[],submit:'Xác nhận',onSubmit:async body=>{
        await Admin.api(`/api/academic/leave-requests/personal/${button.dataset.id}/decide`,'POST',{trangThai:rejected?'tu_choi':'da_duyet',...body});
        toast('Đã xử lý đơn và gửi thông báo cho người gửi.','ok');Admin.render();
      }});
    }));
  }
  window.filterStaffApprove = value => {
    filter=value;
    document.querySelectorAll('#staffApproveFilterChips .chip').forEach(chip=>chip.classList.toggle('on',chip.getAttribute('onclick').includes(`'${value}'`)));
    render();
  };
})();
