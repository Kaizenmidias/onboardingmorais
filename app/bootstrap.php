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
