# Quy ước tổ chức module và feature

Cả frontend và backend dùng `src/modules/<module>/features/<feature>/`. Module gom một nhóm nghiệp vụ lớn; feature là một chức năng cụ thể. Danh sách chuẩn nằm tại [shared/modules.json](../shared/modules.json).

| Module | Phạm vi | Feature |
| --- | --- | --- |
| `administration` | Tài khoản, quyền, nhân sự vận hành, cấu hình, kiểm toán | auth, permissions, staff, catalog, audit |
| `people` | Hồ sơ các bên tham gia | students, teachers, tutors, parents |
| `academic` | Tuyển sinh, lớp và vận hành đào tạo | admissions, academic-affairs, classes, scheduling, attendance, leave-requests, makeup-classes |
| `finance` | Thu học phí, công và chi trả | tuition, timekeeping, payroll |
| `communication` | Gửi và theo dõi thông báo | notifications |
| `reporting` | Tổng quan và báo cáo | dashboard |

21 feature gồm 18 feature có sẵn và 3 khung bổ sung `admissions`, `catalog`, `audit`, để tuyển sinh, danh mục và kiểm toán có vị trí riêng. Giữ cùng tên và cùng module ở cả hai phía.

## File khung

Frontend: mỗi feature có `<feature>.html`, `<feature>.css`, `<feature>.js`. Mỗi module có `<module>.module.js`; `modules/index.js` dành cho việc ghép các module sau này.

Backend: mỗi feature có 7 file `routes`, `controller`, `service`, `repository`, `model`, `validator`, `middleware`. Mỗi module có `<module>.routes.js`; `modules/index.js` dành cho việc đăng ký module sau này.

Toàn bộ file mã nguồn trên đều rỗng. README mô tả trách nhiệm dự kiến, không có chức năng ứng dụng đã triển khai. `config/server.env.example` là cấu hình mẫu, chưa được code nào nạp.

## Phần dùng chung

- `frontend/src/shared/`: component, style, dịch vụ, phân quyền và tiện ích phía trình duyệt.
- `backend/src/shared/`: middleware, lỗi, phân quyền và tiện ích phía server.
- `shared/`: hằng số, hợp đồng dữ liệu và danh sách module dùng cho cả hai phía.
- `src/config/` của mỗi phía: nơi đặt cấu hình ứng dụng sau này.

## Tài liệu và tham chiếu

Giữ 4 README tại thư mục gốc, frontend, backend, database. Tài liệu chi tiết đặt trong `docs/`; `.github/PULL_REQUEST_TEMPLATE.md` là mẫu pull request duy nhất.

Demo HTML gốc chuyển tới `docs/demo/index.html`, không sửa nội dung. Chỉ giữ một bản SQL gốc tại `docs/reference/database/schema-original.sql` vì bộ kiểm tra còn dùng đối chiếu. File Word nghiệp vụ chỉ giữ một bản tại `docs/quy_trinh_nghiep_vu_v2.docx`. Hash các tham chiếu này nằm trong `docs/reference/preserved-files.json`.

Các bản sao frontend/backend cũ, `mau1`, `mau2`, README gốc lỗi thời, SQL procedures cũ và mẫu `database.config` không còn được công cụ triển khai sử dụng đã được bỏ. Khi cần lịch sử, xem Git.

Kiểm tra sau khi thay đổi cấu trúc:

```powershell
python database/tests/verify_structure.py --scaffold-only
```

Bộ kiểm tra xác nhận cấu trúc hai phía khớp danh sách chung, file mã nguồn rỗng, số README/mẫu PR và tham chiếu SQL còn hợp lệ. CI dùng cùng lệnh kiểm tra, đồng thời chạy bộ kiểm thử MySQL.
