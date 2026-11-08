# Hướng dẫn cấu hình và chạy Backend Server

Tài liệu này hướng dẫn thiết lập tài khoản MySQL cho Backend API (`educenter_api`), cấu hình `config/server.env`, khởi chạy server và xử lý các lỗi thường gặp.

## 1. Tạo tài khoản MySQL cho Backend (`educenter_api`)

Sau khi đã khởi tạo database `trung_tam_gia_su` bằng tài khoản triển khai (`educenter_migrator`) theo [database/README.md](../database/README.md), đăng nhập MySQL bằng tài khoản quản trị (`root`) và tạo tài khoản riêng cho ứng dụng API:

```sql
CREATE USER IF NOT EXISTS 'educenter_api'@'127.0.0.1' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_API_RIENG';
CREATE USER IF NOT EXISTS 'educenter_api'@'localhost' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_API_RIENG';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'localhost';
FLUSH PRIVILEGES;
```

- Tài khoản `educenter_api` chỉ có quyền thao tác dữ liệu (`SELECT, INSERT, UPDATE, DELETE`) và gọi Stored Procedure/Function (`EXECUTE`), không có quyền `DROP` hay `ALTER` cấu trúc bảng.

## 2. Điền cấu hình `config/server.env`

Sao chép từ file mẫu nếu chưa có:

```powershell
Copy-Item config/server.env.example config/server.env
```

Mở `config/server.env` và điền thông số kết nối thực tế (ví dụ MySQL chạy trên cổng `3307`):

```dotenv
NODE_ENV=development
HOST=127.0.0.1
PORT=5000
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=trung_tam_gia_su
DB_USER=educenter_api
DB_PASSWORD=MAT_KHAU_API_VUA_DAT
DB_CHARSET=utf8mb4
DB_TIMEZONE=+07:00
JWT_SECRET=CHUOI_BI_MAT_JWT_RIENG_CUA_BAN
JWT_EXPIRES_IN=8h
```

*(File `config/server.env` đã được `.gitignore` bảo vệ, không bị đẩy lên Git).*

## 3. Khởi tạo Admin đầu tiên và chạy Server

```powershell
cd backend
npm.cmd install
npm.cmd run bootstrap-admin
npm.cmd run dev
```

- `npm.cmd run bootstrap-admin`: Tạo (hoặc kích hoạt lại) tài khoản Quản trị viên đầu tiên (`username: admin`, mật khẩu mặc định `Admin@123456` hoặc đặt qua biến môi trường `ADMIN_PASSWORD`) với mật khẩu được băm bằng `bcryptjs`.
- Mở `http://127.0.0.1:5000/api/health` để xác nhận. Kết quả chuẩn:
  ```json
  {
    "success": true,
    "message": "EduCenter ERP API & MySQL đang hoạt động bình thường.",
    "db": {
      "timeZone": "+07:00",
      "isolationLevel": "READ-COMMITTED"
    }
  }
  ```

## 4. Xử lý lỗi thường gặp

| Triệu chứng / Mã lỗi | Nguyên nhân | Cách khắc phục |
| --- | --- | --- |
| `ECONNREFUSED 127.0.0.1:3307` | Dịch vụ MySQL hoặc container Docker chưa chạy, hoặc sai cổng `DB_PORT` | Kiểm tra dịch vụ MySQL / Docker container và đối chiếu `DB_PORT` trong `config/server.env` |
| `ER_ACCESS_DENIED_ERROR` | Sai `DB_USER` / `DB_PASSWORD` hoặc chưa `GRANT` cho host `127.0.0.1` | Chạy lại lệnh `CREATE USER` và `GRANT` cho cả `'educenter_api'@'127.0.0.1'` và `'educenter_api'@'localhost'` |
| `ER_DBACCESS_DENIED_ERROR` | Tài khoản `educenter_api` chưa được cấp quyền trên `trung_tam_gia_su.*` | Chạy `GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.*` |
| `PROCEDURE ... does not exist` khi gọi API xếp lịch | Chưa chạy `init` / `migrate` hoặc thiếu quyền `EXECUTE` | Chạy `pwsh -File database/scripts/run.ps1 -Action check` và cấp quyền `EXECUTE` cho `educenter_api` |
| HTTP `400 BUSINESS_RULE_VIOLATION` | Dữ liệu vi phạm quy tắc nghiệp vụ trong Trigger/Procedure (`SIGNAL SQLSTATE '45000'`) | Đọc trường `message` tiếng Việt trả về trong JSON để biết quy tắc cụ thể bị vi phạm (VD: vượt sĩ số, trùng lịch dạy) |
| HTTP `403 ACCOUNT_LOCKED` khi đăng nhập tài khoản demo | Tài khoản trong `002_demo.sql` mặc định ở trạng thái `locked` | Chạy `npm.cmd run bootstrap-admin` để mở khóa `admin` với mật khẩu băm mới |
