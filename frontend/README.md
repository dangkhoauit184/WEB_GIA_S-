# Frontend

Frontend chỉ gồm **file khung HTML/CSS/JavaScript rỗng**, chưa có giao diện ứng dụng, thư viện hoặc công cụ build. `index.html` và `src/app.js` cũng được giữ rỗng.

## Cấu trúc

```text
frontend/
├── index.html
├── assets/images/
└── src/
    ├── app.js
    ├── config/app.config.js
    ├── modules/
    │   ├── index.js
    │   └── academic/
    │       ├── academic.module.js
    │       └── features/
    │           └── scheduling/
    │               ├── scheduling.html
    │               ├── scheduling.css
    │               └── scheduling.js
    └── shared/
        ├── components/
        ├── permissions/
        ├── services/
        ├── styles/
        └── utils/
```

Tất cả module và feature dùng cùng tên với backend; xem [danh sách chung](../shared/modules.json) và [quy ước cấu trúc](../docs/project-structure.md).

## Quy ước khi bắt đầu viết code

- HTML, CSS, logic riêng của một chức năng đặt trong thư mục feature đó.
- File `<module>.module.js` dự kiến ghép các feature của module; `modules/index.js` dự kiến ghép các module.
- Thành phần, style, dịch vụ HTTP và tiện ích dùng chung đặt trong `src/shared/`.
- Hằng số hoặc hợp đồng dùng cho cả hai phía đặt tại `shared/` ở thư mục gốc.
- Khi chưa được yêu cầu triển khai, giữ nguyên các file khung rỗng.

## Xem giao diện minh họa cũ

Mở [docs/demo/index.html](../docs/demo/index.html) bằng trình duyệt. Demo nằm trong tài liệu tham khảo và dùng localStorage; chưa gọi backend/MySQL. Hướng dẫn chi tiết tại [docs/frontend-demo.md](../docs/frontend-demo.md).
