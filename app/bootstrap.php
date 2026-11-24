<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(['httponly'=>true,'secure'=>$isHttps,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('Configuração ausente. Configure config.php.'); }
$config = require $configFile;
function db(): PDO {
    static $pdo=null; global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn=sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$config['db_host'],$config['db_name']);
    $pdo=new PDO($dsn,$config['db_user'],$config['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function current_user(): ?array {
    static $loaded=false; static $user=null;
    if ($loaded) return $user; $loaded=true;
    if (empty($_SESSION['user_id'])) return null;
    $st=db()->prepare('SELECT id,name,email,role FROM users WHERE id=?');
    $st->execute([(int)$_SESSION['user_id']]); $user=$st->fetch()?:null;
    if (!$user) unset($_SESSION['user_id']);
    return $user;
}
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    $sent=(string)($_POST['csrf']??'');
    if($sent===''||!hash_equals(csrf_token(),$sent)){http_response_code(419);exit('Sessão expirada. Atualize a página e tente novamente.');}
}
function require_post(): void { if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');} }
function property_access(int $id): ?array {
    $u=current_user(); if(!$u||$id<1)return null;
    $st=db()->prepare("SELECT p.*,p.owner_id=(?) AS is_owner,pm.permission FROM properties p LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE p.id=? AND (p.owner_id=? OR pm.user_id=?) LIMIT 1");
    $st->execute([$u['id'],$u['id'],$id,$u['id'],$u['id']]); return $st->fetch()?:null;
}
function find_accessible_property(int $id): ?array { return property_access($id); }
function can_manage_property(array $p): bool { return (int)$p['owner_id']===(int)current_user()['id'] || (($p['permission']??'')==='manage'); }
function can_edit_property(array $p): bool { return can_manage_property($p) || (($p['permission']??'')==='edit'); }
function user_properties(): array {
    $u=current_user(); if(!$u)return [];
    $st=db()->prepare("SELECT p.*,(SELECT COUNT(*) FROM fields f WHERE f.property_id=p.id) field_count,(p.owner_id=?) is_owner,pm.permission FROM properties p LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE p.owner_id=? OR pm.user_id=? ORDER BY p.created_at DESC");
    $st->execute([$u['id'],$u['id'],$u['id'],$u['id']]); return $st->fetchAll();
}
function count_recent_activities(int $userId): int {
    $st=db()->prepare("SELECT COUNT(*) FROM activities a JOIN properties p ON p.id=a.property_id LEFT JOIN property_members pm ON pm.property_id=p.id AND pm.user_id=? WHERE (p.owner_id=? OR pm.user_id=?) AND a.activity_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)");
    $st->execute([$userId,$userId,$userId]); return (int)$st->fetchColumn();
}
function flash(string $message): void { $_SESSION['notice']=$message; }
