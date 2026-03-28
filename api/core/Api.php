<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class Api
{
    private PDO $db;

    public function __construct()
    {
    }

    private function ensureDb(): void
    {
        if (!isset($this->db)) {
            $this->db = Database::connection();
        }
    }

    public function run(string $method, array $segments): void
    {
        if (($segments[0] ?? '') === 'health') {
            Response::json(['success' => true, 'message' => 'API is running', 'time' => date(DATE_ATOM)]);
            return;
        }

        if (($segments[0] ?? '') === 'auth') {
            $this->authRoutes($method, $segments);
            return;
        }

        if (($segments[0] ?? '') === 'admin') {
            $this->requireRole(['admin']);
            $this->adminRoutes($method, $segments);
            return;
        }

        if (($segments[0] ?? '') === 'teacher') {
            $this->requireRole(['gv']);
            $this->teacherRoutes($method, $segments);
            return;
        }

        if (($segments[0] ?? '') === 'student') {
            $this->requireRole(['sv']);
            $this->studentRoutes($method, $segments);
            return;
        }

        Response::error('Endpoint khong ton tai.', 404);
    }

    private function authRoutes(string $method, array $segments): void
    {
        $action = $segments[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $this->ensureDb();
            $body = $this->body();
            $username = trim((string) ($body['ten_dang_nhap'] ?? ''));
            $password = trim((string) ($body['mat_khau'] ?? ''));

            if ($username === '' || $password === '') {
                Response::error('Vui long nhap tai khoan va mat khau.', 422);
                return;
            }

            $stmt = $this->db->prepare('SELECT id, ten_dang_nhap, ho_ten, email, vai_tro FROM nguoi_dung WHERE ten_dang_nhap = :u AND mat_khau = :p LIMIT 1');
            $stmt->execute(['u' => $username, 'p' => $password]);
            $user = $stmt->fetch();

            if (!$user) {
                Response::error('Sai tai khoan hoac mat khau.', 401);
                return;
            }

            $_SESSION['user'] = $user;
            Response::json(['success' => true, 'message' => 'Dang nhap thanh cong.', 'data' => $user]);
            return;
        }

        if ($method === 'POST' && $action === 'logout') {
            session_destroy();
            Response::json(['success' => true, 'message' => 'Da dang xuat.']);
            return;
        }

        if ($method === 'GET' && $action === 'me') {
            if (!isset($_SESSION['user'])) {
                Response::error('Chua dang nhap.', 401);
                return;
            }
            Response::json(['success' => true, 'data' => $_SESSION['user']]);
            return;
        }

        Response::error('Auth endpoint khong ton tai.', 404);
    }

    private function adminRoutes(string $method, array $segments): void
    {
        $this->ensureDb();
        $resource = $segments[1] ?? '';
        $id = isset($segments[2]) ? (int) $segments[2] : null;

        if ($method === 'GET' && $resource === 'stats') {
            $stats = [
                'soHocSinh' => (int) $this->db->query("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro='sv'")->fetchColumn(),
                'soGiaoVien' => (int) $this->db->query("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro='gv'")->fetchColumn(),
                'soLopHoc' => (int) $this->db->query('SELECT COUNT(*) FROM lop_hoc')->fetchColumn(),
            ];
            Response::json(['success' => true, 'data' => $stats]);
            return;
        }

        if ($resource === 'users') {
            $this->crudUsers($method, $id);
            return;
        }

        if ($resource === 'subjects') {
            $this->crudSubjects($method, $id);
            return;
        }

        if ($resource === 'classes') {
            $this->crudClasses($method, $id);
            return;
        }

        if ($resource === 'announcements') {
            $this->crudAnnouncements($method, $id, true);
            return;
        }

        if ($resource === 'materials') {
            $this->crudMaterials($method, $id, true);
            return;
        }

        Response::error('Admin endpoint khong ton tai.', 404);
    }

    private function teacherRoutes(string $method, array $segments): void
    {
        $this->ensureDb();
        $teacherId = (int) $_SESSION['user']['id'];
        $resource = $segments[1] ?? '';

        if ($method === 'GET' && $resource === 'classes') {
            $classId = isset($segments[2]) ? (int) $segments[2] : null;
            $tail = $segments[3] ?? null;

            if ($classId === null) {
                $sql = 'SELECT lh.*, mh.ten_mon FROM lop_hoc lh
                        LEFT JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                        WHERE lh.id_giao_vien = :id
                        ORDER BY lh.id DESC';
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $teacherId]);
                Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
                return;
            }

            if ($method === 'GET' && $tail === 'students') {
                $this->assertTeacherClass($teacherId, $classId);
                $sql = 'SELECT nd.id, nd.ho_ten, nd.email, dk.diem_giua_ky, dk.diem_cuoi_ky
                        FROM dang_ky dk
                        JOIN nguoi_dung nd ON nd.id = dk.id_sinh_vien
                        WHERE dk.id_lop = :id
                        ORDER BY nd.ho_ten';
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $classId]);
                Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
                return;
            }
        }

        if ($resource === 'attendance') {
            $this->teacherAttendance($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'assignments') {
            $this->teacherAssignments($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'submissions') {
            $this->teacherSubmissions($method, $segments);
            return;
        }

        if ($resource === 'grades') {
            $this->teacherGrades($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'announcements') {
            $this->crudAnnouncements($method, isset($segments[2]) ? (int) $segments[2] : null, false, $teacherId);
            return;
        }

        if ($resource === 'materials') {
            $this->crudMaterials($method, isset($segments[2]) ? (int) $segments[2] : null, false, $teacherId);
            return;
        }

        Response::error('Teacher endpoint khong ton tai.', 404);
    }

    private function studentRoutes(string $method, array $segments): void
    {
        $this->ensureDb();
        $studentId = (int) $_SESSION['user']['id'];
        $resource = $segments[1] ?? '';

        if ($method === 'GET' && $resource === 'my-classes') {
            $sql = 'SELECT lh.*, mh.ten_mon, dk.diem_giua_ky, dk.diem_cuoi_ky
                    FROM dang_ky dk
                    JOIN lop_hoc lh ON lh.id = dk.id_lop
                    JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                    WHERE dk.id_sinh_vien = :id
                    ORDER BY lh.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $studentId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'GET' && $resource === 'available-classes') {
            $sql = 'SELECT lh.*, mh.ten_mon,
                    (SELECT COUNT(*) FROM dang_ky dk WHERE dk.id_lop = lh.id) AS si_so_hien_tai
                    FROM lop_hoc lh
                    JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                    WHERE lh.id NOT IN (
                        SELECT id_lop FROM dang_ky WHERE id_sinh_vien = :sv
                    )
                    ORDER BY lh.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sv' => $studentId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST' && $resource === 'enroll') {
            $body = $this->body();
            $idLop = (int) ($body['id_lop'] ?? 0);
            if ($idLop <= 0) {
                Response::error('Lop hoc khong hop le.', 422);
                return;
            }
            try {
                $stmt = $this->db->prepare('INSERT INTO dang_ky (id_sinh_vien, id_lop) VALUES (:sv, :lop)');
                $stmt->execute(['sv' => $studentId, 'lop' => $idLop]);
                Response::json(['success' => true, 'message' => 'Dang ky thanh cong.'], 201);
            } catch (PDOException $e) {
                Response::error('Khong the dang ky (co the da ton tai).', 409);
            }
            return;
        }

        if ($resource === 'classes') {
            $idLop = isset($segments[2]) ? (int) $segments[2] : 0;
            $tail = $segments[3] ?? '';

            if ($method === 'GET' && $idLop > 0 && $tail === '') {
                $this->assertStudentClass($studentId, $idLop);
                $detail = $this->studentClassDetail($studentId, $idLop);
                Response::json(['success' => true, 'data' => $detail]);
                return;
            }

            if ($method === 'GET' && $idLop > 0 && $tail === 'assignments') {
                $this->assertStudentClass($studentId, $idLop);
                $sql = 'SELECT bt.*,
                            (SELECT bn.id FROM bai_nop bn WHERE bn.id_bai_tap = bt.id AND bn.id_sinh_vien = :sv LIMIT 1) AS da_nop
                        FROM bai_tap bt
                        WHERE bt.id_lop = :lop
                        ORDER BY bt.id DESC';
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['sv' => $studentId, 'lop' => $idLop]);
                Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
                return;
            }
        }

        if ($resource === 'assignments') {
            $assignmentId = isset($segments[2]) ? (int) $segments[2] : 0;
            $tail = $segments[3] ?? '';
            if ($method === 'POST' && $assignmentId > 0 && $tail === 'submit') {
                $this->studentSubmitAssignment($studentId, $assignmentId);
                return;
            }
        }

        if ($method === 'GET' && $resource === 'announcements') {
            $sql = 'SELECT tb.*, nd.ho_ten AS nguoi_gui_ten, lh.ten_lop
                    FROM thong_bao tb
                    JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                    LEFT JOIN lop_hoc lh ON lh.id = tb.id_lop
                    WHERE tb.id_lop IS NULL OR tb.id_lop IN (
                        SELECT id_lop FROM dang_ky WHERE id_sinh_vien = :sv
                    )
                    ORDER BY tb.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sv' => $studentId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        Response::error('Student endpoint khong ton tai.', 404);
    }

    private function teacherAttendance(string $method, array $segments, int $teacherId): void
    {
        if ($method === 'POST' && ($segments[2] ?? '') === '') {
            $body = $this->body();
            $idLop = (int) ($body['id_lop'] ?? 0);
            $ngay = (string) ($body['ngay_diem_danh'] ?? '');
            $danhSach = $body['danh_sach'] ?? [];

            $this->assertTeacherClass($teacherId, $idLop);
            $delete = $this->db->prepare('DELETE FROM diem_danh WHERE id_lop = :lop AND ngay_diem_danh = :ngay');
            $delete->execute(['lop' => $idLop, 'ngay' => $ngay]);

            $ins = $this->db->prepare('INSERT INTO diem_danh (id_lop, id_sinh_vien, ngay_diem_danh, trang_thai, ghi_chu)
                                       VALUES (:lop, :sv, :ngay, :tt, :gc)');
            foreach ($danhSach as $item) {
                $ins->execute([
                    'lop' => $idLop,
                    'sv' => (int) $item['id_sinh_vien'],
                    'ngay' => $ngay,
                    'tt' => $item['trang_thai'] ?? 'co_mat',
                    'gc' => $item['ghi_chu'] ?? null,
                ]);
            }

            Response::json(['success' => true, 'message' => 'Da luu diem danh.']);
            return;
        }

        if ($method === 'GET' && isset($segments[2])) {
            $idLop = (int) $segments[2];
            $this->assertTeacherClass($teacherId, $idLop);
            $date = $_GET['date'] ?? null;
            $search = trim((string) ($_GET['search'] ?? ''));

            $sql = 'SELECT dd.*, nd.ho_ten, nd.email
                    FROM diem_danh dd
                    JOIN nguoi_dung nd ON nd.id = dd.id_sinh_vien
                    WHERE dd.id_lop = :lop';
            $params = ['lop' => $idLop];
            if ($date) {
                $sql .= ' AND dd.ngay_diem_danh = :ngay';
                $params['ngay'] = $date;
            }
            if ($search !== '') {
                $sql .= ' AND (nd.ho_ten LIKE :q OR nd.email LIKE :q)';
                $params['q'] = '%' . $search . '%';
            }
            $sql .= ' ORDER BY dd.ngay_diem_danh DESC, nd.ho_ten';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && isset($segments[2])) {
            $id = (int) $segments[2];
            $body = $this->body();
            $stmt = $this->db->prepare('UPDATE diem_danh SET trang_thai = :tt, ghi_chu = :gc WHERE id = :id');
            $stmt->execute([
                'tt' => $body['trang_thai'] ?? 'co_mat',
                'gc' => $body['ghi_chu'] ?? null,
                'id' => $id,
            ]);
            Response::json(['success' => true, 'message' => 'Da cap nhat diem danh.']);
            return;
        }

        Response::error('Attendance endpoint khong ton tai.', 404);
    }

    private function teacherAssignments(string $method, array $segments, int $teacherId): void
    {
        if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === '') {
            $idLop = (int) $segments[2];
            $this->assertTeacherClass($teacherId, $idLop);
            $stmt = $this->db->prepare('SELECT * FROM bai_tap WHERE id_lop = :id ORDER BY id DESC');
            $stmt->execute(['id' => $idLop]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'GET' && isset($segments[2]) && ($segments[3] ?? '') === 'submissions') {
            $assignmentId = (int) $segments[2];
            $sql = 'SELECT bn.*, nd.ho_ten, nd.email
                    FROM bai_nop bn
                    JOIN nguoi_dung nd ON nd.id = bn.id_sinh_vien
                    WHERE bn.id_bai_tap = :id
                    ORDER BY bn.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $assignmentId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST' && ($segments[2] ?? '') === '') {
            $idLop = (int) ($_POST['id_lop'] ?? 0);
            $this->assertTeacherClass($teacherId, $idLop);
            $fileName = null;
            if (isset($_FILES['file_de_bai']) && $_FILES['file_de_bai']['error'] === 0) {
                $fileName = $this->saveUploadedFile($_FILES['file_de_bai'], 'bai_tap');
            }

            $stmt = $this->db->prepare('INSERT INTO bai_tap (id_lop, tieu_de, mo_ta, han_nop, file_de_bai)
                                        VALUES (:lop, :td, :mt, :hn, :fd)');
            $stmt->execute([
                'lop' => $idLop,
                'td' => trim((string) ($_POST['tieu_de'] ?? '')),
                'mt' => trim((string) ($_POST['mo_ta'] ?? '')),
                'hn' => $_POST['han_nop'] ?? null,
                'fd' => $fileName,
            ]);
            Response::json(['success' => true, 'message' => 'Da tao bai tap.'], 201);
            return;
        }

        if ($method === 'DELETE' && isset($segments[2])) {
            $id = (int) $segments[2];
            $stmt = $this->db->prepare('DELETE FROM bai_tap WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa bai tap.']);
            return;
        }

        Response::error('Assignments endpoint khong ton tai.', 404);
    }

    private function teacherSubmissions(string $method, array $segments): void
    {
        if ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'grade') {
            $idBaiNop = (int) $segments[2];
            $body = $this->body();
            $stmt = $this->db->prepare('UPDATE bai_nop SET diem = :d, nhan_xet = :nx WHERE id = :id');
            $stmt->execute([
                'd' => $body['diem'] ?? null,
                'nx' => $body['nhan_xet'] ?? null,
                'id' => $idBaiNop,
            ]);
            Response::json(['success' => true, 'message' => 'Da cham diem bai nop.']);
            return;
        }

        Response::error('Submissions endpoint khong ton tai.', 404);
    }

    private function teacherGrades(string $method, array $segments, int $teacherId): void
    {
        if (($method === 'PUT' || $method === 'PATCH') && isset($segments[2])) {
            $idLop = (int) $segments[2];
            $this->assertTeacherClass($teacherId, $idLop);
            $body = $this->body();
            $danhSach = $body['danh_sach'] ?? [];

            $stmt = $this->db->prepare('UPDATE dang_ky SET diem_giua_ky = :gk, diem_cuoi_ky = :ck WHERE id_lop = :lop AND id_sinh_vien = :sv');
            foreach ($danhSach as $item) {
                $stmt->execute([
                    'gk' => $item['diem_giua_ky'] ?? null,
                    'ck' => $item['diem_cuoi_ky'] ?? null,
                    'lop' => $idLop,
                    'sv' => (int) $item['id_sinh_vien'],
                ]);
            }

            Response::json(['success' => true, 'message' => 'Da cap nhat diem tong ket.']);
            return;
        }

        Response::error('Grades endpoint khong ton tai.', 404);
    }

    private function crudUsers(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $rows = $this->db->query('SELECT id, ten_dang_nhap, ho_ten, email, vai_tro, ngay_tao FROM nguoi_dung ORDER BY id DESC')->fetchAll();
            Response::json(['success' => true, 'data' => $rows]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $stmt = $this->db->prepare('INSERT INTO nguoi_dung (ten_dang_nhap, mat_khau, ho_ten, email, vai_tro)
                                        VALUES (:u, :p, :h, :e, :v)');
            $stmt->execute([
                'u' => $b['ten_dang_nhap'] ?? '',
                'p' => $b['mat_khau'] ?? '123456',
                'h' => $b['ho_ten'] ?? '',
                'e' => $b['email'] ?? null,
                'v' => $b['vai_tro'] ?? 'sv',
            ]);
            Response::json(['success' => true, 'message' => 'Da tao nguoi dung.'], 201);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
            $b = $this->body();
            $sql = 'UPDATE nguoi_dung SET ten_dang_nhap = :u, ho_ten = :h, email = :e, vai_tro = :v';
            if (!empty($b['mat_khau'])) {
                $sql .= ', mat_khau = :p';
            }
            $sql .= ' WHERE id = :id';

            $params = [
                'u' => $b['ten_dang_nhap'] ?? '',
                'h' => $b['ho_ten'] ?? '',
                'e' => $b['email'] ?? null,
                'v' => $b['vai_tro'] ?? 'sv',
                'id' => $id,
            ];
            if (!empty($b['mat_khau'])) {
                $params['p'] = $b['mat_khau'];
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            Response::json(['success' => true, 'message' => 'Da cap nhat nguoi dung.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            if ($id === (int) $_SESSION['user']['id']) {
                Response::error('Khong the xoa tai khoan dang dang nhap.', 422);
                return;
            }
            $stmt = $this->db->prepare('DELETE FROM nguoi_dung WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa nguoi dung.']);
            return;
        }

        Response::error('Users endpoint khong ton tai.', 404);
    }

    private function crudSubjects(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $rows = $this->db->query('SELECT * FROM mon_hoc ORDER BY id DESC')->fetchAll();
            Response::json(['success' => true, 'data' => $rows]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $stmt = $this->db->prepare('INSERT INTO mon_hoc (ten_mon, so_tin_chi, mo_ta) VALUES (:t, :s, :m)');
            $stmt->execute([
                't' => $b['ten_mon'] ?? '',
                's' => (int) ($b['so_tin_chi'] ?? 3),
                'm' => $b['mo_ta'] ?? null,
            ]);
            Response::json(['success' => true, 'message' => 'Da tao mon hoc.'], 201);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
            $b = $this->body();
            $stmt = $this->db->prepare('UPDATE mon_hoc SET ten_mon = :t, so_tin_chi = :s, mo_ta = :m WHERE id = :id');
            $stmt->execute([
                't' => $b['ten_mon'] ?? '',
                's' => (int) ($b['so_tin_chi'] ?? 3),
                'm' => $b['mo_ta'] ?? null,
                'id' => $id,
            ]);
            Response::json(['success' => true, 'message' => 'Da cap nhat mon hoc.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('DELETE FROM mon_hoc WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa mon hoc.']);
            return;
        }

        Response::error('Subjects endpoint khong ton tai.', 404);
    }

    private function crudClasses(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT lh.*, mh.ten_mon, nd.ho_ten AS ten_giao_vien
                    FROM lop_hoc lh
                    LEFT JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                    LEFT JOIN nguoi_dung nd ON nd.id = lh.id_giao_vien
                    ORDER BY lh.id DESC';
            Response::json(['success' => true, 'data' => $this->db->query($sql)->fetchAll()]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $stmt = $this->db->prepare('INSERT INTO lop_hoc (id_mon_hoc, id_giao_vien, ten_lop, hoc_ky, si_so_toi_da)
                                        VALUES (:m, :g, :t, :h, :s)');
            $stmt->execute([
                'm' => (int) ($b['id_mon_hoc'] ?? 0),
                'g' => !empty($b['id_giao_vien']) ? (int) $b['id_giao_vien'] : null,
                't' => $b['ten_lop'] ?? '',
                'h' => $b['hoc_ky'] ?? null,
                's' => (int) ($b['si_so_toi_da'] ?? 50),
            ]);
            Response::json(['success' => true, 'message' => 'Da tao lop hoc.'], 201);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
            $b = $this->body();
            $stmt = $this->db->prepare('UPDATE lop_hoc
                                        SET id_mon_hoc = :m, id_giao_vien = :g, ten_lop = :t, hoc_ky = :h, si_so_toi_da = :s
                                        WHERE id = :id');
            $stmt->execute([
                'm' => (int) ($b['id_mon_hoc'] ?? 0),
                'g' => !empty($b['id_giao_vien']) ? (int) $b['id_giao_vien'] : null,
                't' => $b['ten_lop'] ?? '',
                'h' => $b['hoc_ky'] ?? null,
                's' => (int) ($b['si_so_toi_da'] ?? 50),
                'id' => $id,
            ]);
            Response::json(['success' => true, 'message' => 'Da cap nhat lop hoc.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('DELETE FROM lop_hoc WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa lop hoc.']);
            return;
        }

        Response::error('Classes endpoint khong ton tai.', 404);
    }

    private function crudAnnouncements(string $method, ?int $id, bool $isAdmin, ?int $teacherId = null): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tb.*, nd.ho_ten AS nguoi_gui_ten, lh.ten_lop
                    FROM thong_bao tb
                    JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                    LEFT JOIN lop_hoc lh ON lh.id = tb.id_lop';
            $params = [];
            if (!$isAdmin && $teacherId !== null) {
                $sql .= ' WHERE tb.id_lop IN (SELECT id FROM lop_hoc WHERE id_giao_vien = :gv)';
                $params['gv'] = $teacherId;
            }
            $sql .= ' ORDER BY tb.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $idLop = !empty($b['id_lop']) ? (int) $b['id_lop'] : null;
            if (!$isAdmin && $idLop !== null) {
                $this->assertTeacherClass((int) $teacherId, $idLop);
            }
            $stmt = $this->db->prepare('INSERT INTO thong_bao (tieu_de, noi_dung, nguoi_gui, id_lop)
                                        VALUES (:t, :n, :g, :l)');
            $stmt->execute([
                't' => $b['tieu_de'] ?? '',
                'n' => $b['noi_dung'] ?? '',
                'g' => (int) $_SESSION['user']['id'],
                'l' => $idLop,
            ]);
            Response::json(['success' => true, 'message' => 'Da tao thong bao.'], 201);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
            $b = $this->body();
            $stmt = $this->db->prepare('UPDATE thong_bao SET tieu_de = :t, noi_dung = :n, id_lop = :l WHERE id = :id');
            $stmt->execute([
                't' => $b['tieu_de'] ?? '',
                'n' => $b['noi_dung'] ?? '',
                'l' => !empty($b['id_lop']) ? (int) $b['id_lop'] : null,
                'id' => $id,
            ]);
            Response::json(['success' => true, 'message' => 'Da cap nhat thong bao.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('DELETE FROM thong_bao WHERE id = :id');
            $stmt->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa thong bao.']);
            return;
        }

        Response::error('Announcements endpoint khong ton tai.', 404);
    }

    private function crudMaterials(string $method, ?int $id, bool $isAdmin, ?int $teacherId = null): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tl.*, nd.ho_ten AS nguoi_upload_ten, lh.ten_lop
                    FROM tai_lieu tl
                    LEFT JOIN nguoi_dung nd ON nd.id = tl.nguoi_upload
                    LEFT JOIN lop_hoc lh ON lh.id = tl.id_lop';
            $params = [];
            if (!$isAdmin && $teacherId !== null) {
                $sql .= ' WHERE tl.id_lop IN (SELECT id FROM lop_hoc WHERE id_giao_vien = :gv)';
                $params['gv'] = $teacherId;
            }
            $sql .= ' ORDER BY tl.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            if (!isset($_FILES['file_upload']) || $_FILES['file_upload']['error'] !== 0) {
                Response::error('Vui long chon file tai lieu.', 422);
                return;
            }

            $idLop = !empty($_POST['id_lop']) ? (int) $_POST['id_lop'] : null;
            if (!$isAdmin && $idLop !== null) {
                $this->assertTeacherClass((int) $teacherId, $idLop);
            }

            $fileName = $this->saveUploadedFile($_FILES['file_upload'], 'tai_lieu');
            $stmt = $this->db->prepare('INSERT INTO tai_lieu (tieu_de, duong_dan_file, nguoi_upload, id_lop)
                                        VALUES (:t, :d, :n, :l)');
            $stmt->execute([
                't' => trim((string) ($_POST['tieu_de'] ?? '')),
                'd' => $fileName,
                'n' => (int) $_SESSION['user']['id'],
                'l' => $idLop,
            ]);
            Response::json(['success' => true, 'message' => 'Da them tai lieu.'], 201);
            return;
        }

        if (($method === 'PUT' || $method === 'PATCH') && $id !== null) {
            $b = $this->body();
            $stmt = $this->db->prepare('UPDATE tai_lieu SET tieu_de = :t, id_lop = :l WHERE id = :id');
            $stmt->execute([
                't' => $b['tieu_de'] ?? '',
                'l' => !empty($b['id_lop']) ? (int) $b['id_lop'] : null,
                'id' => $id,
            ]);
            Response::json(['success' => true, 'message' => 'Da cap nhat tai lieu.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('SELECT duong_dan_file FROM tai_lieu WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row && !empty($row['duong_dan_file'])) {
                $filePath = dirname(__DIR__, 2) . '/public/uploads/tai_lieu/' . $row['duong_dan_file'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $del = $this->db->prepare('DELETE FROM tai_lieu WHERE id = :id');
            $del->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa tai lieu.']);
            return;
        }

        Response::error('Materials endpoint khong ton tai.', 404);
    }

    private function studentClassDetail(int $studentId, int $idLop): array
    {
        $lopStmt = $this->db->prepare('SELECT lh.*, mh.ten_mon FROM lop_hoc lh JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc WHERE lh.id = :id');
        $lopStmt->execute(['id' => $idLop]);

        $gradeStmt = $this->db->prepare('SELECT diem_giua_ky, diem_cuoi_ky FROM dang_ky WHERE id_lop = :lop AND id_sinh_vien = :sv LIMIT 1');
        $gradeStmt->execute(['lop' => $idLop, 'sv' => $studentId]);

        $matStmt = $this->db->prepare('SELECT * FROM tai_lieu WHERE id_lop = :lop ORDER BY id DESC');
        $matStmt->execute(['lop' => $idLop]);

        $annStmt = $this->db->prepare('SELECT tb.*, nd.ho_ten AS nguoi_gui_ten
                                      FROM thong_bao tb
                                      JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                                      WHERE tb.id_lop = :lop OR tb.id_lop IS NULL
                                      ORDER BY tb.id DESC');
        $annStmt->execute(['lop' => $idLop]);

        $assStmt = $this->db->prepare('SELECT bt.*,
                                    (SELECT bn.id FROM bai_nop bn WHERE bn.id_bai_tap = bt.id AND bn.id_sinh_vien = :sv LIMIT 1) AS da_nop
                                   FROM bai_tap bt WHERE bt.id_lop = :lop ORDER BY bt.id DESC');
        $assStmt->execute(['sv' => $studentId, 'lop' => $idLop]);

        return [
            'lop' => $lopStmt->fetch(),
            'ket_qua' => $gradeStmt->fetch(),
            'tai_lieu' => $matStmt->fetchAll(),
            'thong_bao' => $annStmt->fetchAll(),
            'bai_tap' => $assStmt->fetchAll(),
        ];
    }

    private function studentSubmitAssignment(int $studentId, int $assignmentId): void
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
            Response::error('Vui long chon file bai nop.', 422);
            return;
        }

        $fileName = $this->saveUploadedFile($_FILES['file'], 'bai_nop');

        $stmt = $this->db->prepare('SELECT id FROM bai_nop WHERE id_bai_tap = :bt AND id_sinh_vien = :sv LIMIT 1');
        $stmt->execute(['bt' => $assignmentId, 'sv' => $studentId]);
        $old = $stmt->fetch();

        if ($old) {
            $up = $this->db->prepare('UPDATE bai_nop SET file_bai_lam = :f, ngay_nop = CURRENT_TIMESTAMP WHERE id = :id');
            $up->execute(['f' => $fileName, 'id' => $old['id']]);
        } else {
            $ins = $this->db->prepare('INSERT INTO bai_nop (id_bai_tap, id_sinh_vien, file_bai_lam) VALUES (:bt, :sv, :f)');
            $ins->execute(['bt' => $assignmentId, 'sv' => $studentId, 'f' => $fileName]);
        }

        Response::json(['success' => true, 'message' => 'Nop bai thanh cong.']);
    }

    private function saveUploadedFile(array $file, string $folder): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/' . $folder . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
        $name = time() . '_' . $base . ($ext ? '.' . $ext : '');

        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            Response::error('Khong the luu file.', 500);
            exit;
        }

        return $name;
    }

    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function requireRole(array $roles): void
    {
        if (!isset($_SESSION['user'])) {
            Response::error('Chua dang nhap.', 401);
            exit;
        }
        if (!in_array($_SESSION['user']['vai_tro'], $roles, true)) {
            Response::error('Khong co quyen truy cap.', 403);
            exit;
        }
    }

    private function assertTeacherClass(int $teacherId, int $classId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM lop_hoc WHERE id = :id AND id_giao_vien = :gv LIMIT 1');
        $stmt->execute(['id' => $classId, 'gv' => $teacherId]);
        if (!$stmt->fetch()) {
            Response::error('Lop hoc khong thuoc giang vien.', 403);
            exit;
        }
    }

    private function assertStudentClass(int $studentId, int $classId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM dang_ky WHERE id_sinh_vien = :sv AND id_lop = :lop LIMIT 1');
        $stmt->execute(['sv' => $studentId, 'lop' => $classId]);
        if (!$stmt->fetch()) {
            Response::error('Ban chua dang ky lop hoc nay.', 403);
            exit;
        }
    }
}
