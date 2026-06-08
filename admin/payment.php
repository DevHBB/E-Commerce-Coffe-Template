<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg = cfg_get();
$ok  = false;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Lire directement settings.json pour éviter tout bug de cfg_get/cfg_save
    $raw = file_get_contents(DATA_DIR . '/settings.json');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $err = 'Impossible de lire settings.json';
    } else {
        // Mettre à jour uniquement les champs de paiement
        $fields = [
            'stripe_enabled', 'stripe_pk', 'stripe_sk', 'stripe_webhook_secret',
            'paypal_enabled', 'paypal_client_id', 'paypal_client_secret', 'paypal_mode',
            'manual_payment_enabled', 'manual_payment_label', 'manual_payment_instructions',
        ];

        foreach ($fields as $f) {
            if (!isset($_POST[$f])) continue;
            $data[$f] = trim($_POST[$f]);
        }

        // Checkboxes (absentes du POST si décochées)
        $data['stripe_enabled']          = isset($_POST['stripe_enabled']);
        $data['paypal_enabled']           = isset($_POST['paypal_enabled']);
        $data['manual_payment_enabled']   = isset($_POST['manual_payment_enabled']);

        // Ne pas effacer les clés si champ vide (garder l'ancienne valeur)
        foreach (['stripe_pk','stripe_sk','stripe_webhook_secret','paypal_client_id','paypal_client_secret'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if ($v === '' || strpos($v, '•') !== false) {
                // Garder la valeur existante
            } else {
                $data[$k] = $v;
            }
        }

        // Écriture directe dans settings.json
        $json  = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bytes = file_put_contents(DATA_DIR . '/settings.json', $json, LOCK_EX);

        if ($bytes === false) {
            $err = 'ERREUR: Impossible d\'écrire settings.json. Vérifiez les permissions du dossier data/.';
        } else {
            $ok  = true;
            $cfg = json_decode(file_get_contents(DATA_DIR . '/settings.json'), true);
            admin_log('payment_settings', 'Paramètres paiement mis à jour');
        }
    }
}

// Toujours relire depuis le fichier directement
$raw  = file_get_contents(DATA_DIR . '/settings.json');
$data = json_decode($raw, true) ?: [];

$at = 'Paiement'; $cur_adm = 'payment';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div>
    <h2>💳 Paramètres de paiement</h2>
    <p>Configurez vos moyens de paiement</p>
  </div>
  <div class="adm-acts">
    <a href="./settings.php" class="a-btn">← Paramètres généraux</a>
  </div>
