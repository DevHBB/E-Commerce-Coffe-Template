<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tester les includes de base
define("ROOT", dirname(__DIR__));
define("DATA_DIR", ROOT . "/data");
define("LOG_DIR", ROOT . "/data/.logs");
define("SKIP_MAINTENANCE", 1);

echo "<h2>Diagnostic Café Maison</h2>";

// Test 1: config.php
try {
    require_once ROOT . "/includes/config.php";
    echo "<p style='color:green'>✓ config.php chargé</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>✗ config.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: settings.json
try {
    $cfg = cfg_get();
    echo "<p style='color:green'>✓ cfg_get() OK (" . count($cfg) . " clés)</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>✗ cfg_get(): " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 3: session
echo "<p style='color:blue'>Session ID: " . session_id() . "</p>";
echo "<p style='color:blue'>Session admin: " . (isset($_SESSION["_admin"]) ? "OUI" : "NON") . "</p>";
echo "<p style='color:blue'>is_logged: " . (is_logged() ? "OUI" : "NON") . "</p>";

// Test 4: mailer
try {
    require_once ROOT . "/includes/mailer.php";
    echo "<p style='color:green'>✓ mailer.php chargé</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>✗ mailer.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='./'>← Retour admin</a></p>";
