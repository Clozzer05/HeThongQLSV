<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseApiController.php';

class StudentApiController extends BaseApiController
{
    public function handle(string $method, array $segments): void
    {
        $studentId = (int) $_SESSION['user']['id'];
        $resource = $segments[1] ?? '';

        if ($method === 'GET' && $resource === 'my-classes') {
            $this->myClasses($studentId);
            return;
        }

        if ($method === 'GET' && $resource === 'available-classes') {
            $this->availableClasses($studentId);
            return;
        }

        if ($method === 'POST' && $resource === 'enroll') {
            $this->enroll($studentId);
            return;
        }

        if ($method === 'POST' && $resource === 'enrollments') {
            $this->enroll($studentId);
            return;
        }

        if ($resource === 'classes') {
            $this->classRoutes($method, $segments, $studentId);
            return;
        }

        if ($resource === 'assignments') {
            $this->assignmentRoutes($method, $segments, $studentId);
            return;
        }

        if ($resource === 'submissions') {
            $this->submissions($method, $studentId);
            return;
        }

        if ($method === 'GET' && $resource === 'announcements') {
            $this->announcements($studentId);
            return;
        }

        if ($method === 'GET' && $resource === 'materials') {
            $this->materials($studentId);
            return;
        }

        Response::error('Student endpoint khong ton tai.', 404);
    }

    private function myClasses(int $studentId): void
    {
        $sql = 'SELECT lh.*, mh.ten_mon, dk.diem_giua_ky, dk.diem_cuoi_ky
                FROM dang_ky dk
                JOIN lop_hoc lh ON lh.id = dk.id_lop
                JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                WHERE dk.id_sinh_vien = :id
                ORDER BY lh.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $studentId]);
        Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function availableClasses(int $studentId): void
    {
        $sql = 'SELECT lh.*, mh.ten_mon,
                       nd.ho_ten AS ten_giao_vien,
                       (SELECT COUNT(*) FROM dang_ky dk WHERE dk.id_lop = lh.id) AS si_so_hien_tai
                FROM lop_hoc lh
                JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
                LEFT JOIN nguoi_dung nd ON nd.id = lh.id_giao_vien
                WHERE lh.id NOT IN (
                    SELECT id_lop FROM dang_ky WHERE id_sinh_vien = :sv
                )
                ORDER BY lh.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sv' => $studentId]);
        Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function enroll(int $studentId): void
    {
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
    }

    private function classRoutes(string $method, array $segments, int $studentId): void
    {
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

        Response::error('Student classes endpoint khong ton tai.', 404);
    }

    private function assignmentRoutes(string $method, array $segments, int $studentId): void
    {
        $assignmentId = isset($segments[2]) ? (int) $segments[2] : 0;
        $tail = $segments[3] ?? '';
        if ($method === 'POST' && $assignmentId > 0 && $tail === 'submit') {
            $this->submitAssignment($studentId, $assignmentId);
            return;
        }

        Response::error('Assignments endpoint khong ton tai.', 404);
    }

    private function submissions(string $method, int $studentId): void
    {
        if ($method !== 'POST') {
            Response::error('Submissions endpoint khong ton tai.', 404);
            return;
        }

        $assignmentId = (int) ($_POST['id_bai_tap'] ?? 0);
        if ($assignmentId <= 0) {
            Response::error('id_bai_tap khong hop le.', 422);
            return;
        }

        $this->submitAssignment($studentId, $assignmentId);
    }

    private function announcements(int $studentId): void
    {
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
    }

    private function materials(int $studentId): void
    {
        $sql = 'SELECT tl.*, nd.ho_ten AS nguoi_upload_ten, lh.ten_lop
                FROM tai_lieu tl
                LEFT JOIN nguoi_dung nd ON nd.id = tl.nguoi_upload
                LEFT JOIN lop_hoc lh ON lh.id = tl.id_lop
                WHERE tl.id_lop IS NULL OR tl.id_lop IN (
                    SELECT id_lop FROM dang_ky WHERE id_sinh_vien = :sv
                )
                ORDER BY tl.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sv' => $studentId]);
        Response::json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function studentClassDetail(int $studentId, int $idLop): array
    {
        $lopStmt = $this->db->prepare('SELECT lh.*, mh.ten_mon, nd.ho_ten AS ten_giao_vien, nd.email AS email_giao_vien
            FROM lop_hoc lh
            JOIN mon_hoc mh ON mh.id = lh.id_mon_hoc
            LEFT JOIN nguoi_dung nd ON nd.id = lh.id_giao_vien
            WHERE lh.id = :id');
        $lopStmt->execute(['id' => $idLop]);

        $tkbStmt = $this->db->prepare('SELECT * FROM thoi_khoa_bieu WHERE id_lop = :lop ORDER BY thu, tiet_bat_dau');
        try {
            $tkbStmt->execute(['lop' => $idLop]);
            $thoi_khoa_bieu = $tkbStmt->fetchAll();
        } catch (PDOException $e) {
            $thoi_khoa_bieu = [];
        }

        $gradeStmt = $this->db->prepare('SELECT diem_giua_ky, diem_cuoi_ky FROM dang_ky WHERE id_lop = :lop AND id_sinh_vien = :sv LIMIT 1');
        $gradeStmt->execute(['lop' => $idLop, 'sv' => $studentId]);

        $matStmt = $this->db->prepare('SELECT * FROM tai_lieu WHERE id_lop = :lop OR id_lop IS NULL ORDER BY id DESC');
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
            'thoi_khoa_bieu' => $thoi_khoa_bieu,
        ];
    }

    private function submitAssignment(int $studentId, int $assignmentId): void
    {
        if (!isset($_FILES['file'])) {
            Response::error('Vui long chon file bai nop.', 422);
            return;
        }

        if ((int) $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error($this->uploadErrorMessage((int) $_FILES['file']['error']), 422);
            return;
        }

        $this->validateUploadedFile(
            $_FILES['file'],
            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png']
        );

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
}
