# EduCenter ERP

Dự án tổ chức theo feature để phát triển hệ thống quản lý trung tâm gia sư. **Frontend và Backend chỉ có bộ khung file rỗng; Database có schema, migration, seeders, cấu hình, routine và trigger riêng.**

`index.html` ở gốc và `frontend/index.html` sẵn có giữ nguyên từng byte làm giao diện tham khảo. Hai file SQL gốc ở gốc repo và `database/` cũng nguyên trạng. **Không chạy SQL gốc trên database có dữ liệu: file đó chứa DROP DATABASE.** Backend mock cũ, procedure khung cũ và README cũ được lưu nguyên trạng tại `docs/reference/`.

## Cấu trúc dự án

```text
WEB/
├── index.html                       # Giao diện mẫu nguyên trạng
├── trung_tam_gia_su_35_tables.sql     # SQL gốc, chỉ đối chiếu
├── frontend/
│   ├── index.html                   # Bản mẫu sẵn có, nguyên trạng
│   ├── assets/                      # Tài nguyên sẵn có
│   └── src/
│       ├── app.js                   # File rỗng
│       ├── config/app.config.js     # File rỗng
│       ├── features/<feature>/      # <feature>.html, .css, .js rỗng
│       └── shared/                  # components, styles, utils, services, permissions
├── backend/
│   ├── server.js                    # File rỗng, chưa có server chạy
│   └── src/
│       ├── app.js                   # File rỗng
│       ├── config/                  # app.config.js, database.config.js rỗng
│       ├── features/<feature>/      # 7 file .js rỗng/feature
│       └── shared/                  # middleware, permissions, utils, errors
├── shared/                          # File dùng chung rỗng: constants, contracts
├── database/
│   ├── trung_tam_gia_su_35_tables.sql # SQL gốc nguyên trạng
│   ├── schema.sql                   # 35 bảng nghiệp vụ + schema_migrations
│   ├── procedures.sql               # Hàm và thủ tục database
│   ├── triggers.sql                 # Ràng buộc nghiệp vụ
│   ├── checks.sql                   # Báo cáo sau triển khai, chỉ đọc
│   ├── database.config              # Mẫu option file MySQL CLI
│   ├── .env.example                 # Kết nối cho script database
│   ├── migrations/
│   │   ├── preflight.sql            # Kiểm tra dữ liệu cũ, chỉ đọc
│   │   └── 001_feature_based.sql    # Nâng cấp đúng schema gốc
│   ├── seeders/
│   │   ├── 001_reference.sql        # Quyền, danh mục và cấu hình gốc
│   │   └── 002_demo.sql             # Demo tùy chọn, tài khoản bị khóa
│   ├── scripts/run.ps1              # Thiết lập bằng MySQL CLI
│   └── tests/                       # Kiểm tra cấu trúc và toàn vẹn SQL
└── docs/
    ├── architecture.md              # Tài liệu sẵn có
    ├── database-review.md           # Đối chiếu nghiệp vụ và thay đổi SQL
    ├── quy_trinh_nghiep_vu_v2.docx   # Tài liệu gốc
    └── reference/                   # Bản lưu code và cấu hình cũ
```

## Các feature và thành phần dùng chung

| Thư mục          | Chức năng dự kiến                                |
| ---------------- | ------------------------------------------------ |
| auth             | Đăng nhập, đăng xuất, phiên người dùng theo UC01 |
| dashboard        | Màn hình tổng quan, thống kê theo quyền          |
| staff            | Nhân sự và hồ sơ chuyên môn                      |
| permissions      | Nhóm quyền và quyền chức năng                    |
| academic-affairs | Giáo vụ, tuyển sinh, ghép người dạy, đề xuất     |
| scheduling       | Lịch tuần, ca học và phân công                   |
| classes          | Lớp học và danh sách lớp                         |
| students         | Hồ sơ học viên                                   |
| teachers         | Nghiệp vụ dành cho giáo viên                     |
| tutors           | Nghiệp vụ dành cho gia sư                        |
| parents          | Phụ huynh, chọn con, theo dõi học tập            |
| attendance       | Điểm danh, sổ học vụ, bài tập giấy, kết quả      |
| leave-requests   | Xin nghỉ và phê duyệt                            |
| makeup-classes   | Ca bù liên kết buổi gốc                          |
| tuition          | Hóa đơn, thu và hoàn học phí                     |
| timekeeping      | Check-in/out và điều chỉnh công                  |
| payroll          | Chốt công, lương và mở khóa                      |
| notifications    | Thông báo và kiểm toán                           |

