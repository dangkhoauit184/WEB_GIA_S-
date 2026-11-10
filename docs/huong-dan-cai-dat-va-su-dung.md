# Hướng dẫn cài đặt và sử dụng EduCenter ERP cho thành viên nhóm

Cập nhật 11/11/2026 (bổ sung quy tắc phối hợp nhóm), áp dụng schema `005_operations_ready`. Đây là tài liệu thao tác duy nhất cho máy mới, máy đang dùng, chia sẻ dữ liệu, kiểm thử và triển khai. Đặc tả và thiết kế nằm trong [Thông tin đồ án](thong-tin-do-an.md).

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
git switch main
git pull --ff-only origin main
```

Nếu đã clone thì không clone đè: vào đúng thư mục, kiểm tra `git status`, xử lý/lưu thay đổi cá nhân rồi mới pull. Có thể dùng đường dẫn khác; mọi lệnh trong tài liệu chạy từ **thư mục gốc repo**, nơi có compose.yaml. Mở terminal PowerShell 7 tại đó.

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

Điền DB_USER=educenter_api, DB_NAME=trung_tam_gia_su. Đặt **DB_PASSWORD riêng** và **JWT_SECRET ngẫu nhiên tối thiểu32 ký tự**. Có thể tự sinh mỗi chuỗi bằng lệnh sau rồi dán vào đúng trường:

```powershell
[Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
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
pwsh -NoProfile -File database/scripts/start-local.ps1
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
# Tùy chọn: thêm 5 khóa mẫu, đang tắt đăng ký và dùng giá minh họa.
pwsh -File database/scripts/run.ps1 -Action seed-courses -Container educenter-mysql -EnvFile config/server.env
```

`init` tự thực hiện schema → routines/triggers theo manifest → reference/policy → lịch sử applied001–005. Không chạy lần lượt từng file SQL bằng tay. Không cần chạy thêm migration sau init005. `check` phải không có dòng VI_PHAM khác0; CAN_XAC_NHAN cần đối chiếu nếu có dữ liệu lịch sử.

DB mới chưa có người dùng/học viên/lớp. Khóa mẫu không tạo đăng ký hoặc hóa đơn. Chưa có CRUD khóa học trên web; đây là dữ liệu chuẩn bị cho bước BE/FE.

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

Vào MySQL root như5B. Thay mật khẩu minh họa dưới đây bằng **đúng DB_PASSWORD của config/server.env trên máy bạn**:

```sql
CREATE USER 'educenter_api'@'%' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_DB_API_RIENG';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON trung_tam_gia_su.* TO 'educenter_api'@'%';
EXIT;
```

Nếu user đã có, dùng lại thông tin đúng; không đặt lại mật khẩu tùy tiện. Host% cho phép PHP container gọi MySQL trong mạng Docker, trong khi cổng DB trên Windows vẫn chỉ bind loopback. Không cấp user API quyền CREATE/ALTER/DROP/GRANT.

```powershell
pwsh -NoProfile -File backend/scripts/start.ps1
pwsh -NoProfile -File backend/scripts/doctor.ps1
```

Start bật lại DB, nối mạng, build PHP/Apache, composer install từ lock và bật backend/Mailpit. Lần đầu cần Internet, có thể mất vài phút. Script không xóa volume, init lại DB hoặc nạp demo vào DB làm việc.

| Địa chỉ | Dùng để làm gì |
| --- | --- |
| http://localhost:5000/ | Web PHP hiện có |
| http://localhost:5000/api/health | Kiểm tra PHP kết nối MySQL |
| http://localhost:5000/api | Manifest API/module |
| http://localhost:8025/ | Hộp thư SMTP thử Mailpit |

Health mong đợi success=true, timeZone=+07:00, isolationLevel=READ-COMMITTED. Doctor kiểm tra PHP/extensions, cấu hình production/JWT, user DB khác root, writable, migration005, timezone, SMTP và HTTP. Health/doctor đạt không đồng nghĩa đã có tất cả tính năng ERP.

<a id="tai-khoan"></a>
## 7. Tạo tài khoản web và sử dụng bản hiện có

Máy cài DB mới chạy:

```powershell
pwsh -NoProfile -File backend/scripts/bootstrap-admin.ps1
```

Script hỏi mật khẩu riêng bằng SecureString; username mặc định **admin**. Sau đó mở web và đăng nhập bằng mật khẩu vừa đặt. Không có mật khẩu Admin dùng chung được công bố trong repo.

Nếu DB khôi phục đã có admin, dùng mật khẩu web được chủ dữ liệu cấp. Chỉ khi chủ động đổi/kích hoạt tài khoản **trong bản local của bạn**, chạy:

```powershell
pwsh -NoProfile -File backend/scripts/bootstrap-admin.ps1 -Username admin -Reset
```

Không dùng Reset như một lệnh khởi động hằng ngày. Mật khẩu MySQL/root không phải mật khẩu ở màn đăng nhập web. Không nhập thông tin DB vào form login.

Bản web hiện có đăng nhập/đổi mật khẩu, tra cứu/tìm học viên, lớp và lịch qua Ajax; các API hồ sơ/quản trị nêu trong [tiến độ](thong-tin-do-an.md#tien-do). Các màn Figma chưa được code đầy đủ. Thanh toán, check-in, học bù, lương và thông báo chưa có đầy đủ API/màn hình dù DB đã hỗ trợ dữ liệu. Mailpit chỉ hứng thư thử, không tự gửi thông báo khi chưa có worker BE.

Demo HTML tại [docs/demo/index.html](demo/index.html) là bản minh họa dùng localStorage, có đăng nhập nhanh riêng, không gọi PHP/MySQL. Không dùng demo đó để kết luận hệ thống thật đã chạy một tính năng.

<a id="hang-ngay"></a>
## 8. Chạy hằng ngày và cập nhật mã nguồn

Mở Docker Desktop, đợi Engine running rồi chạy start.ps1. Nếu container/image/dependency không thay đổi, có thể bật nhanh:

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

Sau khi nhóm merge PR: kiểm tra git status, lưu thay đổi cá nhân, về nhánh phù hợp và pull; đọc thay đổi migration trước khi chạy. Thay code/dependency/Dockerfile/env thì chạy lại start.ps1. Không đưa vendor, writable, .cache, env, backup vào Git. Khi làm task mới dùng nhánh codex/ hoặc quy ước nhóm, mở PR để review, không ghi thẳng main.

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
4. **Mỗi task dùng nhánh và PR nhỏ:** cập nhật từ main sau khi đã lưu thay đổi cá nhân; tách PR theo một luồng hoặc thay đổi có thể review. PR ghi phần đã làm, API/quyền thay đổi, phụ thuộc, kết quả kiểm thử và phần còn thiếu. Người 1 review, tích hợp và kiểm thử luồng xuyên suốt trước khi merge; không ghi thẳng main.
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

Cần PHP>=8.2, khuyên8.4, Composer và intl/mbstring/mysqli/zip. Env DB_HOST/PORT phải gọi được MySQL từ máy chạy PHP, SMTP cũng phải đổi host/port phù hợp; hostname mailpit chỉ hoạt động trong mạng Docker. Từ backend/:

```powershell
composer install
php spark serve --host 127.0.0.1 --port 5000
```

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

SMTP thật dùng MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM_EMAIL/FROM_NAME; adapter gateway và webhook xác thực sẽ cài ở bước BE. [Email CodeIgniter](https://codeigniter.com/user_guide/libraries/email.html), [Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/).

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
| Doctor lỗi SMTP | Bật profile tools/mailpit hoặc sửa SMTP thật; không in password để debug |
| SQL báo thiếu snapshot/dấu vết lịch sử | Đối chiếu chứng từ gốc; không lấy giá hiện tại điền ngược quá khứ |
| Chưa thấy nút/tính năng Figma | Đối chiếu tiến độ; hình Figma và demo không phải FE đã nối API |

Trong PowerShell, dán cả **một khối lệnh hoàn chỉnh** rồi Enter; các lệnh độc lập chạy lần lượt, xem kết quả từng bước. Chỉ nhập SQL sau dấu nhắc mysql>, dùng EXIT; để quay lại PowerShell. Không dán dấu ``` của Markdown vào terminal.
