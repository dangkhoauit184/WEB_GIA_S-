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
| config / shared | Mẫu cấu hình / manifest6 module21 feature |

Giữ4 README làm chỉ dẫn ngắn và 1 mẫu PR. Không commit secret, env riêng, vendor, writable hoặc backup. Không viết Node backend song song.
