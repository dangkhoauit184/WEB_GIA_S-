# Hướng dẫn chạy database EduCenter ERP

> Bản hiện hành **005_operations_ready**: [bàn giao DB/server](../docs/db-server-readiness.md), gồm thanh toán/thông báo và check-in chỉ bấm nút, không giới hạn sớm/muộn. Lịch sử 003 → 004 ở [database-004](../docs/database-004.md); hạn mức/phí bù đã bỏ, chỉ Admin quản trị công-lương.

**Chạy các lệnh từ thư mục gốc repository**, không phải từ bên trong `database/`. Phiên bản schema hiện tại là `005_operations_ready`.

## Bắt đầu với Docker trên Windows

Máy hiện có database dùng lại container/volume cũ. Máy mới cũng chạy được lệnh sau sau khi cài Docker Desktop, PowerShell7 và mở Engine:

```powershell
pwsh -File database/scripts/start-local.ps1
```

Script tái sử dụng container cũ; chỉ khi chưa có container/volume mới tạo MySQL8.4, lưu mật khẩu root ngẫu nhiên riêng vào config/mysql.env (đã gitignore). Không in mật khẩu, không xóa dữ liệu, không tự migrate/seed. Nếu chỉ còn volume, script dừng để tránh khởi tạo nhầm.

Tiếp theo tạo config/server.env từ mẫu, điền DB_NAME, DB_USER/DB_PASSWORD ứng dụng và JWT_SECRET riêng. Cài database mới bằng:

```powershell
pwsh -File database/scripts/run.ps1 -Action init -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action check -Container educenter-mysql -EnvFile config/server.env
```

DB cũ dùng backup/verify → preflight → migrate → check theo [bàn giao005](../docs/db-server-readiness.md), không init lại. Sau đó tạo user API và chạy PHP theo [server-setup](../docs/server-setup.md). Với máy đang dùng, không sao chép đè file env riêng.

Các bước dưới đây dành cho MySQL CLI cài trực tiếp; dùng Docker thì không cần cài thêm MySQL Server/mysql.exe trên Windows.

## 1. Chuẩn bị trước khi chạy

1. Cài **MySQL Server 8.0.16+ hoặc 8.4 LTS** và **MySQL CLI**. Không dùng MariaDB thay thế. Khởi động dịch vụ MySQL trên máy hoặc server của bạn.
2. Cài **PowerShell 7+**; lệnh chạy là `pwsh`, không phải Windows PowerShell 5.1. Python 3 chỉ cần cho bước kiểm tra cấu trúc.
3. Kiểm tra công cụ:

```powershell
pwsh --version
mysql --version
mysql -h 127.0.0.1 -P 3306 -u root -p
```

Lệnh cuối mở MySQL CLI và hỏi mật khẩu quản trị. Nếu chưa có tài khoản triển khai, tạo tài khoản và cấp quyền cho đúng database trong cửa sổ MySQL này. Thay mật khẩu minh họa trước khi chạy:

```sql
CREATE USER 'educenter_migrator'@'127.0.0.1' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_RIENG';
GRANT ALL PRIVILEGES ON trung_tam_gia_su.* TO 'educenter_migrator'@'127.0.0.1';
EXIT;
```

Tài khoản này dùng triển khai schema/routine/trigger, tách biệt với tài khoản API. Nếu dùng server từ xa, DBA cần cấp tài khoản cho đúng địa chỉ máy kết nối. Khi đổi tên database, sửa cả câu `GRANT` lẫn `DB_NAME` bên dưới.

## 2. Tạo file cấu hình

Chỉ sao chép ở lần đầu; nếu đã có `.env`, sửa file hiện có:

```powershell
Copy-Item database/.env.example database/.env
```

Mở `database/.env`, điền:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=trung_tam_gia_su
DB_USER=educenter_migrator
DB_PASSWORD=MAT_KHAU_BAN_DA_DAT
DB_CHARSET=utf8mb4
DB_TIMEZONE=+07:00
MYSQL_BIN=mysql
```

Dùng `KEY=value` nguyên văn; không thêm dấu nháy, `export` hoặc comment cuối dòng. `MYSQL_BIN` có thể là đường dẫn đầy đủ, ví dụ `C:/Program Files/MySQL/MySQL Server 8.4/bin/mysql.exe`, nếu CLI chưa có trong PATH. File `.env` đã được Git bỏ qua.

## 3. Database mới: chạy file nào trước?

Chạy **script `database/scripts/run.ps1`**, không cần mở và import lần lượt từng file SQL:

```powershell
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

`init` tự tạo database nếu chưa có, nhưng chỉ cài vào database rỗng. Thứ tự thực tế:

| Bước | File / thao tác |
| --- | --- |
| 1 | schema.sql: cấu trúc005; ghi lịch sử001–005 applying |
| 2 | functions → helpers → business_rules → payments → procedures trong routines/ |
| 3 | timekeeping → classes → scheduling → academic → tuition → payroll → business_rules → payments trong triggers/ |
| 4 | seeders/001_reference.sql → 004_business_policy.sql → 005_operations_policy.sql |
| 5 | Đánh dấu applying thành applied, giữ applied_at của phiên bản cũ |
| 6 | check chạy checks.sql |

Thứ tự routine/trigger được quản lý tại [install-order.json](install-order.json). Cài mới **không chạy lại các file migration**, vì `schema.sql` đã là cấu trúc 005. `init` chưa tạo tài khoản Admin ứng dụng hay dữ liệu người dùng. Policy 004 chỉ điều chỉnh đúng quyền công-lương và ngưỡng đánh trễ.

