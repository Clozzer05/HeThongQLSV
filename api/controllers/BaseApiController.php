<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Response.php';

abstract class BaseApiController
{
    protected const MAX_UPLOAD_BYTES = 20971520; 

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

        if (!is_writable($dir)) {
            @chmod($dir, 0777);
        }

        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            Response::error('File upload khong hop le.', 422);
            exit;
        }

        if (!is_writable($dir)) {
            Response::error('Thu muc upload khong co quyen ghi: ' . $dir, 500);
            exit;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
        if ($base === '') {
            $base = 'file';
        }
        $name = time() . '_' . $base . ($ext ? '.' . $ext : '');

        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
            Response::error('Khong the luu file.', 500);
            exit;
        }

        return $name;
    }

    protected function validateUploadedFile(array $file, array $allowedExtensions, int $maxBytes = self::MAX_UPLOAD_BYTES): void
    {
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            Response::error('File upload khong hop le hoac rong.', 422);
            exit;
        }

        if ($size > $maxBytes) {
            $maxMb = (int) floor($maxBytes / (1024 * 1024));
            Response::error('File vuot qua dung luong toi da ' . $maxMb . 'MB.', 422);
            exit;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            Response::error('Dinh dang file khong duoc ho tro. Cho phep: ' . implode(', ', $allowedExtensions) . '.', 422);
            exit;
        }
    }

    protected function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File qua lon. Vui long tang upload_max_filesize/post_max_size hoac chon file nho hon.',
            UPLOAD_ERR_PARTIAL => 'File chi duoc upload mot phan. Vui long thu lai.',
            UPLOAD_ERR_NO_FILE => 'Vui long chon file.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server thieu thu muc tam de upload.',
            UPLOAD_ERR_CANT_WRITE => 'Server khong the ghi file upload (quyen ghi).',
            UPLOAD_ERR_EXTENSION => 'Upload bi chan boi PHP extension.',
            default => 'Upload that bai (ma loi: ' . $errorCode . ').',
        };
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
