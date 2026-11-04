# EduCenter ERP — Quản lý trung tâm gia sư và đào tạo

Repository gồm thiết kế MySQL, tài liệu nghiệp vụ và khung frontend/backend theo **module → feature**.

**Trạng thái:** toàn bộ file mã nguồn frontend/backend được giữ rỗng theo yêu cầu. Chưa có server/API chạy được, chưa có lệnh `npm start`. Giao diện minh họa cũ được giữ riêng tại [docs/demo/index.html](docs/demo/index.html), dùng dữ liệu demo và localStorage.

## Cấu trúc chính

```text
.
├── README.md
├── backend/
│   ├── README.md
│   ├── server.js                      # File khung rỗng
│   └── src/{modules,shared,config}/
├── frontend/
│   ├── README.md
│   ├── index.html                     # File khung rỗng
│   ├── assets/
│   └── src/{modules,shared,config}/
├── config/server.env.example          # Cấu hình mẫu cho server tương lai
├── shared/modules.json                # Danh sách module và feature dùng chung
├── database/                          # SQL và công cụ chạy database hiện có
├── docs/                              # Kiến trúc, nghiệp vụ, báo cáo, demo tham khảo
└── .github/
    ├── PULL_REQUEST_TEMPLATE.md        # Một mẫu pull request
    └── workflows/pr-check.yml
```

Cả frontend và backend cùng dùng cấu trúc `src/modules/<module>/features/<feature>/`.

| Module chính | Chức năng |
| --- | --- |
| `administration` — Quản trị | `auth`, `permissions`, `staff`, `catalog`, `audit` |
| `people` — Hồ sơ | `students`, `teachers`, `tutors`, `parents` |
| `academic` — Đào tạo | `admissions`, `academic-affairs`, `classes`, `scheduling`, `attendance`, `leave-requests`, `makeup-classes` |
| `finance` — Tài chính và công lương | `tuition`, `timekeeping`, `payroll` |
| `communication` — Thông báo | `notifications` |
| `reporting` — Báo cáo | `dashboard` |

## Bắt đầu từ đâu?

1. Đọc [database/README.md](database/README.md), cài MySQL và PowerShell 7, tạo tài khoản triển khai, điền `database/.env`.
2. Database mới: chạy `init` rồi `check`. Database cũ: sao lưu, chạy `preflight` → `migrate` → `check`.
3. Muốn thử dữ liệu mẫu: dùng database riêng có hậu tố `_test`, theo hướng dẫn trong README database.
4. Xem demo bằng cách mở [docs/demo/index.html](docs/demo/index.html) trong trình duyệt.
5. Khi bắt đầu viết ứng dụng, đọc [backend/README.md](backend/README.md), [frontend/README.md](frontend/README.md) và [cấu hình server](docs/server-setup.md).

Các lệnh dưới đây chạy từ thư mục gốc repository, sau khi đã điền cấu hình database:

```powershell
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
python database/tests/verify_structure.py --scaffold-only
```

## Tài liệu

Chỉ giữ **4 README**: file này, backend, frontend và database; giữ **1 mẫu pull request** trong `.github`.

- [Hướng dẫn cấu trúc module/feature](docs/project-structure.md).
- [Chuẩn bị cấu hình server](docs/server-setup.md).
- [Kiến trúc và quy tắc nghiệp vụ](docs/architecture.md).
- [Tài liệu nghiệp vụ](docs/quy_trinh_nghiep_vu_v2.docx).
- [Thiết kế và nâng cấp database](docs/database-design.md).
- [Báo cáo rà soát database](docs/database-review.md).
- [Kết quả kiểm chứng database trước lần sắp xếp này](docs/database-validation.md).
- [Cách xem và giới hạn của demo](docs/frontend-demo.md).

Các mẫu `mau1`, `mau2`, bản sao demo và mã backend cũ đã được loại bỏ. SQL gốc được giữ tại `docs/reference/database/schema-original.sql` để đối chiếu migration; không dùng file đó để cài mới.
