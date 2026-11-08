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
<!-- Đối chiếu docs/thong-tin-do-an.md; quyết định hiện hành ưu tiên Word/Figma cũ. -->
- [ ] Chống trùng lịch nhân sự/phòng và giới hạn sĩ số.
- [ ] Check-in/out chỉ bấm nút, không GPS/ảnh; phút công trong lịch, đến sau giờ lịch là trễ.
- [ ] Nghỉ cả lớp Admin duyệt, nghỉ cá nhân Giáo vụ; lịch bù không hạn mức/phí/đơn bắt buộc.
- [ ] Chỉ Admin xếp lớp, độc lập tình trạng học phí.
- [ ] Chỉ Admin xem/sửa đơn giá, duyệt/chốt/trả/mở công.
- [ ] Thu trọn khóa, không hoàn khi tự thôi học; thanh toán có đối soát và chống trùng.

### ✅ 5. Danh sách kiểm tra trước khi Merge (Pre-Merge Checklist)
- [ ] Đã chạy các kiểm tra phù hợp với thay đổi và ghi kết quả trong PR.
- [ ] Nếu thay đổi giao diện demo, đã kiểm tra trên Desktop và thiết bị di động.
- [ ] Không commit file nhạy cảm (`.env`, mật khẩu thật, file tạm).
- [ ] Đã chạy `python database/tests/verify_structure.py`; backend theo PHP/CodeIgniter MVC và module → feature.
- [ ] Nếu thay đổi PHP, đã chạy core test và HTTP/MySQL test trên database riêng có hậu tố `_test`.

### 👥 6. Người đánh giá (Reviewer)
<!-- Tag @username người phụ trách review code trước khi gộp vào nhánh chính -->
@
