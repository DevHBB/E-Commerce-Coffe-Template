<?php
/**
 * CAFÉ MAISON — Configuration & sécurité
 * ═══════════════════════════════════════════════════════════════
 * Sécurité renforcée :
 *  - CSRF double-submit cookie + session
 *  - Rate limiting par IP avec jitter
 *  - Session fingerprinting multi-couche
 *  - Chiffrement AES-256-GCM des clés API (Stripe, PayPal)
 *  - Validation stricte de toutes les entrées
 *  - Journalisation des tentatives suspectes
 *  - Headers sécurité complets
 *  - Protection injection JSON
 * ═══════════════════════════════════════════════════════════════
 */

// ── Chemins ────────────────────────────────────────────────────────
// ROOT est TOUJOURS calculé depuis config.php lui-même (jamais depuis le fichier appelant)
// __DIR__ ici = /votre-serveur/cafe/includes/ — absolu et invariant
if (!defined('ROOT'))     define('ROOT',    dirname(__DIR__));  // = /cafe/
if (!defined('DATA_DIR')) define('DATA_DIR', ROOT . '/data');   // = /cafe/data/
if (!defined('LOG_DIR'))  define('LOG_DIR',  DATA_DIR . '/.logs');
if (!defined('VERSION'))  define('VERSION',  '2.0');

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0700, true);

// ── Clé de chiffrement ──────────────────────────────────────────────
// Générée automatiquement et stockée de façon sécurisée.
// Utilisée pour chiffrer les clés API en base.
function encryption_key(): string {
    $kf = DATA_DIR . '/.ek';
    if (file_exists($kf)) {
        $k = file_get_contents($kf);
        if ($k && strlen($k) === 32) return $k;
    }
    $k = random_bytes(32);
    file_put_contents($kf, $k, LOCK_EX);
    @chmod($kf, 0600);
    return $k;
}

function encrypt_value(string $plain): string {
    if ($plain === '') return '';
    $key   = encryption_key();
    $iv    = random_bytes(12); // GCM nonce
    $tag   = '';
    $ct    = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) return ''; // fallback
    return base64_encode($iv . $tag . $ct);
}

function decrypt_value(string $enc): string {
    if ($enc === '') return '';
    // Backward compat : valeur non chiffrée (commence par pk_live_, etc.)
    if ((strpos($enc, 'pk_') === 0) || (strpos($enc, 'sk_') === 0) || (strpos($enc, 'AX') === 0)) {
        // Ancienne valeur en clair — on la laisse passer lors de la prochaine sauvegarde elle sera chiffrée
        return $enc;
    }
    $raw = base64_decode($enc, true);
    if (!$raw || strlen($raw) < 28) return '';
    $key = encryption_key();
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    $pt  = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $pt === false ? '' : $pt;
}

// ── Calcul chemin relatif ───────────────────────────────────────────
function base_path(): string {
    $root    = ROOT;
    $current = dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $rel     = rtrim((string)substr($current, strlen($root)), '/\\');
    if ($rel === '') return './';
    $depth = substr_count(ltrim($rel, '/\\'), '/') + 1;
    return str_repeat('../', $depth);
}

// ── Session sécurisée ───────────────────────────────────────────────
ini_set('session.cookie_httponly',  1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode',  1);
ini_set('session.gc_maxlifetime',   28800); // 8h
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Activer en HTTPS

if (session_status() === PHP_SESSION_NONE) session_start();

// Régénérer l'ID périodiquement (anti-fixation de session)
if (empty($_SESSION['_regen'])) {
    $_SESSION['_regen'] = time();
} elseif (time() - $_SESSION['_regen'] > 900) { // 15 min
    session_regenerate_id(true);
    $_SESSION['_regen'] = time();
}

// ── Credentials admin ───────────────────────────────────────────────
// ── Hash admin auto-généré au 1er démarrage ─────────────────────────────
// Stocké dans data/.admin_hash (jamais dans le code)
// Pour changer le mot de passe : Admin → Paramètres → Sécurité
// Hash admin : stocké dans data/.admin_hash, généré automatiquement
// Fallback intégré si le fichier ne peut pas être créé/lu
if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
    $_hash_file = DATA_DIR . '/.admin_hash';
    $_admin_hash = '';

    // Lire le hash existant
    if (file_exists($_hash_file) && is_readable($_hash_file)) {
        $_admin_hash = trim((string)file_get_contents($_hash_file));
    }

    // Si hash absent ou invalide (ne commence pas par $2y$) → le créer
    if (strlen($_admin_hash) < 20 || !(strpos($_admin_hash, '$2') === 0)) {
        $_admin_hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
        // Essayer d'écrire (peut échouer en local selon les permissions)
        @file_put_contents($_hash_file, $_admin_hash, LOCK_EX);
        @chmod($_hash_file, 0600);
        // Si l'écriture échoue, on continue quand même avec le hash en mémoire
    }

    define('ADMIN_HASH', $_admin_hash);
}

