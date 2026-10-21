## 📌 Tiêu đề Pull Request (PR)
<!-- Quy ước đặt tên: <type>(<scope>): <mô tả ngắn> -->
<!-- Ví dụ: feat(schedule): bổ sung kiểm tra trùng lịch giáo viên BR01 -->
<!-- Ví dụ: fix(leave): sửa lỗi trạng thái đơn nghỉ của phụ huynh -->

### 📝 1. Mô tả chi tiết thay đổi (Description)
<!-- Tóm tắt các nội dung chính được cập nhật trong PR này -->
- 

### 🏷️ 2. Loại thay đổi (Type of Change)
- [ ] 🚀 **Tính năng mới (Feature):** Thêm chức năng mới mà không phá vỡ logic cũ.
- [ ] 🐛 **Sửa lỗi (Bug fix):** Khắc phục lỗi hoặc hành vi không mong muốn.
- [ ] 🎨 **Giao diện (UI/UX):** Cải tiến layout, màu sắc, responsive hoặc styling.
- [ ] ♻️ **Tái cấu trúc (Refactor):** Cải thiện mã nguồn mà không đổi chức năng.
- [ ] 📚 **Tài liệu (Docs):** Bổ sung, cập nhật tài liệu đồ án hoặc README.
- [ ] ⚙️ **Cấu hình (DevOps/CI-CD):** Cập nhật workflow, package hoặc gitignore.

### 🔍 3. Phân hệ & Vai trò liên quan (Module & Role)
- [ ] 🛡️ Quản trị viên (Admin)
- [ ] 📋 Nhân viên / Giáo vụ (Staff)
- [ ] 👨‍🏫 Giảng viên / Gia sư 1:1 (Teacher/Tutor)
- [ ] 🤝 Trợ giảng (TA)
- [ ] 👪 Phụ huynh (Parent)
- [ ] 🗄️ Database / Backend API

### 📜 4. Quy tắc nghiệp vụ liên quan (Business Rules)
<!-- Đánh dấu quy tắc nghiệp vụ trong Đặc tả v2.0 được áp dụng -->
- [ ] `BR01`: Chống trùng lịch giáo viên / phòng học
- [ ] `BR04/BR05`: Check-in / Check-out GPS
- [ ] `BR08`: Đơn xin nghỉ phép học viên
- [ ] `BR09`: Xếp lịch dạy bù
- [ ] `BR11/BR12`: Khóa sổ bảng công và thù lao tháng
- [ ] `BR14/BR15`: Quản lý học phí và hóa đơn

### ✅ 5. Danh sách kiểm tra trước khi Merge (Pre-Merge Checklist)
- [ ] [x] Mã nguồn đã được kiểm tra chạy thử và không có lỗi cú pháp JavaScript.
- [ ] [x] Giao diện hiển thị tốt trên cả Desktop và thiết bị di động.
- [ ] [x] Không commit file nhạy cảm (`.env`, mật khẩu thật, file tạm).
- [ ] [x] Đã đồng bộ dữ liệu vào `localStorage` nếu có thay đổi state.

### 👥 6. Người đánh giá (Reviewer)
<!-- Tag @username người phụ trách review code trước khi gộp vào nhánh chính -->
@