Mỗi feature backend có `<feature>.routes.js`, `.controller.js`, `.service.js`, `.repository.js`, `.model.js`, `.validator.js`, `.middleware.js`. Đặt cùng thư mục feature để tránh tạo 7 thư mục chỉ chứa một file. Khi lập trình: routes định tuyến; controller nhận/trả dữ liệu; service điều phối nghiệp vụ; repository truy cập database; model mô tả dữ liệu; validator kiểm tra đầu vào; middleware kiểm tra điều kiện truy cập.

`shared/` gốc dành cho hằng số và hợp đồng dữ liệu dùng chung hai tầng. `frontend/src/shared/` và `backend/src/shared/` dành cho thành phần dùng trong từng tầng. Tất cả file bộ khung mới đều **0 byte**, không có code, import hay comment. Chưa chọn framework/build tool, chưa cài dependency, chưa viết API. Package Express cũ nằm trong `docs/reference/backend/package.json`; không chạy `npm install` cho bộ khung hiện tại.

## Thiết lập database mới

Cần **MySQL 8.0.16+ hoặc MySQL 8.4 LTS**, MySQL CLI (`mysql.exe`) và **PowerShell 7+**. Dùng InnoDB, `utf8mb4_unicode_ci`, giờ `+07:00`, kết nối ghi **READ COMMITTED**. MariaDB/SQL Server chưa được hỗ trợ.

Script không cài MySQL hay tự tạo tài khoản DB. Chuẩn bị tài khoản triển khai có quyền tạo database (hoặc nhờ DBA tạo trước database rỗng), bảng, FK, trigger, routine và dữ liệu tham chiếu. Khi bật binary logging, quyền tạo function phụ thuộc cấu hình DBA; không tự bật `log_bin_trust_function_creators` toàn server. Không dùng tài khoản triển khai làm tài khoản API sau này.

Từ thư mục repo:

