# Chuẩn bị cấu hình server

Theo yêu cầu hiện tại, frontend/backend chỉ có file khung rỗng. `backend/server.js` chưa mở cổng HTTP, `database.config.js` chưa kết nối MySQL và chưa có `package.json`. Chưa có lệnh chạy API; không chạy `npm install` hoặc `npm start` ở giai đoạn này.

## Cấu hình mẫu

Thông số dự kiến được chuẩn bị ở [config/server.env.example](../config/server.env.example): HTTP `127.0.0.1:5000`, MySQL `127.0.0.1:3306`, database `trung_tam_gia_su`, charset `utf8mb4`, session `+07:00`.

Khi bắt đầu triển khai server, sao chép mẫu và điền mật khẩu riêng:

```powershell
Copy-Item config/server.env.example config/server.env
```

`config/server.env` đã được Git bỏ qua. File này là quy ước cấu hình chuẩn bị sẵn; chưa được ứng dụng tự động đọc. Người triển khai cần nạp cấu hình vào `backend/src/config/`, thêm thư viện và lệnh chạy khi được yêu cầu viết code.

## Thứ tự chuẩn bị

1. Cài và khởi động MySQL Server.
2. Làm theo [database/README.md](../database/README.md): cấp tài khoản triển khai, điền `database/.env`, chạy `init` → `check`, hoặc nâng cấp database cũ.
3. Tạo tài khoản API riêng, không dùng tài khoản migration cho ứng dụng.
4. Điền `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` trong cấu hình server cho khớp database đã chuẩn bị.
5. Sau khi có yêu cầu triển khai code, viết `database.config.js` → `app.js` → `server.js`, thêm lệnh chạy và kiểm tra kết nối.

Ví dụ cấp quyền ban đầu cho tài khoản API trên máy phát triển, thực hiện trong MySQL CLI bằng tài khoản quản trị; thay mật khẩu minh họa trước khi chạy:

```sql
CREATE USER 'educenter_api'@'127.0.0.1' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_API_RIENG';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'127.0.0.1';
```

Đây là quyền cơ sở cho luồng nghiệp vụ dự kiến; thu hẹp theo endpoint thực tế khi triển khai. Thay tên database/host tương ứng nếu cấu hình khác. Ứng dụng sau này cần đặt session `time_zone='+07:00'`, dùng `READ COMMITTED`, kiểm tra quyền người gọi và sử dụng truy vấn có tham số theo [thiết kế database](database-design.md).

Để xem giao diện minh họa ngay lúc này, mở [demo HTML](demo/index.html). Demo không cần server và chưa ghi dữ liệu vào MySQL.
