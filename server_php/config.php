<?php
// LIVO - PHP 7.4 configuration
// Domain preset for this deployment.
define('APP_NAME', 'LIVO');
define('ADMIN_NAME', 'LIVO Admin');
define('APP_URL', 'https://bbb.ezzy500.vip');
define('API_URL', APP_URL . '/api');

// Installer writes these values to config.local.php.
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
} else {
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', 'CHANGE_ME_DB');
    if (!defined('DB_USER')) define('DB_USER', 'CHANGE_ME_USER');
    if (!defined('DB_PASS')) define('DB_PASS', 'CHANGE_ME_PASS');
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (DB_NAME === 'CHANGE_ME_DB') {
        throw new RuntimeException('Database is not configured. Open /install.php first.');
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bearer_token() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) return $m[1];
    return null;
}

function current_user() {
    $token = bearer_token();
    if (!$token) return null;
    $stmt = db()->prepare('SELECT u.* FROM auth_tokens t JOIN users u ON u.id=t.user_id WHERE t.token=? AND t.expires_at>NOW() AND u.is_blocked=0 LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function require_user() {
    $u = current_user();
    if (!$u) json_response(['ok'=>false,'message'=>'Unauthorized'], 401);
    return $u;
}

function input_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) return $data;
    return is_array($_POST) ? $_POST : [];
}

function random_token() {
    return bin2hex(random_bytes(32));
}

function public_user($u) {
    return [
        'id'=>(int)$u['id'],
        'name'=>$u['name'],
        'email'=>$u['email'] ?? null,
        'bio'=>$u['bio'] ?? '',
        'avatar'=>$u['avatar'] ?? null,
        'created_at'=>$u['created_at'] ?? null,
    ];
}
