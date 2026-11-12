/**
 * EduCenter ERP - Shared HTTP / Ajax Service
 * Wrapper xung quanh fetch() tự động đính kèm Authorization Header (Bearer JWT)
 * và xử lý response chuẩn JSON từ Backend CodeIgniter 4.
 */
const HttpService = {
  /**
   * Thực hiện gửi HTTP request
   * @param {string} endpoint - Đường dẫn API (ví dụ: '/auth/login' hoặc '/people/parents/me')
   * @param {object} options - Fetch options (method, headers, body, v.v.)
   * @returns {Promise<any>}
   */
  async request(endpoint, options = {}) {
    // Chuẩn hóa URL
    const url = endpoint.startsWith('http')
      ? endpoint
      : `${AppConfig.API_BASE_URL.replace(/\/$/, '')}/${endpoint.replace(/^\//, '')}`;

    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers || {}),
    };

    // Đính kèm JWT token nếu đã đăng nhập
    const token = AccessService.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const config = {
      ...options,
      headers,
    };

    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(url, config);

      // Nếu lỗi xác thực 401: Token hết hạn hoặc không hợp lệ -> đăng xuất
      if (response.status === 401) {
        AccessService.clearSession();
        // Nếu không phải đang ở trang đăng nhập thì chuyển về đăng nhập
        if (window.location.pathname !== AppConfig.AUTH_PAGE && !window.location.pathname.includes('/auth/')) {
          window.location.href = AppConfig.AUTH_PAGE;
        }
      }

      const resData = await response.json().catch(() => null);

      if (!response.ok) {
        const errorMsg = (resData && (resData.message || resData.error)) || `HTTP ${response.status}: Yêu cầu thất bại.`;
        const err = new Error(errorMsg);
        err.status = response.status;
        err.data = resData;
        throw err;
      }

      return resData;
    } catch (error) {
      // Bắt lỗi kết nối khi Backend chưa khởi động (chưa có Docker/PHP)
      if (error.name === 'TypeError' && error.message.includes('fetch')) {
        console.warn('[HttpService] Không thể kết nối tới máy chủ backend:', url);
        throw new Error('Không thể kết nối đến máy chủ backend (Máy chủ chưa bật).');
      }
      throw error;
    }
  },

  get(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'GET' });
  },

  post(endpoint, body, options = {}) {
    return this.request(endpoint, { ...options, method: 'POST', body });
  },

  put(endpoint, body, options = {}) {
    return this.request(endpoint, { ...options, method: 'PUT', body });
  },

  delete(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  }
};

if (typeof window !== 'undefined') {
  window.HttpService = HttpService;
}
