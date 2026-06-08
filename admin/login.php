<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/captcha.php';
define('SKIP_MAINTENANCE', 1);

// Déjà connecté → dashboard
if (is_logged()) { header('Location: ./'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $csrf_ok = isset($_POST['_csrf'], $_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
    if (!$csrf_ok) {
        $err = 'Session expirée. Rechargez la page.';
    } else {
        // reCAPTCHA optionnel
        $cfg_login = cfg_get();
        if (captcha_enabled($cfg_login)) {
            $token = $_POST['g-recaptcha-response'] ?? '';
            $cap_result = captcha_verify($cfg_login, $token);
            if (!$cap_result['ok']) {
                if (!$token) {
                    $err = '⚠ reCAPTCHA non chargé. Vérifiez que votre domaine est autorisé dans la console Google reCAPTCHA, ou désactivez le captcha dans les paramètres.';
                } else {
                    $err = $cap_result['error'];
                }
            }
        }

        // Rate limiting + vérification mot de passe
        if (!$err) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!rate_ok('adm_login_' . $ip, 10, 600)) {
                $err = 'Trop de tentatives. Réessayez dans 10 minutes.';
                security_log('login_blocked', "IP=$ip blocked");
            } else {
                $user = clean($_POST['username'] ?? '', 50);
                $pass = $_POST['password'] ?? '';
                if (do_login($user, $pass)) {
                    security_log('login_ok', "Admin login: user=$user");
                    header('Location: ./');
                    exit;
                } else {
                    usleep(random_int(200000, 600000));
                    $err = 'Identifiants incorrects.';
                }
            }
        }
    } // fin else csrf_ok
} // fin POST
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Café Maison</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<?php $cfg_cap2 = cfg_get(); ?>
<?= captcha_enabled($cfg_cap2) ? captcha_script_tag($cfg_cap2) : '' ?>
<meta name="robots" content="noindex,nofollow">
</head>
<body class="adm-login">
<div class="login-card">
  <div class="login-logo">
    <h1>Café <span>Maison</span></h1>
    <p>Administration</p>
  </div>
  <?php if ($err): ?>
  <div class="login-err">✗ <?= e($err) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off" id="loginForm">
    <?= csrf_field() ?>
    <div class="frm-g">
      <label class="frm-l">Identifiant</label>
      <input class="frm-i" type="text" name="username" required
        autocomplete="username" maxlength="50" spellcheck="false"
        value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="frm-g">
      <label class="frm-l">Mot de passe</label>
      <div style="position:relative">
        <input class="frm-i" type="password" name="password" id="passInput"
          required autocomplete="current-password" maxlength="200"
          style="padding-right:2.8rem">
        <button type="button" onclick="
          var i=document.getElementById('passInput');
          i.type=i.type==='password'?'text':'password';
          this.textContent=i.type==='password'?'👁':'🙈'
        " style="position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;padding:.2rem;line-height:1">👁</button>
      </div>
    </div>
    <?php if (captcha_enabled($cfg_cap2)): ?>
    <?= captcha_hidden_field() ?>
    <div id="captcha-badge" style="font-size:.7rem;color:var(--gy2);margin-bottom:.5rem;text-align:center">
      🔒 Protégé par reCAPTCHA v3
      <a href="https://policies.google.com/privacy" style="color:var(--gy2)" target="_blank">Confidentialité</a> ·
      <a href="https://policies.google.com/terms" style="color:var(--gy2)" target="_blank">CGU</a>
    </div>
    <?php endif; ?>
    <button type="submit" class="login-btn" id="loginSubmit">Se connecter →</button>
  </form>
  <?php if (captcha_enabled($cfg_cap2)): ?>
  <?= captcha_js_handler($cfg_cap2['captcha_site_key'], 'loginForm', 'admin_login') ?>
  <?php endif; ?>
  <p class="login-hint">
    Identifiants par défaut : <code>admin</code> / <code>admin123</code>
  </p>
</div>
</body>
</html>
