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
        'expires' => time() + 600, // 10 นาที
        'path' => '/',
        'httponly' => true,
        'secure' => $secure, //เปิดเป็น true ถ้าต้องการใช้ https
        'samesite' => 'Strict'
    ]);

    setcookie('refresh_token', $refreshToken, [
        'expires' => time() + (86400 * 7), // 7 วัน
        'path' => '/',
        'httponly' => true,
        'secure' => $secure, //เปิดเป็น true ถ้าต้องการใช้ https
        'samesite' => 'Strict'
    ]);
}

function getToken($tokenName) {
    return $_COOKIE[$tokenName] ?? null;
}

function validateToken($token) {
    global $secretKey;
    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return !isTokenExpired($decoded);
    } catch (Exception $e) {
        return false;
    }
}

function isTokenExpired($decoded) {
    return isset($decoded->exp) && $decoded->exp < time();
}

function createTokens($username, $role) {
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

function refreshToken($refreshToken) {
    global $secretKey;
    try {
        $decoded = JWT::decode($refreshToken, new Key($secretKey, 'HS256'));
        if (($decoded->type ?? '') === 'refresh' && $decoded->exp > time()) {
            // ถ้าต้องการ role ใหม่ ให้ map จาก DB ได้ตรงนี้
            $username = $decoded->username;
            $role = getUserRole($username); // สมมุติว่าฟังก์ชันนี้มี
            return createTokens($username, $role);
        }
    } catch (Exception $e) {
        return null;
    }
    return null;
}

function checkRole($requiredRole) {
    $accessToken = getToken('access_token');

    if (!$accessToken || !validateToken($accessToken)) {
        $refreshToken = getToken('refresh_token');
        if ($refreshToken && validateToken($refreshToken)) {
            $accessToken = refreshToken($refreshToken);
            if (!$accessToken) {
                redirectToLogin();
            }
        } else {
            redirectToLogin();
        }
    }

    try {
        global $secretKey;
        $decoded = JWT::decode($accessToken, new Key($secretKey, 'HS256'));
        return ($decoded->role ?? '') === $requiredRole;
    } catch (Exception $e) {
        return false;
    }
}

function redirectToLogin() {
    header('Location: login.php');
    exit();
}

// ตัวอย่างจำลองการดึง role จากฐานข้อมูล
function getUserRole($username) {
    // TODO: ดึงจาก DB จริง
    if ($username === 'admin123') return 'admin';
    if ($username === 'dee123') return 'manager';
    return 'user';
}
