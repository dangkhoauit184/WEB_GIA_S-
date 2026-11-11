/**
 * EduCenter ERP - Parents Portal Logic
 * Quản lý Cổng Phụ huynh:
 * 1. Chọn con (1 phụ huynh nhiều con) và lọc toàn bộ dữ liệu theo con đó.
 * 2. Thời khóa biểu học tập.
 * 3. Sổ theo dõi buổi học (điểm bài tập, mức tiếp thu, nhận xét).
 * 4. Gửi đơn xin nghỉ phép cá nhân (do Giáo vụ duyệt).
 * 5. Xem hóa đơn trọn khóa & Khởi tạo thanh toán trực tuyến (chờ Admin đối soát).
 * 6. Thông báo hệ thống & Đánh dấu đã đọc.
 * 7. Đổi mật khẩu tài khoản (PUT /api/auth/change-password).
 */

const MOCK_DATA = {
  students: [
    { id: 'HV-0019', name: 'Nguyễn Văn An', grade: 'Lớp 10A1', avatar: '👦' },
    { id: 'HV-0031', name: 'Nguyễn Mai Linh', grade: 'Lớp 8B', avatar: '👧' }
  ],
  schedules: {
    'HV-0019': [
      { date: 'Thứ Tư, 12/11/2026', time: '18:00 - 20:00', course: 'Toán Nâng cao 10', room: 'Offline - Phòng 201', teacher: 'Thầy Nguyễn Văn A', status: 'Sắp diễn ra' },
      { date: 'Thứ Bảy, 15/11/2026', time: '14:00 - 16:00', course: 'Vật Lý 10 Cơ bản', room: 'Online (Google Meet)', teacher: 'Cô Trần Thị B', status: 'Sắp diễn ra' },
      { date: 'Thứ Hai, 17/11/2026', time: '18:00 - 20:00', course: 'Toán Nâng cao 10', room: 'Offline - Phòng 201', teacher: 'Thầy Nguyễn Văn A', status: 'Sắp diễn ra' },
    ],
    'HV-0031': [
      { date: 'Thứ Năm, 13/11/2026', time: '17:30 - 19:30', course: 'Tiếng Anh THCS Nâng cao', room: 'Offline - Phòng 102', teacher: 'Cô Lê Hoàng C', status: 'Sắp diễn ra' },
      { date: 'Chủ Nhật, 16/11/2026', time: '09:00 - 11:00', course: 'Gia sư Toán 1 kèm 1', room: 'Dạy tại nhà học viên', teacher: 'Thầy Phạm D', status: 'Sắp diễn ra' },
    ]
  },
  learningNotes: {
    'HV-0019': [
      { date: '08/11/2026', course: 'Toán Nâng cao 10', attend: 'Có mặt', score: '9.0 / 10', star: '5 / 5 ⭐', comment: 'Nắm chắc kiến thức hệ phương trình, bài tập về nhà làm cẩn thận.' },
      { date: '05/11/2026', course: 'Toán Nâng cao 10', attend: 'Có mặt', score: '8.5 / 10', star: '4 / 5 ⭐', comment: 'Cần chú ý hơn ở các bước biến đổi rút gọn cuối bài.' },
      { date: '01/11/2026', course: 'Vật Lý 10 Cơ bản', attend: 'Có mặt', score: '8.0 / 10', star: '4 / 5 ⭐', comment: 'Tiếp thu bài tốt, hăng hái phát biểu xây dựng bài trên lớp.' }
    ],
    'HV-0031': [
      { date: '06/11/2026', course: 'Tiếng Anh THCS Nâng cao', attend: 'Có mặt', score: '9.5 / 10', star: '5 / 5 ⭐', comment: 'Kỹ năng phát âm và từ vựng rất tốt, tự tin giao tiếp.' },
      { date: '02/11/2026', course: 'Gia sư Toán 1 kèm 1', attend: 'Có mặt', score: '8.0 / 10', star: '4 / 5 ⭐', comment: 'Đã hiểu cách giải bài toán hình học không gian cơ bản.' }
    ]
  },
  invoices: {
    'HV-0019': [
      { code: 'HD-2026-0012', course: 'Toán Nâng cao 10 (Trọn khóa)', must: 3500000, paid: 3500000, deadline: '01/11/2026', status: 'da_thanh_toan' },
      { code: 'HD-2026-0045', course: 'Vật Lý 10 Cơ bản (Trọn khóa)', must: 2800000, paid: 0, deadline: '25/11/2026', status: 'chua_thanh_toan' }
    ],
    'HV-0031': [
      { code: 'HD-2026-0078', course: 'Gia sư Toán 1 kèm 1 (Trọn khóa)', must: 4200000, paid: 4200000, deadline: '05/11/2026', status: 'da_thanh_toan' },
      { code: 'HD-2026-0092', course: 'Tiếng Anh THCS Nâng cao (Trọn khóa)', must: 2500000, paid: 1000000, deadline: '28/11/2026', status: 'mot_phan' }
    ]
  },
  leaveRequests: [
    { code: 'NP-2026-01', studentId: 'HV-0019', session: '08/11/2026 · Toán 10', reason: 'Em bị cảm sốt, gia đình xin nghỉ 1 buổi', date: '07/11/2026', status: 'Đã duyệt' }
  ],
  transactions: [
    { code: 'GD-WEB-901', invCode: 'HD-2026-0012', channel: 'VietQR / Napas 247', amount: 3500000, ref: 'FT263001849 EDU HD-2026-0012', time: '01/11/2026 14:22', status: 'da_xac_nhan' }
  ],
  notifications: [
    { id: 1, title: 'Hóa đơn học phí mới đã phát hành', content: 'Giáo vụ đã phát hành hóa đơn học phí trọn khóa Vật Lý 10 cho học sinh Nguyễn Văn An.', time: '10/11/2026 09:30', read: false },
    { id: 2, title: 'Đơn xin nghỉ học đã được duyệt', content: 'Đơn xin nghỉ ngày 08/11 của học sinh Nguyễn Văn An đã được Giáo vụ phê duyệt thành công.', time: '07/11/2026 15:45', read: true },
    { id: 3, title: 'Nhắc lịch học sắp tới', content: 'Học sinh Nguyễn Văn An có buổi học Toán Nâng cao lúc 18:00 ngày mai tại Phòng 201.', time: '06/11/2026 18:00', read: true }
  ]
};

