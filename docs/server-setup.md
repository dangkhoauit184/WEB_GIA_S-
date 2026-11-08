# Chạy server PHP / CodeIgniter 4

Thực hiện từ thư mục gốc repo, ví dụ D:\WEB. Nếu đã chạy database trước đây, giữ nguyên container `educenter-mysql`, volume `educenter_mysql_data` và file riêng `config/server.env`.

## 1. Chuẩn bị database trước

Mở Docker Desktop, đợi Engine running, chạy:

```powershell
docker start educenter-mysql
docker ps --filter name=educenter-mysql
```

Máy mới chưa có container: làm [database/README.md](../database/README.md). Cài schema, routines/triggers theo install-order.json, seed-reference rồi check. Chỉ chạy init trên database rỗng. Migration PHP này không yêu cầu chạy lại schema hay import demo vào dữ liệu hiện có.

Có thể dùng `pwsh -File database/scripts/start-local.ps1` để tạo/bật DB local và nối mạng. Khi tạo mới, mật khẩu root sinh ngẫu nhiên được lưu riêng trong config/mysql.env; dùng giá trị đó khi mysql hỏi mật khẩu, không gửi lên GitHub. Container cũ vẫn dùng mật khẩu root cũ của bạn. File mysql.env không đổi mật khẩu của volume đã có.

Nếu chưa có tài khoản MySQL cho ứng dụng, vào container:

```powershell
docker exec -it educenter-mysql mysql -u root -p
```

Nhập mật khẩu root đã đặt lúc tạo MySQL. Trong dấu nhắc mysql>, chạy SQL sau, thay mật khẩu mẫu bằng mật khẩu riêng:

```sql
CREATE USER 'educenter_api'@'%' IDENTIFIED BY 'MAT_KHAU_API_BAN_TU_DAT';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'%';
EXIT;
```

Nếu tài khoản đã có thì dùng lại; không tạo lại/đặt lại mật khẩu tùy tiện. Host `%` cho phép kết nối từ container PHP trong mạng Docker; MySQL trên Windows vẫn chỉ public `127.0.0.1:3307`.

## 2. Cấu hình riêng

Chỉ sao chép nếu chưa có file:

```powershell
if (!(Test-Path config/server.env)) { Copy-Item config/server.env.example config/server.env }
notepad config/server.env
```

Điền DB_NAME=trung_tam_gia_su, DB_USER=educenter_api, DB_PASSWORD đúng ở bước 1. Đặt JWT_SECRET ngẫu nhiên riêng (ít nhất 32 ký tự). Có thể tạo khóa bằng PowerShell, rồi dán kết quả vào file riêng:

```powershell
[Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
```

`CI_ENVIRONMENT=production` giúp không lộ lỗi nội bộ. `JWT_EXPIRES_IN=8h`; CORS_ORIGINS liệt kê các origin frontend được phép, cách nhau bằng dấu phẩy. File mẫu không có secret mặc định. File riêng bị gitignore; không commit hoặc gửi mật khẩu lên GitHub.

DB_HOST=127.0.0.1 và DB_PORT=3307 dành cho PHP chạy trực tiếp trên Windows. Compose tự ghi đè thành `educenter-mysql:3306` trong mạng Docker. Không dùng địa chỉ 127.0.0.1:3307 để PHP container gọi MySQL container.

## 3. Chạy PHP qua Docker

```powershell
powershell -ExecutionPolicy Bypass -File backend/scripts/start.ps1
```

Lần đầu cần Internet để tải PHP/Apache, Composer, Mailpit và thư viện; có thể mất vài phút. Script nối DB hiện có vào mạng educenter-erp, build runtime, composer install rồi bật backend và SMTP local. Không tạo lại DB/volume hoặc chạy seed demo. Mailpit ở http://localhost:8025/ chỉ hứng thư trong máy; không gửi email thật.

Mở http://localhost:5000/ để đăng nhập, hoặc kiểm tra:

```powershell
Invoke-RestMethod http://localhost:5000/api/health
docker compose ps
```

Kết quả health phải có success=true, timeZone=+07:00, isolationLevel=READ-COMMITTED. Đây là kết nối PHP/MySQL, chưa chứng minh tất cả feature ERP đã hoàn thiện.

