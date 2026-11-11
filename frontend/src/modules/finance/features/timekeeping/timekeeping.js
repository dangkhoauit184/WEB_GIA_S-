'use strict';
(() => {
  const Admin=window.Admin;
  const esc=Admin.esc;
  const empty=columns=>`<tr><td colspan="${columns}" class="admin-empty">Chưa có dữ liệu.</td></tr>`;
  const confirmation=Admin.confirmDialog;
  let classLeaves=[],corrections=[];
  const decisionLabel=value=>({cho_duyet:'Chờ Admin duyệt',da_duyet:'Đã duyệt',tu_choi:'Đã từ chối'}[value]||value);
  Admin.register('admin-exceptions',async()=>{
    const results=await Promise.all([AdminClassLeave.list(),Admin.api('/api/finance/timekeeping/corrections'),Admin.api('/api/finance/payroll?month='+Admin.period)]);
    [classLeaves,corrections]=results;
    return {title:'Duyệt Nghỉ lớp, Công & Mở khóa',html:Admin.view('admin-exceptions'),bind(){
      Admin.$('adminClassLeaveBody').innerHTML=classLeaves.map(row=>`<tr><td><b>NL-${row.id}</b></td><td>${esc(row.maLop)} (${esc(row.ngay)})</td><td>${esc(row.nguoiGui)}</td><td>${esc(row.lyDo)}</td><td>${Admin.tag(decisionLabel(row.trangThai),row.trangThai==='cho_duyet'?'w':row.trangThai==='da_duyet'?'ok':'r')}</td><td>${row.trangThai==='cho_duyet'?`<div class="row"><button class="btn p sm" onclick="adminApproveLeaveOrAdj('class',${row.id},'da_duyet')">Duyệt nghỉ cả lớp</button><button class="btn sm" onclick="adminApproveLeaveOrAdj('class',${row.id},'tu_choi')">Từ chối</button></div>`:'Đã xử lý'}</td></tr>`).join('')||empty(6);
      Admin.$('adminTimeAdjBody').innerHTML=corrections.map(row=>`<tr><td>DC-${row.id}</td><td><b>${esc(row.hoTen)}</b></td><td>${esc(row.maLop)} (${esc(row.ngay)})</td><td>${esc(Admin.date(row.checkinMoi))} → ${esc(Admin.date(row.checkoutMoi))}<br>${esc(row.lyDo)}</td><td>${row.loai==='huy_cong'?'0 giờ':row.phutDeXuat!=null?Number((Number(row.phutDeXuat)/60).toFixed(2))+' giờ':'Chưa đủ giờ'}</td><td>${Admin.tag(decisionLabel(row.trangThai),row.trangThai==='cho_duyet'?'w':row.trangThai==='da_duyet'?'ok':'r')}</td><td>${row.trangThai==='cho_duyet'?`<div class="row"><button class="btn p sm" onclick="adminApproveLeaveOrAdj('correction',${row.id},'da_duyet')">Duyệt chỉnh công</button><button class="btn sm" onclick="adminApproveLeaveOrAdj('correction',${row.id},'tu_choi')">Từ chối</button></div>`:'Đã xử lý'}</td></tr>`).join('')||empty(7);
      Admin.$('adminUnlockBody').innerHTML=results[2].items.filter(row=>row.trangThai==='da_chot').map(row=>`<tr><td>BC-${row.id}</td><td>${esc(row.hoTen)}</td><td>${esc(Admin.period)}</td><td>Admin nhập lý do khi mở khóa</td><td>${Admin.tag('Đã chốt, chưa trả','purple')}</td><td><button class="btn purple sm" onclick="adminUnlockPayroll(${row.id})">Mở khóa bảng công</button></td></tr>`).join('')||empty(6);
      document.querySelector('#admin-exceptions .pg-head').insertAdjacentHTML('beforeend',`<div class="row"><div class="period-picker" data-period-picker="exceptionsMonth"></div><input id="exceptionsMonth" type="hidden" aria-label="Kỳ cần mở khóa" value="${Admin.period}" onchange="changePayrollMonth(this.value)"></div>`);
    }};
  });
  window.adminApproveLeaveOrAdj=(type,id,status)=>confirmation(status==='da_duyet'?'Admin phê duyệt':'Admin từ chối',type==='class'?'Yêu cầu nghỉ cả lớp':'Yêu cầu điều chỉnh giờ công',async body=>{
    const decision={trangThai:status,ghiChu:body.ghiChu||null};
    if(type==='class')await AdminClassLeave.decide(id,decision);
    else await Admin.api(`/api/finance/timekeeping/corrections/${id}/decision`,'POST',decision);
    toast('Đã xử lý và cập nhật dữ liệu gốc.','ok');Admin.render();
  },[{name:'ghiChu',label:'Ghi chú xử lý',type:'textarea',maxLength:255}]);

})();
