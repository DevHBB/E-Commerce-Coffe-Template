<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$result = [];

// TEST 1: Lire settings.json directement
$raw = file_get_contents(DATA_DIR . '/settings.json');
$decoded = json_decode($raw, true);
$result['test1_read'] = is_array($decoded) ? 'OK ('.count($decoded).' clés)' : 'ERREUR: '.json_last_error_msg();
$result['stripe_pk_actuel'] = $decoded['stripe_pk'] ?? '(vide)';
$result['stripe_enabled_actuel'] = var_export($decoded['stripe_enabled'] ?? null, true);

// TEST 2: Écriture directe
$test_val = 'pk_test_DEBUG_'.time();
$decoded['stripe_pk'] = $test_val;
$json = json_encode($decoded, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$bytes = file_put_contents(DATA_DIR . '/settings.json', $json, LOCK_EX);
$result['test2_write'] = $bytes !== false ? "OK ($bytes bytes écrits)" : 'ERREUR écriture!';

// TEST 3: Relire pour confirmer
$raw2 = file_get_contents(DATA_DIR . '/settings.json');
$decoded2 = json_decode($raw2, true);
$result['test3_reread'] = ($decoded2['stripe_pk'] ?? '') === $test_val ? 'OK - valeur confirmée' : 'ERREUR - valeur pas sauvegardée!';
$result['stripe_pk_apres'] = $decoded2['stripe_pk'] ?? '(vide)';

// TEST 4: Remettre à vide
$decoded2['stripe_pk'] = '';
file_put_contents(DATA_DIR . '/settings.json', json_encode($decoded2, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
$result['test4_reset'] = 'OK - remis à vide';

// TEST 5: Permissions
$result['DATA_DIR'] = DATA_DIR;
$result['is_writable'] = is_writable(DATA_DIR) ? 'OUI' : 'NON ← PROBLÈME';
$result['settings_writable'] = is_writable(DATA_DIR.'/settings.json') ? 'OUI' : 'NON ← PROBLÈME';
$result['php_user'] = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'inconnu';

// TEST 6: Simuler exactement ce que fait settings.php avec POST stripe_pk
$_POST['stripe_pk'] = 'pk_test_SIMUL_123';
$cfg = cfg_get();
$val = trim($_POST['stripe_pk'] ?? '');
if ($val !== '' && strpos($val, '•') === false) {
    $cfg['stripe_pk'] = $val;
}
$save_ok = cfg_save($cfg);
$cfg_after = cfg_get();
$result['test6_simulate_save'] = $save_ok ? 'cfg_save retourne TRUE' : 'cfg_save retourne FALSE ← PROBLÈME';
$result['test6_stripe_pk_after'] = $cfg_after['stripe_pk'] ?? '(vide)';
$result['test6_match'] = ($cfg_after['stripe_pk'] ?? '') === 'pk_test_SIMUL_123' ? 'OK ✓' : 'ERREUR ← valeur non sauvegardée';

// Remettre à vide
$cfg_after['stripe_pk'] = '';
cfg_save($cfg_after);

// TEST 7: POST réel - vérifier ce qui arrive quand le form settings est soumis
$result['test7_note'] = 'Si tous les tests ci-dessus passent, le problème vient du navigateur ou du form HTML';

// Affichage
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Debug Save</title>';
echo '<style>body{font-family:monospace;padding:2rem;background:#f5f5f5}';
echo '.ok{color:green}.err{color:red;font-weight:bold}';
echo 'table{border-collapse:collapse;width:100%}td{padding:.4rem .8rem;border:1px solid #ddd;background:#fff}';
echo 'th{background:#333;color:#fff;padding:.4rem .8rem;text-align:left}';
echo '</style></head><body>';
echo '<h2>🔍 Debug Sauvegarde Settings</h2>';
echo '<table><tr><th>Test</th><th>Résultat</th></tr>';
foreach ($result as $k => $v) {
    $cls = (strpos($v, 'ERREUR') !== false || strpos($v, 'NON') !== false) ? 'err' : 'ok';
    echo "<tr><td>$k</td><td class='$cls'>".htmlspecialchars($v)."</td></tr>";
}
echo '</table>';
echo '<br><a href="./settings.php">← Retour paramètres</a>';
echo '</body></html>';
