# Quy ước module và feature

Danh sách chuẩn dùng chung ở `shared/modules.json`. Backend PHP dùng PascalCase theo PSR-4: `backend/app/Modules/<Module>/Features/<Feature>/`. Frontend HTML/CSS/JS giữ `frontend/src/modules/<module>/features/<feature>/`.

| Module | Feature |
| --- | --- |
| Administration | Auth, Permissions, Staff, Catalog, Audit |
| People | Students, Teachers, Tutors, Parents |
| Academic | Admissions, AcademicAffairs, Classes, Scheduling, Attendance, LeaveRequests, MakeupClasses |
| Finance | Tuition, Timekeeping, Payroll |
| Communication | Notifications |
| Reporting | Dashboard |

Trong mỗi feature PHP có Controller, Service, Repository, Model, Validator, Filter và Routes.php. Thêm Views/ khi có màn hình MVC. Controller nhận HTTP; service xử lý nghiệp vụ; repository chứa truy vấn; model định dạng dữ liệu; validator kiểm tra đầu vào; filter xác thực/quyền. Không đưa SQL trực tiếp vào view.

Route HTTP giữ tên lowercase/kebab-case, ví dụ `/api/academic/leave-requests`; không thay URL theo PascalCase của PHP. `app/Config/Routes.php` nạp Routes.php của từng feature từ manifest và tắt auto-routing. Feature chưa làm chỉ có status `planned`, các file khung có TODO. Tutors dùng chung triển khai hồ sơ với Teachers.

`backend/app/Shared/` chứa Access (JWT/RBAC/scope), Store (MySQL session/transaction) và lớp JSON/error. `backend/app/Config/` giữ cấu hình CodeIgniter. `backend/public/` là document root; Composer và config riêng nằm ngoài public.

`frontend/src/shared/` giữ chỗ cho component/style/dịch vụ; `shared/` ở gốc giữ manifest và hằng số JS cho phía trình duyệt. Không cần cài Node để chạy backend PHP.

Giữ 4 README và 1 PR template. Tài liệu khác đặt trong docs. Bản demo, SQL gốc và Word gốc có hash trong docs/reference/preserved-files.json; không sửa các bản tham khảo đó.

```powershell
python database/tests/verify_structure.py
docker compose run --rm --no-deps backend composer test
pwsh -File backend/scripts/test.ps1
```
