# Bàn giao DB/server trước khi code BE/FE

Phạm vi: cơ sở dữ liệu, chính sách đã chốt, cấu hình máy chủ và công cụ vận hành. Không thêm API tính năng/màn hình FE. Nguồn: [baseline](business-rules-baseline.md).

## Database 005_operations_ready

- Bảo toàn 41 bảng cũ; thêm `giao_dich_truc_tuyen` và `su_kien_thanh_toan`, thành 43 bảng. Thêm `xep_boi/xep_luc` cho ghi danh lớp, NULL với lịch sử chưa biết người xếp.
- Yêu cầu thanh toán gắn đúng hóa đơn/phụ huynh, VND, số dư phải thu. Có nhiều lần thử; mã yêu cầu và mã giao dịch provider chống trùng. Webhook có khóa provider/event và hash payload; không lưu số thẻ/secret.
- Luồng `khoi_tao → cho_xac_nhan → thanh_cong`, hoặc thất bại/hết hạn/hủy. Thành công cần phiếu thu đúng hóa đơn, tiền và Admin xác nhận. Không tạo trực tiếp thành công, sửa tiền hoặc kích hoạt lại giao dịch đã kết thúc.
- `sp_xac_nhan_giao_dich(giao_dich_id, admin_user_id)` khóa hóa đơn rồi giao dịch, tạo một phiếu, cập nhật số dư/audit trong transaction. Gọi lại trả phiếu cũ. Nhiều lần thử không được thu vượt số dư. Actor lấy từ xác thực BE, không từ body tùy ý.
- Lưu webhook không tăng tiền. BE sẽ xác minh chữ ký, merchant/order/amount/VND/kết quả với provider trước xử lý; redirect/click “đã chuyển” không được xác nhận tiền. Chưa tích hợp provider hoặc nhận tiền thật.
- Phát hành hóa đơn khóa tạo thông báo web cho phụ huynh. Phiếu thu tạo thông báo cho phụ huynh, Admin/giáo vụ active, chống trùng theo hóa đơn/phiếu. DB không gửi email/HTTP.
- Ghi danh khóa mới cần Admin/thời điểm; không kiểm tra tiền. Legacy không khóa giữ tương thích, không đoán actor cũ.
- Bù cá nhân lưu buổi/học viên/giáo vụ; `don_xin_nghi_id` tùy chọn, không hạn mức/phí. Giữ đơn nghỉ lịch sử.
- `checkin_truoc_phut=-1`, `checkout_sau_phut=-1` nghĩa không giới hạn; `checkin_minh_chung=khong`. GPS nullable không bắt buộc. Phút công lấy giao khung lịch, không tính lại lịch sử trước004.
- 5 khóa ở `seeders/003_courses_example.sql`: tên theo gợi ý, giá minh họa, `is_active=0`. Admin sửa rồi bật khi làm CRUD. Action `seed-courses` thêm theo mã, không ghi đè khóa đã chỉnh; không tạo đăng ký/hóa đơn giả và không tự chạy trong migration.

## Nâng cấp từ 004

Từ repo, dùng Docker/PowerShell7. Giữ env, container/volume MySQL cũ:

1. Dừng ghi DB: `docker compose stop backend` và dừng các tác nhân ghi khác nếu có.
2. `python database/scripts/backup.py backup` — SQL/JSON manifest trong `.cache/backups/`, giữ riêng.
3. `python database/scripts/backup.py verify "DUONG_DAN_SQL_VUA_TAO" --migrate` — restore container riêng, không mở cổng, so hash toàn bộ bảng/số routine/trigger, thử migration và so cột nghiệp vụ cũ, rồi dọn container thử.
4. `pwsh -File database/scripts/run.ps1 -Action preflight -Container educenter-mysql -EnvFile config/server.env`
5. `pwsh -File database/scripts/run.ps1 -Action migrate -Container educenter-mysql -EnvFile config/server.env`
6. `pwsh -File database/scripts/run.ps1 -Action check -Container educenter-mysql -EnvFile config/server.env`
7. `pwsh -File database/scripts/run.ps1 -Action seed-courses -Container educenter-mysql -EnvFile config/server.env` để thêm khóa mẫu tắt đăng ký.
8. `powershell -ExecutionPolicy Bypass -File backend/scripts/start.ps1`
9. `pwsh -File backend/scripts/doctor.ps1`

Migrate nhận001/002/003/004, nâng phần còn thiếu lên005. Init chỉ DB rỗng: schema → module theo install-order → reference → policy004/005 → applied. Giữ applied_at cũ; migrate005 lặp chỉ kiểm tra. DDL lỗi/applying phải dừng đối chiếu, không xóa marker tùy tiện vì DDL MySQL không rollback đầy đủ.