Kết quả mong đợi là `Hoàn tất thao tác Database: init.`, sau đó `check` không có dòng `VI_PHAM` khác 0. Dòng `CAN_XAC_NHAN` cần được đối chiếu theo [thiết kế database](../docs/database-design.md).

## 4. Database cũ: không dùng init

Sao lưu và thử khôi phục trước, dừng ứng dụng ghi dữ liệu, chạy thử trên bản sao. Sau đó chạy đúng thứ tự:

```powershell
pwsh -File database/scripts/run.ps1 -Action preflight
pwsh -File database/scripts/run.ps1 -Action migrate
pwsh -File database/scripts/run.ps1 -Action check
```

- `preflight`: kiểm tra dữ liệu trước khi thay đổi.
- `migrate`: nhận diện SQL gốc/001/002/003/004, chạy phần còn thiếu theo thứ tự 001 → 002 → 003 → 004 → 005, nạp routine và trigger. Database đã ở 005 được kiểm tra, không ALTER lại. Backup/restore thử trước khi nâng cấp.
- `check`: kiểm tra toàn vẹn sau nâng cấp.

Nếu có `VI_PHAM` hoặc migration còn `applying`, dừng và đối chiếu dữ liệu/tiến độ. MySQL DDL tự COMMIT; không xóa trạng thái để chạy lại tùy ý. Không import `schema.sql` lên database đang có dữ liệu. Chi tiết thay đổi tại [docs/database-design.md](../docs/database-design.md).

## 5. Nạp demo và chạy bộ kiểm thử

Dùng **database mới riêng**, tên kết thúc bằng `_test`. Tài khoản triển khai cần được cấp quyền trên database thử nghiệm tương ứng. Đổi `DB_NAME` trong một bản cấu hình riêng, ví dụ `database/.env.test.local`:

```powershell
Copy-Item database/.env.example database/.env.test.local
# Sửa kết nối, mật khẩu và DB_NAME=educenter_local_test trong file vừa tạo.
# Cấp quyền trên educenter_local_test.* cho tài khoản triển khai trước khi init.
pwsh -File database/scripts/run.ps1 -Action init -EnvFile database/.env.test.local
pwsh -File database/scripts/run.ps1 -Action seed-demo -EnvFile database/.env.test.local
pwsh -File database/scripts/run.ps1 -Action test -EnvFile database/.env.test.local
pwsh -File database/scripts/run.ps1 -Action check -EnvFile database/.env.test.local
```

`seed-demo` chạy `seeders/002_demo.sql`, yêu cầu chưa có người dùng; không nạp demo hai lần. `test` lần lượt chạy `integrity.sql` → `regressions.sql` → `attributes.sql` → `workflows.sql` → `business_rules.sql` → `operations_ready.sql` trong `database/tests/`. Có 98 ca SQL (51 hồi quy + 27 nghiệp vụ004 + 20 nghiệp vụ005); các suite có COMMIT làm thay đổi demo. Tài khoản demo bị khóa, không phải tài khoản đăng nhập của demo HTML.

Với Docker hiện có, dùng PowerShell 7 và `-Container educenter-mysql -EnvFile config/server.env` cho các Action. Script đọc mật khẩu root bên trong container; không cần cài mysql.exe trên Windows. Chỉ dùng tùy chọn này cho Docker local được bạn quản lý. Chi tiết nâng cấp/backup ở [DB/server005](../docs/db-server-readiness.md).

Kiểm tra cấu trúc repository, không cần MySQL:

```powershell
python database/tests/verify_structure.py --scaffold-only
```

Kết quả SQL đã ghi nhận trước đây tại [docs/database-validation.md](../docs/database-validation.md); báo cáo đó không có nghĩa database trên máy mới đã được khởi tạo.

## 6. Lỗi thường gặp

| Lỗi | Cách xử lý |
| --- | --- |
| Không nhận `pwsh` | Cài PowerShell 7 và mở terminal mới |
| Không nhận `mysql` | Điền đường dẫn `mysql.exe` vào `MYSQL_BIN` |
| Không kết nối được | Kiểm tra dịch vụ MySQL, `DB_HOST`, `DB_PORT` và firewall |
| `Access denied` | Kiểm tra user/mật khẩu và host được cấp quyền; không dùng tài khoản API để migration |
| Thiếu quyền tạo function/trigger, lỗi 1418/1419 | Nhờ DBA kiểm tra quyền và binary logging; với máy phát triển riêng, quản trị viên có thể bật `SET GLOBAL log_bin_trust_function_creators = 1;` trước khi cài |
| `Init chỉ dành cho database rỗng` | Database cũ dùng `preflight` → `migrate`; cài thử thì dùng database rỗng khác |
| Lần init trước bị lỗi giữa chừng | Đối chiếu lỗi/DDL đã chạy; không chạy lại init trên database đã có bảng |
| Demo/test bị từ chối | Dùng database `_test` mới, đã init, chưa có người dùng trước khi seed |

SQL gốc ở `docs/reference/database/schema-original.sql` chứa lệnh xóa database và chỉ phục vụ tham khảo. File đó không nằm trong thứ tự cài đặt.

Sau khi database sẵn sàng, xem [hướng dẫn cấu hình server](../docs/server-setup.md). Backend PHP đã có một số API và trang đăng nhập; frontend vẫn giữ khung. Phạm vi còn lại ghi tại [bàn giao DB/server](../docs/db-server-readiness.md).
