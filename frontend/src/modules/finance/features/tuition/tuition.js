'use strict';
(() => {
  let invoices=[], selected=null;
  StaffUI.register('staff-billing','Hóa đơn trọn khóa & Thu phí',()=>Admin.api('/api/finance/tuition/invoices'),data=>{
    invoices=data;
    selected=null;
    StaffUI.rows('invBody',7,data.map(row=>`<tr><td><b>${Admin.esc(row.maHd)}</b></td><td>${Admin.esc(row.tenHocVien)}<br><small>${Admin.esc(row.tenKhoa || 'Dữ liệu lịch sử')}</small></td><td>${Admin.money(row.phaiThu)}</td><td>${Admin.money(row.daThu)}</td><td>${Admin.money(Number(row.phaiThu)-Number(row.daThu))}</td><td>${Admin.tag({nhap:'Nháp',chua_thanh_toan:'Chưa thanh toán',mot_phan:'Đã thu một phần',da_thanh_toan:'Đã thu đủ',huy:'Đã hủy'}[row.trangThai],row.trangThai==='da_thanh_toan'?'ok':'w')}</td><td>${Admin.button('Chi tiết','select-invoice',row.id)}</td></tr>`));
    Admin.$('invBody').querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>selectInvoice(Number(button.dataset.id))));
    document.querySelector('#staff-billing [onclick="doPayment()"]').textContent=Admin.user.role==='ADMIN'?'Xác nhận & Lập phiếu thu':'Admin xác nhận thu tiền';
    document.querySelector('#staff-billing [onclick="doPayment()"]').disabled=Admin.user.role!=='ADMIN';
    for(const id of ['payAmt','payMethodSel','payNote'])Admin.$(id).disabled=Admin.user.role!=='ADMIN';
  });
  async function selectInvoice(id) {
    try {
      selected=await Admin.api('/api/finance/tuition/invoices/'+id);
      Admin.$('invBanner').innerHTML=`<b>${Admin.esc(selected.maHd)} · ${Admin.esc(selected.tenHocVien)}</b><br>${Admin.esc(selected.tenKhoa || '')} · Còn phải thu <b>${Admin.money(Number(selected.phaiThu)-Number(selected.daThu))}</b><br><small>${selected.phatHanhLuc?'Phát hành lúc '+Admin.esc(selected.phatHanhLuc):'Hóa đơn đang nháp'}</small>`;
      Admin.$('payAmt').value=Math.max(0,Number(selected.phaiThu)-Number(selected.daThu));
    }catch(error){toast(error.message,'err');}
  }
  window.createCourseInvoicePrompt = async () => {
    try {
      const students=await Admin.api('/api/people/students');
      Admin.formDialog({title:'Chọn học viên để lập hóa đơn khóa',fields:[StaffUI.select('hocVienId','Học viên',students,'id','hoTen',true)],submit:'Xem đăng ký khóa',onSubmit:async body=>{
        const registrations=await Admin.api(`${StaffUI.prefix}/students/${body.hocVienId}/registrations`);
        // Open the second form after the current form has finished closing.
        setTimeout(()=>invoiceForm(registrations),0);
      }});
    }catch(error){toast(error.message,'err');}
  };
  function invoiceForm(registrations) {
    const available=registrations.filter(row=>row.trangThai!=='thoi_hoc'&&!invoices.some(invoice=>invoice.dangKyKhoaHocId===row.id));
    if(!available.length){Admin.dialog('Hóa đơn trọn khóa','<div class="dialog-body">Học viên chưa có đăng ký cần lập hóa đơn. Kiểm tra đăng ký khóa và các hóa đơn đã tạo.</div>');return;}
    Admin.formDialog({title:'Tạo hóa đơn trọn khóa',fields:[
      {name:'dangKyKhoaHocId',label:'Đăng ký khóa học',type:'select',required:true,options:available.map(row=>({value:row.id,label:`DK-${row.id} · ${row.tenKhoa} · ${Admin.money(row.hocPhiSnapshot)}`}))},
      {name:'hanThanhToan',label:'Hạn thanh toán',type:'date',nullable:true}
    ],submit:'Tạo hóa đơn nháp',onSubmit:async body=>{
      await Admin.api('/api/finance/tuition/invoices','POST',{...body,dangKyKhoaHocId:Number(body.dangKyKhoaHocId)});
      toast('Đã tạo hóa đơn.','ok');Admin.render();
    }});
  }
  window.publishSelectedInvoice = async () => {
    if(!selected){toast('Chọn hóa đơn cần phát hành.','err');return;}
    Admin.confirmDialog('Phát hành hóa đơn',`${selected.maHd} · ${Admin.money(selected.phaiThu)} · Gửi thông báo phụ huynh`,async()=>{
      await Admin.api(`/api/finance/tuition/invoices/${selected.id}/publish`,'POST',{});
      toast('Đã phát hành hóa đơn và lưu thông báo phụ huynh.','ok');Admin.render();
    });
  };
  window.doPayment = () => {
    if(!selected){toast('Chọn hóa đơn trước khi thu tiền.','err');return;}
    Admin.formDialog({title:'Admin xác nhận chứng từ thu tiền',fields:[{name:'maGiaoDich',label:'Mã chứng từ (dùng lại mã này khi gửi lại)',required:true,maxLength:60}],submit:'Xác nhận thu',onSubmit:async body=>{
      await Admin.api(`/api/finance/tuition/invoices/${selected.id}/receipts`,'POST',{...body,soTien:Number(Admin.$('payAmt').value),phuongThuc:Admin.$('payMethodSel').value,ghiChu:Admin.$('payNote').value||null});
      toast('Đã ghi nhận phiếu thu và cập nhật công nợ.','ok');Admin.render();
    }});
  };
  window.printSelectedInvoice = async () => {
    if(!selected){toast('Chọn hóa đơn cần in.','err');return;}
    try {
      const row=await Admin.api('/api/finance/tuition/invoices/'+selected.id);
      StaffUI.print('Hóa đơn / Phiếu thu',`<h3>${Admin.esc(row.maHd)}</h3><p>${Admin.esc(row.tenHocVien)} · ${Admin.esc(row.tenPhuHuynh)} · ${Admin.esc(row.tenKhoa || '')}</p><p>Học phí trọn khóa ${Admin.money(row.phaiThu)} · Đã thu ${Admin.money(row.daThu)} · Còn lại ${Admin.money(Number(row.phaiThu)-Number(row.daThu))}</p>${Admin.table([{key:'ngayThanhToan',label:'Ngày thu'},{key:'maGiaoDich',label:'Mã chứng từ'},{label:'Số tiền',render:record=>Admin.money(record.soTien)},{key:'tenNguoiThu',label:'Người xác nhận'}],row.receipts)}`);
    }catch(error){toast(error.message,'err');}
  };
})();
(() => {
  const Admin=window.Admin;
  const money=Admin.money, esc=Admin.esc;
  const empty=columns=>`<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu.</td></tr>`;
  const confirmation=Admin.confirmDialog;
  let transactions=[];
  Admin.register('admin-payments',async()=>{
    const data=await Admin.api('/api/finance/tuition/transactions');
    return {title:'Đối soát Thanh toán Web',html:Admin.view('admin-payments'),bind(){
      transactions=data;
      Admin.$('adminOnlineTxBody').innerHTML=transactions.map(row=>`<tr><td><b class="mono">GD-${row.id}</b><br><small>${esc(Admin.date(row.createdAt))}</small></td><td><b>${esc(row.maHd)}</b><br><small>HV ${esc(row.tenHocVien||'—')}</small></td><td>${esc(row.tenPhuHuynh)}</td><td>${Admin.tag(row.nhaCungCap,'i')}</td><td><b style="color:var(--ok)">${money(row.soTien)}</b></td><td class="mono" style="font-size:11px">${esc(row.maYeuCau)}<br>${esc(row.webhookHash||'Chưa có sự kiện từ cổng')}</td><td>${Admin.tag(row.trangThai==='thanh_cong'?'Đã xác nhận · '+(row.tenAdmin||'Admin'):row.trangThai,row.trangThai==='thanh_cong'?'ok':'w')}</td><td>${row.trangThai==='cho_xac_nhan'?`<button class="btn p sm" onclick="adminConfirmOnlineTx(${row.id})">Xác nhận & Lập phiếu thu</button>`:esc(row.thanhToanId?'Phiếu thu #'+row.thanhToanId:'—')}</td></tr>`).join('')||empty(8);
    }};
  });
  window.adminConfirmOnlineTx=id=>{
    const row=transactions.find(item=>item.id===Number(id));
    confirmation('Xác nhận giao dịch trực tuyến',`GD-${id} · ${row.maHd} · ${money(row.soTien)}`,async()=>{
      await Admin.api(`/api/finance/tuition/transactions/${id}/confirm`,'POST',{});
      toast('Đã lập phiếu thu, cập nhật công nợ và ghi nhận thông báo.','ok');Admin.render();
    });
  };

})();
