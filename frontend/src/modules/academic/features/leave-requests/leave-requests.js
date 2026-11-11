'use strict';
window.AdminClassLeave={
  list:()=>Admin.api('/api/academic/leave-requests/classes'),
  decide:(id,body)=>Admin.api(`/api/academic/leave-requests/classes/${id}/decision`,'POST',body)
};
