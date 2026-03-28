<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/core/Api.php';
require_once __DIR__ . '/core/Utils.php';
require_once __DIR__ . '/core/Response.php';

header('Access-Control-Allow-Origin: *');
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
    Response::error('Loi he thong API: ' . $e->getMessage(), 500);
}
