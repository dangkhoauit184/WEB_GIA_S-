# EduCenter ERP

Đồ án quản lý trung tâm gia sư bằng **PHP 8.4 / CodeIgniter 4 / MySQL 8.4**, giao diện HTML/CSS/JavaScript và Ajax.

Tài liệu chính thức chỉ gồm hai file:

1. [Hướng dẫn cài đặt và sử dụng cho đồng đội](docs/huong-dan-cai-dat-va-su-dung.md): tải công cụ, clone repo, cấu hình, DB/server, tài khoản web, chạy hằng ngày, chia sẻ/backup/restore, kiểm thử và deploy.
2. [Toàn bộ thông tin và đặc tả đồ án](docs/thong-tin-do-an.md): phạm vi, vai trò, nghiệp vụ đã chốt, quy trình, kiến trúc, DB43 bảng/20 routine/57 trigger, API, Figma, tiến độ và việc còn lại.

Schema hiện hành **005_operations_ready**. Nền DB/server và một số API đã có; website chưa hoàn thiện tất cả luồng. Máy mới bắt đầu ở tài liệu1, không import schema lên DB đã có dữ liệu.

| Thư mục | Nội dung |
| --- | --- |
| [backend](backend/README.md) | PHP MVC theo module→feature |
| [frontend](frontend/README.md) | Khung HTML/CSS/JS theo module→feature |
| [database](database/README.md) | Schema, migrations, routines, triggers, seed, kiểm tra và công cụ DB |
| docs | Đúng2 tài liệu chính thức ở trên |
| reference | Word, SQL và demo gốc, giữ hash để tham khảo lịch sử |
| config / shared | Mẫu cấu hình / manifest 6 module 22 feature |

Giữ4 README làm chỉ dẫn ngắn và 1 mẫu PR. Không commit secret, env riêng, vendor, writable hoặc backup. Không viết Node backend song song.

Hướng dẫn pull, commit, push và xử lý xung đột nằm trong [tài liệu cài đặt và sử dụng](docs/huong-dan-cai-dat-va-su-dung.md#pull-push-code).

### Cập nhật môi trường Docker local (20/11/2026)

- **Tối ưu tốc độ phản hồi API local (~2.5s → ~90ms):** `compose.yaml` đã tách riêng `backend/vendor` và `backend/writable` vào Docker named volumes Linux (`backend_vendor`, `backend_writable`) để tránh nghẽn I/O qua ổ Windows, đồng thời cấu hình PHP OPcache trong `config/php.ini` (`revalidate_freq = 5`, `enable_file_override = 1`) và tối ưu Composer autoloader trong `backend/scripts/start.ps1`. Source code (`backend/app/`, `backend/public/`, `frontend/`) vẫn giữ bind mount để sửa nóng bình thường; build production trong `Dockerfile` và `compose.production.yaml` giữ nguyên.
- **Đồng đội cần làm sau khi `git pull`:** chạy lại `pwsh -File backend/scripts/start.ps1` để build lại image và nạp `vendor` vào volume Linux. Sau này khi có cập nhật gói Composer, chạy `docker compose exec backend composer install --no-interaction --prefer-dist --optimize-autoloader` rồi `docker compose restart backend`. Chi tiết xem tại [mục 8 hướng dẫn sử dụng](docs/huong-dan-cai-dat-va-su-dung.md#hang-ngay).
