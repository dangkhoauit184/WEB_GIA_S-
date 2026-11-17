# Frontend EduCenter ERP

Khung HTML/CSS/JavaScript đặt tại src/modules/module/features/feature; component/style/service dùng chung nằm tại src/shared/. Manifest module/feature: [shared/modules.json](../shared/modules.json).

- [Cấu trúc và cách tích hợp với PHP/Ajax](../docs/thong-tin-do-an.md#kien-truc).
- [Figma và các màn cần bổ sung/chỉnh sửa](../docs/thong-tin-do-an.md#figma).
- [Nghiệp vụ/quyền và API](../docs/thong-tin-do-an.md#quy-tac).
- [Cài/chạy dự án và dùng bản web hiện có](../docs/huong-dan-cai-dat-va-su-dung.md).
- [Giao diện gốc tham khảo](../docs/demo/index.html).

Đăng nhập chung tại `/login`, PHP chọn cổng Admin, Giáo vụ, Phụ huynh hoặc Giáo viên tại `/app`. Giao diện Giáo viên nằm trong `src/modules/people/features/teachers/`, dùng CSS chung và API PHP/MySQL. Phạm vi từng cổng được ghi trong đặc tả. Tạo lại template/style Admin và Giáo vụ bằng `python backend/scripts/build-admin-assets.py` khi cập nhật bản giao diện tham chiếu.
