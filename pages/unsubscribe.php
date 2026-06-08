<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$token = clean($_GET['token'] ?? '', 100);
$email = clean($_GET['email'] ?? '', 150);
$done  = false;
$err   = false;

if ($token && $email) {
    // Vérifier le token (hash simple email + secret)
    $cfg_u    = cfg_get();
    $expected = hash('sha256', $email . 'cafe-unsub-' . ($cfg_u['site_name'] ?? 'cafemaison'));
    if (hash_equals($expected, $token)) {
        $subs = db('newsletter');
        $found = false;
        foreach ($subs as &$s) {
            if (strtolower($s['email'] ?? '') === strtolower($email)) {
                $s['active'] = false;
                $found = true;
            }
        } unset($s);
        if ($found) {
            db_save('newsletter', $subs);
            $done = true;
        } else {
            $err = true;
        }
    } else {
        $err = true;
    }
} else {
    $err = true;
}

$cfg = cfg_get();
$B   = base_path();
$sn  = $cfg['site_name'] ?? 'Café Maison';
$pt  = 'Désinscription newsletter';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="sec" style="min-height:60vh;display:flex;align-items:center">
  <div class="wrap" style="max-width:500px;text-align:center">
    <?php if ($done): ?>
      <div style="font-size:3rem;margin-bottom:1rem">✓</div>
      <h2 style="font-family:var(--f1)">Désinscription effectuée</h2>
      <p style="color:var(--gy)">L'adresse <strong><?= e($email) ?></strong> a été retirée de notre newsletter.</p>
      <a href="<?= $B ?>" class="btn btn-or" style="margin-top:2rem">← Retour au site</a>
    <?php else: ?>
      <div style="font-size:3rem;margin-bottom:1rem">✗</div>
      <h2 style="font-family:var(--f1)">Lien invalide</h2>
      <p style="color:var(--gy)">Ce lien de désinscription est invalide ou a déjà été utilisé.</p>
      <a href="<?= $B ?>" class="btn btn-or" style="margin-top:2rem">← Retour au site</a>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
