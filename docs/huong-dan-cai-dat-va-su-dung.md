# Hướng dẫn cài đặt và sử dụng EduCenter ERP cho thành viên nhóm

Cập nhật 16/11/2026 (cài đặt, pull/push và bộ demo đủ sáu vai trò), áp dụng schema `005_operations_ready`. Đây là tài liệu thao tác duy nhất cho máy mới, máy đang dùng, chia sẻ dữ liệu, kiểm thử và triển khai. Đặc tả và thiết kế nằm trong [Thông tin đồ án](thong-tin-do-an.md).

Đọc theo thứ tự: **cài công cụ → lấy repo → tạo cấu hình riêng → bật MySQL → cài DB mới hoặc phục hồi DB → cấp user API → bật PHP → tạo tài khoản web → kiểm tra**. Máy đã có dữ liệu đi thẳng đến [chạy hằng ngày](#hang-ngay) hoặc [nâng cấp DB](#db-cu).

## Mục lục

1. [Công cụ và Docker trên Windows](#cong-cu)
2. [Lấy mã nguồn](#tai-repo)
3. [Cấu hình và các loại mật khẩu](#cau-hinh)
4. [Bật MySQL Docker](#mysql)
5. [Cài DB mới hoặc nhận DB từ đồng đội](#db-moi)
6. [Cấp tài khoản DB và chạy PHP](#chay-web)
7. [Đăng nhập và sử dụng bản hiện có](#tai-khoan)
8. [Bật/tắt, cập nhật và nâng cấp DB](#hang-ngay)
   - [Pull, commit và push code](#pull-push-code)
   - [Lưu ý phối hợp 3 người và bàn giao PR](#phoi-hop-nhom)
9. [Backup, restore và chia sẻ](#chia-se)
10. [Kiểm thử và cách chạy PHP trực tiếp](#kiem-thu)
11. [Triển khai public](#deploy)
12. [Lỗi thường gặp](#xu-ly-loi)

<a id="cong-cu"></a>
## 1. Cài công cụ trên Windows

Luồng dưới đây dùng Windows, PowerShell 7 và Docker Desktop chạy Linux containers qua WSL 2. PHP/Apache, Composer, MySQL và SMTP thử nằm trong Docker; không cần cài XAMPP hoặc MySQL Server riêng để chạy theo luồng này.

| Công cụ | Nơi tải | Kiểm tra sau cài |
| --- | --- | --- |
| Git for Windows | [Git](https://git-scm.com/downloads/win) | `git --version` |
| PowerShell 7 | [Microsoft](https://learn.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-windows) | `pwsh --version` |
| Docker Desktop | [Docker Windows](https://docs.docker.com/desktop/setup/install/windows-install/) | `docker version`, `docker compose version` |
| Python 3 | [Python Windows](https://www.python.org/downloads/windows/) | `python --version` |

Python dùng cho kiểm tra cấu trúc/backup/restore, không chạy backend. Trình soạn thảo tùy chọn, ví dụ VS Code. Mở terminal mới sau cài để cập nhật PATH. Lệnh `pwsh` là PowerShell 7; `powershell` thường là Windows PowerShell 5.1.

### 1.1. Chuẩn bị WSL 2

Nếu Docker báo thiếu **Virtual Machine Platform** hoặc WSL, mở PowerShell bằng **Run as administrator**, chạy:

```powershell
wsl --install
```

Khởi động lại máy khi được yêu cầu, rồi kiểm tra/cập nhật:

```powershell
wsl --update
wsl --status
```

Tham khảo [Microsoft WSL](https://learn.microsoft.com/en-us/windows/wsl/install). Nếu Docker báo chưa bật virtualization, bật tính năng ảo hóa trong BIOS/UEFI theo hướng dẫn của hãng máy.

### 1.2. Cài và mở Docker

Chọn bộ cài phù hợp CPU: x86_64/AMD64 cho máy Intel/AMD x64, ARM64 cho Windows ARM. Trong trình cài, có thể chọn **Per-user installation + WSL 2** sau khi các tính năng WSL cần thiết đã bật. Chạy Docker Desktop và chờ **Engine running**; không chỉ mở cửa sổ cài đặt.

```powershell
docker info
docker compose version
```

Nếu thấy lỗi không kết nối Docker daemon, mở Docker Desktop trước rồi chạy lại. Không cần tạo container bằng nút giao diện; script dự án tạo hoặc bật đúng container.

<a id="tai-repo"></a>
## 2. Lấy mã nguồn của nhóm

Repo riêng tư cần được chủ repo mời làm collaborator và đăng nhập GitHub trên máy của bạn. Ví dụ thư mục làm việc `D:\WEB`:

```powershell
git clone https://github.com/dangkhoauit184/WEB_GIA_S-.git D:\WEB
cd D:\WEB
git switch develop
git pull origin develop
```

Nếu đã clone thì vào đúng thư mục, kiểm tra `git status`, xử lý/lưu thay đổi cá nhân rồi mới pull. Có thể dùng đường dẫn khác; mọi lệnh trong tài liệu chạy từ **thư mục gốc repo**, nơi có compose.yaml, trừ phần ghi rõ chuyển thư mục. Mở terminal PowerShell 7 tại đó. Chạy từng lệnh theo thứ tự và xem kết quả trước khi chạy lệnh tiếp theo.

<a id="cau-hinh"></a>
## 3. Tạo cấu hình riêng và hiểu các loại mật khẩu

| Thông tin | Dùng vào việc gì | Nơi lưu |
| --- | --- | --- |
| MySQL root | Cài schema, cấp quyền, backup/restore local | Container MySQL; máy tạo mới có config/mysql.env riêng |
| User DB `educenter_api` | PHP kết nối DB với quyền DML/EXECUTE | config/server.env riêng |
| Tài khoản web | Đăng nhập tại localhost:5000 | account/users trong DB, mật khẩu chỉ lưu hash |
| JWT_SECRET | Ký phiên đăng nhập PHP, không phải mật khẩu người dùng | config/server.env riêng |
| SMTP/merchant | Kết nối dịch vụ gửi mail/thanh toán thật về sau | Cấu hình riêng trên môi trường tương ứng |

Không gửi mật khẩu của máy thành viên lên GitHub. Không dùng mật khẩu web thay cho DB hoặc ngược lại. Mỗi đồng đội tạo cấu hình/mật khẩu của máy mình; không cần biết mật khẩu root của người khác.

Chỉ sao chép mẫu nếu chưa có file:

```powershell
if (!(Test-Path config/server.env)) { Copy-Item config/server.env.example config/server.env }
notepad config/server.env
```

Điền DB_USER=educenter_api, DB_NAME=trung_tam_gia_su. Đặt **DB_PASSWORD riêng** và **JWT_SECRET ngẫu nhiên tối thiểu32 ký tự**. Có thể chạy lệnh sau hai lần để tạo hai chuỗi riêng rồi dán vào từng trường:

```powershell
[Guid]::NewGuid().ToString('N')
```

Giữ các giá trị local còn lại theo mẫu:

| Biến | Giá trị/ý nghĩa |
| --- | --- |
| CI_ENVIRONMENT | production, không hiện lỗi nội bộ ra web |
| APP_BASE_URL | http://localhost:5000/ |
| DB_HOST / DB_PORT | 127.0.0.1 / 3307 khi PHP chạy trên Windows; Compose ghi đè thành educenter-mysql / 3306 |
| DB_NAME / DB_USER / DB_PASSWORD | trung_tam_gia_su / educenter_api / mật khẩu DB đã tự đặt |
| DB_CHARSET / DB_TIMEZONE | utf8mb4 / +07:00 |
| JWT_SECRET / JWT_EXPIRES_IN | Chuỗi riêng / 8h |
| CORS_ORIGINS | http://localhost:5000,http://127.0.0.1:5000 |
| MAIL_PROTOCOL / MAIL_HOST / MAIL_PORT | smtp / mailpit / 1025 |
| MAIL_USERNAME / MAIL_PASSWORD / MAIL_ENCRYPTION | Để trống với SMTP local |
| MAIL_FROM_EMAIL / MAIL_FROM_NAME | noreply@educenter.test / EduCenter |

File env dùng `KEY=value`, không thêm `export` hoặc comment cuối dòng; công cụ DB đọc giá trị nguyên văn. Không sao chép đè cấu hình đang dùng. Các file config/server.env, config/mysql.env, config/deploy.env và database/.env không được commit.

<a id="mysql"></a>
## 4. Bật hoặc tạo MySQL local

```powershell
pwsh -File database/scripts/start-local.ps1
```

Hành vi script:

- Đã có educenter-mysql: dùng lại và bật nếu đang dừng, không thay mật khẩu/dữ liệu.
- Chưa có container và volume: tạo MySQL 8.4, volume educenter_mysql_data, publish **127.0.0.1:3307 → container3306**; sinh root password riêng vào config/mysql.env.
- Chỉ còn volume nhưng không có container: dừng để tránh khởi tạo nhầm; xem phần xử lý lỗi.
- Nối mạng educenter-erp, chờ MySQL sẵn sàng. Script **chưa cài schema**, không migrate hoặc seed.

```powershell
docker ps --filter name=educenter-mysql
```

Nếu cần đăng nhập root, máy mới xem giá trị MYSQL_ROOT_PASSWORD trong file riêng config/mysql.env bằng trình soạn thảo; máy cũ dùng mật khẩu đã đặt khi tạo container. Giá trị đó chính là mật khẩu MySQL root sau lần khởi tạo volume đầu tiên. Đổi file env không tự đổi mật khẩu trong volume đã khởi tạo.

<a id="db-moi"></a>
## 5. Chọn một cách tạo dữ liệu trên máy mới

### 5A. Cài DB mới từ mã nguồn — cách thông thường

Chỉ dành cho database rỗng:

```powershell
pwsh -File database/scripts/run.ps1 -Action init -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action check -Container educenter-mysql -EnvFile config/server.env
```

`init` tự thực hiện schema → routines/triggers theo manifest → reference/policy → lịch sử applied001–005. Không chạy lần lượt từng file SQL bằng tay. Không cần chạy thêm migration sau init005. `check` phải không có dòng VI_PHAM khác0; CAN_XAC_NHAN cần đối chiếu nếu có dữ liệu lịch sử.

Sau khi cài DB mới, làm tiếp bước 6 để bật PHP, rồi bước 7 để tạo Admin. Mở `/login`, đăng nhập Admin và tạo khóa học, nhập dữ liệu trung tâm trong `/app`.

### 5B. Nhận dữ liệu giống một đồng đội — lựa chọn thay cho5A

Git clone chỉ có cấu trúc SQL và seed, **không chứa dữ liệu volume Docker của người khác**. Người gửi dùng [backup](#chia-se), chuyển cặp SQL/JSON qua kênh riêng được nhóm đồng ý. Người nhận giữ bản sao an toàn và kiểm tra:

```powershell
python database/scripts/backup.py verify "C:\DUONG_DAN\BAN_SAO.sql" --migrate
```

Verify phục hồi vào container thử rồi dọn; **không nhập vào DB làm việc**. Muốn nhập bản sao vào máy mới, chỉ làm khi DB đích chưa tồn tại, và chưa chạy5A. Mở root:

```powershell
docker exec -it educenter-mysql mysql -u root -p
```

Nhập root password riêng của máy người nhận. Tại `mysql>`:

```sql
CREATE DATABASE trung_tam_gia_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

Nếu CREATE DATABASE báo đã tồn tại, dừng và kiểm tra; không DROP để thử lại. Từ PowerShell:

```powershell
docker cp "C:\DUONG_DAN\BAN_SAO.sql" educenter-mysql:/tmp/educenter-restore.sql
docker exec educenter-mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --default-character-set=utf8mb4 --database=trung_tam_gia_su < /tmp/educenter-restore.sql'
```

Kiểm tra exit code ngay sau lệnh import: `$LASTEXITCODE` phải bằng0. Có lỗi thì dừng đối chiếu, giữ file backup, không import lặp mù quáng. File SQL do backup.py tạo không chứa CREATE/USE database cố định và không chứa user/grant cấp server. Sau import thành công, xóa file tạm trong container:

```powershell
docker exec educenter-mysql rm /tmp/educenter-restore.sql
```

Nếu backup đang ở005, chạy check. Nếu ở001–004, thực hiện [quy trình nâng cấp](#db-cu) trên bản sao đã khôi phục. Sau đó tiếp tục bước 6 để tạo user API riêng. Không dùng seed-demo lên bản sao người dùng.

<a id="chay-web"></a>
## 6. Cấp user API rồi chạy PHP

Mở MySQL bằng lệnh sau và nhập mật khẩu root của máy mình khi được hỏi.

```powershell
docker exec -it educenter-mysql mysql -u root -p
```

Tại dấu nhắc `mysql>`, thay mật khẩu minh họa dưới đây bằng **đúng DB_PASSWORD trong config/server.env trên máy bạn**, rồi chạy SQL.

```sql
CREATE USER 'educenter_api'@'%' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_DB_API_RIENG';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'%';
EXIT;
```

Nếu user đã có, dùng lại thông tin đúng; không đặt lại mật khẩu tùy tiện. Host% cho phép PHP container gọi MySQL trong mạng Docker, trong khi cổng DB trên Windows vẫn chỉ bind loopback. Không cấp user API quyền CREATE/ALTER/DROP/GRANT.

```powershell
pwsh -File backend/scripts/start.ps1
pwsh -File backend/scripts/doctor.ps1
```

Start bật lại DB, nối mạng, build PHP/Apache, composer install từ lock và bật backend/Mailpit. Lần đầu cần Internet, có thể mất vài phút. Script không xóa volume, init lại DB hoặc nạp demo vào DB làm việc.

| Địa chỉ | Dùng để làm gì |
| --- | --- |
| http://localhost:5000/ | Trang giới thiệu Trung tâm |
| http://localhost:5000/login | Trang đăng nhập chung của các vai trò |
| http://localhost:5000/app | Không gian làm việc theo tài khoản đã đăng nhập |
| http://localhost:5000/api/health | Kiểm tra PHP kết nối MySQL |
| http://localhost:5000/api | Manifest API/module |
| http://localhost:8025/ | Hộp thư SMTP thử Mailpit |

Health mong đợi success=true, timeZone=+07:00, isolationLevel=READ-COMMITTED. Doctor kiểm tra PHP/extensions, cấu hình production/JWT, user DB khác root, writable, migration005, timezone, SMTP và HTTP. Health/doctor đạt không đồng nghĩa đã có tất cả tính năng ERP.

<a id="tai-khoan"></a>
## 7. Tạo tài khoản web và sử dụng bản hiện có

Máy cài DB mới chạy:

```powershell
pwsh -File backend/scripts/bootstrap-admin.ps1
```

Script hỏi mật khẩu riêng bằng SecureString; username mặc định **admin**. Sau đó mở `http://localhost:5000/login` và đăng nhập bằng mật khẩu vừa đặt. Mỗi máy dùng mật khẩu Admin do người quản lý máy đó cấp.

Nếu DB khôi phục đã có admin, dùng mật khẩu web được chủ dữ liệu cấp. Chỉ khi chủ động đổi/kích hoạt tài khoản **trong bản local của bạn**, chạy:

```powershell
pwsh -File backend/scripts/bootstrap-admin.ps1 -Username admin -Reset
```

Không dùng Reset như một lệnh khởi động hằng ngày. Mật khẩu MySQL/root không phải mật khẩu ở màn đăng nhập web. Không nhập thông tin DB vào form login.

Mọi vai trò đăng nhập tại **http://localhost:5000/login**. Sau khi đăng nhập, **http://localhost:5000/app** hiển thị giao diện theo quyền hiện hành của tài khoản trong DB.

- Admin dùng cổng quản trị.
- Giáo vụ dùng cổng tuyển sinh/điều phối.
- Phụ huynh dùng cổng quản lý thông tin các con.
- Giáo viên dùng cổng lịch dạy, check-in/out, sổ buổi học, đề xuất lớp và đơn từ.
- Gia sư và Trợ giảng có hồ sơ cá nhân, chuyên môn và đổi mật khẩu. Giao diện nghiệp vụ riêng đang triển khai.

**Hồ sơ và mật khẩu**

Bấm avatar ở góc trên bên phải để chọn **Hồ sơ cá nhân**, **Chỉnh sửa hồ sơ cá nhân**, **Đổi mật khẩu** hoặc **Đăng xuất**.

- Giáo vụ xem mã nhân viên, chức vụ, cơ sở và ngày vào làm. Chỉ sửa thông tin cá nhân và liên hệ.
- Giáo viên, Gia sư, Trợ giảng xem hồ sơ chuyên môn đúng tài khoản. Được sửa thông tin cá nhân, giới tính, đơn vị công tác và giới thiệu. Chuyên môn được phân công, trạng thái hợp tác, quyền và đơn giá do Admin quản lý. Hồ sơ Giáo viên giữ thời khóa biểu lịch rảnh tuần/tháng cùng các thao tác thêm, sửa, xóa đã có.
- Phụ huynh xem các con đã liên kết, sửa thông tin liên hệ và nghề nghiệp của mình.

Đổi mật khẩu cần mật khẩu hiện tại, mật khẩu mới và xác nhận khớp nhau. Mật khẩu mới từ 6 đến 72 byte theo giới hạn bcrypt, giữ nguyên khoảng trắng. Biểu mẫu báo lỗi tại trường nhập. Đổi thành công sẽ quay về trang đăng nhập và thu hồi các phiên cũ. Mật khẩu không thay đổi khi chỉ sửa hồ sơ.

**QR chuyển khoản ngân hàng và demo ngày 19/11/2026**

Admin mở **Đối soát Thanh toán Web → Tài khoản ngân hàng nhận học phí**. Thêm tên ngân hàng, mã BIN 6 số theo VietQR của ngân hàng, số tài khoản và tên chủ tài khoản. Chọn loại **Tài khoản ngân hàng thật đã kiểm tra thông tin**, lưu rồi bấm **Chọn mặc định**. Chỉ Admin quản lý thông tin này. Có thể lưu nhiều tài khoản; hệ thống có một tài khoản mặc định cho các QR mới.

Phụ huynh mở **Học phí & Thanh toán → Thanh toán** trên hóa đơn đã phát hành. QR lấy số dư hóa đơn và nội dung chuyển khoản từ server. Kiểm tra tên người nhận trên ứng dụng ngân hàng rồi tự xác nhận chuyển tiền tại ngân hàng. Sau đó bấm **Tôi đã chuyển tiền · Gửi thông báo**. Admin đối chiếu tiền vào, số tiền, tài khoản nhận và nội dung chuyển khoản rồi xác nhận lập phiếu thu. Nút của Phụ huynh không tự gạch nợ. Chưa có kết nối thông báo số dư/webhook ngân hàng nên chưa tự xác nhận tiền vào.

File `database/seeders/demo-thu-cong.sql` tạo dữ liệu sáu vai trò và hai tài khoản ngân hàng minh họa. Các tài khoản DEMO không tạo QR chuyển tiền; nút **Gửi thông báo demo** dùng thử luồng đối soát trên dữ liệu demo. Tài khoản ngân hàng thật đã lưu được giữ nguyên khi chạy lại file. Mật khẩu demo **Demo@123**, tài khoản `demo_admin`, `demo_giaovu`, `demo_giaovien`, `demo_giasu`, `demo_trogiang`, `demo_phuhuynh`. Dữ liệu chung còn có nhiều người dạy, phụ huynh và học viên để thử lịch/lớp.

Phần THÊM bổ sung namespace SQLDEMO01, giữ các phiếu tư vấn và dữ liệu đang có. Không cần chạy XÓA trước. Lệnh `CALL sp_demo_educenter('XOA')` dành riêng cho DB local demo, xóa toàn bộ nghiệp vụ kể cả dòng nhập tay, giữ tài khoản/mật khẩu; phải sao lưu trước khi dùng.

Đăng xuất quay về trang đăng nhập. F5 tại `/app` kiểm tra lại phiên. Phiên hết hạn hoặc đã thu hồi sẽ chuyển về `/login`. PHP phục vụ giao diện và API cùng cổng 5000. Các liên kết cũ `/admin`, `/staff` chuyển về `/app`; giao diện được chọn theo vai trò của tài khoản. Cookie phiên có HttpOnly, SameSite=Lax và Secure khi chạy HTTPS. API nghiệp vụ dùng Bearer JWT và kiểm tra quyền ở backend.

Giao diện tham chiếu nằm tại [docs/demo/index.html](demo/index.html). Xem các phần đã triển khai và còn thiếu tại [tiến độ đồ án](thong-tin-do-an.md#tien-do). Khi vừa cập nhật code, nhấn **Ctrl+F5** trên trình duyệt để tải giao diện mới.

### 7.1. Kiểm tra cổng quản trị trước khi push

Mở `http://localhost:5000/login` và đăng nhập tài khoản Admin đã cấp trên DB của máy mình. Hệ thống mở cổng quản trị tại `/app`. Giao diện lấy trực tiếp bố cục, CSS, 10 màn và các form Admin đã chốt trong `docs/demo/index.html`. Dữ liệu quản trị được đọc/ghi qua API PHP/MySQL. Danh sách màn và hợp đồng API ở [mục 12.1 của đặc tả](thong-tin-do-an.md#quan-tri-admin).

Khi cần đồng bộ template/style quản trị từ bản giao diện tham chiếu, chạy lệnh sau tại thư mục gốc repo. Chỉ thực hiện khi đang sửa phần giao diện Admin.

```powershell
python backend/scripts/build-admin-assets.py
```

Kiểm tra lần lượt 10 menu Admin gồm tổng quan, khóa học/xếp lớp, đối soát thanh toán, công/đơn giá/lương, duyệt nghỉ/chỉnh công/mở khóa, nhân sự/hồ sơ, phân quyền, danh mục/cấu hình, kiểm toán và lịch toàn chuỗi. Mỗi màn có trạng thái dữ liệu rỗng khi DB chưa có bản ghi.

Luồng thử nghiệp vụ trên DB thử là tạo người dạy → cập nhật chuyên môn/lịch rảnh/đơn giá → tạo khóa và lớp phù hợp → Admin xếp đăng ký vào lớp dù chưa đóng tiền → xem lịch và chỉnh buổi chưa dạy/xếp bù → duyệt nghỉ cả lớp hoặc chỉnh công → chốt/chi trả công → tra nhật ký. Đối soát chỉ nhận giao dịch `cho_xac_nhan` đã có nguồn sự kiện hợp lệ. Giao diện có thể in phiếu lương và lịch đang lọc qua hộp thoại In/Lưu PDF của trình duyệt.

Danh mục phòng hiện lưu nhãn phòng trong `cau_hinh` với tiền tố `phong_hoc.` và đọc thêm nhãn đang dùng ở lớp/buổi học. Schema 005 giữ 43 bảng. Các giá trị check-in/out tuân theo chính sách đã chốt. Kỳ lương đã trả được bảo vệ khi mở khóa.

Kiểm chứng local 11/11 đạt 460 kiểm tra gồm 71 core, 81 API nền, 210 API Admin và 98 ca SQL trên DB thử, cùng nâng cấp/khôi phục thử. Kiểm tra trình duyệt đủ 10 màn Admin, 5 nhóm quyền và form đọc dữ liệu thật đạt. Chi tiết kiểm chứng ở mục 12.1 của đặc tả.

### 7.2. Tuyển sinh / Điều phối

Sau khi cập nhật code trên `develop`, mở Docker Desktop và chạy `pwsh -File backend/scripts/start.ps1` từ thư mục gốc. Mở `http://localhost:5000/login` và đăng nhập tài khoản Giáo vụ có quyền tiếp nhận. Admin thao tác các menu tuyển sinh được cấp trong cổng quản trị của mình. Admin tạo tài khoản Giáo vụ và hồ sơ người dạy tại menu Nhân sự/Tài khoản, cập nhật chuyên môn, khu vực và lịch rảnh của người dạy để có ứng viên matching.

Trong cổng tuyển sinh, xem Đăng ký khóa học hoặc mở Tuyển sinh & Ghép người dạy. Tiếp nhận yêu cầu, chọn hồ sơ học viên hoặc nhập liên hệ khách tư vấn, chọn môn/khối/hình thức/khu vực và tất cả khung giờ cần học. Khách tư vấn cần liên kết hồ sơ học viên/phụ huynh trước khi gửi đề xuất. Bấm Tìm người dạy, đối chiếu ứng viên, nhập học phí trọn khóa nếu đã tư vấn và gửi đề xuất. Bước 3 hiển thị phản hồi thật của từng bên, bước 4 chỉ hiện đề xuất đã được cả hai đồng ý. Admin tiếp tục xếp lớp qua màn Khóa học & Xếp lớp.

Phụ huynh phản hồi đề xuất tại cổng Phụ huynh. Cổng phản hồi của người dạy thuộc phần triển khai của thành viên 3. Hợp đồng API và trạng thái ở mục 12.2 của đặc tả. Có thể kiểm tra đầy đủ API hai phía trên DB thử bằng suite `pwsh -File backend/scripts/test.ps1`. DB làm việc được đọc qua cấu hình riêng của từng máy; cập nhật này dùng schema 005 hiện có.

#### Cổng Giáo vụ đầy đủ ngày 12/11/2026

Đăng nhập tài khoản Giáo vụ tại `/login` để mở `/app`. Trang đầu là Tổng quan công việc. Sidebar có đủ 8 mục theo `docs/demo/index.html`, dùng chung giao diện và bộ lịch của Admin. Tài khoản Giáo vụ do Admin cấp, có hồ sơ nhân viên đang làm và các quyền `TiepNhanYeuCau`, `QuanLyLopHoc`, `VanHanhBuoiHoc`, `HocPhiThanhToan` tương ứng thao tác được giao. Admin dùng các chức năng quản trị tương ứng trong cổng Admin.

1. Vào Hồ sơ Phụ huynh để thêm thông tin liên hệ. Mở chi tiết để xem các con hoặc thêm học viên gắn sẵn phụ huynh. Admin cấp tài khoản web tại màn Tài khoản.
2. Vào Học viên & ĐK Khóa để tạo/sửa hồ sơ, chọn khóa đang mở hoặc đăng ký thêm khóa. Tra cứu đăng ký khóa để xem giá đã chốt và lớp Admin đã xếp.
3. Vào Tuyển sinh & Ghép lớp để thực hiện luồng 4 bước ở trên. Admin xếp lớp tại cổng Admin theo đề xuất đã thống nhất.
4. Vào Lớp & Sĩ số để xem danh sách lớp, người dạy, trợ giảng, lịch tuần và học viên. Mở Thời khóa biểu để xem tuần/tháng, lọc hoặc xếp ca, chọn người dạy, phòng và lặp hàng tuần. Người dạy phải khớp chuyên môn/lịch rảnh. Hệ thống kiểm tra trùng lịch và giữ ca đã check-in/chốt công.
5. Vào Duyệt nghỉ cá nhân & Học bù để duyệt/từ chối đơn cá nhân. Việc đổi lịch được Giáo vụ cập nhật ca sau khi thống nhất. Để xếp bù, vào Thời khóa biểu → Xếp ca học mới → Loại buổi học → Buổi học bù, rồi chọn buổi gốc, học viên, ngày/giờ và người dạy. Sổ bù lấy dữ liệu đã lưu.
6. Vào Hóa đơn trọn khóa & Thu phí, tạo nháp từ đăng ký, chọn Chi tiết rồi Phát hành để lưu thông báo cho phụ huynh. Có thể xem số dư, lịch sử phiếu thu và in/lưu PDF. Admin xác nhận thu tiền. Chuông thông báo đọc và đánh dấu đã đọc cho tài khoản đang đăng nhập.

Học viên mới có đăng ký khóa sẽ hiện “Chờ xếp lớp”. Admin vào Khóa học & Xếp lớp để xếp học viên vào lớp, sĩ số cập nhật theo ghi danh. Học phí được theo dõi riêng tại hóa đơn. Hồ sơ “Hoạt động” là trạng thái quản lý hồ sơ, nhãn học tập trên danh sách lấy từ lớp và đăng ký thực tế.

Danh mục cơ sở cần được Admin tạo tại Danh mục & Cấu hình → Cơ sở, sau đó chọn cơ sở khi tạo lớp hoặc xếp ca. Để xóa ca xếp nhầm, bấm vào ca trên lịch → Xóa ca học → Xác nhận. Admin/Giáo vụ có quyền sửa lịch được xóa ca dự kiến chưa diễn ra và chưa có check-in/học vụ. Ca đang liên kết đơn nghỉ, lịch bù nguồn hoặc chứng từ cần xử lý các liên kết trước. Nghỉ cả lớp tiếp tục theo quy trình Admin duyệt.

Khi chọn người dạy, biểu mẫu hiển thị lịch rảnh đã đăng ký. Nếu trình duyệt hiển thị giờ kiểu AM/PM, 06:00 AM là 6 giờ sáng, 06:00 PM là 18 giờ. Khi lưu ngoài lịch rảnh, thông báo ghi khung giờ đã đăng ký của đúng thứ đang chọn. Khi chưa có cơ sở hoạt động, biểu mẫu hướng dẫn Admin bổ sung danh mục. Chi tiết ca có dữ liệu liên quan sẽ hiển thị nguyên nhân chặn xóa ngay khi mở ca.

Các thao tác ở cổng này gọi API PHP và MySQL 005. Máy đã cài 005 chỉ cần chạy server hiện có. Nội dung mẫu trong index phục vụ đối chiếu giao diện. Dữ liệu cổng Giáo vụ lấy từ DB riêng của từng máy. Hợp đồng API và kiểm chứng nằm ở mục 12.3 của đặc tả.

### Kiểm tra cổng Giáo viên

Đăng nhập tại `http://localhost:5000/login` bằng tài khoản Giáo viên được cấp. Với bộ demo tại mục 7.3, dùng **demo_giaovien**, mật khẩu **Demo@123**. PHP mở giao diện Giáo viên tại `/app`. Máy đã chạy schema 005 có thể dùng trực tiếp các API mới.

Tổng quan có ca hôm nay và bảng phân công của tuần hiện tại từ Thứ Hai đến Chủ Nhật, lấy ngày từ máy chủ. Bấm Xem thời khóa biểu tuần để mở đúng tuần đang hiển thị. Đơn đổi lịch kiểm tra ngay giờ kết thúc sau giờ bắt đầu và thời điểm đề xuất còn ở tương lai. Điều chỉnh công và hạn nộp bài dùng hai ô ngày/giờ, nhập giờ HH:mm. Lỗi hiển thị dưới trường đang sai.

1. Vào Tổng quan hoặc Lịch dạy cá nhân để xem các ca được phân công. Lịch có chế độ tuần, tháng, danh sách và bộ lọc lớp. Chọn ca để xem địa điểm, phòng hoặc liên kết học trực tuyến.
2. Bấm Check-in khi vào ca. Hệ thống lưu giờ máy chủ. Sau khi dạy, bấm Check-out để ghi nhận giờ ra và phút công. Thao tác bấm lại giữ lần ghi nhận ban đầu.
3. Trong chi tiết ca, bấm Mở sổ. Danh sách ban đầu hiển thị tên học viên và lớp. Bấm từng học viên để bung thông tin, nhập chủ đề/nội dung và điểm danh/chấm bài. Có bốn trạng thái điểm danh. Mỗi học viên có một điểm bài tập của buổi, nhận xét, mức tiếp thu và thái độ. Nhập tên bài tập, nội dung, hạn nộp và liên kết đề bài/PDF khi giao bài. Bấm Lưu sổ hoặc Lưu sổ & Check-out. Phụ huynh đọc được dữ liệu của con sau khi lưu.
4. Vào Lớp phụ trách & Lịch rảnh để xem danh sách học viên và lịch lớp. Thời khóa biểu lịch rảnh có chế độ tuần/tháng và cũng hiển thị trong Hồ sơ cá nhân & Chuyên môn khi bấm avatar. Bấm Thêm lịch rảnh hoặc dấu + tại ngày cần đăng ký, chọn thứ và giờ rồi lưu. Bấm khung giờ đã có để sửa hoặc xóa, xác nhận trước khi xóa. Các khung giờ lặp lại hàng tuần, thay đổi sẽ áp dụng cho thứ đó trong mọi tuần. Ô giờ dùng dạng 24 giờ HH:mm, ví dụ 11:00–12:00 là 11 giờ đến 12 giờ trưa, 18:00–20:00 là buổi tối. Giờ thiếu, giờ kết thúc trước giờ bắt đầu hoặc khung giờ trùng được báo ngay trong hộp nhập và khóa nút Lưu đến khi hợp lệ. Chỉnh sửa hồ sơ cá nhân dùng biểu mẫu riêng để cập nhật liên hệ, giới thiệu và đơn vị công tác. Môn/khối chuyên môn do trung tâm quản lý.
5. Bộ demo mặc định đã chốt các yêu cầu mẫu. Để thử ứng tuyển, Giáo vụ tạo yêu cầu học mới còn mở, có phụ huynh/học viên và lịch phù hợp hồ sơ giáo viên. Để thử phản hồi đề xuất, Giáo vụ gửi đề xuất đến đúng giáo viên. Trong Đề xuất ghép lớp & Ứng tuyển, phản hồi Đồng ý/Từ chối đề xuất Giáo vụ gửi. Các yêu cầu phù hợp chuyên môn, khu vực và lịch rảnh hiện ở Cơ hội nhận lớp. Bấm Ứng tuyển để gửi sang luồng đề xuất, sau đó theo dõi phản hồi phụ huynh. Admin thực hiện xếp lớp.
6. Gửi đơn nghỉ cả lớp, đổi lịch hoặc điều chỉnh công tại Đơn từ. Nghỉ cả lớp và điều chỉnh công chuyển Admin. Giáo vụ xử lý đề nghị đổi lịch rồi cập nhật ca sau khi thống nhất. Lịch sử đơn hiển thị trạng thái và phản hồi.
7. Chọn tháng tại Bảng công cá nhân để xem các ca đã check-in, phút công, cờ trễ và trạng thái kỳ. Bấm avatar ở góc phải để xem hồ sơ chuyên môn, sửa họ tên/email/ngày sinh/giới tính/thông tin liên hệ hoặc đăng xuất. Có thể in bảng công. Đơn giá và số tiền lương được quản lý ở cổng Admin.

Nếu chưa thấy ca, kiểm tra phân công cho đúng người trong lịch của Giáo vụ/Admin. Buổi bù cá nhân chỉ có các học viên được xếp bù. Ca hủy/đổi và kỳ công đã khóa được bảo vệ. Kiểm thử thao tác ghi trên DB riêng có tên kết thúc `_test` theo mục kiểm thử của tài liệu này.

### 7.3. Dữ liệu demo trong DataGrip

Bộ demo dùng schema 005, tự tạo tài khoản Admin và dữ liệu cho sáu vai trò. Mở `database/seeders/demo-thu-cong.sql` bằng kết nối MySQL root local, chọn schema `trung_tam_gia_su` và SQL dialect **MySQL**. Trong Database Explorer, bấm chuột phải vào schema → **SQL Scripts → Run SQL Script** rồi chọn file này. File cài thủ tục `sp_demo_educenter` và gọi `THEM` một lần. [Cách chạy file SQL trong DataGrip](https://www.jetbrains.com/help/datagrip/run-sql-files.html).

Sau lần chạy đầu, chỉ cần chọn và chạy **một lệnh CALL** tương ứng trong Query Console. Tên schema đang chọn phải là `trung_tam_gia_su`.

**Thêm dữ liệu demo** bằng lệnh sau. Nếu bộ đã được tạo, lệnh trả kết quả hiện có. Các ID nằm trong biến cục bộ của thủ tục, cùng một giao dịch.

```sql
CALL sp_demo_educenter('THEM');
```

**Xem và đối chiếu dữ liệu** bằng lệnh sau. Kết quả gồm số lượng, tài khoản, học viên/lớp, lịch và hóa đơn.

```sql
CALL sp_demo_educenter('XEM');
```

Bộ đầy đủ có **15 tài khoản, 12 học viên, 5 lớp, 20 ca và 10 hóa đơn**. Mật khẩu chung của các tài khoản demo là **`Demo@123`**. Đăng nhập tại **http://localhost:5000/login**.

| Vai trò | Tài khoản |
| --- | --- |
| Admin | demo_admin |
| Giáo vụ | demo_giaovu |
| Giáo viên | demo_giaovien |
| Gia sư | demo_giasu |
| Trợ giảng | demo_trogiang |
| Phụ huynh | demo_phuhuynh |

Admin, Giáo vụ, Phụ huynh và Giáo viên có giao diện nghiệp vụ. Gia sư/Trợ giảng có dữ liệu phân công và màn xác nhận vai trò, giao diện nghiệp vụ vẫn đang triển khai. Lịch có hai ca cho mỗi lớp vào ngày mai và buổi bù vào ngày kia. Phụ huynh mẫu có nhiều con để kiểm tra chuyển học viên.

**Xóa dữ liệu nghiệp vụ demo, giữ tài khoản và mật khẩu** bằng lệnh dưới đây. Lệnh dọn 31 bảng nghiệp vụ, gồm học viên, nhu cầu học, lớp, lịch học, học vụ, hóa đơn, thanh toán, công, thông báo và lịch sử demo. Tài khoản, mật khẩu hiện có, nhóm quyền, danh mục chuẩn, trạng thái cài đặt và hồ sơ vai trò tối thiểu và trạng thái bảo mật tài khoản được giữ để tiếp tục đăng nhập. Dừng backend, sao lưu và kiểm chứng bản sao theo mục 9 trước khi chạy lệnh xóa.

```sql
CALL sp_demo_educenter('XOA');
```

Sau khi xóa, dùng `CALL sp_demo_educenter('THEM');` để tạo lại bộ demo cho tất cả vai trò bằng các tài khoản đã giữ. Mật khẩu hiện có của tài khoản được giữ nguyên khi thêm lại. Tài khoản demo mới dùng mật khẩu **Demo@123**. Chạy lại `THEM` trả bộ đã tạo, tránh chèn trùng. Các lệnh thêm, xem và xóa nằm ở cuối file SQL để chọn chạy riêng. Khởi động lại backend sau khi dọn rồi nhấn Ctrl+F5 trên web.

Ngày 17/11/2026, DB local được dọn theo yêu cầu giữ tài khoản/mật khẩu sau khi sao lưu và khôi phục thử đạt. Bản sao SQL/JSON được lưu ngoài repo trong `%LOCALAPPDATA%/EduCenterBackups`. Chu trình thêm → xóa → thêm lại và giữ mật khẩu được kiểm tra trên database `_test` trước khi áp dụng.

### 7.4. Sử dụng cổng Phụ huynh

Mở `http://localhost:5000/login` và đăng nhập tài khoản Phụ huynh được liên kết với hồ sơ con trên DB của máy mình. `/app` mở giao diện có cùng sidebar/header với Admin và Giáo vụ. Sau khi cập nhật source, nhấn Ctrl+F5 để nhận bản HTML/CSS/JS mới.

1. Chọn con bằng danh sách hoặc nút tên học sinh. Tổng quan, lịch, bài tập, hóa đơn và đơn nghỉ đổi theo con đang chọn.
2. Mở Lịch học để xem tuần/tháng/danh sách. Bấm một buổi để xem chi tiết và gửi đơn nghỉ nếu buổi đó sắp diễn ra. Lịch bù hiển thị ngay trong lịch với nhãn Học bù.
3. Mở Bài tập & Kết quả để xem điểm và nhận xét. Tải PDF dùng URL tệp đề bài đã lưu trong DB.
4. Mở Học phí & Thanh toán để xem/in hóa đơn, gửi thông báo sau khi chuyển khoản và theo dõi giao dịch chờ đối soát. Đối chiếu tiền thực nhận và xác nhận thu thuộc Admin.
5. Ở Tổng quan, đồng ý hoặc từ chối đề xuất người dạy. Nút chuông mở thông báo, avatar mở đổi mật khẩu, Đăng xuất quay về trang đăng nhập chung.

Tài khoản chưa liên kết con hiển thị trạng thái trống. Cấu hình ngân hàng/gateway thật và việc cung cấp URL PDF thực tế được thực hiện ở phần tích hợp tương ứng. Chi tiết API và phạm vi nằm ở mục 12.4 của tài liệu đặc tả.

### 7.5. Khôi phục mật khẩu qua email

Tại trang đăng nhập chung, chọn **Quên mật khẩu**, nhập tên đăng nhập hoặc email rồi lấy OTP. Mã được gửi đến email đã lưu trong tài khoản, có hiệu lực 10 phút và dùng một lần. Không có mã hiển thị sẵn hoặc tự điền trên trang web. Chờ 60 giây giữa hai lần gửi mã. Nhập sai 5 lần phải lấy mã mới.

Khi chạy local bằng bộ server của dự án, mở **http://localhost:8025/** để đọc thư trong Mailpit và lấy OTP. Mailpit chỉ dùng thử, không chuyển email ra hộp thư thật. Nếu SMTP không chạy, hệ thống báo chưa gửi được mã và không cấp OTP mới. Khi triển khai, cấu hình SMTP thật bằng các biến `MAIL_*` trong `config/server.env` riêng, sau đó khởi động lại backend. Đặt lại mật khẩu thành công sẽ thu hồi các phiên đăng nhập cũ.

### 7.6. Đăng nhập Google (Google Identity Services / One Tap) & Ghi nhớ đăng nhập

1. **Tùy chọn "Ghi nhớ đăng nhập" trên `/login`:**
   - Khi **không chọn**: phiên dùng thời hạn `JWT_EXPIRES_IN` (mặc định `8h`) và cookie phiên (`expire=0`, tự xóa khi đóng trình duyệt).
   - Khi **chọn**: JWT và cookie `HttpOnly` (`educenter_session`, `SameSite=Lax`, bật `Secure` khi chạy HTTPS) đều đồng bộ thời hạn theo `JWT_REMEMBER_EXPIRES_IN` (mặc định `7d`, giới hạn tối đa **7 ngày**). Access token không lưu trong `localStorage`.
   - Khi đăng xuất hoặc đổi mật khẩu, token bị thu hồi ngay ở backend (`audit_log`) và cookie bị xóa.

2. **Bật đăng nhập Google (tùy chọn — không bắt buộc khi chạy demo mật khẩu):**
   - Vào **Google Cloud Console → APIs & Services → Credentials → Create Credentials → OAuth client ID (Web application)**.
   - Tại **Authorized JavaScript origins**, khai báo đúng origin chạy web:
     - Local: `http://localhost:5000` (và `http://127.0.0.1:5000` nếu truy cập bằng IP này; lưu ý Google ưu tiên `http://localhost:5000`).
     - Production: `https://<ten-mien-cua-ban>`.
   - *Không cần* khai báo Redirect URI và *không cần* Client Secret vì EduCenter dùng luồng Google Identity Services ID Token xác minh chữ ký RS256 trực tiếp với JWKS của Google ở backend PHP (`POST /api/auth/google`).
   - Dán Client ID vào file riêng `config/server.env` (không commit lên Git):
     ```dotenv
     JWT_REMEMBER_EXPIRES_IN=7d
     GOOGLE_CLIENT_ID=<your-client-id>.apps.googleusercontent.com
     ```
   - Khởi động lại backend (`docker compose up -d --force-recreate backend`), sau đó mở `/login` và nhấn `Ctrl + F5`.

3. **Quy tắc liên kết tài khoản & hành vi thực tế của One Tap:**
   - Backend chỉ đăng nhập vào tài khoản đang `active` có `users.email` khớp chính xác với email đã được Google xác minh (`email_verified=true`); vai trò lấy 100% từ DB. Hệ thống **không tự tạo tài khoản hoặc tự cấp vai trò** nếu email Google chưa tồn tại trong DB (màn hình sẽ hướng dẫn liên hệ trung tâm hoặc dùng tài khoản/mật khẩu).
   - **Giới hạn trình duyệt của Google One Tap:** One Tap có thể không tự động đăng nhập hoặc bị ẩn khi người dùng đang bật chế độ ẩn danh, trình duyệt chặn cookie/FedCM (Safari, Brave, trình chặn quảng cáo), trong thời gian cooldown sau khi đóng hộp One Tap, hoặc ngay sau khi bấm **Đăng xuất** (hệ thống chủ động gọi `disableAutoSelect()` để tránh tự đăng nhập ngược lại khi muốn đổi tài khoản). Khi đó người dùng vẫn dùng bình thường nút **"Đăng nhập bằng Google"** hoặc form tên tài khoản/mật khẩu.

<a id="hang-ngay"></a>
## 8. Chạy hằng ngày và cập nhật mã nguồn

Mở Docker Desktop, đợi **Engine running**, vào thư mục gốc dự án rồi chạy lệnh sau.

```powershell
pwsh -File backend/scripts/start.ps1
```

Mở **http://localhost:5000/login**. Giữ Docker Desktop chạy trong lúc sử dụng web.

> **Lưu ý hiệu năng Docker local (từ 20/11/2026):** `compose.yaml` mount riêng `backend/vendor` (`backend_vendor`) và `backend/writable` (`backend_writable`) vào volume Linux để tránh trễ I/O từ ổ Windows, kết hợp cấu hình OPcache (`revalidate_freq = 5`, `enable_file_override = 1`) trong `config/php.ini`. Source code trong `backend/app/`, `backend/public/`, `frontend/` vẫn sửa nóng bình thường (file PHP tự nhận sau tối đa 5 giây). Khi có thay đổi `composer.json`/`composer.lock`, chạy lại `pwsh -File backend/scripts/start.ps1` hoặc:
> ```powershell
> docker compose exec backend composer install --no-interaction --prefer-dist --optimize-autoloader
> docker compose restart backend
> ```
> Nếu trước đó máy bạn đã tải ảnh QR ngân hàng vào `backend/writable/uploads/payment-qr/` trên ổ Windows, cần sao chép vào container một lần (`docker cp backend/writable/uploads/payment-qr/. educenter-backend-1:/var/www/educenter/backend/writable/uploads/payment-qr/` rồi cấp quyền `www-data`).

Nếu source, image, dependency và cấu hình vẫn như lần chạy trước, có thể bật nhanh bằng các lệnh sau.

```powershell
docker start educenter-mysql
docker compose --profile tools up -d backend mailpit
```

Dừng ứng dụng, giữ dữ liệu:

```powershell
docker compose --profile tools stop
docker stop educenter-mysql
```

Xem trạng thái/log:

```powershell
docker compose --profile tools ps
docker compose logs --tail 50 backend
```

<a id="pull-push-code"></a>
### Pull, commit và push code trên develop

Cả nhóm làm việc và push code vào **`develop`**. Trưởng nhóm kiểm thử, duyệt demo và tạo PR **`develop` → `main`** để đưa bản đã duyệt vào nhánh ổn định.

**Trước khi bắt đầu làm**, mở Terminal tại thư mục dự án, kiểm tra và lưu phần việc đang có rồi cập nhật.

```powershell
git status
git switch develop
git pull origin develop
```

Nếu đang có file sửa chưa commit, lưu file và commit phần việc đó trước khi chuyển nhánh hoặc pull. Nếu vừa pull bản có thay đổi thư viện, Dockerfile hoặc cấu hình server, chạy lại lệnh sau.

```powershell
pwsh -File backend/scripts/start.ps1
```

Nếu bản mới có migration, thực hiện [nâng cấp DB](#db-cu) trước khi bật ứng dụng. Pull cập nhật mã nguồn; dữ liệu MySQL của máy mình nằm trong volume Docker. Đọc thay đổi migration trước khi chạy các lệnh DB.

**Sau khi code và kiểm thử xong**, xem các file đã thay đổi rồi commit.

```powershell
git status
git add .
git commit -m "Hoàn thiện chức năng vừa làm"
```

Thay nội dung commit bằng mô tả phần mình thực hiện. Kiểm tra danh sách file để tránh đưa mật khẩu, env riêng hoặc backup vào commit. Tiếp tục lấy code mới của nhóm.

```powershell
git pull origin develop
```

Kiểm thử lại các phần được gộp rồi push.

```powershell
git push origin develop
```

Nếu `git status` báo đang ở nhánh khác `develop`, kiểm tra và chuyển về `develop` trước khi thực hiện quy trình này. Nếu commit báo `nothing to commit`, xem lại `git status` để xác nhận các thay đổi đã được lưu và commit trước đó.

**Khi push báo `rejected`, `fetch first` hoặc `non-fast-forward`**, lấy code mới của nhóm.

```powershell
git pull origin develop
```

Nếu gộp thành công, kiểm thử lại rồi chạy lệnh push.

```powershell
git push origin develop
```

**Khi pull báo `CONFLICT`**, mở các file xung đột trong VS Code, đối chiếu và gộp nội dung cần giữ của cả hai bên. Xóa các dấu `<<<<<<<`, `=======`, `>>>>>>>`, lưu file và kiểm thử lại rồi chạy các lệnh sau.

```powershell
git add .
git commit -m "Giải quyết xung đột code"
git push origin develop
```

Nếu Git mở trình soạn thảo để nhập lời nhắn merge, giữ hoặc sửa lời nhắn rồi lưu và đóng. Với Vim, nhấn `Esc`, gõ `:wq` rồi Enter. Khi xem diff bị dừng tại `(END)`, nhấn `q` để quay về Terminal.

Quy trình trên dùng merge khi pull. Nếu máy đã cấu hình pull bằng rebase hoặc chỉ cho fast-forward, đặt lại **một lần trong repo này** để các lệnh `git pull origin develop` hoạt động theo hướng dẫn.

```powershell
git config pull.rebase false
git config pull.ff true
```

Nếu `git status` đang báo `rebase in progress` từ lần thao tác trước, cần xử lý hết xung đột rồi chạy `git add .` và `git rebase --continue` cho đến khi hoàn tất. Có thể dùng `git rebase --abort` để quay về trước lần rebase đó rồi thực hiện lại quy trình pull bên trên.

Sau khi push, xem kết quả **GitHub Actions của đúng commit vừa gửi**. Thành viên tự kiểm thử phần mình. Trưởng nhóm kiểm thử luồng xuyên suốt trước khi merge PR `develop` → `main`. Giữ quyền push `main` và thao tác duyệt bản ổn định cho trưởng nhóm; giữ lịch sử chung khi gộp code, tránh force-push hai nhánh.

### 8.1. Đổi cổng HTTP local

Nếu5000 bận, đổi APP_BASE_URL và CORS_ORIGINS trong env sang cổng mới, rồi:

```powershell
pwsh -File backend/scripts/start.ps1 -Port 5001
pwsh -File backend/scripts/doctor.ps1 -Port 5001
```

Chạy start với cùng Port ở những lần sau; lệnh compose trực tiếp mặc định lại5000 nếu không đặt EDUCENTER_HTTP_PORT. Khi đổi tên container MySQL, dùng start.ps1 -DatabaseContainer và biến EDUCENTER_DB_CONTAINER nhất quán.

<a id="db-cu"></a>
### 8.2. Nâng cấp database đang có dữ liệu

**Không chạy init/schema.sql vào DB cũ.** Dừng mọi tác nhân ghi, kể cả ứng dụng/worker/công cụ SQL khác nếu có:

```powershell
docker compose stop backend
python database/scripts/backup.py backup
```

Công cụ trả đường dẫn SQL; giữ cả JSON manifest đi kèm. Thay đường dẫn thực vào lệnh kiểm chứng:

```powershell
python database/scripts/backup.py verify "DUONG_DAN_SQL_VUA_TAO" --migrate
pwsh -File database/scripts/run.ps1 -Action preflight -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action migrate -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action check -Container educenter-mysql -EnvFile config/server.env
```

Chỉ tiếp tục lệnh sau khi lệnh trước thành công. Migrate nhận gốc/001/002/003/004 và nâng phần còn thiếu lên005; đã005 thì chỉ kiểm tra, không ALTER lại. `applied_at` cũ được giữ. Nếu còn applying, gặp version lạ hoặc VI_PHAM, giữ ứng dụng dừng để xử lý; DDL MySQL tự COMMIT, không xóa marker để chạy lại.

Khi mọi kiểm tra đạt:

```powershell
pwsh -File backend/scripts/start.ps1
pwsh -File backend/scripts/doctor.ps1
```

<a id="phoi-hop-nhom"></a>
### 8.3. Lưu ý phối hợp 3 người và bàn giao PR

Phân công theo cổng sử dụng: **người 1 là chủ đồ án, phụ trách Admin/Giáo vụ và tích hợp; người 2 phụ trách đăng nhập, trang giới thiệu, Phụ huynh và thành phần dùng chung; người 3 phụ trách Giáo viên/Gia sư/Trợ giảng**. Phạm vi và quy tắc API chi tiết nằm ở [mục 10.1 của đặc tả](thong-tin-do-an.md#quy-tac-phoi-hop). Đây là phân công phát triển, không tự cấp thêm quyền cho vai trò trên hệ thống.

1. **Chốt dữ liệu, trạng thái và quyền trước khi code:** người 1 chốt hợp đồng API trong đặc tả; hướng dẫn cài/chạy hoặc kiểm thử thay đổi thì cập nhật trong file này. Không tạo thêm Markdown trong docs. Hai người còn lại đối chiếu hợp đồng trước khi nối FE; không tự đổi trường, trạng thái hoặc nghiệp vụ để khớp demo.
2. **Tách thao tác gửi yêu cầu và duyệt/xử lý:** người 2 làm luồng gửi của Phụ huynh; người 3 làm luồng gửi của người dạy/trợ giảng; người 1 làm xử lý của Admin/Giáo vụ theo đúng quyền. Ví dụ người 3 gửi đề nghị điều chỉnh công, chỉ Admin duyệt và áp dụng; người 2 tạo giao dịch thanh toán, không tự xác nhận đã thu. Hai bên dùng chung dữ liệu và trạng thái đã chốt.
3. **Thống nhất người giữ file dùng chung trước khi sửa:** ghi đầu mối feature và danh sách route/service/shared cần sửa trong mô tả task/PR. Phần Admin/Giáo vụ do người 1 giữ; đăng nhập và thành phần FE dùng chung do người 2 giữ; cổng người dạy do người 3 giữ. Feature có cả thao tác gửi và duyệt phải chọn một người ghép route/service chính; trao đổi trước khi người khác sửa cùng file. Không sao chép thành feature/bảng riêng cho từng cổng.
4. **Mỗi task dùng commit nhỏ trên develop:** cập nhật develop trước khi làm; trao đổi file đang sửa, commit theo một luồng có thể review rồi đồng bộ trước khi push. Khi một đợt tích hợp đã đạt, mở PR develop → main; PR ghi phần đã làm, API/quyền thay đổi, phụ thuộc, kết quả kiểm thử và phần còn thiếu. Người 1 review, duyệt demo và kiểm thử luồng xuyên suốt trước khi merge; không push thẳng main. Không tạo thêm nhánh trên GitHub theo quy trình hiện hành của nhóm.
5. **Mỗi người tự kiểm thử phần mình:** thử luồng đúng, dữ liệu sai, sai quyền và đổi id sang dữ liệu người khác. Kiểm thử DB chỉ dùng DB riêng tên kết thúc `_test`; PHP integration cần `TEST_WITH_DB=1`. Ghi lệnh/kết quả thực chạy trong PR, không dùng số kiểm tra lịch sử thay cho kết quả hiện tại. Các kiểm tra bắt buộc phải đạt trước khi merge.
6. **Không tự đổi DB/server dùng chung:** thay schema/routine/trigger phải thống nhất với người 1, có migration và quy trình backup/restore thử → preflight → migrate → check. Giữ nguyên dữ liệu/volume đang dùng; không init đè DB cũ, không đưa env, mật khẩu, token hoặc backup vào Git.
7. **Chỉ báo hoàn thành khi đã nối và kiểm thử:** giao diện dùng dữ liệu giả/demo hoặc feature chỉ có DB/khung file vẫn ghi rõ phần thiếu; giữ TODO/status planned cho phần chưa triển khai. Hoàn thành một luồng cần FE gọi API PHP thật, quyền được chặn ở BE và dữ liệu lưu/đọc đúng MySQL.

<a id="chia-se"></a>
## 9. Sao lưu, khôi phục và chia sẻ với đồng đội

Backup phải được lấy khi đã dừng các tác nhân ghi như8.2. `backup.py backup` mặc định đọc educenter-mysql/trung_tam_gia_su, lưu SQL/JSON riêng trong .cache/backups. Có thể chỉ định --container, --database, --output-dir.

Dump gồm dữ liệu, routine, trigger, event; dùng single-transaction, không đưa password root lên command line. Manifest có checksum/hash bảng và số routine/trigger; công cụ kiểm tra dữ liệu có thay đổi trong lúc dump không. Không coi chỉ có file SQL là đã kiểm chứng backup.

`verify` kiểm checksum rồi tạo container MySQL riêng không public cổng, khôi phục, so hash toàn bộ bảng/số object và dọn container thử. `--migrate` còn chạy thử preflight/migrate/check trên bản sao. Không ghi vào DB nguồn. Lưu thêm một bản riêng ngoài máy làm việc.

Đồng đội cần: repo/commit thống nhất, Dockerfile/compose trong Git, env riêng và SQL/JSON nếu cần dữ liệu giống nhau. Không cần gửi container/volume Docker hoặc mật khẩu root cá nhân. Backup chứa thông tin riêng và hash tài khoản; chỉ chia sẻ với người được phép qua kênh riêng, không đính kèm PR.

Khi rollback lỗi migration, giữ ứng dụng dừng, phục hồi lên **DB/container/volume mới**, so dữ liệu và cấp lại user API trên DB đích, sửa kết nối rồi mới mở ghi. Dump không kèm user/grant server. Giữ volume cũ để đối chiếu, không xóa volume thật hoặc import đè để thử.

<a id="kiem-thu"></a>
## 10. Kiểm thử và chạy không qua Docker

### 10.1. Các lệnh kiểm tra chính

```powershell
python database/tests/verify_structure.py
docker compose run --rm --no-deps backend composer test
pwsh -File backend/scripts/test.ps1
```

Structure chỉ đọc source/hash. Composer test chạy PHP core không ghi DB. Test.ps1 tạo container/network/DB dùng một lần, chạy PHP/API, 6 suite SQL, kiểm tra nâng cấp003/004→005 và backup/restore, rồi dọn tài nguyên của chính lượt test. Không cấu hình test với DB làm việc.

Các suite SQL: integrity11, regressions8, attributes20, workflows12, business_rules27, operations_ready20, tổng98 tình huống. Demo có 8 users, tài khoản bị khóa, chỉ seed trên DB tên kết thúc `_test`. Suite có COMMIT nên không chạy vào dữ liệu thật. Integration PHP còn yêu cầu TEST_WITH_DB=1. Muốn thử SQL riêng phải tạo DB `_test` rỗng, init → seed-demo → test → check với một file env thử riêng.

CI GitHub kiểm tra cấu trúc/reference, cú pháp JS của portal/demo, PHP/Composer, API/MySQL, migration và restore. Số kiểm tra lịch sử trong [Thông tin đồ án](thong-tin-do-an.md#tien-do) không thay cho kết quả lần chạy hiện tại.

### 10.2. Dùng MySQL CLI cài trực tiếp — tùy chọn

Chỉ dùng MySQL 8.0.16+ hoặc8.4, không thay bằng MariaDB. Tạo database/.env từ mẫu, điền DB_HOST/PORT/NAME/USER/PASSWORD/CHARSET/TIMEZONE và MYSQL_BIN. User migrator cần quyền DDL/DML/routine/trigger trên DB mục tiêu, khác user API. Nếu không dùng -Container, run.ps1 đọc file này và truyền mật khẩu qua option file tạm, xóa sau khi xong.

```powershell
pwsh -File database/scripts/run.ps1 -Action init -EnvFile database/.env
pwsh -File database/scripts/run.ps1 -Action check -EnvFile database/.env
```

Init vẫn chỉ DB rỗng; DB cũ dùng preflight/migrate/check. MYSQL_BIN có thể là đường dẫn đầy đủ tới mysql.exe. Nếu bị lỗi1418/1419 khi tạo function/trigger, DBA cần kiểm tra quyền/binary logging; không vô hiệu hóa kiểm tra trên server chung tùy tiện.

### 10.3. PHP cài trực tiếp — tùy chọn

Cần PHP 8.4, Composer và intl/mbstring/mysqli/zip/GD (PNG/JPEG). Env DB_HOST/PORT phải gọi được MySQL từ máy chạy PHP, SMTP cũng phải đổi host/port phù hợp; hostname mailpit chỉ hoạt động trong mạng Docker. Từ thư mục gốc repo, chạy các lệnh sau.

```powershell
cd backend
composer install
php spark serve --host 127.0.0.1 --port 5000
```

Mở một Terminal khác để chạy lệnh tiếp theo. Khi muốn trở lại thư mục gốc trong Terminal này, dừng server bằng Ctrl+C rồi chạy `cd ..`.

Đây là development server, không dùng public. Frontend là HTML/CSS/JS; backend PHP không chạy npm/Node server.

<a id="deploy"></a>
## 11. Triển khai public khi hoàn thiện BE/FE

Hiện có cấu hình release, chưa cấp VPS/domain/chứng chỉ/SMTP thật hoặc merchant thanh toán. Chỉ triển khai khi có tài nguyên của nhóm và các luồng ứng dụng đã nghiệm thu.

1. Chuẩn bị máy chủ Docker, MySQL 8.4 đã cài/migrate và mạng educenter-erp. Cấp user API riêng, giữ DB nội bộ.
2. Tạo config/deploy.env từ mẫu, điền EDUCENTER_DOMAIN thật và tên container DB. Trỏ DNS về server; mở TCP80/443, UDP443 nếu dùng HTTP/3.
3. Tạo config/server.env riêng: production, APP_BASE_URL=https://domain/, CORS đúng domain, secret/DB password riêng và SMTP thật. Không dùng Mailpit gửi thư thật.
4. Build/chạy stack release độc lập:

```powershell
docker compose --env-file config/deploy.env -f compose.production.yaml config --quiet
docker compose --env-file config/deploy.env -f compose.production.yaml up -d --build
docker compose --env-file config/deploy.env -f compose.production.yaml ps
docker compose --env-file config/deploy.env -f compose.production.yaml exec -T --user www-data backend php scripts/doctor.php
```

Image application chứa source/vendor từ composer.lock; document root backend/public. Stack public chỉ Caddy80/443, backend/DB đi mạng nội bộ; volume writable và chứng chỉ được giữ riêng. Không bind toàn repo hoặc dùng spark serve cho public. Caddy cấp/gia hạn HTTPS khi domain/DNS/cổng đáp ứng điều kiện: [tài liệu Caddy](https://caddyserver.com/docs/automatic-https).

PHP cấu hình timezone Việt Nam, tắt display_errors/expose_php; Apache tắt directory listing, có HTTP headers; Docker log tối đa3 file×10MB mỗi service được cấu hình. Sao lưu cả DB và writable nếu có tài liệu upload. Không chạy down -v trên stack có dữ liệu/certificate cần giữ.

SMTP thật dùng MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM_EMAIL/FROM_NAME. Thanh toán payOS cấu hình theo mục bên dưới. [Email CodeIgniter](https://codeigniter.com/user_guide/libraries/email.html), [Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/).

### 11.1. Cấu hình QR ngân hàng và payOS

1. Vào **Admin → Đối soát Thanh toán Web → Tải QR / Thêm tài khoản**. Chọn ảnh VietQR PNG/JPG dưới 750 KB lấy từ ứng dụng ngân hàng. Hệ thống đọc BIN và số tài khoản từ ảnh; nhập tên ngân hàng và chủ tài khoản, chọn loại tài khoản thật sau khi đã kiểm tra người nhận. Bấm lưu, sau đó **Chọn mặc định**. Tài khoản DEMO không tạo mã chuyển tiền thật.
2. Tạo kênh thanh toán tại payOS, liên kết đúng ngân hàng/số tài khoản đã chọn. Một cấu hình payOS hiện kết nối một kênh; chọn QR của tài khoản khác sẽ bị chặn khi tạo thanh toán nếu không khớp kênh đó. Trước khi đổi sang kênh khác, đối soát các yêu cầu cũ và hủy các yêu cầu chưa nhận tiền; chưa hỗ trợ nhiều kênh hoạt động đồng thời. Ảnh tĩnh là thông tin tài khoản; mã thanh toán từng hóa đơn được tạo lại bằng payOS để chứa số tiền và nội dung riêng.
3. Trong **config/server.env riêng trên máy chủ**, bổ sung các khóa sau. Không đưa khóa vào GitHub, ảnh chụp hoặc mã frontend.

```dotenv
PAYOS_ENABLED=true
PAYOS_CLIENT_ID=<client-id-cua-kenh>
PAYOS_API_KEY=<api-key-cua-kenh>
PAYOS_CHECKSUM_KEY=<checksum-key-cua-kenh>
PAYOS_PUBLIC_URL=https://ten-mien-cua-ban
PAYOS_RECONCILE_USER_ID=<user_id-admin-dang-hoat-dong>
```

`PAYOS_RECONCILE_USER_ID` là ID hồ sơ người dùng Admin, không phải account_id. Hệ thống ghi rõ nguồn xác minh tự động trong audit và dùng Admin này làm người chịu trách nhiệm phiếu thu theo ràng buộc DB 005. Không tự chọn Admin đầu tiên.

Tìm user_id trong cửa sổ SQL bằng truy vấn chỉ đọc:

```sql
SELECT a.username, u.user_id, u.hoten
FROM account a JOIN users u ON u.user_id = a.user_id
JOIN role_group g ON g.group_id = a.group_id
WHERE g.group_name = 'Admin' AND a.status = 'active'
  AND a.is_deleted = 0 AND u.is_deleted = 0 AND g.is_deleted = 0;
```

4. Khởi động lại backend để nhận env. Với Docker local dùng `docker compose up -d --force-recreate backend`; máy chủ public dùng cấu hình compose production ở mục 11. URL phải có HTTPS công khai để payOS gọi tới; localhost riêng không nhận được webhook từ payOS.
5. Trở lại **Đối soát Thanh toán Web → Kiểm tra kết nối webhook**. payOS gửi mẫu có chữ ký tới `/api/finance/tuition/payos/webhook`. Chỉ khi xác minh thành công mới bật tạo thanh toán tự động. Thay khóa, tên miền hoặc Admin đối soát cần xác minh lại. Mẫu kiểm tra không tạo hóa đơn/phiếu thu.
6. Phụ huynh chọn hóa đơn → **Thanh toán QR**. Mã thể hiện đúng số dư trọn khóa và mã chuyển khoản riêng; có thể mở cổng payOS. Sau khi chuyển, màn hình kiểm tra trạng thái định kỳ. **Kiểm tra giao dịch** đọc lại dữ liệu thực tế từ payOS; nút này không tự ghi nhận đã trả tiền.
7. Admin có thể bấm **Kiểm tra payOS** khi webhook bị gián đoạn. Thiếu/thừa tiền, nhiều lần chuyển, sai tài khoản/nội dung, hoặc công nợ đã thay đổi đều chờ đối soát và hiển thị lý do. Không yêu cầu phụ huynh chuyển thêm khi chưa kiểm tra. Nếu yêu cầu sai tài khoản hoặc cần tạo lại QR, dùng **Hủy yêu cầu**; chỉ hủy khi payOS xác nhận chưa nhận tiền. Xử lý hoàn tiền ngoài hệ thống nếu cần, theo chứng từ ngân hàng; chưa có API hoàn tiền tự động.

Ảnh QR được lưu ngoài thư mục public tại `backend/writable/uploads/payment-qr`; cần sao lưu/giữ volume writable cùng DB. Cài GD khi chạy PHP trực tiếp; Docker đã có GD. Khi chưa có credentials/webhook công khai, giao diện thông báo payOS chưa sẵn sàng và luồng VietQR hiện có chỉ gửi thông báo để Admin đối chiếu thực tế. Không xem kết quả quay về từ cổng, ảnh chuyển khoản hay nút bấm phụ huynh là bằng chứng đã nhận tiền.

Bộ test tự động chỉ dùng DB `_test` và phản hồi SDK có chữ ký giả lập; không thực hiện chuyển khoản thật. [payOS hiện không có sandbox riêng](https://payos.vn/docs/moi-truong-test/). Khi chủ tài khoản nghiệm thu kết nối thật, họ thực hiện và đối chiếu một khoản nhỏ có kiểm soát trên kênh production. Tham khảo [SDK PHP chính thức payOS](https://payos.vn/docs/sdks/back-end/php/) và [xác minh chữ ký webhook](https://payos.vn/docs/tich-hop-webhook/kiem-tra-du-lieu-voi-signature/).

<a id="xu-ly-loi"></a>
## 12. Xử lý lỗi thường gặp

| Hiện tượng | Cách xử lý |
| --- | --- |
| Thiếu Virtual Machine Platform | Làm1.1 bằng PowerShell Admin, reboot rồi mở Docker lại |
| Docker daemon không chạy | Mở Docker Desktop, chờ Engine running |
| Không nhận pwsh/python/git | Cài công cụ/PATH, mở terminal mới; kiểm tra version |
| Cổng3307 hoặc5000 bị chiếm | Dùng `Get-NetTCPConnection -LocalPort 3307 -State Listen` (hoặc thay5000) xác định tiến trình; không kill tiến trình chưa rõ hoặc xóa volume |
| Container MySQL đang dừng | Bấm Start hoặc chạy start-local.ps1; không tạo container trùng |
| Chỉ còn volume educenter_mysql_data | Giữ nguyên volume; khôi phục container dùng đúng volume và mật khẩu đã khởi tạo dưới sự quản lý của chủ dữ liệu. Script cố ý từ chối tự xử lý |
| Access denied ở DB | Kiểm tra đúng user/password/host/grant; root, API và web là3 tài khoản khác nhau |
| PHP container không gọi DB | Phải dùng hostname container:3306 trong mạng Docker, không gọi chính nó ở127.0.0.1:3307 |
| Init từ chối DB không rỗng | Dùng nâng cấp8.2, không DROP/schema.sql để vượt lỗi |
| Migration còn applying/VI_PHAM | Giữ backup và ứng dụng dừng; đối chiếu DDL/dữ liệu, không xóa marker/chạy --force |
| Login sai hoặc account locked | Dùng mật khẩu web; demo bị khóa. Chỉ Reset khi chủ động quản lý bản local |
| Doctor lỗi SMTP | Chạy `pwsh -File backend/scripts/start.ps1` để bật Mailpit hoặc kiểm tra cấu hình SMTP thật |
| Trang trắng hoặc giao diện cũ sau pull | Chạy lại `pwsh -File backend/scripts/start.ps1`, mở `/login` và nhấn Ctrl+F5 |
| Push bị rejected hoặc pull có conflict | Làm theo mục [Pull, commit và push code](#pull-push-code) |
| SQL báo thiếu snapshot/dấu vết lịch sử | Đối chiếu chứng từ gốc; không lấy giá hiện tại điền ngược quá khứ |
| Chưa thấy nút/tính năng Figma | Đối chiếu tiến độ; hình Figma và demo không phải FE đã nối API |

Trong PowerShell, dán cả **một khối lệnh hoàn chỉnh** rồi Enter; các lệnh độc lập chạy lần lượt, xem kết quả từng bước. Chỉ nhập SQL sau dấu nhắc mysql>, dùng EXIT; để quay lại PowerShell. Không dán dấu ``` của Markdown vào terminal.
