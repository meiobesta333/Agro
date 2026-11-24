<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax']);
    session_start();
}
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Configuração ausente. Copie config.example.php para config.php e informe os dados MySQL da hospedagem.');
}
$config = require $configFile;
function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function current_user(): ?array {
    static $loaded = false; static $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id,name,email,role FROM users WHERE id=?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) unset($_SESSION['user_id']);
    return $user;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $sent = (string)($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419); exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}
function require_post(): void { if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); } }
function find_accessible_property(int $id): ?array {
    if ($id < 1 || !current_user()) return null;
    $stmt = db()->prepare('SELECT * FROM properties WHERE id=? AND owner_id=?');
    $stmt->execute([$id,current_user()['id']]);
    return $stmt->fetch() ?: null;
}
function count_recent_activities(int $userId): int {
    $stmt=db()->prepare('SELECT COUNT(*) FROM activities a JOIN properties p ON p.id=a.property_id WHERE p.owner_id=? AND a.activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute([$userId]); return (int)$stmt->fetchColumn();
}
