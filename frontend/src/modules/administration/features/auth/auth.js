/**
 * EduCenter ERP - Auth Feature Logic
 */
document.addEventListener('DOMContentLoaded', () => {
  if (window.location.href.split('?')[0] !== AppConfig.AUTH_PAGE) {
    window.location.replace(AppConfig.AUTH_PAGE);
    return;
  }
  AccessService.clearSession();
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
      const response = await HttpService.post('/auth/login', { username, password });
      const authResult = response.data || response;

      AccessService.setSession(authResult.token, authResult.user);
      showAlert(`Đăng nhập thành công! Đang chuyển hướng...`, 'success');

      setTimeout(() => {
        AccessService.redirectByRole();
      }, 600);

    } catch (err) {
      console.error('[Auth Error]', err);
      showAlert(err.message || 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Đăng nhập vào Hệ thống';
    }
  });

  // Helper hiển thị thông báo trong Modal
  function setModalAlert(el, message, type = 'error') {
    if (!el) return;
    if (!message) {
      el.className = 'alert-box';
      el.textContent = '';
      return;
    }
    el.className = `alert-box ${type} show`;
    el.textContent = message;
  }

  // Modal Quên Mật Khẩu (OTP + Đặt lại mật khẩu + Xác nhận mật khẩu)
  const modalForgot = document.getElementById('modalForgotPassword');
  const btnForgot = document.getElementById('btnForgotPwd');
  const closeForgot = document.getElementById('closeForgotModal');
  const cancelForgot = document.getElementById('btnCancelForgot');
  const forgotForm = document.getElementById('forgotPwdForm');
  const forgotAlert = document.getElementById('forgotAlert');
  const btnSendOtp = document.getElementById('btnSendOtp');
  const btnSubmitForgot = document.getElementById('btnSubmitForgot');

  if (btnForgot) {
    btnForgot.addEventListener('click', () => {
      setModalAlert(forgotAlert, '');
      modalForgot.classList.add('show');
    });
  }
  const hideForgot = () => {
    modalForgot.classList.remove('show');
    if (forgotForm) forgotForm.reset();
    setModalAlert(forgotAlert, '');
  };
  if (closeForgot) closeForgot.addEventListener('click', hideForgot);
  if (cancelForgot) cancelForgot.addEventListener('click', hideForgot);

  if (btnSendOtp) {
    btnSendOtp.addEventListener('click', async () => {
      const identifier = document.getElementById('forgotEmail').value.trim();
      if (!identifier) {
        setModalAlert(forgotAlert, 'Vui lòng nhập Email hoặc Tên đăng nhập trước khi lấy mã OTP.');
        return;
      }
      btnSendOtp.disabled = true;
      btnSendOtp.textContent = 'Đang gửi...';
      setModalAlert(forgotAlert, '');
      try {
        const res = await HttpService.post('/auth/forgot-password/send-otp', { identifier });
        const data = res.data || res;
        setModalAlert(forgotAlert, data.message || 'Đã gửi mã OTP đến email đã đăng ký.', 'success');
      } catch (err) {
        setModalAlert(forgotAlert, err.message || 'Không thể gửi mã OTP. Vui lòng kiểm tra lại thông tin.');
      } finally {
        btnSendOtp.disabled = false;
        btnSendOtp.textContent = 'Lấy mã OTP';
      }
    });
  }

  if (forgotForm) {
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      setModalAlert(forgotAlert, '');

      const identifier = document.getElementById('forgotEmail').value.trim();
      const otp = document.getElementById('forgotOtp').value.trim();
      const newPassword = document.getElementById('forgotNewPwd').value;
      const confirmPassword = document.getElementById('forgotConfirmPwd').value;

      if (!identifier || !otp || !newPassword || !confirmPassword) {
        setModalAlert(forgotAlert, 'Vui lòng nhập đầy đủ Email/Tên đăng nhập, mã OTP và mật khẩu mới.');
        return;
      }
      if (newPassword.length < 6) {
        setModalAlert(forgotAlert, 'Mật khẩu mới phải có tối thiểu 6 ký tự.');
        return;
      }
      if (newPassword !== confirmPassword) {
        setModalAlert(forgotAlert, 'Mật khẩu xác nhận không khớp với mật khẩu mới!');
        return;
      }

      if (btnSubmitForgot) {
        btnSubmitForgot.disabled = true;
        btnSubmitForgot.textContent = 'Đang xử lý...';
      }

      try {
        const res = await HttpService.post('/auth/forgot-password/reset', {
          identifier,
          otp,
          newPassword,
          confirmPassword
        });
        const data = res.data || res;
        hideForgot();
        showAlert(data.message || 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.', 'success');
        usernameInput.value = data.username || identifier;
        passwordInput.value = '';
        passwordInput.focus();
      } catch (err) {
        setModalAlert(forgotAlert, err.message || 'Đặt lại mật khẩu không thành công. Vui lòng kiểm tra lại mã OTP.');
      } finally {
        if (btnSubmitForgot) {
          btnSubmitForgot.disabled = false;
          btnSubmitForgot.textContent = 'Đặt lại mật khẩu';
        }
      }
    });
  }

  // Modal Đăng Ký Tài Khoản Phụ Huynh Mới
  const modalReg = document.getElementById('modalRegister');
  const btnOpenReg = document.getElementById('btnOpenRegister');
  const closeReg = document.getElementById('closeRegisterModal');
  const cancelReg = document.getElementById('btnCancelRegister');
  const regForm = document.getElementById('registerForm');
  const registerAlert = document.getElementById('registerAlert');
  const btnSubmitRegister = document.getElementById('btnSubmitRegister');

  if (btnOpenReg) {
    btnOpenReg.addEventListener('click', () => {
      setModalAlert(registerAlert, '');
      modalReg.classList.add('show');
    });
  }
  const hideReg = () => {
    modalReg.classList.remove('show');
    if (regForm) regForm.reset();
    setModalAlert(registerAlert, '');
  };
  if (closeReg) closeReg.addEventListener('click', hideReg);
  if (cancelReg) cancelReg.addEventListener('click', hideReg);

  if (regForm) {
    regForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      setModalAlert(registerAlert, '');

      const hoTen = document.getElementById('regFullname').value.trim();
      const sdt = document.getElementById('regPhone').value.trim();
      const username = document.getElementById('regUsername').value.trim();
      const password = document.getElementById('regPassword').value;
      const confirmPassword = document.getElementById('regConfirmPassword').value;

      if (!hoTen || !sdt || !username || !password || !confirmPassword) {
        setModalAlert(registerAlert, 'Vui lòng điền đầy đủ các trường thông tin bắt buộc.');
        return;
      }
      if (password.length < 6) {
        setModalAlert(registerAlert, 'Mật khẩu phải có tối thiểu 6 ký tự.');
        return;
      }
      if (password !== confirmPassword) {
        setModalAlert(registerAlert, 'Mật khẩu xác nhận không khớp!');
        return;
      }

      if (btnSubmitRegister) {
        btnSubmitRegister.disabled = true;
        btnSubmitRegister.textContent = 'Đang tạo tài khoản...';
      }

      try {
        await HttpService.post('/auth/register', {
          hoTen,
          sdt,
          username,
          password,
          confirmPassword
        });
        hideReg();
        showAlert(`Đăng ký tài khoản Phụ huynh [${hoTen}] thành công! Mời bạn đăng nhập.`, 'success');
        usernameInput.value = username;
        passwordInput.value = '';
        passwordInput.focus();
      } catch (err) {
        setModalAlert(registerAlert, err.message || 'Không thể đăng ký tài khoản. Vui lòng kiểm tra lại SĐT hoặc Tên đăng nhập.');
      } finally {
        if (btnSubmitRegister) {
          btnSubmitRegister.disabled = false;
          btnSubmitRegister.textContent = 'Xác nhận tạo tài khoản';
        }
      }
    });
  }
});
