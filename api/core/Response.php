<?php

class Response
{
    public static function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        if (is_array($data) && !array_key_exists('success', $data)) {
            $data['success'] = $statusCode < 400;
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'error' => [
                'status' => $statusCode,
                'message' => $message,
            ],
        ], $statusCode);
    }
}
