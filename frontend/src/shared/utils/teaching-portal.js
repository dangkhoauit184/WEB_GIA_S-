/* Shared Teaching Portal engine for Teacher, Tutor, and Teaching Assistant roles. */
window.TeachingPortal = (() => {
  "use strict";

  const ICONS = [
    '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    '<path d="M4 3h16v18H4zM8 7h8M8 11h8M8 15h5"/>',
    '<path d="m3 10 9-7 9 7v11H3zM9 21v-8h6v8"/>',
    '<path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>',
    '<path d="M14 3H4v18h16V9M14 3v6h6M8 13h8M8 17h5"/>',
    '<path d="M4 3h16v18H4zM8 7h8M8 11h8M8 15h8M8 18h4"/>',
  ];

  const CLASS_TYPE_LABELS = {
    kem_1_1: "Kèm 1:1",
    kem_1_2: "Kèm 1:2",
    nhom: "Lớp nhóm",
  };

  const BASE_LABELS = {
    du_kien: "Sắp diễn ra",
    dang_day: "Đang dạy",
    da_day: "Đã dạy",
    huy: "Đã hủy",
    doi: "Đã đổi lịch",
    cho_duyet: "Chờ xử lý",
    da_duyet: "Đã duyệt",
    tu_choi: "Từ chối",
    da_huy: "Đã hủy",
    cho: "Chờ phản hồi",
    dong_y: "Đồng ý",
    dang_hoc: "Đang học",
    sap_bat_dau: "Sắp bắt đầu",
    hoan_thanh: "Hoàn thành",
    tam_dung: "Tạm dừng",
    nghi_day: "Nghỉ cả lớp",
    doi_lich: "Đổi lịch",
    bao_ban: "Xin nghỉ cá nhân",
    doi_gio: "Đổi giờ hỗ trợ",
    dieu_chinh_cong: "Điều chỉnh công",
    xin_lop: "Xin nhận lớp",
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

  function mount(cfg) {
    if (!AccessService.requireAuth([cfg.role])) return;

    const p = cfg.prefix;
    const screens = cfg.screens;
    const labels = { ...BASE_LABELS, ...(cfg.statusLabels || {}) };
    const text = cfg.text;
    const ep = cfg.endpoints;

    const state = {
      workspace: null,
      proposals: [],
      opportunities: [],
      shiftOpenings: [],
      notifications: [],
      tab: "dash",
      sessionId: null,
      detail: null,
      dirty: false,
      materialDirty: false,
      calendarMode: "week",
      calendarDate: "",
      availabilityMode: "week",
      availabilityDate: "",
      requestFilter: "",
      month: "",
      classFilter: "",
    };

    const content = document.getElementById(`${p}-content`);
    const dialog = document.getElementById(`${p}-dialog`);
    const dialogContent = document.getElementById(`${p}-dialog-content`);
    let toastTimer;
    let renderVersion = 0;
    let availabilityInProfile = false;
    let profileUpdate = Promise.resolve();

    const escape = (value) =>
      String(value ?? "").replace(
        /[&<>"']/g,
        (ch) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[ch],
      );
    const api = async (path, method = "get", body) =>
      (await HttpService[method](path, body)).data;
    const empty = (msg) => `<div class="tp-empty">${escape(msg)}</div>`;
    const button = (label, action, id = "", style = "", disabled = false) =>
      `<button type="button" class="btn sm ${style}" data-action="${action}" data-id="${escape(id)}" ${disabled ? "disabled" : ""}>${label}</button>`;
    const head = (title, subtitle = "", actions = "") =>
      `<div class="pg-head"><div><h1>${escape(title)}</h1>${subtitle ? `<p class="sub">${escape(subtitle)}</p>` : ""}</div><div class="tp-actions">${actions}</div></div>`;
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
      session.vaiTro !== cfg.vaiTro;
    const isSessionDone = (session) =>
      cfg.useAssignmentDoneStatus
        ? session.phanCongTrangThai === "da_day"
        : session.trangThai === "da_day";
    const classTypeTag = (value) =>
      cfg.showClassType && value
        ? `<span class="tag ${value === "nhom" ? "w" : "i"}">${escape(CLASS_TYPE_LABELS[value] || value)}</span>`
        : "";
    const formatPlace = (session, missingText = "Trực tuyến") =>
      session.hinhThuc === "online"
        ? "Trực tuyến"
        : session.tenCoSo || session.diaDiem || missingText;

    function tag(value, fallback) {
      const style = [
        "da_day",
        "da_duyet",
        "dong_y",
        "hop_le",
        "dang_hoc",
        "da_du_nguoi",
      ].includes(value)
        ? "ok"
        : ["huy", "tu_choi", "da_huy", "da_dong"].includes(value)
          ? "r"
          : ["cho", "cho_duyet", "du_kien", "dang_mo"].includes(value)
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
      const element = document.getElementById(`${p}-toast`);
      element.textContent = message;
      element.hidden = false;
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => {
        element.hidden = true;
      }, 5000);
    }

    function menu(open) {
      document.body.classList.toggle("menu-open", open);
      document.getElementById(`${p}-backdrop`).hidden = !open;
      document
        .getElementById(`${p}-menu`)
        .setAttribute("aria-expanded", String(open));
    }

    function modal(title, body) {
      dialogContent.innerHTML = `<div class="tp-dialog-head"><h2 id="${p}-dialog-title">${escape(title)}</h2>${button("✕", "close", "", "", false)}</div><div class="tp-dialog-body">${body}</div>`;
      if (!dialog.open) dialog.showModal();
    }

    function formFoot(label = "Gửi yêu cầu") {
      return `<p class="tp-error" data-form-error role="alert" hidden></p><div class="tp-dialog-foot">${button("Hủy", "close")}<button class="btn p" type="submit">${label}</button></div>`;
    }

    function field(label, name, value = "", type = "text", extra = "") {
      if (type === "datetime-local") {
        const parts = value.replace(" ", "T").split("T");
        return `<fieldset class="tp-date-time" data-date-time="${name}"><legend>${escape(label)}</legend><div class="tp-form-grid">
          ${field("Ngày", name + "Date", parts[0] || "", "date", extra)}
          ${field("Giờ", name + "Time", parts[1]?.slice(0, 5) || "", "time", extra)}</div></fieldset>`;
      }
      if (type === "time") {
        return `<div class="tp-field"><label for="${p}-input-${name}">${escape(label)} (HH:mm)</label><input id="${p}-input-${name}" name="${name}" type="text" data-time24 value="${escape(value)}" placeholder="HH:mm" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" aria-describedby="${p}-error-${name}" ${extra}><small id="${p}-error-${name}" class="tp-field-error" data-field-error aria-live="polite" hidden></small></div>`;
      }
      const control = `<label>${escape(label)}<input name="${name}" type="${type}" value="${escape(value)}" ${extra}${type === "date" ? ` aria-describedby="${p}-error-${name}"` : ""}></label>`;
      return type === "date"
        ? `<div class="tp-field">${control}<small id="${p}-error-${name}" class="tp-field-error" data-field-error aria-live="polite" hidden></small></div>`
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
        .closest(".tp-field")
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
      const form = document.getElementById(`${p}-request-form`);
      if (!form) return true;
      let valid = validateTimeFields(form);
      if (valid && form.elements.loai.value === cfg.rescheduleType) {
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
        (item) => Number(item.nam) === y && Number(item.thang) === m,
      );
      return hit ? hit.trangThai : "mo";
    }

    async function allProposals() {
      const items = [];
      let page = 1;
      let total;
      do {
        const response = await api(
          `/academic/admissions/proposals?perPage=100&page=${page++}`,
        );
        items.push(...response.items);
        total = response.total;
      } while (items.length < total);
      return items;
    }

    async function load() {
      if (cfg.hasProposals) {
        const [workspace, proposals, opportunities, shiftOpenings, notifications] =
          await Promise.all([
            api(ep.workspace),
            allProposals(),
            api(ep.opportunities),
            api("/academic/scheduling/openings"),
            api("/communication/notifications"),
          ]);
        state.workspace = workspace;
        state.proposals = Array.isArray(proposals) ? proposals : [];
        state.opportunities = Array.isArray(opportunities) ? opportunities : [];
        state.shiftOpenings = Array.isArray(shiftOpenings) ? shiftOpenings : [];
        state.notifications = Array.isArray(notifications) ? notifications : [];
      } else {
        const [workspace, shiftOpenings, notifications] = await Promise.all([
          api(ep.workspace),
          api("/academic/scheduling/openings"),
          api("/communication/notifications"),
        ]);
        state.workspace = workspace;
        state.shiftOpenings = Array.isArray(shiftOpenings) ? shiftOpenings : [];
        state.notifications = Array.isArray(notifications) ? notifications : [];
      }

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
      document.getElementById(`${p}-name`).textContent = profile.hoTen;
      document.getElementById(`${p}-avatar`).textContent = AccountUI.initial(
        profile.hoTen,
      );
      document.getElementById(`${p}-date`).textContent =
        new Intl.DateTimeFormat("vi-VN", {
          dateStyle: "full",
          timeZone: "UTC",
        }).format(new Date(today() + "T12:00:00Z"));
      const unread = state.notifications.filter(
        (notification) => !Number(notification.daDoc),
      ).length;
      document.getElementById(`${p}-unread`).textContent = unread;
      document.getElementById(`${p}-unread`).hidden = !unread;
    }

    function sessionRows(sessions) {
      return sessions.map(
        (session) =>
          `<tr><td><b>${escape(session.maLop)}</b>${cfg.showClassType ? " " + classTypeTag(session.loaiLop) : ""}<br><small>${escape(session.tenLop)}</small></td><td><b>${date(session.ngay)}</b><br><small>${time(session.gioBatDau)}–${time(session.gioKetThuc)} · ${session.loai === "bu" ? "Học bù" : "Thường"}</small></td><td>${escape(formatPlace(session, cfg.placeFallback || "Online"))}${session.phongHoc ? `<br><small>${escape(session.phongHoc)}</small>` : ""}</td><td>${tag(session.trangThai)}</td><td class="act-col"><div class="act-row">${button("Xem ca", "session", session.id)}${button("Ghi sổ", "log", session.id)}</div></td></tr>`,
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
        return `<span class="tag w">${escape(text.onTimeBadge)}</span>`;
      return session.loai === "bu"
        ? '<span class="tag purple">Học bù · Sắp tới</span>'
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
          ? text.noTodayShift
          : sessions.every((item) => item.checkoutLuc)
            ? text.allTodayDone
            : "Các ca hôm nay đã qua khung giờ dự kiến.";
        return `<section class="tp-session-banner tp-dashboard-banner"><div><span class="tag">${escape(text.todayTag)} · ${date(today())}</span><h2>${message}</h2></div>${button("Xem lịch tuần", "week-schedule", "", "p")}</section>`;
      }
      const classroom = state.workspace.classes.find(
        (item) => String(item.id) === String(session.lopId),
      );
      const toMinutes = (value) =>
        Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
      const duration = toMinutes(session.gioKetThuc) - toMinutes(session.gioBatDau);
      const heading = session.checkinLuc
        ? "Ca đã Check-in hôm nay"
        : session.ngay + " " + session.gioBatDau <= now
          ? text.todayInProgress
          : text.todayUpcoming;
      const placePrefix = cfg.showClassType
        ? `${escape(CLASS_TYPE_LABELS[session.loaiLop] || "Lớp kèm")} · `
        : "";
      return `<section class="tp-session-banner tp-dashboard-banner"><div><span class="tag">${heading}</span>
        <h2>${escape(session.maLop)} — ${escape(session.tenLop)}</h2>
        <p>${placePrefix}${escape(formatPlace(session, cfg.placeDetailFallback || "Trực tuyến"))} ${escape(session.phongHoc || "")}</p>
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
      const pending = cfg.hasProposals
        ? state.proposals.filter(
            (proposal) =>
              proposal.ttNhanSu === "cho" &&
              !["da_chot", "huy"].includes(proposal.trangThaiYeuCau),
          )
        : [];
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
        [sessions.length, `${text.statToday} · ${date(today())}`],
        [workspace.classes.length, text.statClasses],
        [pending.length + openShifts.length, text.statOpenings],
        [minutes, "Phút công tháng này"],
      ];

      const openingsHtml =
        openShifts.length || pending.length
          ? openShifts
              .slice(0, cfg.hasProposals ? 3 : 5)
              .map(
                (item) =>
                  `<div class="tp-row"><div><b>${escape(item.maDeXuat)} · ${escape(item.maLop)}</b>${cfg.showClassType ? " " + classTypeTag(item.loaiLop) : ""}<small>${date(item.ngay)} · ${time(item.gioBatDau)}–${time(item.gioKetThuc)} · ${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></div>${button("Ứng tuyển", "tab", "recruit", "p")}</div>`,
              )
              .join("") +
            pending
              .slice(0, 3)
              .map(
                (proposal) =>
                  `<div class="tp-row"><div><b>${escape(proposal.maYc)}</b><small>${escape(proposal.tenHocVien || proposal.hoTenLienHe || text.newRequestFallback)}</small></div>${button("Xem đề xuất", "tab", "recruit")}</div>`,
              )
              .join("")
          : empty(text.noOpenings);

      const reqBtnLabel =
        !cfg.hasProposals && pendingRequests.length
          ? `Gửi đơn từ (${pendingRequests.length} chờ)`
          : "Gửi đơn từ";

      return (
        head(
          `Xin chào, ${workspace.profile.hoTen}`,
          `Hôm nay ${date(today())} · Tuần ${date(weekStart)} – ${date(addDays(weekStart, 6))}${text.dashSubtitleSuffix || "."}`,
          button(text.viewScheduleBtn, "tab", "schedule", "p"),
        ) +
        `<div class="tp-stats">${stats.map(([value, label]) => `<div class="card tp-stat"><strong>${value}</strong><span>${label}</span></div>`).join("")}</div>` +
        `<div class="tp-grid"><div>${todayBanner(sessions)}<div class="card tp-week-assignments"><div class="row-b"><div><h2>Các ca phân công trong tuần</h2><p class="tp-muted">${date(weekStart)} – ${date(addDays(weekStart, 6))}</p></div>${button("Xem lịch tuần", "week-schedule")}</div>${table(
          [
            "Ngày · Giờ",
            "Lớp · Địa điểm",
            "Vai trò",
            "Trạng thái",
            "Thao tác",
          ],
          weekSessions.map(
            (session) => `<tr><td><b>${dayLabel(session.ngay)}</b><br><small>${time(session.gioBatDau)}–${time(session.gioKetThuc)}</small></td>
          <td><b>${escape(session.maLop)}</b> · ${escape(session.tenMon)}<br><small>${escape(session.phongHoc ? session.phongHoc + " · " : "")}${escape(formatPlace(session, cfg.placeFallback || "Online"))}</small></td>
          <td>${cfg.showClassType ? classTypeTag(session.loaiLop) + "<br>" : ""}<span class="tag ${session.loai === "bu" ? "purple" : "i"}">${session.loai === "bu" ? escape(text.makeupShiftTag) : escape(text.roleName)}</span></td>
          <td>${dashboardStatus(session)}</td><td class="act-col"><div class="act-row">${button(session.checkinLuc ? "Xem ca" : "Chi tiết", "session", session.id, session.ngay === today() && !session.checkoutLuc ? "p" : "")}${session.checkinLuc ? button(text.logBtnLabel, "log", session.id) : ""}</div></td></tr>`,
          ),
        )}</div>
        <div class="card"><h2>${escape(text.dashOpeningsHeading)}</h2>${openingsHtml}</div></div>
        <div><div class="card"><h2>${escape(text.workCardHeading)}</h2><div class="tp-row">${button("Mở thời khóa biểu", "tab", "schedule", "p")}</div><div class="tp-row">${button(screens.recruit, "tab", "recruit")}</div><div class="tp-row">${button("Hồ sơ & Lịch rảnh", "profile-view")}</div><div class="tp-row">${button(reqBtnLabel, "request")}</div><div class="tp-row">${button("Xem bảng công cá nhân", "tab", "paysheet")}</div></div>
        <div class="card"><h2>Thông báo gần đây</h2>${
          state.notifications
            .slice(0, 4)
            .map(
              (notification) =>
                `<div class="tp-row"><div><b>${escape(window.ECDate ? window.ECDate.replaceInlineDates(notification.tieuDe) : notification.tieuDe)}</b><small>${escape(window.ECDate ? window.ECDate.replaceInlineDates(notification.noiDung) : notification.noiDung)}</small></div></div>`,
            )
            .join("") || empty("Chưa có thông báo.")
        }${button("Xem thông báo", "notifications")}</div></div></div>`
      );
    }

    function calendarStatus(session) {
      if (["huy", "doi"].includes(session.trangThai)) return "cancelled";
      if (session.checkoutLuc || isSessionDone(session)) return "completed";
      if (session.checkinLuc) return "active";
      return session.loai === "bu" ? "makeup" : "upcoming";
    }

    function calendarCard(session, compact = false) {
      const status = calendarStatus(session);
      const statusLabel = {
        cancelled: "Đã hủy / Đổi lịch",
        completed: "Hoàn tất",
        active: "Đã Check-in",
        makeup: text.calendarMakeupLabel,
        upcoming: "",
      }[status];
      const title = escape(sessionTitle(session) + (statusLabel ? " · " + statusLabel : ""));
      if (compact)
        return `<button type="button" class="cal-m-chip ${status}" data-action="session" data-id="${session.id}" title="${title}"><span>${time(session.gioBatDau)}</span><b>${escape(session.maLop)}</b>${session.checkinLuc ? "<span>✓</span>" : ""}</button>`;
      return `<button type="button" class="ses-card ${status}" data-action="session" data-id="${session.id}" title="${title}">
        ${statusLabel ? `<span class="tp-calendar-badge">${escape(statusLabel)}</span>` : ""}
        <span class="sc-code">${escape(session.maLop)}</span>
        <span class="sc-subj">${escape(session.tenMon)} · ${escape(session.tenLop)}</span>
        <span class="sc-time">${time(session.gioBatDau)} – ${time(session.gioKetThuc)}</span>
        <span class="sc-loc">${escape(formatPlace(session, "Chưa cập nhật địa điểm"))} ${escape(session.phongHoc || "")}</span>
        ${session.loai === "bu" && status !== "makeup" ? '<span class="tp-muted">Buổi học bù</span>' : ""}
      </button>`;
    }

    function calendarPeriod(selected, view) {
      const first = selected.slice(0, 7) + "-01";
      const start = monday(view === "month" ? first : selected);
      const nextMonth = new Date(first + "T12:00:00Z");
      nextMonth.setUTCMonth(nextMonth.getUTCMonth() + 1);
      const monthEnd = addDays(nextMonth.toISOString().slice(0, 10), -1);
      const count = (Date.parse(monthEnd) - Date.parse(start)) / 86400000 + 1;
      const endWeek = addDays(start, 6);
      return {
        start,
        days: view === "month" ? Math.ceil(count / 7) * 7 : 7,
        range:
          view === "week"
            ? `Tuần: ${date(start).slice(0, 5)} – ${date(endWeek)}`
            : `Tháng ${selected.slice(5, 7)} / ${selected.slice(0, 4)}`,
        headings: ["THỨ 2", "THỨ 3", "THỨ 4", "THỨ 5", "THỨ 6", "THỨ 7", "CN"],
      };
    }

    const CAL_ICON_SVG = '<svg class="svg-ico" style="color:var(--pri)" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';

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
        <div class="cal-bar"><div class="row" style="gap:6px"><button type="button" class="cal-nav-btn" data-action="calendar-prev">‹ Trước</button>${button("Hôm nay", "calendar-today")}<button type="button" class="cal-nav-btn" data-action="calendar-next">Sau ›</button></div>
          <div><button type="button" class="cal-range-btn" data-action="calendar-pick-month" title="Chọn nhanh tháng / năm">${CAL_ICON_SVG}<span>${escape(range)}</span><span style="font-size:10px;color:var(--g400)">▾</span></button></div>
          <div class="seg"><button type="button" class="${view === "week" ? "on" : ""}" data-action="calendar-mode" data-id="week">Lịch Tuần</button><button type="button" class="${view === "month" ? "on" : ""}" data-action="calendar-mode" data-id="month">Lịch Tháng</button><button type="button" class="${view === "list" ? "on" : ""}" data-action="calendar-mode" data-id="list">Danh sách</button></div>
        </div>
        <div class="cal-filter-bar">${select("Lớp học", "class-filter", { "": "Tất cả lớp", ...Object.fromEntries(state.workspace.classes.map((item) => [item.id, item.maLop])) }, state.classFilter)}${button("Đặt lại lọc", "calendar-reset")} ${button("Tải lại lịch", "reload")}</div>
        <div class="tp-calendar-scroll">${view === "list" ? table(["Lớp", "Ngày · Giờ", "Địa điểm", "Trạng thái", "Thao tác"], sessionRows(sessions.filter((session) => session.ngay.startsWith(selected.slice(0, 7))))) : view === "month" ? `<div class="tp-month-view"><div class="cal-month-head">${headings.map((label) => `<div class="cal-month-head-cell">${label}</div>`).join("")}</div><div class="cal-month-grid">${cells}</div></div>` : `<div class="cal-week-grid">${cells}</div>`}</div>
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
      return `<span class="tp-work-minutes">${escape(session.soPhutTinhCong)} phút${reason ? `<small>${escape(reason)}</small>` : ""}</span>`;
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
        return `<p class="tp-muted">${escape(text.emptyMaterial)}</p>`;
      }
      const metaParts = [];
      if (mat.laTepTaiLen && mat.tenTep) {
        metaParts.push(
          `${mat.tenTep}${mat.kichThuoc ? " · " + formatBytes(mat.kichThuoc) : ""}`,
        );
      } else if (mat.url) {
        metaParts.push(mat.url);
      }
      return `<div class="tp-material-current">
        <div><b>${escape(mat.tieuDe || mat.tenTep || "Tài liệu chuẩn bị buổi học")}</b>
        ${metaParts.length ? `<small class="tp-muted" style="display:block">${escape(metaParts.join(" · "))}</small>` : ""}
        ${mat.ghiChu ? `<p class="tp-muted" style="margin:4px 0 0">${escape(mat.ghiChu)}</p>` : ""}</div>
        <div class="tp-actions">${link(mat.url, mat.laTepTaiLen ? "Xem / Tải tệp" : "Mở liên kết tài liệu")}</div>
      </div>`;
    }

    function materialCard(session) {
      if (!cfg.canEditMaterial) {
        return `<div class="card"><h2>Tài liệu chuẩn bị trước buổi học</h2>
          ${materialSummary(session)}
          <p class="tp-muted" style="margin:8px 0 0;font-size:12px">Trợ giảng chỉ xem/tải tài liệu của ca được phân công; quyền tải lên hoặc chỉnh sửa tệp thuộc Giáo viên chính.</p>
        </div>`;
      }
      const mat = session.taiLieu || {};
      const editable =
        !["huy", "doi"].includes(session.trangThai) &&
        session.vaiTro === "day_chinh";
      return `<div class="card"><h2>Tài liệu &amp; Bài tập chuẩn bị trước buổi học</h2>
        <p class="tp-muted" style="margin-top:-6px">Tài liệu được chuẩn bị trước buổi dạy để học viên trong lớp xem trước (tệp có thể bao gồm cả bài giảng và bài tập về nhà).</p>
        ${materialSummary(session)}
        ${
          editable
            ? `<form id="${p}-material-form" data-session-id="${session.id}" style="margin-top:12px">
          <div class="tp-form-grid">
            <div class="full"><label>Tải tệp từ thiết bị (PDF, Word, PowerPoint, Excel, TXT, PNG, JPG · tối đa 10 MB)<input name="file" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg"></label><small class="tp-muted" data-selected-file style="display:block;margin-top:4px"></small></div>
            ${field("Tiêu đề tài liệu / bài tập xem trước", "tieuDe", mat.tieuDe || "", "text", 'maxlength="255" placeholder="Ví dụ: Bài giảng & BTVN tuần 4"')}
            ${field("Hoặc liên kết tài liệu xem trước", "fileUrl", !mat.laTepTaiLen ? mat.url || "" : "", "url", 'maxlength="255" placeholder="https://..."')}
            <div class="full">${textarea("Hướng dẫn chuẩn bị trước buổi học (tùy chọn)", "ghiChu", mat.ghiChu || "", 15000)}</div>
          </div>
          <p class="tp-error" data-form-error role="alert" hidden></p>
          <div class="tp-actions" style="margin-top:10px">
            <button class="btn p sm" type="submit">Tải lên &amp; Lưu tài liệu</button>
            ${mat.coTaiLieu ? button("Gỡ tài liệu", "remove-material", session.id, "danger") : ""}
          </div>
        </form>`
            : ""
        }
      </div>`;
    }

    function shiftRequestControls(session) {
      const caps = session.quyenYeuCau || {};
      const rows = [];
      if (caps.coTheDoiLich) {
        rows.push(
          `<div class="tp-row">${button("Yêu cầu đổi lịch", "reschedule", session.id)}</div>`,
        );
      }
      if (caps.coTheChinhCong) {
        rows.push(
          `<div class="tp-row">${button("Yêu cầu chỉnh công", "correction", session.id)}</div>`,
        );
      }
      if (!rows.length && caps.lyDoKhoa) {
        rows.push(
          `<p class="tp-muted" style="margin:8px 0 0">${escape(caps.lyDoKhoa)}</p>`,
        );
      }
      return rows.join("");
    }

    function sessionView(session) {
      const blocked = disabledSession(session);
      const presence = session.soPhutThucTe;
      const metaRows = [
        [text.sessionStatusRowLabel, tag(session.trangThai)],
        ...(cfg.showClassType
          ? [["Loại lớp", classTypeTag(session.loaiLop) || "—"]]
          : []),
        ["Check-in", datetime(session.checkinLuc)],
        ["Check-out", datetime(session.checkoutLuc)],
        [
          "Thời gian hiện diện",
          presence === null || presence === undefined ? "—" : presence + " phút",
        ],
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
      ];
      const bannerMeta = `${date(session.ngay)} · ${time(session.gioBatDau)}–${time(session.gioKetThuc)}${cfg.showClassType && session.loaiLop ? " · " + escape(CLASS_TYPE_LABELS[session.loaiLop] || session.loaiLop) : ""}${session.loai === "bu" ? " · Học bù" : ""}`;
      const mainTeacherMeta = session.tenGiaoVienChinh
        ? ` · GV chính: ${escape(session.tenGiaoVienChinh)}`
        : "";
      return (
        head(
          screens.session,
          sessionTitle(session),
          button("← Thời khóa biểu", "tab", "schedule") +
            button("Tải lại ca", "reload-session"),
        ) +
        `<div class="tp-session-banner"><p>${bannerMeta}</p><h2>${escape(session.tenLop)}</h2><p>${escape(session.maLop)} · ${escape(formatPlace(session, cfg.placeDetailFallback || "Trực tuyến"))} ${escape(session.phongHoc || "")}${mainTeacherMeta}</p><div class="tp-actions">${button("Bấm Check-in", "checkin", session.id, "p", blocked || Boolean(session.checkinLuc) || isSessionDone(session))}${button("Bấm Check-out", "checkout", session.id, "", blocked || !session.checkinLuc || Boolean(session.checkoutLuc))}${link(session.linkHoc, "Vào phòng học trực tuyến")}</div></div>` +
        `<div class="tp-grid"><div class="card"><h2>Thông tin chấm công</h2>${metaRows
          .map(
            ([label, value]) =>
              `<div class="tp-row"><span class="tp-muted">${label}</span><b>${value}</b></div>`,
          )
          .join(
            "",
          )}</div><div class="card"><h2>Sổ buổi học &amp; Yêu cầu</h2><p class="tp-muted">${session.hocViens.length} học viên trong ca này.</p><div class="tp-row">${button("Mở sổ · Điểm danh & Chấm bài", "log", session.id, "p")}</div>${shiftRequestControls(session)}</div></div>` +
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
      const savedHwId =
        session.hocViens.find((s) => s.baiTapId)?.baiTapId || null;
      const selectedPrev =
        prevList.find((item) => String(item.id) === String(savedHwId)) ||
        prevList[0] ||
        null;
      const activeHomeworkId = selectedPrev?.id ?? savedHwId ?? "";
      const canGrade = Boolean(activeHomeworkId);
      const prevHomeworkBox = prevList.length
        ? `<div class="tp-prev-homework">
            ${
              prevList.length > 1
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
                 ${selectedPrev.noiDung ? `<p class="tp-muted" style="margin:4px 0 0">${escape(selectedPrev.noiDung)}</p>` : ""}</div>`
            }
            <div class="tp-actions" style="margin-top:8px">${link(selectedPrev?.fileDeBaiUrl, "Xem đề bài buổi trước")}</div>
          </div>`
        : savedHwId
          ? `<input type="hidden" name="baiTapChamId" value="${escape(savedHwId)}"><p class="tp-muted">Đang hiển thị kết quả chấm bài tập đã lưu của buổi học này.</p>`
          : '<div class="tp-empty">Chưa có bài tập về nhà từ buổi học trước để chấm. Bạn vẫn có thể điểm danh và ghi nhận xét buổi học.</div>';

      const materialHeader = cfg.canEditMaterial
        ? `<div class="row-b"><span class="tp-muted"><b>Tài liệu chuẩn bị buổi học</b></span>${button("Quản lý tệp ở chi tiết ca", "session", session.id)}</div>`
        : '<span class="tp-muted"><b>Tài liệu chuẩn bị buổi học (chỉ xem)</b></span>';

      return (
        head(
          "Sổ buổi học & Chấm bài",
          sessionTitle(session),
          button("← Quay lại chi tiết ca", "session", session.id) +
            button("Tải lại", "reload-session"),
        ) +
        `<div class="card"><div class="row-b"><b>${escape(sessionTitle(session))}</b>${session.checkinLuc ? tag("hop_le", "Đã check-in").replace("Hợp lệ", "Đã check-in") : tag("chua_check")}</div></div>
        ${!writable ? `<div class="card tp-muted">${disabledSession(session) ? "Ca học hiện chỉ được xem." : "Check-in để bắt đầu ghi sổ buổi học."} ${button(text.viewShiftBtn, "session", session.id)}</div>` : ""}
        <form id="${p}-log-form"><fieldset ${writable ? "" : "disabled"} style="border:0;min-width:0">
        <div class="card"><h2>Hôm nay học gì</h2>${field("Chủ đề bài học *", "chuDe", session.chuDeBaiHoc || "", "text", 'required maxlength="255"')}${textarea("Nội dung đã giảng dạy", "noiDung", session.noiDungBaiHoc || "", 15000)}
          <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--bd)">
            ${materialHeader}
            ${materialSummary(session)}
          </div>
        </div>
        <div class="card"><h2>Điểm danh &amp; Chấm bài tập về nhà buổi trước</h2>
        ${prevHomeworkBox}
        <div class="tp-students" style="margin-top:12px">${
          session.hocViens
            .map(
              (
                student,
              ) => `<details class="tp-student" data-student="${student.hocVienId}">
          <summary><span><b>${escape(student.hoTen)}</b><small>${escape(session.tenLop)} · ${escape(session.maLop)}${Number(student.coHocBu) === 1 ? " · Học bù cá nhân" : ""}</small></span><span class="tp-student-arrow" aria-hidden="true">⌄</span></summary>
          <div class="tp-student-fields"><div class="tp-form-grid">
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
        <p class="tp-error" data-form-error role="alert" hidden></p><div class="tp-log-footer"><button class="btn p" type="submit" name="saveMode" value="save">Lưu sổ buổi học</button><button class="btn" type="submit" name="saveMode" value="checkout" ${session.checkoutLuc ? "disabled" : ""}>Lưu sổ &amp; Check-out</button></div></fieldset></form>`
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
              ? `<button type="button" class="cal-m-chip tp-free-slot" data-action="edit-availability" data-id="${slot.id}" aria-label="Sửa lịch rảnh ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}">${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}</button>`
              : `<button type="button" class="ses-card tp-free-slot" data-action="edit-availability" data-id="${slot.id}"><b class="sc-code">${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}</b><span class="sc-subj">Lịch rảnh</span></button>`,
          )
          .join("");
        if (mode === "month")
          return `<div class="cal-month-cell ${isToday ? "is-today" : ""} ${day.slice(0, 7) !== selected.slice(0, 7) ? "other-month" : ""}"><div class="row-b"><b>${Number(day.slice(8))}</b><button type="button" class="tp-add-free" data-action="add-availability" data-id="${weekday}" aria-label="Thêm lịch rảnh ngày ${date(day)}">+</button></div>${entries}</div>`;
        return `<div class="cal-day-col ${isToday ? "is-today" : ""}"><div class="cal-col-head ${isToday ? "is-today" : ""}"><div class="d-name">${headings[index]}${isToday ? '<span class="d-today-badge">Hôm nay</span>' : ""}</div><div class="d-num">${day.slice(8)}/${day.slice(5, 7)}</div></div><div class="cal-col-body">${entries}${button("+ Thêm lịch rảnh", "add-availability", weekday, "tp-add-free")}</div></div>`;
      }).join("");
      return `<section data-availability-calendar aria-label="Thời khóa biểu lịch rảnh"><div class="pg-head"><div><h2>Thời khóa biểu lịch rảnh</h2><p class="sub">Các khung giờ lặp lại hàng tuần. Bấm vào lịch để sửa hoặc xóa.</p></div>${button("+ Thêm lịch rảnh", "add-availability", "", "p")}</div><div class="cal-box">
        <div class="cal-bar"><div class="row" style="gap:6px"><button type="button" class="cal-nav-btn" data-action="availability-prev">‹ Trước</button>${button("Hôm nay", "availability-today")}<button type="button" class="cal-nav-btn" data-action="availability-next">Sau ›</button></div>
          <div><button type="button" class="cal-range-btn" data-action="availability-pick-month" title="Chọn nhanh tháng / năm">${CAL_ICON_SVG}<span>${escape(range)}</span><span style="font-size:10px;color:var(--g400)">▾</span></button></div>
          <div class="seg"><button type="button" class="${mode === "week" ? "on" : ""}" data-action="availability-mode" data-id="week">Lịch Tuần</button><button type="button" class="${mode === "month" ? "on" : ""}" data-action="availability-mode" data-id="month">Lịch Tháng</button></div>
        </div><div class="tp-calendar-scroll">${mode === "month" ? `<div class="tp-month-view"><div class="cal-month-head">${headings.map((label) => `<div class="cal-month-head-cell">${label}</div>`).join("")}</div><div class="cal-month-grid">${cells}</div></div>` : `<div class="cal-week-grid">${cells}</div>`}</div></div></section>`;
    }

    function shortCode(code) {
      const s = String(code || "");
      return s.length > 11 ? `${s.slice(0, 7)}…${s.slice(-3)}` : s;
    }

    function classes() {
      const headers = [
        "Lớp · Khóa học",
        "Môn · Khối",
        "Lịch học",
        "Sĩ số",
        "Trạng thái",
        "Thao tác",
      ];
      return (
        head(
          screens.myclasses,
          text.classesSubtitle,
          button("Hồ sơ cá nhân", "profile-view"),
        ) +
        `<div class="card">${table(
          headers,
          state.workspace.classes.map(
            (item) =>
              `<tr><td><b>${escape(item.maLop)}</b>${cfg.showClassType ? " " + classTypeTag(item.loaiLop) : ""}<br><small>${escape(item.tenLop)}${item.tenKhoa ? " · " + escape(item.tenKhoa) : ""}</small></td><td>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</td><td>${item.lichHocs.map((slot) => `${Number(slot.thu) === 8 ? "CN" : "T" + slot.thu} ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}`).join("<br>") || "Chưa có lịch"}</td><td><b>${item.siSo}</b></td><td>${tag(item.trangThai)}</td><td class="act-col"><div class="act-row">${button("Lịch", "class-schedule", item.id)}${button("Học viên", "roster", item.id)}</div></td></tr>`,
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
      const roleTagClass = cfg.role === "TRO_GIANG" ? "purple" : "i";
      const proposalsSection = cfg.hasProposals
        ? `<div class="card"><h2>${escape(text.proposalsHeading)}</h2>${table(
            [
              "Đề xuất · Yêu cầu",
              "Học viên",
              "Môn · Lịch học",
              "Phản hồi bạn",
              "Phụ huynh",
              "Thao tác",
            ],
            state.proposals.map((proposal) => {
              const pending =
                proposal.ttNhanSu === "cho" &&
                !["huy", "da_chot"].includes(proposal.trangThaiYeuCau);
              return `<tr><td><b class="code-short" title="${escape(proposal.maYc)}">${escape(shortCode(proposal.maYc))}</b> <small>DX-${proposal.id}</small>${proposal.ghiChu ? `<br><small class="clamp-2">${escape(proposal.ghiChu)}</small>` : ""}</td><td>${escape(proposal.tenHocVien || proposal.hoTenLienHe || "—")}</td><td><b>${escape(proposal.tenMon)} · ${escape(proposal.tenCapLop)}</b><br><small>${(proposal.lichYeuCau || []).map((slot) => `${Number(slot.thu) === 8 ? "CN" : "T" + slot.thu} ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}`).join(", ")}${proposal.tenKhuVuc ? " · " + escape(proposal.tenKhuVuc) : ""}</small></td><td>${tag(proposal.ttNhanSu)}${proposal.lyDoTuChoiNhanSu ? `<br><small>${escape(proposal.lyDoTuChoiNhanSu)}</small>` : ""}</td><td>${tag(proposal.ttPhuHuynh)}</td><td class="act-col">${pending ? `<div class="act-row">${button("Đồng ý", "accept", proposal.id, "p")}${button("Từ chối", "reject", proposal.id)}</div>` : `<small>${escape(proposal.maLop || (proposal.trangThaiYeuCau === "da_chot" ? "Đã chốt" : "Đã ghi nhận"))}</small>`}</td></tr>`;
            }),
            "Chưa có đề xuất ghép lớp mới từ Giáo vụ.",
          )}</div><div class="card"><h2>${escape(text.opportunitiesHeading)}</h2>${table(
            [
              "Yêu cầu · Môn · Khối",
              "Hình thức · Khu vực",
              "Lịch học",
              "Mục tiêu",
              "Thao tác",
            ],
            state.opportunities.map(
              (item) =>
                `<tr><td><b class="code-short" title="${escape(item.maYc)}">${escape(shortCode(item.maYc))}</b>${cfg.showClassType ? " " + classTypeTag(item.loaiLopMongMuon) : ""}<br><small>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></td><td>${item.hinhThuc === "online" ? "Online" : item.hinhThuc === "offline" ? "Trực tiếp" : "Linh hoạt"}<br><small>${escape(item.tenKhuVuc || "—")}</small></td><td>${item.lichYeuCau.map((slot) => `${Number(slot.thu) === 8 ? "CN" : "T" + slot.thu} ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)}`).join("<br>")}</td><td><span class="clamp-2">${escape(item.mucTieu || "—")}</span></td><td class="act-col">${button(text.applyOppBtn, "apply", item.id, "p")}</td></tr>`,
            ),
            text.emptyOpportunities,
          )}</div>`
        : "";

      return (
        head(screens.recruit, text.recruitSubtitle, button("Tải lại", "reload")) +
        `<div class="card"><h2>${escape(text.openShiftsHeading)}</h2>${table(
          [
            "Đề xuất · Lớp",
            "Ca học · Địa điểm",
            "Vị trí · Yêu cầu",
            "Điều kiện",
            "Thao tác",
          ],
          openShifts.map((item) => {
            const mine = item.donCuaToi;
            const place =
              item.hinhThuc === "online"
                ? "Online"
                : `${item.tenCoSo || item.diaDiem || "Trực tiếp"}${item.phongHoc ? " · " + item.phongHoc : ""}${item.tenKhuVuc ? " · " + item.tenKhuVuc : ""}`;
            const statusCell = mine
              ? `${tag(mine.trangThai)}${mine.lyDoTuChoi ? `<span class="tp-elig-note">Lý do: ${escape(mine.lyDoTuChoi)}</span>` : ""}`
              : item.duDieuKien
                ? '<span class="tag ok">Đủ điều kiện</span>'
                : `<span class="tag r">Chưa đủ ĐK</span><span class="tp-elig-note">${escape((item.lyDoKhongDuDieuKien || []).join(" "))}</span>`;
            const actionCell = mine
              ? mine.trangThai === "da_duyet"
                ? button("Xem ca", "session", item.buoiHocId, "p")
                : '<span class="tp-muted">Đã ứng tuyển</span>'
              : button(
                  text.applyShiftBtn,
                  "apply-shift",
                  item.id,
                  "p",
                  !item.duDieuKien,
                );
            const roleBadge =
              cfg.role === "TRO_GIANG"
                ? `Trợ giảng (${item.soDaDuyet}/${item.soLuong})`
                : text.roleName;
            return `<tr>
              <td><b>${escape(item.maLop)}</b> · <span class="code-short">${escape(item.maDeXuat)}</span>${cfg.showClassType ? " " + classTypeTag(item.loaiLop) : ""}<br><small>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></td>
              <td><b>${date(item.ngay)}</b> <small>${time(item.gioBatDau)}–${time(item.gioKetThuc)}</small><br><small>${escape(place)}</small></td>
              <td><span class="tag ${roleTagClass}">${escape(roleBadge)}</span><br><small class="clamp-2">${escape(item.yeuCau || text.defaultShiftRequirement)}</small></td>
              <td>${statusCell}</td>
              <td class="act-col">${actionCell}</td>
            </tr>`;
          }),
          text.emptyOpenShifts,
        )}</div>
        <div class="card"><h2>Đơn ứng tuyển ca của tôi</h2>${table(
          [
            "Đề xuất · Lớp",
            "Ca học",
            "Lời nhắn",
            "Trạng thái · Kết quả",
            "Thao tác",
          ],
          myShiftApps.map((item) => {
            const mine = item.donCuaToi;
            const resultNote =
              mine.trangThai === "da_duyet"
                ? "Đã phân công lên lịch."
                : mine.trangThai === "tu_choi"
                  ? `Từ chối: ${mine.lyDoTuChoi || "Chưa đạt."}`
                  : "Chờ Giáo vụ duyệt.";
            return `<tr>
              <td><b>${escape(item.maLop)}</b> · <span class="code-short">${escape(item.maDeXuat)}</span>${cfg.showClassType ? " " + classTypeTag(item.loaiLop) : ""}<br><small>${escape(item.tenMon)} · ${escape(item.tenCapLop)}</small></td>
              <td><b>${date(item.ngay)}</b><br><small>${time(item.gioBatDau)}–${time(item.gioKetThuc)}</small></td>
              <td><span class="clamp-2">${escape(mine.ghiChu || "—")}</span></td>
              <td>${tag(mine.trangThai)} <small>${datetime(mine.duyetLuc || mine.createdAt)}</small><br><small>${escape(resultNote)}</small></td>
              <td class="act-col">${mine.trangThai === "da_duyet" ? button("Xem ca", "session", item.buoiHocId, "p") : "—"}</td>
            </tr>`;
          }),
          "Bạn chưa gửi đơn ứng tuyển ca nào.",
        )}</div>${proposalsSection}`
      );
    }

    function requests() {
      const rows = state.workspace.requests.filter(
        (request) => !state.requestFilter || request.loai === state.requestFilter,
      );
      return (
        head(
          "Đơn từ & Yêu cầu cá nhân",
          text.requestsSubtitle,
          button("+ Làm đơn mới", "request", "", "p"),
        ) +
        `<div class="card"><div class="tp-toolbar">${select("Loại yêu cầu", "request-filter", cfg.requestFilterOptions, state.requestFilter)}</div>${
          cfg.hasProposals && state.requestFilter === "xin_lop"
            ? table(
                [
                  "Mã",
                  "Yêu cầu học",
                  "Phản hồi của bạn",
                  "Phụ huynh",
                  "Lớp đã xếp",
                ],
                state.proposals.map(
                  (proposal) =>
                    `<tr><td>DX-${proposal.id}</td><td><span class="code-short" title="${escape(proposal.maYc)}">${escape(shortCode(proposal.maYc))}</span></td><td>${tag(proposal.ttNhanSu)}</td><td>${tag(proposal.ttPhuHuynh)}</td><td>${escape(proposal.maLop || "—")}</td></tr>`,
                ),
              )
            : table(
                [
                  "Đơn · Loại",
                  "Lớp · Buổi",
                  "Nội dung",
                  "Trạng thái · Ngày gửi",
                  "Phản hồi",
                ],
                rows.map(
                  (request) =>
                    `<tr><td><b class="code-short">${escape(request.ma)}</b><br><small>${escape(labels[request.loai] || request.loai)}</small></td><td><b>${escape(request.maLop)}</b><br><small>${date(request.ngay)}</small></td><td><span class="clamp-2" title="${escape(request.lyDo || "")}">${escape(request.lyDo)}</span></td><td>${tag(request.trangThai)}<br><small>${datetime(request.createdAt)}</small></td><td><span class="clamp-2">${escape(request.phanHoi || "—")}</span></td></tr>`,
                ),
              )
        }</div>`
      );
    }

    function paysheet() {
      const sessions = state.workspace.sessions.filter((session) =>
        session.ngay.startsWith(state.month),
      );
      const minutes = sessions
        .filter((session) => session.trangThaiCong !== "tu_choi")
        .reduce((sum, session) => sum + Number(session.soPhutTinhCong || 0), 0);
      const period = periodStatus(state.month);
      return (
        head("Bảng công cá nhân", "", button("In bảng công", "print")) +
        `<div class="card"><div class="tp-toolbar">${field("Tháng", "timesheet-month", state.month, "month", "required")}<b>${Math.floor(minutes / 60)} giờ ${minutes % 60} phút · ${sessions.filter((session) => session.checkoutLuc).length} ca đã check-out · Kỳ công: ${tag(period)}</b></div>${table(
          [
            "Lớp · Ngày giờ",
            "Check-in / Out",
            "Phút công",
            "Đi trễ",
            "Trạng thái · Kỳ",
            "Thao tác",
          ],
          sessions.map(
            (session) =>
              `<tr><td><b>${escape(session.maLop)}</b> · ${date(session.ngay)}<br><small>${time(session.gioBatDau)}–${time(session.gioKetThuc)}</small></td><td><small>In: ${datetime(session.checkinLuc)}<br>Out: ${datetime(session.checkoutLuc)}</small></td><td><b>${workMinutes(session)}</b>${session.soPhutThucTe != null ? `<br><small>Hiện diện: ${escape(session.soPhutThucTe)}p</small>` : ""}</td><td>${session.checkinLuc ? (Number(session.checkinTre) ? tag("cho_duyet", "Đi trễ").replace("Chờ xử lý", "Đi trễ") : tag("hop_le", "Đúng giờ").replace("Hợp lệ", "Đúng giờ")) : "—"}</td><td>${tag(session.trangThaiCong)}<br><small>${tag(session.kyCong)}</small></td><td class="act-col"><div class="act-row">${button("Xem ca", "session", session.id)}${session.quyenYeuCau?.coTheChinhCong ? button("Điều chỉnh", "correction", session.id) : ""}</div></td></tr>`,
          ),
        )}</div>`
      );
    }

    async function render() {
      const version = ++renderVersion;
      menu(false);
      document.querySelectorAll(`#${p}-nav [data-id]`).forEach((item) => {
        const active =
          item.dataset.id ===
          (["session", "log"].includes(state.tab) ? "schedule" : state.tab);
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
          const detail = await api(`${ep.sessions}/${state.sessionId}`);
          if (version !== renderVersion) return;
          state.detail = detail;
          content.innerHTML =
            state.tab === "log" ? logView(detail) : sessionView(detail);
        } catch (error) {
          if (version === renderVersion)
            content.innerHTML =
              head(screens[state.tab]) +
              `<div class="tp-error" role="alert">${escape(error.message)} ${button("Thử lại", "reload-session")}</div>`;
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
      state.materialDirty = false;
      state.tab = tab;
      if (sessionId) state.sessionId = sessionId;
      history.replaceState(null, "", `${cfg.hashRoot}${cfg.hashPrefix}${tab}`);
      if (refresh) await load();
      await render();
    }

    function requestModal(type = cfg.rescheduleType, sessionId = state.sessionId) {
      if (!cfg.requestModalOptions[type]) type = cfg.rescheduleType;
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
        `<form id="${p}-request-form"><div class="tp-form-grid">${select("Loại yêu cầu", "loai", cfg.requestModalOptions, type)}<div class="full">${select("Ca học *", "buoiHocId", Object.fromEntries(sessions.map((session) => [session.id, sessionTitle(session)])), selected?.id ?? "")}</div>
        ${type === cfg.rescheduleType ? field("Ngày đề xuất *", "ngayMoi", selected?.ngay || "", "date", "required") + field("Giờ bắt đầu *", "gioBatDau", selected ? time(selected.gioBatDau) : "", "time", "required") + field("Giờ kết thúc *", "gioKetThuc", selected ? time(selected.gioKetThuc) : "", "time", "required") : ""}
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
      const form = document.getElementById(`${p}-availability-form`);
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
        `<form id="${p}-availability-form" class="tp-availability-form" data-id="${escape(id)}"><div class="tp-form-grid">
        <div class="full">${select("Ngày trong tuần", "thu", { 2: "Thứ Hai", 3: "Thứ Ba", 4: "Thứ Tư", 5: "Thứ Năm", 6: "Thứ Sáu", 7: "Thứ Bảy", 8: "Chủ Nhật" }, slot?.thu || weekday || 2)}</div>
        ${field("Từ giờ *", "start", slot ? time(slot.gioBatDau) : "08:00", "time", "required")}
        ${field("Đến giờ *", "end", slot ? time(slot.gioKetThuc) : "10:00", "time", "required")}
        </div>
        <p class="tp-muted">Lịch rảnh lặp lại hàng tuần vào ngày đã chọn.</p>
        <p class="tp-error" data-form-error role="alert" hidden></p>
        <div class="tp-dialog-foot">${slot ? button("Xóa lịch", "delete-availability", id, "danger") : ""}${button("Hủy", "availability-back")}<button class="btn p" type="submit">Lưu lịch rảnh</button></div></form>`,
      );
      validateAvailability();
    }

    function accountMenu(open) {
      document.getElementById(`${p}-account-menu`).hidden = !open;
      document
        .getElementById(`${p}-avatar`)
        .setAttribute("aria-expanded", String(open));
    }

    function viewProfile() {
      if (dialog.open) dialog.close();
      AccountUI.view();
    }

    function notificationsModal() {
      const fmt = v => (window.ECDate ? window.ECDate.replaceInlineDates(v || "") : (v || ""));
      modal(
        "Thông báo",
        `${button("Đánh dấu tất cả đã đọc", "read-all")}<div>${state.notifications.map((notification) => `<div class="tp-row"><div><b>${escape(fmt(notification.tieuDe))}</b><p>${escape(fmt(notification.noiDung))}</p><small>${datetime(notification.createdAt)}</small></div>${Number(notification.daDoc) ? '<span class="tp-muted">Đã đọc</span>' : button("Đánh dấu đã đọc", "read", notification.id)}</div>`).join("") || empty("Chưa có thông báo.")}</div>`,
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
          accountMenu(document.getElementById(`${p}-account-menu`).hidden);
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
        case "reschedule":
          requestModal(cfg.rescheduleType, id || state.sessionId);
          return;
        case "correction":
          requestModal("dieu_chinh_cong", id);
          return;
        case "remove-material":
          if (!cfg.canEditMaterial) return;
          if (!window.confirm("Gỡ tài liệu chuẩn bị đã gắn cho buổi học này?"))
            return;
          state.detail = await api(`${ep.sessions}/${id}/material`, "delete");
          await load();
          await render();
          toast("Đã gỡ tài liệu buổi học.");
          return;
        case "add-availability":
        case "edit-availability":
          if (element.closest("[data-availability-calendar]"))
            availabilityInProfile = Boolean(
              element.closest(`#${p}-dialog,.account-dialog`),
            );
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
            `<form id="${p}-availability-delete-form" data-id="${escape(id)}">
            <p>Xóa khung giờ ${Number(slot.thu) === 8 ? "Chủ Nhật" : "Thứ " + slot.thu}, ${time(slot.gioBatDau)}–${time(slot.gioKetThuc)} khỏi lịch rảnh hàng tuần?</p>
            <p class="tp-error" data-form-error role="alert" hidden></p><div class="tp-dialog-foot">${button("Quay lại", "edit-availability", id)}<button type="submit" class="btn danger">Xóa lịch rảnh</button></div></form>`,
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
        case "availability-pick-month":
          event.stopPropagation();
          window.ECPicker?.openMonthMenu(element, state.availabilityDate.slice(0, 7), (ym) => {
            if (!ym) return;
            state.availabilityDate = `${ym}-01`;
            refreshAvailability();
          });
          return;
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
        case "calendar-pick-month":
          event.stopPropagation();
          window.ECPicker?.openMonthMenu(element, state.calendarDate.slice(0, 7), (ym) => {
            if (!ym) return;
            state.calendarDate = `${ym}-01`;
            render();
          });
          return;
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
          if (action === "checkout" && !window.confirm(text.checkoutConfirm))
            return;
          await api(`${ep.sessions}/${id}/${action}`, "post", {});
          await load();
          await render();
          toast(
            action === "checkin"
              ? "Đã ghi nhận Check-in."
              : "Đã ghi nhận Check-out.",
          );
          return;
        case "accept":
        case "reject":
          if (!cfg.hasProposals) return;
          modal(
            action === "accept" ? "Đồng ý nhận đề xuất" : "Từ chối đề xuất",
            `<form id="${p}-proposal-form" data-id="${id}" data-response="${action === "accept" ? "dong_y" : "tu_choi"}">${action === "reject" ? textarea("Lý do từ chối *", "lyDo", "", 255) : `<p>${escape(text.acceptProposalConfirm)}</p>`}${formFoot("Xác nhận")}</form>`,
          );
          if (action === "reject")
            dialog.querySelector("[name=lyDo]").required = true;
          return;
        case "apply-shift": {
          const opening = state.shiftOpenings.find(
            (item) => String(item.id) === String(id),
          );
          if (!opening) return;
          modal(
            `Ứng tuyển vị trí ${text.roleName}`,
            `<form id="${p}-shift-apply-form" data-id="${escape(opening.id)}">
              <p><b>${escape(opening.maDeXuat)} · ${escape(opening.maLop)}</b>${cfg.showClassType ? " " + classTypeTag(opening.loaiLop) : ""} — ${escape(opening.tenLop)}</p>
              <p class="tp-muted">Ca ngày ${date(opening.ngay)} · ${time(opening.gioBatDau)}–${time(opening.gioKetThuc)} · ${escape(opening.tenMon)} · ${escape(opening.tenCapLop)}</p>
              ${opening.yeuCau ? `<p class="tp-muted">Yêu cầu: ${escape(opening.yeuCau)}</p>` : ""}
              ${textarea("Lời nhắn cho Giáo vụ (tùy chọn)", "ghiChu", "", 500)}
              ${formFoot("Gửi đơn ứng tuyển")}
            </form>`,
          );
          return;
        }
        case "apply":
          if (!cfg.hasProposals || !ep.opportunities) return;
          if (!window.confirm(text.applyOppConfirm)) return;
          await api(`${ep.opportunities}/${id}/apply`, "post", {});
          await load();
          await render();
          toast(text.applyOppSuccess);
          return;
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

    function updatePickedFileLabel(target) {
      if (!target.matches('input[type="file"]')) return;
      const info = target
        .closest(`#${p}-material-form`)
        ?.querySelector("[data-selected-file]");
      const picked = target.files?.[0];
      if (info) {
        info.textContent = picked
          ? `Đã chọn tệp: ${picked.name} (${formatBytes(picked.size)}) — Bấm "Tải lên & Lưu tài liệu" để lưu.`
          : "";
      }
    }

    document.addEventListener("click", async (event) => {
      if (!event.target.closest(".tp-account")) accountMenu(false);
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
      if (event.target.closest(`#${p}-availability-form`))
        validateAvailability();
      if (
        event.target.closest(`#${p}-request-form`) &&
        event.target.name !== "loai"
      )
        validateRequest();
      if (
        event.target.closest(`#${p}-log-form`) &&
        event.target.matches("[data-time24],input[type=date]")
      )
        validateTimeFields(event.target.form);
      if (event.target.closest(`#${p}-log-form`)) state.dirty = true;
      if (cfg.canEditMaterial && event.target.closest(`#${p}-material-form`)) {
        state.materialDirty = true;
        updatePickedFileLabel(event.target);
      }
      if (event.target.closest(`#${p}-request-form`) && name === "loai") {
        if (cfg.hasProposals && value === "xin_lop") {
          dialog.close();
          await navigate("recruit");
        } else {
          requestModal(value, dialog.querySelector("[name=buoiHocId]")?.value);
        }
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
        const student = event.target.closest("details.tp-student");
        if (student) student.open = true;
      },
      true,
    );

    document.addEventListener("input", (event) => {
      if (event.target.closest(`#${p}-availability-form`))
        validateAvailability();
      if (
        event.target.closest(`#${p}-request-form`) &&
        event.target.name !== "loai"
      )
        validateRequest();
      if (
        event.target.closest(`#${p}-log-form`) &&
        event.target.matches("[data-time24],input[type=date]")
      )
        validateTimeFields(event.target.form);
      if (event.target.closest(`#${p}-log-form`)) state.dirty = true;
      if (cfg.canEditMaterial && event.target.closest(`#${p}-material-form`)) {
        state.materialDirty = true;
        updatePickedFileLabel(event.target);
      }
    });

    document.addEventListener("submit", async (event) => {
      const form = event.target;
      if (!form.id.startsWith(`${p}-`)) return;
      event.preventDefault();
      if (!validateTimeFields(form)) return;
      if (form.id === `${p}-request-form` && !validateRequest()) return;
      if (form.id === `${p}-availability-form` && !validateAvailability())
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
        if (form.id === `${p}-request-form`) {
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
          await api(ep.requests, "post", body);
        } else if (form.id === `${p}-availability-form`) {
          await api(
            ep.availability + (form.dataset.id ? "/" + form.dataset.id : ""),
            form.dataset.id ? "put" : "post",
            {
              thu: Number(values.get("thu")),
              gioBatDau: values.get("start"),
              gioKetThuc: values.get("end"),
            },
          );
        } else if (form.id === `${p}-availability-delete-form`) {
          await api(`${ep.availability}/${form.dataset.id}`, "delete");
        } else if (form.id === `${p}-shift-apply-form`) {
          await api(
            `/academic/scheduling/openings/${form.dataset.id}/apply`,
            "post",
            { ghiChu: values.get("ghiChu")?.trim() || null },
          );
        } else if (cfg.hasProposals && form.id === `${p}-proposal-form`) {
          await api(
            `/academic/admissions/proposals/${form.dataset.id}/respond`,
            "post",
            {
              trangThai: form.dataset.response,
              lyDo: values.get("lyDo") || null,
            },
          );
        } else if (cfg.canEditMaterial && form.id === `${p}-material-form`) {
          const sid = form.dataset.sessionId || state.sessionId;
          const fileInput = form.querySelector('input[name="file"]');
          const file = fileInput?.files?.[0] || null;
          if (file && file.size > 10 * 1024 * 1024) {
            throw new Error("Tệp tài liệu vượt quá dung lượng tối đa 10 MB.");
          }
          const payload = new FormData();
          if (file) payload.append("file", file);
          const fileUrl = String(values.get("fileUrl") || "").trim();
          const tieuDe = String(values.get("tieuDe") || "").trim();
          const ghiChu = String(values.get("ghiChu") || "").trim();
          if (fileUrl) payload.append("fileUrl", fileUrl);
          if (tieuDe) payload.append("tieuDe", tieuDe);
          if (ghiChu) payload.append("ghiChu", ghiChu);
          state.detail = await api(`${ep.sessions}/${sid}/material`, "post", payload);
          state.materialDirty = false;
        } else if (form.id === `${p}-log-form`) {
          const num = (name) =>
            values.get(name) === "" || values.get(name) === null
              ? null
              : Number(values.get(name));
          const hwIdRaw = values.get("baiTapChamId");
          const body = {
            revision: state.detail.revision,
            chuDe: values.get("chuDe"),
            noiDung: values.get("noiDung"),
            baiTapChamId: hwIdRaw ? Number(hwIdRaw) : null,
            hocViens: state.detail.hocViens.map((student) => ({
              hocVienId: Number(student.hocVienId),
              trangThaiDiemDanh: values.get("attendance-" + student.hocVienId),
              diemBaiTapGiay: num("score-" + student.hocVienId),
              nhanXetBaiTap: values.get("comment-" + student.hocVienId) || null,
              mucTiepThu: num("level-" + student.hocVienId),
              thaiDo: values.get("attitude-" + student.hocVienId) || null,
              nhanXetBuoiHoc: values.get("note-" + student.hocVienId),
              huongCaiThien: values.get("improve-" + student.hocVienId),
            })),
          };
          state.detail = await api(
            `${ep.sessions}/${state.sessionId}/log`,
            "put",
            body,
          );
          state.dirty = false;
          if (event.submitter?.value === "checkout") {
            try {
              await api(
                `${ep.sessions}/${state.sessionId}/checkout`,
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
        if (form.id.startsWith(`${p}-availability-`) && availabilityInProfile)
          viewProfile();
        toast(
          form.id === `${p}-availability-delete-form`
            ? "Đã xóa lịch rảnh."
            : form.id === `${p}-shift-apply-form`
              ? "Đã gửi đơn ứng tuyển. Chờ Giáo vụ phê duyệt."
              : form.id === `${p}-material-form`
                ? "Đã tải lên và lưu tài liệu buổi học."
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
        if (form.id === `${p}-availability-form` && form.isConnected)
          validateAvailability();
        if (form.id === `${p}-request-form` && form.isConnected)
          validateRequest();
      }
    });

    document
      .getElementById(`${p}-menu`)
      .addEventListener("click", () =>
        menu(!document.body.classList.contains("menu-open")),
      );
    document
      .getElementById(`${p}-backdrop`)
      .addEventListener("click", () => menu(false));

    window.addEventListener("focus", async () => {
      const matForm = document.getElementById(`${p}-material-form`);
      const hasPickedFile = Boolean(
        matForm?.querySelector('input[type="file"]')?.files?.length,
      );
      if (
        !state.workspace ||
        state.dirty ||
        state.materialDirty ||
        hasPickedFile ||
        dialog.open ||
        state.tab === "session"
      )
        return;
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
      navigate(location.hash.split(cfg.hashPrefix)[1] || "dash"),
    );

    document.getElementById(`${p}-nav`).innerHTML = Object.entries(screens)
      .map(([key, label], index) =>
        key === "log" || key === "session"
          ? ""
          : `<button type="button" data-action="tab" data-id="${key}"><span class="nav-icon"><svg class="svg-ico" viewBox="0 0 24 24">${ICONS[index]}</svg></span>${label}</button>`,
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
        navigate(location.hash.split(cfg.hashPrefix)[1] || "dash", null, false),
      )
      .catch((error) => {
        content.innerHTML =
          head(cfg.portalTitle) +
          `<div class="tp-error" role="alert">${escape(error.message)} ${button("Thử lại", "reload")}</div>`;
      });
  }

  return { mount };
})();
