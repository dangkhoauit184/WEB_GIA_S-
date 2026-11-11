# Frontend EduCenter ERP

Khung HTML/CSS/JavaScript đặt tại src/modules/module/features/feature; component/style/service dùng chung nằm tại src/shared/. Manifest module/feature: [shared/modules.json](../shared/modules.json).

- [Cấu trúc và cách tích hợp với PHP/Ajax](../docs/thong-tin-do-an.md#kien-truc).
- [Figma và các màn cần bổ sung/chỉnh sửa](../docs/thong-tin-do-an.md#figma).
- [Nghiệp vụ/quyền và API](../docs/thong-tin-do-an.md#quy-tac).
- [Cài/chạy dự án và dùng bản web hiện có](../docs/huong-dan-cai-dat-va-su-dung.md).
- [Giao diện gốc tham khảo](../reference/Educenter.html).

Cổng Admin chạy tại `/admin`, view PHP nằm trong backend và gọi API bằng các module JavaScript ở frontend/src. Những feature còn lại theo trạng thái trong đặc tả. Tạo lại template/style Admin bằng `python backend/scripts/build-admin-assets.py` khi cập nhật bản giao diện tham chiếu.
