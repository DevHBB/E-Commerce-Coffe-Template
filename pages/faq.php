<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();
$pt  = $cfg['page_faq_title'] ?? 'FAQ';
$cur = '';
include ROOT . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><a href="<?= $B ?>" style="color:inherit">Accueil</a> · FAQ</p>
    <h1><?= $pt ? e($pt) : '<em>FAQ</em>' ?></h1>
  </div>
</section>
<section class="sec">
  <div class="wrap" style="max-width:800px">
    <?php $custom = $cfg['page_faq_content'] ?? ''; ?>
    <?php if ($custom): ?>
      <div class="art-content"><?= $custom ?></div>
    <?php else: ?>
      <div class="art-content">
        <h2>Questions fréquentes</h2>
        <details class="faq-item"><summary class="faq-q">Vos cafés sont-ils torréfiés à la commande ?<span class="faq-ico">+</span></summary>
          <div class="faq-a">Oui ! Chaque lot est torréfié après réception de votre commande pour garantir la fraîcheur maximale.</div></details>
        <details class="faq-item"><summary class="faq-q">Quelle est la durée de conservation ?<span class="faq-ico">+</span></summary>
          <div class="faq-a">6 à 12 mois dans un endroit frais et sec, à l'abri de la lumière. Ouvrez et consommez dans le mois.</div></details>
        <details class="faq-item"><summary class="faq-q">Proposez-vous des abonnements ?<span class="faq-ico">+</span></summary>
          <div class="faq-a">Contactez-nous par email, nous pouvons personnaliser une offre mensuelle.</div></details>
        <details class="faq-item"><summary class="faq-q">Les ateliers sont-ils remboursables ?<span class="faq-ico">+</span></summary>
          <div class="faq-a">Oui, jusqu'à 48h avant la date de l'atelier. Passé ce délai, un bon cadeau vous sera proposé.</div></details>
        <details class="faq-item"><summary class="faq-q">Puis-je offrir un atelier ?<span class="faq-ico">+</span></summary>
          <div class="faq-a">Oui ! Rendez-vous sur notre page Carte Cadeau.</div></details>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
