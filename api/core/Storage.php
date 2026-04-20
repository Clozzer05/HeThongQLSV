<?php

declare(strict_types=1);

class Storage
{
    private const METADATA_TOKEN_URL = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token';

    private static ?string $cachedToken = null;
    private static int $cachedTokenExpiry = 0;

    public static function isGcs(): bool
    {
        return strtolower(trim((string) getenv('STORAGE_DRIVER'))) === 'gcs';
    }

    public static function saveUploadedFile(array $file, string $folder, string $name): bool
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            return false;
        }

        if (!self::isGcs()) {
            $dir = self::localUploadDir($folder);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                return false;
            }
            if (!is_writable($dir)) {
                @chmod($dir, 0777);
            }
            if (!is_writable($dir)) {
                return false;
            }
            return move_uploaded_file($tmpName, $dir . $name);
        }

        $objectKey = self::normalizeObjectKey($folder, $name);
        return self::uploadFileToGcs($tmpName, $objectKey);
    }

    public static function deleteStoredFile(string $folder, string $storedValue): void
    {
        $objectKey = self::normalizeObjectKey($folder, $storedValue);
        if ($objectKey === '') {
            return;
        }

        if (!self::isGcs()) {
            $path = self::localUploadsRoot() . '/' . $objectKey;
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }

        self::deleteObjectFromGcs($objectKey);
    }

    public static function objectExists(string $folder, string $storedValue): bool
    {
        $objectKey = self::normalizeObjectKey($folder, $storedValue);
        if ($objectKey === '') {
            return false;
        }

        if (!self::isGcs()) {
            return is_file(self::localUploadsRoot() . '/' . $objectKey);
        }

        return self::objectExistsInGcs($objectKey);
    }

    public static function streamToClient(string $folder, string $storedValue, string $downloadName): bool
    {
        $objectKey = self::normalizeObjectKey($folder, $storedValue);
        if ($objectKey === '') {
            return false;
        }

        if (!self::isGcs()) {
            $path = self::localUploadsRoot() . '/' . $objectKey;
            if (!is_file($path)) {
                return false;
            }

            $mimeType = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detected = mime_content_type($path);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            }

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
            readfile($path);
            return true;
        }

        return self::streamObjectFromGcs($objectKey, $downloadName);
    }

    public static function normalizeObjectKey(string $folder, string $storedValue): string
    {
        $value = trim($storedValue);
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        $parts = array_values(array_filter(explode('/', $value), static fn($part) => $part !== ''));
        if ($parts === []) {
            return '';
        }

        if (count($parts) >= 2 && in_array($parts[0], ['tai_lieu', 'bai_tap', 'bai_nop'], true)) {
            return implode('/', $parts);
        }

        return $folder . '/' . end($parts);
    }

    private static function localUploadsRoot(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads';
    }

    private static function localUploadDir(string $folder): string
    {
        return self::localUploadsRoot() . '/' . $folder . '/';
    }

    private static function gcsBucket(): string
    {
        return trim((string) getenv('GCS_BUCKET'));
    }

    private static function uploadFileToGcs(string $tmpFile, string $objectKey): bool
    {
        $bucket = self::gcsBucket();
        if ($bucket === '' || !is_file($tmpFile)) {
            return false;
        }

        $token = self::fetchAccessToken();
        if ($token === null) {
            error_log('[GCS_TOKEN_ERROR] Khong lay duoc access token tu metadata server.');
            return false;
        }

        $content = file_get_contents($tmpFile);
        if ($content === false) {
            return false;
        }

        $url = sprintf(
            'https://storage.googleapis.com/upload/storage/v1/b/%s/o?uploadType=media&name=%s',
            rawurlencode($bucket),
            self::encodeObjectKey($objectKey)
        );

        [$status, $body] = self::httpRequest('POST', $url, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/octet-stream',
        ], $content);

        if ($status < 200 || $status >= 300) {
            error_log('[GCS_UPLOAD_ERROR] status=' . $status . ' object=' . $objectKey . ' body=' . $body);
            return false;
        }

        return true;
    }

    private static function objectExistsInGcs(string $objectKey): bool
    {
        $bucket = self::gcsBucket();
        if ($bucket === '') {
            return false;
        }

        $token = self::fetchAccessToken();
        if ($token === null) {
            return false;
        }

        $url = sprintf(
            'https://storage.googleapis.com/storage/v1/b/%s/o/%s',
            rawurlencode($bucket),
            self::encodeObjectKey($objectKey)
        );

        [$status] = self::httpRequest('GET', $url, [
            'Authorization: Bearer ' . $token,
        ]);

        return $status === 200;
    }

    private static function deleteObjectFromGcs(string $objectKey): void
    {
        $bucket = self::gcsBucket();
        if ($bucket === '') {
            return;
        }

        $token = self::fetchAccessToken();
        if ($token === null) {
            return;
        }

        $url = sprintf(
            'https://storage.googleapis.com/storage/v1/b/%s/o/%s',
            rawurlencode($bucket),
            self::encodeObjectKey($objectKey)
        );

        [$status, $body] = self::httpRequest('DELETE', $url, [
            'Authorization: Bearer ' . $token,
        ]);

        if (!in_array($status, [200, 204, 404], true)) {
            error_log('[GCS_DELETE_ERROR] status=' . $status . ' object=' . $objectKey . ' body=' . $body);
        }
    }

    private static function streamObjectFromGcs(string $objectKey, string $downloadName): bool
    {
        $bucket = self::gcsBucket();
        if ($bucket === '') {
            return false;
        }

        $token = self::fetchAccessToken();
        if ($token === null) {
            return false;
        }

        $url = sprintf(
            'https://storage.googleapis.com/storage/v1/b/%s/o/%s?alt=media',
            rawurlencode($bucket),
            self::encodeObjectKey($objectKey)
        );

        [$status, $body, $responseHeaders] = self::httpRequestWithHeaders('GET', $url, [
            'Authorization: Bearer ' . $token,
        ]);

        if ($status !== 200) {
            error_log('[GCS_STREAM_STATUS_ERROR] status=' . $status . ' object=' . $objectKey);
            return false;
        }

        $contentType = 'application/octet-stream';
        $contentLength = strlen($body);

        foreach ($responseHeaders as $line) {
            if (stripos($line, 'Content-Type:') === 0) {
                $contentType = trim(substr($line, 13));
            }
            if (stripos($line, 'Content-Length:') === 0) {
                $contentLength = (int) trim(substr($line, 15));
            }
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) $contentLength);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
        echo $body;
        return true;
    }

    private static function fetchAccessToken(): ?string
    {
        $now = time();
        if (self::$cachedToken !== null && $now < self::$cachedTokenExpiry - 30) {
            return self::$cachedToken;
        }

        [$status, $body] = self::httpRequest('GET', self::METADATA_TOKEN_URL, [
            'Metadata-Flavor: Google',
        ]);

        if ($status !== 200) {
            error_log('[GCS_METADATA_TOKEN_HTTP_ERROR] status=' . $status . ' body=' . $body);
            return null;
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['access_token'], $json['expires_in'])) {
            return null;
        }

        self::$cachedToken = (string) $json['access_token'];
        self::$cachedTokenExpiry = $now + (int) $json['expires_in'];
        return self::$cachedToken;
    }

    private static function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        [$status, $responseBody] = self::httpRequestWithHeaders($method, $url, $headers, $body);
        return [$status, $responseBody];
    }

    private static function httpRequestWithHeaders(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HEADER => true,
            ];

            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                return [0, $error, []];
            }

            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $responseHeaders = explode("\r\n", trim(substr($response, 0, $headerSize)));
            $responseBody = (string) substr($response, $headerSize);
            return [$status, $responseBody, $responseHeaders];
        }

        $headerLines = [];
        foreach ($headers as $header) {
            $headerLines[] = $header;
        }
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ];
        if ($body !== null) {
            $contextOptions['http']['content'] = $body;
        }

        $context = stream_context_create($contextOptions);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = self::parseStatusCode($responseHeaders);
        if ($responseBody === false) {
            return [$status, '', $responseHeaders];
        }
        return [$status, $responseBody, $responseHeaders];
    }

    private static function parseStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    private static function encodeObjectKey(string $objectKey): string
    {
        return str_replace('%2F', '/', rawurlencode($objectKey));
    }
}
