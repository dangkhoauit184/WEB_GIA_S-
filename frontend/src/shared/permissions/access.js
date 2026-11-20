/**
 * EduCenter ERP - Access & Session Management
 * Quản lý thông tin hiển thị người dùng ở client và điều hướng vai trò.
 * Access token (JWT) chỉ nằm trong HttpOnly cookie `educenter_session`, KHÔNG lưu vào localStorage.
 */
const AccessService = {
  _user: null,

  /**
   * Lưu thông tin hiển thị người dùng hiện tại (không lưu access token vào localStorage)
   * @param {string|null} _token - Không lưu ở trình duyệt (đã nằm trong HttpOnly cookie)
   * @param {object} user - Thông tin tài khoản người dùng (không nhạy cảm)
   */
  setSession(_token, user) {
    // Dọn token cũ nếu phiên bản trước từng ghi vào localStorage
    localStorage.removeItem(AppConfig.STORAGE_KEYS.TOKEN);
    localStorage.removeItem(AppConfig.STORAGE_KEYS.USER);
    if (user) {
      this._user = user;
      sessionStorage.setItem(AppConfig.STORAGE_KEYS.USER, JSON.stringify(user));
      sessionStorage.removeItem('educenter_disable_onetap');
    }
  },

  /**
   * Access token chỉ nằm trong HttpOnly cookie, không đọc từ JS trình duyệt.
   * @returns {null}
   */
  getToken() {
    return null;
  },

  /**
   * Lấy thông tin user trong phiên hiện tại
   * @returns {object|null}
   */
  getCurrentUser() {
    if (this._user) return this._user;
    const raw = sessionStorage.getItem(AppConfig.STORAGE_KEYS.USER);
    if (!raw) return null;
    try {
      this._user = JSON.parse(raw);
      return this._user;
    } catch (e) {
      return null;
    }
  },

  /**
   * Kiểm tra người dùng đã có thông tin phiên hợp lệ hay chưa
   * @returns {boolean}
   */
  isAuthenticated() {
    return Boolean(this.getCurrentUser());
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
   * Xóa trạng thái phiên ở client
   */
  clearSession() {
    this._user = null;
    localStorage.removeItem(AppConfig.STORAGE_KEYS.TOKEN);
    localStorage.removeItem(AppConfig.STORAGE_KEYS.USER);
    localStorage.removeItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID);
    sessionStorage.removeItem(AppConfig.STORAGE_KEYS.USER);
  },

  /**
   * Xóa phiên và ngăn Google One Tap tự đăng nhập lại ngay sau khi người dùng chủ động đăng xuất
   */
  markLoggedOut() {
    this.clearSession();
    sessionStorage.setItem('educenter_disable_onetap', '1');
    try {
      window.google?.accounts?.id?.disableAutoSelect?.();
    } catch {}
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
  localStorage.removeItem(AppConfig.STORAGE_KEYS.TOKEN);
  const sessionElement = document.getElementById('portal-session');
  if (sessionElement) {
    const session = JSON.parse(sessionElement.textContent);
    AccessService.setSession(null, session.user);
    sessionElement.remove();
  }
}