## 4. Tài khoản ứng dụng

Mật khẩu đăng nhập web khác mật khẩu root và mật khẩu educenter_api của MySQL. Nếu đã có tài khoản active, dùng username/mật khẩu cũ; bcrypt cũ tương thích PHP. Phiên JWT Node cần đăng nhập lại.

Chưa có tài khoản admin:

```powershell
powershell -ExecutionPolicy Bypass -File backend/scripts/bootstrap-admin.ps1
```

Script hỏi mật khẩu bằng SecureString và không ghi mật khẩu vào Git. Nếu admin đã tồn tại, mặc định từ chối ghi đè. Muốn chủ động đặt lại/kích hoạt tài khoản demo bị khóa:

```powershell
powershell -ExecutionPolicy Bypass -File backend/scripts/bootstrap-admin.ps1 -Username admin -Reset
```

CLI tương đương là `php spark educenter:bootstrap-admin`; có thể cấp INITIAL_ADMIN_PASSWORD, ADMIN_USERNAME, ADMIN_NAME, ADMIN_EMAIL, ADMIN_PHONE qua biến môi trường riêng. Nếu gọi CLI trực tiếp không cấp password thì prompt hiển thị ký tự; ưu tiên script PowerShell ở trên.

## 5. Những lần chạy sau

Mở Docker Desktop rồi chạy lại start.ps1. Nếu không thay code/dependency và container backend đã được tạo:

```powershell
docker start educenter-mysql
docker compose up -d backend
```

Dừng backend: `docker compose stop backend`. Xem log: `docker compose logs --tail 50 backend`. Đổi cổng local: `start.ps1 -Port 5001`, đồng thời sửa APP_BASE_URL/CORS_ORIGINS nếu sử dụng địa chỉ mới.

Nếu cổng 5000 bận, dừng terminal Node cũ bằng Ctrl+C trước. Với cổng MySQL 3307 bận, dùng `Get-NetTCPConnection -LocalPort 3307 -State Listen` xác định tiến trình; không tạo container DB mới hoặc xóa volume để chữa lỗi cổng.

## 6. Kiểm thử

```powershell
python database/tests/verify_structure.py
docker compose run --rm --no-deps backend composer test
pwsh -File backend/scripts/test.ps1
```

Script test.ps1 cần PowerShell 7 và Docker, tạo container MySQL/mạng/volume dùng một lần. API PHP và SQL regression chạy trên hai database riêng trong container tạm, sau đó dọn chúng. Không dùng database thật cho integration test.

Đã có PHP >=8.2 (khuyên 8.4), Composer, intl/mbstring/mysqli, có thể chạy trực tiếp:

```powershell
cd backend
composer install
php spark serve --host 127.0.0.1 --port 5000
composer test
```

Integration trực tiếp cần database đã init riêng có tên kết thúc `_test`, DB_HOST/PORT/NAME/USER/PASSWORD riêng và TEST_WITH_DB=1 trong môi trường; sau đó `composer test:db`. Test tự mở HTTP server tạm và dừng khi kết thúc.

## 7. Triển khai server nộp đồ án

Dockerfile có target application chứa mã nguồn và vendor đã cài từ composer.lock:

```powershell
docker build --target application -t educenter-php:release -f backend/Dockerfile .
```

Triển khai image này trên máy chủ có MySQL 8.4, truyền file env riêng với DB_HOST/PORT theo mạng server, CI_ENVIRONMENT=production, APP_BASE_URL là URL thật, JWT_SECRET riêng và CORS_ORIGINS đúng domain. Chỉ public Apache port80 qua reverse proxy HTTPS; document root đã cố định backend/public. Mount volume riêng cho backend/writable và cấp quyền www-data, sao lưu database/volume theo lịch. File config, .git, .cache và dữ liệu thử bị loại khỏi image. Không dùng spark serve cho server public.

Đây là cấu hình triển khai có thể dùng; repo chưa tự tạo VPS, domain, HTTPS hay gửi mail thật. Các công việc đó cùng các feature còn thiếu được theo dõi trong [is207-migration.md](is207-migration.md).

## 8. Cấu hình server bổ sung ở DB005

