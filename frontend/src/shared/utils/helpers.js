/* Shared account dialogs. Identity and editable fields are enforced again by the server. */
window.AccountUI = (() => {
  "use strict";
  const labels = {ADMIN:"Quản trị viên",GIAO_VU:"Giáo vụ",GIAO_VIEN:"Giáo viên",GIA_SU:"Gia sư",TRO_GIANG:"Trợ giảng",PHU_HUYNH:"Phụ huynh"};
  const esc = value => String(value ?? "").replace(/[&<>"']/g, char => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[char]);
  const initial = name => (String(name || "").replace(/\[[^\]]*\]/g, "").trim().split(/\s+/).pop()?.[0] || "E").toUpperCase();
  const api = async (path, method = "get", body) => (await HttpService[method](path, body)).data;
  let dialog;
  let pending = false;
  let profileView;
  function closeMenus() {
    document.querySelectorAll("details.parent-account").forEach(el => { el.open = false; });
    document.getElementById("userDropdown")?.classList.remove("show");
    document.getElementById("admin-avatar")?.setAttribute("aria-expanded", "false");
    const menu = document.getElementById("teacher-account-menu");
    if (menu) menu.hidden = true;
    document.getElementById("teacher-avatar")?.setAttribute("aria-expanded", "false");
    const assistantMenu = document.getElementById("assistant-account-menu");
    if (assistantMenu) assistantMenu.hidden = true;
    document.getElementById("assistant-avatar")?.setAttribute("aria-expanded", "false");
    const tutorMenu = document.getElementById("tutor-account-menu");
    if (tutorMenu) tutorMenu.hidden = true;
    document.getElementById("tutor-avatar")?.setAttribute("aria-expanded", "false");
  }
  function open(title, body) {
    closeMenus();
    dialog?.classList.remove("account-dialog-wide");
    if (!dialog) {
      dialog = document.createElement("dialog");
      dialog.className = "account-dialog";
      dialog.setAttribute("aria-labelledby", "account-title");
      document.body.append(dialog);
      dialog.addEventListener("cancel", event => { if (pending) event.preventDefault(); });
      dialog.addEventListener("click", event => {
        if (event.target.closest("[data-account-close]") && !pending) dialog.close();
      });
    }
    dialog.innerHTML = `<header><h2 id="account-title">${esc(title)}</h2><button class="btn sm" type="button" data-account-close aria-label="Đóng">✕</button></header><div class="account-body">${body}</div>`;
    if (!dialog.open) dialog.showModal();
  }
  const row = (label, value) => `<div class="account-row"><dt>${esc(label)}</dt><dd>${esc(value ?? "Chưa cập nhật") || "Chưa cập nhật"}</dd></div>`;
  const date = value => value ? value.slice(0,10).split("-").reverse().join("/") : null;
  const status = value => ({dang_lam:"Đang làm",nghi_viec:"Đã nghỉ việc",hoat_dong:"Đang hoạt động",tam_nghi:"Tạm nghỉ",ngung_hop_tac:"Ngừng hợp tác"})[value] || value;
  async function view() {
    open("Hồ sơ cá nhân", '<p role="status">Đang tải hồ sơ…</p>');
    try {
      const p = await api("/auth/me/profile");
      if (!dialog.open) return;
      let extra = "";
      if (p.staff) extra = `<h3>Thông tin công tác</h3><dl>${row("Mã nhân viên",p.staff.maNv)}${row("Chức vụ",p.staff.chucVu)}${row("Cơ sở",p.staff.tenCoSo)}${row("Ngày vào làm",date(p.staff.ngayVaoLam))}${row("Trạng thái",status(p.staff.trangThai))}</dl>`;
      if (p.professional) {
        const ns = p.professional;
        extra = `<h3>Hồ sơ chuyên môn · ${esc(labels[p.role])}</h3><dl>${row("Mã nhân sự",ns.maNs)}${row("Giới tính",{nam:"Nam",nu:"Nữ",khac:"Khác"}[ns.gioiTinh])}${row("Đơn vị công tác",ns.donViCongTac)}${row("Kinh nghiệm",ns.kinhNghiemNam + " năm")}${row(p.role === "TRO_GIANG" ? "Môn và khối hỗ trợ" : p.role === "GIA_SU" ? "Môn và khối gia sư" : "Môn và khối giảng dạy",ns.monDays.map(m=>`${m.tenMon} · Khối ${m.capLopTu}–${m.capLopDen}`).join(", "))}${row("Hình thức",ns.hinhThucDay?.split(",").map(v=>v === "online" ? "Trực tuyến" : "Trực tiếp").join(", "))}${row("Khu vực",ns.khuVucs.map(k=>k.tenKhuVuc).join(", "))}${row("Trạng thái",status(ns.trangThai))}${row("Giới thiệu chuyên môn",ns.gioiThieu)}</dl>`;
      }
      if (p.role === "PHU_HUYNH") extra = `<h3>Thông tin phụ huynh</h3><dl>${row("Nghề nghiệp",p.ngheNghiep)}</dl><h3>Học sinh đang theo dõi</h3>${p.children.length ? `<ul>${p.children.map(c=>`<li>${esc(c.hoTen)} · ${esc(c.maHv)}</li>`).join("")}</ul>` : '<p>Chưa có học sinh liên kết.</p>'}`;
      const roleContent = profileView ? await profileView() : "";
      open("Hồ sơ cá nhân", `<div class="account-identity"><b>${esc(p.hoTen)}</b><span>${esc(labels[p.role])}</span></div><dl>${row("Tên đăng nhập",p.username)}${row("Email",p.email)}${row("Số điện thoại",p.sdt)}${row("Ngày sinh",date(p.ngaySinh))}${row("Địa chỉ",p.diaChi)}</dl>${extra}<footer><button class="btn" type="button" data-account-action="password">Đổi mật khẩu</button><button class="btn p" type="button" data-account-action="edit">Chỉnh sửa hồ sơ</button></footer>${roleContent}`);
      dialog.classList.toggle("account-dialog-wide",Boolean(roleContent));
    } catch (error) { open("Hồ sơ cá nhân", `<p class="account-error" role="alert">${esc(error.message)}</p>`); }
  }
  function field(label, name, value, type = "text", attrs = "") {
    return `<label>${esc(label)}<input name="${name}" type="${type}" value="${type === "password" ? "" : esc(value || "")}" ${attrs} aria-describedby="account-error-${name}"><small class="account-error" id="account-error-${name}" aria-live="polite"></small></label>`;
  }
  function wireForm(work, validate = () => ({})) {
    const form = dialog.querySelector("form");
    const values = () => Object.fromEntries(new FormData(form));
    function errors() {
      const issues = validate(values());
      form.querySelectorAll("[name]").forEach(control => {
        const message = issues[control.name] || "";
        control.setCustomValidity(message);
        control.setAttribute("aria-invalid", String(Boolean(message)));
        const hint = form.querySelector(`#account-error-${control.name}`);
        if (hint) hint.textContent = message;
      });
      return Object.keys(issues).length > 0;
    }
    form.addEventListener("input", errors);
    form.addEventListener("change", errors);
    form.addEventListener("submit", async event => {
      event.preventDefault();
      if (pending) return;
      if (errors()) { form.querySelector('[aria-invalid="true"]')?.focus(); return; }
      if (!form.reportValidity()) return;
      const body = values();
      pending = true;
      form.querySelector("[role=alert]").textContent = "";
      dialog.querySelectorAll("button,input,textarea,select").forEach(c=>{ c.disabled = true; });
      try { await work(body); }
      catch (error) {
        form.querySelector("[role=alert]").textContent = error.message;
        const key = {INVALID_CURRENT_PASSWORD:"currentPassword",PASSWORD_MISMATCH:"confirmPassword",PROFILE_EMAIL_EXISTS:"email",PROFILE_PHONE_EXISTS:"sdt"}[error.data?.code];
        if (key) {
          form.querySelector(`#account-error-${key}`).textContent = error.message;
          form.elements[key].setAttribute("aria-invalid", "true");
        }
      } finally {
        pending = false;
        dialog.querySelectorAll("button,input,textarea,select").forEach(c=>{ c.disabled = false; });
        form.querySelector('[aria-invalid="true"]')?.focus();
      }
    });
  }
  const foot = label => `<p class="account-error" role="alert"></p><footer><button class="btn" type="button" data-account-close>Hủy</button><button class="btn p" type="submit">${label}</button></footer>`;
  async function edit() {
    open("Chỉnh sửa hồ sơ cá nhân", '<p role="status">Đang tải hồ sơ…</p>');
    try {
      const p = await api("/auth/me/profile");
      if (!dialog.open) return;
      const ns = p.professional;
      open("Chỉnh sửa hồ sơ cá nhân", `<p class="account-muted">${esc(labels[p.role])} · ${esc(p.username)}</p><form novalidate><div class="account-grid">${field("Họ và tên *","hoTen",p.hoTen,"text",'required maxlength="150" autocomplete="name"')}${field("Email *","email",p.email,"email",'required maxlength="150" autocomplete="email"')}${field("Số điện thoại","sdt",p.sdt,"tel",'maxlength="20" autocomplete="tel"')}${field("Ngày sinh","ngaySinh",p.ngaySinh,"date",`max="${new Date().toLocaleDateString("sv-SE",{timeZone:"Asia/Ho_Chi_Minh"})}"`)}${field("Địa chỉ","diaChi",p.diaChi,"text",'maxlength="255" autocomplete="street-address"')}${p.role === "PHU_HUYNH" ? field("Nghề nghiệp","ngheNghiep",p.ngheNghiep,"text",'maxlength="100"') : ""}${ns ? `<label>Giới tính<select name="gioiTinh">${Object.entries({"":"Chưa cập nhật",nam:"Nam",nu:"Nữ",khac:"Khác"}).map(([v,l])=>`<option value="${v}" ${v === ns.gioiTinh ? "selected" : ""}>${l}</option>`).join("")}</select></label>${field("Đơn vị công tác","donViCongTac",ns.donViCongTac,"text",'maxlength="150"')}<label class="account-full">Giới thiệu chuyên môn<textarea name="gioiThieu" rows="4" maxlength="10000">${esc(ns.gioiThieu)}</textarea></label>` : ""}</div>${foot("Lưu hồ sơ")}</form>`);
      wireForm(async body => {
        Object.keys(body).forEach(key=>{ body[key] = body[key].trim() || null; });
        const saved = await api("/auth/me/profile","put",body);
        const me = await api("/auth/me");
        AccessService.setSession(null,me.user);
        document.querySelectorAll("#currentUserName,#accountMenuName,#udName,#admin-name,#teacher-name,#tutor-name,#assistant-name,#accountName").forEach(el=>{ el.textContent = saved.hoTen; });
        document.querySelectorAll("#parentAvatar,#admin-avatar,#teacher-avatar,#tutor-avatar,#assistant-avatar,#account-avatar").forEach(el=>{ el.textContent = initial(saved.hoTen); });
        window.dispatchEvent(new CustomEvent("account-profile-updated"));
        open("Đã cập nhật hồ sơ", `<p role="status">Thông tin cá nhân đã được lưu.</p><footer><button class="btn p" type="button" data-account-action="view">Xem hồ sơ</button></footer>`);
      }, body => {
        const issues = {};
        if (!body.hoTen.trim()) issues.hoTen = "Vui lòng nhập họ và tên.";
        if (!body.email || !formEmail(body.email)) issues.email = "Email không đúng định dạng.";
        if (body.sdt && !/^\+?[0-9][0-9 .()-]{5,19}$/.test(body.sdt)) issues.sdt = "Số điện thoại không đúng định dạng.";
        if (body.ngaySinh && body.ngaySinh > new Date().toLocaleDateString("sv-SE",{timeZone:"Asia/Ho_Chi_Minh"})) issues.ngaySinh = "Ngày sinh không được ở tương lai.";
        return issues;
      });
    } catch (error) { open("Chỉnh sửa hồ sơ cá nhân", `<p class="account-error" role="alert">${esc(error.message)}</p>`); }
  }
  function formEmail(value) { const input = document.createElement("input"); input.type = "email"; input.value = value; return input.checkValidity(); }
  function password() {
    open("Đổi mật khẩu", `<p class="account-muted">Sau khi đổi mật khẩu, bạn cần đăng nhập lại. Các phiên đăng nhập cũ sẽ hết hiệu lực.</p><form novalidate>${field("Mật khẩu hiện tại *","currentPassword","","password",'required autocomplete="current-password"')}${field("Mật khẩu mới *","newPassword","","password",'required autocomplete="new-password"')}${field("Xác nhận mật khẩu mới *","confirmPassword","","password",'required autocomplete="new-password"')}<p class="account-muted">Mật khẩu mới từ 6 đến 72 byte. Ký tự tiếng Việt có thể chiếm nhiều byte.</p>${foot("Đổi mật khẩu")}</form>`);
    wireForm(async body => {
      await api("/auth/change-password","put",body);
      AccessService.clearSession();
      window.location.replace(AppConfig.AUTH_PAGE + "?passwordChanged=1");
    }, body => {
      const issues = {};
      if (!body.currentPassword) issues.currentPassword = "Vui lòng nhập mật khẩu hiện tại.";
      const bytes = new TextEncoder().encode(body.newPassword).length;
      if (bytes < 6 || bytes > 72 || body.newPassword.includes("\0")) issues.newPassword = "Mật khẩu mới cần từ 6 đến 72 byte, không chứa ký tự rỗng.";
      if (!body.confirmPassword || body.confirmPassword !== body.newPassword) issues.confirmPassword = "Xác nhận mật khẩu mới chưa khớp.";
      return issues;
    });
  }
  async function logout() {
    if (pending) return;
    open("Đăng xuất", '<p role="status">Đang đăng xuất…</p>');
    pending = true;
    try { await api("/auth/logout","post",{}); AccessService.markLoggedOut(); window.location.replace(AppConfig.AUTH_PAGE + "?loggedOut=1"); }
    catch (error) { open("Đăng xuất", `<p class="account-error" role="alert">${esc(error.message)}</p><button class="btn" data-account-action="logout">Thử lại</button>`); }
    finally { pending = false; }
  }
  document.addEventListener("click", event => {
    if (!event.target.closest("details.parent-account,.teacher-account,.tutor-account,.assistant-account,#userDropdown,#admin-avatar,#tutor-avatar,#assistant-avatar")) closeMenus();
    const control = event.target.closest("[data-account-action]");
    if (!control || control.disabled || pending) return;
    ({view,edit,password,logout})[control.dataset.accountAction]?.();
  });
  document.addEventListener("keydown", event => { if (event.key === "Escape") closeMenus(); });
  return {view,edit,password,logout,initial,close:()=>dialog?.close(),setProfileView:handler=>{ profileView = handler; }};
})();

/* Shared Vietnamese Date/Time Formatter & Unified Date/Month/Time Picker across all portals. */
window.ECDate = (() => {
  "use strict";
  const pad = n => String(n).padStart(2, "0");
  function todayYmd() {
    return new Date().toLocaleDateString("sv-SE", { timeZone: "Asia/Ho_Chi_Minh" });
  }
  function date(value) {
    if (!value) return "—";
    const s = String(value).trim();
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(s)) return s;
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) return `${m[3]}/${m[2]}/${m[1]}`;
    const ym = s.match(/^(\d{4})-(\d{2})$/);
    if (ym) return `${ym[2]}/${ym[1]}`;
    return s;
  }
  function dateTime(value) {
    if (!value) return "—";
    const s = String(value).trim();
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2})(?::\d{2})?)?/);
    if (m) return m[4] ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}` : `${m[3]}/${m[2]}/${m[1]}`;
    const ym = s.match(/^(\d{4})-(\d{2})$/);
    if (ym) return `${ym[2]}/${ym[1]}`;
    return s;
  }
  function month(value) {
    if (!value) return "—";
    const m = String(value).trim().match(/^(\d{4})-(\d{2})/);
    return m ? `${m[2]}/${m[1]}` : String(value);
  }
  function replaceInlineDates(text) {
    if (text == null || text === "") return "";
    return String(text)
      .replace(/(^|[^A-Za-z0-9-])(20\d{2})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])(?![A-Za-z0-9-])/g, "$1$4/$3/$2")
      .replace(/\bT8\b/g, "CN");
  }
  return { pad, todayYmd, date, dateTime, month, replaceInlineDates };
})();

window.ECPicker = (() => {
  "use strict";
  const pad = n => String(n).padStart(2, "0");
  const esc = v => String(v ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);
  const nativeValDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
  const MONTHS_SHORT = ["Thg 1","Thg 2","Thg 3","Thg 4","Thg 5","Thg 6","Thg 7","Thg 8","Thg 9","Thg 10","Thg 11","Thg 12"];
  const WEEKDAYS = ["T2","T3","T4","T5","T6","T7","CN"];
  const QUICK_TIMES = ["08:00","09:00","14:00","16:00","17:30","18:00","19:30","20:00"];
  const ICO_CAL = '<svg class="svg-ico sm ec-dp-ico" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
  const ICO_CLK = '<svg class="svg-ico sm ec-dp-ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

  let activePop = null;
  let activeAnchor = null;

  function closeActive() {
    if (activePop) {
      activePop.remove();
      activePop = null;
    }
    if (activeAnchor) {
      activeAnchor.classList.remove("open");
      activeAnchor.setAttribute("aria-expanded", "false");
      activeAnchor = null;
    }
  }

  function positionPop(pop, anchor) {
    const r = anchor.getBoundingClientRect();
    const pw = pop.offsetWidth || 280;
    const ph = pop.offsetHeight || 300;
    let left = r.left;
    if (left + pw > window.innerWidth - 10) left = Math.max(8, window.innerWidth - pw - 10);
    if (left < 8) left = 8;
    let top = r.bottom + 6;
    if (top + ph > window.innerHeight - 10 && r.top - ph - 6 > 8) {
      top = r.top - ph - 6;
    } else if (top + ph > window.innerHeight - 10) {
      top = Math.max(8, window.innerHeight - ph - 10);
    }
    pop.style.left = `${Math.round(left)}px`;
    pop.style.top = `${Math.round(top)}px`;
  }

  function createPop(anchor, extraClass = "") {
    closeActive();
    const pop = document.createElement("div");
    pop.className = `ec-dp-pop ${extraClass}`.trim();
    pop.setAttribute("data-ec-dp-pop", "1");
    pop.setAttribute("role", "dialog");
    const host = anchor.closest("dialog") || document.body;
    host.appendChild(pop);
    activePop = pop;
    activeAnchor = anchor;
    anchor.classList.add("open");
    anchor.setAttribute("aria-expanded", "true");
    return pop;
  }

  function detectMode(input) {
    if (!(input instanceof HTMLInputElement)) return null;
    if (input.dataset.noEcPicker === "1" || input.type === "hidden") return null;
    if (input.type === "date") return "date";
    if (input.type === "month") return "month";
    if (input.type === "time" || input.hasAttribute("data-time24")) return "time";
    return null;
  }

  function formatDisplay(mode, val) {
    if (!val) return mode === "date" ? "dd/mm/yyyy" : mode === "month" ? "Chọn tháng" : "HH:mm";
    if (mode === "date") return window.ECDate.date(val);
    if (mode === "month") {
      const m = String(val).match(/^(\d{4})-(\d{2})/);
      return m ? `Tháng ${Number(m[2])} / ${m[1]}` : val;
    }
    return String(val).slice(0, 5);
  }

  function setNativeValue(input, nextVal, emit = true) {
    const prev = nativeValDesc.get.call(input);
    nativeValDesc.set.call(input, nextVal);
    input._ecSync?.();
    if (emit && prev !== nextVal) {
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }

  function openDatePop(input, btn) {
    const pop = createPop(btn, "ec-dp-date");
    const today = window.ECDate.todayYmd();
    const minStr = input.getAttribute("min") || "";
    const maxStr = input.getAttribute("max") || "";
    const curVal = nativeValDesc.get.call(input) || "";
    const initYmd = /^\d{4}-\d{2}-\d{2}$/.test(curVal) ? curVal : today;
    let viewY = Number(initYmd.slice(0, 4)) || Number(today.slice(0, 4));
    let viewM = (Number(initYmd.slice(5, 7)) || Number(today.slice(5, 7))) - 1;
    let viewMode = "days"; // days | months | years

    const isOutOfRange = ymd => (minStr && ymd < minStr) || (maxStr && ymd > maxStr);

    const render = () => {
      const selected = nativeValDesc.get.call(input) || "";
      let headTitle = `Tháng ${viewM + 1} / ${viewY}`;
      if (viewMode === "months") headTitle = `Năm ${viewY}`;
      else if (viewMode === "years") {
        const startY = Math.floor(viewY / 12) * 12;
        headTitle = `${startY} – ${startY + 11}`;
      }

      let body = "";
      if (viewMode === "days") {
        const first = new Date(viewY, viewM, 1);
        const lead = (first.getDay() + 6) % 7;
        const dim = new Date(viewY, viewM + 1, 0).getDate();
        const prevDim = new Date(viewY, viewM, 0).getDate();
        const cells = [];
        for (let i = lead - 1; i >= 0; i--) {
          const d = prevDim - i;
          const dt = new Date(viewY, viewM - 1, d);
          cells.push({ d, ymd: `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(d)}`, other: true });
        }
        for (let d = 1; d <= dim; d++) {
          cells.push({ d, ymd: `${viewY}-${pad(viewM + 1)}-${pad(d)}`, other: false });
        }
        let nextD = 1;
        while (cells.length % 7 !== 0 || cells.length < 35) {
          const dt = new Date(viewY, viewM + 1, nextD);
          cells.push({ d: nextD++, ymd: `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`, other: true });
        }
        body = `<div class="ec-dp-wk">${WEEKDAYS.map(w => `<span>${w}</span>`).join("")}</div>
          <div class="ec-dp-days">${cells.map(c => {
            const disabled = isOutOfRange(c.ymd);
            const cls = [
              "ec-dp-day",
              c.other ? "other" : "",
              c.ymd === today ? "today" : "",
              c.ymd === selected ? "on" : "",
            ].filter(Boolean).join(" ");
            return `<button type="button" class="${cls}" data-pick-ymd="${c.ymd}" ${disabled ? "disabled" : ""}>${c.d}</button>`;
          }).join("")}</div>`;
      } else if (viewMode === "months") {
        body = `<div class="ec-dp-grid3">${MONTHS_SHORT.map((lbl, idx) => {
          const on = selected.startsWith(`${viewY}-${pad(idx + 1)}`);
          return `<button type="button" class="ec-dp-cell ${on ? "on" : ""}" data-pick-m="${idx}">${lbl}</button>`;
        }).join("")}</div>`;
      } else {
        const startY = Math.floor(viewY / 12) * 12;
        body = `<div class="ec-dp-grid3">${Array.from({ length: 12 }, (_, i) => {
          const y = startY + i;
          const on = selected.startsWith(`${y}-`);
          return `<button type="button" class="ec-dp-cell ${on ? "on" : ""}" data-pick-y="${y}">${y}</button>`;
        }).join("")}</div>`;
      }

      const canToday = !isOutOfRange(today);
      pop.innerHTML = `
        <div class="ec-dp-head">
          <button type="button" class="ec-dp-nav" data-nav="-1" aria-label="Trước">‹</button>
          <button type="button" class="ec-dp-title" data-cycle-view="1">${esc(headTitle)} <span class="ec-dp-caret">▾</span></button>
          <button type="button" class="ec-dp-nav" data-nav="1" aria-label="Sau">›</button>
        </div>
        ${body}
        <div class="ec-dp-foot">
          ${!input.required ? '<button type="button" class="ec-dp-link muted" data-clear="1">Xóa</button>' : "<span></span>"}
          ${canToday ? '<button type="button" class="ec-dp-link" data-today="1">Hôm nay</button>' : ""}
        </div>`;
      positionPop(pop, btn);
    };

    pop.addEventListener("click", ev => {
      ev.stopPropagation();
      const target = ev.target.closest("button");
      if (!target || target.disabled) return;
      if (target.dataset.nav) {
        const delta = Number(target.dataset.nav);
        if (viewMode === "days") {
          const d = new Date(viewY, viewM + delta, 1);
          viewY = d.getFullYear(); viewM = d.getMonth();
        } else if (viewMode === "months") {
          viewY += delta;
        } else {
          viewY += delta * 12;
        }
        render();
      } else if (target.dataset.cycleView) {
        viewMode = viewMode === "days" ? "months" : viewMode === "months" ? "years" : "days";
        render();
      } else if (target.dataset.pickY) {
        viewY = Number(target.dataset.pickY);
        viewMode = "months";
        render();
      } else if (target.dataset.pickM != null) {
        viewM = Number(target.dataset.pickM);
        viewMode = "days";
        render();
      } else if (target.dataset.pickYmd) {
        setNativeValue(input, target.dataset.pickYmd, true);
        closeActive();
      } else if (target.dataset.today) {
        setNativeValue(input, today, true);
        closeActive();
      } else if (target.dataset.clear) {
        setNativeValue(input, "", true);
        closeActive();
      }
    });

    render();
  }

  function openMonthMenu(anchor, currentYm, onPick, allowClear = false) {
    const pop = createPop(anchor, "ec-dp-month");
    const todayYm = window.ECDate.todayYmd().slice(0, 7);
    const activeYm = /^\d{4}-\d{2}$/.test(currentYm || "") ? currentYm : todayYm;
    let viewY = Number(activeYm.slice(0, 4)) || Number(todayYm.slice(0, 4));

    const render = () => {
      pop.innerHTML = `
        <div class="ec-dp-head">
          <button type="button" class="ec-dp-nav" data-nav="-1" aria-label="Năm trước">‹</button>
          <span class="ec-dp-title static">Năm ${viewY}</span>
          <button type="button" class="ec-dp-nav" data-nav="1" aria-label="Năm sau">›</button>
        </div>
        <div class="ec-dp-grid3">${MONTHS_SHORT.map((lbl, idx) => {
          const ym = `${viewY}-${pad(idx + 1)}`;
          const isCur = ym === todayYm;
          const on = ym === activeYm;
          return `<button type="button" class="ec-dp-cell ${on ? "on" : ""} ${isCur ? "today" : ""}" data-pick-ym="${ym}">${lbl}</button>`;
        }).join("")}</div>
        <div class="ec-dp-foot">
          ${allowClear ? '<button type="button" class="ec-dp-link muted" data-clear="1">Xóa</button>' : "<span></span>"}
          <button type="button" class="ec-dp-link" data-this-month="1">Tháng này</button>
        </div>`;
      positionPop(pop, anchor);
    };

    pop.addEventListener("click", ev => {
      ev.stopPropagation();
      const target = ev.target.closest("button");
      if (!target || target.disabled) return;
      if (target.dataset.nav) {
        viewY += Number(target.dataset.nav);
        render();
      } else if (target.dataset.pickYm) {
        closeActive();
        onPick(target.dataset.pickYm);
      } else if (target.dataset.thisMonth) {
        closeActive();
        onPick(todayYm);
      } else if (target.dataset.clear) {
        closeActive();
        onPick("");
      }
    });

    render();
  }

  function openTimePop(input, btn) {
    const pop = createPop(btn, "ec-dp-time");
    const curVal = (nativeValDesc.get.call(input) || "").slice(0, 5);
    const parts = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(curVal);
    let selH = parts ? parts[1] : "18";
    let selM = parts ? parts[2] : "00";

    const commit = (closeAfter = false) => {
      setNativeValue(input, `${selH}:${selM}`, true);
      if (closeAfter) closeActive();
    };

    const render = (scrollToSel = false) => {
      const hours = Array.from({ length: 24 }, (_, i) => pad(i));
      const mins = Array.from({ length: 12 }, (_, i) => pad(i * 5));
      if (!mins.includes(selM)) mins.push(selM), mins.sort();
      pop.innerHTML = `
        <div class="ec-dp-time-top">
          <span class="ec-dp-time-badge">${selH}:${selM}</span>
          <input type="text" class="ec-dp-time-direct" value="${selH}:${selM}" maxlength="5" placeholder="HH:mm" aria-label="Nhập giờ 24h">
          <button type="button" class="btn p sm" data-done="1">Xong</button>
        </div>
        <div class="ec-dp-quick">${QUICK_TIMES.map(t => `<button type="button" class="ec-dp-qchip ${t === `${selH}:${selM}` ? "on" : ""}" data-quick="${t}">${t}</button>`).join("")}</div>
        <div class="ec-dp-time-cols">
          <div class="ec-dp-tcol" data-col="h">
            <div class="ec-dp-tcol-lbl">Giờ</div>
            <div class="ec-dp-tlist">${hours.map(h => `<button type="button" class="ec-dp-titem ${h === selH ? "on" : ""}" data-h="${h}">${h}</button>`).join("")}</div>
          </div>
          <div class="ec-dp-tcol" data-col="m">
            <div class="ec-dp-tcol-lbl">Phút</div>
            <div class="ec-dp-tlist">${mins.map(m => `<button type="button" class="ec-dp-titem ${m === selM ? "on" : ""}" data-m="${m}">${m}</button>`).join("")}</div>
          </div>
        </div>`;
      positionPop(pop, btn);
      if (scrollToSel) {
        pop.querySelector('.ec-dp-tlist .on')?.scrollIntoView?.({ block: "center" });
        pop.querySelectorAll(".ec-dp-tlist").forEach(list => {
          const on = list.querySelector(".on");
          if (on) list.scrollTop = Math.max(0, on.offsetTop - list.clientHeight / 2 + on.offsetHeight / 2);
        });
      }
      const direct = pop.querySelector(".ec-dp-time-direct");
      direct?.addEventListener("change", () => {
        const m = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec(direct.value.trim());
        if (m) {
          selH = pad(Number(m[1]));
          selM = m[2];
          commit(false);
          render(true);
        }
      });
      direct?.addEventListener("keydown", ev => {
        if (ev.key === "Enter") {
          ev.preventDefault();
          const m = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec(direct.value.trim());
          if (m) { selH = pad(Number(m[1])); selM = m[2]; commit(true); }
        }
      });
    };

    pop.addEventListener("click", ev => {
      ev.stopPropagation();
      const target = ev.target.closest("button");
      if (!target) return;
      if (target.dataset.quick) {
        const [qh, qm] = target.dataset.quick.split(":");
        selH = qh; selM = qm;
        commit(true);
      } else if (target.dataset.h) {
        selH = target.dataset.h;
        commit(false);
        render(false);
      } else if (target.dataset.m) {
        selM = target.dataset.m;
        commit(false);
        render(false);
      } else if (target.dataset.done) {
        commit(true);
      }
    });

    render(true);
  }

  function enhanceInput(input) {
    const mode = detectMode(input);
    if (!mode || input.dataset.ecPickerBound === "1") return;
    if (!input.parentNode) return;
    input.dataset.ecPickerBound = "1";

    const wrap = document.createElement("span");
    wrap.className = `ec-dp-wrap ec-dp-wrap-${mode}`;

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "ec-dp-btn";
    btn.setAttribute("aria-haspopup", "dialog");
    btn.setAttribute("aria-expanded", "false");
    if (input.getAttribute("aria-label")) btn.setAttribute("aria-label", input.getAttribute("aria-label"));

    const valSpan = document.createElement("span");
    valSpan.className = "ec-dp-val";
    btn.appendChild(valSpan);
    btn.insertAdjacentHTML("beforeend", mode === "time" ? ICO_CLK : ICO_CAL);

    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(btn);
    wrap.appendChild(input);

    input.classList.add("ec-dp-native");
    input.tabIndex = -1;
    input.setAttribute("aria-hidden", "true");

    const sync = () => {
      const raw = nativeValDesc.get.call(input) || "";
      valSpan.textContent = formatDisplay(mode, raw);
      btn.classList.toggle("placeholder", !raw);
      btn.disabled = input.disabled;
      btn.classList.toggle("invalid", input.getAttribute("aria-invalid") === "true");
    };
    input._ecSync = sync;

    Object.defineProperty(input, "value", {
      configurable: true,
      get() { return nativeValDesc.get.call(this); },
      set(v) { nativeValDesc.set.call(this, v); sync(); }
    });

    const attrObs = new MutationObserver(sync);
    attrObs.observe(input, { attributes: true, attributeFilter: ["disabled", "aria-invalid", "value"] });
    input.addEventListener("change", sync);
    input.addEventListener("input", sync);
    input.form?.addEventListener("reset", () => setTimeout(sync, 0));

    btn.addEventListener("click", ev => {
      ev.preventDefault();
      ev.stopPropagation();
      if (btn.disabled) return;
      if (activeAnchor === btn) { closeActive(); return; }
      if (mode === "date") openDatePop(input, btn);
      else if (mode === "month") openMonthMenu(btn, nativeValDesc.get.call(input), ym => setNativeValue(input, ym, true), !input.required);
      else openTimePop(input, btn);
    });

    sync();
  }

  function enhance(root = document) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('input[type="date"], input[type="month"], input[type="time"], input[data-time24]').forEach(enhanceInput);
  }

  document.addEventListener("click", ev => {
    if (activePop && !activePop.contains(ev.target) && (!activeAnchor || !activeAnchor.contains(ev.target))) {
      closeActive();
    }
  });
  document.addEventListener("keydown", ev => {
    if (ev.key === "Escape" && activePop) {
      ev.stopPropagation();
      closeActive();
    }
  }, true);
  window.addEventListener("resize", () => { if (activePop && activeAnchor) positionPop(activePop, activeAnchor); });
  window.addEventListener("scroll", ev => {
    if (activePop && !activePop.contains(ev.target)) closeActive();
  }, true);

  let rafPending = false;
  function scheduleEnhance() {
    if (rafPending) return;
    rafPending = true;
    requestAnimationFrame(() => {
      rafPending = false;
      enhance(document);
    });
  }

  function boot() {
    enhance(document);
    if (document.body) {
      new MutationObserver(scheduleEnhance).observe(document.body, { childList: true, subtree: true });
    }
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();

  return { enhance, openMonthMenu, close: closeActive };
})();
