/**
 * EduCenter ERP - Frontend Application Configuration
 */
const AppConfig = {
  // Frontend and CodeIgniter are served from one origin.
  API_BASE_URL: '/api',

  STORAGE_KEYS: {
    TOKEN: 'educenter_token',
    USER: 'educenter_user',
    CURRENT_STUDENT_ID: 'educenter_selected_student_id',
  },

  PORTAL_ROUTES: {
    ADMIN: '/admin',
    GIAO_VU: '/staff',
    GIAO_VIEN: '/portal-pending?role=GIAO_VIEN',
    GIAO_SU: '/portal-pending?role=GIAO_SU',
    TRO_GIANG: '/portal-pending?role=TRO_GIANG',
    PHU_HUYNH: '/parent',
  },

  AUTH_PAGE: '/login',
  LANDING_PAGE: '/',
};

if (typeof window !== 'undefined') {
  window.AppConfig = AppConfig;
}
