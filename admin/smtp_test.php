<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$cfg = cfg_get();
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $to = filter_var($_POST['to'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$to) {
        $error = "Adresse email invalide.";
    } elseif (empty($cfg['smtp_host'])) {
        $error = "SMTP non configuré : remplissez d'abord les champs SMTP dans les Paramètres.";
    } else {
        $ok = cm_mail(
            $to, 'Test',
            'Test SMTP — Café Maison',
            '<h2 style="font-family:serif">Test réussi !</h2><p>Si vous lisez cet email, votre configuration SMTP fonctionne correctement.</p>'
        );
        $result = $ok ? "✅ Email envoyé à <strong>$to</strong> avec succès !" : "❌ Échec d'envoi. Vérifiez host/user/pass et consultez les logs PHP (error_log).";
    }
}

$at = 'Test SMTP'; $cur_adm = 'settings';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>📧 Test d'envoi SMTP</h2><p>Vérifiez que votre configuration email fonctionne</p></div>
  <div class="adm-acts"><a href="./settings.php" class="a-btn">← Paramètres</a></div>
</div>
<div class="adm-content">

  <!-- État actuel -->
  <div class="editor-wrap" style="margin-bottom:1.2rem">
    <h4 style="font-family:var(--f1);margin-bottom:.8rem">Configuration actuelle</h4>
    <table style="font-size:.85rem;width:100%;border-collapse:collapse">
      <?php foreach([
        'Serveur SMTP' => $cfg['smtp_host'] ?? '',
        'Port'         => $cfg['smtp_port'] ?? 587,
        'Utilisateur'  => $cfg['smtp_user'] ?? '',
        'Email from'   => $cfg['smtp_from'] ?? '',
        'Mot de passe' => ($cfg['smtp_pass'] ?? '') ? '••••••••' : '',
      ] as $label => $val): ?>
      <tr style="border-bottom:1px solid var(--cr)">
        <td style="padding:.4rem .6rem;color:var(--gy2);width:140px"><?= $label ?></td>
        <td style="padding:.4rem .6rem">
          <?php if ($val): ?>
            <code style="background:rgba(42,76,30,.08);padding:.1rem .4rem;border-radius:3px;color:var(--vr2)"><?= e((string)$val) ?></code>
          <?php else: ?>
            <span style="color:var(--or)">⚠ Non configuré</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!($cfg['smtp_host'] ?? '') || !($cfg['smtp_user'] ?? '') || !($cfg['smtp_pass'] ?? '')): ?>
    <div style="margin-top:.8rem;padding:.6rem .9rem;background:rgba(200,86,30,.06);border-radius:var(--r);font-size:.8rem">
      ⚠️ Configuration incomplète. <a href="./settings.php" style="color:var(--or)">Compléter les paramètres →</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Formulaire test -->
  <div class="editor-wrap" style="max-width:480px">
    <h4 style="font-family:var(--f1);margin-bottom:.8rem">Envoyer un email de test</h4>

    <?php if ($result): ?>
    <div style="padding:.8rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:.85rem;
      background:<?= str_starts_with($result,'✅') ? 'rgba(42,76,30,.08)' : 'rgba(200,86,30,.08)' ?>;
      border:1px solid <?= str_starts_with($result,'✅') ? 'var(--vr3)' : 'var(--or)' ?>">
      <?= $result ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div style="padding:.8rem 1rem;border-radius:var(--r);margin-bottom:1rem;font-size:.85rem;background:rgba(200,86,30,.08);border:1px solid var(--or)">
      ❌ <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="frm-g">
        <label class="frm-l">Envoyer le test à cette adresse</label>
        <input class="frm-i" type="email" name="to" required
          value="<?= e($cfg['email'] ?? '') ?>"
          placeholder="votre@email.fr">
      </div>
      <button type="submit" class="save-btn" style="margin-top:.5rem">📤 Envoyer le test</button>
    </form>
  </div>

  <!-- Guide SMTP -->
  <div class="editor-wrap" style="margin-top:1.2rem">
    <h4 style="font-family:var(--f1);margin-bottom:.8rem">📖 Guide de configuration</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:.83rem">
      <div style="padding:.8rem 1rem;background:var(--cr);border-radius:var(--r)">
        <strong>Gmail</strong><br>
        Serveur : <code>smtp.gmail.com</code><br>
        Port : <code>587</code><br>
        User : <code>votre@gmail.com</code><br>
        Pass : <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--or)">Mot de passe d'application</a>
        <div style="margin-top:.4rem;font-size:.75rem;color:var(--gy2)">⚠ Activez d'abord la validation 2 étapes Google</div>
      </div>
      <div style="padding:.8rem 1rem;background:var(--cr);border-radius:var(--r)">
        <strong>OVH / Infomaniak / Ionos</strong><br>
        Serveur : celui de votre hébergeur<br>
        Port : <code>587</code> ou <code>465</code><br>
        User : adresse email complète<br>
        Pass : mot de passe email
      </div>
      <div style="padding:.8rem 1rem;background:var(--cr);border-radius:var(--r)">
        <strong>Mailtrap (tests)</strong><br>
        Serveur : <code>sandbox.smtp.mailtrap.io</code><br>
        Port : <code>2525</code><br>
        User + Pass : dans votre compte <a href="https://mailtrap.io" target="_blank" style="color:var(--or)">Mailtrap</a>
        <div style="margin-top:.4rem;font-size:.75rem;color:var(--gy2)">✓ Idéal pour tester en local</div>
      </div>
      <div style="padding:.8rem 1rem;background:var(--cr);border-radius:var(--r)">
        <strong>Sendinblue / Brevo</strong><br>
        Serveur : <code>smtp-relay.brevo.com</code><br>
        Port : <code>587</code><br>
        User : votre email Brevo<br>
        Pass : clé API SMTP
      </div>
    </div>
  </div>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
