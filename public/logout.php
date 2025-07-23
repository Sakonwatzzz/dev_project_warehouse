<?php
// logout.php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// ล้าง cookies
setcookie('access_token', '', time() - 3600, '/', '', true, true);
setcookie('refresh_token', '', time() - 3600, '/', '', true, true);

// ล้างเซสชัน (ถ้าใช้)
session_start();
session_unset();
session_destroy();

header('Location: login.php');
exit();
?>