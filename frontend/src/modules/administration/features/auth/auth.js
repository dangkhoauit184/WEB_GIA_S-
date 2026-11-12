/**
 * EduCenter ERP - Auth Feature Logic
 */
document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const togglePasswordBtn = document.getElementById('togglePassword');
  const loginAlert = document.getElementById('loginAlert');
  const btnSubmit = document.getElementById('btnSubmit');

  // Toggle ẩn/hiện mật khẩu
  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      togglePasswordBtn.textContent = isPassword ? '🙈' : '👁️';
    });
  }

  function showAlert(message, type = 'error') {
    loginAlert.className = `alert-box ${type} show`;
    loginAlert.textContent = message;
  }

  function hideAlert() {
    loginAlert.className = 'alert-box';
    loginAlert.textContent = '';
  }

  // Submit login
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
      let authResult = null;
      try {
        const response = await HttpService.post('/auth/login', { username, password });
        authResult = response.data || response;
      } catch (networkOrApiError) {
        if (networkOrApiError.status === 401 || networkOrApiError.status === 403) {
          throw networkOrApiError;
        }

        console.warn('[Auth] Máy chưa bật PHP server. Khởi động Mock Session.');
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

      AccessService.setSession(authResult.token, authResult.user);
      showAlert(`Đăng nhập thành công! Đang chuyển hướng...`, 'success');

      const urlParams = new URLSearchParams(window.location.search);
      const redirectUrl = urlParams.get('redirect');

      setTimeout(() => {
        if (redirectUrl) {
          window.location.href = redirectUrl;
        } else {
          AccessService.redirectByRole();
        }
      }, 600);

    } catch (err) {
      console.error('[Auth Error]', err);
      showAlert(err.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Đăng nhập vào Hệ thống';
    }
  });

  // Modal Quên Mật Khẩu
  const modalForgot = document.getElementById('modalForgotPassword');
  const btnForgot = document.getElementById('btnForgotPwd');
  const closeForgot = document.getElementById('closeForgotModal');
  const cancelForgot = document.getElementById('btnCancelForgot');
  const forgotForm = document.getElementById('forgotPwdForm');

  if (btnForgot) btnForgot.addEventListener('click', () => modalForgot.classList.add('show'));
  const hideForgot = () => { modalForgot.classList.remove('show'); forgotForm.reset(); };
  if (closeForgot) closeForgot.addEventListener('click', hideForgot);
  if (cancelForgot) cancelForgot.addEventListener('click', hideForgot);

  if (forgotForm) {
    forgotForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = document.getElementById('forgotEmail').value;
      alert(`Hệ thống đã gửi liên kết khôi phục mật khẩu tới: ${email}. Vui lòng kiểm tra hòm thư!`);
      hideForgot();
    });
  }

  // Modal Đăng Ký Tài Khoản Phụ Huynh Mới
  const modalReg = document.getElementById('modalRegister');
  const btnOpenReg = document.getElementById('btnOpenRegister');
  const closeReg = document.getElementById('closeRegisterModal');
  const cancelReg = document.getElementById('btnCancelRegister');
  const regForm = document.getElementById('registerForm');

  if (btnOpenReg) btnOpenReg.addEventListener('click', () => modalReg.classList.add('show'));
  const hideReg = () => { modalReg.classList.remove('show'); regForm.reset(); };
  if (closeReg) closeReg.addEventListener('click', hideReg);
  if (cancelReg) cancelReg.addEventListener('click', hideReg);

  if (regForm) {
    regForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const fullname = document.getElementById('regFullname').value;
      const username = document.getElementById('regUsername').value;
      alert(`Đăng ký tài khoản thành công cho Phụ huynh [${fullname}]! Bạn có thể dùng tên đăng nhập '${username}' để đăng nhập ngay.`);
      hideReg();
      usernameInput.value = username;
      passwordInput.focus();
    });
  }
});
