<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$cfg      = cfg_get();
$ecom     = !empty($cfg['ecommerce_enabled']);
$booking  = !empty($cfg['booking_enabled'] ?? true);  // indépendant du ecom
$book_paid = !empty($cfg['booking_paid'] ?? true);     // paiement en ligne ou sur place
$wks      = array_filter(db('workshops'), fn($w) => $w['active'] ?? true);
$sessions = db('sessions');
$bookings = db('bookings');
// Préparer les créneaux par atelier
$sess_by_wk = [];
$bk_cts = [];
foreach ($bookings as $b) {
    $sid = $b['session_id']??'';
    $bk_cts[$sid] = ($bk_cts[$sid]??0) + (($b['status']??'')!=='cancelled'?(int)($b['guests']??1):0);
}
foreach ($sessions as $s) {
    if (!($s['active']??true) || ($s['date']??'')<date('Y-m-d')) continue;
    $wid = $s['workshop_id']??'';
    $booked = $bk_cts[$s['id']] ?? 0;
    $seats  = $s['seats'] ?? 8;
    $s['remaining'] = max(0, $seats - $booked);
    $s['is_full']   = $s['remaining'] <= 0;
    $sess_by_wk[$wid][] = $s;
}
$sn  = $cfg['site_name'] ?? 'Café Maison'; $pt = 'Atelier'; $cur = 'atelier';
include ROOT . '/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <div class="breadcrumb"><a href="../">Accueil</a><span>/</span><span>Atelier</span></div>
    <p class="lbl"><?= !empty($cfg['page_atelier_title']) ? e($cfg['page_atelier_title']) : 'Atelier' ?></p>
    <h1><?= !empty($cfg['page_atelier_title']) ? e($cfg['page_atelier_title']) : 'Ateliers <em>de café</em>' ?></h1>
    <?php if (!empty($cfg['page_atelier_desc'])): ?>
    <p style="max-width:520px;margin-top:.8rem"><?= e($cfg['page_atelier_desc']) ?></p>
    <?php else: ?>
    <p style="max-width:520px;margin-top:.8rem">Apprenez, dégustez, partagez notre passion.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Ateliers -->
<section class="sec">
  <div class="wrap">
    <div class="tc fade-up" style="margin-bottom:3.5rem;">
      <p class="lbl">Programme</p>
      <h2>Choisissez votre <em>expérience</em></h2>
      <span class="line"></span>
    </div>
    <div class="atl-full-grid">
      <?php foreach ($wks as $w): ?>
      <article class="atl-full-card fade-up">
        <?php if (!empty($w['image'])): ?>
        <div style="height:180px;overflow:hidden;border-radius:var(--rl) var(--rl) 0 0">
          <img src="<?= e($w['image']) ?>" alt="<?= e($w['name']) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.closest('div').style.display='none'">
        </div>
        <?php endif; ?>
        <div class="atl-top">
          <div class="atl-top-ico"><?= e($w['emoji'] ?? '☕') ?></div>
          <h3><?= e($w['name']) ?></h3>
          <div class="atl-top-tags">
            <span class="tag-pill tag-lvl"><?= e($w['level'] ?? '') ?></span>
            <span class="tag-pill tag-dur"><?= e($w['duration'] ?? $w['dur'] ?? '') ?></span>
            <span class="tag-pill tag-cap">Max <?= (int)($w['capacity'] ?? $w['cap'] ?? 0) ?> pers.</span>
          </div>
        </div>
        <div class="atl-bot">
          <p><?= e($w['desc'] ?? '') ?></p>
          <div class="atl-sched">🗓 <?= e($w['schedule'] ?? $w['sched'] ?? '') ?></div>
          <div class="atl-foot">
            <div class="atl-foot-price"><?= number_format((float)($w['price'] ?? 0),0,',',' ') ?> € <small>/ pers.</small></div>
            <a href="./booking.php?w=<?= e($w['id']) ?>" class="btn btn-or" style="font-size:.75rem;">Réserver →</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="sec" style="background:var(--cr);padding-top:4rem;">
  <div class="wrap">
    <div class="tc fade-up" style="margin-bottom:3rem;">
      <p class="lbl">FAQ</p>
      <h2>Questions <em>fréquentes</em></h2>
      <span class="line"></span>
    </div>
    <div style="max-width:700px;margin:0 auto;">
      <?php
      $faqs = [
        ['Dois-je avoir de l\'expérience ?','Pas du tout. L\'initiation à la dégustation est pensée pour les grands débutants. Pas de prérequis.'],
        ['Que faut-il apporter ?','Rien ! Matériel, cafés et mise en place sont fournis. Venez simplement avec votre curiosité.'],
        ['Combien de personnes par session ?','4 à 8 personnes maximum selon l\'atelier, pour une expérience vraiment personnalisée.'],
        ['Puis-je offrir un atelier en cadeau ?','Oui, nous proposons des bons cadeaux personnalisés. Contactez-nous par email ou formulaire.'],
        ['Y a-t-il quelque chose à manger ?','Nous proposons des mignardises pour les courtes sessions. Pour les ateliers ≥3h, une collation est incluse.'],
      ];
      foreach ($faqs as [$q,$a]): ?>
      <div class="faq-item fade-up">
        <details>
          <summary class="faq-q"><?= e($q) ?><span class="faq-ico">+</span></summary>
          <p class="faq-a"><?= e($a) ?></p>
        </details>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="background:var(--or);padding:5rem 0;text-align:center;">
  <div class="wrap fade-up">
    <h2 style="color:#fff;margin-bottom:.8rem;">Prêt à vous lancer ?</h2>
    <p style="color:rgba(255,255,255,.85);max-width:440px;margin:0 auto 2rem;line-height:1.9;">Réservez votre place ou posez toutes vos questions — on adore en parler.</p>
    <a href="./booking.php" class="btn" style="background:#fff;color:var(--or);">Voir les créneaux</a>
  </div>
</section>

<?php include ROOT . '/includes/footer.php'; ?>
