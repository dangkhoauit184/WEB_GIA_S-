# EduCenter ERP

Repo clean để phát triển hệ thống trung tâm gia sư theo feature. Frontend và Backend giữ bộ khung rỗng; Database MySQL đã có schema, dữ liệu tham chiếu, migration, function, procedure, trigger và kiểm thử.

**Xem giao diện mẫu:** mở [preview/index.html](preview/index.html) bằng trình duyệt. File được chuyển nguyên byte theo yêu cầu mới; không còn trộn mẫu vào `frontend/`. Mẫu này vẫn dùng dữ liệu demo/localStorage và chưa kết nối database.

## Lấy về máy để code

Chưa có repo local:

```bash
git clone --branch codex/clean-project-database https://github.com/dangkhoauit184/WEB_GIA_S-.git
cd WEB_GIA_S-
```

Đã clone repo, working tree sạch:

```bash
git fetch origin
git switch --track origin/codex/clean-project-database
```

Nếu nhánh local đã tồn tại: `git switch codex/clean-project-database` rồi `git pull --ff-only`. Nhánh clean được tạo từ main đã gộp PR trước. Sau khi PR clean được merge, có thể dùng `main` thay tên nhánh trên. Không dùng reset để ghi đè công việc đang làm.

## Cấu trúc và nơi bắt đầu

| Thư mục | Chứa gì | Bạn dùng để làm gì |
| --- | --- | --- |
| `frontend/src/features/` | 18 feature; mỗi feature có HTML/CSS/JS rỗng | Viết giao diện mới, không sửa mẫu để xây app |
| `frontend/src/shared/` | components, styles, services, utils, permissions | Thành phần giao diện dùng chung |
| `frontend/src/config/` | app.config.js rỗng | Cấu hình frontend sau khi chọn cách build |
| `frontend/assets/` | Vị trí tài nguyên giao diện | Ảnh/font/tài nguyên khi lập trình |
| `backend/src/features/` | 7 file rỗng cho mỗi feature | Triển khai API theo từng nghiệp vụ |
| `backend/src/shared/` | middleware, permissions, errors, utils | Phần tái sử dụng ở backend |
| `backend/src/config/` | app.config.js, database.config.js rỗng | Kết nối/config ứng dụng sau này |
| `shared/` | constants, contracts rỗng | Quy ước dữ liệu dùng chung hai tầng |
| `database/` | SQL, cấu hình và script triển khai độc lập | Thiết lập MySQL và phát triển dữ liệu |
| `preview/` | Giao diện mẫu độc lập | Mở tham khảo bằng trình duyệt |
| `docs/` | Tài liệu nghiệp vụ và báo cáo rà soát | Hiểu quy tắc trước khi lập trình |
| `docs/reference/` | Bản lưu backend/mẫu cũ | Chỉ đối chiếu, không import vào app mới |

```text
WEB/
├── frontend/             # Chỉ phần frontend để phát triển
├── backend/              # Chỉ phần backend để phát triển
├── shared/               # Hợp đồng và hằng số dùng chung
├── preview/index.html    # Xem giao diện mẫu riêng
├── database/
│   ├── schema.sql        # 35 bảng nghiệp vụ + 1 bảng phiên bản
│   ├── install-order.json
│   ├── routines/         # functions.sql, helpers.sql, procedures.sql
│   ├── triggers/         # timekeeping, classes, scheduling, academic, tuition
│   ├── migrations/       # 001 nâng cấu trúc; 002 rà soát stored objects
│   ├── seeders/          # Danh mục bắt buộc + demo tùy chọn
│   ├── reference/        # SQL nguyên gốc, chỉ đối chiếu
│   ├── scripts/run.ps1   # init / migrate / check / seed-demo / test
│   ├── tests/            # Kiểm thử tĩnh, toàn vẹn và quy trình
│   ├── .env.example
│   └── database.config
├── docs/
└── README.md
```

### 18 feature trong cả Frontend và Backend

| Feature | Phạm vi |
| --- | --- |
| auth | Đăng nhập, đăng xuất, phiên người dùng |
| dashboard | Tổng quan và thống kê theo quyền |
| staff | Nhân sự và hồ sơ chuyên môn |
| permissions | Nhóm quyền và ma trận chức năng |
| academic-affairs | Giáo vụ, tuyển sinh, ghép người dạy, đề xuất |
| scheduling | Lịch tuần, ca học, phân công |
| classes | Lớp và danh sách ghi danh |
| students | Hồ sơ học viên |
| teachers | Màn hình/nghiệp vụ giáo viên |
| tutors | Màn hình/nghiệp vụ gia sư |
| parents | Phụ huynh chọn con, theo dõi học tập |
| attendance | Điểm danh, sổ học vụ, bài tập giấy và điểm |
| leave-requests | Xin nghỉ và phê duyệt |
| makeup-classes | Xếp học bù, nối về buổi gốc |
| tuition | Hóa đơn, thu và hoàn học phí |
| timekeeping | Check-in/out, điều chỉnh công |
| payroll | Chốt công, tính lương, mở khóa có audit |
| notifications | Thông báo và kiểm toán |

