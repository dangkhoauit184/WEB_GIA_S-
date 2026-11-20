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
  let transactions=[], bankAccounts=[], payosStatus={};
  function bankCard() {
    return `<div class="card"><div class="row-b"><h2>Mã QR nhận học phí</h2><button class="btn p sm" onclick="adminBankForm()">+ Tải QR / Thêm tài khoản</button></div><p>Chọn một mã mặc định cho hóa đơn mới. Mỗi hóa đơn có QR riêng kèm đúng số tiền và nội dung chuyển khoản. Yêu cầu đang chờ thanh toán giữ tài khoản đã chọn ban đầu.</p><div class="banner">${payosStatus.ready?'payOS đã kết nối webhook · Chỉ tự ghi nhận sau khi xác thực giao dịch ngân hàng.':`payOS chưa sẵn sàng. ${!payosStatus.enabled?'Chưa bật kết nối. ':''}${esc((payosStatus.missing||[]).join(' · '))} ${payosStatus.configured?'Cần xác minh webhook.':''}`} ${payosStatus.configured?'<button class="btn sm" onclick="adminConnectPayos()">Kiểm tra kết nối webhook</button>':''}</div>${Admin.table([
      {label:'Ảnh QR',render:r=>r.qrImageHash?`<button class="btn sm" onclick="adminQrPreview('${esc(r.id)}')">Xem QR</button>`:'Chưa tải ảnh'},
      {label:'Ngân hàng',render:r=>esc(r.bankName)+' · '+esc(r.bankBin)},
      {label:'Số tài khoản',key:'accountNo'},{label:'Chủ tài khoản',key:'accountName'},
      {label:'Trạng thái',render:r=>Admin.tag(r.isDefault?'Mặc định':r.active?'Đang sử dụng':'Ngừng dùng',r.isDefault?'ok':'')+(r.isDemo?' · DEMO':'')},
      {label:'Thao tác',render:r=>`<button class="btn sm" onclick="adminBankForm('${esc(r.id)}')">Sửa</button> ${!r.isDefault&&r.active?`<button class="btn sm p" onclick="adminBankDefault('${esc(r.id)}')">Chọn mặc định</button>`:''}`}
    ],bankAccounts,'Chưa có tài khoản nhận tiền. Thêm tài khoản ngân hàng thật để bật QR chuyển khoản.')}</div>`;
  }
  window.adminBankForm=(id='')=>{
    const row=bankAccounts.find(r=>r.id===id);
    let qrImageHash=row?.qrImageHash||null;
    Admin.formDialog({title:row?'Sửa tài khoản nhận học phí':'Thêm tài khoản nhận học phí',fields:[
      {name:'bankName',label:'Tên ngân hàng',required:true,value:row?.bankName,maxLength:50},
      {name:'bankBin',label:'Mã BIN ngân hàng',help:'6 chữ số trên thông tin mã VietQR do ngân hàng cung cấp.',required:true,value:row?.bankBin,maxLength:6},
      {name:'accountNo',label:'Số tài khoản',required:true,value:row?.accountNo,maxLength:19},
      {name:'accountName',label:'Tên chủ tài khoản',required:true,value:row?.accountName,maxLength:70},
      {name:'active',label:'Sử dụng',type:'select',value:row?.active===false?'0':'1',options:[{value:'1',label:'Đang sử dụng'},{value:'0',label:'Ngừng sử dụng'}]},
      {name:'isDemo',label:'Loại tài khoản',type:'select',value:row?.isDemo===false?'0':'1',options:[{value:'1',label:'Minh họa demo · Không tạo mã chuyển tiền'},{value:'0',label:'Tài khoản ngân hàng thật đã kiểm tra thông tin'}]},
    ],onSubmit:async body=>{delete body.qrUpload;body.active=body.active==='1';body.isDemo=body.isDemo==='1';if(qrImageHash)body.qrImageHash=qrImageHash;await Admin.api('/api/finance/tuition/bank-accounts'+(id?'/'+id:''),id?'PUT':'POST',body);Admin.render();}});
    const form=Admin.$('dialog-form');
    form.querySelector('.dialog-body').insertAdjacentHTML('afterbegin','<label>Ảnh QR từ ứng dụng ngân hàng<input type="file" name="qrUpload" accept="image/png,image/jpeg"><small>PNG/JPG dưới 750 KB. Hệ thống đọc và đối chiếu số tài khoản từ ảnh.</small></label><div id="bankQrPreview" aria-live="polite"></div>');
    const showImage=url=>{Admin.$('bankQrPreview').innerHTML=url?`<img src="${esc(url)}" alt="QR ngân hàng đã tải" style="display:block;max-width:220px;max-height:240px;margin:auto"><p>Ảnh gốc để kiểm tra tài khoản. QR thanh toán từng hóa đơn được tạo kèm số tiền và mã đối soát riêng.</p>`:'';};
    if(row?.qrImageHash) Admin.api(`/api/finance/tuition/bank-accounts/${id}/qr-image`).then(r=>{if(form.isConnected)showImage(r.imageUrl);}).catch(error=>{if(form.isConnected)Admin.$('dialog-error').textContent=error.message;});
    form.elements.qrUpload.addEventListener('change',async event=>{
      const file=event.target.files[0];if(!file)return;
      Admin.$('dialog-submit').disabled=true;
      Admin.$('dialog-error').textContent='Đang kiểm tra ảnh QR…';
      try {
        if(!['image/png','image/jpeg'].includes(file.type)||file.size>750000)throw new Error('Chọn ảnh PNG/JPG dưới 750 KB.');
        const payload=new FormData();payload.append('qrImage',file);
        const result=await Admin.api('/api/finance/tuition/qr-images','POST',payload);
        if(!form.isConnected)return;
        qrImageHash=result.imageHash;form.elements.bankBin.value=result.bankBin;form.elements.accountNo.value=result.accountNo;
        showImage(result.imageUrl);Admin.$('dialog-error').textContent='';Admin.$('dialog-submit').disabled=false;
      }catch(error){if(form.isConnected)Admin.$('dialog-error').textContent=error.message;}
    });
  };
  window.adminQrPreview=async id=>{try{const r=await Admin.api(`/api/finance/tuition/bank-accounts/${id}/qr-image`);Admin.dialog('Ảnh QR tài khoản nhận học phí',`<div class="dialog-body">${r.imageUrl?`<img alt="QR ngân hàng" src="${esc(r.imageUrl)}" style="max-width:100%;max-height:440px;display:block;margin:auto">`:'Ảnh chưa có trên máy chủ.'}<p>Phụ huynh thanh toán bằng QR riêng của hóa đơn để có đúng số tiền và mã đối soát.</p></div>`);}catch(error){toast(error.message,'err');}};
  window.adminConnectPayos=async()=>{try{await Admin.api('/api/finance/tuition/payos/connect','POST',{});toast('payOS đã xác minh webhook.','ok');Admin.render();}catch(error){toast(error.message,'err');}};
  window.adminCancelPayos=id=>confirmation('Hủy yêu cầu payOS chưa nhận tiền','Chỉ hủy khi payOS xác minh chưa nhận tiền. Phụ huynh có thể mở lại hóa đơn để nhận QR mới.',async()=>{await Admin.api(`/api/finance/tuition/transactions/${id}/cancel-payos`,'POST',{});toast('Đã hủy yêu cầu chưa nhận tiền.','ok');Admin.render();});
  window.adminCheckPayos=async id=>{try{const r=await Admin.api(`/api/finance/tuition/transactions/${id}/check-payos`,'POST',{});toast(r.paid?'Đã xác minh khoản thu với payOS.':r.review||'payOS chưa nhận đủ khoản thanh toán.',r.review?'err':'ok');Admin.render();}catch(error){toast(error.message,'err');}};
  window.adminBankDefault=id=>{
    const row=bankAccounts.find(r=>r.id===id);
    confirmation('Chọn tài khoản nhận học phí mặc định',`${row.bankName} · ${row.accountNo} · ${row.accountName}`,async()=>{await Admin.api(`/api/finance/tuition/bank-accounts/${id}/default`,'POST',{});Admin.render();});
  };
  Admin.register('admin-payments',async()=>{
    const [data,banks,gateway]=await Promise.all([Admin.api('/api/finance/tuition/transactions'),Admin.api('/api/finance/tuition/bank-accounts'),Admin.api('/api/finance/tuition/payos/status')]);
    bankAccounts=banks;payosStatus=gateway;
    return {title:'Đối soát Thanh toán Web',html:Admin.view('admin-payments').replace('<div class="card">',bankCard()+'<div class="card">'),bind(){
      transactions=data;
      Admin.$('adminOnlineTxBody').innerHTML=transactions.map(row=>`<tr><td><b class="mono">GD-${row.id}</b><br><small>${esc(Admin.date(row.createdAt))}</small></td><td><b>${esc(row.maHd)}</b><br><small>HV ${esc(row.tenHocVien||'—')}</small></td><td>${esc(row.tenPhuHuynh)}</td><td>${Admin.tag(row.nhaCungCap,'i')}</td><td><b style="color:var(--ok)">${money(row.soTien)}</b></td><td class="mono" style="font-size:11px">${esc(row.maYeuCau)}<br>${transferDetails(row)}</td><td>${Admin.tag(row.trangThai==='thanh_cong'?'Đã xác nhận · '+(row.tenAdmin||'Admin'):({cho_xac_nhan:'Chờ đối soát',khoi_tao:'Đang khởi tạo',that_bai:'Thất bại',het_han:'Hết hạn',da_huy:'Đã hủy',huy:'Đã hủy'}[row.trangThai]||row.trangThai),row.trangThai==='thanh_cong'?'ok':'w')}</td><td>${row.nhaCungCap==='PAYOS'&&row.trangThai!=='thanh_cong'?`<button class="btn p sm" onclick="adminCheckPayos(${row.id})">Kiểm tra payOS</button> ${['khoi_tao','cho_xac_nhan'].includes(row.trangThai)?`<button class="btn sm" onclick="adminCancelPayos(${row.id})">Hủy yêu cầu</button>`:''}`:row.trangThai==='cho_xac_nhan'?`<button class="btn p sm" onclick="adminConfirmOnlineTx(${row.id})">Xác nhận & Lập phiếu thu</button>`:esc(row.thanhToanId?'Phiếu thu #'+row.thanhToanId:'—')}</td></tr>`).join('')||empty(8);
    }};
  });
  function transferDetails(row) {
    const details=typeof row.transferDetails==='string'?JSON.parse(row.transferDetails):row.transferDetails;
    if(row.paymentReview&&row.trangThai!=='thanh_cong')return `<b class="form-error">${esc(row.paymentReview)}</b>`;
    if(details?.bank)return `${esc(details.bank.bankName)} · ${esc(details.bank.accountNo)}<br>${esc(details.bank.accountName)}<br>Nội dung: ${esc(details.description||details.memo)}`;
    return details?.receivingAccount?`${esc(details.receivingAccount.bankName)} · ${esc(details.receivingAccount.accountNo)}<br>${esc(details.receivingAccount.accountName)}<br>Nội dung: ${esc(details.memo)}${details.receivingAccount.isDemo?'<br>DEMO · Không có tiền thật':''}`:'Đối chiếu chứng từ chuyển khoản';
  }
  window.adminConfirmOnlineTx=id=>{
    const row=transactions.find(item=>item.id===Number(id));
    confirmation('Xác nhận giao dịch trực tuyến',`GD-${id} · ${row.maHd} · ${money(row.soTien)}. Chỉ xác nhận sau khi đã đối chiếu tiền vào và nội dung chuyển khoản trong ngân hàng.`,async()=>{
      await Admin.api(`/api/finance/tuition/transactions/${id}/confirm`,'POST',{});
      toast('Đã lập phiếu thu, cập nhật công nợ và ghi nhận thông báo.','ok');Admin.render();
    });
  };

})();
