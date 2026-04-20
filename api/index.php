<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => $isHttps ? 'None' : 'Lax',
]);

session_start();

$requestId = bin2hex(random_bytes(8));
$_SERVER['APP_REQUEST_ID'] = $requestId;
header('X-Request-Id: ' . $requestId);

require_once __DIR__ . '/core/Api.php';
require_once __DIR__ . '/core/Utils.php';
require_once __DIR__ . '/core/Response.php';


$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = normalizeApiPath($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['SCRIPT_NAME'] ?? '/api/index.php');
$segments = array_values(array_filter(explode('/', $path)));

try {
    $api = new Api();
    $api->run($method, $segments);
} catch (Throwable $e) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $userId = $_SESSION['user']['id'] ?? 'guest';
    error_log(sprintf(
        '[API_EXCEPTION] request_id=%s method=%s uri=%s user_id=%s error=%s trace=%s',
        $requestId,
        $method,
        $requestUri,
        (string) $userId,
        $e->getMessage(),
        $e->getTraceAsString()
    ));

    Response::error('Loi he thong API.', 500, [
        'request_id' => $requestId,
    ]);
}
