# Thiết lập và phát triển Database

## Các file cần dùng

| Vị trí | Vai trò | Khi nào chạy |
| --- | --- | --- |
| `schema.sql` | Toàn bộ bảng, PK/FK/index/CHECK | Chỉ database mới rỗng |
| `install-order.json` | Danh sách module theo thứ tự phụ thuộc | Script tự đọc |
| `routines/functions.sql` | 2 function | Trước helper/procedure/trigger |
| `routines/helpers.sql` | 7 procedure kiểm tra dùng chung | Trước procedure nghiệp vụ/trigger |
| `routines/procedures.sql` | 3 procedure nghiệp vụ có transaction | Sau functions/helpers |
| `triggers/` | 23 trigger theo 5 nhóm nghiệp vụ | Sau routines |
| `seeders/001_reference.sql` | Danh mục, 5 nhóm và ma trận quyền gốc | Init; không ghi đè quyền database cũ |
| `seeders/002_demo.sql` | Demo khóa tài khoản | Tùy chọn trên DB thử nghiệm |
| `migrations/001_feature_based.sql` | Thay đổi cấu trúc so với 35 bảng gốc | Chỉ một lần khi nâng cấp SQL gốc |
| `migrations/002_routine_review.sql` | Ghi tiến trình cập nhật stored objects | Script điều phối, không chạy riêng |
| `checks.sql` | Báo cáo VI_PHAM/CAN_XAC_NHAN | Sau triển khai hoặc khi rà dữ liệu |
| `reference/schema-original.sql` | SQL gốc nguyên byte, có DROP DATABASE | Chỉ đối chiếu, không import lên dữ liệu thật |

## Kết nối

Cần MySQL 8.0.16+ hoặc 8.4 LTS, MySQL CLI, PowerShell 7+. Tài khoản triển khai phải có quyền DDL/routine/trigger và DML trên database được chọn. DBA có thể tạo trước database rỗng nếu không cấp CREATE DATABASE cho tài khoản này. Khi bật binary logging, quyền tạo function tùy cấu hình DBA; không tự thay cấu hình toàn server. Tài khoản triển khai không dùng làm tài khoản API.

Từ thư mục repo:

```powershell
Copy-Item database/.env.example database/.env
```

Điền thông tin kết nối, `MYSQL_BIN=mysql` nếu có PATH, hoặc đường dẫn tuyệt đối đến mysql.exe. Dùng `KEY=value` nguyên văn, không dấu nháy, không nội suy hay comment cuối dòng. Có thể dùng `-EnvFile <đường-dẫn>` để đặt config ngoài repo. Script dùng option file tạm, không đặt password lên command line và xóa file trong finally.

`database.config` là mẫu option file cho mysql client, không phải code kết nối backend; CLI không tự đọc `.env`. Dùng `mysql --defaults-extra-file=<config-riêng> -p` nếu chạy thủ công. Không commit password thật.

## Database mới

```powershell
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

Init kiểm tra rỗng, nạp schema, ghi hai migration applying, nạp modules theo manifest, seed danh mục rồi đánh dấu applied. Chưa tạo người dùng hay Admin thật, chưa seed demo. Thông báo CAN_XAC_NHAN không tự biến thành giá trị mặc định.

Chạy trực tiếp trong mysql client từ thư mục gốc repo, sau khi đã chọn database rỗng:

```sql
SET NAMES utf8mb4;
SET SESSION time_zone='+07:00';
SET SESSION transaction_isolation='READ-COMMITTED';
SOURCE database/schema.sql;
INSERT INTO schema_migrations(version,status) VALUES('001_feature_based','applying');
SOURCE database/migrations/002_routine_review.sql;
SOURCE database/routines/functions.sql;
SOURCE database/routines/helpers.sql;
SOURCE database/routines/procedures.sql;
SOURCE database/triggers/timekeeping.sql;
SOURCE database/triggers/classes.sql;
SOURCE database/triggers/scheduling.sql;
SOURCE database/triggers/academic.sql;
SOURCE database/triggers/tuition.sql;
SOURCE database/seeders/001_reference.sql;
UPDATE schema_migrations SET status='applied',applied_at=NOW()
WHERE version IN ('001_feature_based','002_routine_review');
SOURCE database/checks.sql;
```

SOURCE là lệnh mysql CLI. GUI cần chạy từng file cùng thứ tự. Dừng ngay khi một file lỗi; không đánh dấu applied và không dùng `--force`.

## Nâng cấp dữ liệu đang có

1. Backup và thử khả năng khôi phục, dừng ghi ứng dụng. Thử trên bản sao trước. Không import lại schema/seed.
2. Chạy `-Action preflight` rồi `-Action migrate`, cuối cùng `-Action check`.
3. Nếu database đúng 35 bảng gốc, script chạy 001 rồi 002. Nếu đã có 001 applied, chỉ chạy 002. Nếu đã 002, migrate không làm gì. Gặp applying, phiên bản lạ hoặc lỗi dữ liệu thì dừng.
4. DDL MySQL tự COMMIT, không rollback toàn bộ migration. Lỗi giữa chừng phải đối chiếu các lệnh đã chạy và phục hồi backup hoặc lập migration tiếp theo; không xóa cờ applying để chạy tiếp mù quáng.
5. 001 thêm cột nullable, CHECK/FK/index, sao cơ sở hiện tại cho buổi chưa khóa. Cần xác minh cơ sở lịch sử trước. Snapshot cách tính lương cũ giữ NULL đến khi đối chiếu hợp đồng. 002 không thêm/xóa bảng/cột hoặc đổi dữ liệu nghiệp vụ.

## Kiểm thử

```powershell
# DB_NAME là database dùng một lần có hậu tố _test, đã init:
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action test
pwsh -File database/scripts/run.ps1 -Action check
```

31 case gồm 11 toàn vẹn, 8 regression, 12 workflow. Workflow có COMMIT riêng và thay đổi demo, không chạy trên dữ liệu thật. `test` yêu cầu database _test có đúng 8 người dùng demo; chưa coi đây là xác thực dữ liệu production.

## Gọi procedure từ backend sau này

```sql
CALL sp_sinh_buoi_hoc(1,'2026-11-01','2026-11-30');
CALL sp_chot_cong(3,11,2026,1);
CALL sp_mo_khoa_cong(1,1,'Đối chiếu lại giờ công theo khiếu nại');
```

Các ID chỉ là ví dụ, không gọi lên production mà chưa kiểm tra. Ba procedure nghiệp vụ có transaction riêng, phải CALL độc lập, không đặt trong transaction đang mở của caller. Các helper/function là nội bộ, không tự cấp quyền EXECUTE cho client ngoài.

Kết nối ghi dùng READ COMMITTED và transaction; retry toàn giao dịch khi deadlock. Backend phải xác thực người dùng, kiểm tra quyền chức năng, phạm vi cơ sở/con của phụ huynh. Tham số user_id không thay thế xác thực. Không cấp UPDATE trực tiếp bảng công hoặc EXECUTE mở khóa cho kết nối người dùng thường. Chưa tự triển khai GPS/bán kính, phí nghỉ, tạo hóa đơn, thưởng/phạt khi tài liệu chưa rõ.

Nguồn kỹ thuật: [MySQL stored programs](https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html), [MySQL triggers](https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html).
