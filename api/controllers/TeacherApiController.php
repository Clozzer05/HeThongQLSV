<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseApiController.php';
require_once __DIR__ . '/../core/Storage.php';

class TeacherApiController extends BaseApiController
{
    public function handle(string $method, array $segments): void
    {
        $teacherId = (int) $_SESSION['user']['id'];
        $resource = $segments[1] ?? '';

        if ($method === 'GET' && $resource === 'classes') {
            $this->listClasses($segments, $teacherId);
            return;
        }

        if ($resource === 'attendance') {
            $this->attendance($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'assignments') {
            $this->assignments($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'submissions') {
            $this->submissions($method, $segments);
            return;
        }

        if ($resource === 'grades') {
            $this->grades($method, $segments, $teacherId);
            return;
        }

        if ($resource === 'announcements') {
            $id = isset($segments[2]) ? (int) $segments[2] : null;
            $this->announcements($method, $id, $teacherId);
            return;
        }

        if ($resource === 'materials') {
            $id = isset($segments[2]) ? (int) $segments[2] : null;
            $this->materials($method, $id, $teacherId);
            return;
        }

        Response::error('Teacher endpoint khong ton tai.', 404);
    }

    private function listClasses(array $segments, int $teacherId): void
    {
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

        if ($tail === 'students') {
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

        Response::error('Teacher classes endpoint khong ton tai.', 404);
    }

    private function attendance(string $method, array $segments, int $teacherId): void
    {
        if ($method === 'POST' && ($segments[2] ?? '') === '') {
            $body = $this->body();
            $idLop = (int) ($body['id_lop'] ?? 0);
            $ngay = (string) ($body['ngay_diem_danh'] ?? '');
            $danhSach = $body['danh_sach'] ?? [];

            if ($idLop <= 0) {
                Response::error('id_lop khong hop le.', 422);
                return;
            }

            if ($ngay === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay)) {
                Response::error('ngay_diem_danh khong hop le. Dinh dang: YYYY-MM-DD.', 422);
                return;
            }

            if (!is_array($danhSach)) {
                Response::error('danh_sach khong hop le.', 422);
                return;
            }

            $this->assertTeacherClass($teacherId, $idLop);

            try {
                $this->db->beginTransaction();

                $delete = $this->db->prepare('DELETE FROM diem_danh WHERE id_lop = :lop AND ngay_diem_danh = :ngay');
                $delete->execute(['lop' => $idLop, 'ngay' => $ngay]);

                $ins = $this->db->prepare('INSERT INTO diem_danh (id_lop, id_sinh_vien, ngay_diem_danh, trang_thai, ghi_chu)
                                           VALUES (:lop, :sv, :ngay, :tt, :gc)');
                foreach ($danhSach as $item) {
                    $studentId = (int) ($item['id_sinh_vien'] ?? 0);
                    if ($studentId <= 0) {
                        throw new InvalidArgumentException('id_sinh_vien khong hop le trong danh_sach.');
                    }

                    $ins->execute([
                        'lop' => $idLop,
                        'sv' => $studentId,
                        'ngay' => $ngay,
                        'tt' => $item['trang_thai'] ?? 'co_mat',
                        'gc' => $item['ghi_chu'] ?? null,
                    ]);
                }

                $this->db->commit();
            } catch (InvalidArgumentException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                Response::error($e->getMessage(), 422);
                return;
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log('[TEACHER_ATTENDANCE_DB_ERROR] ' . $e->getMessage());
                Response::error('Khong the luu diem danh.', 500);
                return;
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

    private function assignments(string $method, array $segments, int $teacherId): void
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
            if (isset($_FILES['file_de_bai'])) {
                $uploadError = (int) ($_FILES['file_de_bai']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    Response::error($this->uploadErrorMessage($uploadError), 422);
                    return;
                }
                $this->validateUploadedFile(
                    $_FILES['file_de_bai'],
                    ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png']
                );
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

    private function submissions(string $method, array $segments): void
    {
        if (
            ($method === 'POST' && isset($segments[2]) && ($segments[3] ?? '') === 'grade')
            || (($method === 'PATCH' || $method === 'PUT') && isset($segments[2]) && ($segments[3] ?? '') === '')
        ) {
            $idBaiNop = (int) $segments[2];
            $body = $this->body();
            $stmt = $this->db->prepare('UPDATE bai_nop SET diem = :d, nhan_xet = :nx WHERE id = :id');
            $stmt->execute([
                'd' => $body['diem'] ?? null,
                'nx' => $body['nhan_xet'] ?? null,
                'id' => $idBaiNop,
            ]);
            if ($stmt->rowCount() === 0) {
                Response::error('Bai nop khong ton tai.', 404);
                return;
            }
            Response::json(['success' => true, 'message' => 'Da cham diem bai nop.']);
            return;
        }

        Response::error('Submissions endpoint khong ton tai.', 404);
    }

    private function grades(string $method, array $segments, int $teacherId): void
    {
        if (($method === 'PUT' || $method === 'PATCH') && isset($segments[2])) {
            $idLop = (int) $segments[2];
            if ($idLop <= 0) {
                Response::error('id_lop khong hop le.', 422);
                return;
            }

            $this->assertTeacherClass($teacherId, $idLop);
            $body = $this->body();
            $danhSach = $body['danh_sach'] ?? [];
            if (!is_array($danhSach) || $danhSach === []) {
                Response::error('danh_sach khong hop le hoac dang rong.', 422);
                return;
            }

            try {
                $stmt = $this->db->prepare('UPDATE dang_ky SET diem_giua_ky = :gk, diem_cuoi_ky = :ck WHERE id_lop = :lop AND id_sinh_vien = :sv');
                foreach ($danhSach as $item) {
                    $studentId = (int) ($item['id_sinh_vien'] ?? 0);
                    if ($studentId <= 0) {
                        throw new InvalidArgumentException('id_sinh_vien khong hop le trong danh_sach.');
                    }

                    $stmt->execute([
                        'gk' => $item['diem_giua_ky'] ?? null,
                        'ck' => $item['diem_cuoi_ky'] ?? null,
                        'lop' => $idLop,
                        'sv' => $studentId,
                    ]);
                }
            } catch (InvalidArgumentException $e) {
                Response::error($e->getMessage(), 422);
                return;
            } catch (PDOException $e) {
                error_log('[TEACHER_GRADES_DB_ERROR] ' . $e->getMessage());
                Response::error('Khong the cap nhat diem tong ket.', 500);
                return;
            }

            Response::json(['success' => true, 'message' => 'Da cap nhat diem tong ket.']);
            return;
        }

        Response::error('Grades endpoint khong ton tai.', 404);
    }

    private function announcements(string $method, ?int $id, int $teacherId): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tb.*, nd.ho_ten AS nguoi_gui_ten, lh.ten_lop
                    FROM thong_bao tb
                    JOIN nguoi_dung nd ON nd.id = tb.nguoi_gui
                    LEFT JOIN lop_hoc lh ON lh.id = tb.id_lop
                    WHERE tb.id_lop IN (SELECT id FROM lop_hoc WHERE id_giao_vien = :gv)
                    ORDER BY tb.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gv' => $teacherId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
            return;
        }

        if ($method === 'POST' && $id === null) {
            $b = $this->body();
            $idLop = !empty($b['id_lop']) ? (int) $b['id_lop'] : null;
            if ($idLop !== null) {
                $this->assertTeacherClass($teacherId, $idLop);
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

    private function materials(string $method, ?int $id, int $teacherId): void
    {
        if ($method === 'GET' && $id === null) {
            $sql = 'SELECT tl.*, nd.ho_ten AS nguoi_upload_ten, lh.ten_lop
                    FROM tai_lieu tl
                    LEFT JOIN nguoi_dung nd ON nd.id = tl.nguoi_upload
                    LEFT JOIN lop_hoc lh ON lh.id = tl.id_lop
                    WHERE tl.id_lop IN (SELECT id FROM lop_hoc WHERE id_giao_vien = :gv)
                    ORDER BY tl.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gv' => $teacherId]);
            Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
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

            $this->validateUploadedFile(
                $_FILES['file_upload'],
                ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png']
            );

            $idLop = !empty($_POST['id_lop']) ? (int) $_POST['id_lop'] : null;
            if ($idLop !== null) {
                $this->assertTeacherClass($teacherId, $idLop);
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
                Storage::deleteStoredFile('tai_lieu', (string) $row['duong_dan_file']);
            }
            $del = $this->db->prepare('DELETE FROM tai_lieu WHERE id = :id');
            $del->execute(['id' => $id]);
            Response::json(['success' => true, 'message' => 'Da xoa tai lieu.']);
            return;
        }

        Response::error('Materials endpoint khong ton tai.', 404);
    }
}
