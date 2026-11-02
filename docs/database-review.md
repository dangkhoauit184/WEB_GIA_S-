# Rà soát Database EduCenter ERP

Ngày rà soát: 02/11/2026. Đã đối chiếu SQL nguồn, tài liệu Word tại `docs/quy_trinh_nghiep_vu_v2.docx`, bản gốc tại `docs/reference/business-process-original.docx` và tài liệu `docs/architecture.md`. Không sửa nội dung nghiệp vụ nguồn.

## Mô hình hiện tại

Giữ 35 bảng nghiệp vụ MySQL và một bảng kỹ thuật schema_migrations. Danh sách toàn bộ, PK và FK nằm trong README gốc repo. Không chia bảng theo cổng giao diện: Giáo viên/Gia sư/Trợ giảng dùng nhan_su_chuyen_mon, Phụ huynh dùng users và liên kết hoc_vien.phu_huynh_user_id. Giữ 5 nhóm quyền trong Word, 8 phân hệ và ma trận seed gốc.

## Các thay đổi so với SQL gốc ở phiên bản 001

| Thay đổi | Lý do |
| --- | --- |
| Tách schema, seed, routine, trigger; bỏ DROP DATABASE khỏi schema mới | Thiết lập database mà không xóa dữ liệu đang có |
| lop_hoc.hoc_phi_thang nullable | Có chỗ lưu giá tháng nhưng không đoán từ giá buổi |
| buoi_hoc.co_so_id, FK và index phòng | Kiểm tra phòng theo cơ sở/ngày, giữ tên phòng dạng chuỗi |
| phan_cong_buoi_hoc.cach_tinh_luong nullable | Snapshot cách tính theo buổi/giờ, tránh tính lại quá khứ theo lớp hiện tại |
| FK bảng công và chi tiết học phí chuyển SET NULL sang RESTRICT | Không làm mất dấu vết quyết toán/chứng từ |
| CHECK thời gian, tiền, cặp GPS, điểm và công hợp lệ | Chặn dữ liệu sai ở mức quan hệ/miền giá trị |
| Trigger INSERT/UPDATE cho sĩ số, lịch, học vụ, thu/hoàn | Kiểm tra cả tạo mới và sửa đổi, khóa tài nguyên liên quan |
| Procedure sinh lịch/chốt công/mở khóa đúng tên cột | Khớp schema thực tế và giữ giao dịch/audit |
| Seed demo tách riêng, khóa tài khoản có hash công khai | Không đưa tài khoản demo hoạt động vào production |

## Rà soát và hoàn thiện ở phiên bản 002

| Vị trí | Sửa/thêm | Tình huống được chặn |
| --- | --- | --- |
| routines/functions.sql | Tách fn_trung_lich, thêm fn_tien_cong thuần DETERMINISTIC NO SQL | Công thức theo snapshot, giá âm/NULL hoặc cách tính không rõ |
| routines/helpers.sql | Tách 7 helper, kiểm tra kỳ công đích và hóa đơn tồn tại | Dời một ca chưa khóa vào tháng đã chốt; NULL khiến kiểm tra tiền bị bỏ qua |
| routines/procedures.sql | Kiểm tra user tồn tại và công hợp lệ khớp trạng thái; gọi function tính tiền | Im lặng bỏ sót công có trạng thái buổi/phân công chưa hoàn thành |
| triggers/timekeeping.sql | Bảo vệ ID, xóa ca trong kỳ khóa, khóa cùng người dạy khi ghi đơn điều chỉnh | Xóa ca chưa có bang_cong_id sau chốt; đổi ID; tranh chấp ghi đơn/chốt |
| triggers/scheduling.sql | Giữ ID và quan hệ buổi gốc đã có ca bù | Khôi phục lịch gốc làm ca bù thành sai quan hệ |
| triggers/tuition.sql | Giữ ID phiếu và danh tính/kỳ hóa đơn đã có chứng từ | Chuyển nợ sang học viên khác sau khi đã thu tiền |
| install-order.json và run.ps1 | Một manifest, migration incremental 001/002, applying/applied | Nạp sai thứ tự, chạy lại DDL mù quáng, nhận nhầm phiên bản/hệ quản trị |
| checks.sql, regressions.sql, CI | Thêm báo cáo dữ liệu sai và 8 ca hồi quy | Chỉ đếm đủ object nhưng chưa xác nhận hành vi |

002 không ALTER/DROP bảng hoặc sửa dữ liệu nghiệp vụ. Các vị trí thay đổi SQL đều có comment tiếng Việt ngay tại chỗ. Bản gộp phiên bản 001 có thể xem trong lịch sử Git; file khung ban đầu vẫn tại docs/reference/database/procedures.sql.

## Các điểm chưa được phép suy đoán

| Chủ đề | Nội dung cần xác nhận | Cách giữ hiện tại |
| --- | --- | --- |
| Quyền | README cũ/giao diện có 6 cổng, Word có 5 nhóm | Không tự thêm nhóm Gia sư thứ sáu |
| Quyền chốt | Markdown giao giáo vụ chốt, ma trận gốc thiếu quyền phân hệ 6 cho Nhân viên | Giữ ma trận; routine nhận Admin/NhanVien như mô tả, API cần xác thực quyền chi tiết |
| Mã BR | Word và Markdown dùng BR02/03 cho nghiệp vụ khác nhau, chưa có đủ 20 quy tắc | Dẫn tên nghiệp vụ, không bịa quy tắc còn thiếu |
| GPS | Word ghi nếu có, Markdown đòi bán kính bắt buộc | Chỉ kiểm tra cặp/miền tọa độ, chưa áp bán kính hoặc tin vị trí client |
| Phí | Giá tháng và khi nào vắng tính phí chưa xác định | Giá tháng NULL, chưa tự sinh hóa đơn từ đơn nghỉ |
| Học bù | Cách bù riêng một học viên lớp nhóm chưa rõ | Không tự hủy buổi nhóm khi học viên nghỉ |
| Lương cũ | Cách tính lớp hiện tại có thể khác quá khứ | Migration giữ snapshot NULL, phải xác minh hợp đồng trước chốt lại |
| Kỳ đã trả | Quy trình sửa lương đã trả chưa định nghĩa | Chỉ mở khóa bảng da_chot, không tự mở da_tra |
| Hợp đồng | Phí khóa, nhiều hóa đơn một kỳ, ngày lễ, phạt/thưởng chưa đủ chi tiết | Giữ UNIQUE/kỳ gốc, không tự đặt chính sách |
| Đơn khác | Giao diện có đổi người/đổi lịch nhưng Word chưa đặc tả đầy đủ | Chưa thêm bảng/trạng thái giả định |

## Trách nhiệm khi phát triển API

Database không xác thực được danh tính người dùng web chỉ bằng tham số user_id. API phải kiểm tra quyền trợ giảng không chấm điểm/đổi giáo án, phụ huynh chỉ xem con mình, giới hạn chi nhánh, thời điểm check-in/cờ trễ theo cấu hình, và đối chiếu tổng hóa đơn với chi tiết khi phát hành. Khóa DB/trigger không thay cho phân quyền ứng dụng; DBA có DDL luôn có thể vượt ràng buộc.

Các kiểm tra khóa đã chạy trên một kết nối cho từng ca; chưa coi đó là kiểm chứng tải đồng thời. Backend cần READ COMMITTED, transaction và retry deadlock. Kết quả chạy thực tế tại database/tests/VALIDATION.md.
