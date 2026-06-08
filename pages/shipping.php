<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();
$pt  = $cfg['page_shipping_title'] ?? 'Livraison';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><a href="<?= $B ?>" style="color:inherit">Accueil</a> · Livraison & Retours</p>
    <h1><?= $pt ? e($pt) : '<em>Livraison</em>' ?></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:800px">
    <?php $custom = $cfg['page_shipping_content'] ?? ''; ?>
    <?php if ($custom): ?>
      <div class="art-content"><?= $custom ?></div>
    <?php else: ?>
      <div class="art-content">
        <h2>Délais de livraison</h2>
        <p>Torréfaction à la commande — expédition sous 48h ouvrées.</p>
        <h2>Frais de livraison</h2>
        <p>Livraison offerte à partir de <?= e($cfg['shipping_free_from']??'35') ?> €.<br>
        Sinon : <?= e($cfg['shipping_cost']??'4,90') ?> € en France métropolitaine.</p>
        <h2>Retrait en boutique</h2>
        <p><?= e($cfg['pickup_address']??$cfg['address']??'') ?><br>
        <?= e($cfg['pickup_hours']??'') ?></p>
        <h2>Retours</h2>
        <p>En cas de problème, contactez-nous à <?= e($cfg['email']??'') ?> dans les 14 jours.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
