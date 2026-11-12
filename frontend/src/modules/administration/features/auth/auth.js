'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const byId = id => document.getElementById(id);
  const loginForm = byId('loginForm');
  const usernameInput = byId('username');
  const passwordInput = byId('password');
  const loginAlert = byId('loginAlert');
  const btnSubmit = byId('btnSubmit');
  const togglePasswordBtn = byId('togglePassword');

  const showAlert = (message, type = 'error') => {
    loginAlert.className = `alert-box ${type} show`;
    loginAlert.textContent = message;
  };
  const hideAlert = () => {
    loginAlert.className = 'alert-box';
    loginAlert.textContent = '';
  };

  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', () => {
      const showPassword = passwordInput.type === 'password';
      passwordInput.type = showPassword ? 'text' : 'password';
      togglePasswordBtn.textContent = showPassword ? '🙈' : '👁️';
    });
  }

  loginForm.addEventListener('submit', async event => {
    event.preventDefault();
    hideAlert();

    const username = usernameInput.value.trim();
    const password = passwordInput.value;
    if (!username || !password) {
      showAlert('Vui lòng nhập tên đăng nhập và mật khẩu.');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Đang đăng nhập...';
    try {
      const response = await HttpService.post('/auth/login', { username, password });
      const authResult = response?.data || response;
      if (!authResult?.token || !authResult?.user) {
        throw new Error('Máy chủ trả về dữ liệu đăng nhập không hợp lệ.');
      }

      AccessService.setSession(authResult.token, authResult.user, byId('rememberMe')?.checked === true);
      showAlert('Đăng nhập thành công. Đang chuyển tới cổng của bạn…', 'success');

      const requested = new URLSearchParams(window.location.search).get('redirect');
      let safeTarget = null;
      if (requested) {
        try {
          const target = new URL(requested, window.location.origin);
          if (target.origin === window.location.origin && target.pathname !== AppConfig.AUTH_PAGE) {
            safeTarget = `${target.pathname}${target.search}${target.hash}`;
          }
        } catch (_) {}
      }

      window.setTimeout(() => {
        if (safeTarget) window.location.assign(safeTarget);
        else AccessService.redirectByRole();
      }, 350);
    } catch (error) {
      showAlert(error.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Đăng nhập vào Hệ thống';
    }
  });
});
