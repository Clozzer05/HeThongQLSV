<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Response.php';

abstract class BaseApiController
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    protected function saveUploadedFile(array $file, string $folder): string
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

    protected function assertTeacherClass(int $teacherId, int $classId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM lop_hoc WHERE id = :id AND id_giao_vien = :gv LIMIT 1');
        $stmt->execute(['id' => $classId, 'gv' => $teacherId]);
        if (!$stmt->fetch()) {
            Response::error('Lop hoc khong thuoc giang vien.', 403);
            exit;
        }
    }

    protected function assertStudentClass(int $studentId, int $classId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM dang_ky WHERE id_sinh_vien = :sv AND id_lop = :lop LIMIT 1');
        $stmt->execute(['sv' => $studentId, 'lop' => $classId]);
        if (!$stmt->fetch()) {
            Response::error('Ban chua dang ky lop hoc nay.', 403);
            exit;
        }
    }
}
