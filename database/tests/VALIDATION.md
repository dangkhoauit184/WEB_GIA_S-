# Kết quả kiểm tra Database

Kiểm tra ngày 22/10/2026 bằng **MySQL Community Server 8.4.13**, Windows, InnoDB, utf8mb4, READ COMMITTED, giờ phiên +07:00. Server portable chạy tạm trên localhost với cổng riêng; không kết nối database đang sử dụng của người dùng, không cài dịch vụ Windows. Binary logging tắt riêng trên server thử nghiệm.

## Kết quả đã chạy

| Kiểm tra | Kết quả |
| --- | --- |
| Khởi tạo bằng `scripts/run.ps1 -Action init` | Thành công; 36 bảng, 21 trigger, 11 routine |
| Nạp danh mục gốc | Thành công; 5 nhóm quyền, 8 phân hệ, ma trận gốc |
| Nạp demo bằng `-Action seed-demo` | Thành công, tài khoản demo bị khóa |
| `tests/integrity.sql` | 11/11 ca đạt, transaction dữ liệu test được rollback |
| `tests/workflows.sql` | 12/12 ca đạt trên database demo dùng một lần |
| `migrations/preflight.sql` trên bản sao SQL gốc | Không phát hiện vi phạm, không sửa dữ liệu |
| Migration và cài routine/trigger trên bản sao SQL gốc | Thành công; 36 bảng, 21 trigger, 11 routine, trạng thái applied |
| Kiểm tra sau nâng cấp | Số dư hóa đơn khớp chứng từ; liên kết công đúng người/kỳ |
| Dữ liệu mẫu sau nâng cấp | Giữ 8 người dùng, 14 buổi học, 15 phân công; hóa đơn số 1 vẫn đã thu 180.000đ |
| Hash bảo toàn và bộ khung | `verify_structure.py` đạt: 12 hash, 18 feature, 198 file rỗng |
| Chạy init/migrate lặp lại | Script từ chối đúng theo điều kiện an toàn |
| So cột database mới và database nâng cấp | 0 khác biệt tên/kiểu/null/default/generated expression |

11 ca toàn vẹn kiểm tra FK, giá âm, sĩ số khi INSERT/UPDATE, thu vượt nợ, hoàn vượt số dư, lũy kế sau hoàn, đổi danh tính phiếu, hủy phiếu gây âm dư, ca bù sai gốc và snapshot phân công mới.

12 ca quy trình kiểm tra sinh lịch lặp lại không trùng, trùng phòng, trùng người dạy/trợ giảng, đổi giờ gây xung đột, phòng cùng tên ở hai cơ sở, giảm sĩ số lớp, chặn chốt khi có đơn chờ, tính lương theo snapshot, khóa ca đã chốt, Nhân viên không mở được khóa, Admin mở và chốt lại, nhật ký kiểm toán.

## Những kết quả cần người triển khai xử lý

- Demo mới có 1 lớp phí tháng chưa cấu hình giá: `hoc_phi_thang=NULL`.
- Nâng cấp giữ 15 snapshot cách tính lương lịch sử ở NULL; không tự lấy cách tính hiện tại để áp cho quá khứ. Cần xác minh hợp đồng trước khi chốt lại các ca đó.
- Không thử tải đồng thời, khôi phục backup, quyền tài khoản production, TLS, binary logging hoặc MySQL 8.0. Các việc này cần kiểm tra trên hạ tầng triển khai.
- Báo cáo trên xác nhận đường khởi tạo và nâng cấp từ SQL mẫu trong repo; không chứng minh mọi database đã chỉnh riêng tương thích.

Sau kiểm tra đã dừng các tiến trình MySQL thử nghiệm và dọn runtime/database tạm. Không giữ server chạy nền.

## Chạy lại

Kiểm tra tĩnh không cần MySQL:

```powershell
python database/tests/verify_structure.py
```

Để kiểm tra SQL, tạo database riêng có hậu tố `_test`, init và seed demo theo README. Trong mysql client đã chọn database thử nghiệm:

```sql
SOURCE D:/WEB/database/tests/integrity.sql;
SOURCE D:/WEB/database/tests/workflows.sql;
```

`workflows.sql` thực sự chốt/mở công, sửa dữ liệu demo và gọi procedure có transaction riêng. Chỉ chạy trên database dùng một lần. Các suite chỉ bắt lỗi dự kiến bằng handler, lỗi bất ngờ sẽ làm kiểm thử thất bại; không dùng mysql `--force`.

Để kiểm tra nâng cấp, khôi phục bản sao backup gốc vào database riêng, chạy preflight/migrate/check theo README, đối chiếu số lượng dòng và số liệu tài chính với backup. Không import SQL gốc chứa DROP DATABASE vào server có dữ liệu thật.
