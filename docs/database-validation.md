# Kết quả kiểm chứng database phiên bản 003

Ngày chạy: **03/11/2026**. Môi trường: MySQL Community Server **8.4.13**, Windows, InnoDB, utf8mb4, session `+07:00`, `READ COMMITTED`, PowerShell 7.

MySQL portable được khởi tạo riêng, chỉ lắng nghe localhost trên cổng thử nghiệm 33417, tắt binary logging riêng cho server thử nghiệm. Các database dùng một lần có tiền tố `edu003r4_` hoặc `edu003r5_` và hậu tố `_test`. Không sử dụng database người dùng.

> Đây là báo cáo của lần chạy ngày 03/11/2026, trước khi tổ chức lại module. Các con số về file khung và hash bên dưới mô tả cấu trúc tại thời điểm đó. Hướng dẫn và đường dẫn chạy lại đã được cập nhật.

## Kiểm thử cài mới

| Kiểm tra | Kết quả |
| --- | --- |
| Init schema 003 và seed danh mục | Thành công |
| Seed demo, giữ tài khoản demo bị khóa | Thành công |
| `integrity.sql` | 11/11 đạt |
| `regressions.sql` | 8/8 đạt |
| `attributes.sql` | 20/20 đạt |
| `workflows.sql` | 12/12 đạt |
| Tổng ca hành vi | **51/51 đạt** |
| `check` sau toàn bộ suite | Không có `VI_PHAM` |
| Kiểm tra cấu trúc và file tham chiếu | 15 hash bảo toàn, 18 feature, 198 file khung rỗng |

20 ca thuộc tính 003 bao gồm trạng thái đọc tự tính và không cho ghi thủ công; chi tiết hóa đơn khác lớp; yêu cầu web chưa phân công nhân viên; FK người gửi; lý do từ chối độc lập và khớp trạng thái; phát hành/hủy hóa đơn; hủy phiếu và cập nhật số dư; trả lương cần chốt và dấu vết; bảo vệ kỳ đã trả; hủy buổi học có dấu vết.

## Kiểm thử nâng cấp

| Đường đi hoặc tình huống | Kết quả |
| --- | --- |
| SQL gốc 35 bảng → 001 → 002 → 003 | Thành công; `check` không có `VI_PHAM` |
| Database đã áp dụng 001 → 002 → 003 | Thành công; `check` không có `VI_PHAM` |
| Database 002 có demo → 003 | Thành công; `check` không có `VI_PHAM` |
| Chạy lại migrate khi đã ở 003 | Không chạy lại DDL, giữ nguyên lịch sử migration |
| Trạng thái đọc mâu thuẫn với thời điểm đọc | Từ chối trước DDL và trước khi ghi `003 applying` |
| Lý do cũ có cả hai bên từ chối | Từ chối trước DDL và trước khi ghi `003 applying` |
| Lý do cũ chỉ người dạy từ chối | Chuyển nguyên văn sang đúng trường; trường phụ huynh giữ NULL |
| Thông báo đã đọc có thời điểm hợp lệ | Giữ nguyên thời điểm; cột tự tính trả về 1 |
| Dữ liệu sau nâng cấp từ 002 | Giữ 8 người dùng, 14 buổi học, lũy kế hóa đơn 1 là 180.000đ |
| Đơn vị giá và mốc phát hành lịch sử chưa biết | Giữ NULL |
| `created_at` bổ sung cho 3 ghi danh cũ | Giữ NULL, không tạo thời điểm giả |
| So cột giữa cài mới và cả ba đường nâng cấp | Trùng tên, kiểu, nullable, default, extra và biểu thức cột tự tính; bỏ qua thứ tự cột |

Baseline 002 lấy từ commit `123abf0`; bản SQL gốc được nạp vào database thử nghiệm riêng sau khi loại ba câu lệnh chọn/tạo/xóa database cố định trong bản sao dùng để chạy thử. File tham chiếu trong repository được giữ nguyên byte. Các suite hành vi 51 ca chạy trên cài mới; các đường nâng cấp được kiểm tra bằng báo cáo dữ liệu, giá trị cần bảo toàn và đối chiếu cấu trúc cột.

## Phạm vi còn cần xác minh

Demo có một lớp thu phí tháng chưa xác định `hoc_phi_thang`; giữ NULL. Dữ liệu nâng từ bản gốc có snapshot cách tính lương hoặc dấu vết vận hành chưa xác minh; báo `CAN_XAC_NHAN`, không suy ngược từ cấu hình lớp hiện tại.

Chưa kiểm chứng tải đồng thời, khôi phục backup, phân quyền/TLS/binary logging của hạ tầng triển khai hoặc MySQL 8.0. Kết quả local không thay thế trạng thái CI trên GitHub.

## Chạy lại bộ kiểm thử trong repository

```powershell
python database/tests/verify_structure.py --scaffold-only
# Cấu hình database dùng một lần có hậu tố _test:
pwsh -File database/scripts/run.ps1 -Action init
pwsh -File database/scripts/run.ps1 -Action seed-demo
pwsh -File database/scripts/run.ps1 -Action test
pwsh -File database/scripts/run.ps1 -Action check
```

Để kiểm tra nâng cấp, phục hồi bản sao database gốc/001/002 vào một database dùng một lần khác, rồi chạy `preflight`, `migrate`, `check`. Giữ backup trước migration vì MySQL DDL tự COMMIT. Suite quy trình có COMMIT riêng và làm thay đổi demo, không chạy trên dữ liệu thật.
