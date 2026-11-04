# Hướng dẫn chạy database EduCenter ERP

**Chạy các lệnh từ thư mục gốc repository**, không phải từ bên trong `database/`. Phiên bản schema hiện tại là `003_attribute_review`.

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
| 1 | `schema.sql`: tạo cấu trúc bảng mới nhất |
| 2 | Ghi lịch sử 001/002/003 ở trạng thái `applying` |
| 3 | `routines/functions.sql` |
| 4 | `routines/helpers.sql` |
| 5 | `routines/procedures.sql` |
| 6 | `triggers/timekeeping.sql` |
| 7 | `triggers/classes.sql` |
| 8 | `triggers/scheduling.sql` |
| 9 | `triggers/academic.sql` |
| 10 | `triggers/tuition.sql` |
| 11 | `triggers/payroll.sql` |
| 12 | `seeders/001_reference.sql`: nạp danh mục chuẩn |
| 13 | Đánh dấu các phiên bản đã `applied` |
| 14 | Lệnh `check` chạy `checks.sql` để kiểm tra |

Thứ tự routine/trigger được quản lý tại [install-order.json](install-order.json). Cài mới **không chạy lại các file migration**, vì `schema.sql` đã là cấu trúc 003. `init` chưa tạo tài khoản Admin ứng dụng hay dữ liệu người dùng.

Kết quả mong đợi là `Hoàn tất thao tác Database: init.`, sau đó `check` không có dòng `VI_PHAM` khác 0. Dòng `CAN_XAC_NHAN` cần được đối chiếu theo [thiết kế database](../docs/database-design.md).

## 4. Database cũ: không dùng init

Sao lưu và thử khôi phục trước, dừng ứng dụng ghi dữ liệu, chạy thử trên bản sao. Sau đó chạy đúng thứ tự:

```powershell
pwsh -File database/scripts/run.ps1 -Action preflight
pwsh -File database/scripts/run.ps1 -Action migrate
pwsh -File database/scripts/run.ps1 -Action check
```

- `preflight`: kiểm tra dữ liệu trước khi thay đổi.
- `migrate`: nhận diện SQL gốc/001/002, chạy phần còn thiếu theo thứ tự 001 → 002 → 003, nạp routine và trigger. Database đã ở 003 được kiểm tra, không ALTER lại.
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

`seed-demo` chạy `seeders/002_demo.sql`, yêu cầu chưa có người dùng; không nạp demo hai lần. `test` lần lượt chạy `integrity.sql` → `regressions.sql` → `attributes.sql` → `workflows.sql` trong `database/tests/`. Có 51 ca hành vi; suite workflow có COMMIT và làm thay đổi demo. Tài khoản demo trong MySQL bị khóa, không phải tài khoản đăng nhập của demo HTML.

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

Sau khi database sẵn sàng, xem [hướng dẫn cấu hình server](../docs/server-setup.md). Frontend/backend hiện chỉ là file khung rỗng, chưa có chương trình kết nối database.
