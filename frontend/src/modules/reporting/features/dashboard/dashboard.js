'use strict';
(() => {
  const Admin=window.Admin;
  Admin.register('admin-dash',async () => {
    const data=await Admin.api('/api/reporting/dashboard');
    return {title:'Tổng quan Quản trị cấp cao',html:Admin.view('admin-dash'),bind() {
      const figures=document.querySelectorAll('#admin-dash .stats .n');
      [data.counts.students,`${data.counts.courses} Khóa`,data.counts.classes,Admin.money(data.received)].forEach((value,index) => {figures[index].textContent=value;});
      document.querySelector('#admin-dash .stats .st:last-child span').textContent='Doanh thu trọn khóa T'+Number(data.month.slice(-2));
      const max=Math.max(1,...data.revenue.map(row=>Math.abs(row.amount)));
      Admin.$('adminRevenueBars').innerHTML=data.revenue.map(row=>`<i style="height:${Math.max(1,Math.round(Math.abs(row.amount)/max*100))}%;${row.amount<0?'background:var(--err)':''}" data-m="T${Number(row.month.slice(-2))}" data-v="${Admin.esc(Admin.money(row.amount))}"></i>`).join('');
      const pending=[
        [data.pending.payments,'Giao dịch chờ Admin đối soát và lập phiếu thu','Đối soát tiền','admin-payments','p'],
        [data.pending.enrollments,'Đăng ký khóa chờ Admin xếp lớp','Xếp lớp ngay','admin-courses',''],
        [data.pending.corrections+data.pending.classLeave,'Điều chỉnh giờ công và nghỉ nguyên buổi cả lớp chờ Admin duyệt','Phê duyệt','admin-exceptions','warn']
      ];
      Admin.$('adminPendingWork').innerHTML=pending.map(([count,label,button,screen,style])=>`<div class="item"><span><b>${count}</b> ${Admin.esc(label)}</span><button class="btn sm ${style}" onclick="showS('${screen}')">${button}</button></div>`).join('')+`<div class="item"><span>Mở khóa kỳ công đã chốt chưa chi trả</span><button class="btn sm purple" onclick="showS('admin-exceptions')">Xem xét</button></div>`;
      Admin.$('adminRecentAudit').innerHTML=data.recent.map(row=>`<div class="item"><div><b>${Admin.esc(row.hoTen||'Hệ thống')}</b><br><span style="font-size:12px;color:var(--g500)">${Admin.esc(row.hanhDong+' · '+row.bang+' · '+Admin.date(row.createdAt))}</span></div>${Admin.tag(row.hanhDong,'i')}</div>`).join('')||'<div class="item">Chưa có hoạt động.</div>';
      const totalNotif = Number(data.pending.payments||0)+Number(data.pending.enrollments||0)+Number(data.pending.corrections||0)+Number(data.pending.classLeave||0);
      if(Admin.$('nCount')){Admin.$('nCount').textContent=String(totalNotif);Admin.$('nCount').style.display=totalNotif>0?'grid':'none';}
    }};
  });
})();
