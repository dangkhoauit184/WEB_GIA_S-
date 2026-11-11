'use strict';
(() => {
  const Admin=window.Admin;
  const actions={them:'Thêm mới',sua:'Cập nhật',xoa:'Xóa',dang_nhap:'Đăng nhập',chot_cong:'Chốt công',mo_khoa_cong:'Mở khóa công',duyet:'Phê duyệt',khac:'Khác'};
  let filters={q:'',bang:'',hanhDong:'',tuNgay:'',denNgay:'',page:1,perPage:25};
  Admin.register('admin-audit',async()=>{
    const data=await Admin.api('/api/administration/audit?'+Admin.query(filters));
    return {title:'Kiểm toán & Giám sát',html:Admin.view('admin-audit'),bind(){
      const card=document.querySelector('#admin-audit .card');
      card.insertAdjacentHTML('afterbegin',`<form class="admin-filter" id="audit-filter"><label>Tìm kiếm<input name="q" type="search" value="${Admin.esc(filters.q)}"></label><label>Bảng dữ liệu<input name="bang" value="${Admin.esc(filters.bang)}"></label><label>Thao tác<select name="hanhDong"><option value="">Tất cả</option>${Object.entries(actions).map(([key,label])=>`<option value="${key}" ${key===filters.hanhDong?'selected':''}>${label}</option>`).join('')}</select></label><label>Từ ngày<input name="tuNgay" type="date" value="${Admin.esc(filters.tuNgay)}"></label><label>Đến ngày<input name="denNgay" type="date" value="${Admin.esc(filters.denNgay)}"></label><button class="btn sm">Tra cứu</button></form>`);
      Admin.$('auditBody').innerHTML=data.items.map(row=>`<tr><td class="mono" style="font-size:11px">${Admin.esc(Admin.date(row.createdAt))}</td><td><b>${Admin.esc(row.hoTen||'Hệ thống')}</b></td><td>${Admin.tag(row.bang,'i')}</td><td>${Admin.esc(actions[row.hanhDong]||row.hanhDong)}</td><td><button class="btn sm" onclick="openAuditDetail(${row.id})">Xem dữ liệu trước/sau</button></td></tr>`).join('')||'<tr><td colspan="5" class="admin-empty">Chưa có dữ liệu phù hợp.</td></tr>';
      card.insertAdjacentHTML('beforeend',Admin.pager(data));
      Admin.bindPager(page=>{filters.page=page;Admin.render();});
      Admin.$('audit-filter').onsubmit=event=>{event.preventDefault();filters={...filters,...Object.fromEntries(new FormData(event.target)),page:1};Admin.render();};
    }};
  });
  window.openAuditDetail=async id=>{
    try {
      const row=await Admin.api('/api/administration/audit/'+id);
      Admin.dialog('Nhật ký #'+id,`<div class="dialog-body"><p>${Admin.esc(row.hoTen||'Hệ thống')} · ${Admin.esc(Admin.date(row.createdAt))} · ${Admin.esc(row.bang)}</p><h3>Dữ liệu trước</h3><pre class="audit-json">${Admin.esc(JSON.stringify(row.duLieuCu,null,2))}</pre><h3>Dữ liệu sau</h3><pre class="audit-json">${Admin.esc(JSON.stringify(row.duLieuMoi,null,2))}</pre></div>`);
    }catch(error){toast(error.message,'err');}
  };
})();
