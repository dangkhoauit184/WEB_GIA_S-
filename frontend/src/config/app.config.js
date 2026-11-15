/**
 * EduCenter ERP - Frontend Application Configuration
 */
const _backendOrigin = ['localhost', '127.0.0.1'].includes(window.location.hostname) && window.location.port === '8769'
  ? 'http://localhost:5000'
  : window.location.origin;

const AppConfig = {
  // The shared login and role portals use the PHP origin.
  API_BASE_URL: _backendOrigin + '/api',

  // Khóa lưu trữ trong localStorage / sessionStorage
  STORAGE_KEYS: {
    TOKEN: 'educenter_token',
    USER: 'educenter_user',
    CURRENT_STUDENT_ID: 'educenter_selected_student_id',
  },

  // Cổng giao diện tương ứng theo vai trò (Role-based Routing)
  PORTAL_ROUTES: {
    ADMIN: _backendOrigin + '/app',
    GIAO_VU: _backendOrigin + '/app',
    GIAO_VIEN: _backendOrigin + '/app',
    GIA_SU: _backendOrigin + '/app',
    TRO_GIANG: _backendOrigin + '/app',
    PHU_HUYNH: _backendOrigin + '/app',
  },

  // Trang đăng nhập và trang công khai
  AUTH_PAGE: _backendOrigin + '/login',
  LANDING_PAGE: _backendOrigin + '/',
};

if (typeof window !== 'undefined') {
  window.AppConfig = AppConfig;
}
