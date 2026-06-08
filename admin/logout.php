<?php
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}
if (is_logged()) {
    security_log('logout', 'Admin logged out');
}
do_logout();
header('Location: ./login.php');
exit;
