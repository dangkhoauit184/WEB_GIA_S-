# Database EduCenter ERP

MySQL 8.4 / InnoDB / utf8mb4 / giờ+07:00 / READ COMMITTED. Phiên bản **005_operations_ready**,43 bảng,20 routine,57 trigger.

- [Cài DB mới cho đồng đội](../docs/huong-dan-cai-dat-va-su-dung.md#db-moi).
- [Nâng DB đang có dữ liệu](../docs/huong-dan-cai-dat-va-su-dung.md#db-cu).
- [Chia sẻ, backup và restore](../docs/huong-dan-cai-dat-va-su-dung.md#chia-se).
- [Kiểm thử](../docs/huong-dan-cai-dat-va-su-dung.md#kiem-thu).
- [SQL thêm/xóa dữ liệu demo thủ công](seeders/demo-thu-cong.sql), gồm hai phần THÊM và XÓA để chạy riêng trong DataGrip.
- [Thiết kế/quan hệ DB](../docs/thong-tin-do-an.md#database), [luồng cài và xử lý SQL](../docs/thong-tin-do-an.md#sql-lifecycle).
- [Toàn bộ cột/khóa/ràng buộc](../docs/thong-tin-do-an.md#tu-dien-du-lieu), [routine/trigger](../docs/thong-tin-do-an.md#routine-trigger).

Thực thi qua scripts/run.ps1, dùng [install-order.json](install-order.json) làm thứ tự chuẩn. Không init/schema.sql lên DB cũ, action seed-demo và suite SQL dùng DB `_test`. SQL gốc ở [reference](../reference/database/schema-original.sql) có lệnh xóa DB, không nằm trong luồng cài đặt.
