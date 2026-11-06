<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

function app_config(): array {
    static $config;
    if ($config !== null) return $config;
    $file = APP_ROOT . '/config/config.php';
    if (!is_file($file)) {
        $config = [];
        return $config;
    }
    $config = require $file;
    date_default_timezone_set($config['app']['timezone'] ?? 'America/Sao_Paulo');
    return $config;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $c = app_config()['db'] ?? [];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function now(): string { return gmdate('Y-m-d H:i:s'); }
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}
function portal_mail(string $to, string $subject, string $body, string $from = 'gildasiomorais@moraiscontabilidade.com'): bool {
    $mail = app_config()['mail'] ?? [];
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    if (empty($mail['host']) || empty($mail['user']) || empty($mail['pass'])) {
        return @mail($to, $subject, $body, $headers, '-f' . $from);
    }
    $host = (string)$mail['host']; $port = (int)($mail['port'] ?? 465); $encryption = strtolower((string)($mail['encryption'] ?? 'ssl'));
    $endpoints = [[$host, $port, $encryption]];
    if ($port !== 587 || $encryption !== 'tls') $endpoints[] = [$host, 587, 'tls'];
    foreach ($endpoints as [$smtpHost, $smtpPort, $smtpEncryption]) {
        $fp = @stream_socket_client(($smtpEncryption === 'ssl' ? 'ssl://' : '') . $smtpHost . ':' . $smtpPort, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$fp) continue;
        stream_set_timeout($fp, 15);
        $read = static function ($fp): string { $out = ''; while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $out; };
        $write = static function ($fp, string $command, int $code) use ($read): bool { fwrite($fp, $command . "\r\n"); return str_starts_with($read($fp), (string)$code); };
        $ok = str_starts_with($read($fp), '220') && $write($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
        if ($ok && $smtpEncryption === 'tls') { $ok = $write($fp, 'STARTTLS', 220) && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $write($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250); }
        $ok = $ok && $write($fp, 'AUTH LOGIN', 334) && $write($fp, base64_encode((string)$mail['user']), 334) && $write($fp, base64_encode((string)$mail['pass']), 235);
        $ok = $ok && $write($fp, 'MAIL FROM:<' . $from . '>', 250) && $write($fp, 'RCPT TO:<' . $to . '>', 250) && $write($fp, 'DATA', 354);
        if ($ok) { $message = 'From: ' . $from . "\r\nReply-To: " . $from . "\r\nTo: " . $to . "\r\nSubject: " . $subject . "\r\n" . $headers . "\r\n" . preg_replace('/\r?\n\./', "\r\n..", $body) . "\r\n."; fwrite($fp, $message . "\r\n"); $ok = str_starts_with($read($fp), '250'); }
        @fwrite($fp, "QUIT\r\n"); fclose($fp); if ($ok) return true;
    }
    return @mail($to, $subject, $body, $headers, '-f' . $from);
}
function require_method(string $method): void { if ($_SERVER['REQUEST_METHOD'] !== $method) json_response(['error' => 'Método não permitido.'], 405); }
function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function check_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) json_response(['error' => 'Sessão expirada. Atualize a página e tente novamente.'], 419);
}
function current_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return $_SESSION['user'] ?? null;
}
function require_user(array $roles = []): array {
    $user = current_user();
    if (!$user) json_response(['error' => 'Não autenticado.'], 401);
    if ($roles && !in_array($user['role'], $roles, true)) json_response(['error' => 'Acesso negado.'], 403);
    return $user;
}
function ensure_audit_schema(): void {
    static $ready = false;
    if ($ready) return;
    $ready = true;
    $columns = [
        'message' => 'VARCHAR(500) NULL AFTER result',
        'ip_address' => 'VARCHAR(45) NULL AFTER message',
        'user_agent' => 'VARCHAR(255) NULL AFTER ip_address',
        'request_id' => 'CHAR(32) NULL AFTER user_agent',
    ];
    foreach ($columns as $name => $definition) {
        try { db()->exec("ALTER TABLE audit_events ADD COLUMN {$name} {$definition}"); } catch (Throwable) { /* já existe */ }
    }
    foreach ([
        'idx_audit_result_created' => 'ADD KEY idx_audit_result_created (result, created_at)',
        'idx_audit_entity' => 'ADD KEY idx_audit_entity (entity_type, entity_id)',
    ] as $index => $definition) {
        try { db()->exec("ALTER TABLE audit_events {$definition}"); } catch (Throwable) { /* já existe */ }
    }
}
function ensure_groups_schema(): void {
    static $ready = false;
    if ($ready) return;
    $ready = true;
    db()->exec("CREATE TABLE IF NOT EXISTS client_groups (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_client_groups_name (name),
        KEY idx_client_groups_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS client_group_members (
        group_id BIGINT UNSIGNED NOT NULL,
        client_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (group_id, client_id),
        KEY idx_group_members_client (client_id),
        CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES client_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_group_members_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function audit(?int $userId, string $action, string $type, string|int|null $entity = null, string $result = 'success', ?string $message = null): void {
    try {
        ensure_audit_schema();
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(16));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $s = db()->prepare('INSERT INTO audit_events (user_id, action_name, entity_type, entity_id, result, message, ip_address, user_agent, request_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$userId, substr($action, 0, 100), substr($type, 0, 60), $entity === null ? null : substr((string)$entity, 0, 64), substr($result, 0, 20), $message === null ? null : substr($message, 0, 500), $ip ? substr($ip, 0, 45) : null, $agent ? substr($agent, 0, 255) : null, substr((string)$requestId, 0, 32), now()]);
    } catch (Throwable $ignored) {
        error_log('Falha ao registrar auditoria: ' . $ignored->getMessage());
    }
}
function audit_failure(?int $userId, string $action, string $type = 'request', string|int|null $entity = null, ?Throwable $error = null): void {
    $message = $error ? preg_replace('/\s+/', ' ', trim($error->getMessage())) : 'Operação não concluída.';
    audit($userId, $action, $type, $entity, 'error', $message ?: 'Operação não concluída.');
}
function slugify(string $value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'item');
    return trim($value, '-') ?: 'item';
}
function admin_user(): array { return require_user(['agency_admin', 'office_admin']); }
