'use strict';

// Cổng Phụ huynh khởi tạo trên trang có bộ chọn học sinh.
(() => {
  if (!document.getElementById('studentSelector')) return;

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
    ],
    proposals: [
      {
        id: 102,
        maYc: 'YC-2026-02',
        hocVienId: 'HV-0019',
        tenHocVien: 'Nguyễn Văn An',
        tenNguoiDay: 'Gia sư Trần Quốc Bảo',
        maNs: 'NS-005',
        hocPhiDeXuat: 3500000,
        ghiChu: 'ĐH KHTN · 2 năm kinh nghiệm kèm 1:1',
        ttPhuHuynh: 'cho',
        ttNhanSu: 'dong_y'
      }
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

    // 3. Quản lý trạng thái dữ liệu Portal (ưu tiên dữ liệu thật từ Backend API, fallback sang MOCK_DATA khi chạy offline)
    let isUsingBackend = false;
    let portalData = {
      students: [...MOCK_DATA.students],
      schedules: { ...MOCK_DATA.schedules },
      learningNotes: { ...MOCK_DATA.learningNotes },
      invoices: { ...MOCK_DATA.invoices },
      leaveRequests: [...MOCK_DATA.leaveRequests],
      transactions: [...MOCK_DATA.transactions],
      notifications: [...MOCK_DATA.notifications],
      proposals: [...MOCK_DATA.proposals],
    };

    let currentStudentId = localStorage.getItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID) || (portalData.students[0] ? String(portalData.students[0].id) : '');

    const studentSelector = document.getElementById('studentSelector');
    const selectedStudentName = document.getElementById('selectedStudentName');
    const kidChipsContainer = document.getElementById('kidChipsContainer');

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, (m) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
    }

    async function loadPortalFromBackend() {
      if (typeof HttpService === 'undefined') return;
      try {
        const [portalRes, notifRes, propRes] = await Promise.all([
          HttpService.get('/people/parents/portal'),
          HttpService.get('/communication/notifications').catch(() => null),
          HttpService.get('/academic/admissions/proposals').catch(() => null),
        ]);

        const data = portalRes && portalRes.data ? portalRes.data : null;
        if (data && Array.isArray(data.students) && data.students.length > 0) {
          isUsingBackend = true;
          portalData.students = data.students.map((s, idx) => ({
            id: String(s.id),
            maHv: s.maHv,
            name: s.hoTen,
            grade: s.tenCapLop || (s.truong ? s.truong : 'Học viên'),
            avatar: s.gioiTinh === 'nu' ? '👧' : (idx % 2 === 0 ? '👦' : '👧'),
          }));
          portalData.schedules = data.schedules || {};
          portalData.learningNotes = data.learningNotes || {};
          portalData.invoices = data.invoices || {};
          portalData.leaveRequests = Array.isArray(data.leaveRequests) ? data.leaveRequests : [];
          portalData.transactions = Array.isArray(data.transactions) ? data.transactions : [];

          if (data.parent && data.parent.hoTen) {
            document.getElementById('currentUserName').textContent = data.parent.hoTen;
          }

          const exists = portalData.students.some(s => String(s.id) === String(currentStudentId));
          if (!exists) {
            currentStudentId = String(portalData.students[0].id);
            localStorage.setItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID, currentStudentId);
          }
        }

        if (notifRes && Array.isArray(notifRes.data)) {
          portalData.notifications = notifRes.data.map(n => ({
            id: Number(n.id),
            title: n.tieuDe,
            content: n.noiDung || '',
            time: n.createdAt || '',
            read: Boolean(n.daDoc),
          }));
        }

        if (propRes && propRes.data && Array.isArray(propRes.data.items)) {
          portalData.proposals = propRes.data.items;
        }
      } catch (err) {
        console.warn('[ParentsPortal] Sử dụng dữ liệu mẫu khi chưa kết nối API hoặc chưa gán học viên:', err.message);
      }

      initStudentSelector();
    }

    function initStudentSelector() {
      if (!portalData.students.length) {
        studentSelector.innerHTML = `<option value="">Chưa có hồ sơ con liên kết</option>`;
        kidChipsContainer.innerHTML = '';
        selectedStudentName.textContent = 'Chưa liên kết học sinh';
        return;
      }

      studentSelector.innerHTML = portalData.students.map(s => `
        <option value="${escapeHtml(s.id)}" ${String(s.id) === String(currentStudentId) ? 'selected' : ''}>
          ${escapeHtml(s.name)} (${escapeHtml(s.grade)})
        </option>
      `).join('');

      renderKidChips();
      renderAllViewsForCurrentStudent();
    }

    function renderKidChips() {
      kidChipsContainer.innerHTML = portalData.students.map(s => `
        <button type="button" class="chip ${String(s.id) === String(currentStudentId) ? 'active' : ''}" data-id="${escapeHtml(s.id)}">
          ${s.avatar} ${escapeHtml(s.name)} (${escapeHtml(s.grade)})
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
      currentStudentId = String(id);
      studentSelector.value = currentStudentId;
      localStorage.setItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID, currentStudentId);
      renderKidChips();
      renderAllViewsForCurrentStudent();
    }

    // 4. Render các View theo con đang chọn
    function renderAllViewsForCurrentStudent() {
      const student = portalData.students.find(s => String(s.id) === String(currentStudentId)) || portalData.students[0];
      if (student) {
        selectedStudentName.textContent = `${student.name} (${student.grade})`;
      }

      renderProposals();
      renderSchedule();
      renderLearningNotes();
      renderInvoices();
      renderLeaveHistory();
      renderTransactions();
      renderNotifications();
      populateLeaveSessionDropdown();
    }

    // 4.0 Đề xuất người dạy chờ Phụ huynh phản hồi (GET /api/academic/admissions/proposals & POST /api/academic/admissions/proposals/:id/respond)
    function renderProposals() {
      const bannerEl = document.getElementById('parentProposalBanner');
      if (!bannerEl) return;

      const list = (portalData.proposals || []).filter(p => {
        if (p.hocVienId && String(p.hocVienId) !== String(currentStudentId)) return false;
        return true;
      });

      if (!list.length) {
        bannerEl.innerHTML = '';
        return;
      }

      bannerEl.innerHTML = list.map(p => {
        const isPending = p.ttPhuHuynh === 'cho';
        const statusLabel = p.ttPhuHuynh === 'dong_y'
          ? '<span class="badge badge-ok">Phụ huynh đã đồng ý</span>'
          : (p.ttPhuHuynh === 'tu_choi' ? '<span class="badge badge-warn">Phụ huynh đã từ chối</span>' : '<span class="badge badge-teal">Chờ Phụ huynh xác nhận</span>');
        const feeText = p.hocPhiDeXuat ? ` · Học phí trọn khóa đề xuất: <strong>${Number(p.hocPhiDeXuat).toLocaleString('vi-VN')} đ</strong>` : '';

        return `
          <div class="card" style="background:var(--pri-ll, #f0fdfa); border:1px solid #99f6e4; margin-bottom:10px; padding:16px 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
              <div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                  <strong style="color:var(--pri-d, #0f766e); font-size:14.5px;">🔔 Đề xuất Gia sư / Người dạy (DX-${escapeHtml(p.id)} · ${escapeHtml(p.maYc || '')})</strong>
                  ${statusLabel}
                </div>
                <p style="margin:0; font-size:13px; color:var(--g700, #334155);">
                  Giáo vụ đề xuất <strong>${escapeHtml(p.tenNguoiDay)}</strong> ${p.ghiChu ? `(${escapeHtml(p.ghiChu)})` : ''} phụ trách học sinh <strong>${escapeHtml(p.tenHocVien || p.hoTenLienHe || '')}</strong>${feeText}.
                </p>
              </div>
              ${isPending ? `
                <div style="display:flex; gap:8px;">
                  <button type="button" class="btn btn-primary btn-prop-accept" data-id="${escapeHtml(p.id)}" style="padding:7px 14px; font-size:12.5px;">✓ Đồng ý Gia sư</button>
                  <button type="button" class="btn btn-secondary btn-prop-reject" data-id="${escapeHtml(p.id)}" style="padding:7px 14px; font-size:12.5px; color:var(--err, #e11d48); border-color:#fecdd3;">✕ Từ chối</button>
                </div>
              ` : ''}
            </div>
          </div>
        `;
      }).join('');

      bannerEl.querySelectorAll('.btn-prop-accept').forEach(btn => {
        btn.addEventListener('click', () => respondToProposal(Number(btn.dataset.id), 'dong_y'));
      });
      bannerEl.querySelectorAll('.btn-prop-reject').forEach(btn => {
        btn.addEventListener('click', () => {
          const reason = prompt('Vui lòng nhập lý do từ chối đề xuất Gia sư này để Giáo vụ tìm ứng viên khác:');
          if (reason === null) return;
          if (!reason.trim()) {
            alert('Vui lòng nhập lý do khi từ chối đề xuất.');
            return;
          }
          respondToProposal(Number(btn.dataset.id), 'tu_choi', reason.trim());
        });
      });
    }

    async function respondToProposal(proposalId, trangThai, lyDo = null) {
      if (isUsingBackend) {
        try {
          await HttpService.post(`/academic/admissions/proposals/${proposalId}/respond`, {
            trangThai,
            lyDo,
          });
          await loadPortalFromBackend();
          alert(trangThai === 'dong_y'
            ? 'Đã xác nhận ĐỒNG Ý đề xuất người dạy! Hệ thống đã thông báo cho Giáo vụ và Admin xếp lớp.'
            : 'Đã ghi nhận TỪ CHỐI đề xuất người dạy. Giáo vụ sẽ tìm ứng viên khác phù hợp hơn.');
          return;
        } catch (err) {
          alert(err.message || 'Không thể gửi phản hồi đề xuất.');
          return;
        }
      }

      const item = (portalData.proposals || []).find(x => Number(x.id) === Number(proposalId));
      if (item) {
        item.ttPhuHuynh = trangThai;
        item.lyDoTuChoiPhuHuynh = lyDo;
        renderProposals();
        alert(trangThai === 'dong_y'
          ? 'Đã xác nhận ĐỒNG Ý đề xuất người dạy!'
          : 'Đã ghi nhận TỪ CHỐI đề xuất người dạy.');
      }
    }

    // 4.1 Thời khóa biểu
    function renderSchedule() {
      const tbody = document.getElementById('scheduleTableBody');
      const items = portalData.schedules[currentStudentId] || [];

      if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Chưa có lịch học nào.</td></tr>`;
        return;
      }

      tbody.innerHTML = items.map(s => `
        <tr>
          <td><strong>${escapeHtml(s.date)}</strong></td>
          <td>${escapeHtml(s.time)}</td>
          <td><strong>${escapeHtml(s.course)}</strong></td>
          <td>${escapeHtml(s.room)}</td>
          <td>${escapeHtml(s.teacher)}</td>
          <td><span class="badge badge-teal">${escapeHtml(s.status)}</span></td>
        </tr>
      `).join('');
    }

    // 4.2 Sổ theo dõi buổi học
    function renderLearningNotes() {
      const tbody = document.getElementById('learningTableBody');
      const notes = portalData.learningNotes[currentStudentId] || [];

      if (notes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Chưa có dữ liệu theo dõi buổi học.</td></tr>`;
        return;
      }

      tbody.innerHTML = notes.map(n => `
        <tr>
          <td>${escapeHtml(n.date)}</td>
          <td><strong>${escapeHtml(n.course)}</strong></td>
          <td><span class="badge badge-ok">${escapeHtml(n.attend)}</span></td>
          <td><strong style="color:var(--pri-d);">${escapeHtml(n.score)}</strong></td>
          <td>${escapeHtml(n.star)}</td>
          <td>${escapeHtml(n.comment)}</td>
        </tr>
      `).join('');
    }

    // 4.3 Hóa đơn học phí
    function renderInvoices() {
      const tbody = document.getElementById('invoiceTableBody');
      const invs = portalData.invoices[currentStudentId] || [];

      if (invs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Không có hóa đơn học phí.</td></tr>`;
        return;
      }

      tbody.innerHTML = invs.map(i => {
        const must = Number(i.must || 0);
        const paid = Number(i.paid || 0);
        const remaining = Math.max(0, must - paid);
        const statusBadge = i.status === 'da_thanh_toan'
          ? `<span class="badge badge-ok">Đã thanh toán</span>`
          : (i.status === 'mot_phan' ? `<span class="badge badge-warn">Một phần</span>` : `<span class="badge badge-warn">Chưa thanh toán</span>`);

        const actionBtn = remaining > 0
          ? `<button class="btn btn-primary btn-pay-modal" style="padding:6px 12px; font-size:12px;" data-code="${escapeHtml(i.code)}">💳 Thanh toán Web</button>`
          : `<span class="text-muted" style="font-size:12px;">Đã hoàn tất</span>`;

        return `
          <tr>
            <td><strong style="font-family:monospace;">${escapeHtml(i.code)}</strong></td>
            <td><strong>${escapeHtml(i.course)}</strong></td>
            <td>${must.toLocaleString('vi-VN')} đ</td>
            <td>${paid.toLocaleString('vi-VN')} đ</td>
            <td style="color:${remaining > 0 ? 'var(--err)' : 'var(--ok)'}; font-weight:700;">
              ${remaining.toLocaleString('vi-VN')} đ
            </td>
            <td>${escapeHtml(i.deadline || '—')}</td>
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
      const items = (portalData.schedules[currentStudentId] || []).filter(s => !s.canLeave || s.canLeave === true);
      sel.innerHTML = '<option value="">-- Chọn buổi học sắp tới --</option>' +
        items.map((s, idx) => `<option value="${escapeHtml(s.buoiHocId || (idx + 1))}">${escapeHtml(s.date)} (${escapeHtml(s.time)}) · ${escapeHtml(s.course)}</option>`).join('');
    }

    function renderLeaveHistory() {
      const tbody = document.getElementById('leaveHistoryTableBody');
      const list = portalData.leaveRequests.filter(r => String(r.studentId) === String(currentStudentId));

      if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:16px;">Chưa có đơn xin nghỉ nào.</td></tr>`;
        return;
      }

      tbody.innerHTML = list.map(r => `
        <tr>
          <td><strong>${escapeHtml(r.code)}</strong></td>
          <td>${escapeHtml(r.session)}</td>
          <td>${escapeHtml(r.reason)}</td>
          <td>${escapeHtml(r.date)}</td>
          <td><span class="badge badge-ok">${escapeHtml(r.status)}</span></td>
        </tr>
      `).join('');
    }

    // 4.5 Giao dịch trực tuyến
    function renderTransactions() {
      const tbody = document.getElementById('transactionHistoryTableBody');
      if (!portalData.transactions.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted" style="padding:16px;">Chưa có giao dịch trực tuyến nào.</td></tr>`;
        return;
      }
      tbody.innerHTML = portalData.transactions.map(t => {
        const isConfirmed = t.status === 'da_xac_nhan' || t.status === 'thanh_cong';
        return `
          <tr>
            <td><strong style="font-family:monospace;">${escapeHtml(t.code)}</strong></td>
            <td><strong>${escapeHtml(t.invCode)}</strong></td>
            <td>${escapeHtml(t.channel)}</td>
            <td><strong style="color:var(--pri-d);">${Number(t.amount || 0).toLocaleString('vi-VN')} đ</strong></td>
            <td style="font-family:monospace; font-size:12px;">${escapeHtml(t.ref)}</td>
            <td>${escapeHtml(t.time)}</td>
            <td><span class="badge ${isConfirmed ? 'badge-ok' : 'badge-warn'}">${isConfirmed ? 'Admin đã xác nhận' : 'Chờ Admin đối soát'}</span></td>
          </tr>
        `;
      }).join('');
    }

    // 4.6 Thông báo hệ thống
    function renderNotifications() {
      const container = document.getElementById('notificationsListContainer');
      if (!container) return;

      if (!portalData.notifications.length) {
        container.innerHTML = `<div class="text-muted" style="padding:16px; text-align:center;">Chưa có thông báo nào.</div>`;
        return;
      }

      container.innerHTML = portalData.notifications.map(n => `
        <div style="background:${n.read ? 'var(--g50)' : 'var(--pri-ll)'}; border:1px solid ${n.read ? 'var(--g200)' : '#ccfbf1'}; border-radius:var(--radius-md); padding:16px; display:flex; justify-content:space-between; align-items:center;">
          <div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
              <strong style="font-size:14px; color:var(--g900);">${escapeHtml(n.title)}</strong>
              ${!n.read ? `<span class="badge badge-teal" style="font-size:10px;">Mới</span>` : ''}
            </div>
            <p style="margin:0; font-size:13px; color:var(--g600);">${escapeHtml(n.content)}</p>
            <span style="font-size:11px; color:var(--g400); margin-top:4px; display:block;">${escapeHtml(n.time)}</span>
          </div>
          ${!n.read ? `<button class="btn btn-secondary btn-mark-read" style="font-size:11.5px; padding:4px 8px;" data-id="${n.id}">Đã đọc</button>` : ''}
        </div>
      `).join('');

      container.querySelectorAll('.btn-mark-read').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          try {
            await HttpService.post(`/communication/notifications/${id}/read`, {});
          } catch (e) {
            console.warn('[Notifications] Đánh dấu đã đọc cục bộ:', e.message);
          }
          const item = portalData.notifications.find(x => x.id === id);
          if (item) {
            item.read = true;
            renderNotifications();
          }
        });
      });
    }

    const btnMarkAll = document.getElementById('btnMarkAllRead');
    if (btnMarkAll) {
      btnMarkAll.addEventListener('click', async () => {
        try {
          await HttpService.post('/communication/notifications/read-all', {});
        } catch (e) {
          console.warn('[Notifications] Đánh dấu tất cả đã đọc cục bộ:', e.message);
        }
        portalData.notifications.forEach(n => n.read = true);
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
    leaveForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const selectEl = document.getElementById('leaveSessionSelect');
      const buoiHocId = selectEl.value;
      const sessionText = selectEl.selectedOptions[0] ? selectEl.selectedOptions[0].text : '';
      const reason = document.getElementById('leaveReason').value.trim();

      if (!buoiHocId || !reason) return;

      if (isUsingBackend) {
        try {
          const res = await HttpService.post('/people/parents/leave-requests', {
            hocVienId: Number(currentStudentId),
            buoiHocId: Number(buoiHocId),
            lyDo: reason,
          });
          const created = res && res.data ? res.data : null;
          if (created) {
            portalData.leaveRequests.unshift(created);
          } else {
            await loadPortalFromBackend();
          }
          renderLeaveHistory();
          alert('Đã gửi đơn xin nghỉ phép lên hệ thống thành công! Giáo vụ sẽ sớm xem xét và duyệt đơn cho con.');
          leaveForm.reset();
          return;
        } catch (err) {
          alert(err.message || 'Không thể gửi đơn xin nghỉ phép.');
          return;
        }
      }

      const newCode = 'NP-2026-0' + (portalData.leaveRequests.length + 1);
      portalData.leaveRequests.unshift({
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
      const invs = portalData.invoices[currentStudentId] || [];
      payingInvoice = invs.find(i => i.code === invCode);
      if (!payingInvoice) return;

      const remaining = Number(payingInvoice.must || 0) - Number(payingInvoice.paid || 0);
      document.getElementById('ppInvoiceInfo').innerHTML = `
        <div><strong>Hóa đơn:</strong> ${escapeHtml(payingInvoice.code)} · ${escapeHtml(payingInvoice.course)}</div>
        <div style="margin-top:4px;"><strong>Số tiền cần đóng:</strong> <span style="color:var(--err); font-weight:700;">${remaining.toLocaleString('vi-VN')} đ</span></div>
      `;
      const amountInput = document.getElementById('ppAmountInput');
      amountInput.value = remaining;
      amountInput.readOnly = isUsingBackend;
      modalPay.classList.add('show');
    }

    function closePaymentModal() {
      modalPay.classList.remove('show');
      payingInvoice = null;
    }

    btnClosePay.addEventListener('click', closePaymentModal);
    btnCancelPay.addEventListener('click', closePaymentModal);

    onlinePayForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!payingInvoice) return;

      const amount = parseInt(document.getElementById('ppAmountInput').value, 10);
      const providerSelect = document.getElementById('ppProviderSelect');
      const providerCode = providerSelect.value;
      const providerLabel = providerSelect.selectedOptions[0].text;

      if (isUsingBackend && payingInvoice.id) {
        try {
          const res = await HttpService.post('/people/parents/payments', {
            hoaDonId: Number(payingInvoice.id),
            nhaCungCap: providerCode,
            soTien: amount,
          });
          const tx = res && res.data ? res.data : null;
          if (tx) {
            portalData.transactions.unshift(tx);
          }
          closePaymentModal();
          renderTransactions();
          alert(`Đã khởi tạo giao dịch thanh toán ${tx?.code || ''} (${amount.toLocaleString('vi-VN')} đ) thành công! Hệ thống đã chuyển sang trạng thái chờ Admin đối soát.`);
          return;
        } catch (err) {
          alert(err.message || 'Không thể khởi tạo giao dịch thanh toán trực tuyến.');
          return;
        }
      }

      const newTxCode = 'GD-WEB-' + (900 + portalData.transactions.length + 1);
      portalData.transactions.unshift({
        code: newTxCode,
        invCode: payingInvoice.code,
        channel: providerLabel,
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
          try {
            const res = await HttpService.put('/auth/change-password', { currentPassword, newPassword });
            const data = res && res.data ? res.data : null;
            if (data && data.token && data.user) {
              AccessService.setSession(data.token, data.user);
            }
          } catch (apiErr) {
            if (apiErr.status && apiErr.status !== 0) {
              throw apiErr;
            }
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
    loadPortalFromBackend();
  });
})();

// Màn quản lý phụ huynh dùng trong cổng Giáo vụ.
(() => {
  if (!window.Admin || !window.StaffUI) return;
  function render(rows) {
    StaffUI.rows('parentsTableBody',7,rows.map(row=>`<tr><td><b>PH-${row.userId}</b></td><td><b>${Admin.esc(row.hoTen)}</b></td><td>${Admin.esc(row.sdt || '—')}<br><small>${Admin.esc(row.email)}</small></td><td>${Admin.esc(row.tenKhuVuc || '—')}<br><small>${Admin.esc(row.diaChi || '')}</small></td><td>${row.soCon} học viên</td><td>${Admin.tag(row.username || 'Chưa cấp tài khoản',row.accountStatus==='active'?'ok':'w')}</td><td>${Admin.button('Chi tiết & Liên kết con','parent-detail',row.userId)}</td></tr>`));
    Admin.$('parentsTableBody').querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>parentDetail(Number(button.dataset.id))));
  }
  StaffUI.register('staff-parents','Hồ sơ Phụ huynh',()=>Admin.api('/api/people/parents'),rows=>{
    render(rows);
    StaffUI.search('parentsTableBody',async q=>render(await Admin.api('/api/people/parents?'+Admin.query({q}))));
    document.querySelector('#staff-parents [onclick="openParentFormModal()"]').hidden=!StaffUI.can('TiepNhanYeuCau','add');
  });
  window.openParentFormModal = async id => {
    try {
      const [catalog,parent]=await Promise.all([Admin.api('/api/administration/catalog'),id?Admin.api('/api/people/parents/'+id):Promise.resolve(null)]);
      const fields=[{name:'hoTen',label:'Họ tên phụ huynh',required:true,maxLength:150},
        {name:'sdt',label:'Số điện thoại',maxLength:20,nullable:true},{name:'email',label:'Email nhận thông báo',type:'email',required:true,maxLength:150},
        StaffUI.select('khuVucId','Khu vực',catalog.regions),{name:'diaChi',label:'Địa chỉ',maxLength:255,nullable:true},
        {name:'ngheNghiep',label:'Nghề nghiệp',maxLength:150,nullable:true}];
      fields.forEach(field=>{if(parent)field.value=parent[field.name];});
      Admin.formDialog({title:parent?'Sửa hồ sơ phụ huynh':'Thêm hồ sơ phụ huynh',fields,onSubmit:async body=>{
        if(body.khuVucId!=null)body.khuVucId=Number(body.khuVucId);
        await Admin.api('/api/people/parents'+(id?'/'+id:''),id?'PUT':'POST',body);
        toast('Đã lưu hồ sơ phụ huynh.','ok');Admin.render();
      }});
    }catch(error){toast(error.message,'err');}
  };
  async function parentDetail(id) {
    try {
      const parent=await Admin.api('/api/people/parents/'+id);
      Admin.dialog('Hồ sơ phụ huynh · '+parent.hoTen,`<div class="dialog-body"><dl class="staff-detail-list"><dt>Họ tên</dt><dd>${Admin.esc(parent.hoTen)}</dd><dt>Liên hệ</dt><dd>${Admin.esc(parent.sdt || '—')} · ${Admin.esc(parent.email)}</dd><dt>Địa chỉ</dt><dd>${Admin.esc(parent.diaChi || '—')}</dd><dt>Tài khoản web</dt><dd>${Admin.esc(parent.username || 'Chờ Admin cấp tài khoản')}</dd></dl><h3>Các con đã liên kết</h3>${Admin.table([{key:'maHv',label:'Mã học viên'},{key:'hoTen',label:'Học viên'},{key:'tenCapLop',label:'Khối lớp'}],parent.hocViens)}
        <div class="btn-group">${StaffUI.can('TiepNhanYeuCau','edit')?'<button class="btn" id="staff-edit-parent">Sửa hồ sơ</button>':''}${StaffUI.can('QuanLyLopHoc','add')?'<button class="btn p" id="staff-add-child">Thêm học viên cho phụ huynh</button>':''}</div></div>`);
      Admin.$('staff-edit-parent')?.addEventListener('click',()=>openParentFormModal(id));
      Admin.$('staff-add-child')?.addEventListener('click',()=>openStudentForm(null,id));
    }catch(error){toast(error.message,'err');}
  }
})();
