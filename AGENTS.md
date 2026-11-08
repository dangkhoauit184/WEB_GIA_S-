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
