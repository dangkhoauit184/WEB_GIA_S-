"""Build the live Admin and staff templates from the approved UI reference."""
from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[2]
source = (root / 'docs/demo/index.html').read_text(encoding='utf-8')


def prepare_live_template(html):
    """Use customer-facing labels when extracting the approved prototype."""
    labels = {
        'Lối tắt nghiệp vụ Giáo vụ (Chuẩn 005)': 'Lối tắt công việc',
        'Danh sách đề xuất nhân sự (DE_XUAT_NHAN_SU — Chờ Người dạy &amp; Phụ huynh xác nhận)': 'Danh sách đề xuất người dạy',
        '2. Sổ theo dõi Học bù cá nhân theo thỏa thuận (HOC_BU_HOC_VIEN — DB 004/005)': 'Lịch học bù đã xếp',
        '1. Danh sách Đơn từ cần xử lý': 'Danh sách đơn',
        'Lọc so khớp AJAX': 'Tìm người dạy',
        "Buổi dạy bù (loai='bu')": 'Buổi học bù',
        'Giáo vụ xếp (xep_boi)': 'Người xếp lịch',
        'Phụ huynh (1 HV - 1 PH)': 'Phụ huynh',
        'Ghi chú thỏa thuận với Phụ huynh (Không cần đơn xin bù, không tính phí bù)': 'Ghi chú',
        'Lặp lại hàng tuần (Tự lấy đơn giá giờ từng người)': 'Lặp lại hàng tuần',
        'Buổi học thường định kỳ': 'Buổi học thường',
        'Buổi học bù theo thỏa thuận': 'Buổi học bù',
        'Xếp buổi học / Lưu lịch học bù': 'Xếp ca học',
        'Ghi chú thỏa thuận với Phụ huynh': 'Ghi chú',
        'Ghi chú thỏa thuận': 'Ghi chú',
        'Ứng viên phù hợp (Khớp Môn + Cấp lớp + Khu vực + Lịch rảnh)': 'Người dạy phù hợp',
        'Thời khóa biểu, Xếp lịch &amp; Lưu lịch học bù': 'Thời khóa biểu',
        'Mở Thời khóa biểu &amp; Xếp lịch / Lưu lịch bù': 'Thời khóa biểu &amp; Xếp ca học',
        'Duyệt Đơn nghỉ cá nhân, Đổi lịch &amp; Lưu lịch Học bù': 'Duyệt đơn nghỉ &amp; Đổi lịch',
        '(Giáo vụ duyệt)': '',
        '(Admin duyệt)': '',
        'Loại đơn &amp; Thẩm quyền': 'Loại đơn',
        'Danh sách Hóa đơn Khóa học (Nháp → Phát hành → Đã thu)': 'Danh sách hóa đơn',
        'Chuyển khoản / Đối soát Admin': 'Chuyển khoản',
    }
    for original, label in labels.items():
        html = html.replace(original, label)
    html = re.sub(r'\s*\((?:BR\d+|00[45]|[A-Z]+(?:_[A-Z]+)+)\)', '', html)
    html = re.sub(r'\s*<button[^>]*onclick="openMakeupQuickModal\(\)"[^>]*>[\s\S]*?</button>', '', html)
    return html