Sau start, kiểm tra không in secret và không ghi dữ liệu:

```powershell
pwsh -File backend/scripts/doctor.ps1
```

Script kiểm tra cả PHP/extension, production configuration, quyền writable bằng www-data, user DB khác root, timezone/isolation, migration005, SMTP TCP và HTTP headers. Chạy lại start sau khi đổi env để container nhận cấu hình mới.

Thư local: `MAIL_PROTOCOL=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, username/password/encryption để trống. Config Email mặc định các giá trị này để file server.env hiện có tiếp tục dùng được. Khi code BE gửi mail bằng service Email, thư hiện tại http://localhost:8025/. Chưa có worker/API gửi thông báo. Khi deploy, đặt MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM_EMAIL/FROM_NAME của nhà cung cấp SMTP thật vào env riêng; không dùng mật khẩu mẫu.

PHP ini và Apache config nằm trong config/php.ini, config/apache.conf. Giới hạn log Docker 3 file ×10MB; PHP không hiện lỗi ra trình duyệt. Thư mục writable không public, cần sao lưu riêng nếu sau này có tài liệu người dùng tải lên. Hiện check-in không thu ảnh/GPS.

Sao lưu DB (dừng các tác nhân ghi trước) và khôi phục kiểm chứng:

```powershell
docker compose stop backend
python database/scripts/backup.py backup
# Thay bằng đường dẫn SQL công cụ vừa trả, giữ cả file JSON đi kèm.
python database/scripts/backup.py verify "DUONG_DAN_SQL"
docker compose up -d backend
```

Verify dùng container MySQL riêng rồi dọn; không mở port hoặc sửa database nguồn. Dump chứa dữ liệu riêng: giữ ngoài Git, sao chép thêm ra nơi lưu riêng. Thứ tự backup → migrate → check đầy đủ ở [bàn giao DB/server](db-server-readiness.md).

## 9. Stack release có HTTPS

`compose.production.yaml` độc lập với compose local. Trên máy chủ đã có Docker, MySQL8.4 đã cài/migrate và nối mạng educenter-erp:

1. Sao chép config/deploy.env.example thành config/deploy.env; điền domain thật và tên container DB. Trỏ DNS domain về máy chủ, mở TCP80/443 và UDP443 nếu dùng HTTP/3. Tắt các dịch vụ khác đang chiếm80/443.
2. Tạo config/server.env riêng trên server: production, APP_BASE_URL=https://domain-cua-ban/, CORS_ORIGINS=https://domain-cua-ban, user/password DB riêng, JWT riêng và SMTP thật. Không sao chép config local sang server công khai mà giữ nguyên secret/domain/mail.
3. DB phải ở mạng educenter-erp. Nếu thiếu: `docker network create educenter-erp`, sau đó `docker network connect educenter-erp TEN_CONTAINER_DB` (chỉ connect nếu chưa nối).
4. Build/chạy:

```powershell
docker compose --env-file config/deploy.env -f compose.production.yaml config --quiet
docker compose --env-file config/deploy.env -f compose.production.yaml up -d --build
docker compose --env-file config/deploy.env -f compose.production.yaml ps
docker compose --env-file config/deploy.env -f compose.production.yaml exec -T --user www-data backend php scripts/doctor.php
```

Stack dùng image application chứa source/vendor, không bind repo; chỉ Caddy public80/443, backend nội bộ và writable volume. Không chạy `down -v` vì có dữ liệu runtime/certificate trong volume. Không chạy thêm compose local trên server public nếu không cần.

Caddy tự lấy/gia hạn chứng chỉ cho domain có DNS và cổng80/443 phù hợp, chuyển HTTP sang HTTPS. Trên máy hiện tại chưa có domain/VPS nên chỉ kiểm tra cấu hình, chưa cấp certificate hoặc deploy public. [Tài liệu HTTPS Caddy](https://caddyserver.com/docs/automatic-https).

Mailpit là công cụ thử SMTP, không nằm trong stack public; [cấu hình Docker chính thức](https://mailpit.axllent.org/docs/install/docker/). Biến SMTP ánh xạ vào [Email của CodeIgniter](https://codeigniter.com/user_guide/libraries/email.html).
