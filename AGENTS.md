# Quy ước công nghệ của dự án

- Backend của EduCenter ERP phải sử dụng **PHP**, theo yêu cầu người dùng ngày 08/11/2026.
- Database sử dụng **MySQL 8.4**. Môi trường phát triển hiện chạy MySQL bằng Docker, truy cập từ Windows qua `127.0.0.1:3307`.
- Các feature backend triển khai tiếp theo cần tuân theo lựa chọn PHP và cấu trúc module → feature của dự án.
- Căn cứ đề cương IS207 người dùng cung cấp (`C:/Users/dkhoa/Downloads/IS207 (1).pdf`, 21 trang):
  - Trang 3: G3.1/G3.2 yêu cầu PHP và kết nối, truy vấn MySQL trong PHP; G5.1/G5.2 đề cập MVC và CodeIgniter.
  - Trang 6-8: nội dung giảng dạy, thực hành và rubric G5 có CodeIgniter, MVC, Ajax.
  - Trang 17-19: bài tập 3 yêu cầu sử dụng framework PHP và tổ chức code theo MVC.
  - Trang 21: đồ án A1 gồm khảo sát, phân quyền, thiết kế và nhập dữ liệu, giao diện, chức năng tìm kiếm/in ấn/thêm-xóa-sửa/thống kê/hiển thị/gửi mail, triển khai server và báo cáo.
- Backend dùng **CodeIgniter 4**, MVC và Ajax, đã chuyển từ Node.js theo yêu cầu người dùng. PHP 8.4 chạy qua Docker; Composer lock được commit, `vendor/` không commit.
- Đối chiếu EduCenter với bộ tiêu chí đồ án A1 ở trang 21. Các đề giỏ hàng, bán hàng và SB-Admin 2 trong phần bài tập được xem trong đúng phạm vi bài tập tương ứng.
- Mã nghiệp vụ nằm tại `backend/app/Modules/<Module>/Features/<Feature>/`. Không thêm server Node.js song song.
- Giữ nguyên dữ liệu MySQL đang dùng. Chỉ chạy integration test trên database riêng có tên kết thúc `_test`, với `TEST_WITH_DB=1`.
- Giữ 4 README của dự án và 1 mẫu PR. Feature chưa triển khai giữ khung PHP có chú thích TODO, không quảng cáo là hoàn tất.
- Người dùng đã yêu cầu ưu tiên chốt nghiệp vụ, thiết kế DB và môi trường server trước khi viết tiếp backend/frontend. Câu hỏi về việc "nên code chưa" không phải lệnh triển khai. Chỉ viết tiếp tính năng khi người dùng yêu cầu thực hiện trong phạm vi đã rõ.
- Quyết định nghiệp vụ mới và câu hỏi còn mở nằm tại `docs/business-rules-baseline.md`; ưu tiên các quyết định người dùng đã xác nhận so với Word/Markdown cũ. Không tự biến phần đề xuất hoặc câu hỏi còn mở thành quy tắc đã chốt; không áp dụng migration chỉ vì đã có bản rà soát.
- Người dùng đã yêu cầu áp dụng DB theo nghiệp vụ mới. Phiên bản hiện hành `004_business_rules`: thu trọn khóa, KHÔNG có kỳ thu/hạn mức/phí bù; giáo vụ xếp bù theo thỏa thuận; chỉ Admin quản trị công/lương. Check-in 18:10 → out 20:05 trên lịch 18–20 tính 110 phút/trễ; out 19:30 tính 80 phút/trễ. Đơn giá tiền/giờ tăng từ lúc thay đổi, giữ ca đã dạy.
- Xác nhận tiếp theo 08/11/2026: chỉ bấm check-in/out, không GPS/ảnh, không giới hạn đến sớm/về muộn; giữ cách tính giao khung lịch và trễ ở trên. Bù do gia đình/trung tâm thỏa thuận ngoài hệ thống, giáo vụ chỉ lưu lịch loại bù; không bắt buộc đơn xin bù. Chỉ Admin xếp lớp, độc lập với tình trạng thanh toán. Phát hành học phí thông báo phụ huynh; thanh toán xác nhận xong Admin và giáo vụ đều xem được.
- DB mới nhất `005_operations_ready`: dữ liệu yêu cầu thanh toán/webhook chống trùng, phiếu thu xác nhận Admin, thông báo học phí, dấu vết xếp lớp và chính sách check-in. Xem `docs/db-server-readiness.md` trước khi viết BE/FE. Chưa tích hợp cổng thanh toán/worker email hoặc cấp VPS/domain; không báo là tính năng web đã xong chỉ vì có DB/server.
