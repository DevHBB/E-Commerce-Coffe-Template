<?php
/**
 * OUTIL DE DÉBLOCAGE D'URGENCE
 * ⚠ CE FICHIER SE SUPPRIME AUTOMATIQUEMENT APRÈS USAGE
 * Accès : /admin/reset_ratelimit.php?token=VOTRE_TOKEN
 */
// Token à usage unique stocké dans data/
$token_file = ROOT . '/data/.reset_token';

// Générer le token au premier accès
if (!file_exists($token_file)) {
    $token = bin2hex(random_bytes(16));
    file_put_contents($token_file, $token, LOCK_EX);
    chmod($token_file, 0600);
    // Afficher le token UNE SEULE FOIS dans les logs serveur
    error_log('[CAFE MAISON] Reset token generated: ' . $token);
    die('<h2>Token généré.</h2><p>Consultez les logs PHP/Apache pour récupérer le token, puis revenez ici avec <code>?token=VOTRE_TOKEN</code></p>');
}

$stored_token = trim(file_get_contents($token_file));
$given_token  = $_GET['token'] ?? '';

if (!$given_token || !hash_equals($stored_token, $given_token)) {
    http_response_code(403);
    die('Token invalide.');
}

// Token valide → réinitialiser ET supprimer le token + ce fichier
require_once __DIR__ . '/../includes/config.php';
require_auth();
$count = 0;
foreach (glob(DATA_DIR . '/.rl_*.json') as $f) { unlink($f); $count++; }

// Supprimer le token et CE fichier après usage
unlink($token_file);
unlink(__FILE__);

echo "<h2>✅ Déblocage effectué ($count fichier(s) supprimé(s))</h2>";
echo "<p>Ce fichier s'est auto-supprimé. <a href='./login.php'>Se connecter →</a></p>";
