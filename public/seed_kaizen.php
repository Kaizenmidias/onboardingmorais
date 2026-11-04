<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$token = $_GET['token'] ?? '';
if (!hash_equals('kaizen-seed-2026', (string)$token)) { http_response_code(404); exit; }
$cfg = app_config();
$pdo = new PDO("mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};dbname={$cfg['db']['name']};charset=utf8mb4", $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$hash = password_hash('Kaizen@@2026', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users(name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role='office_admin',status='active'")->execute(['Kaizen Administrador','kaizen',$hash,'office_admin','active',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
