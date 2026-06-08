<?php
/**
 * includes/captcha.php — reCAPTCHA v3 (invisible)
 * v3 = score 0-1, pas de widget visible, intégration transparente
 */
if (!function_exists('captcha_enabled')) {

function captcha_enabled(array $cfg): bool {
    return !empty($cfg['captcha_enabled']) && !empty($cfg['captcha_site_key']) && !empty($cfg['captcha_secret_key']);
}

function captcha_reviews_enabled(array $cfg): bool {
    return !empty($cfg['captcha_reviews']) && !empty($cfg['captcha_site_key']) && !empty($cfg['captcha_secret_key']);
}

/** Script à mettre dans <head> ou avant </body> — TOUJOURS avec ?render=KEY pour v3 */
function captcha_script_tag(array $cfg): string {
    $key = htmlspecialchars($cfg['captcha_site_key'] ?? '', ENT_QUOTES);
    if (!$key) return '';
    return '<script src="https://www.google.com/recaptcha/api.js?render=' . $key . '" async defer></script>' . "\n";
}

/** Input hidden pour recevoir le token généré par le JS */
function captcha_hidden_field(): string {
    return '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">' . "\n";
}

/**
 * JS inline à appeler au submit — remplit le hidden field avec le token v3
 * @param string $site_key   Clé publique
 * @param string $form_id    ID du formulaire
 * @param string $action     Nom de l'action (login, review, booking, contact)
 */
function captcha_js_handler(string $site_key, string $form_id, string $action): string {
    $key = json_encode($site_key);
    $fid = json_encode($form_id);
    $act = json_encode($action);
    return <<<JS
<script>
(function() {
  var form = document.getElementById({$fid});
  if (!form) return;
  form.addEventListener('submit', function(e) {
    var key = {$key};
    if (!key || typeof grecaptcha === 'undefined') return true;
    e.preventDefault();
    var btn = form.querySelector('[type=submit]');
    if (btn) { btn.disabled = true; btn.setAttribute('data-orig', btn.textContent); btn.textContent = '…'; }
    var done = false;
    var fallback = setTimeout(function() {
      if (done) return;
      done = true;
      form.submit();
    }, 4000);
    grecaptcha.ready(function() {
      grecaptcha.execute(key, {action: {$act}}).then(function(token) {
        if (done) return;
        done = true;
        clearTimeout(fallback);
        document.getElementById('g-recaptcha-response').value = token;
        form.submit();
      }).catch(function() {
        if (done) return;
        done = true;
        clearTimeout(fallback);
        form.submit();
      });
    });
  });
})();
</script>
JS;
}

/** Vérifie le token côté serveur. Retourne ['ok'=>bool, 'error'=>string] */
function captcha_verify(array $cfg, string $token): array {
    $secret = trim($cfg['captcha_secret_key'] ?? '');
    if (!$secret) {
        return ['ok' => true, 'error' => '']; // Pas de clé → pas de vérification
    }
    if (!$token) {
        // Token vide = domaine non autorisé dans Google ou script non chargé
        // On laisse passer: le rate limiting protège contre les abus
        return ['ok' => true, 'error' => ''];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => true, 'error' => '']; // cURL absent → laisser passer
    }
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) return ['ok' => true, 'error' => '']; // Réseau KO → laisser passer
    $res = json_decode($raw, true) ?: [];
    if (!($res['success'] ?? false)) {
        $codes = $res['error-codes'] ?? [];
        if (in_array('invalid-input-secret', $codes)) {
            error_log('[CAFE] reCAPTCHA: clé secrète invalide');
            return ['ok' => true, 'error' => '']; // Config KO → laisser passer
        }
        return ['ok' => false, 'error' => 'Vérification anti-bot échouée. Réessayez.'];
    }
    $score = (float)($res['score'] ?? 1.0);
    if ($score < 0.3) {
        return ['ok' => false, 'error' => 'Activité suspecte détectée. Réessayez.'];
    }
    return ['ok' => true, 'error' => ''];
}

} // end if !function_exists
