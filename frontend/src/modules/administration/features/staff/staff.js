'use strict';
(() => {
  const Admin=window.Admin;
  const money=Admin.money, esc=Admin.esc;
  const empty=columns=>`<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu.</td></tr>`;
  const roleLabel=type=>type?.includes('giao_vien')?'Giáo viên':type?.includes('gia_su')?'Gia sư':'Trợ giảng';
  const confirmation=Admin.confirmDialog;
  let employees=[],currentProfile=null;
  Admin.register('admin-staff',async()=>{
    const data=await Admin.api('/api/administration/staff');
    return {title:'Nhân sự & Hồ sơ chuyên môn',html:Admin.view('admin-staff'),bind(){
      employees=data;
      document.querySelector('#admin-staff .pg-head').insertAdjacentHTML('beforeend','<button class="btn sm" onclick="openAccountManagement()">Quản lý tài khoản & Nhóm quyền</button>');
      renderStaff();
    }};
  });
  window.renderStaff=()=>{
    const query=Admin.$('staffSearchInput').value.toLowerCase(),role=Admin.$('staffRoleFilter').value;
    const name=row=>row.groupName==='Admin'?'Admin':row.groupName==='NhanVien'?'Nhân viên':roleLabel(row.phanLoai);
    const rows=employees.filter(row=>(!query||[row.hoTen,row.email,row.sdt].join(' ').toLowerCase().includes(query))&&(!role||name(row)===role));
    Admin.$('staffRows').innerHTML=rows.map(row=>`<tr><td><b>${esc(row.hoTen)}</b></td><td>${esc(row.email)}<br><small>${esc(row.sdt||'—')}</small></td><td>${Admin.tag(name(row),'i')}</td><td style="font-size:12px">${esc(row.chuyenMon||row.gioiThieu||'Chưa nhập chuyên môn')}<br>${esc(row.lichRanh||'Chưa nhập lịch rảnh')}</td><td>${row.nhanSuId?money(row.donGiaGioChuan)+'/giờ':'Lương quản lý/văn phòng'}</td><td>${Admin.tag(row.status==='active'?'Hoạt động':row.status==='pending'?'Chờ kích hoạt':'Khóa',row.status==='active'?'ok':'r')}</td><td><div class="row">${row.nhanSuId?`<button class="btn sm p" onclick="openTeacherProfileModal(${row.userId})">Đơn giá & Hồ sơ</button>`:''}<button class="btn sm" onclick="toggleStaff(${row.userId})">${row.status==='active'?'Khóa':'Kích hoạt'}</button></div></td></tr>`).join('')||empty(7);
    Admin.$('staffCount').textContent=`Hiển thị ${rows.length} / ${employees.length} tài khoản`;
  };
  window.toggleStaff=userId=>{
    const row=employees.find(item=>item.userId===userId),status=row.status==='active'?'locked':'active';
    confirmation(status==='locked'?'Khóa tài khoản':'Kích hoạt tài khoản',row.hoTen,async()=>{
      await Admin.api(`/api/administration/permissions/accounts/${row.accountId}`,'PUT',{status});toast('Đã cập nhật trạng thái.','ok');Admin.render();
    });
  };
  window.onStaffRoleChanged=()=>{
    const professional=['Giáo viên','Gia sư','Trợ giảng'].includes(Admin.$('sfStaffRole').value);
    Admin.$('sfStaffHourlyRate').disabled=!professional;
    if(!professional)Admin.$('sfStaffHourlyRate').value='0';
  };
  window.openStaffForm=()=>{Admin.$('staffAddForm').reset();onStaffRoleChanged();Admin.$('modalStaffForm').classList.add('show');};
  window.closeModalStaffForm=()=>Admin.$('modalStaffForm').classList.remove('show');
  window.handleStaffAddSubmit=async event=>{
    event.preventDefault();
    const roles={'Giáo viên':'giao_vien','Gia sư':'gia_su','Trợ giảng':'tro_giang','Nhân viên':'NhanVien','Admin':'Admin'};
    const body={hoTen:Admin.$('sfStaffName').value,email:Admin.$('sfStaffEmail').value,sdt:Admin.$('sfStaffPhone').value,
      role:roles[Admin.$('sfStaffRole').value],donGiaGio:Number(Admin.$('sfStaffHourlyRate').value),gioiThieu:Admin.$('sfStaffSpec').value||null,
      username:Admin.$('sfStaffUsername').value,password:Admin.$('sfStaffPassword').value};
    await Admin.busy(event.target.querySelector('button[type=submit]'),async()=>{
      await Admin.api('/api/administration/staff','POST',body);closeModalStaffForm();toast('Đã tạo hồ sơ và tài khoản nhân sự.','ok');Admin.render();
    });
  };

  let profileCatalog={subjects:[],regions:[]};
  function subjectRow(value={}) {
    return `<div class="admin-profile-row" data-subject data-fee="${Number(value.hocPhiBuoiDeXuat||0)}"><label>Môn<select data-subject-id>${Admin.options(profileCatalog.subjects,'id','ten',value.monHocId)}</select></label><label>Từ lớp<input data-grade-from type="number" min="1" max="12" value="${value.capLopTu||1}" required></label><label>Đến lớp<input data-grade-to type="number" min="1" max="12" value="${value.capLopDen||12}" required></label><button type="button" class="btn sm" onclick="this.closest('[data-subject]').remove()">Bỏ</button></div>`;
  }
  function availableRow(value={}) {
    return `<div class="admin-profile-row" data-available><label>Thứ<select data-day>${Array.from({length:7},(_,i)=>`<option value="${i+2}" ${i+2===(value.thu||2)?'selected':''}>${i===6?'Chủ nhật':'Thứ '+(i+2)}</option>`).join('')}</select></label><label>Từ giờ<input data-from type="time" value="${(value.gioBatDau||'18:00').slice(0,5)}" required></label><label>Đến giờ<input data-to type="time" value="${(value.gioKetThuc||'20:00').slice(0,5)}" required></label><button type="button" class="btn sm" onclick="this.closest('[data-available]').remove()">Bỏ</button></div>`;
  }
  window.addProfileSubject=()=>Admin.$('profileSubjects').insertAdjacentHTML('beforeend',subjectRow());
  window.addProfileAvailability=()=>Admin.$('profileAvailability').insertAdjacentHTML('beforeend',availableRow());
  window.openTeacherProfileModal=async userId=>{
    try{
      const [data,subjects,regions]=await Promise.all([Admin.api(`/api/administration/staff/${userId}/profile`),Admin.api('/api/administration/catalog/subjects'),Admin.api('/api/administration/catalog/regions')]);
      currentProfile={...data,userId};profileCatalog={subjects,regions};const p=data.professional;
      if(!p)throw new Error('Hồ sơ người dạy không còn hoạt động. Vui lòng tải lại danh sách.');
      Admin.$('tpModalTitle').textContent='Hồ sơ Chuyên môn & Đơn giá giờ — '+data.user.hoTen;
      const labels=[['hoTen','Họ tên',p.hoTen],['email','Email',p.email],['sdt','Điện thoại',p.sdt],['nganHang','Ngân hàng',p.nganHang],['soTaiKhoan','Số tài khoản',p.soTaiKhoan],['chuTaiKhoan','Chủ tài khoản',p.chuTaiKhoan]];
      Admin.$('tpModalBody').innerHTML=`<form id="profileEditForm" onsubmit="saveStaffProfile(event)">${labels.map(([key,label,value])=>`<div class="form-group"><label>${label}<input name="${key}" value="${esc(value||'')}" ${['hoTen','email'].includes(key)?'required':''}></label></div>`).join('')}<div class="card"><h4>Môn & Khối lớp nhận dạy</h4><div id="profileSubjects">${p.monDays.map(subjectRow).join('')}</div><button type="button" class="btn sm" onclick="addProfileSubject()">+ Thêm môn</button></div><div class="card"><h4>Lịch rảnh đăng ký</h4><div id="profileAvailability">${p.lichRanhs.map(availableRow).join('')}</div><button type="button" class="btn sm" onclick="addProfileAvailability()">+ Thêm lịch rảnh</button></div><div class="card"><h4>Khu vực nhận dạy</h4><div class="chips">${regions.map(row=>`<label class="chip"><input type="checkbox" data-region="${row.id}" ${p.khuVucs.some(item=>item.khuVucId===row.id)?'checked':''}>${esc(row.ten)}</label>`).join('')}</div></div><button class="btn p sm" type="submit">Lưu hồ sơ chuyên môn & Lịch rảnh</button></form><div class="card" style="margin-top:14px;border-color:var(--pri-l);background:var(--pri-ll)"><h4>Đặc quyền Admin · Cập nhật Đơn giá tiền/giờ</h4><div class="row"><label>Đơn giá hiện hành<input value="${money(p.donGiaGioChuan)}/giờ" disabled></label><label>Đơn giá mới (VNĐ/giờ)<input id="tpNewRate" type="number" min="0" max="999999999999" step="1" value="${Number(p.donGiaGioChuan)}"></label></div><button class="btn p sm" onclick="saveAdminRate(this)">Cập nhật đơn giá giờ</button></div>`;
      Admin.$('modalTeacherProfile').classList.add('show');
    }catch(error){toast(error.message,'err');}
  };
  window.closeTeacherProfileModal=()=>Admin.$('modalTeacherProfile').classList.remove('show');
  window.saveStaffProfile=async event=>{
    event.preventDefault();
    const body=Object.fromEntries(new FormData(event.target));
    for(const key of ['sdt','nganHang','soTaiKhoan','chuTaiKhoan'])if(!body[key])body[key]=null;
    body.monDays=[...document.querySelectorAll('[data-subject]')].map(row=>({monHocId:Number(row.querySelector('[data-subject-id]').value),capLopTu:Number(row.querySelector('[data-grade-from]').value),capLopDen:Number(row.querySelector('[data-grade-to]').value),hocPhiBuoiDeXuat:Number(row.dataset.fee||0)}));
    body.lichRanhs=[...document.querySelectorAll('[data-available]')].map(row=>({thu:Number(row.querySelector('[data-day]').value),gioBatDau:row.querySelector('[data-from]').value,gioKetThuc:row.querySelector('[data-to]').value}));
    body.khuVucIds=[...document.querySelectorAll('[data-region]:checked')].map(row=>Number(row.dataset.region));
    await Admin.busy(event.target.querySelector('button[type=submit]'),async()=>{
      await Admin.api(`/api/administration/staff/${currentProfile.userId}/profile`,'PUT',body);toast('Đã lưu hồ sơ chuyên môn.','ok');Admin.render();
    });
  };
  window.saveAdminRate=button=>Admin.busy(button,async()=>{
    const value=Number(Admin.$('tpNewRate').value);
    if(!Number.isSafeInteger(value)||value<0)throw new Error('Đơn giá cần là số nguyên VND không âm.');
    await Admin.api(`/api/finance/payroll/rates/${currentProfile.professional.id}`,'PUT',{donGiaGio:value});
    toast('Đã cập nhật đơn giá giờ và lịch sử.','ok');closeTeacherProfileModal();Admin.render();
  });
})();
