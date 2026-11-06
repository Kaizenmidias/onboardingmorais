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
function portal_mail_config(): array {
    $mail = app_config()['mail'] ?? [];
    // SMTP settings can be adjusted by an administrator without editing PHP.
    // The password is intentionally never exposed by bootstrap responses.
    try {
        $rows = db()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'mail.%'")->fetchAll();
        foreach ($rows as $row) {
            $name = substr((string)$row['setting_key'], 5);
            if ($name !== '') $mail[$name] = (string)$row['setting_value'];
        }
    } catch (Throwable) { /* settings table may not exist during installation */ }
    return $mail;
}

function portal_mail(string $to, string $subject, string $body, string $from = ''): bool {
    $mail = portal_mail_config();
    $configuredFrom = (string)($mail['user'] ?? 'gildasiomorais@moraiscontabilidade.com');
    // Older API calls passed the original demonstrator address explicitly.
    // Treat it as legacy so all outgoing messages use the configured mailbox.
    $from = ($from === '' || $from === 'gildasiomorais@moraiscontabilidade.com') ? $configuredFrom : $from;
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

function portal_mail_diagnostic(array $mail, string $to): array {
    $host = trim((string)($mail['host'] ?? ''));
    $port = (int)($mail['port'] ?? 465);
    $encryption = strtolower(trim((string)($mail['encryption'] ?? 'ssl')));
    $user = trim((string)($mail['user'] ?? ''));
    $pass = (string)($mail['pass'] ?? '');
    if ($host === '' || $user === '' || $pass === '') return ['ok' => false, 'stage' => 'config', 'message' => 'Preencha servidor, usuário e senha SMTP.'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'stage' => 'recipient', 'message' => 'Informe um e-mail de teste válido.'];
    if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) return ['ok' => false, 'stage' => 'config', 'message' => 'Criptografia inválida. Use SSL, TLS ou Nenhuma.'];
    $endpoint = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($endpoint, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) return ['ok' => false, 'stage' => 'connect', 'host' => $host, 'port' => $port, 'encryption' => $encryption, 'message' => "Não foi possível conectar ao servidor ({$errno}): {$errstr}"];
    stream_set_timeout($fp, 15);
    $read = static function ($socket): string { $out = ''; while (($line = fgets($socket, 515)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $out; };
    $write = static function ($socket, string $command, int $code) use ($read): array { fwrite($socket, $command . "\r\n"); $response = $read($socket); return ['ok' => str_starts_with($response, (string)$code), 'response' => trim($response)]; };
    $greeting = $read($fp);
    if (!str_starts_with($greeting, '220')) { fclose($fp); return ['ok' => false, 'stage' => 'greeting', 'message' => 'O servidor SMTP não aceitou a conexão.', 'response' => trim($greeting)]; }
    $ehlo = $write($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
    if (!$ehlo['ok']) { fclose($fp); return ['ok' => false, 'stage' => 'ehlo', 'message' => 'O servidor recusou o EHLO.', 'response' => $ehlo['response']]; }
    if ($encryption === 'tls') {
        $start = $write($fp, 'STARTTLS', 220);
        if (!$start['ok'] || !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return ['ok' => false, 'stage' => 'tls', 'message' => 'Não foi possível ativar TLS.', 'response' => $start['response'] ?? '']; }
        $ehlo = $write($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
        if (!$ehlo['ok']) { fclose($fp); return ['ok' => false, 'stage' => 'ehlo', 'message' => 'O servidor recusou o EHLO após TLS.', 'response' => $ehlo['response']]; }
    }
    foreach ([['AUTH LOGIN', 334], [base64_encode($user), 334], [base64_encode($pass), 235]] as [$command, $code]) {
        $auth = $write($fp, (string)$command, (int)$code);
        if (!$auth['ok']) { fclose($fp); return ['ok' => false, 'stage' => 'auth', 'message' => 'Usuário ou senha SMTP recusados.', 'response' => $auth['response']]; }
    }
    $from = $user;
    $mailFrom = $write($fp, 'MAIL FROM:<' . $from . '>', 250);
    $rcpt = $mailFrom['ok'] ? $write($fp, 'RCPT TO:<' . $to . '>', 250) : ['ok' => false, 'response' => $mailFrom['response']];
    if (!$rcpt['ok']) { fclose($fp); return ['ok' => false, 'stage' => 'recipient', 'message' => 'O servidor recusou o destinatário.', 'response' => $rcpt['response']]; }
    $data = $write($fp, 'DATA', 354);
    if (!$data['ok']) { fclose($fp); return ['ok' => false, 'stage' => 'data', 'message' => 'O servidor recusou o envio.', 'response' => $data['response']]; }
    $subject = 'Teste SMTP do portal';
    $message = "From: {$from}\r\nTo: {$to}\r\nSubject: {$subject}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nEste é um teste de configuração SMTP do portal.\r\n.";
    fwrite($fp, $message . "\r\n");
    $sent = $read($fp);
    fwrite($fp, "QUIT\r\n"); fclose($fp);
    return ['ok' => str_starts_with($sent, '250'), 'stage' => 'send', 'host' => $host, 'port' => $port, 'encryption' => $encryption, 'message' => str_starts_with($sent, '250') ? 'Teste enviado com sucesso.' : 'O servidor não confirmou o envio.', 'response' => trim($sent)];
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
