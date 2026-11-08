# EduCenter ERP — Quản lý trung tâm gia sư

Đồ án dùng **PHP 8.4 + CodeIgniter 4 (MVC, Ajax) + MySQL 8.4**. Backend đã chuyển từ Node.js/Express sang PHP để phù hợp đề cương IS207. Composer lock cố định phiên bản thư viện; chạy bằng Docker, không cần cài PHP trực tiếp trên Windows.

## Trạng thái

- Database: giữ schema 36 bảng, 25 trigger, 12 procedure/function, dữ liệu và quy trình cài đặt hiện có.
- Backend: đăng nhập/JWT, phân quyền, phụ huynh/học viên, giáo viên/gia sư, gợi ý ghép BR02, lớp/ghi danh, lịch tuần/sinh buổi học BR03 đã có API PHP.
- Có trang MVC đăng nhập, xem/tìm dữ liệu và đổi mật khẩu qua Ajax tại `http://localhost:5000/`. Đây là giao diện khởi đầu; chưa triển khai toàn bộ màn hình Figma.
- `frontend/` tiếp tục giữ khung HTML/CSS/JS. Các feature backend khác cũng giữ khung PHP; `/status` trả `planned` khi chưa có nghiệp vụ.
- Danh sách phần đã chuyển và phần còn thiếu của đồ án: [docs/is207-migration.md](docs/is207-migration.md).

## Chạy trên máy đã có database

Mở Docker Desktop, giữ file riêng `config/server.env` đã điền mật khẩu, rồi chạy từ thư mục gốc repo:

```powershell
cd D:\WEB
powershell -ExecutionPolicy Bypass -File backend/scripts/start.ps1
```

Script bật lại container MySQL `educenter-mysql`, nối mạng nội bộ, cài Composer và chạy Apache/PHP. Nó dùng lại volume MySQL hiện có. Mở [ứng dụng](http://localhost:5000/) hoặc [health check](http://localhost:5000/api/health).

Nếu chưa có tài khoản ứng dụng:

```powershell
powershell -ExecutionPolicy Bypass -File backend/scripts/bootstrap-admin.ps1
```

Nếu `admin` đã tồn tại nhưng đang bị khóa bởi seed demo, thêm `-Reset` để chủ động đặt mật khẩu mới. Mật khẩu ứng dụng độc lập với mật khẩu MySQL. Token cũ của Node.js cần đăng nhập lại sau khi chuyển PHP.

Máy mới: làm [database/README.md](database/README.md) trước, rồi [docs/server-setup.md](docs/server-setup.md). Không chạy lại schema lên database đang có dữ liệu.

## Cấu trúc

```text
backend/
  app/Config/                         cấu hình và ghép routes
  app/Modules/<Module>/Features/<Feature>/
    <Feature>Controller.php           HTTP / MVC controller
    <Feature>Service.php              nghiệp vụ
    <Feature>Repository.php           truy vấn MySQL
    <Feature>Model.php                model / ánh xạ dữ liệu
    <Feature>Validator.php            kiểm tra đầu vào
    <Feature>Filter.php               quyền truy cập
    Routes.php                        routes tường minh
    Views/                            PHP view nếu feature có màn hình
  app/Shared/                         xác thực, scope, DB, xử lý lỗi
  public/                             document root Apache
  scripts/                            start, bootstrap và kiểm thử Docker
  tests/                              core và HTTP/MySQL
  composer.json, composer.lock, spark
frontend/src/modules/<module>/features/<feature>/
database/                             SQL, seed, migration, tests
shared/modules.json                   6 module / 21 feature dùng chung
config/server.env.example             mẫu cấu hình, không chứa mật khẩu
compose.yaml                          PHP kết nối container DB hiện có
docs/                                 tài liệu chi tiết và tham khảo
```

## Kiểm tra

```powershell
python database/tests/verify_structure.py
docker compose run --rm --no-deps backend composer test
# PowerShell 7: tạo MySQL kiểm thử riêng, chạy PHP + SQL rồi dọn container tạm
pwsh -File backend/scripts/test.ps1
```

Giữ **4 README** (gốc, backend, frontend, database) và **1 mẫu pull request**. Xem [backend/README.md](backend/README.md), [cấu trúc module](docs/project-structure.md), [nghiệp vụ](docs/architecture.md), [tài liệu nghiệp vụ gốc](docs/quy_trinh_nghiep_vu_v2.docx), [kiểm chứng database](docs/database-validation.md), [demo tham khảo](docs/frontend-demo.md).