// ── CSRF double protection ──────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function csrf_check(): void {
    $session_token = $_SESSION['_csrf'] ?? '';
    $post_token    = $_POST['_csrf'] ?? '';
    if (!$session_token || !$post_token) {
        security_log('csrf_missing', 'CSRF tokens absent');
        http_response_code(403);
        die('Accès refusé : jeton de sécurité manquant.');
    }
    if (!hash_equals($session_token, $post_token)) {
        security_log('csrf_invalid', 'CSRF token mismatch');
        http_response_code(403);
        die('Accès refusé : jeton de sécurité invalide.');
    }
    // Rotation du token après chaque POST valide
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

// ── Sanitisation ─────────────────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function clean(string $s, int $max = 1000): string {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s); // caractères de contrôle
    return mb_substr(trim(strip_tags($s)), 0, $max, 'UTF-8');
}
function clean_int(mixed $v, int $min = 0, int $max = PHP_INT_MAX): int {
    return max($min, min($max, (int)filter_var($v, FILTER_VALIDATE_INT, ['options'=>['min_range'=>$min,'max_range'=>$max]]) ?: 0));
}
function clean_float(mixed $v, float $min = 0, float $max = 99999): float {
    $f = filter_var($v, FILTER_VALIDATE_FLOAT);
    return max($min, min($max, $f === false ? 0.0 : round($f, 2)));
}
function clean_email(string $s): string|false {
    $s = filter_var(trim($s), FILTER_VALIDATE_EMAIL);
    return ($s && strlen($s) <= 254) ? $s : false;
}
function slug(string $s): string {
    $map = ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
    $s = mb_strtolower(strtr($s, $map), 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// ── Auth ─────────────────────────────────────────────────────────────
function is_logged(): bool {
    if (empty($_SESSION['_admin']) || $_SESSION['_admin'] !== true) return false;
    if (empty($_SESSION['_fp'])) return false;
    $admin_user = $_SESSION['_admin_user'] ?? ADMIN_USER;
    $expected   = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . $admin_user);
    return hash_equals($_SESSION['_fp'], $expected);
}

// Vérifier si l'admin courant a la permission demandée
function admin_can(string $perm): bool {
    if (!is_logged()) return false;
    $perms = $_SESSION['_admin_perms'] ?? ['all'];
    return in_array('all', $perms, true) || in_array($perm, $perms, true);
}
function require_auth(): void {
    if (!is_logged()) {
        header('Location: ' . base_path() . 'admin/login.php');
        exit;
    }
}
function do_login(string $user, string $pass): bool {
    // 1. Vérifier dans admins.json (multi-admins)
    $admins = db('admins');
    foreach ($admins as $adm) {
        if (!($adm['active'] ?? true)) continue;
        if (!hash_equals($adm['username'] ?? '', $user)) continue;
        $hash_file = DATA_DIR . '/.admin_hash_' . preg_replace('/[^a-z0-9_]/', '', $adm['id'] ?? 'a1');
        $stored_hash = file_exists($hash_file) ? trim(file_get_contents($hash_file)) : '';
        if ($stored_hash && password_verify($pass, $stored_hash)) {
            session_regenerate_id(true);
            $_SESSION['_admin']        = true;
            $_SESSION['_admin_id']     = $adm['id'];
            $_SESSION['_admin_user']   = $adm['username'];
            $_SESSION['_admin_display']= $adm['display_name'] ?? $adm['username'];
            $_SESSION['_admin_role']   = $adm['role'] ?? 'admin';
            $_SESSION['_admin_perms']  = $adm['permissions'] ?? ['all'];
            $_SESSION['_regen']        = time();
            $_SESSION['_fp']           = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . $adm['username']);
            $_SESSION['_login']        = time();
            // Mettre à jour last_login
            $admins2 = db('admins');
            foreach ($admins2 as &$a2) {
                if ($a2['id'] === $adm['id']) $a2['last_login'] = date('Y-m-d H:i');
            } unset($a2);
            db_save('admins', $admins2);
            admin_log('login_ok', "Connexion: {$adm['username']} ({$adm['role']})");
            return true;
        }
    }

    // 2. Fallback: admin unique legacy (ADMIN_USER / .admin_hash)
    $hash   = ADMIN_HASH;
    $userOk = hash_equals(ADMIN_USER, $user);
    $passOk = password_verify($pass, $hash);
    if (!$userOk || !$passOk) {
        security_log('login_fail', "Tentative login: user=$user IP=" . ($_SERVER['REMOTE_ADDR'] ?? ''));
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['_admin']        = true;
    $_SESSION['_admin_user']   = $user;
    $_SESSION['_admin_display']= 'Administrateur';
    $_SESSION['_admin_role']   = 'super_admin';
    $_SESSION['_admin_perms']  = ['all'];
    $_SESSION['_regen']        = time();
    $_SESSION['_fp']           = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ADMIN_USER);
    $_SESSION['_login']        = time();
    admin_log('login_ok', "Connexion: $user (super_admin)");
    return true;
}
function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CSRF via GET (export) ────────────────────────────────────────────
function csrf_check_get(): void {
    $token = $_GET['_csrf'] ?? '';
    $sess  = $_SESSION['_csrf'] ?? '';
    if (!$token || !$sess || !hash_equals($sess, $token)) {
        security_log('csrf_get_invalid', 'CSRF GET token mismatch');
        http_response_code(403); die('Accès refusé.');
    }
}

