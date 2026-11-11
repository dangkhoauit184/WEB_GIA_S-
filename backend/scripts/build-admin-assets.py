"""Build the live Admin templates and styles from the approved UI reference."""
from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[2]
source = (root / 'reference/Educenter.html').read_text(encoding='utf-8')
sections = re.findall(r'(<section class="screen" id="(admin-[^"]+)">[\s\S]*?</section>)', source)
views = {key: html.replace('class="screen"', 'class="screen active"', 1) for html, key in sections}
assert len(views) == 10, 'Expected ten approved Admin screens'
emoji_pattern = re.search(r'const EMOJI_STRIP_REGEX = /(.*)/gu;', source)[1]
views = {key: re.sub(emoji_pattern, '', html) for key, html in views.items()}
dashboard = re.sub(r'<div class="n">[\s\S]*?</div>', '<div class="n">—</div>', views['admin-dash'])
dashboard = dashboard.replace('<div class="bars">', '<div class="bars" id="adminRevenueBars">')
dashboard = re.sub(r'<div class="bars" id="adminRevenueBars">[\s\S]*?</div>', '<div class="bars" id="adminRevenueBars"></div>', dashboard)
start = dashboard.index('      <div class="card">\n        <h2>Việc cần Admin')
end = dashboard.index('\n    </div>\n    <div class="card">', start)
dashboard = dashboard[:start] + '<div class="card"><h2>Việc cần Admin phê duyệt &amp; đối soát ngay</h2><div id="adminPendingWork"></div></div>' + dashboard[end:]
start = dashboard.index('    <div class="card">\n      <h2>Hoạt động kiểm toán')
views['admin-dash'] = dashboard[:start] + '<div class="card"><h2>Hoạt động kiểm toán gần đây</h2><div id="adminRecentAudit"></div></div>\n  </div>\n</section>'
views['admin-exceptions'] = re.sub(r'<tbody>\s*<tr>[\s\S]*?</tbody>', '<tbody id="adminUnlockBody"></tbody>', views['admin-exceptions'])
views['admin-payroll'] = views['admin-payroll'].replace('<div class="row">', '<div class="row"><div class="period-picker" data-period-picker="payrollMonth"></div><input id="payrollMonth" type="hidden" aria-label="Kỳ công" onchange="changePayrollMonth(this.value)">', 1)
views['admin-payroll'] = re.sub(r'<select style="width:145px">[\s\S]*?</select>', '', views['admin-payroll'])
(root / 'frontend/src/modules/administration/administration.views.js').write_text('/* UI reference: reference/Educenter.html. Rebuild with build-admin-assets.py. */\nwindow.AdminViews = ' + json.dumps(views, ensure_ascii=False, indent=2) + ';\n', encoding='utf-8')
css = re.search(r'<style>([\s\S]*?)</style>', source)[1]
(root / 'frontend/src/shared/styles/common.css').write_text('/* Approved styles from reference/Educenter.html. */\n' + css, encoding='utf-8')
shell = source[source.index('<div class="app" id="appRoot">'):source.index('<main class="main">')]
shell = shell.replace('id="appRoot"', 'id="admin-workspace" hidden').replace('id="sidebar"', 'id="admin-sidebar"')
shell = re.sub(r'(<[^>]+id="nCount"[^>]*>)[^<]*', r'\1—', shell)
shell = shell.replace('id="sName"', 'id="admin-name"').replace('id="nav"', 'id="admin-nav"').replace('id="acctBtn"', 'id="admin-avatar"')
shell = shell.replace('onclick="handleLogout()"', 'id="admin-logout"', 1)
shell = shell.replace('onclick="document.getElementById(\'sidebar\').classList.toggle(\'open\')"', 'id="admin-menu" aria-label="Mở menu"')
shell = shell.replace('onclick="resetDemoData();toggleUserDropdown()"', 'onclick="openAccountManagement();toggleUserDropdown()"').replace('Đặt lại dữ liệu mẫu', 'Quản lý tài khoản')
icons = dict(re.findall(r"'(admin-[^']+)':\s*'(<svg[^\n]+?</svg>)'", source))
labels = [('admin-dash','Tổng quan & Thống kê'),('admin-courses','Khóa học & Xếp lớp'),('admin-payments','Đối soát Thanh toán Web'),('admin-payroll','Công, Đơn giá giờ & Lương'),('admin-exceptions','Duyệt Nghỉ lớp, Công & Mở khóa'),('admin-staff','Nhân sự & Hồ sơ chuyên môn'),('admin-perms','Phân quyền hệ thống'),('admin-catalog','Danh mục & Cấu hình'),('admin-audit','Kiểm toán & Giám sát'),('admin-schedule','Giám sát Lịch toàn chuỗi')]
nav = ''.join('<button type="button" data-screen="' + key + '" onclick="showS(\'' + key + '\')"><span class="nav-icon">' + icons.get(key,'') + '</span><span>' + label + '</span></button>' for key,label in labels)
shell = shell.replace('<nav id="admin-nav"></nav>', '<nav id="admin-nav">' + nav + '</nav>')
assets = ['administration.js','views.js','catalog.js','permissions.js','audit.js','dashboard.js','tuition.js','payroll.js','leave-requests.js','timekeeping.js','staff.js','schedule.js']
scripts = '\n'.join('<script src="/admin-assets/' + name + '" defer></script>' for name in assets)
login = '''<section id="admin-login" class="login-wrap"><form id="admin-login-form" class="card login-card">
<div class="brand-icon">E</div><h1>EduCenter ERP</h1><p class="sub">Đăng nhập cổng Quản trị viên</p>
<label>Tên đăng nhập<input name="username" autocomplete="username" required></label>
<label>Mật khẩu<input name="password" type="password" autocomplete="current-password" required></label>
<p id="login-error" class="form-error" role="alert"></p><button class="btn p" type="submit">Đăng nhập hệ thống</button><p><a href="/">Website Trung tâm</a></p>
</form></section>'''
tail = '''<main class="main" id="admin-content" tabindex="-1" aria-live="polite"></main></div></div>
<dialog id="admin-dialog" aria-labelledby="dialog-title"><div id="dialog-content"></div></dialog>
<div id="admin-toast" role="status" hidden></div><span id="admin-page-title" hidden></span>
<div class="api-bar"><div><span class="m" id="apiMethod">GET</span> <span id="apiUri" class="mono">/api/reporting/dashboard</span></div><span id="apiStatus">EduCenter ERP Core</span></div>
</body></html>'''
def extract_modal(identifier):
    marker = 'id="' + identifier + '"'
    pos = source.index(marker)
    start = source.rfind('<div ', 0, pos)
    depth = 0
    for tag in re.finditer(r'</?div\b[^>]*>', source[start:]):
        depth += -1 if tag[0].startswith('</') else 1
        if depth == 0:
            return source[start:start + tag.end()]
    raise ValueError(identifier)