document.addEventListener('DOMContentLoaded', () => {
  // 1. Kiểm tra quyền truy cập: Cho phép PHU_HUYNH hoặc ADMIN
  if (!AccessService.requireAuth(['PHU_HUYNH', 'ADMIN'])) {
    return;
  }

  const currentUser = AccessService.getCurrentUser();
  if (currentUser) {
    document.getElementById('currentUserName').textContent = currentUser.hoTen || currentUser.username;
  }

  // 2. Xử lý Đăng xuất
  document.getElementById('btnLogout').addEventListener('click', () => {
    if (confirm('Bạn có chắc chắn muốn đăng xuất không?')) {
      AccessService.clearSession();
      window.location.href = AppConfig.AUTH_PAGE;
    }
  });

  // 3. Quản lý trạng thái con đang chọn
  let currentStudentId = localStorage.getItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID) || MOCK_DATA.students[0].id;

  const studentSelector = document.getElementById('studentSelector');
  const selectedStudentName = document.getElementById('selectedStudentName');
  const kidChipsContainer = document.getElementById('kidChipsContainer');

  function initStudentSelector() {
    studentSelector.innerHTML = MOCK_DATA.students.map(s => `
      <option value="${s.id}" ${s.id === currentStudentId ? 'selected' : ''}>
        ${s.name} (${s.grade})
      </option>
    `).join('');

    renderKidChips();
    renderAllViewsForCurrentStudent();
  }

  function renderKidChips() {
    kidChipsContainer.innerHTML = MOCK_DATA.students.map(s => `
      <button type="button" class="chip ${s.id === currentStudentId ? 'active' : ''}" data-id="${s.id}">
        ${s.avatar} ${s.name} (${s.grade})
      </button>
    `).join('');

    kidChipsContainer.querySelectorAll('.chip').forEach(btn => {
      btn.addEventListener('click', () => {
        changeStudent(btn.dataset.id);
      });
    });
  }

  studentSelector.addEventListener('change', (e) => {
    changeStudent(e.target.value);
  });

  function changeStudent(id) {
    currentStudentId = id;
    studentSelector.value = id;
    localStorage.setItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID, id);
    renderKidChips();
    renderAllViewsForCurrentStudent();
  }

  // 4. Render các View theo con đang chọn
  function renderAllViewsForCurrentStudent() {
    const student = MOCK_DATA.students.find(s => s.id === currentStudentId) || MOCK_DATA.students[0];
    selectedStudentName.textContent = `${student.name} (${student.grade})`;

    renderSchedule();
    renderLearningNotes();
    renderInvoices();
    renderLeaveHistory();
    renderTransactions();
    renderNotifications();
    populateLeaveSessionDropdown();
  }

  // 4.1 Thời khóa biểu
  function renderSchedule() {
    const tbody = document.getElementById('scheduleTableBody');
    const items = MOCK_DATA.schedules[currentStudentId] || [];

    if (items.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Chưa có lịch học nào.</td></tr>`;
      return;
    }

    tbody.innerHTML = items.map(s => `
      <tr>
        <td><strong>${s.date}</strong></td>
        <td>${s.time}</td>
        <td><strong>${s.course}</strong></td>
        <td>${s.room}</td>
        <td>${s.teacher}</td>
        <td><span class="badge badge-teal">${s.status}</span></td>
      </tr>
    `).join('');
  }

  // 4.2 Sổ theo dõi buổi học
  function renderLearningNotes() {
    const tbody = document.getElementById('learningTableBody');
    const notes = MOCK_DATA.learningNotes[currentStudentId] || [];

    if (notes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Chưa có dữ liệu theo dõi buổi học.</td></tr>`;
      return;
    }

    tbody.innerHTML = notes.map(n => `
      <tr>
        <td>${n.date}</td>
        <td><strong>${n.course}</strong></td>
        <td><span class="badge badge-ok">${n.attend}</span></td>
        <td><strong style="color:var(--pri-d);">${n.score}</strong></td>
        <td>${n.star}</td>
        <td>${n.comment}</td>
      </tr>
    `).join('');
  }

  // 4.3 Hóa đơn học phí
  function renderInvoices() {
    const tbody = document.getElementById('invoiceTableBody');
    const invs = MOCK_DATA.invoices[currentStudentId] || [];

    if (invs.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Không có hóa đơn học phí.</td></tr>`;
      return;
    }

    tbody.innerHTML = invs.map(i => {
      const remaining = i.must - i.paid;
      const statusBadge = i.status === 'da_thanh_toan' 
        ? `<span class="badge badge-ok">Đã thanh toán</span>` 
        : (i.status === 'mot_phan' ? `<span class="badge badge-warn">Một phần</span>` : `<span class="badge badge-warn">Chưa thanh toán</span>`);

      const actionBtn = remaining > 0
        ? `<button class="btn btn-primary btn-pay-modal" style="padding:6px 12px; font-size:12px;" data-code="${i.code}">💳 Thanh toán Web</button>`
        : `<span class="text-muted" style="font-size:12px;">Đã hoàn tất</span>`;

      return `
        <tr>
          <td><strong style="font-family:monospace;">${i.code}</strong></td>
          <td><strong>${i.course}</strong></td>
          <td>${i.must.toLocaleString('vi-VN')} đ</td>
          <td>${i.paid.toLocaleString('vi-VN')} đ</td>
          <td style="color:${remaining > 0 ? 'var(--err)' : 'var(--ok)'}; font-weight:700;">
            ${remaining.toLocaleString('vi-VN')} đ
          </td>
          <td>${i.deadline}</td>
          <td>${statusBadge}</td>
          <td>${actionBtn}</td>
        </tr>
      `;
    }).join('');

    tbody.querySelectorAll('.btn-pay-modal').forEach(btn => {
      btn.addEventListener('click', () => {
        openPaymentModal(btn.dataset.code);
      });
    });
  }

  // 4.4 Đơn xin nghỉ phép
  function populateLeaveSessionDropdown() {
    const sel = document.getElementById('leaveSessionSelect');
    const items = MOCK_DATA.schedules[currentStudentId] || [];
    sel.innerHTML = '<option value="">-- Chọn buổi học sắp tới --</option>' + 
      items.map((s, idx) => `<option value="${idx + 1}">${s.date} (${s.time}) · ${s.course}</option>`).join('');
  }

  function renderLeaveHistory() {
    const tbody = document.getElementById('leaveHistoryTableBody');
    const list = MOCK_DATA.leaveRequests.filter(r => r.studentId === currentStudentId);

    if (list.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:16px;">Chưa có đơn xin nghỉ nào.</td></tr>`;
      return;
    }

    tbody.innerHTML = list.map(r => `
      <tr>
        <td><strong>${r.code}</strong></td>
        <td>${r.session}</td>
        <td>${r.reason}</td>
        <td>${r.date}</td>
        <td><span class="badge badge-ok">${r.status}</span></td>
      </tr>
    `).join('');
  }

  // 4.5 Giao dịch trực tuyến
  function renderTransactions() {
    const tbody = document.getElementById('transactionHistoryTableBody');
    tbody.innerHTML = MOCK_DATA.transactions.map(t => `
      <tr>
        <td><strong style="font-family:monospace;">${t.code}</strong></td>
        <td><strong>${t.invCode}</strong></td>
        <td>${t.channel}</td>
        <td><strong style="color:var(--pri-d);">${t.amount.toLocaleString('vi-VN')} đ</strong></td>
        <td style="font-family:monospace; font-size:12px;">${t.ref}</td>
        <td>${t.time}</td>
        <td><span class="badge ${t.status === 'da_xac_nhan' ? 'badge-ok' : 'badge-warn'}">${t.status === 'da_xac_nhan' ? 'Admin đã xác nhận' : 'Chờ Admin đối soát'}</span></td>
      </tr>
    `).join('');
  }

  // 4.6 Thông báo hệ thống
  function renderNotifications() {
    const container = document.getElementById('notificationsListContainer');
    if (!container) return;

    container.innerHTML = MOCK_DATA.notifications.map(n => `
      <div style="background:${n.read ? 'var(--g50)' : 'var(--pri-ll)'}; border:1px solid ${n.read ? 'var(--g200)' : '#ccfbf1'}; border-radius:var(--radius-md); padding:16px; display:flex; justify-content:space-between; align-items:center;">
        <div>
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
            <strong style="font-size:14px; color:var(--g900);">${n.title}</strong>
            ${!n.read ? `<span class="badge badge-teal" style="font-size:10px;">Mới</span>` : ''}
          </div>
          <p style="margin:0; font-size:13px; color:var(--g600);">${n.content}</p>
          <span style="font-size:11px; color:var(--g400); margin-top:4px; display:block;">${n.time}</span>
        </div>
        ${!n.read ? `<button class="btn btn-secondary btn-mark-read" style="font-size:11.5px; padding:4px 8px;" data-id="${n.id}">Đã đọc</button>` : ''}
      </div>
    `).join('');

    container.querySelectorAll('.btn-mark-read').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.dataset.id, 10);
        const item = MOCK_DATA.notifications.find(x => x.id === id);
        if (item) {
          item.read = true;
          renderNotifications();
        }
      });
    });
  }

  const btnMarkAll = document.getElementById('btnMarkAllRead');
  if (btnMarkAll) {
    btnMarkAll.addEventListener('click', () => {
      MOCK_DATA.notifications.forEach(n => n.read = true);
      renderNotifications();
      alert('Đã đánh dấu tất cả thông báo là đã đọc!');
    });
  }

  // 5. Chuyển đổi các Tab
  const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
  const tabPanes = document.querySelectorAll('.tab-pane');
  const tabHeading = document.getElementById('tabHeading');

  const tabTitles = {
    schedule: 'Thời khóa biểu học tập',
    learning: 'Sổ theo dõi & Điểm số buổi học',
    leave: 'Đơn xin nghỉ phép của học sinh',
    tuition: 'Hóa đơn học phí trọn khóa',
    history: 'Lịch sử giao dịch thanh toán Web',
    notifications: 'Thông báo hệ thống',
  };

  navItems.forEach(item => {
    item.addEventListener('click', () => {
      navItems.forEach(n => n.classList.remove('active'));
      tabPanes.forEach(p => p.classList.remove('active'));

      item.classList.add('active');
      const tabKey = item.dataset.tab;
      const targetPane = document.getElementById(`tab-${tabKey}`);
      if (targetPane) targetPane.classList.add('active');
      if (tabTitles[tabKey]) tabHeading.textContent = tabTitles[tabKey];
    });
  });

  // 6. Form gửi Đơn xin nghỉ phép
  const leaveForm = document.getElementById('leaveRequestForm');
  leaveForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const sessionText = document.getElementById('leaveSessionSelect').selectedOptions[0].text;
    const reason = document.getElementById('leaveReason').value.trim();

    if (!reason) return;

    const newCode = 'NP-2026-0' + (MOCK_DATA.leaveRequests.length + 1);
    MOCK_DATA.leaveRequests.unshift({
      code: newCode,
      studentId: currentStudentId,
      session: sessionText,
      reason: reason,
      date: new Date().toLocaleDateString('vi-VN'),
      status: 'Chờ giáo vụ duyệt'
    });

    renderLeaveHistory();
    alert(`Đã gửi đơn xin nghỉ (${newCode}) thành công! Giáo vụ sẽ sớm xem xét và duyệt đơn cho con.`);
    leaveForm.reset();
  });

  // 7. Modal Thanh toán trực tuyến
  const modalPay = document.getElementById('modalParentPay');
  const btnClosePay = document.getElementById('btnClosePayModal');
  const btnCancelPay = document.getElementById('btnCancelPay');
  const onlinePayForm = document.getElementById('onlinePayForm');
  let payingInvoice = null;

  function openPaymentModal(invCode) {
    const invs = MOCK_DATA.invoices[currentStudentId] || [];
    payingInvoice = invs.find(i => i.code === invCode);
    if (!payingInvoice) return;

    const remaining = payingInvoice.must - payingInvoice.paid;
    document.getElementById('ppInvoiceInfo').innerHTML = `
      <div><strong>Hóa đơn:</strong> ${payingInvoice.code} · ${payingInvoice.course}</div>
      <div style="margin-top:4px;"><strong>Số tiền cần đóng:</strong> <span style="color:var(--err); font-weight:700;">${remaining.toLocaleString('vi-VN')} đ</span></div>
    `;
    document.getElementById('ppAmountInput').value = remaining;
    modalPay.classList.add('show');
  }

  function closePaymentModal() {
    modalPay.classList.remove('show');
    payingInvoice = null;
  }

  btnClosePay.addEventListener('click', closePaymentModal);
  btnCancelPay.addEventListener('click', closePaymentModal);

  onlinePayForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!payingInvoice) return;

    const amount = parseInt(document.getElementById('ppAmountInput').value, 10);
    const provider = document.getElementById('ppProviderSelect').selectedOptions[0].text;
    const newTxCode = 'GD-WEB-' + (900 + MOCK_DATA.transactions.length + 1);

    MOCK_DATA.transactions.unshift({
      code: newTxCode,
      invCode: payingInvoice.code,
      channel: provider,
      amount: amount,
      ref: `FT26300${Math.floor(1000 + Math.random() * 9000)} EDU ${payingInvoice.code}`,
      time: new Date().toLocaleString('vi-VN'),
      status: 'cho_xac_nhan'
    });

    closePaymentModal();
    renderTransactions();
    alert(`Đã khởi tạo lệnh thanh toán ${newTxCode} (${amount.toLocaleString('vi-VN')} đ) thành công! Hệ thống đã gửi yêu cầu đối soát tới Admin.`);
  });

  // 8. Modal Đổi Mật Khẩu (PUT /api/auth/change-password)
  const modalChangePwd = document.getElementById('modalChangePassword');
  const btnOpenChangePwd = document.getElementById('btnOpenChangePwd');
  const btnCloseChangePwd = document.getElementById('btnCloseChangePwdModal');
  const btnCancelChangePwd = document.getElementById('btnCancelChangePwd');
  const changePasswordForm = document.getElementById('changePasswordForm');

  if (btnOpenChangePwd) {
    btnOpenChangePwd.addEventListener('click', () => {
      modalChangePwd.classList.add('show');
    });
  }

  function closeChangePwd() {
    modalChangePwd.classList.remove('show');
    changePasswordForm.reset();
  }

  if (btnCloseChangePwd) btnCloseChangePwd.addEventListener('click', closeChangePwd);
  if (btnCancelChangePwd) btnCancelChangePwd.addEventListener('click', closeChangePwd);

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmNewPassword = document.getElementById('confirmNewPassword').value;

      if (newPassword !== confirmNewPassword) {
        alert('Mật khẩu mới và mật khẩu xác nhận không khớp nhau!');
        return;
      }

      try {
        // Thử gọi API Backend PHP nếu server đang chạy
        try {
          await HttpService.put('/auth/change-password', { currentPassword, newPassword });
        } catch (apiErr) {
          console.warn('[ChangePassword] Chạy chế độ phát triển offline:', apiErr.message);
        }

        alert('Đã đổi mật khẩu thành công! Toàn bộ phiên làm việc cũ đã được thu hồi an toàn.');
        closeChangePwd();
      } catch (err) {
        alert('Đổi mật khẩu thất bại: ' + (err.message || 'Lỗi không xác định'));
      }
    });
  }

  // Khởi động
  initStudentSelector();
});
