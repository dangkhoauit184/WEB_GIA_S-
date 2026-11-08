# Backend — EduCenter ERP RESTful API

Backend sử dụng **Node.js, Express, MySQL2 (Promise Pool), JSON Web Token (JWT) và BcryptJS**, được tổ chức theo cấu trúc **Module → Feature** đồng bộ với Frontend.

## 1. Cấu trúc thư mục

```text
backend/
├── package.json
├── server.js                          # Khởi động HTTP Server & đóng MySQL Pool an toàn
├── scripts/
│   └── bootstrap-admin.js             # Tạo hoặc mở khóa tài khoản Admin đầu tiên (băm mật khẩu)
├── tests/
│   ├── core.test.js                   # Kiểm thử khung lõi (Router, JWT, RBAC 6 vai trò, SQLSTATE 45000)
│   └── integration.test.js            # Kiểm thử tích hợp trực tiếp với MySQL
└── src/
    ├── app.js                         # Ghép middleware, /api/health, /api/* và xử lý lỗi tập trung
    ├── config/
    │   ├── app.config.js              # Nạp biến môi trường từ config/server.env
    │   └── database.config.js         # MySQL Connection Pool (+07:00, READ COMMITTED, Transaction helper)
    ├── modules/
    │   ├── index.js                   # Đăng ký 6 module chính dưới /api
    │   └── <module>/
    │       ├── <module>.routes.js
    │       └── features/<feature>/
    │           ├── <feature>.routes.js
    │           ├── <feature>.controller.js
    │           ├── <feature>.service.js
    │           ├── <feature>.repository.js
    │           ├── <feature>.model.js
    │           ├── <feature>.validator.js
    │           └── <feature>.middleware.js
    └── shared/
        ├── errors/http-error.js       # Lớp HttpError chuẩn hóa mã lỗi
        ├── middleware/
        │   ├── auth.middleware.js     # Xác thực JWT (signToken, verifyToken, authenticate)
        │   └── error.middleware.js    # Bắt lỗi Trigger/Procedure MySQL (SQLSTATE '45000'), 409, 404
        ├── permissions/rbac.js        # Phân quyền 6 vai trò & ma trận bảng role_group_function
        └── utils/helpers.js           # asyncHandler & sendSuccess
```

## 2. Cấu hình và chạy Server

1. Sao chép file cấu hình mẫu từ thư mục gốc repository:
   ```powershell
   Copy-Item config/server.env.example config/server.env
   ```
2. Cập nhật `config/server.env` khớp với MySQL của bạn (`DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_NAME=trung_tam_gia_su`, `DB_USER=educenter_api`, `DB_PASSWORD`, `JWT_SECRET`).
3. Cài đặt thư viện, khởi tạo Admin đầu tiên và chạy server:
   ```powershell
   cd backend
   npm.cmd install
   npm.cmd run bootstrap-admin
   npm.cmd run dev
   ```
4. Kiểm tra kết nối tại `http://127.0.0.1:5000/api/health`. Khi thành công, API trả về HTTP `200` kèm `db: { timeZone: "+07:00", isolationLevel: "READ-COMMITTED" }`.

## 3. Các nhóm API đã triển khai

### Xác thực & Tài khoản (`/api/administration/auth` hoặc `/api/auth`)
- `POST /login`: Đăng nhập, trả về JWT token, hồ sơ người dùng, vai trò (`ADMIN`, `GIAO_VU`, `GIAO_VIEN`, `GIA_SU`, `TRO_GIANG`, `PHU_HUYNH`) và ma trận quyền `permissions`.
- `GET /me`: Lấy thông tin tài khoản hiện tại kèm ma trận quyền.
- `PUT /change-password` (hoặc `POST /change-password`): Đổi mật khẩu (kiểm tra mật khẩu cũ, băm mật khẩu mới bằng `bcryptjs`).
- `POST /logout`: Ghi nhận nhật ký đăng xuất.

### Quản lý Hồ sơ (`/api/people`)
- **Phụ huynh (`/api/people/parents`)**: `GET /`, `GET /:id` (kèm danh sách con), `POST /` (tạo hồ sơ + tài khoản tùy chọn), `PUT /:id`.
- **Học viên (`/api/people/students`)**: `GET /` (tự động lọc theo con nếu người gọi là `PHU_HUYNH`), `GET /:id` (kèm lịch sử lớp học), `POST /`, `PUT /:id`, `DELETE /:id`.
- **Giáo viên & Gia sư (`/api/people/teachers`, `/api/people/tutors`)**:
  - `GET /`: Danh sách và lọc theo phân loại, môn học, cấp lớp, khu vực.
  - `GET /match`: Gợi ý ghép người dạy phù hợp theo **BR02** (`monHocId`, `capLopId`, `khuVucId`, `nganSachMax`, `thu`, `gioBatDau`, `gioKetThuc`).
  - `GET /:id`, `POST /`, `PUT /:id`: Quản lý trọn vẹn hồ sơ, chuyên môn (`nhan_su_mon`), khu vực (`nhan_su_khu_vuc`) và lịch rảnh (`nhan_su_lich_ranh`) trong giao dịch nguyên tử.

### Quản lý Đào tạo (`/api/academic`)
- **Lớp học & Ghi danh (`/api/academic/classes`)**:
  - `GET /`, `GET /:id`, `POST /`, `PUT /:id`: Quản lý lớp học (`kem_1_1`, `kem_1_2`, `nhom`).
  - `GET /:id/enrollments`, `POST /:id/enrollments`, `PUT /:id/enrollments/:enrollmentId`: Ghi danh học viên vào lớp (tự động kiểm tra sĩ số tối đa qua Trigger/Procedure `sp_kiem_tra_si_so`).
- **Xếp lịch & Sinh buổi học (`/api/academic/scheduling`)**:
  - `GET /weekly`, `POST /weekly`: Quản lý lịch tuần cố định (`lich_hoc`).
  - `GET /sessions`: Tra cứu buổi học theo lớp, khoảng ngày, người dạy, trạng thái.
  - `POST /generate-sessions`: Gọi `CALL sp_sinh_buoi_hoc(lopId, tuNgay, denNgay)` (**BR03**) để sinh chuỗi buổi học và tự động phân công người dạy chính, đồng thời kiểm tra chống trùng lịch (**BR01**).
  - `POST /sessions/:id/assign`, `PUT /sessions/:id`: Phân công trợ giảng/người dạy và cập nhật/dời/hủy buổi học.

## 4. Kiểm thử

```powershell
# Chạy kiểm thử Core (không cần MySQL đang mở)
npm.cmd --prefix backend test

# Chạy kiểm thử tích hợp đầu-cuối trên MySQL
npm.cmd --prefix backend run test:db
```
