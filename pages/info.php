<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg  = cfg_get();
$B    = base_path();
$pkey = 'page_info';
$pt   = $cfg[$pkey.'_title'] ?? 'Informations';
$cur  = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><a href="<?= $B ?>" style="color:inherit">Accueil</a> · Informations</p>
    <h1><?= $pt ? e($pt) : 'Informations<br><em>pratiques</em>' ?></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:800px">
    <?php $content = $cfg[$pkey.'_content'] ?? ''; ?>
    <?php if ($content): ?>
      <div class="art-content"><?= $content ?></div>
    <?php else: ?>
      <div class="art-content">
        <?php if ('info' === 'legal'): ?>
        <h2>Éditeur du site</h2>
        <p><?= e($cfg['site_name']??'Café Maison') ?> — <?= e($cfg['address']??'') ?><br>
        Email : <?= e($cfg['email']??'') ?> · Tél : <?= e($cfg['phone']??'') ?></p>
        <h2>Hébergement</h2><p>À compléter avec les informations de votre hébergeur.</p>
        <h2>Données personnelles</h2>
        <p>Les données collectées (commandes, réservations, newsletter) sont utilisées uniquement par <?= e($cfg['site_name']??'') ?> et ne sont jamais cédées à des tiers. Conformément au RGPD, vous disposez d'un droit d'accès, de rectification et de suppression.</p>
        <?php elseif ('info' === 'cgv'): ?>
        <h2>Prix</h2><p>Tous les prix sont indiqués en euros TTC. <?= e($cfg['site_name']??'') ?> se réserve le droit de modifier ses prix à tout moment.</p>
        <h2>Commandes</h2><p>Toute commande passée sur le site vaut acceptation des présentes CGV.</p>
        <h2>Paiement</h2><p>Le paiement est sécurisé via Stripe et/ou PayPal. Aucune donnée bancaire n'est stockée sur nos serveurs.</p>
        <h2>Livraison</h2><p>Délai d'expédition : 48h ouvrées. Livraison offerte à partir de <?= e($cfg['shipping_free_from']??'35') ?> €.</p>
        <h2>Rétractation</h2><p>Conformément à la législation, vous disposez d'un délai de 14 jours pour exercer votre droit de rétractation sur les produits non ouverts.</p>
        <?php elseif ('info' === 'shipping'): ?>
        <h2>Délais de livraison</h2><p>Torréfaction à la commande — expédition sous 48h ouvrées.</p>
        <h2>Frais de livraison</h2>
        <p>Livraison offerte à partir de <?= e($cfg['shipping_free_from']??'35') ?> €.<br>
        Sinon : <?= e($cfg['shipping_cost']??'4,90') ?> € en France métropolitaine.</p>
        <h2>Retrait en boutique</h2>
        <p><?= e($cfg['pickup_address']??$cfg['address']??'') ?><br><?= e($cfg['pickup_hours']??'') ?></p>
        <h2>Retours</h2><p>En cas de problème, contactez-nous à <?= e($cfg['email']??'') ?> dans les 14 jours.</p>
        <?php elseif ('info' === 'faq'): ?>
        <h2>Vos questions fréquentes</h2>
        <?php foreach ([
          ['Vos cafés sont-ils torréfiés à la commande ?','Oui ! Chaque lot est torréfié après réception de votre commande pour garantir la fraîcheur maximale.'],
          ['Quelle est la durée de conservation ?','6 à 12 mois dans un endroit frais et sec, à l'abri de la lumière. Ouvrez et consommez dans le mois.'],
          ['Proposez-vous des abonnements ?','Contactez-nous par email, nous pouvons personnaliser une offre mensuelle.'],
          ['Les ateliers sont-ils remboursables ?','Oui, jusqu'à 48h avant la date de l'atelier. Passé ce délai, un bon cadeau vous sera proposé.'],
          ['Puis-je offrir un atelier ?','Oui ! Rendez-vous sur notre page Carte Cadeau.'],
        ] as [$q,$r]): ?>
        <details class="faq-item"><summary class="faq-q"><?= $q ?><span class="faq-ico">+</span></summary><div class="faq-a"><?= $r ?></div></details>
        <?php endforeach; ?>
        <?php else: ?>
        <p>Contenu à compléter dans Admin → Modification des pages.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
