<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$cfg  = cfg_get();
$arts = array_filter(db('articles'), fn($a) => ($a['published'] ?? false) && ($a['page'] ?? 'blog') === 'blog');
usort($arts, fn($a,$b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
$sn  = $cfg['site_name'] ?? 'Café Maison'; $pt = 'Journal'; $cur = 'blog';
include ROOT . '/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <div class="breadcrumb"><a href="../">Accueil</a><span>/</span><span>Journal</span></div>
    <p class="lbl"><?= !empty($cfg['page_blog_title']) ? e($cfg['page_blog_title']) : 'Journal' ?></p>
    <h1><?= !empty($cfg['page_blog_title']) ? e($cfg['page_blog_title']) : 'Notre <em>journal</em>' ?></h1>
    <?php if (!empty($cfg['page_blog_desc'])): ?>
    <p style="max-width:520px;margin-top:.8rem"><?= e($cfg['page_blog_desc']) ?></p>
    <?php else: ?>
    <p style="max-width:520px;margin-top:.8rem">Actualités, conseils et découvertes café.</p>
    <?php endif; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <?php if (!$arts): ?>
    <div class="empty-st"><div class="empty-ico">📝</div><h4>Aucun article publié</h4><p>Revenez bientôt !</p></div>
    <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($arts as $a): ?>
      <article class="blog-card fade-up">
        <div class="blog-img" style="font-size:2.5rem;overflow:hidden;position:relative">
          <?php if (!empty($a['image'])): ?>
          <img src="<?= e($a['image']) ?>" alt="<?= e($a['title']) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0" onerror="this.style.display='none'">
          <?php else: ?>
          <?= ['Conseil'=>'💡','Voyage'=>'✈️','Éducation'=>'📚'][$a['cat']] ?? '📖' ?>
          <?php endif; ?>
        </div>
        <div class="blog-body">
          <div class="blog-meta"><span class="tag"><?= e($a['cat']) ?></span><span><?= e($a['created'] ?? '') ?></span><span><?= e($a['author'] ?? '') ?></span></div>
          <h2 class="blog-title"><?= e($a['title']) ?></h2>
          <p class="blog-exc"><?= e($a['excerpt']) ?></p>
          <a href="../pages/article.php?s=<?= e($a['slug']) ?>" class="blog-read">Lire l'article →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
