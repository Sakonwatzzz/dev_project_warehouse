<?php
header('Content-Type: application/json');
require_once __DIR__ . '../../../../../config/database_server.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    echo json_encode(['error' => 'Session timeout']);
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Set database timezone to match PHP
$pdo->exec("SET time_zone = '+07:00'");

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Logic to generate PDF (to be implemented)
    exit;
} elseif (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Logic to generate Excel (to be implemented)
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>