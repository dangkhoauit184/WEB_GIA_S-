(() => {
  "use strict";
  if (!AccessService.requireAuth(["TRO_GIANG"])) return;

  const screens = {
    dash: "Tổng quan hỗ trợ",
    schedule: "Lịch ca trợ giảng",
    session: "Chi tiết ca hỗ trợ & Check-in / Check-out",
    log: "Sổ buổi học & Chấm bài",
    myclasses: "Lớp hỗ trợ & Lịch rảnh",
    recruit: "Đề xuất & Ứng tuyển ca",
    requests: "Đơn nghỉ cá nhân / Đổi giờ / Công",
    paysheet: "Bảng công cá nhân",
  };
  const icons = [
    '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    '<path d="M4 3h16v18H4zM8 7h8M8 11h8M8 15h5"/>',
    '<path d="m3 10 9-7 9 7v11H3zM9 21v-8h6v8"/>',
    '<path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>',
    '<path d="M14 3H4v18h16V9M14 3v6h6M8 13h8M8 17h5"/>',
    '<path d="M4 3h16v18H4zM8 7h8M8 11h8M8 15h8M8 18h4"/>',
  ];
  const state = {
    workspace: null,
    shiftOpenings: [],
    notifications: [],
    tab: "dash",
    sessionId: null,
    detail: null,
    dirty: false,
    calendarMode: "week",
    calendarDate: "",
    availabilityMode: "week",
    availabilityDate: "",
    requestFilter: "",
    month: "",
    classFilter: "",
  };
  const content = document.getElementById("assistant-content");
  const dialog = document.getElementById("assistant-dialog");
  const dialogContent = document.getElementById("assistant-dialog-content");
  let toastTimer;
  let renderVersion = 0;
  let availabilityInProfile = false;
  let profileUpdate = Promise.resolve();
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character],
    );
  const api = async (path, method = "get", body) =>
    (await HttpService[method](path, body)).data;
  const assistants = "/people/assistants";
  const empty = (text) => `<div class="assistant-empty">${escape(text)}</div>`;
  const button = (label, action, id = "", style = "", disabled = false) =>
    `<button type="button" class="btn sm ${style}" data-action="${action}" data-id="${escape(id)}" ${disabled ? "disabled" : ""}>${label}</button>`;
  const head = (title, subtitle = "", actions = "") =>
    `<div class="pg-head"><div><h1>${escape(title)}</h1>${subtitle ? `<p class="sub">${escape(subtitle)}</p>` : ""}</div><div class="assistant-actions">${actions}</div></div>`;
  const date = (value) =>
    value
      ? new Intl.DateTimeFormat("vi-VN", { timeZone: "UTC" }).format(
          new Date(value.slice(0, 10) + "T12:00:00Z"),
        )
      : "—";
  const datetime = (value) =>
    value ? `${value.slice(11, 16)} · ${date(value)}` : "—";
  const time = (value) => value?.slice(0, 5) || "—";
  const today = () => state.workspace.serverNow.slice(0, 10);
  const addDays = (value, count) =>
    new Date(Date.parse(value + "T12:00:00Z") + count * 86400000)
      .toISOString()
      .slice(0, 10);
  const monday = (value) =>
    addDays(value, -((new Date(value + "T12:00:00Z").getUTCDay() + 6) % 7));
  const sessionTitle = (session) =>
    `${session.maLop} · ${date(session.ngay)} · ${time(session.gioBatDau)}–${time(session.gioKetThuc)}`;
  const disabledSession = (session) =>
    ["huy", "doi"].includes(session.trangThai) ||
    session.kyCong !== "mo" ||
    session.vaiTro !== "tro_giang";
  const labels = {
    du_kien: "Sắp diễn ra",
    dang_day: "Đang diễn ra",
    da_day: "Đã dạy",
    huy: "Đã hủy",
    doi: "Đã đổi lịch",
    cho_duyet: "Chờ xử lý",
    da_duyet: "Đã duyệt",
    tu_choi: "Từ chối",
    da_huy: "Đã hủy",
    dang_hoc: "Đang học",
    sap_bat_dau: "Sắp bắt đầu",
    hoan_thanh: "Hoàn thành",
    tam_dung: "Tạm dừng",
    bao_ban: "Xin nghỉ cá nhân",
    doi_gio: "Đổi giờ hỗ trợ",
    dieu_chinh_cong: "Điều chỉnh công",
    mo: "Đang mở",
    da_chot: "Đã chốt",
    da_tra: "Đã trả",
    hop_le: "Hợp lệ",
    chua_check: "Chưa check-in",
    hoc_thu: "Học thử",
    da_roi: "Đã rời lớp",
    tam_nghi: "Tạm nghỉ",
    cho_xep_lop: "Chờ xếp lớp",
    dang_mo: "Đang mở tuyển",
    da_du_nguoi: "Đã đủ người",
    da_dong: "Đã đóng",
  };
  function tag(value, fallback) {
    const style = [
      "da_day",
      "da_duyet",
      "hop_le",
      "dang_hoc",
      "da_du_nguoi",
    ].includes(value)
      ? "ok"
      : ["huy", "tu_choi", "da_huy", "da_dong"].includes(value)
        ? "r"
        : ["cho_duyet", "du_kien", "dang_mo"].includes(value)
          ? "w"
          : "i";
    return `<span class="tag ${style}">${escape(labels[value] || fallback || value)}</span>`;
  }
  function link(value, label) {
    try {
      if (!value) return "";
      const isLocalPath =
        value.startsWith("/") &&
        !value.startsWith("//") &&
        !value.includes("\\");
      const url = new URL(value, isLocalPath ? location.origin : undefined);
      if (!["http:", "https:"].includes(url.protocol)) return "";
      return `<a class="btn sm" href="${escape(url.href)}" target="_blank" rel="noopener noreferrer">${escape(label)}</a>`;
    } catch {
      return "";
    }
  }
  function table(headers, rows, emptyMessage = "Chưa có dữ liệu.") {
    return `<div class="tw"><table><thead><tr>${headers.map((label) => `<th>${label}</th>`).join("")}</tr></thead><tbody>${rows.length ? rows.join("") : `<tr><td colspan="${headers.length}">${empty(emptyMessage)}</td></tr>`}</tbody></table></div>`;
  }
  function toast(message) {
    const element = document.getElementById("assistant-toast");
    element.textContent = message;
    element.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      element.hidden = true;
    }, 5000);
  }
  function menu(open) {
    document.body.classList.toggle("menu-open", open);
    document.getElementById("assistant-backdrop").hidden = !open;
    document
      .getElementById("assistant-menu")
      .setAttribute("aria-expanded", String(open));
  }
  function modal(title, body) {
    dialogContent.innerHTML = `<div class="assistant-dialog-head"><h2 id="assistant-dialog-title">${escape(title)}</h2>${button("✕", "close", "", "", false)}</div><div class="assistant-dialog-body">${body}</div>`;
    if (!dialog.open) dialog.showModal();
  }
  function formFoot(label = "Gửi yêu cầu") {
    return `<p class="assistant-error" data-form-error role="alert" hidden></p><div class="assistant-dialog-foot">${button("Hủy", "close")}<button class="btn p" type="submit">${label}</button></div>`;
  }
  function field(label, name, value = "", type = "text", extra = "") {
    if (type === "datetime-local") {
      const parts = value.replace(" ", "T").split("T");
      return `<fieldset class="assistant-date-time" data-date-time="${name}"><legend>${escape(label)}</legend><div class="assistant-form-grid">
        ${field("Ngày", name + "Date", parts[0] || "", "date", extra)}
        ${field("Giờ", name + "Time", parts[1]?.slice(0, 5) || "", "time", extra)}</div></fieldset>`;
    }
    if (type === "time") {
      return `<div class="assistant-field"><label for="assistant-input-${name}">${escape(label)} (HH:mm)</label><input id="assistant-input-${name}" name="${name}" type="text" data-time24 value="${escape(value)}" placeholder="HH:mm" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" aria-describedby="assistant-error-${name}" ${extra}><small id="assistant-error-${name}" class="assistant-field-error" data-field-error aria-live="polite" hidden></small></div>`;
    }
    const control = `<label>${escape(label)}<input name="${name}" type="${type}" value="${escape(value)}" ${extra}${type === "date" ? ` aria-describedby="assistant-error-${name}"` : ""}></label>`;
    return type === "date"
      ? `<div class="assistant-field">${control}<small id="assistant-error-${name}" class="assistant-field-error" data-field-error aria-live="polite" hidden></small></div>`
      : control;
  }
  const validTime = (value) => /^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(value);
  function dateTimeValue(values, name) {
    const day = values.get(name + "Date");
    const hour = values.get(name + "Time");
    return day && hour ? day + "T" + hour : null;
  }
  function fieldError(control, message = "") {
    control.setCustomValidity(message);
    if (message) control.setAttribute("aria-invalid", "true");
    else control.removeAttribute("aria-invalid");
    const feedback = control
      .closest(".assistant-field")
      ?.querySelector("[data-field-error]");
    if (feedback) {
      feedback.textContent = message;
      feedback.hidden = !message;
    }
  }
  function validateTimeFields(form) {
    let valid = true;
    form
      .querySelectorAll("[data-time24],input[type=date]")
      .forEach((control) => {
        let message = "";
        if (!control.value && (control.required || control.validity.badInput))
          message = control.hasAttribute("data-time24")
            ? "Vui lòng nhập giờ theo dạng HH:mm, từ 00:00 đến 23:59."
            : "Vui lòng chọn ngày hợp lệ.";
        else if (
          control.value &&
          control.hasAttribute("data-time24") &&
          !validTime(control.value)
        )
          message =
            "Giờ cần có dạng HH:mm, từ 00:00 đến 23:59. Ví dụ 12:00 là buổi trưa, 18:00 là buổi tối.";
        fieldError(control, message);
        if (message) valid = false;
      });
    form.querySelectorAll("[data-date-time]").forEach((group) => {
      const day = group.querySelector("input[type=date]");
      const hour = group.querySelector("[data-time24]");
      if (Boolean(day.value) !== Boolean(hour.value)) {
        fieldError(
          day.value ? hour : day,
          day.value ? "Vui lòng nhập giờ." : "Vui lòng chọn ngày.",
        );
        valid = false;
      }
    });
    return valid;
  }
  function validateRequest() {
    const form = document.getElementById("assistant-request-form");
    if (!form) return true;
    let valid = validateTimeFields(form);
    if (valid && form.elements.loai.value === "doi_gio") {
      if (form.elements.gioKetThuc.value <= form.elements.gioBatDau.value) {
        fieldError(
          form.elements.gioKetThuc,
          "Giờ kết thúc phải sau giờ bắt đầu trong cùng ngày.",
        );
        valid = false;
      }
      if (
        form.elements.ngayMoi.value +
          " " +
          form.elements.gioBatDau.value +
          ":00" <=
        state.workspace.serverNow
      ) {
        fieldError(
          form.elements.ngayMoi,
          "Ngày và giờ đề xuất phải sau thời điểm hiện tại.",
        );
        valid = false;
      }
    }
    if (valid && form.elements.loai.value === "dieu_chinh_cong") {
      const values = new FormData(form);
      if (
        dateTimeValue(values, "checkoutMoi") <=
        dateTimeValue(values, "checkinMoi")
      ) {
        fieldError(
          form.elements.checkoutMoiTime,
          "Check-out đề nghị phải sau Check-in đề nghị. Kiểm tra cả ngày và giờ.",
        );
        valid = false;
      }
    }
    form.querySelector("[type=submit]").disabled =
      !valid ||
      !form.elements.buoiHocId.value ||
      form.hasAttribute("aria-busy");
    return valid;
  }
  const options = (values, selected) =>
    Object.entries(values)
      .map(
        ([value, label]) =>
          `<option value="${escape(value)}" ${String(selected ?? "") === value ? "selected" : ""}>${escape(label)}</option>`,
      )
      .join("");
  const select = (label, name, values, selected, extra = "") =>
    `<label>${label}<select aria-label="${escape(label)}" name="${name}" ${extra}>${options(values, selected)}</select></label>`;
  const textarea = (label, name, value = "", maxlength = 10000) =>
    `<label>${label}<textarea name="${name}" maxlength="${maxlength}" rows="2">${escape(value)}</textarea></label>`;

  function periodStatus(ym) {
    const [y, m] = String(ym || "").split("-").map(Number);
    const hit = (state.workspace?.periods || []).find(
      (p) => Number(p.nam) === y && Number(p.thang) === m,
    );
    return hit ? hit.trangThai : "mo";
  }

  async function load() {
    const [workspace, shiftOpenings, notifications] = await Promise.all([
      api(assistants + "/workspace"),
      api("/academic/scheduling/openings"),
      api("/communication/notifications"),
    ]);
    state.workspace = workspace;
    state.shiftOpenings = Array.isArray(shiftOpenings) ? shiftOpenings : [];
    state.notifications = Array.isArray(notifications) ? notifications : [];
    state.calendarDate ||= today();
    state.availabilityDate ||= today();
    state.month ||= today().slice(0, 7);
    if (
      state.sessionId &&
      !state.workspace.sessions.some(
        (session) => String(session.id) === String(state.sessionId),
      )
    ) {
      state.sessionId = null;
    }
    const profile = state.workspace.profile;
    document.getElementById("assistant-name").textContent = profile.hoTen;
    document.getElementById("assistant-avatar").textContent = AccountUI.initial(profile.hoTen);
    document.getElementById("assistant-date").textContent =
      new Intl.DateTimeFormat("vi-VN", {
        dateStyle: "full",
        timeZone: "UTC",
      }).format(new Date(today() + "T12:00:00Z"));
    const unread = state.notifications.filter(
      (notification) => !Number(notification.daDoc),
    ).length;
    document.getElementById("assistant-unread").textContent = unread;
    document.getElementById("assistant-unread").hidden = !unread;
  }

  function sessionRows(sessions) {
    return sessions.map(
      (session) =>
        `<tr><td><b>${escape(session.maLop)}</b><br><small>${escape(session.tenLop)}</small></td><td>${date(session.ngay)}</td><td>${time(session.gioBatDau)}–${time(session.gioKetThuc)}</td><td>${session.loai === "bu" ? '<span class="tag purple">Học bù</span>' : "Buổi thường"}</td><td>${escape(session.hinhThuc === "online" ? "Trực tuyến" : session.tenCoSo || session.diaDiem || "Chưa cập nhật")}<br>${escape(session.phongHoc || "")}</td><td>${tag(session.trangThai)}</td><td>${button("Xem ca", "session", session.id)} ${button("Ghi sổ", "log", session.id)}</td></tr>`,
    );
  }
  function dashboardStatus(session) {
    if (["huy", "doi"].includes(session.trangThai))
      return tag(session.trangThai);
    if (session.checkoutLuc) return '<span class="tag g">Đã Check-out</span>';
    if (session.checkinLuc) return '<span class="tag i">Đã Check-in</span>';
    if (session.ngay + " " + session.gioKetThuc <= state.workspace.serverNow)
      return '<span class="tag w">Chưa Check-in</span>';
    if (session.ngay + " " + session.gioBatDau <= state.workspace.serverNow)
      return '<span class="tag w">Đến giờ hỗ trợ</span>';
    return session.loai === "bu"
      ? '<span class="tag purple">Đã xếp lịch bù</span>'
      : '<span class="tag i">Sắp diễn ra</span>';
  }
  function todayBanner(sessions) {
    const now = state.workspace.serverNow;
    const session =
      sessions.find((item) => item.checkinLuc && !item.checkoutLuc) ||
      sessions.find(
        (item) => !item.checkoutLuc && item.ngay + " " + item.gioKetThuc > now,
      );
    if (!session) {
      const message = !sessions.length
        ? "Hôm nay bạn chưa có ca được phân công."
        : sessions.every((item) => item.checkoutLuc)
          ? "Bạn đã hoàn tất các ca hỗ trợ hôm nay."
          : "Các ca hôm nay đã qua khung giờ dự kiến.";
      return `<section class="assistant-session-banner assistant-dashboard-banner"><div><span class="tag">Ca hỗ trợ hôm nay · ${date(today())}</span><h2>${message}</h2></div>${button("Xem lịch tuần", "week-schedule", "", "p")}</section>`;
    }
    const classroom = state.workspace.classes.find(
      (item) => String(item.id) === String(session.lopId),
    );
    const minutes = (value) =>
      Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
    const duration = minutes(session.gioKetThuc) - minutes(session.gioBatDau);
    const heading = session.checkinLuc
      ? "Ca đã Check-in hôm nay"
      : session.ngay + " " + session.gioBatDau <= now
        ? "Ca hỗ trợ trong khung giờ hôm nay"
        : "Ca hỗ trợ sắp diễn ra hôm nay";
    return `<section class="assistant-session-banner assistant-dashboard-banner"><div><span class="tag">${heading}</span>
      <h2>${escape(session.maLop)} — ${escape(session.tenLop)}</h2>
      <p>${escape(session.hinhThuc === "online" ? "Trực tuyến" : session.tenCoSo || session.diaDiem || "Chưa cập nhật địa điểm")} ${escape(session.phongHoc || "")}</p>
      <p>Khung giờ lịch <b>${time(session.gioBatDau)}–${time(session.gioKetThuc)} (${duration} phút)</b>${classroom ? ` · Sĩ số lớp ${classroom.siSo} học viên` : ""}</p></div>
      ${button("Xem chi tiết ca →", "session", session.id, "p")}</section>`;
  }
  function dashboard() {
    const workspace = state.workspace;
    const sessions = workspace.sessions.filter(
      (session) =>
        session.ngay === today() && !["huy", "doi"].includes(session.trangThai),
    );
    const weekStart = monday(today());
    const nextWeek = addDays(weekStart, 7);
    const weekSessions = workspace.sessions.filter(
      (session) => session.ngay >= weekStart && session.ngay < nextWeek,
    );
    const dayLabel = (day) =>
      (day === today()
        ? "Hôm nay"
        : new Intl.DateTimeFormat("vi-VN", {
            weekday: "long",
            timeZone: "UTC",
          }).format(new Date(day + "T12:00:00Z"))) +
      ", " +
      date(day);
    const openShifts = state.shiftOpenings.filter(
      (item) => item.trangThai === "dang_mo" && !item.donCuaToi && item.duDieuKien,
    );
    const pendingRequests = workspace.requests.filter(
      (req) => req.trangThai === "cho_duyet",
    );
    const monthSessions = workspace.sessions.filter(
      (session) =>
        session.ngay.startsWith(today().slice(0, 7)) &&
        session.trangThaiCong !== "tu_choi",
    );
    const minutes = monthSessions.reduce(
      (sum, session) => sum + Number(session.soPhutTinhCong || 0),
      0,
    );
    const stats = [
      [sessions.length, `Ca hỗ trợ hôm nay · ${date(today())}`],
      [workspace.classes.length, "Lớp đang hỗ trợ"],
      [openShifts.length, "Ca tuyển Trợ giảng phù hợp"],
      [minutes, "Phút công tháng này"],
    ];
    return (
      head(
        `Xin chào, ${workspace.profile.hoTen}`,
        `Hôm nay ${date(today())} · Tuần ${date(weekStart)} – ${date(addDays(weekStart, 6))}.`,
        button("Xem lịch ca trợ giảng", "tab", "schedule", "p"),
      ) +
      `<div class="assistant-stats">${stats.map(([value, label]) => `<div class="card assistant-stat"><strong>${value}</strong><span>${label}</span></div>`).join("")}</div>` +
      `<div class="assistant-grid"><div>${todayBanner(sessions)}<div class="card assistant-week-assignments"><div class="row-b"><div><h2>Các ca phân công trong tuần</h2><p class="assistant-muted">${date(weekStart)} – ${date(addDays(weekStart, 6))}</p></div>${button("Xem thời khóa biểu tuần", "week-schedule")}</div>${table(
        [
          "Ngày & Giờ lịch",
          "Mã lớp / Môn học",
          "Phòng / Cơ sở",
          "Vai trò",
          "Trạng thái",
          "Thao tác",
        ],
        weekSessions.map(
          (
            session,
          ) => `<tr><td><b>${dayLabel(session.ngay)}</b><br><small>${time(session.gioBatDau)}–${time(session.gioKetThuc)}</small></td>
        <td><b>${escape(session.maLop)}</b><br><small>${escape(session.tenMon)} · ${escape(session.tenLop)}</small></td>
        <td>${escape(session.phongHoc || "")}<br><small>${escape(session.hinhThuc === "online" ? "Trực tuyến" : session.tenCoSo || session.diaDiem || "Chưa cập nhật")}</small></td>
        <td><span class="tag ${session.loai === "bu" ? "purple" : "i"}">${session.loai === "bu" ? "Ca hỗ trợ bù" : "Trợ giảng"}</span></td>
        <td>${dashboardStatus(session)}</td><td>${button(session.checkinLuc ? "Xem ca" : "Chi tiết ca", "session", session.id, session.ngay === today() && !session.checkoutLuc ? "p" : "")}${session.checkinLuc ? button("Sổ buổi học", "log", session.id) : ""}</td></tr>`,
        ),
      )}</div>
      <div class="card"><h2>Đề xuất tuyển Trợ giảng phù hợp</h2>${
        openShifts.length
          ? openShifts
              .slice(0, 5)
              .map(
                (item) =>
                  `<div class="assistant-row"><div><b>${escape(item.maDeXuat)} · ${escape(item.maLop)}</b><small>${date(item.ngay)} · ${time(item.gioBatDau)}–${time(item.gioKetThuc)} · ${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></div>${button("Ứng tuyển", "tab", "recruit", "p")}</div>`,
              )
              .join("")
          : empty("Chưa có đề xuất tuyển Trợ giảng phù hợp đang mở.")
      }</div></div>
      <div><div class="card"><h2>Công việc trợ giảng</h2><div class="assistant-row">${button("Mở thời khóa biểu", "tab", "schedule", "p")}</div><div class="assistant-row">${button("Đề xuất & Ứng tuyển ca", "tab", "recruit")}</div><div class="assistant-row">${button("Hồ sơ & Lịch rảnh", "profile-view")}</div><div class="assistant-row">${button(`Gửi đơn từ${pendingRequests.length ? ` (${pendingRequests.length} chờ)` : ""}`, "request")}</div><div class="assistant-row">${button("Xem bảng công cá nhân", "tab", "paysheet")}</div></div>
      <div class="card"><h2>Thông báo gần đây</h2>${
        state.notifications
          .slice(0, 4)
          .map(
            (notification) =>
              `<div class="assistant-row"><div><b>${escape(notification.tieuDe)}</b><small>${escape(notification.noiDung)}</small></div></div>`,
          )
          .join("") || empty("Chưa có thông báo.")
      }${button("Xem thông báo", "notifications")}</div></div></div>`
    );
  }
  function calendarStatus(session) {
    if (["huy", "doi"].includes(session.trangThai)) return "cancelled";
    if (session.checkoutLuc || session.phanCongTrangThai === "da_day")
      return "completed";
    if (session.checkinLuc) return "active";
    return session.loai === "bu" ? "makeup" : "upcoming";
  }
  function calendarCard(session, compact = false) {
    const status = calendarStatus(session);
    const statusLabel = {
      cancelled: "Đã hủy / Đổi lịch",
      completed: "Hoàn tất",
      active: "Đã Check-in",
      makeup: "Hỗ trợ bù",
      upcoming: "Đã xếp lịch",
    }[status];
    const title = escape(sessionTitle(session) + " · " + statusLabel);
    if (compact)
      return `<button type="button" class="cal-m-chip ${status}" data-action="session" data-id="${session.id}" title="${title}"><span>${time(session.gioBatDau)}</span><b>${escape(session.maLop)}</b>${session.checkinLuc ? "<span>✓</span>" : ""}</button>`;
    return `<button type="button" class="ses-card ${status}" data-action="session" data-id="${session.id}" title="${title}">
      <span class="assistant-calendar-badge">${escape(statusLabel)}</span>
      <span class="sc-code">${escape(session.maLop)}</span>
      <span class="sc-subj">${escape(session.tenMon)} · ${escape(session.tenLop)}</span>
      <span class="sc-time">${time(session.gioBatDau)} – ${time(session.gioKetThuc)}</span>
      <span class="sc-loc">${escape(session.hinhThuc === "online" ? "Trực tuyến" : session.tenCoSo || session.diaDiem || "Chưa cập nhật địa điểm")} ${escape(session.phongHoc || "")}</span>
      ${session.loai === "bu" && status !== "makeup" ? '<span class="assistant-muted">Buổi học bù</span>' : ""}
    </button>`;
  }
  function calendarPeriod(selected, view) {
    const first = selected.slice(0, 7) + "-01";
    const start = monday(view === "month" ? first : selected);
    const nextMonth = new Date(first + "T12:00:00Z");
    nextMonth.setUTCMonth(nextMonth.getUTCMonth() + 1);
    const monthEnd = addDays(nextMonth.toISOString().slice(0, 10), -1);
    const count = (Date.parse(monthEnd) - Date.parse(start)) / 86400000 + 1;
    return {
      start,
      days: view === "month" ? Math.ceil(count / 7) * 7 : 7,
      range:
        view === "week"
          ? `Tuần ${date(start)} – ${date(addDays(start, 6))}`
          : `Tháng ${selected.slice(5, 7)} / ${selected.slice(0, 4)}`,
      headings: ["THỨ 2", "THỨ 3", "THỨ 4", "THỨ 5", "THỨ 6", "THỨ 7", "CN"],
    };
  }
  function schedule() {
    const view = state.calendarMode;
    const selected = state.calendarDate;
    const { start, days, range, headings } = calendarPeriod(selected, view);
    const sessions = state.workspace.sessions.filter(
      (session) =>
        !state.classFilter || String(session.lopId) === state.classFilter,
    );
    const cells = Array.from({ length: days }, (_, index) => {
      const day = addDays(start, index);
      const isToday = day === today();
      const rows = sessions.filter((session) => session.ngay === day);
      if (view === "month")
        return `<div class="cal-month-cell ${isToday ? "is-today" : ""} ${day.slice(0, 7) !== selected.slice(0, 7) ? "other-month" : ""}"><div class="row-b"><b>${Number(day.slice(8))}</b>${isToday ? '<span class="d-today-badge">Hôm nay</span>' : ""}</div>${rows.map((session) => calendarCard(session, true)).join("")}</div>`;
      return `<div class="cal-day-col ${isToday ? "is-today" : ""}"><div class="cal-col-head ${isToday ? "is-today" : ""}"><div class="d-name">${headings[index]}${isToday ? '<span class="d-today-badge">Hôm nay</span>' : ""}</div><div class="d-num">${day.slice(8)}/${day.slice(5, 7)}</div></div><div class="cal-col-body">${rows.map((session) => calendarCard(session)).join("") || '<div class="cal-col-empty">—</div>'}</div></div>`;
    }).join("");
    return (
      head(
        "Thời khóa biểu cá nhân",
        "Bấm vào một ca trên lịch để xem chi tiết, Check-in / Check-out và ghi sổ buổi học.",
      ) +
      `<div class="cal-box">
      <div class="cal-bar"><div class="row">${button("‹ Trước", "calendar-prev")}${button("Hôm nay", "calendar-today")}${button("Sau ›", "calendar-next")}</div>
        <div class="assistant-calendar-period"><b class="cal-range-btn">${escape(range)}</b>${field("Chọn tháng", "calendar-month", selected.slice(0, 7), "month", "required")}</div>
        <div class="seg"><button type="button" class="${view === "week" ? "on" : ""}" data-action="calendar-mode" data-id="week">Lịch Tuần</button><button type="button" class="${view === "month" ? "on" : ""}" data-action="calendar-mode" data-id="month">Lịch Tháng</button><button type="button" class="${view === "list" ? "on" : ""}" data-action="calendar-mode" data-id="list">Danh sách</button></div>
      </div>
      <div class="cal-filter-bar">${select("Lớp học", "class-filter", { "": "Tất cả lớp", ...Object.fromEntries(state.workspace.classes.map((item) => [item.id, item.maLop])) }, state.classFilter)}${button("Đặt lại lọc", "calendar-reset")} ${button("Tải lại lịch", "reload")}</div>
      <div class="assistant-calendar-scroll">${view === "list" ? table(["Lớp", "Ngày", "Giờ", "Loại buổi", "Địa điểm", "Trạng thái", "Thao tác"], sessionRows(sessions.filter((session) => session.ngay.startsWith(selected.slice(0, 7))))) : view === "month" ? `<div class="assistant-month-view"><div class="cal-month-head">${headings.map((label) => `<div class="cal-month-head-cell">${label}</div>`).join("")}</div><div class="cal-month-grid">${cells}</div></div>` : `<div class="cal-week-grid">${cells}</div>`}</div>
    </div>`
    );
  }
  function workMinutes(session) {
    if (session.soPhutTinhCong === null || session.soPhutTinhCong === undefined)
      return "—";
    let reason = "";
    if (
      Number(session.soPhutTinhCong) === 0 &&
      session.checkinLuc &&
      session.checkoutLuc
    ) {
      const start =
        session.gioLichBatDau || `${session.ngay} ${session.gioBatDau}`;
      const end =
        session.gioLichKetThuc || `${session.ngay} ${session.gioKetThuc}`;
      if (session.checkinLuc >= end)
        reason = `Check-in sau khi ca học đã kết thúc (${datetime(end)}).`;
      else if (session.checkoutLuc <= start)
        reason = `Check-out trước giờ bắt đầu ca (${datetime(start)}).`;
      else reason = "Thời gian trong khung lịch chưa đủ 1 phút.";
    }
    return `<span class="assistant-work-minutes">${escape(session.soPhutTinhCong)} phút${reason ? `<small>${escape(reason)}</small>` : ""}</span>`;
  }
  function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (!size) return "";
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
  }
  function materialSummary(session) {
    const mat = session.taiLieu || {};
    if (!mat.coTaiLieu) {
      return '<p class="assistant-muted">Giáo viên chính chưa gắn tài liệu chuẩn bị cho buổi học này.</p>';
    }
    const metaParts = [];
    if (mat.laTepTaiLen && mat.tenTep) {
      metaParts.push(`${mat.tenTep}${mat.kichThuoc ? " · " + formatBytes(mat.kichThuoc) : ""}`);
    } else if (mat.url) {
      metaParts.push(mat.url);
    }
    return `<div class="assistant-material-current">
      <div><b>${escape(mat.tieuDe || mat.tenTep || "Tài liệu chuẩn bị buổi học")}</b>
      ${metaParts.length ? `<small class="assistant-muted" style="display:block">${escape(metaParts.join(" · "))}</small>` : ""}
      ${mat.ghiChu ? `<p class="assistant-muted" style="margin:4px 0 0">${escape(mat.ghiChu)}</p>` : ""}</div>
      <div class="assistant-actions">${link(mat.url, mat.laTepTaiLen ? "Xem / Tải tệp" : "Mở liên kết tài liệu")}</div>
    </div>`;
  }
  function materialCard(session) {
    return `<div class="card"><h2>Tài liệu chuẩn bị trước buổi học</h2>
      ${materialSummary(session)}
      <p class="assistant-muted" style="margin:8px 0 0;font-size:12px">Trợ giảng chỉ xem/tải tài liệu của ca được phân công; quyền tải lên hoặc chỉnh sửa tệp thuộc Giáo viên chính.</p>
    </div>`;
  }
  function shiftRequestControls(session) {
    const caps = session.quyenYeuCau || {};
    const rows = [];
    if (caps.coTheDoiLich) {
      rows.push(`<div class="assistant-row">${button("Yêu cầu đổi lịch", "reschedule", session.id)}</div>`);
    }
    if (caps.coTheChinhCong) {
      rows.push(`<div class="assistant-row">${button("Yêu cầu chỉnh công", "correction", session.id)}</div>`);
    }
    if (!rows.length && caps.lyDoKhoa) {
      rows.push(`<p class="assistant-muted" style="margin:8px 0 0">${escape(caps.lyDoKhoa)}</p>`);
    }
    return rows.join("");
  }
  function sessionView(session) {
    const blocked = disabledSession(session);
    const presence = session.soPhutThucTe;
    return (
      head(
        "Chi tiết ca hỗ trợ & Check-in / Check-out",
        sessionTitle(session),
        button("← Thời khóa biểu", "tab", "schedule") +
          button("Tải lại ca", "reload-session"),
      ) +
      `<div class="assistant-session-banner"><p>${date(session.ngay)} · ${time(session.gioBatDau)}–${time(session.gioKetThuc)}${session.loai === "bu" ? " · Học bù" : ""}</p><h2>${escape(session.tenLop)}</h2><p>${escape(session.maLop)} · ${escape(session.hinhThuc === "online" ? "Trực tuyến" : session.tenCoSo || session.diaDiem || "Chưa cập nhật địa điểm")} ${escape(session.phongHoc || "")}${session.tenGiaoVienChinh ? ` · GV chính: ${escape(session.tenGiaoVienChinh)}` : ""}</p><div class="assistant-actions">${button("Bấm Check-in", "checkin", session.id, "p", blocked || Boolean(session.checkinLuc) || session.phanCongTrangThai === "da_day")}${button("Bấm Check-out", "checkout", session.id, "", blocked || !session.checkinLuc || Boolean(session.checkoutLuc))}${link(session.linkHoc, "Vào phòng học trực tuyến")}</div></div>` +
      `<div class="assistant-grid"><div class="card"><h2>Thông tin chấm công</h2>${[
        ["Trạng thái buổi học", tag(session.trangThai)],
        ["Check-in", datetime(session.checkinLuc)],
        ["Check-out", datetime(session.checkoutLuc)],
        ["Thời gian hiện diện", presence === null || presence === undefined ? "—" : presence + " phút"],
        ["Phút tính công", workMinutes(session)],
        [
          "Đi trễ",
          session.checkinLuc
            ? Number(session.checkinTre)
              ? tag("cho_duyet", "Đi trễ").replace("Chờ xử lý", "Đi trễ")
              : tag("hop_le", "Đúng giờ").replace("Hợp lệ", "Đúng giờ")
            : "—",
        ],
        ["Kỳ công", tag(session.kyCong)],
      ]
        .map(
          ([label, value]) =>
            `<div class="assistant-row"><span class="assistant-muted">${label}</span><b>${value}</b></div>`,
        )
        .join(
          "",
        )}</div><div class="card"><h2>Sổ buổi học &amp; Yêu cầu</h2><p class="assistant-muted">${session.hocViens.length} học viên trong ca này.</p><div class="assistant-row">${button("Mở sổ · Điểm danh & Chấm bài", "log", session.id, "p")}</div>${shiftRequestControls(session)}</div></div>` +
      materialCard(session)
    );
  }
  function logView(session) {
    const writable = !disabledSession(session) && Boolean(session.checkinLuc);
    const attendanceOptions = {
      "": "Chọn điểm danh",
      co_mat: "Có mặt",
      di_tre: "Đi trễ",
      vang_co_phep: "Vắng có phép",
      vang_khong_phep: "Vắng không phép",
    };
    const prevList = Array.isArray(session.danhSachBaiTapTruoc)
      ? session.danhSachBaiTapTruoc
      : [];
    const savedHwId = session.hocViens.find((s) => s.baiTapId)?.baiTapId || null;
    const selectedPrev =
      prevList.find((item) => String(item.id) === String(savedHwId)) ||
      prevList[0] ||
      null;
    const activeHomeworkId = selectedPrev?.id ?? savedHwId ?? "";
    const canGrade = Boolean(activeHomeworkId);
    const prevHomeworkBox = prevList.length
      ? `<div class="assistant-prev-homework">
          ${prevList.length > 1
            ? select(
                "Bài tập về nhà buổi trước cần chấm",
                "baiTapChamId",
                Object.fromEntries(
                  prevList.map((item) => [
                    item.id,
                    `${item.tieuDe} · Buổi ${date(item.ngayBuoiHoc)}`,
                  ]),
                ),
                activeHomeworkId,
              )
            : `<input type="hidden" name="baiTapChamId" value="${escape(activeHomeworkId)}">
               <div><span class="tag i">Bài tập buổi trước · ${date(selectedPrev.ngayBuoiHoc)}</span>
               <b style="display:block;margin-top:6px">${escape(selectedPrev.tieuDe)}</b>
               ${selectedPrev.noiDung ? `<p class="assistant-muted" style="margin:4px 0 0">${escape(selectedPrev.noiDung)}</p>` : ""}</div>`}
          <div class="assistant-actions" style="margin-top:8px">${link(selectedPrev?.fileDeBaiUrl, "Xem đề bài buổi trước")}</div>
        </div>`
      : savedHwId
        ? `<input type="hidden" name="baiTapChamId" value="${escape(savedHwId)}"><p class="assistant-muted">Đang hiển thị kết quả chấm bài tập đã lưu của buổi học này.</p>`
        : '<div class="assistant-empty">Chưa có bài tập về nhà từ buổi học trước để chấm. Bạn vẫn có thể điểm danh và ghi nhận xét buổi học.</div>';
    return (
      head(
        "Sổ buổi học & Chấm bài",
        sessionTitle(session),
        button("← Quay lại chi tiết ca", "session", session.id) +
          button("Tải lại", "reload-session"),
      ) +
      `<div class="card"><div class="row-b"><b>${escape(sessionTitle(session))}</b>${session.checkinLuc ? tag("hop_le", "Đã check-in").replace("Hợp lệ", "Đã check-in") : tag("chua_check")}</div></div>
      ${!writable ? `<div class="card assistant-muted">${disabledSession(session) ? "Ca học hiện chỉ được xem." : "Check-in để bắt đầu ghi sổ buổi học."} ${button("Xem ca hỗ trợ", "session", session.id)}</div>` : ""}
      <form id="assistant-log-form"><fieldset ${writable ? "" : "disabled"} style="border:0;min-width:0">
      <div class="card"><h2>Hôm nay học gì</h2>${field("Chủ đề bài học *", "chuDe", session.chuDeBaiHoc || "", "text", 'required maxlength="255"')}${textarea("Nội dung đã giảng dạy", "noiDung", session.noiDungBaiHoc || "", 15000)}
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--bd)">
          <span class="assistant-muted"><b>Tài liệu chuẩn bị buổi học (chỉ xem)</b></span>
          ${materialSummary(session)}
        </div>
      </div>
      <div class="card"><h2>Điểm danh &amp; Chấm bài tập về nhà buổi trước</h2>
      ${prevHomeworkBox}
      <div class="assistant-students" style="margin-top:12px">${
        session.hocViens
          .map(
            (
              student,
            ) => `<details class="assistant-student" data-student="${student.hocVienId}">
        <summary><span><b>${escape(student.hoTen)}</b><small>${escape(session.tenLop)} · ${escape(session.maLop)}${Number(student.coHocBu) === 1 ? " · Học bù cá nhân" : ""}</small></span><span class="assistant-student-arrow" aria-hidden="true">⌄</span></summary>
        <div class="assistant-student-fields"><div class="assistant-form-grid">
          ${select("Điểm danh *", "attendance-" + student.hocVienId, attendanceOptions, student.trangThaiDiemDanh).replace("<select ", "<select required ")}
          ${field("Điểm bài tập buổi trước / 10", "score-" + student.hocVienId, canGrade ? (student.diemBaiTapGiay ?? "") : "", "number", 'min="0" max="10" step="0.01"' + (canGrade ? "" : ' disabled placeholder="Chưa có bài buổi trước"'))}
          ${select("Mức tiếp thu", "level-" + student.hocVienId, { "": "Chưa đánh giá", 1: "1 / 5", 2: "2 / 5", 3: "3 / 5", 4: "4 / 5", 5: "5 / 5" }, student.mucTiepThu)}
          ${select("Thái độ", "attitude-" + student.hocVienId, { "": "Chưa đánh giá", tot: "Tốt", kha: "Khá", trung_binh: "Trung bình", can_cai_thien: "Cần cải thiện" }, student.thaiDo)}
          <div class="full">${canGrade ? textarea("Nhận xét bài tập buổi trước", "comment-" + student.hocVienId, student.nhanXetBaiTap || "", 255) : `<label>Nhận xét bài tập buổi trước<textarea name="comment-${student.hocVienId}" rows="2" disabled placeholder="Chưa có bài tập buổi trước để chấm"></textarea></label>`}</div>
          ${textarea("Nhận xét buổi học", "note-" + student.hocVienId, student.nhanXetBuoiHoc || "")}
          ${textarea("Hướng cải thiện", "improve-" + student.hocVienId, student.huongCaiThien || "")}
        </div></div></details>`,
          )
          .join("") || empty("Ca học chưa có học viên.")
      }</div></div>
      <p class="assistant-error" data-form-error role="alert" hidden></p><div class="assistant-log-footer"><button class="btn p" type="submit" name="saveMode" value="save">Lưu sổ buổi học</button><button class="btn" type="submit" name="saveMode" value="checkout" ${session.checkoutLuc ? "disabled" : ""}>Lưu sổ &amp; Check-out</button></div></fieldset></form>`
    );
  }
  function availabilityCalendar() {
    const mode = state.availabilityMode;
    const selected = state.availabilityDate;
    const { start, days, range, headings } = calendarPeriod(selected, mode);
    const slotsAll = Array.isArray(state.workspace?.profile?.lichRanhs)
      ? state.workspace.profile.lichRanhs
      : [];
    const cells = Array.from({ length: days }, (_, index) => {
      const day = addDays(start, index);
      const weekday = ((new Date(day + "T12:00:00Z").getUTCDay() + 6) % 7) + 2;
      const isToday = day === today();
      const slots = slotsAll.filter((slot) => Number(slot.thu) === weekday);
      const entries = slots
        .map((slot) =>
          mode === "month"
            ? `<button type="button" class="cal-m-chip assistant-free-slot" data-action="edit-availability" data-id="${slot.id}" aria-label="Sửa lịch rảnh ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}">${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}</button>`
            : `<button type="button" class="ses-card assistant-free-slot" data-action="edit-availability" data-id="${slot.id}"><b class="sc-code">${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}</b><span class="sc-subj">Lịch rảnh</span></button>`,
        )
        .join("");
      if (mode === "month")
        return `<div class="cal-month-cell ${isToday ? "is-today" : ""} ${day.slice(0, 7) !== selected.slice(0, 7) ? "other-month" : ""}"><div class="row-b"><b>${Number(day.slice(8))}</b><button type="button" class="assistant-add-free" data-action="add-availability" data-id="${weekday}" aria-label="Thêm lịch rảnh ngày ${date(day)}">+</button></div>${entries}</div>`;
      return `<div class="cal-day-col ${isToday ? "is-today" : ""}"><div class="cal-col-head ${isToday ? "is-today" : ""}"><div class="d-name">${headings[index]}${isToday ? '<span class="d-today-badge">Hôm nay</span>' : ""}</div><div class="d-num">${day.slice(8)}/${day.slice(5, 7)}</div></div><div class="cal-col-body">${entries}${button("+ Thêm lịch rảnh", "add-availability", weekday, "assistant-add-free")}</div></div>`;
    }).join("");
    return `<section data-availability-calendar aria-label="Thời khóa biểu lịch rảnh"><div class="pg-head"><div><h2>Thời khóa biểu lịch rảnh</h2><p class="sub">Các khung giờ lặp lại hàng tuần. Bấm vào lịch để sửa hoặc xóa.</p></div>${button("+ Thêm lịch rảnh", "add-availability", "", "p")}</div><div class="cal-box">
      <div class="cal-bar"><div class="row">${button("‹ Trước", "availability-prev")}${button("Hôm nay", "availability-today")}${button("Sau ›", "availability-next")}</div>
        <div class="assistant-calendar-period"><b class="cal-range-btn">${escape(range)}</b>${field("Chọn tháng lịch rảnh", "availability-month", selected.slice(0, 7), "month", "required")}</div>
        <div class="seg"><button type="button" class="${mode === "week" ? "on" : ""}" data-action="availability-mode" data-id="week">Lịch Tuần</button><button type="button" class="${mode === "month" ? "on" : ""}" data-action="availability-mode" data-id="month">Lịch Tháng</button></div>
      </div><div class="assistant-calendar-scroll">${mode === "month" ? `<div class="assistant-month-view"><div class="cal-month-head">${headings.map((label) => `<div class="cal-month-head-cell">${label}</div>`).join("")}</div><div class="cal-month-grid">${cells}</div></div>` : `<div class="cal-week-grid">${cells}</div>`}</div></div></section>`;
  }
  function classes() {
    return (
      head(
        "Lớp hỗ trợ & Lịch rảnh",
        "Danh sách lớp đang hỗ trợ và lịch rảnh cá nhân đã đăng ký.",
        button("Hồ sơ cá nhân", "profile-view"),
      ) +
      `<div class="card">${table(
        [
          "Mã lớp",
          "Lớp / Khóa học",
          "Môn · Khối",
          "Lịch học",
          "Sĩ số",
          "Trạng thái",
          "Thao tác",
        ],
        state.workspace.classes.map(
          (item) =>
            `<tr><td><b>${escape(item.maLop)}</b></td><td>${escape(item.tenLop)}<br><small>${escape(item.tenKhoa || "")}</small></td><td>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</td><td>${item.lichHocs.map((slot) => `${Number(slot.thu) === 8 ? "CN" : "T" + slot.thu} ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}`).join("<br>") || "Chưa có lịch lặp"}</td><td>${item.siSo}</td><td>${tag(item.trangThai)}</td><td>${button("Xem lịch", "class-schedule", item.id)} ${button("Danh sách học viên", "roster", item.id)}</td></tr>`,
        ),
      )}</div>` +
      availabilityCalendar()
    );
  }
  function recruit() {
    const openShifts = state.shiftOpenings.filter(
      (item) => item.trangThai === "dang_mo",
    );
    const myShiftApps = state.shiftOpenings.filter((item) => item.donCuaToi);
    return (
      head(
        "Đề xuất & Ứng tuyển ca",
        "Ứng tuyển các ca tuyển Trợ giảng phù hợp lịch rảnh, chuyên môn và theo dõi kết quả duyệt từ Giáo vụ.",
        button("Tải lại", "reload"),
      ) +
      `<div class="card"><h2>Đề xuất tuyển Trợ giảng cho lớp / ca đang mở</h2>${table(
        [
          "Mã đề xuất",
          "Lớp · Môn · Khối",
          "Ngày & Giờ ca",
          "Hình thức · Địa điểm",
          "Vị trí & Yêu cầu",
          "Điều kiện & Trạng thái",
          "Thao tác",
        ],
        openShifts.map((item) => {
          const mine = item.donCuaToi;
          const place =
            item.hinhThuc === "online"
              ? "Trực tuyến (Online)"
              : `${item.tenCoSo || item.diaDiem || "Trực tiếp"}${item.phongHoc ? " · " + item.phongHoc : ""}${item.tenKhuVuc ? " · " + item.tenKhuVuc : ""}`;
          const statusCell = mine
            ? `${tag(mine.trangThai)}${mine.lyDoTuChoi ? `<span class="assistant-elig-note">Lý do: ${escape(mine.lyDoTuChoi)}</span>` : ""}`
            : item.duDieuKien
              ? '<span class="tag ok">Phù hợp lịch &amp; chuyên môn</span>'
              : `<span class="tag r">Chưa đủ điều kiện</span><span class="assistant-elig-note">${escape((item.lyDoKhongDuDieuKien || []).join(" "))}</span>`;
          const actionCell = mine
            ? mine.trangThai === "da_duyet"
              ? button("Xem ca trên lịch", "session", item.buoiHocId, "p")
              : '<span class="assistant-muted">Đã ứng tuyển</span>'
            : button(
                "Ứng tuyển Trợ giảng",
                "apply-shift",
                item.id,
                "p",
                !item.duDieuKien,
              );
          return `<tr>
            <td><b>${escape(item.maDeXuat)}</b><br><small>${escape(item.tenNguoiTao || "")}</small></td>
            <td><b>${escape(item.maLop)}</b><br><small>${escape(item.tenLop)} · ${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></td>
            <td><b>${date(item.ngay)}</b><br><small>${time(item.gioBatDau)}–${time(item.gioKetThuc)}</small></td>
            <td>${escape(place)}</td>
            <td><span class="tag purple">Trợ giảng (${item.soDaDuyet}/${item.soLuong})</span><br><small>${escape(item.yeuCau || "Theo chuẩn lớp học")}</small></td>
            <td>${statusCell}</td>
            <td>${actionCell}</td>
          </tr>`;
        }),
        "Hiện chưa có đề xuất tuyển Trợ giảng nào đang mở.",
      )}</div>
      <div class="card"><h2>Đơn ứng tuyển ca của tôi &amp; Kết quả duyệt</h2>${table(
        [
          "Mã đề xuất",
          "Lớp · Môn · Khối",
          "Ngày & Giờ ca",
          "Vị trí",
          "Lời nhắn",
          "Trạng thái duyệt",
          "Kết quả / Lý do từ chối",
          "Thao tác",
        ],
        myShiftApps.map((item) => {
          const mine = item.donCuaToi;
          const resultNote =
            mine.trangThai === "da_duyet"
              ? "Đã phân công chính thức lên thời khóa biểu cá nhân."
              : mine.trangThai === "tu_choi"
                ? `Từ chối: ${mine.lyDoTuChoi || "Chưa đạt yêu cầu."}`
                : "Đang chờ Giáo vụ duyệt (chưa hiển thị trên thời khóa biểu).";
          return `<tr>
            <td><b>${escape(item.maDeXuat)}</b></td>
            <td><b>${escape(item.maLop)}</b><br><small>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></td>
            <td><b>${date(item.ngay)}</b><br><small>${time(item.gioBatDau)}–${time(item.gioKetThuc)}</small></td>
            <td><span class="tag purple">Trợ giảng</span></td>
            <td>${escape(mine.ghiChu || "—")}</td>
            <td>${tag(mine.trangThai)}<br><small>${datetime(mine.duyetLuc || mine.createdAt)}</small></td>
            <td>${escape(resultNote)}</td>
            <td>${mine.trangThai === "da_duyet" ? button("Xem ca", "session", item.buoiHocId, "p") : "—"}</td>
          </tr>`;
        }),
        "Bạn chưa gửi đơn ứng tuyển ca nào.",
      )}</div>`
    );
  }
  function requests() {
    const rows = state.workspace.requests.filter(
      (request) => !state.requestFilter || request.loai === state.requestFilter,
    );
    return (
      head(
        "Đơn từ & Yêu cầu cá nhân",
        "Trợ giảng chỉ gửi đơn xin nghỉ cá nhân (Giáo vụ duyệt), đổi giờ hỗ trợ (Giáo vụ duyệt) và điều chỉnh công (Admin duyệt).",
        button("+ Làm đơn mới", "request", "", "p"),
      ) +
      `<div class="card"><div class="assistant-toolbar">${select("Loại yêu cầu", "request-filter", { "": "Tất cả đơn", bao_ban: "Xin nghỉ cá nhân", doi_gio: "Đổi giờ hỗ trợ", dieu_chinh_cong: "Điều chỉnh công" }, state.requestFilter)}</div>${table(
        [
          "Mã đơn",
          "Loại đơn",
          "Lớp / Buổi học",
          "Nội dung",
          "Ngày gửi",
          "Trạng thái",
          "Phản hồi",
        ],
        rows.map(
          (request) =>
            `<tr><td>${escape(request.ma)}</td><td>${escape(labels[request.loai] || request.loai)}</td><td><b>${escape(request.maLop)}</b><br>${date(request.ngay)}</td><td>${escape(request.lyDo)}</td><td>${datetime(request.createdAt)}</td><td>${tag(request.trangThai)}</td><td>${escape(request.phanHoi || "—")}</td></tr>`,
        ),
      )}</div>`
    );
  }
  function paysheet() {
    const sessions = state.workspace.sessions.filter(
      (session) =>
        session.ngay.startsWith(state.month) && Boolean(session.checkinLuc),
    );
    const minutes = sessions
      .filter((session) => session.trangThaiCong !== "tu_choi")
      .reduce((sum, session) => sum + Number(session.soPhutTinhCong || 0), 0);
    const period = periodStatus(state.month);
    return (
      head("Bảng công cá nhân", "", button("In bảng công", "print")) +
      `<div class="card"><div class="assistant-toolbar">${field("Tháng", "timesheet-month", state.month, "month", "required")}<b>${Math.floor(minutes / 60)} giờ ${minutes % 60} phút · ${sessions.filter((session) => session.checkoutLuc).length} ca đã check-out · Kỳ công: ${tag(period)}</b></div>${table(
        [
          "Ngày",
          "Lớp",
          "Khung giờ",
          "Check-in",
          "Check-out",
          "Phút hiện diện",
          "Phút công",
          "Đi trễ",
          "Trạng thái công",
          "Kỳ công",
          "Thao tác",
        ],
        sessions.map(
          (session) =>
            `<tr><td>${date(session.ngay)}</td><td>${escape(session.maLop)}</td><td>${time(session.gioBatDau)}–${time(session.gioKetThuc)}</td><td>${datetime(session.checkinLuc)}</td><td>${datetime(session.checkoutLuc)}</td><td>${session.soPhutThucTe ?? "—"}</td><td>${workMinutes(session)}</td><td>${Number(session.checkinTre) ? tag("cho_duyet", "Đi trễ").replace("Chờ xử lý", "Đi trễ") : tag("hop_le", "Đúng giờ").replace("Hợp lệ", "Đúng giờ")}</td><td>${tag(session.trangThaiCong)}</td><td>${tag(session.kyCong)}</td><td>${button("Xem ca", "session", session.id)}${session.quyenYeuCau?.coTheChinhCong ? " " + button("Điều chỉnh", "correction", session.id) : ""}</td></tr>`,
        ),
      )}</div>`
    );
  }
  async function render() {
    const version = ++renderVersion;
    document.querySelectorAll("#assistant-nav button").forEach((item) => {
      const active =
        item.dataset.id === state.tab ||
        (["session", "log"].includes(state.tab) &&
          item.dataset.id === "schedule");
      item.classList.toggle("on", active);
      if (active) item.setAttribute("aria-current", "page");
      else item.removeAttribute("aria-current");
    });
    if (["session", "log"].includes(state.tab)) {
      if (!state.sessionId) {
        content.innerHTML =
          head(
            screens[state.tab],
            "",
            button("← Thời khóa biểu", "tab", "schedule", "p"),
          ) +
          `<div class="card">${empty("Vui lòng chọn một ca trên thời khóa biểu để xem chi tiết và thực hiện Check-in / Check-out.")}</div>`;
        return;
      }
      content.innerHTML =
        head(screens[state.tab]) + '<div class="card">Đang tải ca học…</div>';
      try {
        const detail = await api(`${assistants}/sessions/${state.sessionId}`);
        if (version !== renderVersion) return;
        state.detail = detail;
        content.innerHTML =
          state.tab === "log" ? logView(detail) : sessionView(detail);
      } catch (error) {
        if (version === renderVersion)
          content.innerHTML =
            head(screens[state.tab]) +
            `<div class="assistant-error" role="alert">${escape(error.message)} ${button("Thử lại", "reload-session")}</div>`;
      }
      return;
    }
    const views = {
      dash: dashboard,
      schedule,
      myclasses: classes,
      recruit,
      requests,
      paysheet,
    };
    content.innerHTML = views[state.tab]();
  }
  async function navigate(tab, sessionId, refresh = true) {
    if (!screens[tab]) tab = "dash";
    if (state.dirty && !window.confirm("Sổ buổi học chưa lưu. Rời trang này?"))
      return;
    state.dirty = false;
    state.tab = tab;
    if (sessionId) state.sessionId = sessionId;
    menu(false);
    history.replaceState(null, "", "#/assistant/ta-" + tab);
    if (refresh) await load();
    await render();
  }
  function requestModal(type = "doi_gio", sessionId = state.sessionId) {
    if (!["bao_ban", "doi_gio", "dieu_chinh_cong"].includes(type))
      type = "doi_gio";
    const all = state.workspace.sessions;
    const sessions =
      type === "dieu_chinh_cong"
        ? all.filter((session) => Boolean(session.quyenYeuCau?.coTheChinhCong))
        : all.filter((session) => Boolean(session.quyenYeuCau?.coTheDoiLich));
    const selected =
      sessions.find((item) => String(item.id) === String(sessionId)) ||
      sessions[0];
    modal(
      "Làm đơn mới",
      `<form id="assistant-request-form"><div class="assistant-form-grid">${select("Loại yêu cầu", "loai", { doi_gio: "Yêu cầu đổi lịch / đổi giờ (Giáo vụ duyệt)", bao_ban: "Xin nghỉ cá nhân (Giáo vụ duyệt)", dieu_chinh_cong: "Yêu cầu chỉnh công (Admin duyệt)" }, type)}<div class="full">${select("Ca học *", "buoiHocId", Object.fromEntries(sessions.map((session) => [session.id, sessionTitle(session)])), selected?.id ?? "")}</div>
      ${type === "doi_gio" ? field("Ngày đề xuất *", "ngayMoi", selected?.ngay || "", "date", "required") + field("Giờ bắt đầu *", "gioBatDau", selected ? time(selected.gioBatDau) : "", "time", "required") + field("Giờ kết thúc *", "gioKetThuc", selected ? time(selected.gioKetThuc) : "", "time", "required") : ""}
      ${type === "dieu_chinh_cong" ? field("Check-in đề nghị *", "checkinMoi", selected?.checkinLuc || (selected ? selected.ngay + "T" + selected.gioBatDau : ""), "datetime-local", "required") + field("Check-out đề nghị *", "checkoutMoi", selected?.checkoutLuc || (selected ? selected.ngay + "T" + selected.gioKetThuc : ""), "datetime-local", "required") : ""}
      <div class="full">${textarea("Lý do *", "lyDo", "", 255)}</div></div>${!sessions.length ? empty("Chưa có ca phù hợp theo trạng thái hiện tại để gửi loại yêu cầu này.") : ""}${formFoot()}</form>`,
    );
    dialog.querySelector("[name=lyDo]").required = true;
    dialog.querySelector("[name=buoiHocId]").required = true;
    validateRequest();
  }
  function slotProblems(slots) {
    return slots.map((slot, index) => {
      if (!slot.start)
        return { field: "start", message: "Vui lòng nhập giờ bắt đầu." };
      if (!slot.end)
        return { field: "end", message: "Vui lòng nhập giờ kết thúc." };
      if (!validTime(slot.start))
        return {
          field: "start",
          message: "Giờ bắt đầu cần có dạng HH:mm, từ 00:00 đến 23:59.",
        };
      if (!validTime(slot.end))
        return {
          field: "end",
          message: "Giờ kết thúc cần có dạng HH:mm, từ 00:00 đến 23:59.",
        };
      if (slot.end <= slot.start)
        return { field: "end", message: "Giờ kết thúc phải sau giờ bắt đầu." };
      const other = slots.find(
        (item, otherIndex) =>
          otherIndex !== index &&
          item.day === slot.day &&
          item.start &&
          item.end > item.start &&
          slot.start < item.end &&
          slot.end > item.start,
      );
      if (other)
        return {
          field: "end",
          message: `Khung giờ này trùng với lịch ${slot.day === 8 ? "Chủ Nhật" : "Thứ " + slot.day}, ${other.start}–${other.end}.`,
        };
      return null;
    });
  }
  function validateAvailability() {
    const form = document.getElementById("assistant-availability-form");
    if (!form) return true;
    const slot = {
      day: Number(form.elements.thu.value),
      start: form.elements.start.value,
      end: form.elements.end.value,
    };
    const others = (state.workspace.profile.lichRanhs || [])
      .filter((item) => String(item.id) !== form.dataset.id)
      .map((item) => ({
        day: Number(item.thu),
        start: time(item.gioBatDau),
        end: time(item.gioKetThuc),
      }));
    const problem = slotProblems([slot, ...others])[0];
    form.querySelectorAll("input,select").forEach((control) => {
      control.setCustomValidity("");
      control.removeAttribute("aria-invalid");
    });
    if (problem) fieldError(form.elements[problem.field], problem.message);
    form.querySelectorAll("[data-time24]").forEach((control) => {
      if (!problem || control.name !== problem.field) fieldError(control);
    });
    form.querySelector("[type=submit]").disabled =
      Boolean(problem) || form.hasAttribute("aria-busy");
    return !problem;
  }
  function refreshAvailability() {
    document
      .querySelectorAll("[data-availability-calendar]")
      .forEach((calendar) => {
        calendar.outerHTML = availabilityCalendar();
      });
  }
  function availabilityBack() {
    if (availabilityInProfile) viewProfile();
    else dialog.close();
  }
  function availabilityModal(id = "", weekday = "") {
    const slot = (state.workspace.profile.lichRanhs || []).find(
      (item) => String(item.id) === String(id),
    );
    if (id && !slot) return;
    modal(
      slot ? "Sửa lịch rảnh" : "Thêm lịch rảnh",
      `<form id="assistant-availability-form" data-id="${escape(id)}"><div class="assistant-form-grid">
      <div class="full">${select("Ngày trong tuần", "thu", { 2: "Thứ Hai", 3: "Thứ Ba", 4: "Thứ Tư", 5: "Thứ Năm", 6: "Thứ Sáu", 7: "Thứ Bảy", 8: "Chủ Nhật" }, slot?.thu || weekday || 2)}</div>
      ${field("Từ giờ *", "start", slot ? time(slot.gioBatDau) : "08:00", "time", "required")}
      ${field("Đến giờ *", "end", slot ? time(slot.gioKetThuc) : "10:00", "time", "required")}
      </div>
      <p class="assistant-muted">Lịch rảnh lặp lại hàng tuần vào ngày đã chọn.</p>
      <p class="assistant-error" data-form-error role="alert" hidden></p>
      <div class="assistant-dialog-foot">${slot ? button("Xóa lịch", "delete-availability", id, "danger") : ""}${button("Hủy", "availability-back")}<button class="btn p" type="submit">Lưu lịch rảnh</button></div></form>`,
    );
    validateAvailability();
  }
  function accountMenu(open) {
    document.getElementById("assistant-account-menu").hidden = !open;
    document
      .getElementById("assistant-avatar")
      .setAttribute("aria-expanded", String(open));
  }
  function viewProfile() {
    if (dialog.open) dialog.close();
    AccountUI.view();
  }
  function notificationsModal() {
    modal(
      "Thông báo",
      `${button("Đánh dấu tất cả đã đọc", "read-all")}<div>${state.notifications.map((notification) => `<div class="assistant-row"><div><b>${escape(notification.tieuDe)}</b><p>${escape(notification.noiDung)}</p><small>${datetime(notification.createdAt)}</small></div>${Number(notification.daDoc) ? '<span class="assistant-muted">Đã đọc</span>' : button("Đánh dấu đã đọc", "read", notification.id)}</div>`).join("") || empty("Chưa có thông báo.")}</div>`,
    );
  }
  function rosterModal(id) {
    const item = state.workspace.classes.find(
      (classroom) => String(classroom.id) === String(id),
    );
    if (!item) return;
    modal(
      "Học viên · " + item.maLop,
      table(
        ["Mã học viên", "Họ tên", "Ngày vào", "Ngày rời", "Trạng thái"],
        item.hocViens.map(
          (student) =>
            `<tr><td>${escape(student.maHv)}</td><td>${escape(student.hoTen)}</td><td>${date(student.ngayVao)}</td><td>${date(student.ngayRoi)}</td><td>${tag(student.trangThai)}</td></tr>`,
        ),
      ),
    );
  }
  async function act(action, id, element) {
    switch (action) {
      case "print":
        window.print();
        return;
      case "tab":
        if (id === "schedule") state.classFilter = "";
        return navigate(id);
      case "session":
        return navigate("session", id);
      case "log":
        return navigate("log", id);
      case "close":
        dialog.close();
        return;
      case "account":
        accountMenu(document.getElementById("assistant-account-menu").hidden);
        return;
      case "profile-view":
        accountMenu(false);
        await profileUpdate;
        viewProfile();
        return;
      case "profile":
        accountMenu(false);
        dialog.close();
        AccountUI.edit();
        return;
      case "week-schedule":
        state.calendarMode = "week";
        state.calendarDate = today();
        state.classFilter = "";
        return navigate("schedule");
      case "class-schedule":
        state.classFilter = String(id);
        return navigate("schedule");
      case "roster":
        rosterModal(id);
        return;
      case "request":
        requestModal("doi_gio", id || state.sessionId);
        return;
      case "reschedule":
        requestModal("doi_gio", id || state.sessionId);
        return;
      case "correction":
        requestModal("dieu_chinh_cong", id);
        return;
      case "add-availability":
      case "edit-availability":
        if (element.closest("[data-availability-calendar]"))
          availabilityInProfile = Boolean(element.closest("#assistant-dialog,.account-dialog"));
        if (element.closest(".account-dialog")) AccountUI.close();
        availabilityModal(
          action === "edit-availability" ? id : "",
          action === "add-availability" ? id : "",
        );
        return;
      case "availability-back":
        availabilityBack();
        return;
      case "delete-availability": {
        const slot = (state.workspace.profile.lichRanhs || []).find(
          (item) => String(item.id) === String(id),
        );
        if (!slot) return;
        modal(
          "Xóa lịch rảnh",
          `<form id="assistant-availability-delete-form" data-id="${escape(id)}">
          <p>Xóa khung giờ ${Number(slot.thu) === 8 ? "Chủ Nhật" : "Thứ " + slot.thu}, ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)} khỏi lịch rảnh hàng tuần?</p>
          <p class="assistant-error" data-form-error role="alert" hidden></p><div class="assistant-dialog-foot">${button("Quay lại", "edit-availability", id)}<button type="submit" class="btn danger">Xóa lịch rảnh</button></div></form>`,
        );
        return;
      }
      case "notifications":
        notificationsModal();
        return;
      case "read":
        await api("/communication/notifications/" + id + "/read", "post", {});
        await load();
        notificationsModal();
        return;
      case "read-all":
        await api("/communication/notifications/read-all", "post", {});
        await load();
        notificationsModal();
        return;
      case "reload":
        await load();
        await render();
        return;
      case "reload-session":
        return navigate(state.tab);
      case "availability-mode":
        state.availabilityMode = id;
        return refreshAvailability();
      case "availability-today":
        state.availabilityDate = today();
        return refreshAvailability();
      case "availability-prev":
      case "availability-next": {
        const step = action === "availability-next" ? 1 : -1;
        if (state.availabilityMode === "week")
          state.availabilityDate = addDays(state.availabilityDate, step * 7);
        else {
          const value = new Date(
            state.availabilityDate.slice(0, 7) + "-01T12:00:00Z",
          );
          value.setUTCMonth(value.getUTCMonth() + step);
          state.availabilityDate = value.toISOString().slice(0, 10);
        }
        return refreshAvailability();
      }
      case "calendar-mode":
        state.calendarMode = id;
        return render();
      case "calendar-reset":
        state.classFilter = "";
        return render();
      case "calendar-today":
        state.calendarDate = today();
        return render();
      case "calendar-prev":
      case "calendar-next": {
        const step = action === "calendar-next" ? 1 : -1;
        if (state.calendarMode === "week")
          state.calendarDate = addDays(state.calendarDate, step * 7);
        else {
          const value = new Date(
            state.calendarDate.slice(0, 7) + "-01T12:00:00Z",
          );
          value.setUTCMonth(value.getUTCMonth() + step);
          state.calendarDate = value.toISOString().slice(0, 10);
        }
        return render();
      }
      case "checkin":
      case "checkout":
        if (
          action === "checkout" &&
          !window.confirm("Xác nhận kết thúc ca hỗ trợ và ghi giờ Check-out?")
        )
          return;
        await api(`${assistants}/sessions/${id}/${action}`, "post", {});
        await load();
        await render();
        toast(
          action === "checkin"
            ? "Đã ghi nhận Check-in."
            : "Đã ghi nhận Check-out.",
        );
        return;
      case "apply-shift": {
        const opening = state.shiftOpenings.find(
          (item) => String(item.id) === String(id),
        );
        if (!opening) return;
        modal(
          "Ứng tuyển vị trí Trợ giảng",
          `<form id="assistant-shift-apply-form" data-id="${escape(opening.id)}">
            <p><b>${escape(opening.maDeXuat)} · ${escape(opening.maLop)}</b> — ${escape(opening.tenLop)}</p>
            <p class="assistant-muted">Ca ngày ${date(opening.ngay)} · ${time(opening.gioBatDau)}–${time(opening.gioKetThuc)} · ${escape(opening.tenMon)} · ${escape(opening.tenCapLop)}</p>
            ${opening.yeuCau ? `<p class="assistant-muted">Yêu cầu: ${escape(opening.yeuCau)}</p>` : ""}
            ${textarea("Lời nhắn cho Giáo vụ (tùy chọn)", "ghiChu", "", 500)}
            ${formFoot("Gửi đơn ứng tuyển")}
          </form>`,
        );
        return;
      }
      case "logout":
        if (state.dirty && !window.confirm("Sổ buổi học chưa lưu. Đăng xuất?"))
          return;
        state.dirty = false;
        await AccountUI.logout();
        return;
      default:
        return;
    }
  }
  document.addEventListener("click", async (event) => {
    if (!event.target.closest(".assistant-account")) accountMenu(false);
    const element = event.target.closest("[data-action]");
    if (!element || element.disabled) return;
    element.disabled = true;
    try {
      await act(element.dataset.action, element.dataset.id, element);
    } catch (error) {
      toast(error.message);
    } finally {
      element.disabled = false;
    }
  });
  document.addEventListener("change", async (event) => {
    const { name, value } = event.target;
    if (event.target.closest("#assistant-availability-form"))
      validateAvailability();
    if (
      event.target.closest("#assistant-request-form") &&
      event.target.name !== "loai"
    )
      validateRequest();
    if (
      event.target.closest("#assistant-log-form") &&
      event.target.matches("[data-time24],input[type=date]")
    )
      validateTimeFields(event.target.form);
    if (event.target.closest("#assistant-log-form")) state.dirty = true;
    if (event.target.closest("#assistant-request-form") && name === "loai") {
      requestModal(value, dialog.querySelector("[name=buoiHocId]")?.value);
      return;
    }
    const fields = {
      "calendar-date": "calendarDate",
      "class-filter": "classFilter",
      "request-filter": "requestFilter",
      "timesheet-month": "month",
    };
    if (name === "availability-month" && value) {
      state.availabilityDate = value + "-01";
      refreshAvailability();
      return;
    }
    if (name === "calendar-month" && value) {
      state.calendarDate = value + "-01";
      await render();
      return;
    }
    if (fields[name] && value !== undefined) {
      if (
        (name === "calendar-date" && !value) ||
        (name === "timesheet-month" && !value)
      )
        return;
      state[fields[name]] = value;
      await render();
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") accountMenu(false);
  });
  document.addEventListener(
    "invalid",
    (event) => {
      const student = event.target.closest("details.assistant-student");
      if (student) student.open = true;
    },
    true,
  );
  document.addEventListener("input", (event) => {
    if (event.target.closest("#assistant-availability-form"))
      validateAvailability();
    if (
      event.target.closest("#assistant-request-form") &&
      event.target.name !== "loai"
    )
      validateRequest();
    if (
      event.target.closest("#assistant-log-form") &&
      event.target.matches("[data-time24],input[type=date]")
    )
      validateTimeFields(event.target.form);
    if (event.target.closest("#assistant-log-form")) state.dirty = true;
  });
  document.addEventListener("submit", async (event) => {
    const form = event.target;
    if (!form.id.startsWith("assistant-")) return;
    event.preventDefault();
    if (!validateTimeFields(form)) return;
    if (form.id === "assistant-request-form" && !validateRequest()) return;
    if (form.id === "assistant-availability-form" && !validateAvailability())
      return;
    const errorElement = form.querySelector("[data-form-error]");
    errorElement.hidden = true;
    form.setAttribute("aria-busy", "true");
    const submitters = [...form.querySelectorAll("[type=submit]")];
    submitters.forEach((item) => {
      item.disabled = true;
    });
    const values = new FormData(form);
    try {
      if (form.id === "assistant-request-form") {
        const body = Object.fromEntries(
          [...values].filter(
            ([key]) => !/^(checkinMoi|checkoutMoi)(Date|Time)$/.test(key),
          ),
        );
        if (body.loai === "dieu_chinh_cong") {
          body.checkinMoi = dateTimeValue(values, "checkinMoi");
          body.checkoutMoi = dateTimeValue(values, "checkoutMoi");
        }
        body.buoiHocId = Number(body.buoiHocId);
        await api(assistants + "/requests", "post", body);
      } else if (form.id === "assistant-availability-form") {
        await api(
          assistants +
            "/availability" +
            (form.dataset.id ? "/" + form.dataset.id : ""),
          form.dataset.id ? "put" : "post",
          {
            thu: Number(values.get("thu")),
            gioBatDau: values.get("start"),
            gioKetThuc: values.get("end"),
          },
        );
      } else if (form.id === "assistant-shift-apply-form") {
        await api(
          "/academic/scheduling/openings/" + form.dataset.id + "/apply",
          "post",
          {
            ghiChu: values.get("ghiChu")?.trim() || null,
          },
        );
      } else if (form.id === "assistant-availability-delete-form") {
        await api(assistants + "/availability/" + form.dataset.id, "delete");
      } else if (form.id === "assistant-log-form") {
        const number = (name) =>
          values.get(name) === "" || values.get(name) === null ? null : Number(values.get(name));
        const hwIdRaw = values.get("baiTapChamId");
        const body = {
          revision: state.detail.revision,
          chuDe: values.get("chuDe"),
          noiDung: values.get("noiDung"),
          baiTapChamId: hwIdRaw ? Number(hwIdRaw) : null,
          hocViens: state.detail.hocViens.map((student) => ({
            hocVienId: Number(student.hocVienId),
            trangThaiDiemDanh: values.get("attendance-" + student.hocVienId),
            diemBaiTapGiay: number("score-" + student.hocVienId),
            nhanXetBaiTap: values.get("comment-" + student.hocVienId) || null,
            mucTiepThu: number("level-" + student.hocVienId),
            thaiDo: values.get("attitude-" + student.hocVienId) || null,
            nhanXetBuoiHoc: values.get("note-" + student.hocVienId),
            huongCaiThien: values.get("improve-" + student.hocVienId),
          })),
        };
        state.detail = await api(
          `${assistants}/sessions/${state.sessionId}/log`,
          "put",
          body,
        );
        state.dirty = false;
        if (event.submitter?.value === "checkout") {
          try {
            await api(
              `${assistants}/sessions/${state.sessionId}/checkout`,
              "post",
              {},
            );
          } catch (error) {
            await load();
            await render();
            toast("Sổ đã lưu. Check-out chưa thành công. " + error.message);
            return;
          }
        }
      }
      await load();
      dialog.close();
      await render();
      if (form.id.startsWith("assistant-availability-") && availabilityInProfile)
        viewProfile();
      toast(
        form.id === "assistant-availability-delete-form"
          ? "Đã xóa lịch rảnh."
          : form.id === "assistant-shift-apply-form"
            ? "Đã gửi đơn ứng tuyển. Chờ Giáo vụ phê duyệt."
            : "Đã lưu thành công.",
      );
    } catch (error) {
      errorElement.textContent = error.message;
      errorElement.hidden = false;
      errorElement.scrollIntoView({ block: "nearest" });
    } finally {
      form.removeAttribute("aria-busy");
      submitters.forEach((item) => {
        item.disabled = false;
      });
      if (form.id === "assistant-availability-form" && form.isConnected)
        validateAvailability();
      if (form.id === "assistant-request-form" && form.isConnected)
        validateRequest();
    }
  });
  document
    .getElementById("assistant-menu")
    .addEventListener("click", () =>
      menu(!document.body.classList.contains("menu-open")),
    );
  document
    .getElementById("assistant-backdrop")
    .addEventListener("click", () => menu(false));
  window.addEventListener("focus", async () => {
    if (!state.workspace || state.dirty || dialog.open) return;
    try {
      await load();
      await render();
    } catch (error) {
      toast(error.message);
    }
  });
  window.addEventListener("beforeunload", (event) => {
    if (state.dirty) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  window.addEventListener("hashchange", () =>
    navigate(location.hash.split("ta-")[1] || "dash"),
  );
  document.getElementById("assistant-nav").innerHTML = Object.entries(screens)
    .map(([key, label], index) =>
      key === "log" || key === "session"
        ? ""
        : `<button type="button" data-action="tab" data-id="${key}"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24">${icons[index]}</svg></span>${label}</button>`,
    )
    .join("");
  window.addEventListener("account-profile-updated", () => {
    profileUpdate = load()
      .then(() => {
        if (!state.dirty) return render();
      })
      .catch((error) => toast(error.message));
  });
  AccountUI.setProfileView(async () => {
    await profileUpdate;
    return availabilityCalendar();
  });
  load()
    .then(() =>
      navigate(location.hash.split("ta-")[1] || "dash", null, false),
    )
    .catch((error) => {
      content.innerHTML =
        head("Cổng Trợ giảng") +
        `<div class="assistant-error" role="alert">${escape(error.message)} ${button("Thử lại", "reload")}</div>`;
    });
})();
