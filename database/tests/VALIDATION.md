# Kiểm thử bản clean Database 002

Đã chạy ngày **02/11/2026** trên MySQL Community Server **8.4.13**, Windows, InnoDB, utf8mb4, session +07:00 và READ COMMITTED. Server portable chỉ lắng nghe localhost trên cổng thử nghiệm riêng, không cài dịch vụ hoặc đụng database người dùng. Binary logging tắt riêng trên server thử nghiệm.

| Kiểm tra | Kết quả |
| --- | --- |
| Init từ bộ module mới | Thành công: 36 bảng, 23 trigger, 2 function + 10 procedure |
| Seed tham chiếu và demo | Thành công, tài khoản demo vẫn khóa |
| integrity.sql | 11/11 đạt |
| regressions.sql | 8/8 đạt |
| workflows.sql | 12/12 đạt |
| Tổng ca hành vi | **31/31 đạt** |
| Gọi migrate khi đã ở 002 | Không chạy lại DDL hoặc sửa dữ liệu |
| SQL gốc 35 bảng → 001 → 002 | Thành công, kiểm tra dữ liệu không có VI_PHAM |
| Database đã có 001 → 002 | Thành công, không chạy lại ALTER của 001 |
| Dữ liệu sau nâng cấp từ 001 | Giữ 8 người dùng, 14 buổi học, số đã thu hóa đơn 1 là 180.000đ |
| So cột giữa database mới và database nâng từ gốc | 0 khác biệt tên/kiểu/null/default/generated expression |
| verify_structure.py --scaffold-only | 15 hash bảo toàn, 18 feature, 198 file rỗng; không có mẫu trong frontend |

## Phạm vi các suite

| Suite | Các hành vi đã kiểm tra |
| --- | --- |
| Toàn vẹn | FK, giá âm, sĩ số INSERT/UPDATE, thu vượt nợ, hoàn vượt số dư, lũy kế, đổi danh tính/hủy phiếu, học bù sai gốc, snapshot phân công mới |
| Hồi quy 002 | Công thức/round tiền công, snapshot NULL, chuyển nợ hóa đơn, đổi ID phiếu, dời ca sang kỳ khóa, xóa ca trong kỳ khóa, khôi phục buổi gốc có ca bù, chốt khi trạng thái công chưa khớp |
| Quy trình | Sinh lại không trùng, xung đột phòng/người/trợ giảng khi đổi lịch, cùng tên phòng khác cơ sở, giảm sức chứa, đơn công chờ, lương theo snapshot, khóa công, quyền mở khóa, chốt lại và audit |

## Những dữ liệu vẫn cần xác nhận

Demo có 1 lớp phí tháng chưa có `hoc_phi_thang`; giữ NULL theo tài liệu chưa xác định giá. Nâng cấp từ SQL nguyên gốc giữ 15 snapshot cách tính lương quá khứ ở NULL; không tự đoán giá trị từ lớp hiện tại. Đây là CAN_XAC_NHAN, không phải dữ liệu bị xóa hoặc lỗi import.

Chưa kiểm thử tải đồng thời, khôi phục backup, quyền/TLS/binary logging của hạ tầng production hoặc MySQL 8.0. CI mới được cấu hình để chạy test MySQL trên GitHub; kết quả local không thay cho trạng thái CI từ xa.

## Chạy lại

```powershell
python database/tests/verify_structure.py
python database/tests/verify_structure.py --scaffold-only
# Sau init và seed demo trên database dùng một lần _test:
pwsh -File database/scripts/run.ps1 -Action test
pwsh -File database/scripts/run.ps1 -Action check
```

`test` chạy ba suite theo thứ tự. Workflow gọi procedure có COMMIT riêng và thay đổi demo, chỉ chạy trên database thử nghiệm dùng một lần. Không dùng mysql --force. Lỗi không dự kiến sẽ làm suite thất bại.
