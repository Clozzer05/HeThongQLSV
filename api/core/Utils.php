<?php

declare(strict_types=1);

function normalizeApiPath(string $requestUri, string $scriptName): string
{
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }

    if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
        $path = $_SERVER['PATH_INFO'];
    }

    return '/' . trim((string) $path, '/');
}
