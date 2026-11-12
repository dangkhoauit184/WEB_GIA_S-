# Frontend EduCenter ERP

Khung HTML/CSS/JavaScript đặt tại src/modules/module/features/feature; component/style/service dùng chung nằm tại src/shared/. Manifest module/feature: [shared/modules.json](../shared/modules.json).

- [Cấu trúc và cách tích hợp với PHP/Ajax](../docs/thong-tin-do-an.md#kien-truc).
- [Figma và các màn cần bổ sung/chỉnh sửa](../docs/thong-tin-do-an.md#figma).
- [Nghiệp vụ/quyền và API](../docs/thong-tin-do-an.md#quy-tac).
- [Cài/chạy dự án và dùng bản web hiện có](../docs/huong-dan-cai-dat-va-su-dung.md).
- [Giao diện gốc tham khảo](../docs/demo/index.html).

Trang giới thiệu chạy tại `/`, đăng nhập tại `/login`, cổng phụ huynh tại `/parent`; Admin và Giáo vụ dùng `/admin` và `/staff`. PHP phục vụ các trang/asset frontend cùng origin và các module gọi API qua `/api`. Feature còn lại theo trạng thái trong đặc tả. Tạo lại template/style Admin và Giáo vụ bằng `python backend/scripts/build-admin-assets.py` khi cập nhật bản giao diện tham chiếu.
