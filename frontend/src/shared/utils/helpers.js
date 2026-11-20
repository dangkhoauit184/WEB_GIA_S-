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
        extra = `<h3>Hồ sơ chuyên môn · ${esc(labels[p.role])}</h3><dl>${row("Mã nhân sự",ns.maNs)}${row("Giới tính",{nam:"Nam",nu:"Nữ",khac:"Khác"}[ns.gioiTinh])}${row("Đơn vị công tác",ns.donViCongTac)}${row("Kinh nghiệm",ns.kinhNghiemNam + " năm")}${row(p.role === "TRO_GIANG" ? "Môn và khối hỗ trợ" : "Môn và khối giảng dạy",ns.monDays.map(m=>`${m.tenMon} · Khối ${m.capLopTu}–${m.capLopDen}`).join(", "))}${row("Hình thức",ns.hinhThucDay?.split(",").map(v=>v === "online" ? "Trực tuyến" : "Trực tiếp").join(", "))}${row("Khu vực",ns.khuVucs.map(k=>k.tenKhuVuc).join(", "))}${row("Trạng thái",status(ns.trangThai))}${row("Giới thiệu chuyên môn",ns.gioiThieu)}</dl>`;
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
        document.querySelectorAll("#currentUserName,#accountMenuName,#udName,#admin-name,#teacher-name,#accountName").forEach(el=>{ el.textContent = saved.hoTen; });
        document.querySelectorAll("#parentAvatar,#admin-avatar,#teacher-avatar,#account-avatar").forEach(el=>{ el.textContent = initial(saved.hoTen); });
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
    try { await api("/auth/logout","post",{}); AccessService.clearSession(); window.location.replace(AppConfig.AUTH_PAGE); }
    catch (error) { open("Đăng xuất", `<p class="account-error" role="alert">${esc(error.message)}</p><button class="btn" data-account-action="logout">Thử lại</button>`); }
    finally { pending = false; }
  }
  document.addEventListener("click", event => {
    if (!event.target.closest("details.parent-account,.teacher-account,#userDropdown,#admin-avatar")) closeMenus();
    const control = event.target.closest("[data-account-action]");
    if (!control || control.disabled || pending) return;
    ({view,edit,password,logout})[control.dataset.accountAction]?.();
  });
  document.addEventListener("keydown", event => { if (event.key === "Escape") closeMenus(); });
  return {view,edit,password,logout,initial,close:()=>dialog?.close(),setProfileView:handler=>{ profileView = handler; }};
})();
