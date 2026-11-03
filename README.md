# Đồ án xây dựng hệ thống quản lý trung tâm gia sư và đào tạo

**EduCenter ERP** hỗ trợ trung tâm quản lý xuyên suốt từ tiếp nhận nhu cầu học, ghép người dạy, tổ chức lớp và lịch học đến điểm danh, chấm công, quyết toán thù lao và thu học phí.

Repository chứa tài liệu phân tích nghiệp vụ, thiết kế cơ sở dữ liệu MySQL, giao diện minh họa và bộ khung phát triển ứng dụng theo chức năng. Phiên bản database hiện tại là **003_attribute_review**.

## 1. Mục tiêu và phạm vi đồ án

- Tập trung hồ sơ học viên, phụ huynh, nhân viên, giáo viên, gia sư và trợ giảng.
- Hỗ trợ tìm người dạy theo môn, khối lớp, địa bàn, lịch rảnh và ngân sách.
- Quản lý lớp kèm 1–1, kèm 1–2 và lớp nhóm; lập lịch tuần và sinh buổi học.
- Ghi nhận check-in/out, điểm danh, nội dung buổi học và kết quả bài tập giấy.
- Xử lý đơn nghỉ, học bù, chốt công và bảo vệ lịch sử thù lao.
- Lập hóa đơn, thu nhiều đợt, hoàn tiền, thông báo và ghi nhật ký thao tác.

Các chính sách chưa thống nhất được ghi tại [báo cáo rà soát database](docs/database-review.md), để nhóm thực hiện xác nhận trước khi triển khai API.

## 2. Tác nhân và phân quyền

| Nhóm quyền | Đối tượng | Phạm vi chính |
| --- | --- | --- |
| Admin | Quản trị viên, ban quản lý | Quản lý tài khoản, cấu hình, theo dõi toàn hệ thống và mở khóa công |
| NhanVien | Giáo vụ, điều phối, kế toán | Tiếp nhận, ghép lớp, xếp lịch, duyệt đơn, hóa đơn và thu tiền theo quyền được cấp |
| GiangVien | Giáo viên và gia sư dạy chính | Lịch dạy, check-in/out, điểm danh, nội dung học và chấm bài |
| TroGiang | Trợ giảng | Check-in/out và hỗ trợ điểm danh theo phân công |
| PhuHuynh | Phụ huynh học viên | Chọn con, theo dõi lịch và kết quả, xin nghỉ, xem học phí |

Hồ sơ chuyên môn phân biệt giáo viên/gia sư/trợ giảng bằng `nhan_su_chuyen_mon.phan_loai`. Cổng giao diện không tương đương một nhóm quyền mới. API phải kiểm tra thêm phạm vi cơ sở, phân công và quan hệ phụ huynh–học viên.

## 3. Luồng nghiệp vụ

1. Nhân viên tiếp nhận hoặc phụ huynh gửi yêu cầu học; lưu các khung giờ mong muốn.
2. Trung tâm lọc và đề xuất nhân sự phù hợp; lưu phản hồi riêng của người dạy và phụ huynh.
3. Khi chốt phương án, tạo lớp, ghi danh học viên, lập lịch tuần và sinh buổi học.
4. Người dạy check-in/out, cập nhật nội dung, điểm danh, chấm bài giấy và giao bài về nhà.
5. Giáo vụ xử lý xin nghỉ và tổ chức học bù liên kết với buổi gốc.
6. Tổng hợp ca hợp lệ để chốt công; ghi nhận chi trả và bảo vệ kỳ đã trả.
7. Phát hành hóa đơn, lập phiếu thu/hoàn và theo dõi số dư.

Đặc tả chi tiết: [tài liệu nghiệp vụ](docs/quy_trinh_nghiep_vu_v2.docx), [kiến trúc và quy tắc](docs/architecture.md).

## 4. Trạng thái triển khai và công nghệ

