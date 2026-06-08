<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$cfg  = cfg_get();
$s    = clean($_GET['s'] ?? '', 150);
$arts = db('articles');
$art  = null;
foreach ($arts as $a) {
    if (($a['slug'] ?? '') === $s && ($a['published'] ?? false)) { $art = $a; break; }
}
if (!$art) { http_response_code(404); header('Location: /pages/blog.php'); exit; }

$sn  = $cfg['site_name'] ?? 'Café Maison'; $pt = $art['title']; $cur = 'blog';
include ROOT . '/includes/header.php';
?>

<div style="background:var(--bk);padding:6rem 0 3rem;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(200,86,30,.14),transparent 60%);pointer-events:none;"></div>
  <div class="wrap" style="position:relative;z-index:1;max-width:780px;">
    <div class="breadcrumb"><a href="../">Accueil</a><span>/</span><a href="../pages/blog.php">Journal</a><span>/</span><span><?= e($art['cat'] ?? '') ?></span></div>
    <h1 style="color:#fff;font-size:clamp(2rem,4vw,3rem);margin-top:.6rem;line-height:1.1;"><?= e($art['title']) ?></h1>
    <div style="display:flex;gap:1.5rem;margin-top:1rem;font-size:.77rem;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.1em;">
      <span><?= e($art['author'] ?? '') ?></span><span><?= e($art['created'] ?? '') ?></span>
    </div>
  </div>
</div>

<div class="wrap" style="max-width:780px;padding-top:3rem;padding-bottom:5rem;">
  <?php if (!empty($art['image'])): ?>
  <div style="margin-bottom:2rem;border-radius:14px;overflow:hidden;max-height:420px">
    <img src="<?= e($art['image']) ?>" alt="<?= e($art['title']) ?>" style="width:100%;height:420px;object-fit:cover" onerror="this.parentElement.style.display='none'">
  </div>
  <?php endif; ?>
  <p class="art-quote"><?= e($art['excerpt'] ?? '') ?></p>
  <div class="art-content"><?= $art['content'] /* HTML stocké par admin de confiance */ ?></div>
  <div style="margin-top:3rem;padding-top:2rem;border-top:1px solid var(--cr);display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <a href="../pages/blog.php" class="btn btn-out">← Retour au journal</a>
    <a href="../#contact" class="btn btn-or">Nous contacter</a>
  </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
