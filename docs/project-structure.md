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

## Cấu trúc mỗi Feature

- **Frontend**: mỗi feature có `<feature>.html`, `<feature>.css`, `<feature>.js`. Mỗi module có `<module>.module.js`; `modules/index.js` dành cho việc ghép các module.
- **Backend**: mỗi feature có 7 file `routes`, `controller`, `service`, `repository`, `model`, `validator`, `middleware`. Mỗi module có `<module>.routes.js`; `modules/index.js` đăng ký toàn bộ 6 module dưới `/api`.

## Phần dùng chung

- `frontend/src/shared/`: component, style, dịch vụ, phân quyền và tiện ích phía trình duyệt.
- `backend/src/shared/`: middleware xác thực JWT, xử lý lỗi HTTP/MySQL (`SIGNAL SQLSTATE '45000'`), phân quyền RBAC 6 vai trò và tiện ích phía server.
- `shared/`: hằng số vai trò (`roles.js`), quy tắc nghiệp vụ (`business-rules.js`), hợp đồng dữ liệu và danh sách module (`modules.json`) dùng cho cả hai phía.
- `src/config/` của mỗi phía: cấu hình ứng dụng và kết nối MySQL Pool.

## Tài liệu và tham chiếu

Giữ 4 README tại thư mục gốc, frontend, backend, database. Tài liệu chi tiết đặt trong `docs/`; `.github/PULL_REQUEST_TEMPLATE.md` là mẫu pull request duy nhất.

Demo HTML gốc chuyển tới `docs/demo/index.html`, không sửa nội dung. Chỉ giữ một bản SQL gốc tại `docs/reference/database/schema-original.sql` vì bộ kiểm tra còn dùng đối chiếu. File Word nghiệp vụ chỉ giữ một bản tại `docs/quy_trinh_nghiep_vu_v2.docx`. Hash các tham chiếu này nằm trong `docs/reference/preserved-files.json`.

Kiểm tra cấu trúc và bộ test:

```powershell
python database/tests/verify_structure.py
npm --prefix backend test
npm --prefix backend run test:db
```