Mỗi feature frontend có `<feature>.html`, `.css`, `.js`. Mỗi feature backend có `.routes.js`, `.controller.js`, `.service.js`, `.repository.js`, `.model.js`, `.validator.js`, `.middleware.js`. Routes nối endpoint, controller nhận/trả dữ liệu, service điều phối nghiệp vụ, repository truy cập DB, model mô tả dữ liệu, validator kiểm tra đầu vào, middleware kiểm tra điều kiện truy cập. Giữ các file trong cùng feature để dễ tìm, không tạo 7 thư mục chỉ chứa một file.

Hiện có **198 file bộ khung rỗng**. Chưa có API/server/build mới để chạy; chỉ giao diện mẫu có thể mở ngay. Bạn chọn công cụ build khi bắt đầu code; không có package manifest giả hay dependency cài sẵn. Code Express cũ nằm trong `docs/reference/backend/`.

## Database có những bảng gì?

**36 bảng = 35 bảng nghiệp vụ + `schema_migrations`**. Bảng dưới liệt kê toàn bộ, PK và các bảng được tham chiếu bằng FK. Khóa số giữ nguyên kiểu UNSIGNED; tiền dùng DECIMAL cho VND. Chưa tạo bảng trùng riêng cho Giáo viên/Gia sư/Phụ huynh vì hồ sơ chung đã thể hiện các đối tượng này.

| # | Bảng | Mục đích | Khóa chính | Tham chiếu chính |
| --- | --- | --- | --- | --- |
| 1 | `users` | Hồ sơ cá nhân dùng chung | `user_id` | `khu_vuc` |
| 2 | `role_group` | 5 nhóm quyền theo tài liệu | `group_id` | — |
| 3 | `account` | Đăng nhập và khóa tài khoản | `account_id` | `users`, `role_group` |
| 4 | `function` | Danh mục 8 phân hệ quyền | `function_id` | — |
| 5 | `role_group_function` | Ma trận 5 cờ quyền | `id` | `role_group`, `function` |
| 6 | `mon_hoc` | Danh mục môn học | `id` | — |
| 7 | `cap_lop` | Khối và cấp học | `id` | — |
| 8 | `khu_vuc` | Địa bàn dạy học | `id` | — |
| 9 | `co_so` | Chi nhánh trung tâm | `id` | `khu_vuc`, `users` |
| 10 | `cau_hinh` | Tham số vận hành | `khoa` | — |
| 11 | `nhan_vien` | Giáo vụ/điều phối/kế toán | `id` | `users`, `co_so` |
| 12 | `nhan_su_chuyen_mon` | Hồ sơ giáo viên/gia sư/trợ giảng | `id` | `users` |
| 13 | `hoc_vien` | Học viên gắn với phụ huynh | `id` | `users`, `cap_lop`, `khu_vuc` |
| 14 | `nhan_su_mon` | Năng lực môn/khối của người dạy | `id` | `nhan_su_chuyen_mon`, `mon_hoc`, `cap_lop` |
| 15 | `nhan_su_khu_vuc` | Địa bàn người dạy nhận lớp | `nhan_su_id,khu_vuc_id` | `nhan_su_chuyen_mon`, `khu_vuc` |
| 16 | `nhan_su_lich_ranh` | Khung giờ rảnh hàng tuần | `id` | `nhan_su_chuyen_mon` |
| 17 | `yeu_cau_hoc` | Tiếp nhận nhu cầu học | `id` | `hoc_vien`, `mon_hoc`, `cap_lop`, `co_so`, `khu_vuc`, `nhan_vien` |
| 18 | `yeu_cau_lich` | Lịch mong muốn của yêu cầu | `id` | `yeu_cau_hoc` |
| 19 | `de_xuat_nhan_su` | Ghép người dạy và xác nhận hai bên | `id` | `yeu_cau_hoc`, `nhan_su_chuyen_mon`, `nhan_vien` |
| 20 | `lop_hoc` | Lớp kèm/nhóm và mức thu/chi | `id` | `de_xuat_nhan_su`, `mon_hoc`, `cap_lop`, `nhan_su_chuyen_mon`, `co_so`, `khu_vuc`, `nhan_vien` |
| 21 | `lop_hoc_vien` | Ghi danh và thời gian học | `id` | `lop_hoc`, `hoc_vien` |
| 22 | `lich_hoc` | Lịch lặp và khoảng hiệu lực | `id` | `lop_hoc` |
| 23 | `buoi_hoc` | Ca cụ thể, học bù và nội dung học | `id` | `lop_hoc`, `lich_hoc`, `buoi_hoc`, `co_so` |
| 24 | `phan_cong_buoi_hoc` | Người dạy, check-in/out và snapshot lương | `id` | `buoi_hoc`, `users`, `bang_cong_thang` |
| 25 | `dieu_chinh_cong` | Đề nghị sửa giờ công và phê duyệt | `id` | `phan_cong_buoi_hoc`, `users` |
| 26 | `don_xin_nghi` | Đơn nghỉ học/nghỉ dạy và ca bù | `id` | `buoi_hoc`, `hoc_vien`, `users` |
| 27 | `bai_tap` | Giao bài tập giấy, lưu link đề | `id` | `buoi_hoc`, `users` |
| 28 | `so_theo_doi_buoi_hoc` | Điểm danh 4 trạng thái, chấm bài, nhận xét | `id` | `buoi_hoc`, `hoc_vien`, `bai_tap`, `users` |
| 29 | `ket_qua_hoc_tap` | Kết quả kiểm tra/thi | `id` | `lop_hoc`, `hoc_vien`, `users` |
| 30 | `bang_cong_thang` | Tổng hợp lương và khóa kỳ công | `id` | `users` |
| 31 | `hoa_don` | Khoản phải thu theo học viên/lớp/kỳ | `id` | `lop_hoc_vien`, `users` |
| 32 | `hoa_don_chi_tiet` | Từng khoản theo buổi/tháng/khóa | `id` | `hoa_don`, `buoi_hoc`, `hoc_vien` |
| 33 | `thanh_toan` | Chứng từ thu/hoàn và hủy phiếu | `id` | `hoa_don`, `users` |
| 34 | `thong_bao` | Thông báo trong app và trạng thái email | `id` | `users` |
| 35 | `audit_log` | Dấu vết thay đổi nhạy cảm | `id` | `users` |
| 36 | `schema_migrations` | Phiên bản triển khai kỹ thuật | `version` | — |

