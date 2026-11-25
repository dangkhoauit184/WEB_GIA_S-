'use strict';
(() => {
  const base = '/api/academic/scheduling';
  const classTypeLabels = { kem_1_1: 'Kèm 1:1', kem_1_2: 'Kèm 1:2', nhom: 'Lớp nhóm' };
  const roleLabels = { day_chinh: 'Giáo viên / Gia sư (Dạy chính)', tro_giang: 'Trợ giảng' };
  const roleLabelFor = row => row.vaiTro === 'tro_giang'
    ? 'Trợ giảng'
    : (['kem_1_1', 'kem_1_2'].includes(row.loaiLop) ? 'Gia sư / Giáo viên' : 'Giáo viên (Lớp nhóm)');
  const openingStates = { dang_mo: 'Đang mở tuyển', da_du_nguoi: 'Đã đủ người', da_dong: 'Đã đóng' };
  const appStates = { cho_duyet: 'Chờ duyệt', da_duyet: 'Đã duyệt', tu_choi: 'Từ chối', da_huy: 'Đã hủy' };
  const filters = { trangThai: '', vaiTro: '', q: '' };
  let openings = [];
  let selectedOpeningId = null;

  function can(action) {
    return Admin.user?.role === 'ADMIN' || Admin.user?.permissions.some(row => row.functionName === 'VanHanhBuoiHoc' && row[action]);
  }

  function openingTag(value) {
    const cls = value === 'da_du_nguoi' ? 'ok' : value === 'da_dong' ? 'r' : 'w';
    return Admin.tag(openingStates[value] || value, cls);
  }

  function appTag(value) {
    const cls = value === 'da_duyet' ? 'ok' : value === 'tu_choi' || value === 'da_huy' ? 'r' : 'w';
    return Admin.tag(appStates[value] || value, cls);
  }

  function formatDate(ymd) {
    if (!ymd) return '—';
    const [y, m, d] = String(ymd).slice(0, 10).split('-');
    return d && m && y ? `${d}/${m}/${y}` : ymd;
  }

  function formatTime(t) {
    return t ? String(t).slice(0, 5) : '—';
  }

  async function loadOpenings() {
    const body = Admin.$('recruitmentOpeningsBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="8" class="admin-empty">Đang tải danh sách đề xuất tuyển ca…</td></tr>';
    try {
      openings = await Admin.api(`${base}/openings?${Admin.query(filters)}`);
      if (!body.isConnected) return;
      if (selectedOpeningId && !openings.some(item => item.id === selectedOpeningId)) {
        selectedOpeningId = openings[0]?.id ?? null;
      } else if (!selectedOpeningId && openings.length) {
        selectedOpeningId = openings[0].id;
      }
      renderOpenings();
      renderApplications();
    } catch (error) {
      if (!body.isConnected) return;
      body.innerHTML = `<tr><td colspan="8" class="form-error">${Admin.esc(error.message)} <button type="button" class="btn sm" id="recruitment-retry">Thử lại</button></td></tr>`;
      Admin.$('recruitment-retry')?.addEventListener('click', loadOpenings);
    }
  }

  function renderOpenings() {
    const body = Admin.$('recruitmentOpeningsBody');
    if (!body) return;
    if (!openings.length) {
      body.innerHTML = '<tr><td colspan="8" class="admin-empty">Chưa có đề xuất tuyển Giáo viên / Trợ giảng nào. Bấm “+ Đăng đề xuất tuyển ca” để tạo mới.</td></tr>';
      return;
    }
    body.innerHTML = openings.map(row => {
      const selected = row.id === selectedOpeningId;
      const modeText = row.hinhThuc === 'online' ? 'Trực tuyến' : `${row.tenCoSo || row.diaDiem || 'Trực tiếp'}${row.phongHoc ? ' · ' + row.phongHoc : ''}`;
      const regionText = row.hinhThuc === 'online' ? '' : (row.tenKhuVuc ? ` · ${row.tenKhuVuc}` : '');
      return `<tr class="${selected ? 'is-selected' : ''}" style="${selected ? 'background:var(--pri-ll)' : ''}">
        <td><b>${Admin.esc(row.maDeXuat)}</b><br><small>${Admin.esc(row.tenNguoiTao || '')}</small></td>
        <td><b>${Admin.esc(row.maLop)}</b> · ${Admin.esc(row.tenLop)} ${row.loaiLop ? `<span class="tag i">${Admin.esc(classTypeLabels[row.loaiLop] || row.loaiLop)}</span>` : ''}<br><small>${Admin.esc(row.tenMon)} · ${Admin.esc(row.tenCapLop)}</small></td>
        <td><b>${formatDate(row.ngay)}</b><br><small>${formatTime(row.gioBatDau)}–${formatTime(row.gioKetThuc)} · ${Admin.esc(modeText + regionText)}</small></td>
        <td><span class="tag ${row.vaiTro === 'day_chinh' ? 'i' : 'purple'}">${Admin.esc(roleLabelFor(row))}</span><br><small>Đã duyệt: ${row.soDaDuyet}/${row.soLuong}</small></td>
        <td>${Admin.esc(row.yeuCau || '—')}<br><small>GV hiện tại: ${Admin.esc(row.tenNguoiDayChinh || 'Chưa phân công')}${row.tenTroGiang ? ' · TG: ' + Admin.esc(row.tenTroGiang) : ''}</small></td>
        <td><b>${row.soChoDuyet}</b> chờ duyệt<br><small>Tổng ${row.tongUngTuyen} đơn</small></td>
        <td>${openingTag(row.trangThai)}</td>
        <td><div class="row">
          <button type="button" class="btn sm ${selected ? 'p' : ''}" data-rec-action="select" data-id="${row.id}">Ứng viên (${row.tongUngTuyen})</button>
          ${row.trangThai === 'dang_mo' && can('edit') ? `<button type="button" class="btn sm danger" data-rec-action="close" data-id="${row.id}">Đóng</button>` : ''}
        </div></td>
      </tr>`;
    }).join('');
    body.querySelectorAll('[data-rec-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = Number(btn.dataset.id);
        if (btn.dataset.recAction === 'select') {
          selectedOpeningId = id;
          renderOpenings();
          renderApplications();
        } else if (btn.dataset.recAction === 'close') {
          closeOpening(id);
        }
      });
    });
  }

  function renderApplications() {
    const titleEl = Admin.$('recruitmentAppsTitle');
    const metaEl = Admin.$('recruitmentAppsMeta');
    const body = Admin.$('recruitmentAppsBody');
    if (!body || !titleEl) return;
    const opening = openings.find(item => item.id === selectedOpeningId);
    if (!opening) {
      titleEl.textContent = 'Danh sách ứng tuyển';
      if (metaEl) metaEl.textContent = 'Chọn một đề xuất ở bảng trên để xem và duyệt danh sách ứng viên.';
      body.innerHTML = '<tr><td colspan="6" class="admin-empty">Chưa chọn đề xuất tuyển nhân sự.</td></tr>';
      return;
    }
    titleEl.textContent = `Ứng viên cho ${opening.maDeXuat} · ${opening.maLop} (${roleLabelFor(opening)})`;
    if (metaEl) {
      metaEl.textContent = `Ca ngày ${formatDate(opening.ngay)} ${formatTime(opening.gioBatDau)}–${formatTime(opening.gioKetThuc)} · ${opening.tenMon} · ${opening.tenCapLop} · Đã duyệt ${opening.soDaDuyet}/${opening.soLuong} · ${openingStates[opening.trangThai] || opening.trangThai}`;
    }
    const apps = opening.ungTuyens || [];
    if (!apps.length) {
      body.innerHTML = '<tr><td colspan="6" class="admin-empty">Chưa có Giáo viên / Trợ giảng nào ứng tuyển vào đề xuất này.</td></tr>';
      return;
    }
    body.innerHTML = apps.map(app => {
      const eligHtml = app.trangThai === 'cho_duyet'
        ? (app.duDieuKien
          ? Admin.tag('Đủ lịch rảnh & chuyên môn', 'ok')
          : `${Admin.tag('Không còn đủ điều kiện', 'r')}<br><small>${Admin.esc((app.lyDoKhongDuDieuKien || []).join(' '))}</small>`)
        : (app.phanCongId ? Admin.tag(`Đã tạo phân công #${app.phanCongId}`, 'ok') : '—');
      const resultNote = app.trangThai === 'tu_choi'
        ? `<br><small style="color:var(--err)">Lý do: ${Admin.esc(app.lyDoTuChoi || '—')}</small>`
        : (app.duyetLuc ? `<br><small>${Admin.esc(app.nguoiDuyetTen || '')} · ${Admin.esc(Admin.date(app.duyetLuc))}</small>` : '');
      const actions = app.trangThai === 'cho_duyet' && opening.trangThai === 'dang_mo' && can('edit')
        ? `<div class="row">
            <button type="button" class="btn sm p" data-app-action="approve" data-id="${app.id}" ${app.duDieuKien ? '' : 'disabled title="Ứng viên hiện không còn đủ điều kiện lịch hoặc chuyên môn"'}>Duyệt &amp; Phân công</button>
            <button type="button" class="btn sm danger" data-app-action="reject" data-id="${app.id}">Từ chối</button>
          </div>`
        : '—';
      return `<tr>
        <td><b>${Admin.esc(app.hoTen)}</b><br><small>${Admin.esc(app.maNs || '')}${app.donViCongTac ? ' · ' + Admin.esc(app.donViCongTac) : ''}${app.kinhNghiemNam != null ? ` · ${app.kinhNghiemNam} năm KN` : ''}</small></td>
        <td>${Admin.esc(app.ghiChu || '—')}</td>
        <td>${eligHtml}</td>
        <td>${Admin.esc(Admin.date(app.createdAt))}</td>
        <td>${appTag(app.trangThai)}${resultNote}</td>
        <td>${actions}</td>
      </tr>`;
    }).join('');
    body.querySelectorAll('[data-app-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const appId = Number(btn.dataset.id);
        const app = apps.find(item => item.id === appId);
        if (!app) return;
        if (btn.dataset.appAction === 'approve') approveApplication(opening, app);
        else rejectApplication(opening, app);
      });
    });
  }

  function approveApplication(opening, app) {
    const warnReplace = opening.vaiTro === 'day_chinh' && opening.tenNguoiDayChinh
      ? ` Lưu ý: ca hiện có Giáo viên chính (${opening.tenNguoiDayChinh}) chưa check-in sẽ được gỡ phân công thay thế; lịch học của lớp giữ nguyên.`
      : '';
    Admin.confirmDialog(
      'Duyệt ứng tuyển & tạo phân công',
      `Xác nhận duyệt ${app.hoTen} vào vị trí ${roleLabels[opening.vaiTro]} cho ca ${opening.maLop} (${formatDate(opening.ngay)} ${formatTime(opening.gioBatDau)}–${formatTime(opening.gioKetThuc)})?${warnReplace}`,
      async () => {
        await Admin.api(`${base}/applications/${app.id}/review`, 'POST', { trangThai: 'da_duyet' });
        Admin.notify('Đã duyệt ứng tuyển và tạo phân công chính thức lên thời khóa biểu.');
        await loadOpenings();
      }
    );
  }

  function rejectApplication(opening, app) {
    Admin.formDialog({
      title: `Từ chối đơn ứng tuyển · ${app.hoTen}`,
      submit: 'Xác nhận từ chối',
      fields: [
        { name: 'summary', type: 'summary', label: `${opening.maDeXuat} · ${opening.maLop} (${formatDate(opening.ngay)} ${formatTime(opening.gioBatDau)}–${formatTime(opening.gioKetThuc)})`, disabled: true },
        { name: 'lyDoTuChoi', label: 'Lý do từ chối *', type: 'textarea', required: true, maxLength: 255 }
      ],
      onSubmit: async data => {
        await Admin.api(`${base}/applications/${app.id}/review`, 'POST', { trangThai: 'tu_choi', lyDoTuChoi: data.lyDoTuChoi });
        Admin.notify('Đã từ chối đơn ứng tuyển và lưu lý do.');
        await loadOpenings();
      }
    });
  }

  function closeOpening(id) {
    const opening = openings.find(item => item.id === id);
    if (!opening) return;
    Admin.confirmDialog(
      'Đóng đề xuất tuyển nhân sự',
      `Đóng đề xuất ${opening.maDeXuat} (${opening.maLop} · ${formatDate(opening.ngay)})? Các đơn đang chờ duyệt (nếu có) sẽ chuyển sang trạng thái Từ chối.`,
      async () => {
        await Admin.api(`${base}/openings/${id}/close`, 'POST', {});
        Admin.notify('Đã đóng đề xuất tuyển nhân sự.');
        await loadOpenings();
      }
    );
  }

  async function openCreateModal(presetSessionId = null) {
    if (!can('add')) {
      Admin.notify('Tài khoản chưa được cấp quyền đăng đề xuất tuyển nhân sự.', true);
      return;
    }
    try {
      const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' });
      const sessions = await Admin.api(`${base}/sessions?${Admin.query({ tuNgay: today, trangThai: 'du_kien' })}`);
      const upcoming = sessions.filter(item => Number(item.chuaBatDau));
      if (!upcoming.length) {
        Admin.notify('Chưa có ca học sắp diễn ra nào để đăng đề xuất tuyển nhân sự.', true);
        return;
      }
      Admin.dialog('Đăng đề xuất tuyển Giáo viên / Gia sư / Trợ giảng', `<form id="shift-opening-form"><div class="dialog-body">
        <label>Ca học cần phân công *
          <select name="buoiHocId" id="openingSessionSel" required>
            ${upcoming.map(s => `<option value="${s.id}" data-loai-lop="${Admin.esc(s.loaiLop || '')}" ${String(s.id) === String(presetSessionId) ? 'selected' : ''}>${Admin.esc(`${s.maLop}${s.loaiLop ? ' [' + (classTypeLabels[s.loaiLop] || s.loaiLop) + ']' : ''} · ${formatDate(s.ngay)} ${formatTime(s.gioBatDau)}–${formatTime(s.gioKetThuc)} · Dạy chính: ${s.tenNguoiDayChinh || 'Chưa có'}${s.tenTroGiang ? ' · TG: ' + s.tenTroGiang : ''}`)}</option>`).join('')}
          </select>
        </label>
        <label>Vị trí cần tuyển *
          <select name="vaiTro" id="openingRoleSel" required>
            <option value="tro_giang">Trợ giảng</option>
            <option value="day_chinh">Giáo viên / Gia sư (Dạy chính)</option>
          </select>
          <small id="openingRoleHint"></small>
        </label>
        <label>Số lượng cần tuyển *
          <input name="soLuong" id="openingQtyInput" type="number" min="1" max="5" step="1" value="1" required>
          <small>Vị trí dạy chính cố định 1 người/ca. Trợ giảng có thể chọn từ 1 đến 5.</small>
        </label>
        <label>Yêu cầu / Ghi chú tuyển dụng
          <textarea name="yeuCau" rows="3" maxlength="500" placeholder="VD: Ưu tiên đã có kinh nghiệm luyện đề lớp 9, hỗ trợ kiểm tra bài tập đầu giờ..."></textarea>
        </label>
        <p id="dialog-error" class="form-error" role="alert"></p>
      </div><div class="dialog-foot">
        <button type="button" class="btn" id="opening-cancel">Hủy</button>
        <button type="submit" class="btn p" id="opening-submit">Đăng đề xuất</button>
      </div></form>`);
      const form = Admin.$('shift-opening-form');
      const sessionSel = Admin.$('openingSessionSel');
      const roleSel = Admin.$('openingRoleSel');
      const roleHint = Admin.$('openingRoleHint');
      const qtyInput = Admin.$('openingQtyInput');
      const syncQty = () => {
        const loaiLop = sessionSel?.selectedOptions[0]?.dataset.loaiLop || '';
        if (roleSel.value === 'day_chinh') {
          qtyInput.value = '1';
          qtyInput.max = '1';
          qtyInput.readOnly = true;
          if (roleHint) {
            roleHint.textContent = ['kem_1_1', 'kem_1_2'].includes(loaiLop)
              ? 'Ca lớp kèm gia sư: cả Gia sư và Giáo viên phù hợp đều được ứng tuyển.'
              : 'Ca lớp nhóm: chỉ Giáo viên phù hợp được ứng tuyển (Gia sư không xếp vào lớp nhóm).';
          }
        } else {
          qtyInput.max = '5';
          qtyInput.readOnly = false;
          if (roleHint) roleHint.textContent = 'Chỉ tài khoản Trợ giảng phù hợp lịch rảnh và chuyên môn được ứng tuyển.';
        }
      };
      syncQty();
      roleSel.addEventListener('change', syncQty);
      sessionSel?.addEventListener('change', syncQty);
      Admin.$('opening-cancel').addEventListener('click', () => {
        if (Admin.$('admin-dialog').dataset.busy !== 'true') Admin.$('admin-dialog').close();
      });
      form.addEventListener('submit', async event => {
        event.preventDefault();
        const dialog = Admin.$('admin-dialog');
        if (dialog.dataset.busy === 'true') return;
        const fd = new FormData(form);
        const body = {
          buoiHocId: Number(fd.get('buoiHocId')),
          vaiTro: fd.get('vaiTro'),
          soLuong: Number(fd.get('soLuong') || 1),
          yeuCau: String(fd.get('yeuCau') || '').trim() || null,
        };
        dialog.dataset.busy = 'true';
        Admin.$('opening-submit').disabled = true;
        Admin.$('dialog-error').textContent = '';
        try {
          const created = await Admin.api(`${base}/openings`, 'POST', body);
          dialog.close();
          selectedOpeningId = created.id;
          Admin.notify(`Đã đăng đề xuất ${created.maDeXuat}.`);
          if (Admin.currentScreen() === 'staff-recruitment') await loadOpenings();
          else showS('staff-recruitment');
        } catch (error) {
          if (form.isConnected) Admin.$('dialog-error').textContent = error.message;
        } finally {
          dialog.dataset.busy = 'false';
          if (Admin.$('opening-submit')) Admin.$('opening-submit').disabled = false;
        }
      });
    } catch (error) {
      Admin.notify(error.message, true);
    }
  }

  window.openShiftRecruitmentForm = openCreateModal;

  StaffUI.register('staff-recruitment', 'Đề xuất & Duyệt ứng tuyển ca', async () => true, () => {
    const createBtn = Admin.$('recruitmentCreateBtn');
    if (createBtn) {
      createBtn.disabled = !can('add');
      createBtn.addEventListener('click', () => openCreateModal());
    }
    const form = Admin.$('recruitmentFilterForm');
    if (form) {
      form.elements.q.value = filters.q;
      form.elements.trangThai.value = filters.trangThai;
      form.elements.vaiTro.value = filters.vaiTro;
      form.addEventListener('submit', event => {
        event.preventDefault();
        const fd = new FormData(form);
        filters.q = String(fd.get('q') || '').trim();
        filters.trangThai = String(fd.get('trangThai') || '');
        filters.vaiTro = String(fd.get('vaiTro') || '');
        loadOpenings();
      });
    }
    Admin.$('recruitmentReloadBtn')?.addEventListener('click', loadOpenings);
    loadOpenings();
  });
})();
