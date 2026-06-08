<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();
$pt  = $cfg['page_legal_title'] ?? 'Mentions légales';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><a href="<?= $B ?>" style="color:inherit">Accueil</a> · Mentions légales</p>
    <h1><?= $pt ? e($pt) : '<em>Mentions légales</em>' ?></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:800px">
    <?php $custom = $cfg['page_legal_content'] ?? ''; ?>
    <?php if ($custom): ?>
      <div class="art-content"><?= $custom ?></div>
    <?php else: ?>
      <div class="art-content">
        <h2>Éditeur du site</h2>
        <p><?= e($cfg['site_name']??'Café Maison') ?> — <?= e($cfg['address']??'') ?><br>
        Email : <?= e($cfg['email']??'') ?> &middot; Tél : <?= e($cfg['phone']??'') ?></p>
        <h2>Hébergement</h2>
        <p>À compléter avec les informations de votre hébergeur.</p>
        <h2>Données personnelles</h2>
        <p>Les données collectées (commandes, réservations, newsletter) sont utilisées uniquement
        par <?= e($cfg['site_name']??'') ?> et ne sont jamais cédées à des tiers.
        Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
