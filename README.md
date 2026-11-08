# EduCenter ERP — Quản lý trung tâm gia sư và đào tạo

Repository gồm thiết kế MySQL (`003_attribute_review`), tài liệu nghiệp vụ, Backend RESTful API (Node.js/Express + MySQL2 + JWT + RBAC 6 vai trò) và khung Frontend theo kiến trúc **module → feature**.

**Trạng thái hiện tại:**
- **Database (`database/`)**: Đã hoàn thiện schema 36 bảng, 25 triggers, 12 stored procedures/functions, seeders và bộ kiểm thử tự động.
- **Backend (`backend/`)**: Đã triển khai khung lõi Server + kết nối MySQL Pool (`+07:00`, `READ-COMMITTED`), JWT Auth, phân quyền RBAC 6 vai trò, xử lý lỗi MySQL `SIGNAL SQLSTATE '45000'`, cùng các nhóm API nghiệp vụ: Đăng nhập (`auth`), Phụ huynh (`parents`), Học viên (`students`), Người dạy & Gợi ý ghép BR02 (`teachers`, `tutors`), Lớp học & Ghi danh (`classes`), Xếp lịch & Sinh buổi học tự động BR03 (`scheduling`).
- **Frontend (`frontend/`)**: Đang ở dạng khung module/feature để phát triển giao diện từ Figma. Giao diện minh họa cũ được lưu tại [docs/demo/index.html](docs/demo/index.html).

## Cấu trúc chính

```text
.
├── README.md
├── backend/
│   ├── README.md
│   ├── package.json
│   ├── server.js                      # Entry point khởi động HTTP server (cổng 5000)
│   ├── scripts/bootstrap-admin.js     # Khởi tạo/mở khóa tài khoản Admin đầu tiên
│   ├── tests/                         # Bộ kiểm thử Core & Tích hợp MySQL
│   └── src/{modules,shared,config}/
├── frontend/
│   ├── README.md
│   ├── index.html
│   ├── assets/
│   └── src/{modules,shared,config}/
├── config/server.env.example          # Cấu hình mẫu cho Backend Server
├── shared/                            # Danh sách module, hằng số Role & Business Rules
├── database/                          # SQL và công cụ vận hành database
├── docs/                              # Kiến trúc, nghiệp vụ, báo cáo, demo tham khảo
└── .github/
    ├── PULL_REQUEST_TEMPLATE.md
    └── workflows/pr-check.yml
```

Cả frontend và backend cùng dùng cấu trúc `src/modules/<module>/features/<feature>/`.

| Module chính                        | Chức năng                                                                                                   |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `administration` — Quản trị         | `auth`, `permissions`, `staff`, `catalog`, `audit`                                                          |
| `people` — Hồ sơ                    | `students`, `teachers`, `tutors`, `parents`                                                                 |
| `academic` — Đào tạo                | `admissions`, `academic-affairs`, `classes`, `scheduling`, `attendance`, `leave-requests`, `makeup-classes` |
| `finance` — Tài chính và công lương | `tuition`, `timekeeping`, `payroll`                                                                         |
| `communication` — Thông báo         | `notifications`                                                                                             |
| `reporting` — Báo cáo               | `dashboard`                                                                                                 |

## Bắt đầu nhanh

1. **Khởi tạo Database**: Đọc [database/README.md](database/README.md), điền `database/.env`, chạy `init` và `check`:
   ```powershell
   pwsh -File database/scripts/run.ps1 -Action init
   pwsh -File database/scripts/run.ps1 -Action check
   ```
2. **Cấu hình và chạy Backend Server**: Đọc [backend/README.md](backend/README.md) và [docs/server-setup.md](docs/server-setup.md):
   ```powershell
   Copy-Item config/server.env.example config/server.env
   # Điền DB_HOST=127.0.0.1, DB_PORT=3307, DB_NAME=trung_tam_gia_su, DB_USER=educenter_api, DB_PASSWORD và JWT_SECRET
   cd backend
   npm.cmd install
   npm.cmd run bootstrap-admin
   npm.cmd run dev
   ```
3. **Kiểm tra sức khỏe hệ thống & chạy bộ test**:
   ```powershell
   irm http://127.0.0.1:5000/api/health
   npm.cmd --prefix backend test
   npm.cmd --prefix backend run test:db
   python database/tests/verify_structure.py
   ```

## Tài liệu

Chỉ giữ **4 README**: file này, backend, frontend và database; giữ **1 mẫu pull request** trong `.github`.

- [Hướng dẫn cấu trúc module/feature](docs/project-structure.md).
- [Hướng dẫn cấu hình và chạy server](docs/server-setup.md).
- [Kiến trúc và quy tắc nghiệp vụ](docs/architecture.md).
- [Tài liệu nghiệp vụ](docs/quy_trinh_nghiep_vu_v2.docx).
- [Thiết kế và nâng cấp database](docs/database-design.md).
- [Báo cáo rà soát database](docs/database-review.md).
- [Kết quả kiểm chứng database](docs/database-validation.md).
- [Cách xem và giới hạn của demo](docs/frontend-demo.md).
