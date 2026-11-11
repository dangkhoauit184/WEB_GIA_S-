'use strict';
(() => {
  const Admin=window.Admin;
  const money=Admin.money, esc=Admin.esc;
  const empty=columns=>`<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu.</td></tr>`;
  const roleLabel=type=>type?.includes('giao_vien')?'Giáo viên':type?.includes('gia_su')?'Gia sư':'Trợ giảng';
  const confirmation=Admin.confirmDialog;
  let payroll=[];
  window.changePayrollMonth=value=>{if(value){Admin.period=value;Admin.render();}};
  Admin.register('admin-payroll',async()=>{
    const data=await Admin.api('/api/finance/payroll?month='+Admin.period);
    return {title:'Công, Đơn giá giờ & Lương',html:Admin.view('admin-payroll'),bind(){
      payroll=data.items;
      Admin.$('payrollMonth').value=Admin.period;
      const [py, pm] = String(Admin.period).split('-');
      document.querySelector('#admin-payroll .card h2').textContent=`1. Bảng tổng hợp Công & Lương Tháng ${pm}/${py}`;
      Admin.$('adminPayrollBody').innerHTML=payroll.map(row=>`<tr><td><b class="mono">${row.id?'BC-'+row.id:'Chưa chốt'}</b></td><td><b>${esc(row.hoTen)}</b></td><td>${Admin.tag(roleLabel(row.phanLoai),'i')}</td><td><b class="mono">${money(row.donGiaGioChuan)}/giờ</b></td><td>${row.soBuoi} buổi <small>(${row.soLanTre} trễ)</small></td><td><b>${Number((Number(row.tongPhut||0)/60).toFixed(2))} giờ</b></td><td><b style="color:var(--ok)">${money(row.thucNhan)}</b></td><td>${Admin.tag({mo:'Tạm tính',da_chot:'Đã chốt',da_tra:'Đã chi trả'}[row.trangThai],row.trangThai==='mo'?'w':row.trangThai==='da_chot'?'purple':'ok')}</td><td><div class="row">${row.trangThai==='mo'?`<button class="btn p sm" onclick="closeOnePayroll(${row.userId})">Chốt công</button>`:row.trangThai==='da_chot'?`<button class="btn p sm" onclick="adminMarkPayrollPaid(${row.userId})">Xác nhận chi trả</button>`:''}<button class="btn sm" onclick="printPayslip(${row.userId})">In phiếu</button></div></td></tr>`).join('')||empty(9);
      Admin.$('rateHistoryBody').innerHTML=data.history.map(row=>`<tr><td><b>${esc(row.hoTen)}</b></td><td>${money(row.donGiaGio)}/giờ</td><td class="mono">${esc(row.hieuLucTu)}</td><td>${esc(row.tenAdmin)}</td><td>Cập nhật đơn giá giờ</td></tr>`).join('')||empty(5);
    }};
  });
  window.closeOnePayroll=userId=>confirmation('Admin chốt công tháng',Admin.period,async()=>{
    await Admin.api('/api/finance/payroll/close','POST',{userId,month:Admin.period});toast('Đã chốt công.','ok');Admin.render();
  });
  window.staffClosePayrollMonth=()=>confirmation('Admin chốt công tháng',Admin.period+' · Chốt các bảng đang mở',async()=>{
    const candidates=payroll.filter(row=>row.trangThai==='mo');
    const errors=[];let count=0;
    for(const row of candidates){
      try{await Admin.api('/api/finance/payroll/close','POST',{userId:row.userId,month:Admin.period});count++;}
      catch(error){errors.push(row.hoTen+' · '+error.message);}
    }
    Admin.render();
    if(errors.length)throw new Error(`Đã chốt ${count}/${candidates.length} bảng.\n`+errors.join('\n'));
    toast(`Đã chốt ${count} bảng công.`, 'ok');
  });
  window.adminMarkPayrollPaid=userId=>{
    const row=payroll.find(item=>item.userId===userId);
    confirmation('Xác nhận chi trả lương',`${row.hoTen} · ${Admin.period} · ${money(row.thucNhan)}`,async body=>{
      await Admin.api(`/api/finance/payroll/${row.id}/pay`,'POST',{maGiaoDich:body.maGiaoDich||null});
      toast('Đã ghi nhận chi trả lương.','ok');Admin.render();
    },[{name:'maGiaoDich',label:'Mã đối chiếu chi trả',maxLength:60}]);
  };
  window.adminUnlockPayroll=id=>confirmation('Admin mở khóa bảng công','Kỳ đã chốt chưa trả',async body=>{
    await Admin.api(`/api/finance/payroll/${id}/unlock`,'POST',{lyDo:body.lyDo});toast('Đã mở khóa bảng công.','ok');Admin.render();
  },[{name:'lyDo',label:'Lý do mở khóa',required:true,maxLength:255}]);
  window.printPayslip=async userId=>{
    try{
      const person=payroll.find(row=>row.userId===userId);
      const rows=await Admin.api(`/api/finance/payroll/${userId}/sessions?month=${Admin.period}`);
      Admin.dialog('PHIẾU LƯƠNG & BẢNG ĐỐI SOÁT CÔNG',`<div class="dialog-body"><h2>TRUNG TÂM EDUCENTER ERP</h2><p>Kỳ công ${esc(Admin.period)} · ${esc(person.hoTen)}</p>${Admin.table([{key:'ngay',label:'Ngày'},{key:'maLop',label:'Lớp'},{label:'Số giờ công',render:row=>row.soPhutTinhCong!=null?Number((Number(row.soPhutTinhCong)/60).toFixed(2))+' giờ':'Chưa đủ giờ'},{label:'Đơn giá snapshot',render:row=>money(row.donGia)},{label:'Tiền công',render:row=>money(row.tien)}],rows)}<div class="item"><span>Thưởng</span><b>${money(person.thuong)}</b></div><div class="item"><span>Khấu trừ</span><b>${money(person.khauTru)}</b></div><div class="item"><span>Thực nhận</span><b>${money(person.thucNhan)}</b></div><p>Người xác nhận ${esc(Admin.user.hoTen)} · ${esc(person.trangThai)}</p></div><div class="dialog-foot"><button class="btn p" onclick="window.print()">In / Lưu PDF</button></div>`);
    }catch(error){toast(error.message,'err');}
  };

})();
