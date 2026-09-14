<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$token = getenv('INSTAGRAM_ACCESS_TOKEN');

if (!$token) {
    http_response_code(503);
    echo json_encode(['error' => 'Instagram não configurado.']);
    exit;
}

$fields = implode(',', [
    'id',
    'caption',
    'media_type',
    'media_url',
    'thumbnail_url',
    'permalink',
    'timestamp'
]);

$url = 'https://graph.instagram.com/me/media?' . http_build_query([
    'fields' => $fields,
    'limit' => 50,
    'access_token' => $token
]);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json']
]);

$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($body === false || $status < 200 || $status >= 300) {
    error_log('Falha na Instagram API: ' . ($error ?: 'HTTP ' . $status));
    http_response_code(502);
    echo json_encode(['error' => 'Não foi possível sincronizar o Instagram.']);
    exit;
}

$payload = json_decode($body, true);
$items = is_array($payload['data'] ?? null) ? $payload['data'] : [];

echo json_encode(
    ['items' => $items],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
