/**
 * EduCenter ERP - Frontend Application Configuration
 */
const getPrefix = () => {
  if (typeof window === 'undefined') return '';
  const path = window.location.pathname;
  const idx = path.indexOf('/frontend');
  return idx !== -1 ? path.substring(0, idx) + '/frontend' : '';
};

const _prefix = getPrefix();

const AppConfig = {
  // API Backend Base URL (Hỗ trợ cả port 5000 chuẩn và port 8080)
  API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? 'http://localhost:5000/api'
    : '/api',

  // Khóa lưu trữ trong localStorage / sessionStorage
  STORAGE_KEYS: {
    TOKEN: 'educenter_token',
    USER: 'educenter_user',
    CURRENT_STUDENT_ID: 'educenter_selected_student_id',
  },

  // Cổng giao diện tương ứng theo vai trò (Role-based Routing)
  PORTAL_ROUTES: {
    ADMIN: _prefix + '/src/modules/reporting/features/dashboard/dashboard.html',
    GIAO_VU: _prefix + '/src/modules/academic/features/classes/classes.html',
    GIAO_VIEN: _prefix + '/src/modules/academic/features/attendance/attendance.html',
    GIA_SU: _prefix + '/src/modules/academic/features/attendance/attendance.html',
    TRO_GIANG: _prefix + '/src/modules/academic/features/attendance/attendance.html',
    PHU_HUYNH: _prefix + '/src/modules/people/features/parents/parents.html',
  },

  // Trang đăng nhập và trang công khai
  AUTH_PAGE: _prefix + '/src/modules/administration/features/auth/auth.html',
  LANDING_PAGE: _prefix + '/index.html',
};

if (typeof window !== 'undefined') {
  window.AppConfig = AppConfig;
}
