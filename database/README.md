# Cơ sở dữ liệu EduCenter ERP

Database phục vụ đồ án quản lý trung tâm gia sư và đào tạo. Phiên bản hiện tại: **003_attribute_review**, gồm 36 bảng, 2 function, 10 procedure và 25 trigger.

## Yêu cầu và cấu hình

- MySQL 8.0.16+ hoặc 8.4 LTS, MySQL CLI, PowerShell 7+.
- InnoDB, utf8mb4, session `+07:00`, giao dịch ghi `READ COMMITTED`.
- Tài khoản triển khai có quyền DDL, routine, trigger và DML trên database được chọn. Tài khoản API dùng quyền tối thiểu riêng.

```powershell
Copy-Item database/.env.example database/.env
```

Điền cấu hình `KEY=value` nguyên văn, không đặt dấu nháy hoặc comment cuối dòng. `MYSQL_BIN` nhận `mysql` trong PATH hoặc đường dẫn tuyệt đối đến `mysql.exe`. Dùng `-EnvFile <đường-dẫn>` nếu đặt cấu hình ngoài repository. Mật khẩu được chuyển qua option file tạm, không đặt lên command line; file tạm được xóa khi kết thúc.

## Cài mới

```powershell
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action check
```

`init` từ chối database không rỗng. Script nạp `schema.sql`, các module trong `install-order.json`, seed danh mục rồi đánh dấu 001/002/003 đã áp dụng. Chưa tạo tài khoản Admin hay dữ liệu người dùng.

## Nâng cấp từ SQL gốc, 001 hoặc 002

1. Sao lưu và thử khôi phục; dừng ghi ứng dụng. Kiểm tra trước trên bản sao.
2. Chạy `-Action preflight`, xử lý các dòng `VI_PHAM` bằng cách đối chiếu nguồn dữ liệu.
3. Chạy `-Action migrate`, sau đó `-Action check`.
4. Database gốc chạy thay đổi cấu trúc 001, ghi nhận 002, áp dụng 003 và nạp toàn bộ routine/trigger mới. Database 001/002 chỉ chạy phần còn thiếu. Database đã ở 003 được kiểm tra, không chạy lại DDL.
5. Gặp phiên bản lạ hoặc migration `applying`, script dừng. DDL MySQL tự COMMIT: cần đối chiếu tiến độ và khôi phục backup hoặc lập kế hoạch sửa tiếp, không xóa cờ để chạy lại mù quáng.

```powershell
pwsh -File database/scripts/run.ps1 -Action preflight
pwsh -File database/scripts/run.ps1 -Action migrate
pwsh -File database/scripts/run.ps1 -Action check
```

`migrations/003_preflight.sql` dùng tên cột cũ và chạy trước DDL của 003. Các trường hợp cần đối chiếu gồm chi tiết hóa đơn sai học viên/lớp, trạng thái đọc mâu thuẫn với `doc_luc`, lý do từ chối không xác định được đúng một bên và số dư hóa đơn lệch phiếu thu/hoàn. Script dừng trước khi ghi `003 applying` khi gặp các vấn đề này.

Không import `schema.sql` lên database cũ. `reference/schema-original.sql` có `DROP DATABASE`, chỉ dùng tham khảo. Không chạy riêng file migration hoặc dùng mysql `--force` để bỏ qua lỗi.

## Thay đổi thuộc tính ở 003

