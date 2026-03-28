<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Response.php';

class AuthController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function login(array $body): void
    {
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

        session_regenerate_id(true);
        $_SESSION['user'] = $user;

        Response::json([
            'success' => true,
            'message' => 'Dang nhap thanh cong.',
            'data' => $user,
        ]);
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
        Response::json(['success' => true, 'message' => 'Da dang xuat.']);
    }

    public function me(): void
    {
        if (!isset($_SESSION['user'])) {
            Response::error('Chua dang nhap.', 401);
            return;
        }

        Response::json(['success' => true, 'data' => $_SESSION['user']]);
    }
}