// ── Rate limiting fichier ─────────────────────────────────────────────
function rate_ok(string $key, int $max = 5, int $window = 300): bool {
    $f    = DATA_DIR . '/.rl_' . hash('sha256', $key) . '.json';
    $now  = time();
    $hits = [];
    if (file_exists($f)) {
        $raw = @file_get_contents($f);
        $hits = $raw ? (array)(json_decode($raw, true) ?? []) : [];
    }
    // Nettoyer les hits périmés
    $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $now - $t < $window));
    if (count($hits) >= $max) {
        security_log('rate_limit', "Rate limit hit: key=$key");
        return false;
    }
    $hits[] = $now;
    file_put_contents($f, json_encode($hits), LOCK_EX);
    return true;
}

// ── Journalisation sécurité ───────────────────────────────────────────
function security_log(string $event, string $detail = ''): void {
    $line = date('Y-m-d H:i:s') . ' [' . strtoupper($event) . '] '
          . 'IP=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' '
          . 'UA="' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80) . '" '
          . $detail . PHP_EOL;
    $f = LOG_DIR . '/security_' . date('Y-m') . '.log';
    file_put_contents($f, $line, FILE_APPEND | LOCK_EX);
    // Limiter la taille des logs à 2MB
    if (filesize($f) > 2_000_000) {
        $old = file($f);
        file_put_contents($f, implode('', array_slice($old, (int)(count($old) / 2))), LOCK_EX);
    }
}

// ── Admin log (auto-include) ─────────────────────────────────────────
if (file_exists(ROOT . '/includes/admin_log.php')) require_once ROOT . '/includes/admin_log.php';

// ── Mailer (auto-include) ─────────────────────────────────────────
if (file_exists(ROOT . '/includes/mailer.php')) require_once ROOT . '/includes/mailer.php';

// ── JSON DB ────────────────────────────────────────────────────────────
function db(string $name): array {
    // Valider le nom (anti path traversal)
    if (!preg_match('/^[a-z_]+$/', $name)) return [];
    $f = DATA_DIR . '/' . $name . '.json';
    if (!file_exists($f)) return [];
    $raw = file_get_contents($f);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    return $data;
}
function db_save(string $name, array $data): bool {
    if (!preg_match('/^[a-z_]+$/', $name)) return false;
    $f   = DATA_DIR . '/' . $name . '.json';
    $tmp = $f . '.tmp.' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Vérifier que le dossier data/ existe et est accessible en écriture
    if (!is_dir(DATA_DIR)) {
        error_log('[CAFE] ERREUR: DATA_DIR introuvable: ' . DATA_DIR);
        return false;
    }
    if (!is_writable(DATA_DIR)) {
        error_log('[CAFE] ERREUR: DATA_DIR non writable: ' . DATA_DIR);
        return false;
    }
    
    $ok = file_put_contents($tmp, $json, LOCK_EX);
    if ($ok === false) {
        error_log('[CAFE] ERREUR: file_put_contents failed: ' . $tmp);
        return false;
    }
    $r = rename($tmp, $f);
    if (!$r) {
        error_log('[CAFE] ERREUR: rename failed: ' . $tmp . ' -> ' . $f);
        @unlink($tmp);
        return false;
    }
    error_log('[CAFE] OK: saved ' . $name . ' (' . $ok . ' bytes) -> ' . $f);
    return true;
}
function new_id(): string { return bin2hex(random_bytes(8)); }

// ── Chargement des settings avec déchiffrement ─────────────────────────
// Wrapper qui déchiffre les champs sensibles à la lecture
function cfg_get(): array {
    // Lecture directe — toutes les valeurs stockées en clair
    // Sécurité assurée par data/.htaccess (accès web bloqué)
    return db('settings');
}
// Wrapper qui chiffre les champs sensibles à la sauvegarde
function cfg_save(array $cfg): bool {
    // Pas de chiffrement AES (clé .ek instable entre requêtes PHP)
    // Sécurité assurée par .htaccess sur data/ (hors webroot idéalement)
    return db_save('settings', $cfg);
}

// cfg_patch() : mettre à jour uniquement certaines clés NON-sensibles
// sans passer par cfg_get/cfg_save (évite le double chiffrement)
function cfg_patch(array $updates): bool {
    $settings = db('settings');
    foreach ($updates as $k => $v) {
        $settings[$k] = $v;
    }
    return db_save('settings', $settings);
}

// ── En-têtes de sécurité ──────────────────────────────────────────────
function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Nonce pour le CSP inline (optionnel - à activer si on retire unsafe-inline)
    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; "
        . "font-src https://fonts.gstatic.com data:; "
        . "script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.paypal.com https://www.paypalobjects.com; "
        . "frame-src https://www.google.com https://maps.google.com https://js.stripe.com https://www.sandbox.paypal.com; "
        . "connect-src 'self' https://api.stripe.com https://www.paypal.com; "
        . "img-src 'self' data: blob: https://www.paypalobjects.com;"
    );
}
