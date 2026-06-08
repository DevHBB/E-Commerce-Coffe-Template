<?php
// Test ultra simple - désactivé en prod
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/includes/config.php';

echo "<pre>";
echo "PHP OK\n";
echo "ROOT: " . ROOT . "\n";
$cfg = cfg_get();
echo "cfg_get() OK: " . count($cfg) . " clés\n";

// Simuler layout.php
$msgs_all = db('messages');
echo "db(messages) OK: " . count($msgs_all) . "\n";

// Vérifier si quelque chose dans header.php cause le problème
echo "Test terminé\n";
