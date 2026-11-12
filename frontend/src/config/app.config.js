/**
 * EduCenter ERP - Frontend Application Configuration
 */
const AppConfig = {
  // API Backend Base URL (Khi có Docker chạy ở cổng 8080)
  API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? 'http://localhost:8080/api'
    : '/api',

  // Khóa lưu trữ trong localStorage / sessionStorage
  STORAGE_KEYS: {
    TOKEN: 'educenter_token',
    USER: 'educenter_user',
    CURRENT_STUDENT_ID: 'educenter_selected_student_id',
  },

  // Cổng giao diện tương ứng theo vai trò (Role-based Routing)
  PORTAL_ROUTES: {
    ADMIN: '/src/modules/reporting/features/dashboard/dashboard.html',
    GIAO_VU: '/src/modules/academic/features/classes/classes.html',
    GIAO_VIEN: '/src/modules/academic/features/attendance/attendance.html',
    GIA_SU: '/src/modules/academic/features/attendance/attendance.html',
    TRO_GIANG: '/src/modules/academic/features/attendance/attendance.html',
    PHU_HUYNH: '/src/modules/people/features/parents/parents.html',
  },

  // Trang đăng nhập và trang công khai
  AUTH_PAGE: '/src/modules/administration/features/auth/auth.html',
  LANDING_PAGE: '/index.html',
};

if (typeof window !== 'undefined') {
  window.AppConfig = AppConfig;
}