```powershell
Copy-Item database/.env.example database/.env
# Sửa DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, MYSQL_BIN
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

`MYSQL_BIN` là `mysql` nếu có trong PATH hoặc đường dẫn tuyệt đối đến `mysql.exe`, không bọc dấu nháy trong `.env`. Dùng nguyên văn `KEY=value`, không nội suy biến/chú thích cuối dòng. Không commit `.env`. Mật khẩu không nằm trên command line; script dùng option file tạm và xóa trong `finally`.

`init` chỉ chạy trên database rỗng. Thứ tự: `schema.sql` → `procedures.sql` → `triggers.sql` → `seeders/001_reference.sql` → đánh dấu migration applied. Init chưa tạo người dùng hay Admin thật; thực hiện qua luồng xác thực an toàn khi phát triển backend. Seed không đặt mật khẩu production.

Dữ liệu minh họa chỉ dành cho **database riêng có tên kết thúc `_test`**. Sau khi init, chạy:

```powershell
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action check
```

Demo chỉ nạp khi bảng users rỗng. Tài khoản demo có hash cũ công khai nên đã khóa; không mở trước khi thay hash. Demo có procedure tự quản lý transaction; nếu lỗi giữa chừng không chạy lại mù quáng trên dữ liệu dở dang.

### Chạy trực tiếp bằng SQL client

`database.config` là mẫu option file CLI; `.env` dành cho script PowerShell. MySQL CLI không tự đọc `.env`. Có thể sao chép `database.config` ra ngoài repo, điền kết nối và dùng `mysql --defaults-extra-file=<file-riêng> -p`. Trong mysql client:

```sql
CREATE DATABASE educenter_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE educenter_test;
SET SESSION time_zone = '+07:00';
SET SESSION transaction_isolation = 'READ-COMMITTED';
SOURCE D:/WEB/database/schema.sql;
SOURCE D:/WEB/database/procedures.sql;
SOURCE D:/WEB/database/triggers.sql;
SOURCE D:/WEB/database/seeders/001_reference.sql;
INSERT INTO schema_migrations(version,status,applied_at)
VALUES ('001_feature_based','applied',NOW());
SOURCE D:/WEB/database/checks.sql;
```

Đổi đường dẫn theo checkout. `SOURCE` là lệnh mysql client; trong GUI chạy từng file cùng thứ tự và bật dừng khi lỗi. Không dùng `--force`. Schema không hard-code tên database.

## Nâng cấp database đang có dữ liệu

Migration chỉ áp dụng cho database **đúng cấu trúc 35 bảng SQL gốc**, không phải schema tùy chỉnh bất kỳ. Không import lại schema hay seed trên dữ liệu thật.

1. Sao lưu và kiểm tra khả năng khôi phục. Dừng ghi ứng dụng; xác nhận đúng host/database. So sánh cấu trúc hiện có với SQL gốc, nhất là cột, khóa và trigger. Preflight kiểm tra dữ liệu nhưng không chứng minh một schema tùy chỉnh tương thích.
2. Thử trên bản sao database cũ trước. Điền `.env` và chạy:

   ```powershell
   pwsh -File database/scripts/run.ps1 -Action preflight
   pwsh -File database/scripts/run.ps1 -Action migrate
   pwsh -File database/scripts/run.ps1 -Action check
   ```

3. Có vấn đề preflight hoặc lỗi SQL thì dừng. Đối chiếu nghiệp vụ và sửa bằng kế hoạch riêng được xác nhận. Script không tự xóa dữ liệu, đổi ID, số tiền hoặc tắt ràng buộc để chạy tiếp.
4. Migration thêm cột nullable, CHECK/FK/index và nhật ký. Chỉ sao chép cơ sở hiện tại của lớp cho buổi **chưa gắn bảng công**; phải kiểm tra cơ sở lịch sử trước. Không tự backfill cách tính lương quá khứ từ lớp hiện tại. `checks.sql` báo `CAN_XAC_NHAN` cho dữ liệu chưa rõ; chốt công từ chối snapshot NULL.
5. DDL MySQL tự COMMIT. Nếu lỗi, trạng thái có thể còn `applying`; script từ chối chạy lại. Kiểm tra DDL đã áp dụng và phục hồi backup hoặc lập migration tiếp theo; không tự xóa cờ applying. Không cung cấp down migration phá dữ liệu.

Nếu chạy bằng mysql client: preflight trước, kiểm tra kết quả, rồi `001_feature_based.sql` → `procedures.sql` → `triggers.sql`. Chỉ khi tất cả thành công mới `UPDATE schema_migrations SET status='applied', applied_at=NOW() WHERE version='001_feature_based';`. Không chạy seeder trên database cũ.

## Danh sách thay đổi SQL

- Bỏ thao tác xóa database ở bản hoàn thiện; tách schema, routine, trigger, seed tham chiếu và demo. Giữ SQL gốc nguyên trạng.
- Thêm `lop_hoc.hoc_phi_thang`, `buoi_hoc.co_so_id`, `phan_cong_buoi_hoc.cach_tinh_luong`, đều nullable để không đoán lịch sử.
- Thêm CHECK cho hiệu lực lịch, ngân sách/đơn giá/học phí, số buổi, thang điểm, GPS, công hợp lệ, giờ điều chỉnh, tiền và chi tiết hóa đơn; thêm chỉ mục phòng.
- Giữ PK, ID và 35 bảng nghiệp vụ. Đổi hai FK SET NULL thành RESTRICT để giữ chứng từ và khóa công. Thêm duy nhất bảng kỹ thuật `schema_migrations`.
- Kiểm tra INSERT/UPDATE cho sĩ số, lịch, học vụ, đơn nghỉ, khoản thu; kiểm soát thu/hoàn/hủy phiếu và ca đã khóa.
- Hoàn thiện sinh lịch và chốt công đúng schema, tính lương bằng snapshot; chặn chốt khi còn thiếu checkout/đơn điều chỉnh chờ duyệt. Bổ sung audit và mở khóa có lý do.
- Thêm kiểm tra trước/sau migration, cấu hình, script thiết lập và demo tùy chọn với tài khoản khóa.

Chi tiết từng thay đổi và ánh xạ bảng/feature nằm trong [docs/database-review.md](docs/database-review.md). Mọi thay đổi Database có comment tiếng Việt ngay tại vị trí. Giữ 5 nhóm quyền trong Word; chưa tự thêm nhóm Gia sư thứ sáu, phí nghỉ, bán kính GPS, quy trình điều chỉnh lương đã trả hay business rule chưa được quy định.

## Kiểm tra và phát triển tiếp

```powershell
python database/tests/verify_structure.py
```

Kiểm tra hash file bảo toàn, file rỗng và cấu trúc SQL. `database/tests/integrity.sql` và `database/tests/workflows.sql` kiểm tra ràng buộc/quy trình trên **database thử nghiệm riêng** sau init/seed-demo; đọc hướng dẫn đầu file. Kết quả kiểm tra thực tế được ghi trong `database/tests/VALIDATION.md`.

Không sửa giao diện mẫu khi phát triển nếu chưa có chỉ định. Đặt code mới vào feature tương ứng; chỉ đưa phần tái sử dụng vào shared. Không import `docs/reference` vào ứng dụng mới. Tài liệu nghiệp vụ và các thay đổi có sẵn ngoài phạm vi vẫn được giữ.
