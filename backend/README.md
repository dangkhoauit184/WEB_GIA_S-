# Backend PHP — EduCenter ERP

Backend dùng **PHP 8.4, CodeIgniter 4.7, MySQLi, JWT và bcrypt**. Điểm vào HTTP: `public/index.php`; CLI: `spark`. Chỉ public thư mục `public/` trên web server.

## Khởi động

Từ thư mục gốc repo, với Docker Desktop và database đã chuẩn bị:

```powershell
powershell -ExecutionPolicy Bypass -File backend/scripts/start.ps1
# Chỉ khi cần tạo tài khoản mới
powershell -ExecutionPolicy Bypass -File backend/scripts/bootstrap-admin.ps1
```

Ứng dụng: `http://localhost:5000/`. API: `http://localhost:5000/api/health`.
File `config/server.env` cũ vẫn dùng được, giữ nguyên `DB_PASSWORD`, đặt `JWT_SECRET` riêng tối thiểu 32 ký tự. Trong Docker, Compose tự đổi DB host/port sang `educenter-mysql:3306`; khi chạy PHP trên Windows thì dùng `127.0.0.1:3307`.

Đã có admin: dùng thông tin đăng nhập cũ. Bootstrap mặc định từ chối ghi đè tài khoản; thêm `-Reset` chỉ khi muốn đổi mật khẩu/kích hoạt lại. Token Node cũ không dùng trên PHP.

## Module → feature và MVC

Ví dụ `app/Modules/People/Features/Students/`:

- `StudentsController.php`: nhận request, trả JSON.
- `StudentsService.php`: điều phối nghiệp vụ.
- `StudentsRepository.php`: truy vấn có bind tham số, transaction.
- `StudentsModel.php`: model/ánh xạ trường camelCase.
- `StudentsValidator.php`: kiểm tra dữ liệu.
- `StudentsFilter.php`: quyền theo nhóm và hành động.
- `Routes.php`: routes tường minh, tắt auto-routing.

View đăng nhập tại `app/Modules/Administration/Features/Auth/Views/login.php`, được controller `Portal` render. Ajax đặt trong `public/assets/`. Gia sư dùng chung logic hồ sơ với giáo viên; không nhân đôi truy vấn. Feature chưa làm có chú thích TODO và chỉ có route `/status` với `planned`.

## API đang có

Mọi API nghiệp vụ cần `Authorization: Bearer <token>`, ngoại trừ login, health, manifest và status.

| Nhóm | Đường dẫn và thao tác |
| --- | --- |
| Auth | `/api/auth` hoặc `/api/administration/auth`: POST login/logout, GET me, PUT/POST change-password |
| Phụ huynh | `/api/people/parents`: GET/POST, GET/PUT `/:id` |
| Học viên | `/api/people/students`: GET/POST, GET/PUT/DELETE `/:id`; DELETE là soft delete |
| Người dạy | `/api/people/teachers`, `/api/people/tutors`: GET/POST, GET/PUT `/:id`, GET `/match` |
| Lớp học | `/api/academic/classes`: GET/POST, GET/PUT `/:id`, GET/POST `/:id/enrollments`, PUT `/:id/enrollments/:enrollmentId` |
| Xếp lịch | `/api/academic/scheduling`: GET/POST weekly, GET sessions, POST generate-sessions, POST sessions/:id/assign, PUT sessions/:id |
| Danh mục | `/api/administration/catalog`: dữ liệu chọn dùng chung, quản lý môn/khu vực/cơ sở, cập nhật tên cấp lớp và cấu hình |
| Phân quyền | `/api/administration/permissions`: nhóm chuẩn, chức năng, ma trận có kiểm tra phiên bản, tài khoản và gán nhóm |

Response thành công giữ `{success:true,data:...}` và tên trường camelCase của API trước. Lỗi có `success:false`, `code`, `message`. SIGNAL 45000 trả 400 `BUSINESS_RULE_VIOLATION`, trùng dữ liệu trả 409. Lỗi máy chủ không trả chi tiết truy vấn/mật khẩu cho client.

## Quyền và phiên đăng nhập

- 6 vai trò ứng dụng: ADMIN, GIAO_VU, GIAO_VIEN, GIA_SU, TRO_GIANG, PHU_HUYNH; suy ra từ 5 nhóm DB và phân loại chuyên môn.
- Admin toàn quyền; giáo vụ theo `role_group_function`. Quyền quản lý phụ huynh dùng `TiepNhanYeuCau`, học viên/lớp dùng `QuanLyLopHoc`, người dạy dùng `QuanLyNhanSu`, lịch dùng `VanHanhBuoiHoc`.
- Seed hiện tại chưa cấp `QuanLyNhanSu` cho giáo vụ, nên thao tác quản lý người dạy mặc định dành cho admin. Admin có thể cấp/thu hồi quyền qua API ma trận; code không tự sửa ma trận đang dùng.
- Chỉ admin được sửa danh mục, cấu hình và phân quyền. Người dùng có quyền tra cứu lớp/vận hành được đọc danh mục để điền form. Không xóa cứng môn/khu vực/cơ sở: DELETE chuyển isActive=false; cấp lớp 1–12 chỉ sửa tên để giữ thứ tự so khớp chuyên môn.
- Phụ huynh chỉ xem con và lớp/lịch liên quan; người dạy xem hồ sơ mình, lớp được giao và buổi có phân công. Kiểm tra ở cả list và detail.
- Mỗi request kiểm tra lại tài khoản/nhóm/quyền trong DB. Logout lưu hash token bị thu hồi trong `audit_log`; đổi mật khẩu vô hiệu hóa các token cũ. Không xóa bản audit đăng xuất trước khi token hết hạn.
- Chuyển PHP không đổi schema, không hash lại tài khoản sẵn có. `password_verify` hỗ trợ bcrypt đã lưu từ Node/PHP.

## Kiểm thử

```powershell
docker compose run --rm --no-deps backend composer test
pwsh -File backend/scripts/test.ps1
```

Core test không ghi database. Script thứ hai tạo container MySQL dùng một lần, chạy API tích hợp và 6 bộ SQL test rồi xóa container/volume tạm. Integration từ chối chạy nếu thiếu `TEST_WITH_DB=1` hoặc tên DB không kết thúc `_test`. Chi tiết chạy trực tiếp và triển khai: [server-setup](../docs/server-setup.md). Phạm vi đồ án còn lại: [IS207](../docs/is207-migration.md).

Suite quản trị chạy bằng `composer test:admin` trên môi trường DB kiểm thử tương tự `test:db`; đã được gọi trong script test.ps1 và CI. Hợp đồng request/response, quyền và giới hạn: [administration-api.md](../docs/administration-api.md).

## DB/server 005

`pwsh -File backend/scripts/doctor.ps1` kiểm tra môi trường đang chạy; SMTP local ở http://localhost:8025/. Bản release HTTPS: compose.production.yaml. [Bàn giao005](../docs/db-server-readiness.md) nêu rõ migration, sao lưu/restore, các thay đổi quyền cần cập nhật vào API cũ và thứ tự làm BE/FE. Lần005 chỉ thêm hạ tầng/config và sửa validator cấu hình để tương thích giá trị không giới hạn; chưa viết tính năng thanh toán/check-in.
