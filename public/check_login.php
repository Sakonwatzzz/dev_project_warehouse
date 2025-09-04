<?php
require_once __DIR__ . '/auth_handler.php';

header('Content-Type: application/json');

$accessToken = getToken('access_token');
$loggedIn = $accessToken && validateToken($accessToken);

echo json_encode(['loggedIn' => $loggedIn]);
?>