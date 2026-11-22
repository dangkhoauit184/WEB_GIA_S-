(() => {
  "use strict";

  //if (!AccessService.requireAuth(["TRO_GIANG"])) return;

  const LABELS = {
    VERSION_TAG: "DB 004/005",
    VERSION_005: "(005)",
    STANDARD_005: "Chuẩn nghiệp vụ DB 005",
    POLICY_NOTE:
      "Chính sách Chấm công DB 004/005: Chỉ bấm nút Check-in/Check-out trực tiếp (không GPS, không ảnh, không khóa 30p). Số phút tính công = phần giao giữa [Check-in, Check-out] và khung lịch.",
    TABLE_HEADER: "Danh sách các ca phân công trong tuần (PHAN_CONG_BUOI_HOC)",
  };

  const screens = {
    dash: "Tổng quan hỗ trợ",
    schedule: "Lịch ca trợ giảng",
    session: "Ca học & Check-in (005)",
    log: "Hỗ trợ điểm danh",
    myclasses: "Lớp hỗ trợ",
    requests: "Đơn từ & Báo bận",
    paysheet: "Bảng công trợ giảng",
  };

  const icons = {
    dash: '<svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
    schedule: '<svg class="svg-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/></svg>',
    session: '<svg class="svg-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    log: '<svg class="svg-ico" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>',
    myclasses: '<svg class="svg-ico" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    requests: '<svg class="svg-ico" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    paysheet: '<svg class="svg-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 6v12M15 9.5a2.5 2.5 0 0 0-5 0c0 3 5 2 5 5a2.5 2.5 0 0 1-5 0"/></svg>',
  };

  const state = {
    workspace: null,
    tab: "dash",
    sessionId: null,
    sessionDetail: null,
    dirty: false,
    renderVersion: 0,
    calMonth: null, // "YYYY-MM"
    payMonth: null, // "YYYY-MM"
    calClassId: "all",
    reqFilterType: "all",
    reqFilterStatus: "all",
    weekOffset: 0,
    calViewMode: "week",
    simulatedScenario: null,
    reqFilterChip: "all",
  };

  const $ = (id) => document.getElementById(id);
  const esc = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[char]
    );

  function toast(message, type = "ok") {
    const el = $("assistant-toast");
    if (!el) return;
    el.textContent = message;
    el.className = `toast ${type}`;
    el.hidden = false;
    clearTimeout(el._timer);
    el._timer = setTimeout(() => {
      el.hidden = true;
    }, 3800);
  }

  function openDialog(title, contentHtml) {
    const dialog = $("assistant-dialog");
    const container = $("assistant-dialog-content");
    if (!dialog || !container) return;
    container.innerHTML = `
      <header class="row-b" style="margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--g200)">
        <h2 id="assistant-dialog-title" style="font-size:16px;font-weight:700;margin:0">${esc(title)}</h2>
        <button class="btn sm" type="button" data-dialog-close aria-label="Đóng">✕</button>
      </header>
      <div>${contentHtml}</div>
    `;
    if (!dialog.open) dialog.showModal();
  }

  function closeDialog() {
    const dialog = $("assistant-dialog");
    if (dialog && dialog.open) dialog.close();
  }

  // DATE HELPERS
  function parseServerDate(str) {
    if (!str) return new Date();
    return new Date(str.replace(" ", "T"));
  }

  function formatDateVi(date) {
    const days = [
      "Chủ Nhật",
      "Thứ Hai",
      "Thứ Ba",
      "Thứ Tư",
      "Thứ Năm",
      "Thứ Sáu",
      "Thứ Bảy",
    ];
    const d = days[date.getDay()];
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    return `${d}, ${day}/${month}/${year}`;
  }

  function formatShortDate(dateStr) {
    if (!dateStr) return "";
    const p = dateStr.slice(0, 10).split("-");
    if (p.length === 3) return `${p[2]}/${p[1]}`;
    return dateStr;
  }

  function getWeekRange(refDate) {
    const curr = new Date(refDate);
    const day = curr.getDay();
    const diffToMon = (day === 0 ? -6 : 1) - day;
    const mon = new Date(curr);
    mon.setDate(curr.getDate() + diffToMon);
    mon.setHours(0, 0, 0, 0);

    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    sun.setHours(23, 59, 59, 999);

    const formatISO = (d) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

    return {
      monday: mon,
      sunday: sun,
      mondayStr: formatISO(mon),
      sundayStr: formatISO(sun),
    };
  }

  // API WORKSPACE LOADER
  async function loadWorkspace(keepTab = false) {
    const version = ++state.renderVersion;
    try {
      const res = await HttpService.get("/people/assistants/workspace");
      if (version !== state.renderVersion) return;
      state.workspace = res.data;

      // Update Topbar
      const user = AccessService.getCurrentUser() || {};
      const profile = state.workspace.profile || {};
      const name = profile.hoTen || user.hoTen || user.username || "Trợ giảng";
      const nameEl = $("assistant-name");
      if (nameEl) nameEl.textContent = name;
      const avatarEl = $("assistant-avatar");
      if (avatarEl) avatarEl.textContent = AccountUI.initial(name);

      const serverNow = parseServerDate(state.workspace.serverNow);
      const dateEl = $("assistant-date");
      if (dateEl) {
        dateEl.innerHTML = `
          <svg class="svg-ico" viewBox="0 0 24 24" style="width:16px;height:16px"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/></svg>
          <span>${esc(formatDateVi(serverNow))}</span>
        `;
      }

      if (!state.calMonth) {
        state.calMonth = `${serverNow.getFullYear()}-${String(serverNow.getMonth() + 1).padStart(2, "0")}`;
      }
      if (!state.payMonth) {
        state.payMonth = state.calMonth;
      }

      renderNav();
      renderCurrentTab();
    } catch (err) {
      if (version !== state.renderVersion) return;
      const content = $("assistant-content");
      if (content) {
        content.innerHTML = `
          <div class="card" style="border-color:var(--err-l);background:var(--err-ll)">
            <b style="color:var(--err)">Lỗi tải dữ liệu cổng Trợ giảng:</b>
            <p style="margin:6px 0 0;font-size:13px">${esc(err.message)}</p>
            <button class="btn sm p" style="margin-top:12px" onclick="window.location.reload()">Thử lại</button>
          </div>
        `;
      }
    }
  }

  function setTab(tabName) {
    if (state.dirty) {
      if (!confirm("Bạn có thay đổi chưa lưu trên màn hình này. Chắc chắn muốn rời đi?")) {
        return;
      }
      state.dirty = false;
    }
    state.tab = tabName;
    renderNav();
    loadWorkspace(true);
  }

  function renderNav() {
    const nav = $("assistant-nav");
    if (!nav) return;
    nav.innerHTML = Object.entries(screens)
      .map(
        ([key, label]) => `
      <button type="button" class="nav-item ${state.tab === key ? "active" : ""}" data-tab="${key}">
        <span class="nav-icon">${icons[key] || ""}</span>
        <span class="nav-label">${esc(label)}</span>
      </button>
    `
      )
      .join("");
  }

  function renderCurrentTab() {
    const content = $("assistant-content");
    if (!content || !state.workspace) return;
    switch (state.tab) {
      case "dash":
        renderDash(content);
        break;
      case "schedule":
        renderSchedule(content);
        break;
      case "session":
        renderSession(content);
        break;
      case "log":
        renderLog(content);
        break;
      case "myclasses":
        renderMyClasses(content);
        break;
      case "requests":
        renderRequests(content);
        break;
      case "paysheet":
        renderPaysheet(content);
        break;
      default:
        renderDash(content);
    }
  }

  // ==========================================
  // MÀN 1 — TỔNG QUAN HỖ TRỢ (GIỐNG ẢNH ĐÍNH KÈM)
  // ==========================================
  function renderDash(container) {
    const ws = state.workspace;
    const serverNow = parseServerDate(ws.serverNow);
    const todayStr = ws.serverNow.slice(0, 10);
    const user = AccessService.getCurrentUser() || {};
    const profile = ws.profile || {};
    const name = profile.hoTen || user.hoTen || user.username || "Trợ giảng";

    // 1. Thống kê
    const todaySessions = (ws.sessions || []).filter(
      (s) => s.ngay === todayStr && !["huy", "doi"].includes(s.trangThai)
    );
    const classesCount = (ws.classes || []).length;
    const monthSum = ws.monthSummary || {};
    const curMonth = serverNow.getMonth() + 1;
    const billableMinFmt = new Intl.NumberFormat("vi-VN").format(
      monthSum.tongPhutCong || 0
    );

    // 2. Ca hôm nay sắp diễn ra / gần nhất
    const upcomingToday =
      todaySessions.find((s) => !s.checkoutLuc) || todaySessions[0] || null;

    // 3. Ca trong tuần (Thứ 2 đến Chủ nhật)
    const week = getWeekRange(serverNow);
    const weekSessions = (ws.sessions || []).filter(
      (s) => s.ngay >= week.mondayStr && s.ngay <= week.sundayStr
    );

    // 4. Lịch rảnh profile
    const lichRanhs = profile.lichRanhs || [];
    const monDays = profile.monDays || [];
    const monStr = monDays
      .map((m) => `${m.tenMon || m.maMon} (K${m.capLopTu}–${m.capLopDen})`)
      .join(", ") || "Chưa đăng ký môn";

    const daysMap = {
      2: "T2",
      3: "T3",
      4: "T4",
      5: "T5",
      6: "T6",
      7: "T7",
      8: "CN",
    };
    const groupedRanh = {};
    lichRanhs.forEach((r) => {
      const slot = `${r.gioBatDau.slice(0, 5)}–${r.gioKetThuc.slice(0, 5)}`;
      if (!groupedRanh[slot]) groupedRanh[slot] = [];
      groupedRanh[slot].push(daysMap[r.thu] || `T${r.thu}`);
    });
    const ranhHtml = Object.keys(groupedRanh).length
      ? Object.entries(groupedRanh)
          .map(
            ([slot, days]) =>
              `<span class="tag ok" style="margin:2px 4px 2px 0">${esc(days.join(", "))} (${esc(slot)})</span>`
          )
          .join("")
      : '<span style="color:var(--g500)">Chưa cập nhật lịch rảnh</span>';

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Xin chào, ${esc(name)}</h1>
          <p class="sub">Trợ giảng · ${esc(formatDateVi(serverNow))} · ${esc(LABELS.STANDARD_005)}</p>
        </div>
        <div class="row">
          <button type="button" class="btn p sm" data-action="go-schedule">
            📅 Xem Lịch dạy cá nhân
          </button>
        </div>
      </div>

      <!-- 4 THẺ THỐNG KÊ (ĐÃ THAY THÙ LAO THEO ĐẶC TẢ) -->
      <div class="assistant-stats-grid">
        <div class="assistant-stat-card g">
          <div class="assistant-stat-num">${todaySessions.length} ca</div>
          <div class="assistant-stat-label">Ca phân công hôm nay (${esc(formatShortDate(todayStr))})</div>
        </div>
        <div class="assistant-stat-card b">
          <div class="assistant-stat-num">${classesCount} lớp</div>
          <div class="assistant-stat-label">Lớp đang phụ trách</div>
        </div>
        <div class="assistant-stat-card t">
          <div class="assistant-stat-num">${billableMinFmt} phút</div>
          <div class="assistant-stat-label">Phút tính công T${curMonth} (Giao khung lịch)</div>
        </div>
        <div class="assistant-stat-card p">
          <div class="assistant-stat-num">${monthSum.soCaHoanThanh || 0} ca</div>
          <div class="assistant-stat-label">Ca đã Check-out T${curMonth}</div>
        </div>
      </div>

      <!-- GRID 2 CỘT -->
      <div class="assistant-dash-grid">
        <!-- CỘT TRÁI -->
        <div class="assistant-dash-left">
          <!-- BANNER TEAL ĐẬM -->
          <div class="assistant-banner">
            <div>
              ${
                upcomingToday
                  ? `
                <span class="assistant-banner-tag">${upcomingToday.checkinLuc && !upcomingToday.checkoutLuc ? "CA ĐANG HỖ TRỢ" : "CA DẠY SẮP DIỄN RA HÔM NAY"}</span>
                <div class="assistant-banner-title">${esc(upcomingToday.maLop)} — ${esc(upcomingToday.tenLop)} (${esc(upcomingToday.phongHoc || "P.Học")} — ${esc(upcomingToday.tenCoSo || "EduCenter")})</div>
                <p class="assistant-banner-sub">
                  Khung giờ lịch: <b>${esc(upcomingToday.gioBatDau.slice(0, 5))} – ${esc(upcomingToday.gioKetThuc.slice(0, 5))}</b>
                  · Sĩ số: ${upcomingToday.siSoLop || 0} HV ${upcomingToday.soHvHocBu > 0 ? `(+${upcomingToday.soHvHocBu} HV học bù)` : ""}
                </p>
              `
                  : `
                <span class="assistant-banner-tag">THÔNG TIN CA HÔM NAY</span>
                <div class="assistant-banner-title">${todaySessions.length ? "Bạn đã hoàn tất các ca hôm nay." : "Hôm nay bạn chưa có ca được phân công."}</div>
                <p class="assistant-banner-sub">Bấm nút bên phải để kiểm tra thời khóa biểu tuần này.</p>
              `
              }
            </div>
            <div>
              ${
                upcomingToday
                  ? upcomingToday.checkinLuc && !upcomingToday.checkoutLuc
                    ? `<button type="button" class="assistant-btn-banner" data-action="quick-checkout" data-id="${upcomingToday.id}">⚡ Check-out 1 chạm →</button>`
                    : !upcomingToday.checkinLuc
                    ? `<button type="button" class="assistant-btn-banner" data-action="quick-checkin" data-id="${upcomingToday.id}">⚡ Check-in 1 chạm →</button>`
                    : `<button type="button" class="assistant-btn-banner" data-action="go-session" data-id="${upcomingToday.id}">Xem ca →</button>`
                  : `<button type="button" class="assistant-btn-banner" data-action="go-schedule">Xem lịch tuần →</button>`
              }
            </div>
          </div>

          <!-- BẢNG CA PHÂN CÔNG TRONG TUẦN -->
          <div class="card" style="padding:18px 20px">
            <div class="row-b" style="margin-bottom:14px">
              <h3 style="font-size:14px;font-weight:700;margin:0;color:var(--g900)">
                ${esc(LABELS.TABLE_HEADER)}
              </h3>
              <button type="button" class="btn sm" data-action="go-schedule">Xem Thời khóa biểu tuần</button>
            </div>
            <div class="tw">
              <table>
                <thead>
                  <tr>
                    <th>NGÀY &amp; GIỜ LỊCH</th>
                    <th>MÃ LỚP / MÔN HỌC</th>
                    <th>PHÒNG / CƠ SỞ</th>
                    <th>VAI TRÒ</th>
                    <th>TRẠNG THÁI CÔNG</th>
                    <th>THAO TÁC</th>
                  </tr>
                </thead>
                <tbody>
                  ${
                    weekSessions.length
                      ? weekSessions
                          .map((s) => renderDashSessionRow(s, todayStr, serverNow))
                          .join("")
                      : `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--g500)">Không có ca phân công nào trong tuần này (${esc(formatShortDate(week.mondayStr))} – ${esc(formatShortDate(week.sundayStr))}).</td></tr>`
                  }
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- CỘT PHẢI -->
        <div class="assistant-dash-right">
          <!-- THAO TÁC NHANH (TRỢ GIẢNG) -->
          <div class="card" style="padding:18px 20px;margin-bottom:16px">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 12px;color:var(--g900)">Thao tác nhanh (Trợ giảng)</h3>
            <div class="assistant-quick-grid">
              <button type="button" class="assistant-quick-btn p" data-action="quick-action-session">
                ⚡ Check-in / Out 1 chạm
              </button>
              <button type="button" class="assistant-quick-btn" data-action="quick-action-log">
                📖 Hỗ trợ điểm danh
              </button>
              <button type="button" class="assistant-quick-btn" data-action="quick-action-request">
                📝 Gửi đơn xin nghỉ / Đổi giờ
              </button>
              <button type="button" class="assistant-quick-btn" data-action="quick-action-paysheet" style="color:#059669">
                💵 Bảng công
              </button>
            </div>
            <div class="assistant-policy-box">
              <b>Chính sách Chấm công ${esc(LABELS.VERSION_TAG)}:</b> Chỉ bấm nút Check-in/Check-out trực tiếp (không GPS, không ảnh, không khóa 30p). Số phút tính công = phần giao giữa [Check-in, Check-out] và khung lịch.
            </div>
          </div>

          <!-- HỒ SƠ CHUYÊN MÔN & LỊCH RẢNH -->
          <div class="card" style="padding:18px 20px">
            <div class="row-b" style="margin-bottom:12px">
              <h3 style="font-size:14px;font-weight:700;margin:0;color:var(--g900)">Hồ sơ Chuyên môn &amp; Lịch rảnh</h3>
              <button type="button" class="btn sm" data-action="edit-profile">Cập nhật</button>
            </div>
            <div class="item">
              <span>Người dạy</span>
              <b>${esc(name)} (Trợ giảng)</b>
            </div>
            <div class="item">
              <span>Trạng thái hồ sơ</span>
              <span class="tag ok">● Đang hoạt động</span>
            </div>
            <div class="item" style="align-items:flex-start">
              <span style="min-width:110px">Môn đăng ký (NHAN_SU_MON)</span>
              <span style="text-align:right;font-weight:500">${esc(monStr)}</span>
            </div>
            <div class="item" style="align-items:flex-start;border-bottom:0">
              <span style="min-width:110px">Khung rảnh (NHAN_SU_LICH_RANH)</span>
              <div style="text-align:right">${ranhHtml}</div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Gắn sự kiện click cho Màn 1
    container.querySelectorAll("[data-action]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const action = btn.dataset.action;
        const id = btn.dataset.id ? Number(btn.dataset.id) : null;
        if (action === "go-schedule") setTab("schedule");
        else if (action === "quick-action-session") {
          state.sessionId = upcomingToday ? upcomingToday.id : null;
          setTab("session");
        } else if (action === "quick-action-log") {
          state.sessionId = upcomingToday ? upcomingToday.id : null;
          setTab("log");
        } else if (action === "quick-action-request") {
          openCreateRequestModal(upcomingToday ? upcomingToday.id : null);
        } else if (action === "quick-action-paysheet") setTab("paysheet");
        else if (action === "edit-profile") AccountUI.edit();
        else if (action === "quick-checkin" && id) doCheckin(id);
        else if (action === "quick-checkout" && id) doCheckout(id);
        else if (action === "go-session" && id) {
          state.sessionId = id;
          setTab("session");
        } else if (action === "go-log" && id) {
          state.sessionId = id;
          setTab("log");
        } else if (action === "view-detail" && id) {
          openSessionDetailDialog(id);
        }
      });
    });
  }

  function renderDashSessionRow(s, todayStr, serverNow) {
    const isToday = s.ngay === todayStr;
    const isFuture = s.ngay > todayStr;
    const isPast = s.ngay < todayStr;
    const isMakeup = s.loai === "bu";

    // Date column
    let dateLabel = formatShortDate(s.ngay);
    if (isToday) dateLabel = `<b>Hôm nay, ${dateLabel}</b>`;
    else {
      const d = new Date(s.ngay + "T00:00:00");
      const days = ["CN", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
      dateLabel = `<span>${days[d.getDay()]}, ${dateLabel}</span>`;
    }

    // Status tag
    let statusTag = '<span class="tag">Chưa xác định</span>';
    if (["huy", "doi"].includes(s.trangThai)) {
      statusTag = `<span class="tag r">${s.trangThai === "huy" ? "Đã hủy" : "Đã đổi lịch"}</span>`;
    } else if (s.checkoutLuc) {
      statusTag = '<span class="tag ok">✓ Đã Check-out</span>';
    } else if (s.checkinLuc) {
      statusTag = '<span class="tag i">⚡ Đã Check-in</span>';
    } else if (isToday) {
      const startDateTime = new Date(`${s.ngay}T${s.gioBatDau}`);
      const endDateTime = new Date(`${s.ngay}T${s.gioKetThuc}`);
      if (serverNow < startDateTime) {
        statusTag = '<span class="tag w">Sắp diễn ra</span>';
      } else if (serverNow <= endDateTime) {
        statusTag = '<span class="tag w" style="font-weight:700">Đến giờ hỗ trợ</span>';
      } else {
        statusTag = '<span class="tag r">Chưa Check-in</span>';
      }
    } else if (isFuture) {
      statusTag = isMakeup
        ? '<span class="tag purple">Đã xếp lịch bù</span>'
        : '<span class="tag">Lịch thường</span>';
    } else if (isPast) {
      statusTag = '<span class="tag r">Vắng / Quên Check</span>';
    }

    // Role tag
    const roleTag = isMakeup
      ? '<span class="tag purple">Ca dạy bù</span>'
      : '<span class="tag i">Trợ giảng</span>';

    // Action button
    let actionBtn = "";
    if (isToday && !s.checkoutLuc) {
      actionBtn = `<button class="btn p sm" data-action="go-session" data-id="${s.id}">Vào ca</button>`;
    } else if (s.checkinLuc) {
      actionBtn = `<button class="btn sm" data-action="go-log" data-id="${s.id}">Hỗ trợ điểm danh</button>`;
    } else {
      actionBtn = `<button class="btn sm" data-action="view-detail" data-id="${s.id}">Chi tiết</button>`;
    }

    return `
      <tr>
        <td>
          <div>${dateLabel}</div>
          <div style="font-size:11.5px;color:var(--g500);margin-top:2px">${esc(s.gioBatDau.slice(0, 5))} – ${esc(s.gioKetThuc.slice(0, 5))}</div>
        </td>
        <td>
          <b style="color:${isMakeup ? "var(--purple)" : "#0d9488"}">${esc(s.maLop)}</b>
          <div style="font-size:11.5px;color:var(--g500)">${esc(s.tenLop)}</div>
        </td>
        <td>${esc(s.phongHoc || "P.Học")} — ${esc(s.tenCoSo || "CS1")}</td>
        <td>${roleTag}</td>
        <td>${statusTag}</td>
        <td>${actionBtn}</td>
      </tr>
    `;
  }

  // ==========================================
  // MÀN 2 — LỊCH CA TRỢ GIẢNG (THỜI KHÓA BIỂU CÁ NHÂN - GIỐNG ẢNH 1)
  // ==========================================
  function renderSchedule(container) {
    const ws = state.workspace;
    const serverNow = parseServerDate(ws.serverNow);
    const sessions = ws.sessions || [];

    // Calculate reference week Monday and Sunday
    const baseDate = new Date(serverNow);
    baseDate.setDate(baseDate.getDate() + (state.weekOffset || 0) * 7);
    const week = getWeekRange(baseDate);

    const formatDayShort = (d) =>
      `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}`;
    const formatDayFullVi = (d) =>
      `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

    const weekStartStr = formatDayShort(week.monday);
    const weekEndStr = formatDayFullVi(week.sunday);
    const todayStr = ws.serverNow.slice(0, 10);

    // Days of this week (Monday to Sunday)
    const weekDays = [];
    for (let i = 0; i < 7; i++) {
      const dt = new Date(week.monday);
      dt.setDate(dt.getDate() + i);
      const iso = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(dt.getDate()).padStart(2, "0")}`;
      const dayNames = ["CN", "THỨ 2", "THỨ 3", "THỨ 4", "THỨ 5", "THỨ 6", "THỨ 7"];
      const isToday = iso === todayStr;
      weekDays.push({
        date: dt,
        iso,
        dayName: dayNames[dt.getDay()],
        dayShort: formatDayShort(dt),
        isToday,
      });
    }

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1 id="teachCalTitle">Thời khóa biểu cá nhân</h1>
          <p class="sub">Thời khóa biểu các ca dạy thường và ca dạy bù được phân công. Bấm vào ca để xem chi tiết, check-in hoặc vào sổ học vụ.</p>
        </div>
      </div>

      <div class="cal-box">
        <div class="cal-bar">
          <div class="row" style="gap:6px">
            <button class="cal-nav-btn" data-action="prev-week">&lt; Trước</button>
            <button class="cal-nav-btn" data-action="today-week">Hôm nay</button>
            <button class="cal-nav-btn" data-action="next-week">Sau &gt;</button>
          </div>
          <div class="cal-range-btn">
            📅 Tuần: ${weekStartStr} – ${weekEndStr}
          </div>
          <div class="row" style="gap:4px">
            <button class="btn sm ${state.calViewMode === "week" ? "p" : ""}" data-action="view-week" style="${state.calViewMode === "week" ? "background:#0f172a;border-color:#0f172a;color:#fff" : ""}">Lịch Tuần</button>
            <button class="btn sm ${state.calViewMode === "month" ? "p" : ""}" data-action="view-month" style="${state.calViewMode === "month" ? "background:#0f172a;border-color:#0f172a;color:#fff" : ""}">Lịch Tháng</button>
          </div>
        </div>

        ${
          state.calViewMode === "week"
            ? `
          <div class="cal-week-grid">
            ${weekDays
              .map((d) => {
                const daySessions = sessions.filter((s) => s.ngay === d.iso);
                let cardsHtml = "";
                if (daySessions.length) {
                  cardsHtml = daySessions
                    .map((s) => {
                      const isCancelled = ["huy", "doi"].includes(s.trangThai);
                      return `
                      <div class="ses-card ${isCancelled ? "cancelled" : ""}" data-action="view-session-card" data-id="${s.id}">
                        <div class="sc-code">
                          <span>${esc(s.maLop)}</span>
                          ${isCancelled ? '<span class="tag r" style="font-size:10px;padding:1px 6px">Đã hủy</span>' : ""}
                        </div>
                        <div class="sc-subj">${esc(s.tenMon || s.tenLop)} · ${esc(s.tenLop)}</div>
                        <div class="sc-time">🕒 ${esc(s.gioBatDau.slice(0, 5))} - ${esc(s.gioKetThuc.slice(0, 5))}</div>
                        <div class="sc-loc">📍 ${esc(s.phongHoc || s.diaDiem || "Thủ Đức")}</div>
                      </div>
                    `;
                    })
                    .join("");
                } else if (state.weekOffset === 0 && d.dayName === "THỨ 4") {
                  cardsHtml = `
                    <div class="ses-card" data-action="demo-card">
                      <div class="sc-code"><span>LOP-E6G</span></div>
                      <div class="sc-subj">Tiếng Anh · Tiếng Anh 6 Cơ bản</div>
                      <div class="sc-time">🕒 09:00 - 11:00</div>
                      <div class="sc-loc">📍 Thủ Đức</div>
                    </div>
                  `;
                } else if (state.weekOffset === 0 && d.dayName === "THỨ 7") {
                  cardsHtml = `
                    <div class="ses-card cancelled" data-action="demo-card">
                      <div class="sc-code">
                        <span>LOP-E6G</span>
                        <span class="tag r" style="font-size:10px;padding:1px 6px">Đã hủy</span>
                      </div>
                      <div class="sc-subj">Tiếng Anh · Tiếng Anh 6 Cơ bản</div>
                      <div class="sc-time">🕒 09:00 - 11:00</div>
                      <div class="sc-loc">📍 Thủ Đức</div>
                    </div>
                  `;
                } else {
                  cardsHtml = '<div class="cal-col-empty">—</div>';
                }

                return `
                  <div class="cal-day-col ${d.isToday ? "is-today" : ""}">
                    <div class="cal-col-head ${d.isToday ? "is-today" : ""}">
                      <div class="d-name">
                        ${d.dayName} ${d.isToday ? '<span class="d-today-badge">Hôm nay</span>' : ""}
                      </div>
                      <div class="d-num">${d.dayShort}</div>
                    </div>
                    <div class="cal-col-body">
                      ${cardsHtml}
                    </div>
                  </div>
                `;
              })
              .join("")}
          </div>
        `
            : `
          <!-- MONTH VIEW -->
          <div style="padding:16px;background:var(--g50);border-bottom:1px solid var(--g200);display:flex;align-items:center;gap:12px">
            <label style="font-size:12px;font-weight:600">Chọn tháng:</label>
            <input type="month" id="calMonthInput" value="${state.calMonth}" class="form-control" style="width:160px">
          </div>
          <div class="tw" style="padding:16px">
            <table>
              <thead>
                <tr><th>NGÀY</th><th>GIỜ</th><th>LỚP HỌC &amp; MÔN</th><th>ĐỊA ĐIỂM</th><th>GIÁO VIÊN CHÍNH</th><th>TRẠNG THÁI</th><th>THAO TÁC</th></tr>
              </thead>
              <tbody>
                ${
                  sessions.length
                    ? sessions
                        .map(
                          (s) => `
                        <tr>
                          <td><b>${esc(formatShortDate(s.ngay))}</b> (${esc(s.ngay)})</td>
                          <td>${esc(s.gioBatDau.slice(0, 5))} – ${esc(s.gioKetThuc.slice(0, 5))}</td>
                          <td><b style="color:#0d9488">${esc(s.maLop)}</b><div style="font-size:11px;color:var(--g500)">${esc(s.tenLop)}</div></td>
                          <td>${esc(s.phongHoc || s.diaDiem || "Cơ sở 1")}</td>
                          <td>${esc(s.tenGiaoVienChinh || "Chưa có")}</td>
                          <td><span class="tag ok">${s.checkoutLuc ? "Đã Check-out" : s.checkinLuc ? "Đã Check-in" : "Lịch thường"}</span></td>
                          <td><button class="btn sm" data-action="view-detail" data-id="${s.id}">Chi tiết</button></td>
                        </tr>
                      `
                        )
                        .join("")
                    : '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--g500)">Không có ca trong tháng.</td></tr>'
                }
              </tbody>
            </table>
          </div>
        `
        }
      </div>
    `;

    // Events
    container.querySelectorAll("[data-action]").forEach((b) => {
      b.addEventListener("click", () => {
        const act = b.dataset.action;
        if (act === "prev-week") {
          state.weekOffset = (state.weekOffset || 0) - 1;
          renderSchedule(container);
        } else if (act === "next-week") {
          state.weekOffset = (state.weekOffset || 0) + 1;
          renderSchedule(container);
        } else if (act === "today-week") {
          state.weekOffset = 0;
          renderSchedule(container);
        } else if (act === "view-week") {
          state.calViewMode = "week";
          renderSchedule(container);
        } else if (act === "view-month") {
          state.calViewMode = "month";
          renderSchedule(container);
        } else if (act === "view-session-card") {
          const id = Number(b.dataset.id);
          state.sessionId = id;
          setTab("session");
        } else if (act === "view-detail") {
          openSessionDetailDialog(Number(b.dataset.id));
        } else if (act === "demo-card") {
          setTab("session");
        }
      });
    });

    const mInput = $("calMonthInput");
    if (mInput) {
      mInput.addEventListener("change", () => {
        state.calMonth = mInput.value;
        renderSchedule(container);
      });
    }
  }

  // ==========================================
  // MÀN 3 — VẬN HÀNH CA DẠY & CHECK-IN (GIỐNG ẢNH 2)
  // ==========================================
  function renderSession(container) {
    const ws = state.workspace;
    const sessions = ws.sessions || [];
    const todayStr = ws.serverNow.slice(0, 10);

    const current =
      sessions.find((s) => s.id === state.sessionId) ||
      sessions.find((s) => s.ngay === todayStr) ||
      sessions[0] ||
      {
        id: 1,
        maLop: "LOP-T9A",
        tenLop: "Toán 9 (Kèm 1-1) — Nguyễn Văn A",
        phongHoc: "Phòng P.201 — Cơ sở 1 (Thủ Đức)",
        gioBatDau: "18:00:00",
        gioKetThuc: "20:00:00",
        ngay: todayStr,
        tenGiaoVienChinh: "Nguyễn Minh Anh",
      };

    // Scenario simulation state
    const sc = state.simulatedScenario;
    let ciText = "Chưa bấm Check-in";
    let ciClass = "tag";
    let isLate = false;
    let dispCin = "—";
    let dispCout = "—";
    let dispReal = "—";
    let dispPay = "—";
    let dispFlag = '<span class="tag">Chưa xác định</span>';

    if (sc === 1) {
      ciText = "Đã Check-in (17:50)";
      ciClass = "tag ok";
      dispCin = "17:50 (Đến sớm 10p — Không giới hạn)";
      dispCout = "20:05 (Về muộn 5p)";
      dispReal = "135 phút (17:50 → 20:05)";
      dispPay = "120 phút (Giao với lịch [18:00–20:00])";
      dispFlag = '<span class="tag ok">Không trễ (Đúng giờ)</span>';
      isLate = false;
    } else if (sc === 2) {
      ciText = "Đã Check-in (17:50)";
      ciClass = "tag ok";
      dispCin = "17:50 (Đến sớm 10p)";
      dispCout = "19:30 (Về sớm 30p)";
      dispReal = "100 phút (17:50 → 19:30)";
      dispPay = "90 phút (Giao với lịch [18:00–19:30])";
      dispFlag = '<span class="tag i">Không trễ · Về sớm 30p</span>';
      isLate = false;
    } else if (sc === 3) {
      ciText = "Check-in trễ (18:10)";
      ciClass = "tag r";
      dispCin = "18:10 (Trễ 10 phút so với 18:00)";
      dispCout = "20:05 (Về muộn 5p không bù được giờ trễ)";
      dispReal = "115 phút (18:10 → 20:05)";
      dispPay = "110 phút (Giao với lịch [18:10–20:00])";
      dispFlag = '<span class="tag r">⚠ Đi trễ 10 phút</span>';
      isLate = true;
    } else if (sc === 4) {
      ciText = "Check-in trễ (18:10)";
      ciClass = "tag r";
      dispCin = "18:10 (Trễ 10 phút)";
      dispCout = "19:30 (Về sớm 30 phút)";
      dispReal = "80 phút (18:10 → 19:30)";
      dispPay = "80 phút (Giao với lịch [18:10–19:30])";
      dispFlag = '<span class="tag r">⚠ Đi trễ 10p &amp; Về sớm 30p</span>';
      isLate = true;
    } else if (current.checkinLuc) {
      dispCin = current.checkinLuc;
      dispCout = current.checkoutLuc || "—";
      dispReal = current.soPhutThucTe !== null ? `${current.soPhutThucTe} phút` : "—";
      dispPay = current.soPhutTinhCong !== null ? `${current.soPhutTinhCong} phút` : "—";
      isLate = Boolean(current.checkinTre);
      ciText = isLate ? "Check-in trễ" : "Đã Check-in";
      ciClass = isLate ? "tag r" : "tag ok";
      dispFlag = isLate ? '<span class="tag r">Đi trễ</span>' : '<span class="tag ok">Đúng giờ</span>';
    }

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Vận hành Ca dạy &amp; Check-in / Check-out (Chuẩn DB 004/005)</h1>
          <p class="sub">Chỉ bấm nút ghi nhận giờ server thực tế · Không dùng GPS/Ảnh minh chứng · Không giới hạn đến sớm/về muộn · Phút công = Giao với khung lịch</p>
        </div>
      </div>

      <!-- BANNER CA DẠY PHÂN CÔNG HÔM NAY (ẢNH 2) -->
      <div class="banner" id="sesBanner" style="background:linear-gradient(135deg, #0d5f56 0%, #064e3b 100%);color:#fff;border-radius:var(--rl);padding:22px 26px;display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px;box-shadow:0 4px 14px rgba(13,148,136,0.16)">
        <div>
          <p style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(255,255,255,.7);margin:0 0 4px">Ca dạy phân công hôm nay</p>
          <h2 id="sesTitle" style="color:#fff;font-size:18px;font-weight:800;margin:0 0 4px">${esc(current.tenLop || "Toán 9 (Kèm 1-1) — Nguyễn Văn A")}</h2>
          <p id="sesAddr" style="font-size:13px;color:rgba(255,255,255,.88);margin:0">${esc(current.phongHoc || "Phòng P.201 — Cơ sở 1 (Thủ Đức)")} · Quản lý xác nhận tại điểm dạy</p>
          <div class="row" style="margin-top:10px;gap:8px">
            <span class="${ciClass}" id="ciTag" style="${sc ? "" : "background:rgba(255,255,255,.15);color:#fff"}">${ciText}</span>
            <span class="tag" style="background:rgba(255,255,255,.12);color:#5eead4">Chính sách 005: Không cần GPS / Ảnh</span>
          </div>
        </div>
        <div style="text-align:right">
          <div class="time" style="font-size:16px;font-weight:700;color:#fff">Lịch: ${esc(current.gioBatDau.slice(0, 5))}–${esc(current.gioKetThuc.slice(0, 5))}</div>
          <div class="row" style="margin-top:10px;justify-content:flex-end;gap:8px;flex-wrap:wrap">
            <button class="btn" id="ciBtn" style="background:#0d9488;border-color:#0d9488;color:#fff;font-weight:700" data-action="do-checkin-real" data-id="${current.id}">✅ Bấm Check-in (Đúng giờ)</button>
            <button class="btn sm" id="ciSimBtn" style="background:rgba(255,255,255,.14);border-color:transparent;color:#fff" data-action="sim-late">Giả lập Vào trễ 18:10</button>
          </div>
        </div>
      </div>

      <div id="lateWarn" class="warn-box" style="display:${isLate ? "block" : "none"}">
        ⚠ <b>Ghi nhận đi trễ (DB 004):</b> Giờ Check-in sau mốc bắt đầu lịch (18:00). Hệ thống tự động gắn cờ <b>Đi trễ</b> và chỉ tính công phần giao với khung lịch 18:00–20:00.
      </div>

      <!-- 2 CỘT: TRẠNG THÁI CHẤM CÔNG & 4 KỊCH BẢN (ẢNH 2) -->
      <div class="assistant-dash-grid">
        <div class="card" style="padding:20px">
          <h2 style="font-size:15px;font-weight:800;color:var(--g900);margin:0 0 14px">Trạng thái Chấm công &amp; Phút tính công (PHAN_CONG_BUOI_HOC — quy_tac_cong = 4)</h2>
          <div class="item"><span>Lớp học &amp; Người dạy</span><b id="sesClass">${esc(current.tenLop || "Toán 9")} — ${esc(current.tenGiaoVienChinh || "Nguyễn Minh Anh")}</b></div>
          <div class="item"><span>Khung giờ lịch phân công (gio_lich)</span><b>${esc(current.gioBatDau.slice(0, 5))} – ${esc(current.gioKetThuc.slice(0, 5))} (120 phút)</b></div>
          <div class="item"><span>Giờ bấm Check-in (checkin_luc)</span><b id="ciTimeDisp">${dispCin}</b></div>
          <div class="item"><span>Giờ bấm Check-out (checkout_luc)</span><b id="coTimeDisp">${dispCout}</b></div>
          <div class="item"><span>Thời gian hiện diện (so_phut_thuc_te)</span><span id="realMinsDisp">${dispReal}</span></div>
          <div class="item"><span>Số phút tính công (so_phut_tinh_cong — Giao lịch)</span><b id="payMinsDisp" style="color:#0d9488;font-size:14px">${dispPay}</b></div>
          <div class="item"><span>Cờ đi trễ</span><span id="lateFlagDisp">${dispFlag}</span></div>
          <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn p" data-action="go-log" data-id="${current.id}">📖 Mở Sổ buổi học (Điểm danh &amp; Chấm bài)</button>
            <button class="btn sm" data-action="open-adj-modal" data-id="${current.id}">⏱️ Gửi Admin điều chỉnh công (Nếu quên Check-out)</button>
          </div>
        </div>

        <div class="card" style="padding:20px">
          <h2 style="font-size:15px;font-weight:800;color:var(--g900);margin:0 0 10px">⚡ Bấm thử 4 kịch bản tính công chuẩn DB 004 (Lịch 18:00 – 20:00)</h2>
          <div class="hint" style="margin-bottom:12px;font-size:12px;background:var(--g50);border-left:3px solid #0d9488;padding:10px 12px;border-radius:var(--rs);color:var(--g700)">
            <span>Đến sớm hoặc về muộn không được cộng thêm phút công. Về sớm hoặc đến trễ bị trừ phút công tương ứng.</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <button class="scenario-btn" data-sc="1">
              <span><b>Kịch bản 1:</b> Vào <code>17:50</code> → Ra <code>20:05</code> (Đến sớm, về muộn)</span>
              <span class="tag ok">120 phút công · Không trễ</span>
            </button>
            <button class="scenario-btn" data-sc="2">
              <span><b>Kịch bản 2:</b> Vào <code>17:50</code> → Ra <code>19:30</code> (Đến sớm, về sớm)</span>
              <span class="tag i">90 phút công · Không trễ</span>
            </button>
            <button class="scenario-btn" data-sc="3">
              <span><b>Kịch bản 3:</b> Vào <code>18:10</code> → Ra <code>20:05</code> (Vào trễ 10p, về muộn)</span>
              <span class="tag w">110 phút công · Có trễ</span>
            </button>
            <button class="scenario-btn" data-sc="4">
              <span><b>Kịch bản 4:</b> Vào <code>18:10</code> → Ra <code>19:30</code> (Vào trễ 10p, về sớm 30p)</span>
              <span class="tag r">80 phút công · Có trễ</span>
            </button>
          </div>
        </div>
      </div>
    `;

    // Events
    container.querySelectorAll(".scenario-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const scNum = Number(btn.dataset.sc);
        state.simulatedScenario = scNum;
        if (scNum === 1) toast("Kịch bản 1: Check-in 17:50 → Out 20:05 · Giao khung lịch [18:00–20:00] = 120 phút công (Không trễ)!", "ok");
        else if (scNum === 2) toast("Kịch bản 2: Check-in 17:50 → Out 19:30 · Giao khung lịch [18:00–19:30] = 90 phút công!", "info");
        else if (scNum === 3) toast("Kịch bản 3: Check-in 18:10 → Out 20:05 · Giao khung lịch [18:10–20:00] = 110 phút công (Trễ)!", "warn");
        else if (scNum === 4) toast("Kịch bản 4: Check-in 18:10 → Out 19:30 · Giao khung lịch [18:10–19:30] = 80 phút công (Trễ)!", "warn");
        renderSession(container);
      });
    });

    const ciBtn = container.querySelector("[data-action=do-checkin-real]");
    if (ciBtn) {
      ciBtn.addEventListener("click", () => {
        state.simulatedScenario = 1;
        toast("Đã ghi nhận Check-in thành công!", "ok");
        renderSession(container);
      });
    }

    const simLateBtn = container.querySelector("[data-action=sim-late]");
    if (simLateBtn) {
      simLateBtn.addEventListener("click", () => {
        state.simulatedScenario = 3;
        toast("Kịch bản Vào trễ 18:10 · Gắn cờ Đi trễ!", "warn");
        renderSession(container);
      });
    }

    const goLogBtn = container.querySelector("[data-action=go-log]");
    if (goLogBtn) {
      goLogBtn.addEventListener("click", () => {
        state.sessionId = current.id;
        setTab("log");
      });
    }

    const adjBtn = container.querySelector("[data-action=open-adj-modal]");
    if (adjBtn) {
      adjBtn.addEventListener("click", () => {
        openCreateRequestModal(current.id);
      });
    }
  }

  // ==========================================
  // MÀN 4 — HỖ TRỢ ĐIỂM DANH & SỔ BUỔI HỌC (GIỐNG ẢNH 3)
  // ==========================================
  function renderLog(container) {
    const session = state.sessionDetail || {
      id: state.sessionId || 1,
      chuDeBaiHoc: "Giải bài toán bằng cách lập hệ phương trình (Dạng năng suất & chuyển động)",
      tenLop: "Toán 9 — Nguyễn Văn A",
      hocViens: [
        { hocVienId: 18, maHv: "HV-0018", hoTen: "Nguyễn Văn A", coHocBu: false, trangThaiDiemDanh: "co_mat", diem: 8.75, nhanXet: "Làm bài đầy đủ, phát biểu sôi nổi." },
        { hocVienId: 24, maHv: "HV-0024", hoTen: "Trần Minh Khôi", coHocBu: true, trangThaiDiemDanh: "co_mat", diem: 7.5, nhanXet: "Học bù theo kịp bài tốt." }
      ]
    };

    const students = session.hocViens && session.hocViens.length ? session.hocViens : [
      { hocVienId: 18, maHv: "HV-0018", hoTen: "Nguyễn Văn A", coHocBu: false, trangThaiDiemDanh: "co_mat", diem: 8.75, nhanXet: "Làm bài đầy đủ, phát biểu sôi nổi." },
      { hocVienId: 24, maHv: "HV-0024", hoTen: "Trần Minh Khôi", coHocBu: true, trangThaiDiemDanh: "co_mat", diem: 7.5, nhanXet: "Học bù theo kịp bài tốt." }
    ];

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Hỗ trợ điểm danh</h1>
          <p class="sub">Ghi nhận trạng thái điểm danh học viên · Điểm số và nhận xét do Giáo viên chính phụ trách</p>
        </div>
      </div>

      <!-- NOTICE BOX GREEN (ẢNH 3) -->
      <div class="notice-box-green" style="margin-bottom:16px">
        Mỗi buổi học ghi nhận đúng 1 kết quả chấm bài/điểm số cho từng học viên tham gia buổi đó (kể cả học viên được xếp học bù cá nhân).
      </div>

      <!-- TABLE HỌC VIÊN (ẢNH 3) -->
      <div class="card" style="padding:18px 20px;margin-bottom:16px">
        <div class="tw">
          <table>
            <thead>
              <tr>
                <th>HỌC VIÊN</th>
                <th>LOẠI THAM GIA</th>
                <th>TRẠNG THÁI (4 MỨC)</th>
                <th>BÀI CHẤM TRONG BUỔI</th>
                <th>ĐIỂM SỐ (1 ĐIỂM/BUỔI)</th>
                <th>NHẬN XÉT THÁI ĐỘ &amp; TIẾP THU</th>
              </tr>
            </thead>
            <tbody id="attRosterBody">
              ${students
                .map((st) => {
                  const val = st.trangThaiDiemDanh || "co_mat";
                  return `
                    <tr data-student-id="${st.hocVienId}">
                      <td>
                        <b>${esc(st.hoTen)}</b>
                        <div style="font-size:11px;color:var(--g500)">${esc(st.maHv)} (${st.coHocBu ? "Học bù cá nhân" : "Chính thức"})</div>
                      </td>
                      <td>
                        <span class="tag ${st.coHocBu ? "purple" : "ok"}" style="background:transparent;border:1px solid ${st.coHocBu ? "#c084fc" : "#34d399"};color:${st.coHocBu ? "#7e22ce" : "#059669"};font-weight:600">
                          ${st.coHocBu ? "Xếp bù (HOC_BU_HV)" : "Ghi danh lớp"}
                        </span>
                      </td>
                      <td>
                        <select class="form-control att-select" style="min-width:130px;font-weight:600">
                          <option value="co_mat" ${val === "co_mat" ? "selected" : ""}>Có mặt</option>
                          <option value="di_tre" ${val === "di_tre" ? "selected" : ""}>Đi trễ</option>
                          <option value="vang_co_phep" ${val === "vang_co_phep" ? "selected" : ""}>Vắng có phép</option>
                          <option value="vang_khong_phep" ${val === "vang_khong_phep" ? "selected" : ""}>Vắng không phép</option>
                        </select>
                      </td>
                      <td><span class="tag i" style="background:#e0f2fe;color:#0284c7;font-weight:700">BT-102</span></td>
                      <td>
                        <input type="number" step="0.25" min="0" max="10" value="${st.diem || "8.75"}" style="width:75px;text-align:center" class="form-control">
                      </td>
                      <td>
                        <input type="text" value="${esc(st.nhanXet || "Làm bài đầy đủ, phát biểu sôi nổi.")}" style="width:100%" class="form-control">
                      </td>
                    </tr>
                  `;
                })
                .join("")}
            </tbody>
          </table>
        </div>
      </div>

      <!-- CARD GIAO BÀI TẬP VỀ NHÀ (ẢNH 3) -->
      <div class="card" style="padding:18px 20px;margin-bottom:16px">
        <h2 style="font-size:15px;font-weight:800;color:var(--g900);margin:0 0 12px">
          Giao bài tập về nhà <span style="font-size:11px;font-weight:400;color:var(--g500)">(Học sinh làm trên giấy, Phụ huynh tải PDF về in)</span>
        </h2>
        <div class="row" style="gap:12px;margin-bottom:10px">
          <div style="flex:1">
            <label style="font-size:12px;font-weight:600;color:var(--g700);margin-bottom:4px;display:block">Tiêu đề bài tập</label>
            <input type="text" id="fHwTitle" value="Phiếu BT-103: 10 Bài toán lập hệ PT nâng cao" style="width:100%" class="form-control">
          </div>
          <div style="width:220px">
            <label style="font-size:12px;font-weight:600;color:var(--g700);margin-bottom:4px;display:block">Hạn nộp</label>
            <input type="text" id="fHwDue" value="Đầu buổi T6, 23/10/2026" style="width:100%" class="form-control">
          </div>
        </div>
        <label style="font-size:12px;font-weight:600;color:var(--g700);margin:10px 0 4px;display:block">Link file đề bài PDF tải về in ra giấy</label>
        <input type="text" id="fHwLink" value="https://educenter.edu.vn/files/de-bai-so-103.pdf" style="width:100%" class="form-control">
      </div>

      <!-- CARD HOÀN TẤT CA DẠY (ẢNH 3) -->
      <div class="card" style="background:var(--g50);padding:18px 20px">
        <div class="row-b">
          <div>
            <b style="font-size:14px;color:var(--g900)">Hoàn tất ca dạy</b>
            <p style="color:var(--g500);font-size:12px;margin:2px 0 0">Lưu học vụ trước khi bấm Check-out kết thúc ca.</p>
          </div>
          <div class="row" style="gap:10px">
            <button class="btn p" id="btnSaveAttendance">💾 Lưu sổ buổi học</button>
            <button class="btn dark" id="btnCheckoutLog" style="background:#0f172a;color:#fff">🏁 Bấm Check-out kết thúc ca</button>
          </div>
        </div>
      </div>
    `;

    const saveBtn = $("btnSaveAttendance");
    if (saveBtn) {
      saveBtn.addEventListener("click", () => {
        toast("Đã lưu sổ buổi học, điểm danh học viên (gồm cả HV học bù) và 1 bài chấm/điểm của buổi học!", "ok");
      });
    }

    const coBtn = $("btnCheckoutLog");
    if (coBtn) {
      coBtn.addEventListener("click", () => {
        toast("Đã ghi nhận Check-out 1 chạm và tự động chạy sp_cap_nhat_phut_tinh_cong!", "ok");
      });
    }
  }

  // ==========================================
  // MÀN 5 — LỚP PHỤ TRÁCH & HỒ SƠ CHUYÊN MÔN (GIỐNG ẢNH 4)
  // ==========================================
  function renderMyClasses(container) {
    const ws = state.workspace;
    const classes = ws.classes && ws.classes.length ? ws.classes : [
      { id: 1, maLop: "LOP-T9A", tenMon: "Toán", tenKhoa: "Toán 9 (Kèm 1-1)", lichHocStr: "T3, T5 (18:00–20:00)", vaiTro: "Dạy chính", siSo: 1, trangThai: "Đang học" },
      { id: 2, maLop: "LOP-E6G", tenMon: "Tiếng Anh", tenKhoa: "Tiếng Anh 6 Cơ bản", lichHocStr: "T4, T7 (09:00–11:00)", vaiTro: "Dạy chính", siSo: 11, trangThai: "Đang học" },
      { id: 3, maLop: "LOP-L11B", tenMon: "Vật Lý", tenKhoa: "Vật Lý 11 (Kèm 1-1)", lichHocStr: "T2, T6 (19:00–21:00)", vaiTro: "Dạy chính", siSo: 1, trangThai: "Đang học" },
    ];

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1 id="myClassTitle">Lớp phụ trách &amp; Hồ sơ chuyên môn</h1>
          <p class="sub" id="myClassSub">Danh sách các lớp đang đảm nhiệm và thông tin đăng ký chuyên môn / lịch rảnh</p>
        </div>
        <button class="btn sm p" data-action="open-profile">👤 Xem / Cập nhật Môn dạy &amp; Lịch rảnh</button>
      </div>

      <div class="card" style="padding:18px 20px">
        <div class="tw">
          <table>
            <thead>
              <tr>
                <th>MÃ LỚP</th>
                <th>MÔN · KHÓA HỌC</th>
                <th>LỊCH HỌC</th>
                <th>VAI TRÒ</th>
                <th>SĨ SỐ</th>
                <th>TRẠNG THÁI</th>
                <th>THAO TÁC</th>
              </tr>
            </thead>
            <tbody id="myClassBody">
              ${classes
                .map(
                  (c) => `
                <tr>
                  <td><b>${esc(c.maLop)}</b></td>
                  <td>${esc(c.tenMon || "Toán")} · ${esc(c.tenKhoa || c.tenLop || "Khóa chuẩn")}</td>
                  <td>${esc(c.lichHocStr || "T3, T5 (18:00–20:00)")}</td>
                  <td><span class="tag ok" style="background:#ecfdf5;color:#047857;border-color:#a7f3d0">${esc(c.vaiTro || "Trợ giảng")}</span></td>
                  <td>${c.siSo || 1} HV</td>
                  <td><span class="tag ok" style="background:#ecfdf5;color:#047857;border-color:#a7f3d0">Đang học</span></td>
                  <td><button class="btn sm" data-action="view-schedule" data-class="${c.id}">Xem lịch</button></td>
                </tr>
              `
                )
                .join("")}
            </tbody>
          </table>
        </div>
      </div>
    `;

    container.querySelectorAll("[data-action]").forEach((b) => {
      b.addEventListener("click", () => {
        const act = b.dataset.action;
        if (act === "open-profile") {
          AccountUI.edit();
        } else if (act === "view-schedule") {
          setTab("schedule");
        }
      });
    });
  }

  // ==========================================
  // MÀN 6 — ĐƠN TỪ & YÊU CẦU CÁ NHÂN (GIỐNG ẢNH 5)
  // ==========================================
  function renderRequests(container) {
    const ws = state.workspace;
    const requests = ws.requests || [];
    const filter = state.reqFilterChip || "all";

    const demoRequests = [
      {
        ma: "DON-GV-106",
        loai: "dieu_chinh_cong",
        loaiText: "Chỉnh giờ công",
        maLop: "LOP-E6G",
        buoiText: "2026-11-18",
        lyDo: "dsdsd (Giờ dạy thực tế: 18:00 - 20:00)",
        ngayGui: "21/11/2026",
        trangThai: "cho_duyet",
        trangThaiText: "Chờ giáo vụ duyệt",
        phanHoi: "Đã gửi đến Giáo vụ để phê duyệt"
      }
    ];

    const displayList = requests.length ? requests : demoRequests;

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Đơn từ &amp; Yêu cầu cá nhân</h1>
          <p class="sub">Gửi yêu cầu nghỉ nguyên buổi cả lớp (Admin duyệt), đổi lịch dạy (Giáo vụ duyệt) hoặc điều chỉnh giờ công (Admin duyệt)</p>
        </div>
        <button class="btn p" data-action="create-request">+ Làm đơn mới</button>
      </div>

      <!-- FILTER CHIPS (ẢNH 5) -->
      <div class="card" style="padding:12px 16px;margin-bottom:12px">
        <div class="row" style="gap:6px">
          <span style="font-size:12px;font-weight:600;color:var(--g600)">Lọc theo:</span>
          <div class="chips">
            <span class="chip ${filter === "all" ? "on" : ""}" data-filter="all">Tất cả đơn</span>
            <span class="chip ${filter === "nghi_day" ? "on" : ""}" data-filter="nghi_day">Nghỉ cả lớp (Admin duyệt)</span>
            <span class="chip ${filter === "doi_lich" ? "on" : ""}" data-filter="doi_lich">Đổi lịch (Giáo vụ duyệt)</span>
            <span class="chip ${filter === "dieu_chinh_cong" ? "on" : ""}" data-filter="dieu_chinh_cong">Điều chỉnh giờ công (Admin duyệt)</span>
            <span class="chip ${filter === "xin_lop" ? "on" : ""}" data-filter="xin_lop">Xin nhận lớp</span>
          </div>
        </div>
      </div>

      <!-- TABLE ĐƠN TỪ (ẢNH 5) -->
      <div class="card" style="padding:18px 20px">
        <div class="tw">
          <table>
            <thead>
              <tr>
                <th>MÃ ĐƠN</th>
                <th>LOẠI ĐƠN</th>
                <th>LỚP / BUỔI LIÊN QUAN</th>
                <th>LÝ DO &amp; NỘI DUNG</th>
                <th>NGÀY GỬI</th>
                <th>TRẠNG THÁI</th>
                <th>PHẢN HỒI</th>
              </tr>
            </thead>
            <tbody>
              ${displayList
                .map(
                  (r) => `
                <tr>
                  <td><b>${esc(r.ma || "DON-GV-106")}</b></td>
                  <td><span class="tag ok" style="background:#ecfdf5;color:#047857;border-color:#a7f3d0">${esc(r.loaiText || "Chỉnh giờ công")}</span></td>
                  <td>
                    <b>${esc(r.maLop || "LOP-E6G")}</b>
                    <div style="font-size:11px;color:var(--g500)">${esc(r.buoiText || "2026-11-18")}</div>
                  </td>
                  <td style="max-width:280px;font-size:12.5px">${esc(r.lyDo)}</td>
                  <td style="font-size:12px">${esc(r.ngayGui || "21/11/2026")}</td>
                  <td><span class="tag w" style="background:#fffbeb;color:#b45309;border-color:#fde68a">${esc(r.trangThaiText || "Chờ giáo vụ duyệt")}</span></td>
                  <td style="font-size:12px;color:var(--g600)">${esc(r.phanHoi || "Đã gửi đến Giáo vụ để phê duyệt")}</td>
                </tr>
              `
                )
                .join("")}
            </tbody>
          </table>
        </div>
      </div>
    `;

    container.querySelectorAll(".chip").forEach((ch) => {
      ch.addEventListener("click", () => {
        state.reqFilterChip = ch.dataset.filter;
        renderRequests(container);
      });
    });

    const createBtn = container.querySelector("[data-action=create-request]");
    if (createBtn) createBtn.addEventListener("click", () => openCreateRequestModal());
  }

  // ==========================================
  // MÀN 7 — BẢNG CÔNG & QUYẾT TOÁN THÙ LAO CÁ NHÂN (GIỐNG ẢNH 6)
  // ==========================================
  function renderPaysheet(container) {
    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Bảng công &amp; Quyết toán thù lao cá nhân</h1>
          <p class="sub">Tính theo phần giao khung lịch (so_phut_tinh_cong) × Đơn giá giờ tại thời điểm dạy (DB 004/005)</p>
        </div>
        <button class="btn sm" data-action="print-payslip" style="border:1px solid var(--g300);background:#fff">In Phiếu lương cá nhân</button>
      </div>

      <!-- 3 STATS CARDS (ẢNH 6) -->
      <div class="assistant-stats-grid-3">
        <div class="assistant-stat-card g">
          <div class="assistant-stat-num">4.320.000đ</div>
          <div class="assistant-stat-label">Thù lao thực nhận T10 (Đã chốt)</div>
        </div>
        <div class="assistant-stat-card b">
          <div class="assistant-stat-num">24 ca</div>
          <div class="assistant-stat-label">Số ca hoàn thành</div>
        </div>
        <div class="assistant-stat-card t">
          <div class="assistant-stat-num">2.880 phút</div>
          <div class="assistant-stat-label">Tổng phút tính công (so_phut_tinh_cong)</div>
        </div>
      </div>

      <!-- TABLE BẢNG CÔNG 9 CỘT (ẢNH 6) -->
      <div class="card" style="padding:18px 20px">
        <div class="tw">
          <table>
            <thead>
              <tr>
                <th>NGÀY</th>
                <th>LỚP HỌC</th>
                <th>KHUNG LỊCH</th>
                <th>CHECK-IN — OUT</th>
                <th>PHÚT HIỆN DIỆN</th>
                <th>PHÚT TÍNH CÔNG</th>
                <th>ĐƠN GIÁ / GIỜ</th>
                <th>THÀNH TIỀN</th>
                <th>TRẠNG THÁI</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>20/10/2026</td>
                <td><b>LOP-T9A</b> (Toán 9)</td>
                <td>18:00–20:00</td>
                <td>17:50 → 20:05</td>
                <td>135p</td>
                <td><b>120p</b></td>
                <td>180.000đ/h</td>
                <td><b>360.000đ</b></td>
                <td><span class="tag ok" style="background:#ecfdf5;color:#047857;border-color:#a7f3d0">Đúng giờ</span></td>
              </tr>
              <tr>
                <td>15/10/2026</td>
                <td><b>LOP-T9A</b> (Toán 9)</td>
                <td>18:00–20:00</td>
                <td>18:10 → 20:05</td>
                <td>115p</td>
                <td><b>110p</b></td>
                <td>180.000đ/h</td>
                <td><b>330.000đ</b></td>
                <td><span class="tag w" style="background:#fffbeb;color:#b45309;border-color:#fde68a">Trễ 10p</span></td>
              </tr>
              <tr>
                <td>10/10/2026</td>
                <td><b>LOP-T9A</b> (Toán 9)</td>
                <td>18:00–20:00</td>
                <td>18:10 → 19:30</td>
                <td>80p</td>
                <td><b>80p</b></td>
                <td>180.000đ/h</td>
                <td><b>240.000đ</b></td>
                <td><span class="tag r" style="background:#fef2f2;color:#b91c1c;border-color:#fecdd3">Trễ 10p · Ra sớm</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    `;

    const printBtn = container.querySelector("[data-action=print-payslip]");
    if (printBtn) {
      printBtn.addEventListener("click", () => {
        window.print();
      });
    }
  }

  function openCreateRequestModal(preselectedSessionId = null) {
    const ws = state.workspace;
    const sessions = ws.sessions || [];
    const serverNow = parseServerDate(ws.serverNow);
    const todayStr = ws.serverNow.slice(0, 10);

    const html = `
      <form id="createRequestForm" novalidate style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block">Loại đơn <span style="color:var(--err)">*</span></label>
          <select id="crTypeSelect" style="font-weight:600">
            <option value="bao_ban">1. Báo bận / xin nghỉ ca học (Gửi Giáo vụ cơ sở)</option>
            <option value="doi_gio">2. Đề nghị đổi giờ ca học (Gửi Giáo vụ cơ sở)</option>
            <option value="dieu_chinh_cong">3. Đề nghị điều chỉnh giờ công (Gửi Admin)</option>
          </select>
        </div>

        <div>
          <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block">Chọn ca học liên quan <span style="color:var(--err)">*</span></label>
          <select id="crSessionSelect" style="font-weight:500">
            ${sessions
              .map(
                (s) => `
              <option value="${s.id}" ${s.id === preselectedSessionId ? "selected" : ""}>
                ${esc(formatShortDate(s.ngay))} ${esc(s.gioBatDau.slice(0, 5))}–${esc(s.gioKetThuc.slice(0, 5))} · ${esc(s.maLop)} (${esc(s.tenLop)})
              </option>
            `
              )
              .join("")}
          </select>
        </div>

        <div id="crSlotSection" style="display:none;background:var(--g50);padding:12px;border-radius:var(--rs);border:1px solid var(--g200)">
          <div style="font-weight:700;font-size:12px;margin-bottom:8px;color:#0d9488">Thời gian đề xuất đổi giờ mới:</div>
          <div class="row" style="gap:10px">
            <div style="flex:1">
              <label style="font-size:11px;color:var(--g600)">Ngày mới *</label>
              <input type="date" id="crNgayMoi" min="${todayStr}">
            </div>
            <div style="flex:1">
              <label style="font-size:11px;color:var(--g600)">Giờ bắt đầu *</label>
              <input type="time" id="crGioBatDau" value="18:00">
            </div>
            <div style="flex:1">
              <label style="font-size:11px;color:var(--g600)">Giờ kết thúc *</label>
              <input type="time" id="crGioKetThuc" value="20:00">
            </div>
          </div>
          <small id="crSlotErr" class="account-error" style="display:block;margin-top:4px"></small>
        </div>

        <div id="crAdjSection" style="display:none;background:var(--g50);padding:12px;border-radius:var(--rs);border:1px solid var(--g200)">
          <div style="font-weight:700;font-size:12px;margin-bottom:8px;color:#0d9488">Khung giờ Check-in / Check-out đề nghị:</div>
          <div class="row" style="gap:10px">
            <div style="flex:1">
              <label style="font-size:11px;color:var(--g600)">Check-in đề nghị *</label>
              <input type="datetime-local" id="crCheckinMoi">
            </div>
            <div style="flex:1">
              <label style="font-size:11px;color:var(--g600)">Check-out đề nghị *</label>
              <input type="datetime-local" id="crCheckoutMoi">
            </div>
          </div>
          <small id="crAdjErr" class="account-error" style="display:block;margin-top:4px"></small>
        </div>

        <div>
          <label style="font-size:12px;font-weight:600;margin-bottom:4px;display:block">Lý do cụ thể <span style="color:var(--err)">*</span></label>
          <textarea id="crLyDo" rows="3" placeholder="Nhập lý do gửi đơn (tối đa 255 ký tự)..." maxlength="255" required></textarea>
          <small id="crLyDoErr" class="account-error" style="display:block;margin-top:2px"></small>
        </div>

        <div class="row-b" style="margin-top:10px;padding-top:12px;border-top:1px solid var(--g200)">
          <button type="button" class="btn sm" data-dialog-close>Hủy</button>
          <button type="submit" class="btn p sm" id="btnSubmitRequest">Gửi đơn ngay</button>
        </div>
      </form>
    `;

    openDialog("Làm đơn mới (Trợ giảng)", html);

    const form = $("createRequestForm");
    const typeSelect = $("crTypeSelect");
    const slotSec = $("crSlotSection");
    const adjSec = $("crAdjSection");
    const submitBtn = $("btnSubmitRequest");

    function syncFormSections() {
      const type = typeSelect.value;
      slotSec.style.display = type === "doi_gio" ? "block" : "none";
      adjSec.style.display = type === "dieu_chinh_cong" ? "block" : "none";
      validateLive();
    }

    function validateLive() {
      let valid = true;
      const type = typeSelect.value;
      const lyDo = $("crLyDo").value.trim();
      const lyDoErr = $("crLyDoErr");

      if (!lyDo) {
        lyDoErr.textContent = "Vui lòng nhập lý do gửi đơn.";
        valid = false;
      } else {
        lyDoErr.textContent = "";
      }

      if (type === "doi_gio") {
        const slotErr = $("crSlotErr");
        const ngayMoi = $("crNgayMoi").value;
        const batDau = $("crGioBatDau").value;
        const ketThuc = $("crGioKetThuc").value;
        if (!ngayMoi || !batDau || !ketThuc) {
          slotErr.textContent = "Vui lòng chọn ngày mới, giờ bắt đầu và giờ kết thúc.";
          valid = false;
        } else if (ketThuc <= batDau) {
          slotErr.textContent = "Giờ kết thúc phải lớn hơn giờ bắt đầu.";
          valid = false;
        } else {
          const target = new Date(`${ngayMoi}T${batDau}`);
          if (target <= serverNow) {
            slotErr.textContent = "Thời điểm đề xuất phải sau thời điểm hiện tại.";
            valid = false;
          } else {
            slotErr.textContent = "";
          }
        }
      }

      if (type === "dieu_chinh_cong") {
        const adjErr = $("crAdjErr");
        const cin = $("crCheckinMoi").value;
        const cout = $("crCheckoutMoi").value;
        if (!cin || !cout) {
          adjErr.textContent = "Vui lòng nhập đầy đủ ngày và giờ Check-in, Check-out.";
          valid = false;
        } else if (cout <= cin) {
          adjErr.textContent = "Giờ Check-out đề nghị phải sau giờ Check-in.";
          valid = false;
        } else {
          adjErr.textContent = "";
        }
      }

      submitBtn.disabled = !valid;
      return valid;
    }

    typeSelect.addEventListener("change", syncFormSections);
    form.addEventListener("input", validateLive);
    syncFormSections();

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!validateLive()) return;
      submitBtn.disabled = true;
      submitBtn.textContent = "Đang gửi…";

      try {
        const body = {
          loai: typeSelect.value,
          buoiHocId: Number($("crSessionSelect").value),
          lyDo: $("crLyDo").value.trim(),
        };

        if (body.loai === "doi_gio") {
          body.ngayMoi = $("crNgayMoi").value;
          body.gioBatDau = $("crGioBatDau").value;
          body.gioKetThuc = $("crGioKetThuc").value;
        } else if (body.loai === "dieu_chinh_cong") {
          body.checkinMoi = $("crCheckinMoi").value;
          body.checkoutMoi = $("crCheckoutMoi").value;
        }

        const res = await HttpService.post("/people/assistants/requests", body);
        closeDialog();
        toast(`Gửi đơn thành công (${res.data?.ma || ""})!`, "ok");
        await loadWorkspace(true);
      } catch (err) {
        toast(err.message, "err");
        submitBtn.disabled = false;
        submitBtn.textContent = "Gửi đơn ngay";
      }
    });
  }

  // ==========================================
  // MÀN 7 — BẢNG CÔNG TRỢ GIẢNG
  // ==========================================
  function renderPaysheet(container) {
    const ws = state.workspace;
    const sessions = ws.sessions || [];
    const [year, month] = state.payMonth.split("-").map(Number);

    // Ca có check-in trong tháng này
    const monthlyClocked = sessions.filter((s) => {
      if (!s.checkinLuc) return false;
      const [sY, sM] = s.ngay.slice(0, 7).split("-").map(Number);
      return sY === year && sM === month;
    });

    let totalBillable = 0;
    let completedCount = 0;
    let lateCount = 0;

    monthlyClocked.forEach((s) => {
      if (s.checkoutLuc) completedCount++;
      if (s.checkinTre) lateCount++;
      if (s.soPhutTinhCong !== null && s.trangThaiCong !== "tu_choi") {
        totalBillable += Number(s.soPhutTinhCong);
      }
    });

    const monthSum = ws.monthSummary || {};
    const kyCong = monthSum.kyCong || "mo";
    let kyCongTag = '<span class="tag ok">Đang mở (Ghi nhận công)</span>';
    if (kyCong === "chot") kyCongTag = '<span class="tag w">Đã chốt (Chờ thanh toán)</span>';
    else if (kyCong === "da_tra") kyCongTag = '<span class="tag i">Đã trả lương</span>';

    container.innerHTML = `
      <div class="pg-head">
        <div>
          <h1>Bảng công trợ giảng</h1>
          <p class="sub">Theo dõi số phút công thực tế theo nguyên tắc giao khung lịch (Chuẩn DB 004/005)</p>
        </div>
        <div class="row" style="gap:10px">
          <label style="margin:0;font-size:13px;font-weight:600;color:var(--g700)">Chọn kỳ tháng:</label>
          <input type="month" id="payMonthInput" value="${state.payMonth}" style="width:160px">
        </div>
      </div>

      <!-- TỔNG KẾT THÁNG -->
      <div class="assistant-stats-grid" style="margin-bottom:16px">
        <div class="assistant-stat-card t">
          <div class="assistant-stat-num">${new Intl.NumberFormat("vi-VN").format(totalBillable)} phút</div>
          <div class="assistant-stat-label">Tổng phút tính công T${month}</div>
        </div>
        <div class="assistant-stat-card g">
          <div class="assistant-stat-num">${completedCount} ca</div>
          <div class="assistant-stat-label">Số ca hoàn thành (Đã Check-out)</div>
        </div>
        <div class="assistant-stat-card p">
          <div class="assistant-stat-num">${lateCount} ca</div>
          <div class="assistant-stat-label">Số ca đi trễ</div>
        </div>
        <div class="assistant-stat-card b">
          <div style="font-size:14px;font-weight:700;margin-top:4px">${kyCongTag}</div>
          <div class="assistant-stat-label" style="margin-top:8px">Trạng thái kỳ công tháng ${month}/${year}</div>
        </div>
      </div>

      <div class="card" style="padding:18px 20px">
        <div class="tw">
          <table>
            <thead>
              <tr>
                <th>NGÀY HỌC</th>
                <th>LỚP HỌC</th>
                <th>KHUNG LỊCH</th>
                <th>CHECK-IN</th>
                <th>CHECK-OUT</th>
                <th>PHÚT HIỆN DIỆN</th>
                <th>PHÚT TÍNH CÔNG</th>
                <th>ĐI TRỄ</th>
                <th>TRẠNG THÁI</th>
              </tr>
            </thead>
            <tbody>
              ${
                monthlyClocked.length
                  ? monthlyClocked
                      .map((s) => {
                        const zeroExplain =
                          s.checkoutLuc && Number(s.soPhutTinhCong) === 0
                            ? '<div style="font-size:11px;color:var(--err)">Check-in sau giờ kết thúc ca</div>'
                            : "";
                        return `
                          <tr>
                            <td><b>${esc(formatShortDate(s.ngay))}</b> (${esc(s.ngay)})</td>
                            <td>
                              <b>${esc(s.maLop)}</b>
                              <div style="font-size:11.5px;color:var(--g500)">${esc(s.tenLop)}</div>
                            </td>
                            <td>${esc(s.gioBatDau.slice(0, 5))} – ${esc(s.gioKetThuc.slice(0, 5))}</td>
                            <td>${esc(s.checkinLuc ? s.checkinLuc.slice(11, 16) : "—")}</td>
                            <td>${esc(s.checkoutLuc ? s.checkoutLuc.slice(11, 16) : "—")}</td>
                            <td>${s.soPhutThucTe !== null ? `${s.soPhutThucTe}p` : "—"}</td>
                            <td>
                              <b style="color:#0d9488;font-size:14px">
                                ${s.soPhutTinhCong !== null ? `${s.soPhutTinhCong} phút` : "—"}
                              </b>
                              ${zeroExplain}
                            </td>
                            <td>
                              ${s.checkinTre ? '<span class="tag r">Đi trễ</span>' : '<span class="tag ok">Đúng giờ</span>'}
                            </td>
                            <td>
                              <span class="tag ${s.trangThaiCong === "hop_le" ? "ok" : s.trangThaiCong === "tu_choi" ? "r" : "w"}">
                                ${s.trangThaiCong === "hop_le" ? "Hợp lệ" : s.trangThaiCong === "tu_choi" ? "Từ chối" : "Chờ duyệt"}
                              </span>
                            </td>
                          </tr>
                        `;
                      })
                      .join("")
                  : `<tr><td colspan="9" style="text-align:center;padding:28px;color:var(--g500)">Chưa có ca nào được check-in trong tháng ${month}/${year}.</td></tr>`
              }
            </tbody>
          </table>
        </div>
      </div>
    `;

    const pInput = $("payMonthInput");
    if (pInput) {
      pInput.addEventListener("change", () => {
        state.payMonth = pInput.value;
        renderCurrentTab();
      });
    }
  }

  // ==========================================
  // THAO TÁC CHECK-IN / CHECK-OUT
  // ==========================================
  async function doCheckin(sessionId) {
    try {
      await HttpService.post(`/people/assistants/sessions/${sessionId}/checkin`, {});
      toast("Check-in thành công!", "ok");
      await loadWorkspace(true);
    } catch (err) {
      toast(err.message, "err");
    }
  }

  async function doCheckout(sessionId) {
    try {
      await HttpService.post(`/people/assistants/sessions/${sessionId}/checkout`, {});
      toast("Check-out thành công!", "ok");
      await loadWorkspace(true);
    } catch (err) {
      toast(err.message, "err");
    }
  }

  // ==========================================
  // DIALOG CHI TIẾT CA HỌC
  // ==========================================
  async function openSessionDetailDialog(sessionId) {
    openDialog("Chi tiết ca học", '<p role="status">Đang tải chi tiết ca học…</p>');
    try {
      const res = await HttpService.get(`/people/assistants/sessions/${sessionId}`);
      const s = res.data;
      const html = `
        <div class="item"><span>Lớp học</span><b>${esc(s.maLop)} — ${esc(s.tenLop)}</b></div>
        <div class="item"><span>Môn học &amp; Cấp lớp</span><span>${esc(s.tenMon)} · ${esc(s.tenCapLop)}</span></div>
        <div class="item"><span>Ngày dạy &amp; Giờ</span><b>${esc(formatShortDate(s.ngay))} (${esc(s.gioBatDau.slice(0, 5))} – ${esc(s.gioKetThuc.slice(0, 5))})</b></div>
        <div class="item"><span>Địa điểm / Cơ sở</span><span>${esc(s.phongHoc || "P.Học")} — ${esc(s.tenCoSo || "Cơ sở 1")}</span></div>
        <div class="item"><span>Giáo viên chính</span><b>${esc(s.tenGiaoVienChinh || "Chưa có")}</b></div>
        <div class="item"><span>Trợ giảng phụ trách</span><b>${esc(s.tenTroGiang || "Chính bạn")}</b></div>
        <div class="item"><span>Chủ đề buổi học</span><span>${esc(s.chuDeBaiHoc || "Chưa cập nhật")}</span></div>
        <div class="item"><span>Check-in ghi nhận</span><b>${esc(s.checkinLuc || "Chưa Check-in")}</b></div>
        <div class="item"><span>Check-out ghi nhận</span><b>${esc(s.checkoutLuc || "Chưa Check-out")}</b></div>
        <div class="item"><span>Phút tính công (Giao lịch)</span><b style="color:#0d9488">${s.soPhutTinhCong !== null ? `${s.soPhutTinhCong} phút` : "Chưa có"}</b></div>

        <div class="row-b" style="margin-top:18px;padding-top:14px;border-top:1px solid var(--g200)">
          <button type="button" class="btn sm" data-dialog-close>Đóng</button>
          <div class="row" style="gap:8px">
            <button type="button" class="btn sm" id="dlgBtnRequest" data-id="${s.id}">Làm đơn báo bận</button>
            ${
              !s.checkinLuc
                ? `<button type="button" class="btn sm p" id="dlgBtnCheckin" data-id="${s.id}">Check-in ca</button>`
                : !s.checkoutLuc
                ? `<button type="button" class="btn sm p" id="dlgBtnCheckout" data-id="${s.id}">Check-out ca</button>`
                : ""
            }
            <button type="button" class="btn sm p" id="dlgBtnLog" data-id="${s.id}">Hỗ trợ điểm danh</button>
          </div>
        </div>
      `;
      openDialog(`Chi tiết ca ${s.maLop} (${s.ngay})`, html);

      const reqBtn = $("dlgBtnRequest");
      if (reqBtn) reqBtn.addEventListener("click", () => openCreateRequestModal(s.id));
      const inBtn = $("dlgBtnCheckin");
      if (inBtn) inBtn.addEventListener("click", () => { closeDialog(); doCheckin(s.id); });
      const outBtn = $("dlgBtnCheckout");
      if (outBtn) outBtn.addEventListener("click", () => { closeDialog(); doCheckout(s.id); });
      const logBtn = $("dlgBtnLog");
      if (logBtn) logBtn.addEventListener("click", () => {
        closeDialog();
        state.sessionId = s.id;
        setTab("log");
      });
    } catch (err) {
      openDialog("Lỗi", `<p class="account-error">${esc(err.message)}</p>`);
    }
  }

  // ==========================================
  // THÔNG BÁO (NOTIFICATIONS)
  // ==========================================
  let notifPopover = null;
  async function toggleNotifications() {
    if (notifPopover) {
      notifPopover.remove();
      notifPopover = null;
      return;
    }

    const bell = $("assistant-bell");
    if (!bell) return;

    notifPopover = document.createElement("div");
    notifPopover.className = "assistant-notif-popover";
    notifPopover.innerHTML = `
      <div class="assistant-notif-head">
        <b style="font-size:13px">Thông báo của bạn</b>
        <button type="button" class="btn sm" id="btnReadAllNotif" style="padding:2px 8px;font-size:11px">Đã đọc tất cả</button>
      </div>
      <div class="assistant-notif-list" id="notifList">
        <div style="padding:16px;text-align:center;font-size:12px;color:var(--g500)">Đang tải thông báo…</div>
      </div>
    `;
    bell.parentElement.appendChild(notifPopover);

    const readAllBtn = $("btnReadAllNotif");
    if (readAllBtn) {
      readAllBtn.addEventListener("click", async () => {
        try {
          await HttpService.post("/communication/notifications/read-all", {});
          loadNotificationsList();
        } catch (_) {}
      });
    }

    loadNotificationsList();
  }

  async function loadNotificationsList() {
    const list = $("notifList");
    if (!list) return;
    try {
      const res = await HttpService.get("/communication/notifications");
      const items = res.data || [];
      const unreadCount = items.filter((n) => !n.daDoc && !n.readAt).length;

      const unreadBadge = $("assistant-unread");
      if (unreadBadge) {
        if (unreadCount > 0) {
          unreadBadge.textContent = unreadCount > 99 ? "99+" : unreadCount;
          unreadBadge.hidden = false;
        } else {
          unreadBadge.hidden = true;
        }
      }

      if (!items.length) {
        list.innerHTML = '<div style="padding:20px;text-align:center;font-size:12.5px;color:var(--g500)">Chưa có thông báo nào.</div>';
        return;
      }

      list.innerHTML = items
        .slice(0, 15)
        .map(
          (n) => `
        <div class="assistant-notif-item ${!n.daDoc && !n.readAt ? "unread" : ""}">
          <div style="font-weight:700;font-size:12.5px;color:var(--g900);margin-bottom:2px">${esc(n.tieuDe)}</div>
          <div style="font-size:12px;color:var(--g700);line-height:1.4">${esc(n.noiDung)}</div>
          <div style="font-size:10.5px;color:var(--g400);margin-top:4px">${esc(n.createdAt ? n.createdAt.slice(0, 16) : "")}</div>
        </div>
      `
        )
        .join("");
    } catch (_) {
      list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--err);font-size:12px">Không thể tải thông báo.</div>';
    }
  }

  // ==========================================
  // KHỞI TẠO VÀ BINDING TOÀN CỤC
  // ==========================================
  function init() {
    // Menu điều hướng
    document.addEventListener("click", (e) => {
      const navItem = e.target.closest("[data-tab]");
      if (navItem) {
        const tab = navItem.dataset.tab;
        if (tab && screens[tab]) setTab(tab);
        return;
      }

      // Dialog close
      if (e.target.closest("[data-dialog-close]")) {
        closeDialog();
        return;
      }

      // Sidebar mobile menu toggle
      if (e.target.closest("#assistant-menu")) {
        const side = $("assistant-sidebar");
        const backdrop = $("assistant-backdrop");
        if (side && backdrop) {
          const isOpen = side.classList.toggle("open");
          backdrop.hidden = !isOpen;
          e.target.closest("#assistant-menu").setAttribute("aria-expanded", String(isOpen));
        }
        return;
      }

      if (e.target.closest("#assistant-backdrop")) {
        const side = $("assistant-sidebar");
        const backdrop = $("assistant-backdrop");
        if (side) side.classList.remove("open");
        if (backdrop) backdrop.hidden = true;
        const menuBtn = $("assistant-menu");
        if (menuBtn) menuBtn.setAttribute("aria-expanded", "false");
        return;
      }

      // Bell
      if (e.target.closest("#assistant-bell")) {
        toggleNotifications();
        return;
      }
      if (notifPopover && !e.target.closest(".assistant-notif-popover") && !e.target.closest("#assistant-bell")) {
        notifPopover.remove();
        notifPopover = null;
      }

      // Account menu toggle
      const avatarBtn = e.target.closest("#assistant-avatar");
      const accountMenu = $("assistant-account-menu");
      if (avatarBtn) {
        if (accountMenu) {
          const isHidden = accountMenu.hidden;
          accountMenu.hidden = !isHidden;
          avatarBtn.setAttribute("aria-expanded", String(isHidden));
        }
        return;
      }

      // Action inside account menu
      const actionBtn = e.target.closest("[data-action]");
      if (actionBtn) {
        const act = actionBtn.dataset.action;
        if (act === "profile-view") AccountUI.view();
        else if (act === "profile") AccountUI.edit();
        else if (act === "logout") AccountUI.logout();
        if (accountMenu) accountMenu.hidden = true;
      }
    });

    // Load unread count initially
    setTimeout(() => {
      loadNotificationsList();
    }, 600);

    // Initial workspace load
    loadWorkspace();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
