/**
 * EduCenter ERP - Access & Session Management
 * Phụ trách lưu trữ JWT token, kiểm tra phiên làm việc và phân quyền vai trò.
 */
const AccessService = {
  /**
   * Lưu phiên đăng nhập
   * @param {string} token - Chuỗi JWT token từ backend
   * @param {object} user - Thông tin tài khoản người dùng
   */
  setSession(token, user) {
    if (token) {
      localStorage.setItem(AppConfig.STORAGE_KEYS.TOKEN, token);
    }
    if (user) {
      localStorage.setItem(AppConfig.STORAGE_KEYS.USER, JSON.stringify(user));
    }
  },

  /**
   * Lấy JWT token hiện tại
   * @returns {string|null}
   */
  getToken() {
    return localStorage.getItem(AppConfig.STORAGE_KEYS.TOKEN);
  },

  /**
   * Lấy thông tin user trong phiên hiện tại
   * @returns {object|null}
   */
  getCurrentUser() {
    const raw = localStorage.getItem(AppConfig.STORAGE_KEYS.USER);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  },

  /**
   * Kiểm tra người dùng đã đăng nhập chưa
   * @returns {boolean}
   */
  isAuthenticated() {
    return Boolean(this.getToken() && this.getCurrentUser());
  },

  /**
   * Lấy vai trò (role) của người dùng hiện tại
   * @returns {string|null} ADMIN, GIAO_VU, GIAO_VIEN, GIA_SU, TRO_GIANG, PHU_HUYNH
   */
  getRole() {
    const user = this.getCurrentUser();
    return user ? user.role : null;
  },

  /**
   * Xóa phiên làm việc khi đăng xuất
   */
  clearSession() {
    localStorage.removeItem(AppConfig.STORAGE_KEYS.TOKEN);
    localStorage.removeItem(AppConfig.STORAGE_KEYS.USER);
    localStorage.removeItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID);
  },

  /**
   * Điều hướng người dùng tới trang đích phù hợp với vai trò
   */
  redirectByRole() {
    const role = this.getRole();
    if (!role) {
      window.location.href = AppConfig.AUTH_PAGE;
      return;
    }

    const targetUrl = AppConfig.PORTAL_ROUTES[role] || AppConfig.LANDING_PAGE;
    window.location.href = targetUrl;
  },

  /**
   * Yêu cầu xác thực bắt buộc tại các trang nội bộ (Route Guard)
   * @param {Array<string>} [allowedRoles] - Danh sách role được phép truy cập trang
   */
  requireAuth(allowedRoles = []) {
    if (!this.isAuthenticated()) {
      window.location.href = `${AppConfig.AUTH_PAGE}?redirect=${encodeURIComponent(window.location.pathname)}`;
      return false;
    }

    if (allowedRoles.length > 0) {
      const currentRole = this.getRole();
      if (!allowedRoles.includes(currentRole)) {
        alert('Bạn không có quyền truy cập vào chức năng này!');
        this.redirectByRole();
        return false;
      }
    }
    return true;
  }
};

if (typeof window !== 'undefined') {
  window.AccessService = AccessService;
}