modals = '\n'.join(extract_modal(identifier) for identifier in ['modalStaffForm','modalTeacherProfile','modalAdminEnroll','modalSessionDetail','modalSessionForm','notifPanel'])
modals = modals.replace('<input type="text" id="sfStaffSpec" value="Toán 8-9 · Thủ Đức · Rảnh T3, T5, T7 (18:00-21:00)">', '<input type="text" id="sfStaffSpec" placeholder="Giới thiệu chuyên môn; lịch rảnh được nhập trong hồ sơ">')
modals = modals.replace('<select id="sfStaffRole"', '<select onchange="onStaffRoleChanged()" id="sfStaffRole"')
modals = re.sub(r'(<select id="sfMakeupStudent">)[\s\S]*?(</select>)', r'\1<option value="">Chọn học viên</option>\2', modals)
modals = modals.replace('<select id="sfMakeupScope">', '<select id="sfMakeupScope" onchange="onSfTypeChanged()">')
modals = modals.replace('value="Đã trao đổi điện thoại với phụ huynh thống nhất học bù tối Thứ Sáu"', 'placeholder="Ghi chú thỏa thuận đã chốt với phụ huynh"')
modals = modals.replace('<label style="margin-top:0;color:var(--purple)">Phạm vi bù</label>', '<label style="margin-top:0;color:var(--purple)">Buổi gốc</label><select id="sfMakeupOrigin"></select><label style="margin-top:0;color:var(--purple)">Phạm vi bù</label>')
modals = modals.replace('<div class="modal-act">\n        <button type="button" class="btn sm" onclick="closeModalStaffForm()">', '<div class="row"><div class="form-group" style="flex:1"><label>Tên đăng nhập</label><input id="sfStaffUsername" required maxlength="100"></div><div class="form-group" style="flex:1"><label>Mật khẩu ban đầu</label><input id="sfStaffPassword" type="password" required minlength="6" maxlength="72" autocomplete="new-password"></div></div><div class="modal-act">\n        <button type="button" class="btn sm" onclick="closeModalStaffForm()">')
tail = tail.replace('<dialog id="admin-dialog"', modals + '<dialog id="admin-dialog"')
html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EduCenter ERP · Quản trị viên</title><link rel="stylesheet" href="/admin-assets/base.css"><link rel="stylesheet" href="/admin-assets/administration.css">' + scripts + '</head><body class="administration">' + login + shell + tail
fonts = '\n'.join(re.findall(r'<link[^>]*(?:fonts.googleapis.com|fonts.gstatic.com)[^>]*>', source))
html = html.replace('<head>', '<head>' + fonts, 1)
(root / 'backend/app/Modules/Administration/Features/Permissions/Views/administration.php').write_text(html, encoding='utf-8')
print('Built ten Admin views, live shell and styles.')