def configure_admissions_shell(html):
    """Build the Admin and eight-screen staff portals from one PHP shell."""
    html = "<?php $isAdmissions = ($portal ?? 'admin') === 'admissions'; $portalTitle = $isAdmissions ? 'Giáo vụ / Điều phối' : 'Quản trị viên'; ?>\n" + html
    html = html.replace('EduCenter ERP · Quản trị viên', 'EduCenter ERP · <?= $portalTitle ?>')
    html = html.replace('Đăng nhập cổng Quản trị viên', 'Đăng nhập cổng <?= $portalTitle ?>')
    html = html.replace('<body class="administration">', "<body class=\"administration\" data-portal=\"<?= $isAdmissions ? 'admissions' : 'admin' ?>\">")
    common = '<script src="/admin-assets/academic-affairs.js" defer></script><link rel="stylesheet" href="/admin-assets/academic-affairs.css">'
    html = html.replace('<script src="/admin-assets/administration.js" defer></script>', '<script src="/admin-assets/administration.js" defer></script>' + common)
    html = html.replace('<script src="/admin-assets/views.js"', '<?php if (!$isAdmissions): ?><script src="/admin-assets/views.js"', 1)
    extra = ''.join('<script src="/admin-assets/' + name + '" defer></script>' for name in ['schedule.js', 'admissions.js', 'students.js', 'parents.js', 'classes.js'])
    extra += '<link rel="stylesheet" href="/admin-assets/admissions.css"><?php if ($isAdmissions): ?><script src="/admin-assets/leave-requests.js" defer></script><script src="/admin-assets/tuition.js" defer></script><?php endif; ?>'
    html = html.replace('<script src="/admin-assets/schedule.js" defer></script>', '<?php endif; ?>' + extra)
    staff_icons = dict(re.findall(r"'(staff-[^']+)':\s*'(<svg[^\n]+?</svg>)'", source))
    staff_menu = [('staff-dash','Tổng quan công việc'),('staff-intake','Tuyển sinh & Ghép lớp'),('staff-schedule','Thời khóa biểu'),('staff-classes','Danh sách Lớp & Sĩ số'),('staff-students','Quản lý Học viên & ĐK Khóa'),('staff-parents','Quản lý Hồ sơ Phụ huynh'),('staff-approve','Duyệt nghỉ cá nhân & Học bù'),('staff-billing','Hóa đơn trọn khóa & Thu phí')]
    nav = ''.join('<button type="button" data-screen="' + key + '" onclick="showS(\'' + key + '\')"><span class="nav-icon">' + staff_icons.get(key, '') + '</span><span>' + label + '</span></button>' for key, label in staff_menu)
    admin_intake = ''.join('<button type="button" data-screen="' + key + '" onclick="showS(\'' + key + '\')"><span class="nav-icon">' + staff_icons.get('staff-intake', '') + '</span><span>' + label + '</span></button>' for key, label in [('staff-registrations','Đăng ký khóa học'),('staff-intake','Tuyển sinh & Ghép người dạy')])
    html = re.sub(r'(<nav id="admin-nav">)([\s\S]*?)(</nav>)', lambda match: match[1] + '<?php if (!$isAdmissions): ?>' + match[2] + admin_intake + '<a href="/staff" class="staff-portal-link">Giáo vụ / Điều phối</a><?php else: ?>' + nav + '<?php endif; ?>' + match[3], html, count=1)
    html = html.replace('<button class="ud-item" onclick="openAccountManagement();toggleUserDropdown()">Quản lý tài khoản</button>', '<?php if (!$isAdmissions): ?><button class="ud-item" onclick="openAccountManagement();toggleUserDropdown()">Quản lý tài khoản</button><?php endif; ?>')
    html = html.replace('<div class="modal-ov" id="modalStaffForm"', '<?php if (!$isAdmissions): ?><div class="modal-ov" id="modalStaffForm"', 1)
    html = html.replace('<div class="modal-ov" id="modalSessionDetail"', '<?php endif; ?><div class="modal-ov" id="modalSessionDetail"', 1)
    return html


