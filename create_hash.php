<?php
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
file_put_contents(DATA_DIR . '/.admin_hash', $hash, LOCK_EX);
chmod(DATA_DIR . '/.admin_hash', 0600);
echo "Hash créé: " . substr($hash, 0, 20) . "...\n";