</div>
<div class="adm-content">

  <?php if ($ok): ?>
  <div class="alert-ok" style="margin-bottom:1.2rem">✓ Paramètres de paiement enregistrés !</div>
  <?php endif; ?>
  <?php if ($err): ?>
  <div class="alert-err" style="margin-bottom:1.2rem">✗ <?= e($err) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>

    <!-- STRIPE -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem">
        <label class="tog">
          <input type="checkbox" name="stripe_enabled" <?= !empty($data['stripe_enabled']) ? 'checked' : '' ?>>
          <span class="tog-sl"></span>
        </label>
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin:0">💳 Stripe</h4>
        <small style="color:var(--gy2)">(Carte bancaire, Apple Pay, Google Pay)</small>
      </div>
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Clé publique <code style="font-size:.7rem">pk_live_...</code></label>
          <input class="frm-i" name="stripe_pk" value="<?= e($data['stripe_pk'] ?? '') ?>" placeholder="pk_live_..." autocomplete="off">
        </div>
        <div class="frm-g">
          <label class="frm-l">Clé secrète <code style="font-size:.7rem">sk_live_...</code></label>
          <input class="frm-i" type="password" name="stripe_sk" value="<?= e($data['stripe_sk'] ?? '') ?>" placeholder="sk_live_..." autocomplete="new-password">
        </div>
      </div>
      <div class="frm-g">
        <label class="frm-l">Webhook Secret <code style="font-size:.7rem">whsec_...</code></label>
        <input class="frm-i" name="stripe_webhook_secret" value="<?= e($data['stripe_webhook_secret'] ?? '') ?>" placeholder="whsec_..." autocomplete="off">
        <small style="color:var(--gy2);display:block;margin-top:.3rem">
          URL Webhook : <code><?= rtrim((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].str_replace('/admin/payment.php','',($_SERVER['SCRIPT_NAME']??'')), '/admin/payment.php') ?>/api/stripe_webhook.php</code>
        </small>
      </div>
      <div style="padding:.7rem 1rem;background:var(--cr);border-radius:var(--r);font-size:.78rem;color:var(--gy);margin-top:.5rem">
        📋 <strong>Pour démarrer :</strong>
        <a href="https://stripe.com" target="_blank" style="color:var(--or)">Créer un compte Stripe</a> →
        Dashboard → Developers → API keys → copiez les 2 clés ci-dessus.<br>
        Apple Pay / Google Pay : activez votre domaine dans
        <a href="https://dashboard.stripe.com/settings/payment_method_domains" target="_blank" style="color:var(--or)">Stripe → Payment method domains</a>
      </div>
    </div>

    <!-- PAYPAL -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem">
        <label class="tog">
          <input type="checkbox" name="paypal_enabled" <?= !empty($data['paypal_enabled']) ? 'checked' : '' ?>>
          <span class="tog-sl"></span>
        </label>
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin:0">🅿 PayPal</h4>
      </div>
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Client ID</label>
          <input class="frm-i" name="paypal_client_id" value="<?= e($data['paypal_client_id'] ?? '') ?>" placeholder="AXxx..." autocomplete="off">
        </div>
        <div class="frm-g">
          <label class="frm-l">Client Secret</label>
          <input class="frm-i" type="password" name="paypal_client_secret" value="<?= e($data['paypal_client_secret'] ?? '') ?>" placeholder="EXxx..." autocomplete="new-password">
        </div>
      </div>
      <div class="frm-g">
        <label class="frm-l">Mode</label>
        <select name="paypal_mode" class="frm-sel" style="max-width:180px">
          <option value="sandbox" <?= ($data['paypal_mode']??'sandbox')==='sandbox'?'selected':'' ?>>Sandbox (tests)</option>
          <option value="live"    <?= ($data['paypal_mode']??'')==='live'?'selected':'' ?>>Live (production)</option>
        </select>
      </div>
    </div>

    <!-- PAIEMENT MANUEL -->
    <div class="editor-wrap" style="margin-bottom:1.2rem">
      <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:1rem">
        <label class="tog">
          <input type="checkbox" name="manual_payment_enabled" <?= !empty($data['manual_payment_enabled']) ? 'checked' : '' ?>>
          <span class="tog-sl"></span>
        </label>
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin:0">🏦 Paiement manuel</h4>
        <small style="color:var(--gy2)">(virement, chèque, à la livraison…)</small>
      </div>
      <div class="frm-g">
        <label class="frm-l">Libellé affiché au client</label>
        <input class="frm-i" name="manual_payment_label" value="<?= e($data['manual_payment_label'] ?? 'Virement bancaire ou paiement à la livraison') ?>">
      </div>
      <div class="frm-g">
        <label class="frm-l">Instructions pour le client</label>
        <textarea class="frm-ta" name="manual_payment_instructions" rows="3"><?= e($data['manual_payment_instructions'] ?? '') ?></textarea>
      </div>
    </div>

    <button type="submit" class="save-btn" style="width:100%;justify-content:center;padding:.9rem;font-size:1rem">
      💾 Enregistrer les paramètres de paiement
    </button>
  </form>

  <!-- Debug info -->
  <div style="margin-top:2rem;padding:1rem;background:var(--cr);border-radius:var(--r);font-size:.72rem;color:var(--gy2)">
    <strong>État actuel (lu depuis settings.json) :</strong><br>
    stripe_pk : <code><?= $data['stripe_pk'] ? substr($data['stripe_pk'],0,12).'...' : '(vide)' ?></code> ·
    stripe_enabled : <code><?= var_export($data['stripe_enabled']??false, true) ?></code> ·
    paypal_id : <code><?= $data['paypal_client_id'] ? substr($data['paypal_client_id'],0,10).'...' : '(vide)' ?></code> ·
    manual : <code><?= var_export($data['manual_payment_enabled']??false, true) ?></code><br>
    DATA_DIR writable : <code><?= is_writable(DATA_DIR) ? 'OUI ✓' : 'NON ✗' ?></code> ·
    settings.json writable : <code><?= is_writable(DATA_DIR.'/settings.json') ? 'OUI ✓' : 'NON ✗' ?></code>
  </div>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