| Thành phần | Công nghệ và trạng thái |
| --- | --- |
| Cơ sở dữ liệu | MySQL 8.0.16+ hoặc 8.4 LTS, InnoDB, utf8mb4; đã có schema, migration, routines, trigger và kiểm thử |
| Giao diện minh họa | HTML/CSS/JavaScript trong `preview/index.html`; dùng dữ liệu demo và localStorage |
| Frontend ứng dụng | Bộ khung rỗng theo 18 chức năng; chưa chọn công cụ build |
| Backend ứng dụng | Bộ khung JavaScript theo chức năng; chưa triển khai server, API hoặc kết nối MySQL |
| Công cụ database | PowerShell 7+, MySQL CLI; Python 3 để kiểm tra cấu trúc |
| CI | GitHub Actions kiểm tra JavaScript của demo, cấu trúc dự án và bộ kiểm thử trên MySQL 8.4 |

Hiện có **198 file khung rỗng**. Mã Express cũ được lưu tại `docs/reference/backend/` để tham khảo. Repository chưa có ứng dụng web hoàn chỉnh để chạy bằng `npm start`.

## 5. Cấu trúc mã nguồn

```text
WEB/
├── frontend/                 # Giao diện ứng dụng sẽ phát triển
│   └── src/{features,shared,config}/
├── backend/                  # API sẽ phát triển
│   └── src/{features,shared,config}/
├── shared/                   # Hằng số và hợp đồng dữ liệu dùng chung
├── database/
│   ├── schema.sql            # Cấu trúc mới nhất, dành cho database rỗng
│   ├── routines/             # Functions, helpers và procedure nghiệp vụ
│   ├── triggers/             # Ràng buộc vận hành theo nghiệp vụ
│   ├── migrations/           # Nâng cấp 001 → 002 → 003
│   ├── seeders/              # Danh mục chuẩn và demo tùy chọn
│   ├── scripts/run.ps1       # Công cụ triển khai và kiểm thử
│   └── tests/                # Toàn vẹn, hồi quy, thuộc tính và quy trình
├── preview/index.html        # Giao diện minh họa có thể mở ngay
└── docs/                     # Phân tích nghiệp vụ và tài liệu tham chiếu
```

18 chức năng: `auth`, `dashboard`, `staff`, `permissions`, `academic-affairs`, `scheduling`, `classes`, `students`, `teachers`, `tutors`, `parents`, `attendance`, `leave-requests`, `makeup-classes`, `tuition`, `timekeeping`, `payroll`, `notifications`.

Mỗi chức năng frontend có HTML/CSS/JS. Backend chia thành `routes`, `controller`, `service`, `repository`, `model`, `validator`, `middleware`. Logic nghiệp vụ đặt tại service; repository thực hiện truy cập dữ liệu.

## 6. Thiết kế cơ sở dữ liệu

Database có **35 bảng nghiệp vụ và 1 bảng quản lý phiên bản**.

| Nhóm | Các bảng |
| --- | --- |
| Tài khoản và quyền | `users`, `account`, `role_group`, `function`, `role_group_function` |
| Danh mục và cơ sở | `mon_hoc`, `cap_lop`, `khu_vuc`, `co_so`, `cau_hinh` |
| Hồ sơ | `nhan_vien`, `nhan_su_chuyen_mon`, `hoc_vien` |
| Năng lực và lịch rảnh | `nhan_su_mon`, `nhan_su_khu_vuc`, `nhan_su_lich_ranh` |
| Tuyển sinh và ghép lớp | `yeu_cau_hoc`, `yeu_cau_lich`, `de_xuat_nhan_su` |
| Lớp và lịch tuần | `lop_hoc`, `lop_hoc_vien`, `lich_hoc` |
| Vận hành buổi học | `buoi_hoc`, `phan_cong_buoi_hoc`, `dieu_chinh_cong`, `don_xin_nghi` |
| Học vụ | `bai_tap`, `so_theo_doi_buoi_hoc`, `ket_qua_hoc_tap` |
| Công lương và học phí | `bang_cong_thang`, `hoa_don`, `hoa_don_chi_tiet`, `thanh_toan` |
| Thông báo và kiểm toán | `thong_bao`, `audit_log` |
| Phiên bản | `schema_migrations` |

