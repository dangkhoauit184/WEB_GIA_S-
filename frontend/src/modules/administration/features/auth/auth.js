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
  const rememberMeInput = document.getElementById('rememberMe');
  const togglePasswordBtn = document.getElementById('togglePassword');
  const loginAlert = document.getElementById('loginAlert');
  const btnSubmit = document.getElementById('btnSubmit');
  const googleBtnContainer = document.getElementById('googleBtnContainer');
  const btnGoogleFallback = document.getElementById('btnGoogleFallback');
  const googleStatusNote = document.getElementById('googleStatusNote');
  let googleAuthBusy = false;

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

  const params = new URLSearchParams(window.location.search);
  const justLoggedOut = params.get('loggedOut') === '1' || sessionStorage.getItem('educenter_disable_onetap') === '1';
  const passwordChanged = params.get('passwordChanged') === '1';
  if (passwordChanged) {
    showAlert('Đổi mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.', 'success');
    history.replaceState(null, '', AppConfig.AUTH_PAGE);
  } else if (params.get('loggedOut') === '1') {
    history.replaceState(null, '', AppConfig.AUTH_PAGE);
  }

  function setGoogleNote(message) {
    if (!googleStatusNote) return;
    googleStatusNote.textContent = message || '';
    googleStatusNote.hidden = !message;
  }

  async function handleGoogleCredential(response) {
    if (googleAuthBusy) return;
    const credential = response && typeof response.credential === 'string' ? response.credential : '';
    if (!credential) {
      showAlert('Không nhận được mã xác thực từ Google. Vui lòng thử lại hoặc đăng nhập bằng mật khẩu.');
      return;
    }
    googleAuthBusy = true;
    hideAlert();
    btnSubmit.disabled = true;
    try {
      const apiRes = await HttpService.post('/auth/google', {
        credential,
        rememberMe: Boolean(rememberMeInput && rememberMeInput.checked),
      });
      const authResult = apiRes.data || apiRes;
      AccessService.setSession(null, authResult.user);
      showAlert('Đăng nhập thành công.', 'success');
      AccessService.redirectByRole();
    } catch (err) {
      console.error('[Google Auth Error]', err);
      const code = err?.data?.code;
      if (code === 'GOOGLE_ACCOUNT_NOT_LINKED' || code === 'ACCOUNT_INACTIVE' || code === 'GOOGLE_EMAIL_UNVERIFIED') {
        try { window.google?.accounts?.id?.disableAutoSelect?.(); } catch {}
      }
      showAlert(err.message || 'Đăng nhập bằng Google không thành công.');
    } finally {
      googleAuthBusy = false;
      btnSubmit.disabled = false;
    }
  }

  function loadGoogleIdentityScript() {
    if (window.google?.accounts?.id) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      const timer = setTimeout(() => reject(new Error('GOOGLE_SCRIPT_TIMEOUT')), 8000);
      script.onload = () => {
        clearTimeout(timer);
        if (window.google?.accounts?.id) resolve();
        else reject(new Error('GOOGLE_SCRIPT_INVALID'));
      };
      script.onerror = () => {
        clearTimeout(timer);
        reject(new Error('GOOGLE_SCRIPT_FAILED'));
      };
      document.head.appendChild(script);
    });
  }

  async function initGoogleSignIn() {
    if (!googleBtnContainer) return;
    try {
      const res = await HttpService.get('/auth/google/config');
      const cfg = res?.data || res || {};
      if (!cfg.enabled || !cfg.clientId) {
        if (btnGoogleFallback) {
          btnGoogleFallback.disabled = true;
          btnGoogleFallback.title = 'Máy chủ chưa cấu hình GOOGLE_CLIENT_ID';
        }
        setGoogleNote('Đăng nhập Google chưa được cấu hình trên máy chủ.');
        return;
      }

      await loadGoogleIdentityScript();

      const disableAuto = justLoggedOut || passwordChanged;
      window.google.accounts.id.initialize({
        client_id: cfg.clientId,
        callback: handleGoogleCredential,
        auto_select: !disableAuto,
        cancel_on_tap_outside: true,
        use_fedcm_for_prompt: true,
      });

      if (disableAuto) {
        try { window.google.accounts.id.disableAutoSelect(); } catch {}
      }

      googleBtnContainer.innerHTML = '';
      const width = Math.min(400, Math.max(240, Math.floor(googleBtnContainer.clientWidth || 380)));
      window.google.accounts.id.renderButton(googleBtnContainer, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        text: 'signin_with',
        shape: 'rectangular',
        logo_alignment: 'left',
        locale: 'vi',
        width,
      });
      setGoogleNote('');

      if (!disableAuto) {
        window.google.accounts.id.prompt();
      }
    } catch (err) {
      console.warn('[Google Sign-In Init]', err);
      if (btnGoogleFallback) {
        btnGoogleFallback.disabled = false;
        btnGoogleFallback.onclick = () => {
          showAlert('Không tải được dịch vụ Google. Vui lòng kiểm tra kết nối mạng hoặc đăng nhập bằng mật khẩu.');
        };
      }
      setGoogleNote('Không tải được dịch vụ Google.');
    }
  }

  initGoogleSignIn();

  // Submit login
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const username = usernameInput.value.trim();
    const password = passwordInput.value;
    const rememberMe = Boolean(rememberMeInput && rememberMeInput.checked);

    if (!username || !password) {
      showAlert('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.');
      return;
    }

    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Đang đăng nhập...';

    try {
      const response = await HttpService.post('/auth/login', { username, password, rememberMe });
      const authResult = response.data || response;

      AccessService.setSession(null, authResult.user);
      showAlert('Đăng nhập thành công.', 'success');
      AccessService.redirectByRole();

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