Giữ 5 nhóm quyền trong tài liệu Word: Admin, NhanVien, GiangVien, TroGiang, PhuHuynh. Giáo viên/Gia sư cùng nhóm GiangVien, phân biệt hồ sơ bằng `phan_loai`. Các feature/cổng giao diện không đồng nghĩa với nhóm quyền mới.

## Function, Procedure và Trigger

Stored objects có **một nguồn thực thi duy nhất**, được nạp theo [database/install-order.json](database/install-order.json). Không còn file routine/trigger gộp song song ở gốc `database/`.

| File | Object | Dùng làm gì |
| --- | --- | --- |
| `routines/functions.sql` | `fn_trung_lich` | Kiểm tra ca giao nhau của người dạy/trợ giảng |
| `routines/functions.sql` | `fn_tien_cong` | Tính tiền theo snapshot theo buổi/giờ, làm tròn VND, chặn đầu vào thiếu/sai |
| `routines/helpers.sql` | `sp_kiem_tra_si_so` | Kiểm tra sức chứa khi ghi danh/chuyển trạng thái |
| `routines/helpers.sql` | `sp_kiem_tra_buoi` | Kiểm tra phòng, lịch tuần, buổi gốc, xung đột nhân sự và kỳ công đích |
| `routines/helpers.sql` | `sp_kiem_tra_hoc_vu` | Học viên phải thuộc lớp tại ngày học, bài tập thuộc cùng lớp |
| `routines/helpers.sql` | `sp_kiem_tra_don_nghi` | Đơn nghỉ/học bù nối đúng học viên và buổi gốc |
| `routines/helpers.sql` | `sp_kiem_tra_khoan_thu` | Chi tiết hóa đơn cùng học viên/lớp |
| `routines/helpers.sql` | `sp_kiem_tra_thanh_toan` | Khóa hóa đơn và chặn thu/hoàn vượt số dư |
| `routines/helpers.sql` | `sp_cap_nhat_so_du` | Cập nhật lũy kế và trạng thái hóa đơn từ chứng từ hợp lệ |
| `routines/procedures.sql` | `sp_sinh_buoi_hoc` | Sinh lịch trong khoảng ngày, phân công chính, không nhân đôi buổi đã có |
| `routines/procedures.sql` | `sp_chot_cong` | Chốt đúng người/kỳ, chặn điều kiện chưa đủ, tính snapshot, ghi audit |
| `routines/procedures.sql` | `sp_mo_khoa_cong` | Admin mở kỳ đã chốt với lý do; bảng đã trả cần quy trình riêng |

