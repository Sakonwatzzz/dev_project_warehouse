<?php
require_once __DIR__ . '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// โหลด .env
if (!isset($_ENV['JWT_SECRET'])) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->safeLoad();
}

$secretKey = $_ENV['JWT_SECRET'] ?? 'your_default_secret_key';

function setAuthTokens($accessToken, $refreshToken) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    
    setcookie('access_token', $accessToken, [
        'expires' => time() + 86400, // 1 วัน
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax' // เปลี่ยนจาก Strict เป็น Lax
    ]);

    setcookie('refresh_token', $refreshToken, [
        'expires' => time() + (86400 * 7), // 7 วัน
        'path' => '/',
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax'
    ]);
}

function getToken($tokenName)
{
    return $_COOKIE[$tokenName] ?? null;
}

function validateToken($token)
{
    global $secretKey;
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return !isTokenExpired($decoded);
    } catch (Exception $e) {
        return false;
    }
}

function isTokenExpired($decoded)
{
    return isset($decoded->exp) && $decoded->exp < time();
}

function createTokens($username, $role)
{
    global $secretKey;
    $issuedAt = time();

    $accessPayload = [
        'iat' => $issuedAt,
        'exp' => $issuedAt + 600, // 10 นาที
        'username' => $username,
        'role' => $role,
        'type' => 'access'
    ];

    $refreshPayload = [
        'iat' => $issuedAt,
        'exp' => $issuedAt + (86400 * 7), // 7 วัน
        'username' => $username,
        'type' => 'refresh'
    ];

    $accessToken = JWT::encode($accessPayload, $secretKey, 'HS256');
    $refreshToken = JWT::encode($refreshPayload, $secretKey, 'HS256');

    setAuthTokens($accessToken, $refreshToken);
    return $accessToken;
}

function refreshToken($refreshToken)
{
    global $secretKey;
    try {
        $decoded = JWT::decode($refreshToken, new Key($secretKey, 'HS256'));
        if (($decoded->type ?? '') !== 'refresh') {
            error_log("refreshToken: Invalid token type");
            return null;
        }
        if ($decoded->exp < time()) {
            error_log("refreshToken: Refresh token expired for user {$decoded->username}");
            return null;
        }
        $username = $decoded->username;
        $role = getUserRole($username);
        if (!$role || $role === 'user') {
            error_log("refreshToken: Invalid role for username $username");
            return null;
        }
        return createTokens($username, $role);
    } catch (Exception $e) {
        error_log("refreshToken error: " . $e->getMessage());
        return null;
    }
}

function checkRole($requiredRole)
{
    global $secretKey;
    $accessToken = getToken('access_token');
    if (!$accessToken || !validateToken($accessToken)) {
        $refreshToken = getToken('refresh_token');
        if ($refreshToken && validateToken($refreshToken)) {
            $accessToken = refreshToken($refreshToken);
            if (!$accessToken) {
                error_log("checkRole: Failed to refresh access token");
                redirectToLogin();
            }
        } else {
            error_log("checkRole: Invalid or missing refresh token");
            redirectToLogin();
        }
    }
    try {
        $decoded = JWT::decode($accessToken, new Key($secretKey, 'HS256'));
        $role = $decoded->role ?? 'user';
        if ($role !== $requiredRole) {
            error_log("checkRole: Role mismatch, expected $requiredRole, got $role");
        }
        return $role === $requiredRole;
    } catch (Exception $e) {
        error_log("checkRole error: " . $e->getMessage());
        return false;
    }
}

function redirectToLogin()
{
    header('Location: login.php');
    exit();
}

function getUserRole($username)
{
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
            SELECT pt.personnel_type_name AS role 
            FROM personnel p 
            LEFT JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id 
            WHERE p.personnel_username = :username
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $role = $user['role'] ?? 'user';
        if ($role === 'user') {
            error_log("getUserRole: Default role 'user' assigned for username $username");
        }
        return $role;
    } catch (Exception $e) {
        error_log("getUserRole error: " . $e->getMessage());
        return 'user';
    }
}
