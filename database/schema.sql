DROP DATABASE IF EXISTS quan_ly_sinh_vien;

CREATE DATABASE quan_ly_sinh_vien CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quan_ly_sinh_vien;

CREATE TABLE nguoi_dung (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ten_dang_nhap VARCHAR(50) NOT NULL UNIQUE,
    mat_khau VARCHAR(255) NOT NULL,
    ho_ten VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    vai_tro ENUM('admin', 'gv', 'sv') NOT NULL DEFAULT 'sv',
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mon_hoc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ten_mon VARCHAR(100) NOT NULL,
    so_tin_chi INT NOT NULL DEFAULT 3,
    mo_ta TEXT
);

CREATE TABLE lop_hoc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mon_hoc INT NOT NULL,
    id_giao_vien INT,
    ten_lop VARCHAR(100) NOT NULL,
    hoc_ky VARCHAR(20),
    si_so_toi_da INT DEFAULT 50,
    FOREIGN KEY (id_mon_hoc) REFERENCES mon_hoc(id) ON DELETE CASCADE,
    FOREIGN KEY (id_giao_vien) REFERENCES nguoi_dung(id) ON DELETE SET NULL
);

CREATE TABLE dang_ky (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_sinh_vien INT NOT NULL,
    id_lop INT NOT NULL,
    diem_giua_ky FLOAT,
    diem_cuoi_ky FLOAT,
    ngay_dang_ky TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sinh_vien) REFERENCES nguoi_dung(id) ON DELETE CASCADE,
    FOREIGN KEY (id_lop) REFERENCES lop_hoc(id) ON DELETE CASCADE,
    UNIQUE(id_sinh_vien, id_lop)
);

CREATE TABLE tai_lieu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tieu_de VARCHAR(200) NOT NULL,
    duong_dan_file VARCHAR(255) NOT NULL,
    nguoi_upload INT NOT NULL,
    id_lop INT DEFAULT NULL,
    ngay_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nguoi_upload) REFERENCES nguoi_dung(id) ON DELETE CASCADE,
    FOREIGN KEY (id_lop) REFERENCES lop_hoc(id) ON DELETE CASCADE
);

CREATE TABLE bai_tap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_lop INT NOT NULL,
    tieu_de VARCHAR(200) NOT NULL,
    mo_ta TEXT,
    han_nop DATETIME,
    file_de_bai VARCHAR(255),
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_lop) REFERENCES lop_hoc(id) ON DELETE CASCADE
);

CREATE TABLE bai_nop (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_bai_tap INT NOT NULL,
    id_sinh_vien INT NOT NULL,
    file_bai_lam VARCHAR(255) NOT NULL,
    diem FLOAT,
    nhan_xet TEXT,
    ngay_nop TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_bai_tap) REFERENCES bai_tap(id) ON DELETE CASCADE,
    FOREIGN KEY (id_sinh_vien) REFERENCES nguoi_dung(id) ON DELETE CASCADE
);

CREATE TABLE thong_bao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tieu_de VARCHAR(200) NOT NULL,
    noi_dung TEXT NOT NULL,
    nguoi_gui INT NOT NULL,
    id_lop INT DEFAULT NULL,
    ngay_tao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nguoi_gui) REFERENCES nguoi_dung(id) ON DELETE CASCADE,
    FOREIGN KEY (id_lop) REFERENCES lop_hoc(id) ON DELETE CASCADE
);

CREATE TABLE diem_danh (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_lop INT NOT NULL,
    id_sinh_vien INT NOT NULL,
    ngay_diem_danh DATE NOT NULL,
    trang_thai ENUM('co_mat', 'vang_co_phep', 'vang_khong_phep') DEFAULT 'co_mat',
    ghi_chu VARCHAR(255),
    FOREIGN KEY (id_lop) REFERENCES lop_hoc(id) ON DELETE CASCADE,
    FOREIGN KEY (id_sinh_vien) REFERENCES nguoi_dung(id) ON DELETE CASCADE
);

INSERT INTO nguoi_dung (ten_dang_nhap, mat_khau, ho_ten, email, vai_tro) VALUES
('admin', '123456', 'Quan tri he thong', 'admin@qlsv.vn', 'admin'),
('gv_anh', '123456', 'Nguyen Van Anh', 'anh.gv@qlsv.vn', 'gv'),
('gv_binh', '123456', 'Tran Thi Binh', 'binh.gv@qlsv.vn', 'gv'),
('sv01', '123456', 'Pham Van A', 'sv01@qlsv.vn', 'sv'),
('sv02', '123456', 'Nguyen Thi B', 'sv02@qlsv.vn', 'sv'),
('sv03', '123456', 'Tran Van C', 'sv03@qlsv.vn', 'sv');

INSERT INTO mon_hoc (ten_mon, so_tin_chi, mo_ta) VALUES
('Lap trinh C co ban', 3, 'Nhap mon lap trinh C'),
('Lap trinh PHP', 3, 'Lap trinh web voi PHP va MySQL'),
('Co so du lieu', 3, 'Thiet ke va quan ly CSDL');

INSERT INTO lop_hoc (id_mon_hoc, id_giao_vien, ten_lop, hoc_ky, si_so_toi_da) VALUES
(1, 2, 'C01', 'HK1-2026', 60),
(2, 3, 'PHP01', 'HK1-2026', 55),
(3, 2, 'CSDL01', 'HK2-2026', 50);

INSERT INTO dang_ky (id_sinh_vien, id_lop, diem_giua_ky, diem_cuoi_ky) VALUES
(4, 1, 7.5, 8.0),
(5, 1, 6.5, 7.0),
(4, 2, 7.0, 7.5),
(6, 2, 8.0, 8.5),
(5, 3, 7.0, 7.5);

INSERT INTO thong_bao (tieu_de, noi_dung, nguoi_gui, id_lop) VALUES
('Chao mung sinh vien', 'Chao mung cac ban den voi he thong.', 2, 1),
('Thong bao chung', 'Sinh vien theo doi lich hoc thuong xuyen.', 1, NULL);

INSERT INTO diem_danh (id_lop, id_sinh_vien, ngay_diem_danh, trang_thai) VALUES
(1, 4, '2026-03-01', 'co_mat'),
(1, 5, '2026-03-01', 'vang_co_phep'),
(2, 4, '2026-03-02', 'co_mat'),
(2, 6, '2026-03-02', 'vang_khong_phep');
