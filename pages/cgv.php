<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();
$pt  = $cfg['page_cgv_title'] ?? 'CGV';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><a href="<?= $B ?>" style="color:inherit">Accueil</a> · CGV</p>
    <h1><?= $pt ? e($pt) : '<em>CGV</em>' ?></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:800px">
    <?php $custom = $cfg['page_cgv_content'] ?? ''; ?>
    <?php if ($custom): ?>
      <div class="art-content"><?= $custom ?></div>
    <?php else: ?>
      <div class="art-content">
        <h2>Prix</h2>
        <p>Tous les prix sont indiqués en euros TTC.</p>
        <h2>Commandes</h2>
        <p>Toute commande passée sur le site vaut acceptation des présentes CGV.</p>
        <h2>Paiement</h2>
        <p>Le paiement est sécurisé via Stripe et/ou PayPal. Aucune donnée bancaire n'est stockée sur nos serveurs.</p>
        <h2>Livraison</h2>
        <p>Délai d'expédition : 48h ouvrées. Livraison offerte à partir de <?= e($cfg['shipping_free_from']??'35') ?> €.</p>
        <h2>Rétractation</h2>
        <p>Conformément à la législation, vous disposez d'un délai de 14 jours pour exercer votre droit de rétractation sur les produits non ouverts.</p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