Khôi phục khi lỗi: giữ ứng dụng dừng, restore bản sao lên MySQL/volume mới, so dữ liệu, cấp user/quyền và cấu hình kết nối mới trước khi mở ghi. Dump DB không chứa user/grant cấp server. Không import đè/xóa volume thật để thử rollback.

## Server

- PHP8.4/Apache document root `backend/public`, production, timezone Việt Nam, không hiện lỗi/PHP version, không directory listing, có HTTP headers/log rotation.
- MySQL hiện có chỉ public loopback3307, nội bộ3306. Compose dùng lại DB/network/volume, không init/seed test vào DB thật.
- SMTP local Mailpit tại `http://localhost:8025/`, chỉ hứng thư trên máy. Config Email qua `MAIL_*`; worker thông báo sẽ viết trong BE.
- `doctor.ps1`: PHP/extensions, secret đã cấu hình (không in giá trị), user DB khác root, timezone/isolation/version005, writable, SMTP/HTTP headers. Không sửa DB/gửi thư.
- `backup.py`: dump routine/trigger/event, checksum/hash dữ liệu; verify restore riêng, không đụng nguồn. Lưu thêm bản riêng ngoài máy trước triển khai thật.
- `compose.production.yaml`: image release, writable volume, Caddy HTTPS reverse proxy, healthcheck/log rotation; không bind toàn repo hoặc public DB/backend.

Hướng dẫn public: [server-setup](server-setup.md). Chưa có VPS/domain/SMTP thật/merchant, nên không báo đã deploy public/thanh toán thật. Local đủ để chuyển sang code; kết nối ngoài được điền khi triển khai, không cần thiết kế lại DB.

## Thứ tự code BE tiếp theo

1. API cũ: chỉ Admin xếp lớp (cả legacy), truyền actor từ phiên; lọc mọi trường lương khỏi response cho người khác. TeachersModel/ClassesModel hiện còn trả trường lương; lần DB/server này chưa sửa feature code.
2. CRUD khóa/đăng ký đúng con phụ huynh; giáo vụ chốt/phát hành hóa đơn. Dùng snapshot/thông báo DB, không bắt có lớp trước hóa đơn.
3. API thanh toán + adapter bank/provider, idempotency/đối soát Admin/webhook xác thực; trang kết quả lấy trạng thái DB.
4. Admin xếp lớp độc lập tiền; giáo vụ xếp lịch/bù, scope theo đặc tả; không thêm quy trình xin/duyệt bù.
5. Check-in/out giờ server đúng người/ca, không giới hạn sớm/muộn, không ảnh/GPS. Điều chỉnh công Admin có audit, lương theo phút công.
6. Điểm danh/bài chấm/nghỉ, chốt/chi lương Admin, báo cáo.
7. Thông báo/email worker; FE gọi API từng luồng; kiểm thử người dùng khi được yêu cầu, cấu hình public/SMTP/payment thật, nghiệm thu IS207.

Đây là phần triển khai tiếp theo, không phải các câu hỏi cần chốt lại.

## Kiểm chứng kỹ thuật

`operations_ready.sql` dùng DB `_test`: không GPS/ảnh, sớm/muộn không tăng công, bù không cần đơn, Admin xếp khi chưa hóa đơn, thông báo học phí, đúng phụ huynh/số tiền, chống trùng request/webhook, không tự xác nhận, Admin đối soát lặp không thu hai lần, thông báo các bên. `verify_upgrade.py` kiểm tra baseline003 và004→005, giữ dữ liệu/applied_at, cài mới giống nâng cấp, migrate lặp không đổi dữ liệu. Không gọi ngân hàng/gửi thư người thật.

Đã thực hiện ngày08/11/2026:

- 353 kiểm tra PHP/API/SQL đạt; 220 file PHP lint và Composer lock hợp lệ. Nâng từ003/004, cài mới, lặp migration và từ chối init DB không rỗng đều đạt.
- DB thật đã backup, restore/upgrade bản sao, rồi nâng005. So hash các cột nghiệp vụ cũ và timestamp migration không đổi; chỉ cấu hình/chính sách mới cùng5 khóa mẫu tắt đăng ký được bổ sung có chủ đích. Backup SQL/JSON nằm riêng `.cache/backups/`, không đưa lên Git.
- DB thật43 bảng/57 trigger/20 routine; check không có VI_PHAM và không còn CAN_XAC_NHAN khác0 ở thời điểm kiểm tra.
- Doctor PHP/MySQL/SMTP/HTTP đạt; không gửi thư. Script tạo MySQL được thử trên container/volume riêng: tạo mới, dừng/bật vẫn giữ dữ liệu, từ chối tự tạo lại khi chỉ còn volume.
- Image application build được; Apache configtest, Caddy validate và Compose release config hợp lệ. Chưa chạy chứng chỉ public hoặc thanh toán thật.
