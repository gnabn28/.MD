<?php
/**
 * QR_PROXY.PHP – Lấy ảnh QR từ VietQR qua server PHP
 * Tránh lỗi CORS/firewall khi browser gọi trực tiếp
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$bankId  = $_GET['bank']    ?? 'MB';
$account = $_GET['account'] ?? '';
$amount  = (int)($_GET['amount'] ?? 0);
$content = $_GET['content'] ?? '';
$name    = $_GET['name']    ?? '';

if (!$account || !$amount) {
    http_response_code(400);
    exit('Bad Request');
}

$url = sprintf(
    'https://img.vietqr.io/image/%s-%s-compact.png?amount=%d&addInfo=%s&accountName=%s',
    urlencode($bankId),
    urlencode($account),
    $amount,
    urlencode($content),
    urlencode($name)
);

// Dùng cURL để tải ảnh từ VietQR
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 CellPhoneK/1.0',
]);
$imageData = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error     = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200 || !$imageData) {
    http_response_code(502);
    exit('QR fetch failed: ' . ($error ?: "HTTP $httpCode"));
}

header('Content-Type: image/png');
header('Cache-Control: private, max-age=300');
echo $imageData;