sections = re.findall(r'(<section class="screen" id="(admin-[^"]+)">[\s\S]*?</section>)', source)
views = {key: html.replace('class="screen"', 'class="screen active"', 1) for html, key in sections}
assert len(views) == 10, 'Expected ten approved Admin screens'
emoji_pattern = re.search(r'const EMOJI_STRIP_REGEX = /(.*)/gu;', source)[1]
views = {key: prepare_live_template(re.sub(emoji_pattern, '', html)) for key, html in views.items()}
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
(root / 'frontend/src/modules/administration/administration.views.js').write_text('/* UI reference: docs/demo/index.html. Rebuild with build-admin-assets.py. */\nwindow.AdminViews = ' + json.dumps(views, ensure_ascii=False, indent=2) + ';\n', encoding='utf-8')
css = re.search(r'<style>([\s\S]*?)</style>', source)[1]
(root / 'frontend/src/shared/styles/common.css').write_text('/* Approved styles from docs/demo/index.html. */\n' + css, encoding='utf-8')
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
modals = prepare_live_template(modals)
tail = tail.replace('<dialog id="admin-dialog"', modals + '<dialog id="admin-dialog"')
html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>EduCenter ERP · Quản trị viên</title><link rel="stylesheet" href="/admin-assets/base.css"><link rel="stylesheet" href="/admin-assets/administration.css">' + scripts + '</head><body class="administration">' + login + shell + tail
fonts = '\n'.join(re.findall(r'<link[^>]*(?:fonts.googleapis.com|fonts.gstatic.com)[^>]*>', source))
html = html.replace('<head>', '<head>' + fonts, 1)
html = configure_admissions_shell(html)
(root / 'backend/app/Modules/Administration/Features/Permissions/Views/administration.php').write_text(html, encoding='utf-8')
intake = re.search(r'<section class="screen" id="staff-intake">[\s\S]*?</section>', source)[0]
intake = intake.replace('class="screen"', 'class="screen active"', 1)
intake = re.sub(r'(<select id="matchReqSel"[^>]*>)[\s\S]*?(</select>)', r'\1<option value="">Chọn yêu cầu học</option>\2', intake)
intake = intake.replace('<label>Khu vực</label><select><option>Thủ Đức</option><option>Quận 1</option><option>Quận 3</option></select>', '<label>Khu vực</label><input id="matchRegion" readonly>')
intake = re.sub(r'<select id="budgetSel"[^>]*>[\s\S]*?</select>', '<input id="budgetSel" readonly>', intake)
intake = intake.replace('<div class="card">\n      <div class="row-b"', '<div class="card">\n      <div id="admissionsRequestFilters"></div><div class="row-b"', 1)
for body, pager in [('reqRows', 'admissionsRequestPager'), ('proposalBody', 'admissionsProposalPager'), ('createClassBody', 'admissionsHandoffPager')]:
    intake = intake.replace('<tbody id="' + body + '"></tbody>\n      </table></div>', '<tbody id="' + body + '"></tbody>\n      </table></div><div id="' + pager + '"></div>')
intake = prepare_live_template(intake)
intake = re.sub(r'<p class="sub">[\s\S]*?</p>', '', intake)
intake = re.sub(r'\s*<div class="hint">[\s\S]*?</div>', '', intake)
(root / 'frontend/src/modules/academic/features/admissions/admissions.html').write_text(intake + '\n', encoding='utf-8')
staff_views = []
for key in ['staff-dash','staff-schedule','staff-classes','staff-students','staff-parents','staff-approve','staff-billing']:
    section = re.search(r'<section class="screen" id="' + key + r'">[\s\S]*?</section>', source)[0].replace('class="screen"','class="screen active"',1)
    section = re.sub(emoji_pattern, '', section).replace('\ufe0f', '')
    if key == 'staff-dash':
        section = re.sub(r'(<div class="n"[^>]*>)[\s\S]*?(</div>)', r'\1—\2', section)
        section = section.replace('<div class="st r"><div class="n">','<div class="st r"><div class="n" id="stInvoiceCount">',1)
        start = section.index('      <div class="item">')
        end = section.index('\n    </div>', start)
        section = section[:start] + '      <div id="staffTaskList"></div>' + section[end:]
    if key == 'staff-billing':
        section = section.replace('value="800000"','value=""').replace('step="50000"','step="1" min="1"')
    if key == 'staff-students':
        section = section.replace('<button class="btn sm" onclick="showS(\'staff-parents\')">', '<button class="btn sm" onclick="showS(\'staff-registrations\')">Tra cứu đăng ký khóa</button><button class="btn sm" onclick="showS(\'staff-parents\')">')
    section = prepare_live_template(section)
    section = re.sub(r'<p class="sub">[\s\S]*?</p>', '', section)
    section = re.sub(r'\s*<div class="warn-box"[^>]*>[\s\S]*?</div>', '', section)
    section = re.sub(r'\s*<span class="tag ok">Không cần đơn xin bù[\s\S]*?</span>', '', section)
    section = re.sub(r'(?m)^[ \t]+$', '', section)
    staff_views.append('<template id="view-' + key + '">' + section + '</template>')
(root / 'frontend/src/modules/academic/features/academic-affairs/academic-affairs.html').write_text('\n'.join(staff_views) + '\n', encoding='utf-8')
print('Built ten Admin views, eight staff screens and shared styles.')
