'use strict';
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