| Nhóm trigger | Số lượng | Phạm vi bảo vệ |
| --- | --- | --- |
| `triggers/timekeeping.sql` | 5 | Phân công, kỳ công đã khóa, snapshot, ID, đồng bộ khóa khi ghi đơn điều chỉnh |
| `triggers/classes.sql` | 3 | Sĩ số INSERT/UPDATE và giảm sức chứa lớp |
| `triggers/scheduling.sql` | 2 | Phòng, đổi lịch, ca bù và buổi gốc |
| `triggers/academic.sql` | 4 | Điểm danh, bài tập, đơn nghỉ và liên kết học bù |
| `triggers/tuition.sql` | 9 | Thu/hoàn/hủy phiếu, số dư, danh tính hóa đơn, chi tiết đúng học viên |
| **Tổng** | **23** | **2 function + 10 procedure + 23 trigger** |

## Cách thiết lập MySQL

Hướng dẫn đầy đủ và cách chạy SQL trực tiếp ở [database/README.md](database/README.md). Cần MySQL **8.0.16+ hoặc 8.4 LTS**, MySQL CLI và PowerShell 7+; đã kiểm tra thực tế trên MySQL 8.4.13. Dùng InnoDB, utf8mb4, giờ +07:00 và READ COMMITTED. Chưa hỗ trợ MariaDB/SQL Server.

```powershell
Copy-Item database/.env.example database/.env
# Điền DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, MYSQL_BIN
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

Init chỉ dùng database rỗng, không nạp tài khoản Admin hay demo production. Nếu đã dùng DB bản trước, sao lưu/dừng ghi rồi dùng `-Action preflight`, `-Action migrate`, `-Action check`; không chạy lại schema. Migration 002 chỉ thay stored objects, không sửa cấu trúc/dữ liệu nghiệp vụ. Cấu hình DB thực tế không commit lên Git.

Muốn thử dữ liệu mẫu, chọn database dùng một lần có hậu tố `_test`, init rồi:

```powershell
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action test
```

Test quy trình có COMMIT riêng và sửa demo, không dùng cho dữ liệu thật. Tài khoản demo bị khóa vì hash mật khẩu công khai; đổi hash trước khi mở.

## Kiểm tra và những phần cần xác nhận

```powershell
python database/tests/verify_structure.py
# Kiểm tra nghiêm ngặt bộ khung rỗng khi bàn giao:
python database/tests/verify_structure.py --scaffold-only
```

Kiểm tra mặc định không bắt file lập trình phải rỗng, để bạn code tiếp mà không bị CI chặn vì đã thêm code. CI kiểm tra cấu trúc, cú pháp JS mẫu và chạy SQL test trên MySQL riêng. Kết quả kiểm thử lần này ở [database/tests/VALIDATION.md](database/tests/VALIDATION.md).

Phí tháng, chính sách nghỉ có tính phí, GPS bắt buộc/bán kính, phân quyền chốt của Nhân viên và lương kỳ đã trả còn khác/chưa rõ giữa các tài liệu. Chưa tự suy ra mức tiền hoặc quyền. Chi tiết tại [docs/database-review.md](docs/database-review.md).

## Thay đổi trong bản clean

| Phần | Thay đổi |
| --- | --- |
| Giao diện mẫu | Chuyển nguyên byte sang `preview/index.html`; bản frontend cũ lưu tại `docs/reference/frontend/index.html` |
| Tài liệu và SQL nguồn | Gom SQL gốc vào `database/reference/`, bản trùng và mẫu cũ vào `docs/reference/`; không còn file mẫu rải ở gốc |
| Frontend/Backend | Giữ rỗng theo 18 feature, không trộn code demo, không viết API |
| Database | Giữ schema 36 bảng; tách routines/trigger theo nghiệp vụ và thêm migration 002 |
| Tính lương | Function dùng snapshot, chặn công hợp lệ chưa khớp trạng thái buổi |
| Khóa công | Chặn dời ca sang kỳ đã khóa và xóa ca chưa gắn bảng công trong kỳ đó |
| Học bù | Chặn thay ID/khôi phục buổi gốc đã có ca bù |
| Học phí | Chặn đổi ID chứng từ, chuyển chủ sở hữu/kỳ hóa đơn đã có chứng từ |
| Kiểm thử | Thêm 8 regression case; kiểm thử cả init, SQL gốc→002 và 001→002 |
| GitHub | Giữ file tham chiếu nguyên byte, cập nhật CI đúng vị trí mẫu mới |
