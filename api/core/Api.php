<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AdminApiController.php';
require_once __DIR__ . '/../controllers/TeacherApiController.php';
require_once __DIR__ . '/../controllers/StudentApiController.php';

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
        if (($segments[0] ?? '') === 'v1') {
            array_shift($segments);
        }

        $resource = $segments[0] ?? '';

        if ($resource === 'health') {
            Response::json(['success' => true, 'message' => 'API is running', 'time' => date(DATE_ATOM)]);
            return;
        }

        if ($resource === 'auth') {
            $this->authRoutes($method, $segments);
            return;
        }

        if ($resource === 'files') {
            $this->fileRoutes($method, $segments);
            return;
        }

        $this->ensureDb();

        if ($resource === 'admin') {
            $this->requireRole(['admin']);
            (new AdminApiController($this->db))->handle($method, $segments);
            return;
        }

        if ($resource === 'teacher') {
            $this->requireRole(['gv']);
            (new TeacherApiController($this->db))->handle($method, $segments);
            return;
        }

        if ($resource === 'student') {
            $this->requireRole(['sv']);
            (new StudentApiController($this->db))->handle($method, $segments);
            return;
        }

        Response::error('Endpoint khong ton tai.', 404);
    }

    private function authRoutes(string $method, array $segments): void
    {
        $action = $segments[1] ?? '';
        $this->ensureDb();
        $authController = new AuthController($this->db);

        if ($method === 'POST' && $action === 'login') {
            $authController->login($this->body());
            return;
        }

        if ($method === 'POST' && $action === 'logout') {
            $authController->logout();
            return;
        }

        if ($method === 'GET' && $action === 'me') {
            $authController->me();
            return;
        }

        Response::error('Auth endpoint khong ton tai.', 404);
    }

    private function fileRoutes(string $method, array $segments): void
    {
        if ($method !== 'GET') {
            Response::error('Files endpoint khong ton tai.', 404);
            return;
        }

        if (!isset($_SESSION['user'])) {
            Response::error('Chua dang nhap.', 401);
            return;
        }

        $folder = (string) ($segments[1] ?? '');
        $fileNameRaw = (string) ($segments[2] ?? '');
        $allowedFolders = ['tai_lieu', 'bai_tap', 'bai_nop'];

        if ($folder === '' || $fileNameRaw === '' || !in_array($folder, $allowedFolders, true)) {
            Response::error('File khong hop le.', 422);
            return;
        }

        $fileName = basename(urldecode($fileNameRaw));
        $filePath = dirname(__DIR__, 2) . '/public/uploads/' . $folder . '/' . $fileName;

        if (!is_file($filePath)) {
            Response::error('Khong tim thay file.', 404);
            return;
        }

        $mimeType = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($filePath);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($fileName));
        readfile($filePath);
        exit;
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
}