Các đối tượng SQL gồm **2 function, 10 procedure và 25 trigger**. Ba procedure nghiệp vụ chính:

- `sp_sinh_buoi_hoc`: sinh buổi từ lịch tuần, tránh sinh trùng.
- `sp_chot_cong`: kiểm tra ca chưa hoàn tất/đơn chờ xử lý, tính thù lao từ snapshot và khóa kỳ.
- `sp_mo_khoa_cong`: cho Admin mở kỳ đã chốt kèm lý do và audit.

Phiên bản 003 bỏ `hoa_don_chi_tiet.hoc_vien_id` dư thừa; chuyển `thong_bao.da_doc` thành cột tự tính; tách lý do từ chối hai bên; bổ sung đơn vị giá, người gửi yêu cầu và dấu vết phát hành/hủy/chi trả. Chi tiết chuyển đổi và các trường lịch sử chưa xác minh tại [database/README.md](database/README.md).

## 7. Hướng dẫn sử dụng

### Lấy mã nguồn

```bash
git clone --branch codex/clean-project-database https://github.com/dangkhoauit184/WEB_GIA_S-.git
cd WEB_GIA_S-
```

### Xem giao diện minh họa

Mở `preview/index.html` bằng trình duyệt. Thao tác trên demo lưu vào localStorage của trình duyệt, chưa ghi vào MySQL. Thông tin demo không phải tài khoản của database ứng dụng.

### Khởi tạo database

Cài MySQL CLI và PowerShell 7+. Tại thư mục gốc repository:

```powershell
Copy-Item database/.env.example database/.env
# Điền DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD và MYSQL_BIN.
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

`init` chỉ làm việc với database rỗng, nạp schema mới nhất và danh mục chuẩn; chưa tạo tài khoản Admin thật. Dữ liệu demo chỉ được nạp vào database có tên kết thúc bằng `_test`.

### Nâng cấp database đã có

Sao lưu, kiểm tra khả năng khôi phục và dừng ghi ứng dụng trước khi nâng cấp. Chạy thử trên bản sao:

```powershell
pwsh -File database/scripts/run.ps1 -Action preflight
pwsh -File database/scripts/run.ps1 -Action migrate
pwsh -File database/scripts/run.ps1 -Action check
```

Migration nhận diện SQL gốc, 001 hoặc 002 rồi nâng lên 003. Dữ liệu mâu thuẫn phải được đối chiếu trước khi tiếp tục; không tự suy đoán người thao tác, thời điểm cũ hoặc đơn vị giá. Hướng dẫn đầy đủ tại [tài liệu database](database/README.md).

## 8. Kiểm thử và kết quả

```powershell
python database/tests/verify_structure.py --scaffold-only
# Cấu hình database dùng một lần có hậu tố _test, đã init:
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action test
pwsh -File database/scripts/run.ps1 -Action check
```

| Bộ kiểm thử | Số ca |
| --- | ---: |
| Toàn vẹn dữ liệu | 11 |
| Hồi quy phiên bản 002 | 8 |
| Thuộc tính và dấu vết phiên bản 003 | 20 |
| Quy trình nghiệp vụ | 12 |
| **Tổng** | **51** |

Suite quy trình có giao dịch COMMIT riêng và làm thay đổi demo. Kết quả kiểm chứng, các đường nâng cấp và giới hạn kiểm thử được ghi tại [VALIDATION.md](database/tests/VALIDATION.md).

## 9. Hướng phát triển tiếp

1. Thống nhất chính sách học phí khi vắng, GPS và ma trận quyền duyệt/chốt.
2. Triển khai xác thực, quản lý phiên và kiểm tra quyền theo phạm vi dữ liệu.
3. Xây API và frontend theo từng luồng nghiệp vụ đã đặc tả.
4. Kết nối lưu trữ tệp, gửi email và cơ chế retry.
5. Kiểm thử tích hợp toàn hệ thống, tải đồng thời và triển khai môi trường trình diễn.