| Thay đổi | Quy tắc chuyển đổi |
| --- | --- |
| Bỏ `hoa_don_chi_tiet.hoc_vien_id` | Học viên được xác định qua hóa đơn và ghi danh. Vẫn kiểm tra buổi học phải cùng lớp với hóa đơn. |
| `thong_bao.da_doc` tự tính từ `doc_luc` | Giữ tên cột và index để truy vấn, chỉ cập nhật `doc_luc`. Phải xử lý mâu thuẫn trước migration, không tự tạo thời điểm đọc. |
| Tách lý do từ chối đề xuất | Chuyển lý do cũ sang đúng bên đang từ chối. Nếu cả hai hoặc không bên nào từ chối mà có lý do, preflight yêu cầu đối chiếu. |
| Yêu cầu từ website | Thêm `nguoi_gui_id`, cho phép `nhan_vien_id` NULL trước khi phân công; khách chưa đăng nhập vẫn dùng thông tin liên hệ. |
| Đơn vị giá và phản hồi | Thêm `don_vi_ngan_sach`, `cach_thu_phi_de_xuat`, thời điểm phản hồi hai bên và tài khoản phụ huynh phản hồi. |
| Phát hành/hủy/chi trả | Thêm người, thời điểm, lý do hủy và mã giao dịch chi. Thao tác phát hành/hủy mới phải cung cấp dấu vết; chuyển trả lương phải từ kỳ đã chốt và có ngày trả khớp thời điểm trả. |
| Timestamps và email | Bổ sung thời điểm chỉnh sửa, tạo kết quả/ghi danh và các mốc gửi/thử lại email. |

Đơn vị giá và dấu vết lịch sử chưa biết giữ NULL. Thời điểm tạo mới bổ sung cho ghi danh/kết quả cũ cũng giữ NULL; default thời điểm hiện tại chỉ áp dụng cho bản ghi mới. `check` báo `CAN_XAC_NHAN` để rà soát; không điền người thao tác bằng người lập hay suy giá từ lớp hiện tại.

Giữ `account.account_id` và `role_group_function.id` vì khóa đơn vẫn hợp lệ và chưa có lợi ích đủ lớn để đổi. Giữ snapshot đơn giá/cách tính lương trên phân công, các tổng quyết toán và địa điểm buổi học. Các cột tự tính `so_phut_thuc_te`, `thuc_nhan`, `phai_thu`, `day_chinh_key` tiếp tục phục vụ nghiệp vụ và ràng buộc.

## Trách nhiệm của API

- Xác thực người dùng, kiểm tra quyền chức năng và phạm vi cơ sở/con/phân công. Truyền một `user_id` hợp lệ chưa chứng minh người gọi có quyền.
- Gửi đơn vị ngân sách/giá, phản hồi hai bên và thông tin người/thời điểm thực hiện khi phát sinh thao tác.
- Chuyển hóa đơn nháp sang phát hành cùng `phat_hanh_boi`, `phat_hanh_luc`; khi hủy gửi `huy_boi`, `huy_luc`, `ly_do_huy`. Hủy buổi dùng `nguoi_huy_id`, `huy_luc`, `ly_do_huy_doi`.
- Trả lương một lần cho kỳ đã chốt bằng `tra_boi`, `tra_luc`, `ngay_tra` và mã giao dịch nếu có. Kỳ đã trả được bảo vệ; chi trả nhiều đợt cần thiết kế bảng chứng từ riêng.
- Đối chiếu tổng hóa đơn với chi tiết trước phát hành; retry toàn giao dịch khi deadlock.
- Các procedure `sp_sinh_buoi_hoc`, `sp_chot_cong`, `sp_mo_khoa_cong` quản lý transaction riêng, phải CALL độc lập ngoài transaction của caller.

Chính sách phí vắng, giá riêng theo ghi danh, GPS/bán kính, học bù riêng học viên lớp nhóm, nhiều phụ huynh hoặc nhiều bài chấm trong một buổi còn cần chốt thiết kế. Chưa tự thêm cột hay quy tắc cho các phương án này.

## Kiểm thử

```powershell
python database/tests/verify_structure.py --scaffold-only
# DB_NAME phải kết thúc bằng _test, đã init và chưa có người dùng:
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action test
pwsh -File database/scripts/run.ps1 -Action check
```

51 ca: 11 toàn vẹn, 8 hồi quy 002, 20 thuộc tính 003, 12 quy trình. Demo có 8 người dùng; tài khoản demo bị khóa. Suite quy trình có COMMIT và thay đổi demo, chỉ chạy trên database dùng một lần. Mức phí tháng chưa xác định vẫn giữ NULL.

Kết quả thực tế và phạm vi xác minh tại [tests/VALIDATION.md](tests/VALIDATION.md). Các module nạp theo `install-order.json`; seed danh mục không được nạp lại để ghi đè quyền của database đang dùng.
