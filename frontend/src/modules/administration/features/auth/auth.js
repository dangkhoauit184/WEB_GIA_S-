/**
 * EduCenter ERP - Auth Feature Logic
 * Xử lý form đăng nhập, gọi API backend PHP (/api/auth/login), lưu phiên và điều hướng.
 */
document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const togglePasswordBtn = document.getElementById('togglePassword');
  const loginAlert = document.getElementById('loginAlert');
  const btnSubmit = document.getElementById('btnSubmit');

  // Nếu người dùng đã đăng nhập từ trước, tự động chuyển vào Portal tương ứng
  if (AccessService.isAuthenticated()) {
    const user = AccessService.getCurrentUser();
    console.log('[Auth] Người dùng đã đăng nhập:', user);
  }

  // Toggle ẩn/hiện mật khẩu
  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      togglePasswordBtn.textContent = isPassword ? '🙈' : '👁️';
    });
  }

  // Quick login buttons (để hỗ trợ dev/test nhanh các vai trò)
  document.querySelectorAll('.quick-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      usernameInput.value = btn.dataset.user || '';
      passwordInput.value = 'password123'; // Mật khẩu mẫu mặc định
      usernameInput.focus();
    });
  });

  // Hiển thị thông báo lỗi
  function showAlert(message, type = 'danger') {
    loginAlert.className = `alert alert-${type} show`;
    loginAlert.textContent = message;
  }

  function hideAlert() {
    loginAlert.className = 'alert';
    loginAlert.textContent = '';
  }

  // Xử lý submit đăng nhập
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const username = usernameInput.value.trim();
    const password = passwordInput.value;

    if (!username || !password) {
      showAlert('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Đang đăng nhập...';

    try {
      // 1. Thử gọi API Backend thực tế (nếu có server PHP chạy qua Docker hoặc Apache)
      let authResult = null;
      try {
        const response = await HttpService.post('/auth/login', { username, password });
        authResult = response.data || response;
      } catch (networkOrApiError) {
        // Nếu lỗi 401/403 do sai mật khẩu thì báo lỗi thật từ backend
        if (networkOrApiError.status === 401 || networkOrApiError.status === 403) {
          throw networkOrApiError;
        }

        // Nếu máy chưa bật Docker (không kết nối được backend PHP), kích hoạt Mock Session để sinh viên có thể code & test UI ngay
        console.warn('[Auth] Không kết nối được PHP server. Kích hoạt Mock Session để hỗ trợ phát triển Frontend.');
        
        let mockRole = 'PHU_HUYNH';
        let mockName = 'Nguyễn Văn Phụ Huynh';
        if (username.includes('admin')) { mockRole = 'ADMIN'; mockName = 'Quản trị viên Admin'; }
        else if (username.includes('giaovu')) { mockRole = 'GIAO_VU'; mockName = 'Thầy/Cô Giáo Vụ'; }
        else if (username.includes('giaovien')) { mockRole = 'GIAO_VIEN'; mockName = 'Thầy Nguyễn Văn A'; }
        else if (username.includes('giasu')) { mockRole = 'GIA_SU'; mockName = 'Gia sư Trần Thị B'; }
        else if (username.includes('trogiang')) { mockRole = 'TRO_GIANG'; mockName = 'Trợ giảng Lê C'; }

        authResult = {
          token: 'mock_jwt_token_for_local_development_' + Date.now(),
          user: {
            userId: 101,
            accountId: 101,
            username: username,
            hoTen: mockName,
            role: mockRole,
            groupName: mockRole === 'ADMIN' ? 'Admin' : (mockRole === 'PHU_HUYNH' ? 'PhuHuynh' : 'NhanVien'),
          }
        };
      }

      // Lưu JWT Token và User Info vào AccessService
      AccessService.setSession(authResult.token, authResult.user);

      showAlert(`Đăng nhập thành công! Đang chuyển hướng...`, 'success');

      // Kiểm tra tham số ?redirect= trên URL nếu có
      const urlParams = new URLSearchParams(window.location.search);
      const redirectUrl = urlParams.get('redirect');

      setTimeout(() => {
        if (redirectUrl) {
          window.location.href = redirectUrl;
        } else {
          AccessService.redirectByRole();
        }
      }, 700);

    } catch (err) {
      console.error('[Auth Error]', err);
      showAlert(err.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Đăng nhập vào Hệ thống';
    }
  });
});
