# QuanLySinhVien

Du an web chay tren localhost XAMPP theo mo hinh RESTful API, tach rieng giao dien HTML/CSS/JS va day du 3 vai tro:

- Admin
- Giang vien
- Sinh vien

## Cau truc du an

- `api/`: Backend REST API (PHP + PDO + session role)
- `public/pages/`: Login, Admin, Teacher, Student
- `public/js/`: JS theo tung vai tro
- `public/css/style.css`: giao dien dung chung
- `database/schema.sql`: CSDL day du 9 bang + du lieu mau

## Huong dan chay nhanh

1. Dat thu muc `QuanLySinhVien` vao `htdocs` cua XAMPP.
2. Mo phpMyAdmin va import file `database/schema.sql`.
3. Kiem tra ket noi DB trong `api/config/database.php`.
4. Truy cap: `http://localhost/QuanLySinhVien/`

## Tai khoan mau

- Admin: `admin / 123456`
- Giang vien: `gv_anh / 123456`
- Sinh vien: `sv01 / 123456`

## Cac module da co

- Auth: dang nhap, dang xuat, lay thong tin user hien tai
- Admin: thong ke, CRUD nguoi dung, mon hoc, lop hoc, tai lieu, thong bao
- Giang vien: lop phu trach, diem danh, bai tap, cham diem bai nop, cap nhat diem tong ket, tai lieu, thong bao
- Sinh vien: lop da dang ky, lop mo dang ky, dang ky lop, chi tiet lop, nop bai, xem thong bao

## API chinh

- `GET /QuanLySinhVien/api/health`
- `POST /QuanLySinhVien/api/auth/login`
- `POST /QuanLySinhVien/api/auth/logout`
- `GET /QuanLySinhVien/api/auth/me`
- `GET|POST|PUT|DELETE /QuanLySinhVien/api/admin/*`
- `GET|POST|PUT|PATCH|DELETE /QuanLySinhVien/api/teacher/*`
- `GET|POST /QuanLySinhVien/api/student/*`

## Upload

Du lieu file duoc luu trong:

- `public/uploads/bai_tap/`
- `public/uploads/bai_nop/`
- `public/uploads/tai_lieu/`
