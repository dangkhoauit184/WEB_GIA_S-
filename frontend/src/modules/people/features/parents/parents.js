"use strict";

// Parent workspace. The staff management screen remains below in its own scope.
(() => {
  if (!document.getElementById("parent-workspace")) return;
  document.addEventListener("DOMContentLoaded", () => {
    if (!AccessService.requireAuth(["PHU_HUYNH"])) return;
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
          })[char],
      );
    const money = (value) =>
      new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    const tag = (label, tone = "i") =>
      `<span class="tag ${tone}">${esc(label)}</span>`;
    const empty = (text) => `<div class="parent-empty">${esc(text)}</div>`;
    const emptyRow = (columns, text) =>
      `<tr><td colspan="${columns}">${empty(text)}</td></tr>`;
    const button = (label, action, id = "", style = "") =>
      `<button class="btn sm ${style}" type="button" data-action="${action}" data-id="${esc(id)}" ${label === "✕" ? 'aria-label="Đóng hộp thoại"' : ""}>${esc(label)}</button>`;
    const item = (label, value) =>
      `<div class="item"><span>${esc(label)}</span><span>${value}</span></div>`;
    const account = AccessService.getCurrentUser();
    const dialog = $("parent-dialog");
    let data = {
      students: [],
      courses: [],
      schedules: {},
      learningNotes: {},
      invoices: {},
      leaveRequests: [],
      transactions: [],
    };
    let notifications = [];
    let proposals = [];
    let childId =
      localStorage.getItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID) || "";
    let loaded = false;
    let loading = false;
    let calendarView = "week";
    let serverNow = new Date();
    let calendarDay = dayKey(serverNow);
    let activeScreen = "dash";
    let toastTimer;

    function dayKey(date) {
      return new Intl.DateTimeFormat("sv-SE", {
        timeZone: "Asia/Ho_Chi_Minh",
      }).format(date);
    }
    function dateKey(value) {
      if (!value) return "";
      if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10);
      const match = String(value).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
      return match ? `${match[3]}-${match[2]}-${match[1]}` : "";
    }
    function shiftDay(value, days) {
      const date = new Date(`${value}T12:00:00Z`);
      date.setUTCDate(date.getUTCDate() + days);
      return date.toISOString().slice(0, 10);
    }
    function displayDate(value) {
      const key = dateKey(value);
      return key ? key.split("-").reverse().join("/") : value || "—";
    }
    function displayTime(value) {
      if (!value) return "—";
      const date = new Date(String(value).replace(" ", "T") + "+07:00");
      return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat("vi-VN", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            timeZone: "Asia/Ho_Chi_Minh",
          }).format(date);
    }
    function sessionStart(session) {
      const date = dateKey(session.ngay || session.date);
      const time =
        session.gioBatDau || String(session.time || "").split(" - ")[0];
      return date && time
        ? new Date(`${date}T${time.slice(0, 5)}:00+07:00`).getTime()
        : 0;
    }
    function student() {
      return data.students.find((row) => String(row.id) === childId);
    }
    function sessions() {
      return [...(data.schedules[childId] || [])].sort(
        (left, right) => sessionStart(left) - sessionStart(right),
      );
    }
    function notes() {
      return data.learningNotes[childId] || [];
    }
    function invoices() {
      return data.invoices[childId] || [];
    }
    function pendingPayment(invoice) {
      return data.transactions.find(
        (row) =>
          row.invCode === invoice.code &&
          ["khoi_tao", "cho_xac_nhan"].includes(row.status),
      );
    }
    function notify(message, error = false) {
      clearTimeout(toastTimer);
      $("parent-toast").textContent = message;
      $("parent-toast").classList.toggle("error", error);
      $("parent-toast").hidden = false;
      toastTimer = setTimeout(() => {
        $("parent-toast").hidden = true;
      }, 5000);
    }
    async function busy(control, work) {
      if (control.disabled) return;
      control.disabled = true;
      try {
        await work();
      } catch (error) {
        notify(error.message || "Không thể xử lý yêu cầu.", true);
      } finally {
        if (control.isConnected) {
          control.disabled =
            control.dataset.action === "read-all" &&
            !notifications.some((row) => !row.daDoc);
        }
      }
    }
    function documentLink(url) {
      if (!url) return '<span class="parent-muted">Chưa có đề bài</span>';
      try {
        const parsed = new URL(url, window.location.origin);
        if (!["http:", "https:"].includes(parsed.protocol))
          return '<span class="parent-muted">Tệp chưa khả dụng</span>';
        return `<a class="btn sm" href="${esc(parsed.href)}" target="_blank" rel="noopener noreferrer" download>Tải PDF</a>`;
      } catch {
        return '<span class="parent-muted">Tệp chưa khả dụng</span>';
      }
    }

    $("currentUserName").textContent = account.hoTen || account.username;
    $("accountMenuName").textContent = account.hoTen || account.username;
    $("parentAvatar").textContent = (account.hoTen || account.username)
      .trim()
      .split(/\s+/)
      .pop()[0]
      .toUpperCase();

    async function loadData() {
      if (loading) return;
      loading = true;
      $("parent-refresh").disabled = true;
      const results = await Promise.allSettled([
        HttpService.get("/people/parents/portal"),
        HttpService.get("/communication/notifications"),
        HttpService.get("/academic/admissions/proposals"),
      ]);
      const failures = [];
      if (results[0].status === "fulfilled") {
        data = results[0].value.data;
        loaded = true;
        serverNow = data.serverTime ? new Date(data.serverTime) : new Date();
        if (!data.students.some((row) => String(row.id) === childId))
          childId = String(data.students[0]?.id || "");
        localStorage.setItem(
          AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID,
          childId,
        );
      } else {
        loaded = false;
        failures.push(
          results[0].reason.message || "Không tải được dữ liệu học sinh.",
        );
      }
      notifications =
        results[1].status === "fulfilled" ? results[1].value.data : [];
      proposals =
        results[2].status === "fulfilled" ? results[2].value.data.items : [];
      if (results[1].status === "rejected")
        failures.push("Không tải được thông báo.");
      if (results[2].status === "rejected")
        failures.push("Không tải được đề xuất người dạy.");
      $("parentLoadError").hidden = failures.length === 0;
      $("parentErrorText").textContent = failures.join(" ");
      $("parentDate").textContent = new Intl.DateTimeFormat("vi-VN", {
        weekday: "long",
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        timeZone: "Asia/Ho_Chi_Minh",
      }).format(serverNow);
      render();
      loading = false;
      $("parent-refresh").disabled = false;
    }

    function renderChildren() {
      $("studentSelector").innerHTML = data.students.length
        ? data.students
            .map(
              (row) =>
                `<option value="${esc(row.id)}" ${String(row.id) === childId ? "selected" : ""}>${esc(row.hoTen)} · ${esc(row.tenCapLop || row.truong || "Học viên")}</option>`,
            )
            .join("")
        : '<option value="">Chưa liên kết học sinh</option>';
      $("studentSelector").disabled = !loaded || !data.students.length;
      $("parentNoChildren").hidden = !loaded || data.students.length > 0;
      $("kidChipsContainer").innerHTML = data.students
        .map(
          (row) =>
            `<button class="chip ${String(row.id) === childId ? "on" : ""}" type="button" data-action="child" data-id="${esc(row.id)}" aria-pressed="${String(row.id) === childId}">${esc(row.hoTen)}<small>${esc(row.tenCapLop || "")}</small></button>`,
        )
        .join("");
      document.querySelectorAll("[data-student-name]").forEach((element) => {
        element.textContent = student()?.hoTen || "con";
      });
      document.querySelectorAll("[data-needs-child]").forEach((element) => {
        element.disabled = !loaded || !student();
      });
    }
    function renderProposals() {
      const rows = proposals
        .filter((row) => !row.hocVienId || String(row.hocVienId) === childId)
        .filter((row) => row.ttPhuHuynh === "cho");
      $("parentProposalBanner").innerHTML = rows
        .map(
          (row) =>
            `<div class="card parent-proposal"><div class="row-b"><div><h2>Đề xuất người dạy chờ xác nhận</h2><p><strong>${esc(row.tenNguoiDay)}</strong> · ${esc(row.tenHocVien || row.hoTenLienHe || "")}</p>${row.ghiChu ? `<p>${esc(row.ghiChu)}</p>` : ""}</div><div class="row">${button("Đồng ý", "proposal-accept", row.id, "p")}${button("Từ chối", "proposal-reject", row.id, "danger")}</div></div></div>`,
        )
        .join("");
    }
    function renderDashboard() {
      const past = sessions()
        .filter(
          (row) =>
            sessionStart(row) <= serverNow.getTime() &&
            !["huy", "doi"].includes(row.trangThai),
        )
        .reverse();
      const lesson = past[0];
      const note = notes().find((row) => row.buoiHocId === lesson?.buoiHocId);
      $("parentLessonSummary").innerHTML = lesson
        ? item("Buổi học", esc(`${lesson.course} · ${lesson.date}`)) +
          item("Chủ đề", esc(lesson.chuDe || "Chưa cập nhật")) +
          item(
            "Nội dung",
            `<span class="parent-body-copy">${esc(lesson.noiDung || "Chưa cập nhật nội dung buổi học.")}</span>`,
          ) +
          item(
            "Chuyên cần",
            note ? tag(note.attend, "ok") : esc("Chưa điểm danh"),
          )
        : empty("Chưa có buổi học đã diễn ra.");
      const homework =
        notes().find((row) => row.baiTap || row.fileUrl) || lesson;
      $("parentHomeworkSummary").innerHTML =
        homework && (homework.baiTap || homework.fileUrl)
          ? item("Bài tập", esc(homework.baiTap || "Phiếu bài tập")) +
            item("Hạn nộp", esc(homework.hanNop || "Chưa cập nhật")) +
            `<div class="parent-card-actions">${documentLink(homework.fileUrl)}</div>`
          : empty("Chưa có bài tập được giao.");
      const upcoming = sessions()
        .filter(
          (row) =>
            sessionStart(row) >= serverNow.getTime() &&
            !["huy", "doi"].includes(row.trangThai),
        )
        .slice(0, 2);
      $("parentUpcoming").innerHTML =
        upcoming
          .map(
            (row) =>
              `<div class="parent-next-session"><div><strong>${esc(row.course)}</strong><p>${esc(row.date)} · ${esc(row.time)}</p><p>${esc(row.teacher)} · ${esc(row.room)}</p></div>${button("Chi tiết", "session", row.buoiHocId)}</div>`,
          )
          .join("") || empty("Chưa có lịch học sắp tới.");
      const total = invoices().reduce((sum, row) => sum + Number(row.must), 0);
      const paid = invoices().reduce((sum, row) => sum + Number(row.paid), 0);
      $("parentFeeSummary").innerHTML =
        item("Học phí trọn khóa", `<strong>${money(total)}</strong>`) +
        item("Đã xác nhận thu", money(paid)) +
        item(
          "Còn phải đóng",
          `<strong class="parent-due">${money(Math.max(0, total - paid))}</strong>`,
        );
    }
    function sessionState(row) {
      if (row.loai === "bu") return "makeup";
      if (row.trangThai === "da_day") return "completed";
      if (["huy", "doi"].includes(row.trangThai)) return "cancelled";
      return "";
    }
    function renderCalendar() {
      const periodLabel = calendarView === "month" ? "Tháng" : "Tuần";
      document
        .querySelector('[data-action="previous-week"]')
        .setAttribute("aria-label", `${periodLabel} trước`);
      document
        .querySelector('[data-action="next-week"]')
        .setAttribute("aria-label", `${periodLabel} sau`);
      const date = new Date(`${calendarDay}T12:00:00Z`);
      const monday = shiftDay(calendarDay, -((date.getUTCDay() + 6) % 7));
      const sunday = shiftDay(monday, 6);
      $("parentCalendarRange").textContent =
        `${displayDate(monday)} – ${displayDate(sunday)}`;
      document.querySelectorAll("[data-calendar-view]").forEach((element) => {
        element.classList.toggle(
          "on",
          element.dataset.calendarView === calendarView,
        );
        element.setAttribute(
          "aria-pressed",
          String(element.dataset.calendarView === calendarView),
        );
      });
      if (calendarView === "month") {
        renderMonth();
        return;
      }
      const rows = sessions().filter(
        (row) =>
          dateKey(row.ngay || row.date) >= monday &&
          dateKey(row.ngay || row.date) <= sunday,
      );
      if (calendarView === "list") {
        $("parentCalendar").innerHTML =
          `<div class="parent-calendar-list"><div class="tw"><table><thead><tr><th>Ngày</th><th>Khung giờ</th><th>Khóa học</th><th>Người dạy · Địa điểm</th><th>Loại buổi</th><th>Thao tác</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${esc(row.date)}</td><td>${esc(row.time)}</td><td><strong>${esc(row.course)}</strong></td><td>${esc(row.teacher)}<span class="parent-cell-sub">${esc(row.room)}</span></td><td>${tag(row.loai === "bu" ? "Học bù" : "Buổi học thường", row.loai === "bu" ? "purple" : "i")}</td><td>${button("Chi tiết", "session", row.buoiHocId)}</td></tr>`).join("") || emptyRow(6, "Không có lịch học trong tuần này.")}</tbody></table></div></div>`;
        return;
      }
      $("parentCalendar").innerHTML = `<div class="cal-week-grid">${Array.from(
        { length: 7 },
        (_, index) => {
          const key = shiftDay(monday, index);
          const today = key === dayKey(serverNow);
          const days = [
            "Thứ Hai",
            "Thứ Ba",
            "Thứ Tư",
            "Thứ Năm",
            "Thứ Sáu",
            "Thứ Bảy",
            "Chủ Nhật",
          ];
          const events = rows.filter(
            (row) => dateKey(row.ngay || row.date) === key,
          );
          return `<div class="cal-day-col ${today ? "is-today" : ""}"><div class="cal-col-head ${today ? "is-today" : ""}"><span class="d-name">${days[index]}</span><span class="d-num">${displayDate(key).slice(0, 5)}</span></div><div class="cal-col-body">${events.map((row) => `<button type="button" class="ses-card ${sessionState(row)}" data-action="session" data-id="${esc(row.buoiHocId)}"><div class="sc-code">${esc(row.maLop || row.course)}</div><div class="sc-subj">${esc(row.course)}</div><span class="sc-time">${esc(row.time)}</span><div class="sc-loc">${esc(row.teacher)}</div><div class="sc-loc">${esc(row.room)}</div>${tag(row.loai === "bu" ? "Học bù" : row.status, row.loai === "bu" ? "purple" : "i")}</button>`).join("") || '<div class="cal-col-empty">—</div>'}</div></div>`;
        },
      ).join("")}</div>`;
    }
    function changeCalendarPeriod(direction) {
      if (calendarView === "month") {
        const date = new Date(`${calendarDay.slice(0, 7)}-01T12:00:00Z`);
        date.setUTCMonth(date.getUTCMonth() + direction);
        calendarDay = date.toISOString().slice(0, 10);
      } else {
        calendarDay = shiftDay(calendarDay, direction * 7);
      }
      renderCalendar();
    }
    function renderMonth() {
      const first = `${calendarDay.slice(0, 7)}-01`;
      const date = new Date(`${first}T12:00:00Z`);
      const leadingDays = (date.getUTCDay() + 6) % 7;
      const start = shiftDay(first, -leadingDays);
      const daysInMonth = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0),
      ).getUTCDate();
      const cells = Math.ceil((leadingDays + daysInMonth) / 7) * 7;
      $("parentCalendarRange").textContent =
        `Tháng ${first.slice(5, 7)} / ${first.slice(0, 4)}`;
      const headers = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];
      const rows = sessions();
      const grid = Array.from({ length: cells }, (_, index) => {
        const key = shiftDay(start, index);
        const events = rows.filter(
          (row) => dateKey(row.ngay || row.date) === key,
        );
        const otherMonth = key.slice(0, 7) !== first.slice(0, 7);
        const today = key === dayKey(serverNow);
        return `<div class="cal-month-cell ${otherMonth ? "other-month" : ""} ${today ? "is-today" : ""}">
          <strong>${Number(key.slice(8))}</strong>
          ${events.map((row) => `<button class="cal-m-chip ${sessionState(row)}" type="button" data-action="session" data-id="${esc(row.buoiHocId)}" title="${esc(row.course)} · ${esc(row.time)}">${esc(row.time.slice(0, 5))} · ${esc(row.course)}</button>`).join("")}
        </div>`;
      }).join("");
      $("parentCalendar").innerHTML =
        `<div class="parent-month-scroll"><div class="cal-month-head">${headers.map((label) => `<span class="cal-month-head-cell">${label}</span>`).join("")}</div><div class="cal-month-grid">${grid}</div></div>`;
    }
    function renderLearning() {
      $("learningTableBody").innerHTML =
        notes()
          .map(
            (row) =>
              `<tr><td>${esc(row.date)}</td><td><strong>${esc(row.course)}</strong></td><td>${tag(row.attend, row.attend?.startsWith("Vắng") ? "w" : "ok")}</td><td>${esc(row.baiTap || "Chưa giao bài")}<span class="parent-cell-sub">${esc(row.hanNop || "")}</span></td><td class="parent-money">${esc(row.score)}</td><td>${esc(row.comment)}</td><td>${documentLink(row.fileUrl)}</td></tr>`,
          )
          .join("") || emptyRow(7, "Chưa có kết quả buổi học.");
    }
    function transactionTag(row) {
      const labels = {
        khoi_tao: ["Đang khởi tạo", "i"],
        cho_xac_nhan: ["Chờ đối soát", "w"],
        da_xac_nhan: ["Đã xác nhận", "ok"],
        thanh_cong: ["Đã xác nhận", "ok"],
        that_bai: ["Không thành công", "r"],
        huy: ["Đã hủy", "r"],
        het_han: ["Hết hạn", "r"],
        hoan_tien: ["Đã hoàn tiền", "i"],
      };
      const [label, tone] = labels[row.status] || ["Đang xử lý", "i"];
      return tag(label, tone);
    }
    function renderBilling() {
      $("invoiceTableBody").innerHTML =
        invoices()
          .map((row) => {
            const remaining = Math.max(0, Number(row.must) - Number(row.paid));
            const pending = pendingPayment(row);
            const status = remaining === 0
              ? tag("Đã thanh toán", "ok")
              : pending
                ? tag("Chờ đối soát", "w")
                : tag(
                    row.status === "mot_phan"
                      ? "Đã đóng một phần"
                      : "Chưa thanh toán",
                    "w",
                  );
            const pay =
              loaded && remaining > 0
                ? button("💳 Thanh toán QR", "pay", row.id, "p")
                : "";
            return `<tr><td><strong>${esc(row.code)}</strong></td><td>${esc(row.course)}</td><td class="parent-money">${money(row.must)}</td><td class="parent-money">${money(row.paid)}</td><td class="parent-money ${remaining ? "parent-due" : ""}">${money(remaining)}</td><td>${esc(row.deadline)}</td><td>${status}</td><td><div class="parent-table-actions">${button("Chi tiết", "invoice", row.id)}${pay}</div></td></tr>`;
          })
          .join("") || emptyRow(8, "Chưa có hóa đơn học phí được phát hành.");
      const invoiceCodes = new Set(invoices().map((row) => row.code));
      const transactions = data.transactions.filter(
        (row) =>
          String(row.studentId) === childId || invoiceCodes.has(row.invCode),
      );
      $("transactionHistoryTableBody").innerHTML =
        transactions
          .map(
            (row) =>
              `<tr><td><strong>${esc(row.code)}</strong></td><td>${esc(row.invCode)}</td><td>${esc(providerName(row.channel))}</td><td class="parent-money">${money(row.amount)}</td><td>${esc(row.ref)}</td><td>${esc(row.time)}</td><td>${transactionTag(row)}</td></tr>`,
          )
          .join("") || emptyRow(7, "Chưa có giao dịch thanh toán.");
    }
    function providerName(value) {
      return (
        {
          VIETQR_NAPAS: "Chuyển khoản ngân hàng",
          VNPAY_GATEWAY: "VNPay",
          MOMO_BUSINESS: "MoMo",
        }[value] || value
      );
    }
    function renderLeave() {
      const rows = data.leaveRequests.filter(
        (row) => String(row.studentId) === childId,
      );
      $("leaveHistoryTableBody").innerHTML =
        rows
          .map(
            (row) =>
              `<tr><td><strong>${esc(row.code)}</strong></td><td>${esc(student()?.hoTen)}</td><td>${esc(row.session)}</td><td>${esc(row.reason)}</td><td>${esc(row.date)}</td><td>${tag(row.status, row.status === "Đã duyệt" ? "ok" : row.status === "Từ chối" ? "r" : "w")}</td><td>${esc(row.feedback || "—")}</td></tr>`,
          )
          .join("") || emptyRow(7, "Chưa có đơn xin nghỉ học.");
    }
    function renderNotifications() {
      const unread = notifications.filter((row) => !row.daDoc).length;
      $("notificationCount").hidden = unread === 0;
      $("notificationCount").textContent = unread > 99 ? "99+" : unread;
      $("btnMarkAllRead").disabled = unread === 0;
      $("parentReadAll").disabled = unread === 0;
      $("parentNotificationPreview").innerHTML =
        notifications
          .slice(0, 5)
          .map(
            (row) =>
              `<article class="np-item ${row.daDoc ? "" : "unread"}"><div class="np-item-top"><b>${esc(row.tieuDe)}</b></div><p>${esc(row.noiDung)}</p><time>${esc(displayTime(row.createdAt))}</time>${row.daDoc ? "" : button("Đánh dấu đã đọc", "read", row.id)}</article>`,
          )
          .join("") || empty("Chưa có thông báo.");
      $("notificationsListContainer").innerHTML =
        notifications
          .map(
            (row) =>
              `<article class="parent-notification ${row.daDoc ? "" : "unread"}"><div><h2>${esc(row.tieuDe)}</h2><p>${esc(row.noiDung)}</p><time>${esc(displayTime(row.createdAt))}</time></div>${row.daDoc ? tag("Đã đọc", "ok") : button("Đánh dấu đã đọc", "read", row.id)}</article>`,
          )
          .join("") || empty("Chưa có thông báo.");
    }
    function render() {
      renderChildren();
      renderProposals();
      renderDashboard();
      renderCalendar();
      renderLearning();
      renderBilling();
      renderLeave();
      renderNotifications();
    }
    function selectChild(id) {
      if (!data.students.some((row) => String(row.id) === String(id))) return;
      childId = String(id);
      localStorage.setItem(AppConfig.STORAGE_KEYS.CURRENT_STUDENT_ID, childId);
      render();
    }
    function closeMenu() {
      $("parent-sidebar").classList.remove("open");
      $("parent-backdrop").hidden = true;
      $("parent-menu").setAttribute("aria-expanded", "false");
    }
    function showNotifications(open) {
      $("parentNotificationPanel").hidden = !open;
      $("parent-bell").setAttribute("aria-expanded", String(open));
    }
    function showScreen() {
      const key = location.hash.split("/").pop();
      const aliases = {
        learning: "homework",
        tuition: "billing",
        history: "billing",
      };
      const candidate =
        aliases[key] || String(key || "").replace(/^parent-/, "");
      activeScreen = [
        "dash",
        "schedule",
        "homework",
        "billing",
        "leave",
        "notifications",
      ].includes(candidate)
        ? candidate
        : "dash";
      document.querySelectorAll("[data-screen]").forEach((element) => {
        element.hidden = element.dataset.screen !== activeScreen;
      });
      const screen = document.querySelector(`[data-screen="${activeScreen}"]`);
      screen.querySelector(".pg-head").after($("parentChildBar"));
      $("parentChildBar").after($("parentNoChildren"));
      $("parentNoChildren").after($("parentProposalBanner"));
      $("parentProposalBanner").hidden = activeScreen !== "dash";
      document.querySelectorAll("#parent-nav [data-tab]").forEach((element) => {
        const selected = element.dataset.tab === activeScreen;
        element.classList.toggle("on", selected);
        if (selected) element.setAttribute("aria-current", "page");
        else element.removeAttribute("aria-current");
      });
      closeMenu();
      showNotifications(false);
      $("parentAccountMenu").open = false;
      const title = screen.querySelector("h1").textContent;
      document.title = `${title} · EduCenter`;
    }
    function closeDialog() {
      if (dialog.dataset.busy !== "true") dialog.close();
    }
    function openDialog(
      title,
      body,
      onSubmit,
      submitLabel = "Xác nhận",
      extraActions = "",
    ) {
      dialog.dataset.busy = "false";
      $("parent-dialog-content").innerHTML =
        `<div class="parent-dialog-head"><h2 id="parent-dialog-title">${esc(title)}</h2>${button("✕", "dialog-close")}</div><form id="parentDialogForm"><div class="parent-dialog-body">${body}<p class="parent-dialog-error" id="parentDialogError" role="alert"></p></div><div class="parent-dialog-foot">${extraActions}${button(onSubmit ? "Hủy" : "Đóng", "dialog-close")}${onSubmit ? `<button class="btn p" type="submit">${esc(submitLabel)}</button>` : ""}</div></form>`;
      $("parentDialogForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!onSubmit || dialog.dataset.busy === "true") return;
        const values = Object.fromEntries(new FormData(event.target));
        dialog.dataset.busy = "true";
        event.submitter.disabled = true;
        $("parentDialogError").textContent = "";
        try {
          await onSubmit(values);
          dialog.close();
        } catch (error) {
          $("parentDialogError").textContent =
            error.message || "Không thể xử lý yêu cầu.";
        } finally {
          dialog.dataset.busy = "false";
          if (event.submitter.isConnected) event.submitter.disabled = false;
        }
      });
      if (!dialog.open) dialog.showModal();
    }
    function openSession(id) {
      const row = sessions().find(
        (session) => String(session.buoiHocId) === String(id),
      );
      if (!row) return;
      const note = notes().find((record) => record.buoiHocId === row.buoiHocId);
      const summary =
        item("Học viên", esc(student()?.hoTen)) +
        item("Khóa học", esc(row.course)) +
        item("Thời gian", esc(`${row.date} · ${row.time}`)) +
        item("Người dạy", esc(row.teacher)) +
        item("Địa điểm", esc(row.room)) +
        item(
          "Loại buổi",
          tag(
            row.loai === "bu" ? "Học bù" : "Buổi học thường",
            row.loai === "bu" ? "purple" : "i",
          ),
        ) +
        item("Trạng thái", tag(row.status)) +
        item("Chủ đề", esc(row.chuDe || "Chưa cập nhật")) +
        item(
          "Nội dung",
          `<span class="parent-body-copy">${esc(row.noiDung || "Chưa cập nhật")}</span>`,
        ) +
        item("Chuyên cần", esc(note?.attend || "Chưa điểm danh"));
      const actions = row.canLeave
        ? button("Gửi đơn xin nghỉ", "leave-session", row.buoiHocId, "warn")
        : "";
      openDialog(
        "Chi tiết buổi học",
        `<div class="parent-summary">${summary}</div>${documentLink(row.fileUrl)}`,
        null,
        "",
        actions,
      );
    }
    function availableSessions(id) {
      const existing = new Set(
        data.leaveRequests
          .filter(
            (row) =>
              String(row.studentId) === String(id) &&
              ["Chờ giáo vụ duyệt", "Đã duyệt"].includes(row.status),
          )
          .map((row) => row.buoiHocId),
      );
      return (data.schedules[id] || [])
        .filter(
          (row) =>
            row.canLeave === true &&
            sessionStart(row) > serverNow.getTime() &&
            !existing.has(row.buoiHocId),
        )
        .sort((left, right) => sessionStart(left) - sessionStart(right));
    }
    function openLeave(sessionId = "") {
      if (!student() || !loaded) return;
      const studentOptions = data.students
        .map(
          (row) =>
            `<option value="${esc(row.id)}" ${String(row.id) === childId ? "selected" : ""}>${esc(row.hoTen)}</option>`,
        )
        .join("");
      openDialog(
        "Gửi đơn xin nghỉ học",
        `<label>Học viên<select name="hocVienId" id="leaveKid" required>${studentOptions}</select></label><label>Buổi học cần xin nghỉ<select name="buoiHocId" id="leaveSession" required></select></label><label>Lý do xin nghỉ<textarea name="lyDo" rows="4" maxlength="500" required placeholder="Nhập lý do nghỉ học"></textarea></label>`,
        async (values) => {
          await HttpService.post("/people/parents/leave-requests", {
            hocVienId: Number(values.hocVienId),
            buoiHocId: Number(values.buoiHocId),
            lyDo: values.lyDo.trim(),
          });
          await loadData();
          selectChild(values.hocVienId);
          location.hash = "/parent/parent-leave";
          notify("Đã gửi đơn xin nghỉ đến Giáo vụ.");
        },
        "Gửi đơn xin nghỉ",
      );
      const fillSessions = () => {
        const rows = availableSessions($("leaveKid").value);
        $("leaveSession").innerHTML =
          '<option value="">Chọn buổi học</option>' +
          rows
            .map(
              (row) =>
                `<option value="${esc(row.buoiHocId)}">${esc(row.date)} · ${esc(row.time)} · ${esc(row.course)}</option>`,
            )
            .join("");
        $("parentDialogError").textContent = rows.length
          ? ""
          : "Chưa có buổi học sắp tới có thể gửi đơn xin nghỉ.";
      };
      $("leaveKid").addEventListener("change", fillSessions);
      fillSessions();
      if (sessionId) $("leaveSession").value = sessionId;
    }
    function buildQrSvg(seedText) {
      const size = 25;
      const grid = Array.from({ length: size }, () => Array(size).fill(false));
      const reserved = Array.from({ length: size }, () => Array(size).fill(false));
      const putFinder = (r0, c0) => {
        for (let r = -1; r <= 7; r++) {
          for (let c = -1; c <= 7; c++) {
            const rr = r0 + r, cc = c0 + c;
            if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue;
            reserved[rr][cc] = true;
            grid[rr][cc] =
              r >= 0 && r <= 6 && c >= 0 && c <= 6 &&
              (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4));
          }
        }
      };
      putFinder(0, 0);
      putFinder(0, size - 7);
      putFinder(size - 7, 0);
      for (let i = 8; i < size - 8; i++) {
        reserved[6][i] = reserved[i][6] = true;
        grid[6][i] = grid[i][6] = i % 2 === 0;
      }
      for (let r = size - 9; r <= size - 5; r++) {
        for (let c = size - 9; c <= size - 5; c++) {
          reserved[r][c] = true;
          const dr = Math.abs(r - (size - 7)), dc = Math.abs(c - (size - 7));
          grid[r][c] = Math.max(dr, dc) === 2 || (dr === 0 && dc === 0);
        }
      }
      let hash = 2166136261;
      for (let i = 0; i < seedText.length; i++) {
        hash ^= seedText.charCodeAt(i);
        hash = Math.imul(hash, 16777619);
      }
      for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
          if (reserved[r][c]) continue;
          hash ^= (r * 31 + c * 17) & 0xff;
          hash = Math.imul(hash, 16777619);
          grid[r][c] = (hash >>> 3) % 10 < 5;
        }
      }
      let rects = "";
      for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
          if (grid[r][c]) rects += `<rect x="${c + 2}" y="${r + 2}" width="1" height="1" fill="#0f172a"/>`;
        }
      }
      return `<svg viewBox="0 0 ${size + 4} ${size + 4}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mã QR thanh toán"><rect width="${size + 4}" height="${size + 4}" rx="2" fill="#ffffff"/>${rects}</svg>`;
    }

    function buildQrPaymentCard(invoiceCode, amount) {
      const memo = `EDU ${invoiceCode}`;
      const qrUrl = `https://img.vietqr.io/image/VCB-1029384756-qr_only.png?amount=${encodeURIComponent(amount)}&addInfo=${encodeURIComponent(memo)}&accountName=${encodeURIComponent("TRUNG TAM GIA SU EDUCENTER")}`;
      const fallbackSvg = buildQrSvg(`${memo}|${amount}|VCB|1029384756`);
      return `<div class="parent-qr-card">
        <div class="parent-qr-frame">
          <span class="parent-qr-badge">VIETQR · NAPAS 247</span>
          <div class="parent-qr-visual">
            <img src="${esc(qrUrl)}" alt="Mã VietQR ${esc(invoiceCode)}" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            <div hidden>${fallbackSvg}</div>
          </div>
        </div>
        <div class="parent-qr-meta">
          <div class="parent-qr-meta-title">Quét mã QR để thanh toán học phí</div>
          <div class="parent-qr-row"><span>Ngân hàng</span><b>Vietcombank (VCB)</b></div>
          <div class="parent-qr-row"><span>Số tài khoản</span><b>1029384756</b></div>
          <div class="parent-qr-row"><span>Chủ tài khoản</span><b>TRUNG TAM GIA SU EDUCENTER</b></div>
          <div class="parent-qr-row"><span>Số tiền</span><b class="parent-qr-amount">${esc(money(amount))}</b></div>
          <div class="parent-qr-row"><span>Nội dung CK</span><b>${esc(memo)}</b></div>
        </div>
      </div>`;
    }

    function openEnrollCourse() {
      if (!loaded) return;
      const courses = Array.isArray(data.courses) ? data.courses : [];
      if (!courses.length) {
        notify("Hiện chưa có khóa học nào đang mở đăng ký.", true);
        return;
      }
      const studentOptions = data.students
        .map(
          (row) =>
            `<option value="${esc(row.id)}" ${String(row.id) === childId ? "selected" : ""}>${esc(row.hoTen)} (${esc(row.maHv)})</option>`,
        )
        .join("");
      const courseOptions = courses
        .map(
          (c) =>
            `<option value="${esc(c.id)}">${esc(c.ten)} — ${esc(money(c.hocPhi))} / trọn khóa</option>`,
        )
        .join("");
      openDialog(
        "Đăng ký Khóa học & Thanh toán QR",
        `<label>Học viên đăng ký<select name="hocVienId" required>${studentOptions}</select></label><label>Chọn Khóa học trọn gói<select name="khoaHocId" required>${courseOptions}</select></label><p class="parent-payment-note">Sau khi xác nhận đăng ký, hệ thống sẽ phát hành Hóa đơn học phí trọn khóa và mở ngay mã QR để Phụ huynh thanh toán.</p>`,
        async (values) => {
          const res = await HttpService.post("/people/parents/enrollments", {
            hocVienId: Number(values.hocVienId),
            khoaHocId: Number(values.khoaHocId),
          });
          await loadData();
          selectChild(values.hocVienId);
          location.hash = "/parent/parent-billing";
          showScreen();
          const newInvId = res?.data?.hoaDon?.id;
          if (newInvId) {
            setTimeout(() => openInvoice(newInvId, true), 60);
          } else {
            notify("Đã đăng ký khóa học và tạo hóa đơn thành công.");
          }
        },
        "Tiếp tục & Mở mã QR thanh toán →",
      );
    }

    function openInvoice(id, payment = false) {
      const invoice = invoices().find((row) => String(row.id) === String(id));
      if (!invoice) return;
      const remaining = Math.max(
        0,
        Number(invoice.must) - Number(invoice.paid),
      );
      const summary =
        item("Hóa đơn", `<strong>${esc(invoice.code)}</strong>`) +
        item("Học viên", esc(student()?.hoTen)) +
        item("Khóa học", esc(invoice.course)) +
        item("Phải thu", money(invoice.must)) +
        item("Đã xác nhận thu", money(invoice.paid)) +
        item(
          "Còn lại",
          `<strong class="parent-due">${money(remaining)}</strong>`,
        );
      if (!payment || !remaining) {
        openDialog(
          "Chi tiết hóa đơn",
          `<div class="parent-summary">${summary}</div>`,
          null,
          "",
          button("In hóa đơn", "print"),
        );
        return;
      }
      openDialog(
        "💳 Thanh toán Học phí qua Mã QR (VietQR)",
        `<div class="parent-summary">${summary}</div>${buildQrPaymentCard(invoice.code, remaining)}<label>Phương thức thanh toán trực tuyến<select name="nhaCungCap"><option value="VIETQR_NAPAS">Chuyển khoản QR Ngân hàng (VietQR / Napas 247)</option><option value="VNPAY_GATEWAY">Cổng thanh toán VNPay QR</option><option value="MOMO_BUSINESS">Ví điện tử MoMo Doanh nghiệp</option></select></label><label>Số tiền thanh toán (VNĐ)<input type="text" value="${esc(money(remaining))}" readonly></label><p class="parent-payment-note">Quét mã QR phía trên hoặc bấm xác nhận bên dưới để hoàn tất thanh toán. Hệ thống sẽ tự động lập phiếu thu và đồng bộ học phí sang Giáo vụ &amp; Admin.</p>`,
        async (values) => {
          await HttpService.post("/people/parents/payments", {
            hoaDonId: Number(invoice.id),
            nhaCungCap: values.nhaCungCap,
            soTien: remaining,
            xacNhanQr: true,
          });
          await loadData();
          notify("Thanh toán QR thành công! Học phí đã được gạch nợ và đồng bộ hệ thống.");
        },
        "✓ Tôi đã quét QR & Hoàn tất thanh toán",
      );
    }
    function respondToProposal(id, accepted) {
      const proposal = proposals.find((row) => String(row.id) === String(id));
      if (!proposal) return;
      const body = `<p>${accepted ? "Xác nhận đồng ý" : "Từ chối"} đề xuất <strong>${esc(proposal.tenNguoiDay)}</strong> cho ${esc(proposal.tenHocVien || proposal.hoTenLienHe)}?</p>${accepted ? "" : '<label>Lý do từ chối<textarea name="lyDo" rows="3" maxlength="255" required></textarea></label>'}`;
      openDialog(
        accepted ? "Xác nhận người dạy" : "Từ chối đề xuất",
        body,
        async (values) => {
          await HttpService.post(
            `/academic/admissions/proposals/${proposal.id}/respond`,
            {
              trangThai: accepted ? "dong_y" : "tu_choi",
              lyDo: values.lyDo?.trim() || null,
            },
          );
          await loadData();
          notify("Đã gửi phản hồi đề xuất đến Giáo vụ.");
        },
      );
    }
    function openPassword() {
      $("parentAccountMenu").open = false;
      openDialog(
        "Đổi mật khẩu",
        '<label>Mật khẩu hiện tại<input type="password" name="currentPassword" autocomplete="current-password" required maxlength="72"></label><label>Mật khẩu mới<input type="password" name="newPassword" autocomplete="new-password" required minlength="6" maxlength="72"></label><label>Xác nhận mật khẩu mới<input type="password" name="confirmPassword" autocomplete="new-password" required minlength="6" maxlength="72"></label>',
        async (values) => {
          if (values.newPassword !== values.confirmPassword)
            throw new Error("Mật khẩu xác nhận chưa khớp.");
          await HttpService.put("/auth/change-password", {
            currentPassword: values.currentPassword,
            newPassword: values.newPassword,
          });
          AccessService.clearSession();
          window.location.replace(AppConfig.AUTH_PAGE);
        },
        "Cập nhật mật khẩu",
      );
    }

    document.addEventListener("click", async (event) => {
      if (
        !$("parentNotificationPanel").contains(event.target) &&
        !$("parent-bell").contains(event.target)
      )
        showNotifications(false);
      if (!$("parentAccountMenu").contains(event.target))
        $("parentAccountMenu").open = false;
      const control = event.target.closest(
        "button[data-tab], button[data-action], button[data-calendar-view]",
      );
      if (!control || control.disabled) return;
      if (control.dataset.tab) {
        location.hash = `/parent/parent-${control.dataset.tab}`;
        showScreen();
        return;
      }
      if (control.dataset.calendarView) {
        calendarView = control.dataset.calendarView;
        renderCalendar();
        return;
      }
      const action = control.dataset.action;
      const id = control.dataset.id;
      if (
        loading &&
        [
          "leave",
          "leave-session",
          "pay",
          "proposal-accept",
          "proposal-reject",
          "read",
          "read-all",
        ].includes(action)
      ) {
        notify("Đang cập nhật dữ liệu. Vui lòng thử lại sau ít giây.");
        return;
      }
      switch (action) {
        case "child":
          selectChild(id);
          break;
        case "reload":
          await loadData();
          break;
        case "previous-week":
          changeCalendarPeriod(-1);
          break;
        case "next-week":
          changeCalendarPeriod(1);
          break;
        case "today":
          calendarDay = dayKey(serverNow);
          renderCalendar();
          break;
        case "session":
          openSession(id);
          break;
        case "leave-session":
          closeDialog();
          openLeave(id);
          break;
        case "leave":
          openLeave();
          break;
        case "pay":
          openInvoice(id, true);
          break;
        case "enroll-course":
          openEnrollCourse();
          break;
        case "invoice":
          openInvoice(id);
          break;
        case "proposal-accept":
          respondToProposal(id, true);
          break;
        case "proposal-reject":
          respondToProposal(id, false);
          break;
        case "password":
          openPassword();
          break;
        case "dialog-close":
          closeDialog();
          break;
        case "print":
          window.print();
          break;
        case "read":
          await busy(control, async () => {
            await HttpService.post(
              `/communication/notifications/${Number(id)}/read`,
              {},
            );
            await loadData();
          });
          break;
        case "read-all":
          await busy(control, async () => {
            await HttpService.post("/communication/notifications/read-all", {});
            await loadData();
            notify("Đã đánh dấu tất cả thông báo là đã đọc.");
          });
          break;
      }
    });
    $("studentSelector").addEventListener("change", (event) =>
      selectChild(event.target.value),
    );
    $("btnLogout").addEventListener("click", (event) =>
      busy(event.currentTarget, async () => {
        await HttpService.post("/auth/logout", {});
        AccessService.clearSession();
        window.location.replace(AppConfig.AUTH_PAGE);
      }),
    );
    $("parent-menu").addEventListener("click", () => {
      const open = $("parent-sidebar").classList.toggle("open");
      $("parent-backdrop").hidden = !open;
      $("parent-menu").setAttribute("aria-expanded", String(open));
    });
    $("parent-backdrop").addEventListener("click", closeMenu);
    $("parent-bell").addEventListener("click", () => {
      showNotifications($("parentNotificationPanel").hidden);
      $("parentAccountMenu").open = false;
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !dialog.open) {
        closeMenu();
        showNotifications(false);
        $("parentAccountMenu").open = false;
      }
    });
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener("cancel", (event) => {
      if (dialog.dataset.busy === "true") event.preventDefault();
    });
    window.addEventListener("hashchange", showScreen);
    showScreen();
    render();
    loadData();
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
