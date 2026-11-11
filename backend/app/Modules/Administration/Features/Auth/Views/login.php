<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EduCenter · Đăng nhập</title>
    <link rel="stylesheet" href="/assets/portal.css">
    <script src="/assets/portal.js" defer></script>
</head>
<body>
<main>
    <header><strong>EduCenter</strong><span>Quản lý trung tâm gia sư</span></header>
    <section id="login-panel" class="card">
        <h1>Đăng nhập</h1>
        <p>Sử dụng tài khoản được quản trị viên cấp.</p>
        <form id="login-form">
            <label>Tên đăng nhập<input name="username" autocomplete="username" required maxlength="100"></label>
            <label>Mật khẩu<input name="password" type="password" autocomplete="current-password" required></label>
            <button type="submit">Đăng nhập</button>
        </form>
    </section>
    <section id="workspace" class="card" hidden>
        <div class="heading"><div><h1 id="welcome"></h1><p id="role"></p></div><button id="logout" class="secondary">Đăng xuất</button></div>
        <nav aria-label="Dữ liệu"><button data-resource="students">Học viên</button><button data-resource="classes">Lớp học</button><button data-resource="sessions">Lịch học</button></nav>
        <form id="search-form"><label>Tìm học viên hoặc lớp học<input id="search" placeholder="Nhập mã hoặc tên"></label><button>Tìm kiếm</button></form>
        <p id="result-title" aria-live="polite">Chọn dữ liệu cần xem.</p>
        <div class="table-wrap"><table><thead id="columns"></thead><tbody id="rows"></tbody></table></div>
        <details><summary>Đổi mật khẩu</summary><form id="password-form">
            <label>Mật khẩu hiện tại<input name="currentPassword" type="password" autocomplete="current-password" required></label>
            <label>Mật khẩu mới<input name="newPassword" type="password" autocomplete="new-password" minlength="6" maxlength="72" required></label>
            <button>Cập nhật mật khẩu</button>
        </form></details>
    </section>
    <p><a href="/admin">Mở cổng quản trị</a></p>
    <p id="notice" role="status" aria-live="polite"></p>
</main>
</body>
</html>
