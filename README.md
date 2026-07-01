# HeThongQLSV

Hệ thống quản lý sinh viên (QLSV) gồm API PHP và giao diện web đơn giản.

Website: https://quanlysv.74tt21.software

## Tính năng
- Phân quyền theo vai trò: admin, teacher, student
- Quản lý lớp, môn, người dùng
- Bài tập, bài nộp, chấm điểm
- Thông báo và tài liệu
- Tải lên tệp

## Công nghệ
- PHP 8.x, Apache
- MySQL
- HTML/CSS/JS 

## Cấu trúc thư mục
- api/ : REST API, controllers, core utilities
- public/ : UI pages, JS, CSS, uploads
- database/ : schema và dữ liệu mẫu

## Chạy local (tùy chọn)
1. Tạo database MySQL và import database/schema.sql
2. Cập nhật thông tin kết nối trong api/config/database.php
3. Chạy bằng Docker hoặc Apache/PHP local

Docker nhanh:
1. Build: docker build -t qlsv .
2. Run: docker run -p 8080:8080 qlsv
3. Mở: http://localhost:8080

## Cấu hình
- DB config: api/config/database.php
- Uploads: public/uploads/
- API base URL: public/js/common.js (tự nhận diện)
testccccccccc