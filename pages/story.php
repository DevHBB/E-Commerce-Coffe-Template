<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg = cfg_get();
if (empty($cfg['page_story_enabled'])) {
    header('Location: ../'); exit;
}
$pt  = $cfg['page_story_title'] ?? 'Notre Histoire';
$cur = 'story';
include ROOT . '/includes/header.php';
?>
<section class="page-hero" style="min-height:60vh;display:flex;align-items:center">
  <?php if (!empty($cfg['story_hero_img'])): ?>
  <div style="position:absolute;inset:0;z-index:0">
    <img src="<?= e($cfg['story_hero_img']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;opacity:.35">
  </div>
  <?php endif; ?>
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl"><?= e($cfg['page_story_title'] ?? 'Notre Histoire') ?></p>
    <h1 class="fade-up"><?= e($cfg['story_subtitle'] ?? 'L\'art du café,<br>une passion transmise.') ?></h1>
  </div>
</section>

<?php if (!empty($cfg['story_text1'])): ?>
<section class="sec">
  <div class="wrap">
    <div class="ab-grid fade-up">
      <?php if (!empty($cfg['story_img2'])): ?>
      <div class="ab-img">
        <img src="<?= e($cfg['story_img2']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:inherit">
      </div>
      <?php else: ?>
      <div class="ab-img">☕</div>
      <?php endif; ?>
      <div>
        <?php if (!empty($cfg['story_text1_title'])): ?>
        <p class="lbl"><?= e($cfg['story_text1_title']) ?></p>
        <?php endif; ?>
        <p style="font-size:1rem;line-height:1.9;color:var(--gy)"><?= nl2br(e($cfg['story_text1'] ?? '')) ?></p>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($cfg['story_quote'])): ?>
<section style="background:var(--bk);padding:4rem 0">
  <div class="wrap tc fade-up">
    <p style="font-family:var(--f1);font-size:clamp(1.4rem,3vw,2.4rem);color:#fff;font-style:italic;max-width:700px;margin:0 auto;line-height:1.5">
      « <?= e($cfg['story_quote']) ?> »
    </p>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($cfg['story_text2']) || !empty($cfg['story_text3'])): ?>
<section class="sec sec-cream">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem">
      <?php if (!empty($cfg['story_text2'])): ?>
      <div class="fade-up">
        <?php if (!empty($cfg['story_text2_title'])): ?><h3 style="font-family:var(--f1);margin-bottom:1rem"><?= e($cfg['story_text2_title']) ?></h3><?php endif; ?>
        <p style="color:var(--gy);line-height:1.9"><?= nl2br(e($cfg['story_text2'])) ?></p>
      </div>
      <?php endif; ?>
      <?php if (!empty($cfg['story_text3'])): ?>
      <div class="fade-up" data-delay="1">
        <?php if (!empty($cfg['story_text3_title'])): ?><h3 style="font-family:var(--f1);margin-bottom:1rem"><?= e($cfg['story_text3_title']) ?></h3><?php endif; ?>
        <p style="color:var(--gy);line-height:1.9"><?= nl2br(e($cfg['story_text3'])) ?></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="wrap tc fade-up">
    <h2>Envie de nous rencontrer ?</h2>
    <p style="color:var(--gy);margin-bottom:2rem">Venez déguster, discuter, apprendre. Notre boutique vous attend.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= base_path() ?>pages/atelier.php" class="btn btn-or">Voir les ateliers →</a>
      <a href="<?= base_path() ?>pages/shop.php" class="btn btn-out">La boutique →</a>
    </div>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
