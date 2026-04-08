<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseApiController.php';

class AdminApiController extends BaseApiController
{
    public function handle(string $method, array $segments): void
    {
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
            $this->crudAnnouncements($method, $id);
            return;
        }

        if ($resource === 'materials') {
            $this->crudMaterials($method, $id);
            return;
        }

        Response::error('Admin endpoint khong ton tai.', 404);
    }

    private function crudUsers(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $rows = $this->db->query('SELECT id, ten_dang_nhap, ho_ten, email, vai_tro, ngay_tao FROM nguoi_dung ORDER BY id DESC')->fetchAll();
            Response::json(['success' => true, 'data' => $rows]);
            return;
        }

        if ($method === 'GET' && $id !== null) {
            $stmt = $this->db->prepare('SELECT id, ten_dang_nhap, ho_ten, email, vai_tro, ngay_tao FROM nguoi_dung WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::error('Nguoi dung khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'data' => $row]);
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
            if ($stmt->rowCount() === 0) {
                Response::error('Nguoi dung khong ton tai.', 404);
                return;
            }
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
            if ($stmt->rowCount() === 0) {
                Response::error('Nguoi dung khong ton tai.', 404);
                return;
            }
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

        if ($method === 'GET' && $id !== null) {
            $stmt = $this->db->prepare('SELECT * FROM mon_hoc WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::error('Mon hoc khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'data' => $row]);
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
            if ($stmt->rowCount() === 0) {
                Response::error('Mon hoc khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da cap nhat mon hoc.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('DELETE FROM mon_hoc WHERE id = :id');
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() === 0) {
                Response::error('Mon hoc khong ton tai.', 404);
                return;
            }
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

        if ($method === 'GET' && $id !== null) {
            $sql = 'SELECT lh.*, mh.ten_mon, nd.ho_ten AS ten_giao_vien
                    FROM lop_hoc lh
                    LEFT JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                    LEFT JOIN nguoi_dung nd ON nd.id = lh.id_giao_vien
                    WHERE lh.id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::error('Lop hoc khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'data' => $row]);
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
            if ($stmt->rowCount() === 0) {
                Response::error('Lop hoc khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da cap nhat lop hoc.']);
            return;
        }

        if ($method === 'DELETE' && $id !== null) {
            $stmt = $this->db->prepare('DELETE FROM lop_hoc WHERE id = :id');
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() === 0) {
                Response::error('Lop hoc khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da xoa lop hoc.']);
            return;
        }

        Response::error('Classes endpoint khong ton tai.', 404);
    }

    private function crudAnnouncements(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tb.*, nd.ho_ten AS nguoi_gui_ten, lh.ten_lop
                    FROM thong_bao tb
                    JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                    LEFT JOIN lop_hoc lh ON lh.id = tb.id_lop
                    ORDER BY tb.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'GET' && $id !== null) {
            $sql = 'SELECT tb.*, nd.ho_ten AS nguoi_gui_ten, lh.ten_lop
                    FROM thong_bao tb
                    JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                    LEFT JOIN lop_hoc lh ON lh.id = tb.id_lop
                    WHERE tb.id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::error('Thong bao khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'data' => $row]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $idLop = !empty($b['id_lop']) ? (int) $b['id_lop'] : null;
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
            if ($stmt->rowCount() === 0) {
                Response::error('Thong bao khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da xoa thong bao.']);
            return;
        }

        Response::error('Announcements endpoint khong ton tai.', 404);
    }

    private function crudMaterials(string $method, ?int $id): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tl.*, nd.ho_ten AS nguoi_upload_ten, lh.ten_lop
                    FROM tai_lieu tl
                    LEFT JOIN nguoi_dung nd ON nd.id = tl.nguoi_upload
                    LEFT JOIN lop_hoc lh ON lh.id = tl.id_lop
                    ORDER BY tl.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'GET' && $id !== null) {
            $sql = 'SELECT tl.*, nd.ho_ten AS nguoi_upload_ten, lh.ten_lop
                    FROM tai_lieu tl
                    LEFT JOIN nguoi_dung nd ON nd.id = tl.nguoi_upload
                    LEFT JOIN lop_hoc lh ON lh.id = tl.id_lop
                    WHERE tl.id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::error('Tai lieu khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'data' => $row]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            if (!isset($_FILES['file_upload'])) {
                Response::error('Vui long chon file tai lieu.', 422);
                return;
            }

            if ((int) $_FILES['file_upload']['error'] !== UPLOAD_ERR_OK) {
                Response::error($this->uploadErrorMessage((int) $_FILES['file_upload']['error']), 422);
                return;
            }

            $idLop = !empty($_POST['id_lop']) ? (int) $_POST['id_lop'] : null;
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
            if ($del->rowCount() === 0) {
                Response::error('Tai lieu khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da xoa tai lieu.']);
            return;
        }

        Response::error('Materials endpoint khong ton tai.', 404);
    }
}
