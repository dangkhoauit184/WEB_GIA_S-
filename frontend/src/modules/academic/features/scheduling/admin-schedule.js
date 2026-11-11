'use strict';
var SESSIONS=[], CLASSES=[], currentPortal='admin';
var calCurrentDate=new Date(), calViewMode='week';
var calFilters={branch:'',teacher:'',classCode:'',subject:'',status:''};

function pad(n){return String(n).padStart(2,'0')}

function formatYMD(d){return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`}

function addDays(d,n){const r=new Date(d);r.setDate(r.getDate()+n);return r}

function getMonday(d){
  const date=new Date(d);
  const day=date.getDay();
  const diff=date.getDate()-day+(day===0?-6:1);
  const mon=new Date(date.setDate(diff));
  mon.setHours(0,0,0,0);
  return mon;
}

function renderSharedCalendar(targetContainerId, roleScope){
  const container = document.getElementById(targetContainerId);
  if(!container) return;

  const mon = getMonday(calCurrentDate);
  const sun = addDays(mon, 6);
  const todayYMD = formatYMD(new Date());

  let visibleSessions = SESSIONS.filter(s => {
    if(roleScope === 'teacher'){
      if(s.mainTeacher !== PORTALS.teacher.who && s.ta !== PORTALS.teacher.who) return false;
    } else if(roleScope === 'tutor'){
      if(s.mainTeacher !== PORTALS.tutor.who) return false;
    } else if(roleScope === 'ta'){
      if(s.ta !== PORTALS.ta.who) return false;
    } else if(roleScope === 'parent'){
      const targetChildId = selectedParentKidId || 'HV-0019';
      const cObj = CLASSES.find(c => c.code === s.classCode);
      if(!cObj || !cObj.studentIds.includes(targetChildId)) return false;
    }
    if(calFilters.branch && s.branch !== calFilters.branch) return false;
    if(calFilters.teacher && s.mainTeacher !== calFilters.teacher) return false;
    if(calFilters.classCode && s.classCode !== calFilters.classCode) return false;
    if(calFilters.subject && s.subject !== calFilters.subject) return false;
    if(calFilters.status && s.status !== calFilters.status) return false;
    return true;
  });

  const rangeTitle = (calViewMode === 'week') ?
    `Tuần: ${pad(mon.getDate())}/${pad(mon.getMonth()+1)} – ${pad(sun.getDate())}/${pad(sun.getMonth()+1)}/${sun.getFullYear()}` :
    `Tháng ${pad(calCurrentDate.getMonth()+1)} / ${calCurrentDate.getFullYear()}`;

  const isStaffOrAdmin = (roleScope === 'admin' || roleScope === 'staff');

  let filterBarHtml = '';
  if(isStaffOrAdmin){
    filterBarHtml = `
      <div class="cal-filter-bar">
        <span style="font-weight:600;color:var(--g700)">Bộ lọc:</span>
        <select onchange="onCalFilterChange('branch', this.value)">${calendarOptions('branch')}</select>
        <select onchange="onCalFilterChange('teacher', this.value)">${calendarOptions('teacher')}</select>
        <select onchange="onCalFilterChange('classCode', this.value)">${calendarOptions('classCode')}</select>
        <select onchange="onCalFilterChange('status', this.value)">
          <option value="">Tất cả trạng thái</option>
          <option value="upcoming" ${calFilters.status==='upcoming'?'selected':''}>Sắp diễn ra</option>
          <option value="completed" ${calFilters.status==='completed'?'selected':''}>Đã hoàn thành</option>
          <option value="makeup" ${calFilters.status==='makeup'?'selected':''}>Buổi dạy bù</option>
          <option value="cancelled" ${calFilters.status==='cancelled'?'selected':''}>Đã hủy</option>
        </select>
        <button class="btn sm" onclick="resetCalFilters()" style="margin-left:auto;color:var(--err)">Đặt lại lọc</button>
      </div>
    `;
  }

  let contentHtml = '';
  if(calViewMode === 'week'){
    const dayLabels = ['THỨ 2','THỨ 3','THỨ 4','THỨ 5','THỨ 6','THỨ 7','CN'];
    let colsHtml = '';
    for(let i=0; i<7; i++){
      const d = addDays(mon, i);
      const dYMD = formatYMD(d);
      const isToday = (dYMD === todayYMD);
      const daySessions = visibleSessions.filter(s => s.date === dYMD).sort((a,b)=>a.startTime.localeCompare(b.startTime));

      colsHtml += `
        <div class="cal-day-col ${isToday?'is-today':''}">
          <div class="cal-col-head ${isToday?'is-today':''}">
            <div class="d-name">${dayLabels[i]}${isToday ? `<span class="d-today-badge">Hôm nay</span>` : ''}</div>
            <div class="d-num">${pad(d.getDate())}/${pad(d.getMonth()+1)}</div>
          </div>
          <div class="cal-col-body">
            ${daySessions.length > 0 ? daySessions.map(s => renderSessionCardHtml(s)).join('') : '<div class="cal-col-empty">—</div>'}
            ${roleScope==='staff' ? `<button onclick="openSessionForm('${dYMD}')" class="btn sm" style="margin-top:auto;width:100%;font-size:10.5px;padding:3px;justify-content:center;border-style:dashed;color:var(--g500)">+ Thêm ca</button>` : ''}
          </div>
        </div>
      `;
    }
    contentHtml = `<div class="cal-week-grid">${colsHtml}</div>`;
  } else {
    const year = calCurrentDate.getFullYear();
    const month = calCurrentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const startDay = firstDay.getDay();
    const lead = (startDay === 0) ? 6 : startDay - 1;

    const days = [];
    for(let i=lead; i>0; i--) days.push(new Date(year, month, 1 - i));
    const totalDays = new Date(year, month + 1, 0).getDate();
    for(let i=1; i<=totalDays; i++) days.push(new Date(year, month, i));
    const rem = 7 - (days.length % 7);
    if(rem < 7){
      for(let i=1; i<=rem; i++) days.push(new Date(year, month + 1, i));
    }

    let cellsHtml = '';
    days.forEach(d => {
      const dYMD = formatYMD(d);
      const isToday = (dYMD === todayYMD);
      const isOther = (d.getMonth() !== month);
      const daySessions = visibleSessions.filter(s => s.date === dYMD);

      cellsHtml += `
        <div class="cal-month-cell ${isToday?'is-today':''} ${isOther?'other-month':''}">
          <div class="row-b" style="margin-bottom:2px">
            <span style="font-size:11px;font-weight:700;color:${isToday?'var(--pri-d)':'var(--g700)'}">${d.getDate()}</span>
            ${roleScope==='staff' ? `<button onclick="openSessionForm('${dYMD}')" style="border:0;background:0;font-size:10px;color:var(--pri);font-weight:700">+</button>` : ''}
          </div>
          <div style="display:flex;flex-direction:column;gap:2px">
            ${daySessions.slice(0,2).map(s => `
              <div class="cal-m-chip ${s.status}" onclick="openSessionDetail('${s.id}')">
                <span>${s.startTime}</span> <b>${Admin.esc(s.classCode)}</b>
              </div>
            `).join('')}
            ${daySessions.length > 2 ? `<span style="font-size:9.5px;color:var(--pri-d);font-weight:600">+${daySessions.length-2} ca nữa</span>` : ''}
          </div>
        </div>
      `;
    });

    contentHtml = `
      <div class="cal-month-head">
        <div class="cal-month-head-cell">THỨ 2</div>
        <div class="cal-month-head-cell">THỨ 3</div>
        <div class="cal-month-head-cell">THỨ 4</div>
        <div class="cal-month-head-cell">THỨ 5</div>
        <div class="cal-month-head-cell">THỨ 6</div>
        <div class="cal-month-head-cell">THỨ 7</div>
        <div class="cal-month-head-cell">CN</div>
      </div>
      <div class="cal-month-grid">${cellsHtml}</div>
    `;
  }

  container.innerHTML = `
    <div class="cal-box">
      <div class="cal-bar">
        <div class="row" style="gap:6px">
          <button class="cal-nav-btn" onclick="calPrevPeriod('${targetContainerId}','${roleScope}')">
            <svg class="svg-ico sm" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg> Trước
          </button>
          <button class="btn sm" onclick="calGoToday('${targetContainerId}','${roleScope}')">Hôm nay</button>
          <button class="cal-nav-btn" onclick="calNextPeriod('${targetContainerId}','${roleScope}')">
            Sau <svg class="svg-ico sm" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
          </button>
        </div>

        <div>
          <button class="cal-range-btn" onclick="toast('Đang xem khoảng ngày: ${rangeTitle}','info')">
            <svg class="svg-ico" style="color:var(--pri)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>${rangeTitle}</span>
          </button>
        </div>

        <div class="row">
          <div class="seg">
            <button class="${calViewMode==='week'?'on':''}" onclick="setCalViewMode('week','${targetContainerId}','${roleScope}')">Lịch Tuần</button>
            <button class="${calViewMode==='month'?'on':''}" onclick="setCalViewMode('month','${targetContainerId}','${roleScope}')">Lịch Tháng</button>
          </div>
        </div>
      </div>
      ${filterBarHtml}
      ${contentHtml}
    </div>
  `;
}

function renderSessionCardHtml(s){
  let badgeHtml = '';
  if(s.status === 'completed') badgeHtml = `<span class="tag ok" style="position:absolute;top:6px;right:6px;font-size:9px;padding:1px 5px">Hoàn tất</span>`;
  else if(s.status === 'cancelled') badgeHtml = `<span class="tag r" style="position:absolute;top:6px;right:6px;font-size:9px;padding:1px 5px">Đã hủy</span>`;
  else if(s.status === 'makeup') badgeHtml = `<span class="tag purple" style="position:absolute;top:6px;right:6px;font-size:9px;padding:1px 5px">Dạy bù</span>`;

  const locIcon = s.isOnline
    ? `<svg class="svg-ico sm" style="color:var(--info)" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> Online`
    : `<svg class="svg-ico sm" style="color:var(--g400)" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> ${Admin.esc(s.branch)}`;

  return `
    <div class="ses-card ${s.status}" onclick="openSessionDetail('${s.id}')">
      ${badgeHtml}
      <div class="sc-code">${Admin.esc(s.classCode)}</div>
      <div class="sc-subj">${Admin.esc(s.subject)} · ${Admin.esc(s.className)}</div>
      <div class="sc-time">
        <svg class="svg-ico sm" style="width:11px;height:11px;opacity:.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        ${s.startTime} – ${s.endTime}
      </div>
      <div class="sc-loc">
        <span style="display:inline-flex;align-items:center;gap:4px">${locIcon}</span>
      </div>
    </div>
  `;
}

function setCalViewMode(mode, cid, role){
  calViewMode = mode;
  refreshActiveCalendars();
}

function onCalFilterChange(key, val){
  calFilters[key] = val;
  refreshActiveCalendars();
}

function resetCalFilters(){
  calFilters = { branch:'', teacher:'', classCode:'', subject:'', status:'' };
  refreshActiveCalendars();
}

function calPrevPeriod(cid, role){
  if(calViewMode==='week') calCurrentDate = addDays(calCurrentDate, -7);
  else calCurrentDate = new Date(calCurrentDate.getFullYear(), calCurrentDate.getMonth()-1, 1);
  refreshActiveCalendars();
}

function calNextPeriod(cid, role){
  if(calViewMode==='week') calCurrentDate = addDays(calCurrentDate, 7);
  else calCurrentDate = new Date(calCurrentDate.getFullYear(), calCurrentDate.getMonth()+1, 1);
  refreshActiveCalendars();
}

function calGoToday(cid, role){
  calCurrentDate = new Date();
  refreshActiveCalendars();
}

function setSfOnlineMode(isOnline){
  document.getElementById('sfBtnOffline').classList.toggle('on', !isOnline);
  document.getElementById('sfBtnOnline').classList.toggle('on', isOnline);
  document.getElementById('sfOfflineFields').style.display = isOnline ? 'none' : 'flex';
  document.getElementById('sfOnlineFields').style.display = isOnline ? 'block' : 'none';
}

var calendarStaff=[], calendarCampuses=[], calendarRooms=[];
var editingSessionId=null, selectedSessionId=null, makeupOriginId=null, calendarRequestVersion=0;
function calendarOptions(key) {
  const labels={branch:'Tất cả cơ sở',teacher:'Tất cả giáo viên',classCode:'Tất cả lớp học'};
  const field={branch:'branch',teacher:'mainTeacher',classCode:'classCode'}[key];
  const values=[...new Set(SESSIONS.map(row=>row[field]).filter(Boolean))].sort();
  return `<option value="">${labels[key]}</option>`+values.map(value=>`<option value="${Admin.esc(value)}" ${calFilters[key]===value?'selected':''}>${Admin.esc(value)}</option>`).join('');
}
async function loadAdminCalendar() {
  const version=++calendarRequestVersion;
  const start=formatYMD(getMonday(new Date(calCurrentDate.getFullYear(),calCurrentDate.getMonth(),1)));
  const end=formatYMD(addDays(new Date(calCurrentDate.getFullYear(),calCurrentDate.getMonth()+1,1),7));
  const [sessions,classes,staff,campuses,rooms]=await Promise.all([
    Admin.api('/api/academic/scheduling/sessions?'+Admin.query({tuNgay:start,denNgay:end})),
    Admin.api('/api/academic/classes'),Admin.api('/api/administration/staff'),
    Admin.api('/api/administration/catalog/campuses'),Admin.api('/api/administration/catalog/rooms')
  ]);
  if(version!==calendarRequestVersion || Admin.currentScreen()!=='admin-schedule')return false;
  calendarStaff=staff;calendarCampuses=campuses;calendarRooms=rooms;
  CLASSES=classes.map(row=>({...row,code:row.maLop,name:row.tenLop,subject:row.tenMonHoc,studentIds:[]}));
  SESSIONS=sessions.map(row=>{
    const cls=CLASSES.find(item=>item.id===row.lopId)||{};
    const online=row.phongHoc==='Online'||row.phongHoc==='Google Meet'||cls.hinhThuc==='online';
    return {...row,id:'SES-'+row.id,_id:row.id,classCode:row.maLop,className:row.tenLop,subject:cls.tenMonHoc||'',date:row.ngay,
      startTime:row.gioBatDau.slice(0,5),endTime:row.gioKetThuc.slice(0,5),mainTeacher:row.tenNguoiDayChinh||'',ta:row.tenTroGiang||'',
      isOnline:online,branch:online?'Online':(campuses.find(item=>item.id===(row.coSoId??cls.coSoId))?.tenCoSo||'Tại nhà học viên'),room:row.phongHoc||'',
      onlineLink:cls.linkHoc||'',topic:row.chuDeBaiHoc||'',_assistantIds:(row.troGiangIds||'').split(',').filter(Boolean).map(Number),
      status:['huy','doi'].includes(row.trangThai)?'cancelled':row.loai==='bu'?'makeup':row.trangThai==='da_day'?'completed':'upcoming'};
  });
  return true;
}
Admin.register('admin-schedule',async()=>{
  await loadAdminCalendar();
  return {title:'Giám sát Lịch toàn chuỗi',html:Admin.view('admin-schedule'),bind(){renderSharedCalendar('adminCalContainer','admin');}};
});
async function refreshActiveCalendars() {
  try {
    const loaded=await loadAdminCalendar();
    if(loaded&&document.getElementById('adminCalContainer'))renderSharedCalendar('adminCalContainer','admin');
  } catch(error){toast(error.message,'err');}
}
function openSessionDetail(id) {
  const session=SESSIONS.find(row=>row.id===id);
  if(!session)return;
  selectedSessionId=id;
  document.getElementById('dtModalTitle').textContent=session.classCode+' · '+session.date;
  document.getElementById('dtModalContent').innerHTML=`<div class="item"><span>Lớp</span><b>${Admin.esc(session.className)}</b></div><div class="item"><span>Thời gian</span><b>${session.startTime} – ${session.endTime}</b></div><div class="item"><span>Người dạy</span><b>${Admin.esc(session.mainTeacher||'Chưa phân công')}</b></div><div class="item"><span>Trợ giảng</span><b>${Admin.esc(session.ta||'—')}</b></div><div class="item"><span>Địa điểm</span><span>${Admin.esc(session.branch+' · '+session.room)}</span></div><p>${Admin.esc(session.topic)}</p>${session.isOnline&&/^https?:\/\//i.test(session.onlineLink)?`<p><a target="_blank" rel="noopener noreferrer" href="${Admin.esc(session.onlineLink)}">Mở phòng học trực tuyến</a></p>`:''}<div class="modal-act">${session.status==='upcoming'||session.status==='makeup'?`<button class="btn sm" onclick="rescheduleSessionDetail()">Chỉnh sửa / Dời lịch</button><button class="btn sm danger" onclick="cancelSessionDetail()">Admin hủy buổi</button>`:''}${session.status==='cancelled'?'<button class="btn p sm" onclick="makeupSessionDetail()">Xếp lịch bù cả lớp</button>':''}</div>`;
  document.getElementById('modalSessionDetail').classList.add('show');
}
function closeModalSessionDetail(){document.getElementById('modalSessionDetail').classList.remove('show');}
function rescheduleSessionDetail(){const id=selectedSessionId;closeModalSessionDetail();openSessionForm(id);}
function cancelSessionDetail(){
  const row=SESSIONS.find(item=>item.id===selectedSessionId);
  Admin.formDialog({title:'Admin xác nhận hủy buổi cả lớp',fields:[{name:'lyDo',label:'Lý do',required:true,maxLength:255}],onSubmit:async body=>{
    await Admin.api(`/api/academic/scheduling/sessions/${row._id}`,'PUT',{trangThai:'huy',huyBoi:'trung_tam',lyDoHuyDoi:body.lyDo});
    closeModalSessionDetail();toast('Đã hủy buổi và ghi dấu vết Admin.','ok');await refreshActiveCalendars();
  }});
}
function makeupSessionDetail(){
  const row=SESSIONS.find(item=>item.id===selectedSessionId);
  closeModalSessionDetail();openSessionForm();makeupOriginId=row._id;
  document.getElementById('sfClassSelect').value=String(row.lopId);onSfClassChanged();
  document.getElementById('sfSessionType').value='makeup';document.getElementById('sfMakeupScope').value='ca_lop';onSfTypeChanged();
  document.getElementById('sfDate').value=formatYMD(addDays(new Date(row.date+'T00:00:00'),7));
}
function openSessionForm(id){
  const row=SESSIONS.find(item=>item.id===id);
  editingSessionId=row?row._id:null;makeupOriginId=row?.buoiGocId||null;
  document.getElementById('sessionFormElement').reset();
  const classes=CLASSES.filter(item=>['sap_bat_dau','dang_hoc'].includes(item.trangThai)||item.id===row?.lopId);
  document.getElementById('sfClassSelect').innerHTML=classes.map(item=>`<option value="${item.id}">${Admin.esc(item.code+' · '+item.name)}</option>`).join('');
  document.getElementById('sfClassSelect').disabled=Boolean(row);
  if(row)document.getElementById('sfClassSelect').value=String(row.lopId);
  document.getElementById('sfTeacherSelect').innerHTML=calendarStaff.filter(item=>item.nhanSuId&&item.status==='active'&&(item.phanLoai.includes('giao_vien')||item.phanLoai.includes('gia_su'))).map(item=>`<option value="${item.userId}">${Admin.esc(item.hoTen)}</option>`).join('');
  document.getElementById('sfTaSelect').innerHTML='<option value="">Không phân công</option>'+calendarStaff.filter(item=>item.nhanSuId&&item.status==='active'&&item.phanLoai.includes('tro_giang')).map(item=>`<option value="${item.userId}">${Admin.esc(item.hoTen)}</option>`).join('');
  document.getElementById('sfBranchSelect').innerHTML='<option value="">Tại nhà học viên</option>'+calendarCampuses.filter(item=>item.isActive).map(item=>`<option value="${item.id}">${Admin.esc(item.tenCoSo)}</option>`).join('');
  document.getElementById('sfRoomSelect').innerHTML='<option value="">Chưa chọn phòng</option>'+calendarRooms.map(item=>`<option value="${Admin.esc(item.ten)}">${Admin.esc(item.ten)}</option>`).join('');
  onSfClassChanged();
  document.getElementById('sfDate').value=row?.date||formatYMD(calCurrentDate);
  document.getElementById('sfStartTime').value=row?.startTime||'18:00';
  document.getElementById('sfEndTime').value=row?.endTime||'20:00';
  document.getElementById('sfTopic').value=row?.topic||'';
  document.getElementById('sfIsRecurring').checked=false;
  document.getElementById('sfIsRecurring').disabled=Boolean(row);
  document.getElementById('sfRecurOptions').style.display='none';
  document.getElementById('sfSessionType').value=row?.loai==='bu'?'makeup':'upcoming';
  document.getElementById('sfMakeupScope').value=row?.phamViBu||'ca_nhan';
  onSfTypeChanged();
  if(row){
    document.getElementById('sfTeacherSelect').value=String(row.nguoiDayChinhUserId);
    document.getElementById('sfTaSelect').value=String(row._assistantIds[0]||'');
    document.getElementById('sfBranchSelect').value=String(row.coSoId||'');
    if(row.room&&!calendarRooms.some(item=>item.ten===row.room))document.getElementById('sfRoomSelect').insertAdjacentHTML('beforeend',`<option value="${Admin.esc(row.room)}">${Admin.esc(row.room)}</option>`);
    document.getElementById('sfRoomSelect').value=row.room;
    document.getElementById('sfOnlineLink').value=row.onlineLink;
    setSfOnlineMode(row.isOnline);
  }
  document.getElementById('modalSessionForm').classList.add('show');
}
function closeModalSessionForm(){document.getElementById('modalSessionForm').classList.remove('show');}
async function onSfClassChanged(){
  const cls=CLASSES.find(item=>item.id===Number(document.getElementById('sfClassSelect').value));
  if(!cls)return;
  const person=calendarStaff.find(item=>item.nhanSuId===cls.nguoiDayChinhId);
  document.getElementById('sfTeacherSelect').value=String(person?.userId||'');
  document.getElementById('sfBranchSelect').value=String(cls.coSoId||'');
  document.getElementById('sfRoomSelect').value=cls.phongHoc||'';
  document.getElementById('sfOnlineLink').value=cls.linkHoc||'';
  setSfOnlineMode(cls.hinhThuc==='online');
  try {
    const [origin,students]=await Promise.all([Admin.api('/api/academic/scheduling/sessions?lopId='+cls.id),Admin.api(`/api/academic/classes/${cls.id}/enrollments`)]);
    if(Number(document.getElementById('sfClassSelect').value)!==cls.id)return;
    document.getElementById('sfMakeupOrigin').innerHTML='<option value="">Chọn buổi gốc</option>'+origin.filter(item=>item.loai==='thuong').map(item=>`<option value="${item.id}">${Admin.esc(item.ngay+' '+item.gioBatDau.slice(0,5)+' · '+item.trangThai)}</option>`).join('');
    document.getElementById('sfMakeupOrigin').value=String(makeupOriginId||'');
    document.getElementById('sfMakeupStudent').innerHTML='<option value="">Chọn học viên</option>'+students.map(item=>`<option value="${item.hocVienId}">${Admin.esc(item.hoTen+' ('+item.maHv+')')}</option>`).join('');
    const existing=SESSIONS.find(item=>item._id===editingSessionId);
    if(existing?.hocBuIds)document.getElementById('sfMakeupStudent').value=String(existing.hocBuIds.split(',')[0]);
  }catch(error){toast(error.message,'err');}
}
async function handleSessionFormSubmit(event){
  event.preventDefault();
  const old=SESSIONS.find(item=>item._id===editingSessionId);
  const assistant=Number(document.getElementById('sfTaSelect').value)||null;
  const assistants=old&&assistant===old._assistantIds[0]?old._assistantIds:(assistant?[assistant]:[]);
  const body={lopId:Number(document.getElementById('sfClassSelect').value),ngay:document.getElementById('sfDate').value,
    gioBatDau:document.getElementById('sfStartTime').value,gioKetThuc:document.getElementById('sfEndTime').value,
    userId:Number(document.getElementById('sfTeacherSelect').value),troGiangIds:assistants,
    coSoId:Number(document.getElementById('sfBranchSelect').value)||null,phongHoc:document.getElementById('sfRoomSelect').value||null,
    chuDeBaiHoc:document.getElementById('sfTopic').value||null,linkHoc:document.getElementById('sfOnlineLink').value||null,
    isOnline:document.getElementById('sfBtnOnline').classList.contains('on'),
    loai:document.getElementById('sfSessionType').value==='makeup'?'bu':'thuong',
    phamViBu:document.getElementById('sfSessionType').value==='makeup'?document.getElementById('sfMakeupScope').value:'ca_lop',
    repeatCount:document.getElementById('sfIsRecurring').checked?Number(document.getElementById('sfRepeatCount').value):1};
  body.buoiGocId=body.loai==='bu'?Number(document.getElementById('sfMakeupOrigin').value)||null:null;
  const studentId=Number(document.getElementById('sfMakeupStudent').value);
  const oldStudents=(old?.hocBuIds||'').split(',').filter(Boolean).map(Number);
  body.hocVienIds=body.loai==='bu'&&body.phamViBu==='ca_nhan'?(studentId===oldStudents[0]?oldStudents:(studentId?[studentId]:[])):[];
  body.ghiChuBu=document.getElementById('sfMakeupNote').value||null;
  await Admin.busy(event.target.querySelector('button[type=submit]'),async()=>{
    await Admin.api('/api/academic/scheduling/admin-sessions'+(editingSessionId?'/'+editingSessionId:''),editingSessionId?'PUT':'POST',body);
    closeModalSessionForm();toast('Đã lưu lịch học và phân công.','ok');await refreshActiveCalendars();
  });
}
function exportSchedulePdf(){
  const start=formatYMD(calViewMode==='week'?getMonday(calCurrentDate):new Date(calCurrentDate.getFullYear(),calCurrentDate.getMonth(),1));
  const end=formatYMD(calViewMode==='week'?addDays(getMonday(calCurrentDate),7):new Date(calCurrentDate.getFullYear(),calCurrentDate.getMonth()+1,1));
  const rows=SESSIONS.filter(row=>row.date>=start&&row.date<end&&(!calFilters.branch||row.branch===calFilters.branch)&&(!calFilters.teacher||row.mainTeacher===calFilters.teacher)&&(!calFilters.classCode||row.classCode===calFilters.classCode)&&(!calFilters.status||row.status===calFilters.status));
  Admin.dialog('Báo cáo Lịch toàn chuỗi',`<div class="dialog-body"><h2>EDUCENTER ERP</h2>${Admin.table([{key:'date',label:'Ngày'},{key:'classCode',label:'Lớp'},{label:'Giờ',render:row=>row.startTime+'–'+row.endTime},{key:'mainTeacher',label:'Người dạy'},{key:'branch',label:'Cơ sở'},{key:'room',label:'Phòng'},{key:'status',label:'Trạng thái'}],rows)}</div><div class="dialog-foot"><button class="btn p" onclick="window.print()">In / Lưu PDF</button></div>`);
}
function onSfTypeChanged(){
  const makeup=document.getElementById('sfSessionType').value==='makeup';
  document.getElementById('sfMakeupWrap').style.display=makeup?'block':'none';
  document.getElementById('sfMakeupStudent').disabled=document.getElementById('sfMakeupScope').value==='ca_lop';
}
function onSfBranchChanged(){checkSfConflicts();}
function checkSfConflicts(){
  const date=document.getElementById('sfDate').value;
  const start=document.getElementById('sfStartTime').value, end=document.getElementById('sfEndTime').value;
  const userId=Number(document.getElementById('sfTeacherSelect').value);
  const conflict=SESSIONS.find(row=>row._id!==editingSessionId&&row.date===date&&row.status!=='cancelled'&&row.startTime<end&&row.endTime>start&&(row.nguoiDayChinhUserId===userId||row._assistantIds.includes(userId)));
  document.getElementById('sfConflictBanner').style.display=conflict?'block':'none';
  if(conflict)document.getElementById('sfConflictText').textContent='Người dạy đã được phân công tại '+conflict.classCode+' '+conflict.startTime+'–'+conflict.endTime+'.';
}
