# Hướng dẫn cấu hình và chạy Backend Server

Tài liệu này hướng dẫn thiết lập tài khoản MySQL cho Backend API (`educenter_api`), cấu hình `config/server.env`, khởi chạy server và xử lý các lỗi thường gặp.

Thực hiện các lệnh PowerShell từ thư mục gốc repository (`D:\WEB` trong ví dụ). Với cách chạy trên Windows bên dưới, backend kết nối tới MySQL Docker qua `127.0.0.1:3307`.

## Chuẩn bị MySQL Docker

Mở Docker Desktop và đợi trạng thái **Engine running**. Nếu đã tạo container theo hướng dẫn database, bật lại bằng lệnh:

```powershell
docker start educenter-mysql
docker ps --filter name=educenter-mysql
```

Container đang hoạt động sẽ có trạng thái `Up` và ánh xạ `127.0.0.1:3307->3306/tcp`.

Trên máy mới, tạo container lần đầu bằng khối lệnh sau. Thay `MAT_KHAU_ROOT_BAN_TU_DAT` bằng mật khẩu quản trị MySQL riêng trước khi chạy:

```powershell
docker run -d `
  --name educenter-mysql `
  --restart unless-stopped `
  -p 127.0.0.1:3307:3306 `
  -e MYSQL_ROOT_PASSWORD=MAT_KHAU_ROOT_BAN_TU_DAT `
  --mount "type=volume,source=educenter_mysql_data,target=/var/lib/mysql" `
  --mount "type=bind,source=D:\WEB\database,target=/sql,readonly" `
  mysql:8.4 `
  --character-set-server=utf8mb4 `
  --collation-server=utf8mb4_unicode_ci `
  --default-time-zone=+07:00
```

Nếu clone repository vào thư mục khác, thay đường dẫn `D:\WEB\database` trong bind mount. Volume `educenter_mysql_data` lưu dữ liệu MySQL. Thư mục `/sql` trong container chứa các file SQL của repository.

Xem tiến trình khởi động bằng `docker logs -f educenter-mysql`. Khi thấy `ready for connections` ở cổng `3306`, nhấn `Ctrl+C` để thoát phần xem log. Chuẩn bị schema và danh mục theo [database/README.md](../database/README.md) trước khi tạo tài khoản API.

## 1. Tạo tài khoản MySQL cho Backend (`educenter_api`)

Sau khi đã khởi tạo database `trung_tam_gia_su`, đăng nhập MySQL trong container bằng tài khoản quản trị `root`:

```powershell
docker exec -it educenter-mysql mysql --default-character-set=utf8mb4 -u root -p
```

Nhập mật khẩu root đã đặt khi tạo container. Tại dấu nhắc `mysql>`, thay mật khẩu mẫu trong SQL dưới đây bằng mật khẩu riêng cho tài khoản API rồi chạy:

```sql
CREATE USER 'educenter_api'@'%'
IDENTIFIED BY 'THAY_BANG_MAT_KHAU_API_RIENG';

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE
ON trung_tam_gia_su.*
TO 'educenter_api'@'%';

EXIT;
```

Docker chuyển tiếp kết nối từ Windows qua mạng container. Host `%` cho tài khoản API nhận kết nối này, còn cổng publish được giới hạn ở `127.0.0.1` trên Windows. Các quyền được cấp bao gồm thao tác dữ liệu và thực thi procedure/function trên `trung_tam_gia_su`.

Nếu tài khoản đã tồn tại, kiểm tra các host được khai báo bằng `SELECT User, Host FROM mysql.user WHERE User = 'educenter_api';`. Đặt lại mật khẩu hoặc cấp quyền cho đúng tài khoản sau khi xác định host cần dùng.

## 2. Điền cấu hình `config/server.env`

Sao chép từ file mẫu nếu chưa có:

```powershell
if (!(Test-Path config/server.env)) {
  Copy-Item config/server.env.example config/server.env
}
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

Điền `DB_PASSWORD` khớp với mật khẩu đã đặt cho `educenter_api` và tự đặt `JWT_SECRET` riêng. File `config/server.env` được lưu cục bộ qua quy tắc `.gitignore`.

## 3. Khởi tạo Admin đầu tiên và chạy Server

```powershell
npm.cmd --prefix backend install
```

Đặt mật khẩu cho Admin trong PowerShell bằng lệnh sau. Nhập mật khẩu khi được hỏi rồi nhấn Enter:

```powershell
$adminSecret = Read-Host "Dat mat khau Admin" -AsSecureString
```

Sau khi nhập xong, chạy tiếp:

```powershell
$env:ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecret).Password
npm.cmd --prefix backend run bootstrap-admin
Remove-Item Env:ADMIN_PASSWORD
```

Lệnh bootstrap tạo hoặc cập nhật tài khoản `admin` và băm mật khẩu bằng `bcryptjs`. Khi cập nhật tài khoản có sẵn, mật khẩu và nhóm quyền của tài khoản đó sẽ được đặt lại theo cấu hình bootstrap.

Khởi chạy backend và giữ cửa sổ này mở trong lúc sử dụng:

```powershell
npm.cmd --prefix backend run dev
```

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
| Docker báo `ports are not available` hoặc `Only one usage of each socket address` ở cổng `3307` | Một tiến trình khác đang dùng cổng publish | Dùng `Get-NetTCPConnection -LocalPort 3307 -State Listen` để lấy `OwningProcess`, rồi `Get-Process -Id <PID>` để xác định chương trình. Dừng đúng dịch vụ MySQL kiểm thử bằng lệnh shutdown trước khi bật container, hoặc chọn cổng publish khác và cập nhật `DB_PORT` tương ứng |
| `ECONNREFUSED 127.0.0.1:3307` | Dịch vụ MySQL hoặc container Docker chưa chạy, hoặc sai cổng `DB_PORT` | Kiểm tra dịch vụ MySQL / Docker container và đối chiếu `DB_PORT` trong `config/server.env` |
| `ER_ACCESS_DENIED_ERROR` | Sai `DB_USER` / `DB_PASSWORD` hoặc host của tài khoản chưa khớp kết nối qua Docker | Kiểm tra tài khoản `'educenter_api'@'%'`, mật khẩu và quyền được cấp trong MySQL Docker, sau đó đối chiếu `config/server.env` |
| `ER_DBACCESS_DENIED_ERROR` | Tài khoản `educenter_api` chưa được cấp quyền trên `trung_tam_gia_su.*` | Chạy `GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.*` |
| `PROCEDURE ... does not exist` khi gọi API xếp lịch | Chưa chạy `init` / `migrate` hoặc thiếu quyền `EXECUTE` | Chạy `pwsh -File database/scripts/run.ps1 -Action check` và cấp quyền `EXECUTE` cho `educenter_api` |
| HTTP `400 BUSINESS_RULE_VIOLATION` | Dữ liệu vi phạm quy tắc nghiệp vụ trong Trigger/Procedure (`SIGNAL SQLSTATE '45000'`) | Đọc trường `message` tiếng Việt trả về trong JSON để biết quy tắc cụ thể bị vi phạm (VD: vượt sĩ số, trùng lịch dạy) |
| HTTP `403 ACCOUNT_LOCKED` khi đăng nhập tài khoản demo | Tài khoản trong `002_demo.sql` mặc định ở trạng thái `locked` | Chạy `npm.cmd run bootstrap-admin` để mở khóa `admin` với mật khẩu băm mới |
