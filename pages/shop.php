<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/captcha.php';
security_headers();

$cfg        = cfg_get();
$ecom       = !empty($cfg['ecommerce_enabled']);
$feat_raw  = db('featured');
$feat_en   = !empty($feat_raw['enabled']); // featured activé globalement
$feat_sort = $feat_en && !empty($feat_raw['show_shop']); // trier en premier sur boutique
// Les IDs featured : toujours chargés si featured est activé (pour le badge ⭐)
$feat_ids  = $feat_en ? array_column(array_filter(db('products'), fn($p) => !empty($p['featured'])), 'id') : [];
$cats_all = db('categories');
$cats_active = array_values(array_filter($cats_all, fn($c)=>$c['active']??true));
usort($cats_active, fn($a,$b)=>($a['order']??99)-($b['order']??99));
$cats_idx = [];
foreach ($cats_active as $c) $cats_idx[$c['id']] = $c;

$flt_cat = clean($_GET['c']??'all', 40);
$flt_min = clean_float($_GET['min']??0, 0, 9999);
$flt_max = clean_float($_GET['max']??9999, 0, 9999);
$flt_q   = clean($_GET['q']??'', 100);

$all = array_filter(db('products'), function($p) use ($flt_cat, $flt_min, $flt_max, $flt_q) {
    if (!($p['active']??true)) return false;
    if ($flt_cat !== 'all' && ($p['cat_id']??$p['cat']??'') !== $flt_cat) return false;
    if ($p['price'] < $flt_min || $p['price'] > $flt_max) return false;
    if ($flt_q && !stripos(($p['name']??'').($p['desc']??'').($p['origin']??''), $flt_q) !== false
        && stripos(($p['name']??'').($p['desc']??'').($p['origin']??''), $flt_q) === false) return false;
    return true;
});
// Trier: produits featured en premier si actif et mode manuel avec sélection
$prds = array_values($all);
if ($feat_sort && $feat_ids && $flt_cat === 'all' && !$flt_q) {
    usort($prds, function($a, $b) use ($feat_ids) {
        $a_feat = in_array($a['id'], $feat_ids) ? 0 : 1;
        $b_feat = in_array($b['id'], $feat_ids) ? 0 : 1;
        return $a_feat - $b_feat;
    });
}
$sn   = $cfg['site_name'] ?? 'Café Maison'; $pt = 'Boutique'; $cur = 'shop';
// Script captcha dans <head> si avis avec captcha activé
include ROOT . '/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <div class="breadcrumb"><a href="../">Accueil</a><span>/</span><span>Boutique</span></div>
    <p class="lbl"><?= !empty($cfg['page_shop_title']) ? e($cfg['page_shop_title']) : 'Boutique' ?></p>
    <h1><?= !empty($cfg['page_shop_title']) ? e($cfg['page_shop_title']) : 'Cafés <em>& accessoires</em>' ?></h1>
    <?php if (!empty($cfg['page_shop_desc'])): ?>
    <p style="max-width:520px;margin-top:.8rem"><?= e($cfg['page_shop_desc']) ?></p>
    <?php else: ?>
    <p style="max-width:520px;margin-top:.8rem">Torréfiés à la commande, expédiés sous 48h.</p>
    <?php endif; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="shop-bar">
      <div class="shop-filters" style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a href="?" class="flt <?= $flt_cat==='all'?'on':'' ?>">Tout</a>
        <?php foreach ($cats_active as $dc): ?>
        <a href="?c=<?= urlencode($dc['id']) ?><?= $flt_q?'&q='.urlencode($flt_q):'' ?>"
          class="flt <?= $flt_cat===$dc['id']?'on':'' ?>">
          <?= e($dc['emoji']??'') ?> <?= e($dc['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;align-items:center;gap:.5rem">
        <form method="GET" style="display:flex;gap:.3rem">
          <?php if ($flt_cat!=='all'): ?><input type="hidden" name="c" value="<?= e($flt_cat) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= e($flt_q) ?>" placeholder="Rechercher…"
            class="frm-i" style="width:150px;padding:.3rem .7rem;font-size:.8rem">
          <button type="submit" class="act-btn prim" style="font-size:.8rem">🔍</button>
          <?php if ($flt_q||$flt_cat!=='all'): ?><a href="?" class="act-btn" style="font-size:.8rem">✕</a><?php endif; ?>
        </form>
        <span style="font-size:.8rem;color:var(--gy2)"><?= count($prds) ?> produit<?= count($prds)>1?'s':'' ?></span>
      </div>
    </div>
    <?php if (!$prds): ?>
    <div class="empty-st"><div class="empty-ico">☕</div><h4>Aucun produit dans cette catégorie</h4></div>
    <?php else: ?>
    <div class="prd-grid" style="margin-bottom:4rem;">
      <?php foreach ($prds as $p): ?>
      <article class="prd-card fade-up"
        data-cat="<?= e($p['cat']) ?>"
        data-id="<?= e($p['id']) ?>"
        data-name="<?= e($p['name']) ?>"
        data-price="<?= e($p['price']??0) ?>"
        data-emoji="<?= e($p['emoji'] ?? '☕') ?>">
        <div class="prd-img" style="position:relative;overflow:hidden">
          <?php if (!empty($p['image'])): ?>
          <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"
            style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:inherit"
            onerror="this.style.display='none'">
          <?php else: ?>
          <span style="font-size:4.5rem;color:rgba(255,255,255,.22)"><?= $p['emoji'] ?? '☕' ?></span>
          <?php endif; ?>
          <?php if ($feat_en && in_array($p['id'], $feat_ids)): ?>
          <span class="prd-badge" style="background:var(--or)">⭐ Sélection</span>
          <?php elseif ($p['badge']??''): ?>
          <span class="prd-badge"><?= e($p['badge']) ?></span>
          <?php endif; ?>
          <?php if(($p['roast']??'')&&$p['roast']!=='-'): ?><div class="prd-roast">🔥 <?= e($p['roast']) ?></div><?php endif; ?>
        </div>
        <div class="prd-body">
          <?php
          $p_cid = $p['cat_id'] ?? $p['cat'] ?? '';
          $p_cat_nm = isset($cats_idx[$p_cid]) ? $cats_idx[$p_cid]['name'] : ucfirst($p_cid);
          ?>
          <p class="prd-cat"><?= e($p_cat_nm) ?><?= !empty($p['origin'])&&$p['origin']!=='-'?' · '.e($p['origin']):'' ?></p>
          <h2 class="prd-name" style="font-size:1.25rem;"><?= e($p['name']) ?></h2>
          <p class="prd-desc"><?= e($p['desc']) ?></p>
          <?php if (($p['stock'] ?? 0) > 0 && $p['stock'] <= 5): ?>
          <p style="font-size:.72rem;color:var(--or);font-weight:600;margin-bottom:.7rem;">⚠ Plus que <?= (int)$p['stock'] ?> en stock</p>
          <?php endif; ?>
          
          <div class="prd-foot">
            <div class="prd-price">
              <?= number_format((float)($p['price']??0),2,',',' ') ?> €
              <?php
              $wg = (int)($p['weight_g'] ?? 0);
              $wlabel = $p['weight'] ?? '';
              if (!$wlabel && $wg > 0) {
                $wlabel = $wg >= 1000 ? number_format($wg/1000,($wg%1000?2:0),',','').'kg' : $wg.'g';
              }
              if ($wlabel && $wlabel !== '-') echo '<small> / '.e($wlabel).'</small>';
              ?>
            </div>
            <?php if ($p['stock'] > 0): ?>
            <?php if ($ecom): ?><button class="btn-add">+ Panier</button><?php endif; ?>
            <?php else: ?>
            <span style="font-size:.72rem;color:var(--gy2);font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Épuisé</span>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Infos livraison -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;padding:3rem 0;border-top:1px solid var(--cr);">
      <?php foreach([['🚀','Expédition 48h','Torréfiés à la commande.'],['📦','Livraison offerte','Dès 35 € en France métropolitaine.'],['♻️','Emballages durables','Sachets compostables, éco-conçus.']] as [$ic,$t,$d]): ?>
      <div style="text-align:center;padding:1.5rem;"><div style="font-size:2rem;margin-bottom:.7rem;"><?= $ic ?></div><h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.35rem;"><?= $t ?></h4><p style="font-size:.83rem;color:var(--gy2);"><?= $d ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<!-- Widget avis clients si activé -->
<?php
$cfg_rv = cfg_get();
if (!empty($cfg_rv['reviews_enabled']) && !empty($cfg_rv['reviews_products'])):
    $published_reviews = array_filter(db('reviews'), fn($r) => ($r['approved']??false) && ($r['target']==='product'||empty($r['target'])));
    $avg_r = count($published_reviews) ? round(array_sum(array_column(array_values($published_reviews),'rating'))/count($published_reviews),1) : 0;
?>
<section class="sec" style="background:var(--cr)">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:start">

      <!-- Avis existants -->
      <div>
        <p class="lbl">Avis clients</p>
        <h2 style="margin-bottom:1.5rem"><?= count($published_reviews) ?> avis · <em><?= $avg_r ?>/5 ⭐</em></h2>
        <?php foreach (array_slice(array_reverse(array_values($published_reviews)),0,5) as $rv): ?>
        <div style="background:#fff;border-radius:10px;padding:1.2rem;margin-bottom:.8rem">
          <div style="display:flex;justify-content:space-between;margin-bottom:.5rem">
            <div>
              <strong style="font-size:.9rem"><?= e($rv['author']??'Anonyme') ?></strong>
              <?php if(!empty($rv['order_id'])): ?><span class="bdg bdg-g" style="font-size:.6rem;margin-left:.3rem">✓ Achat vérifié</span><?php endif; ?>
            </div>
            <div style="color:var(--or)"><?= str_repeat('★',$rv['rating']??5) ?></div>
          </div>
          <p style="font-size:.88rem;color:var(--gy);margin:0"><?= e($rv['content']??'') ?></p>
          <?php if(!empty($rv['reply'])): ?>
          <div style="background:rgba(42,76,30,.07);border-left:3px solid var(--vr3);padding:.6rem .8rem;margin-top:.7rem;border-radius:0 6px 6px 0;font-size:.82rem">
            <strong>Réponse de l'équipe :</strong> <?= e($rv['reply']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$published_reviews): ?>
        <p style="color:var(--gy2)">Soyez le premier à laisser un avis !</p>
        <?php endif; ?>
      </div>

      <!-- Formulaire avis -->
      <div>
        <h3 style="font-family:var(--f1);font-size:1.3rem;margin-bottom:1rem">Votre avis</h3>
        <div id="reviewMsg"></div>
        <form id="reviewForm">
          <div class="stars-input" style="margin-bottom:1rem">
            <?php for($i=5;$i>=1;$i--): ?>
            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= $i===5?'required':'' ?>>
            <label for="star<?= $i ?>">★</label>
            <?php endfor; ?>
          </div>
          <div class="form-2">
            <div class="form-g"><label class="form-l">Votre prénom *</label><input class="form-i" name="author" required maxlength="80" placeholder="Jean"></div>
            <div class="form-g"><label class="form-l">Email (non publié)</label><input class="form-i" type="email" name="email" maxlength="150" placeholder="jean@mail.fr"></div>
          </div>
          <div class="form-g"><label class="form-l">Votre avis *</label>
            <textarea class="form-ta" name="content" required maxlength="1000" rows="4" placeholder="Partagez votre expérience…"></textarea></div>
          <?php if(!empty($cfg_rv['captcha_reviews'])&&!empty($cfg_rv['captcha_site_key'])): ?>
          <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">
          <div style="font-size:.68rem;color:var(--gy2);text-align:center;margin-bottom:.5rem">
            🔒 Protégé par reCAPTCHA v3 ·
            <a href="https://policies.google.com/privacy" style="color:var(--gy2)" target="_blank">Confidentialité</a>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-or" style="width:100%;justify-content:center">Envoyer mon avis</button>
          <p style="font-size:.72rem;color:var(--gy2);margin-top:.6rem;text-align:center">Votre avis sera publié après modération.</p>
        </form>
      </div>
    </div>
  </div>
</section>
<?php
endif; // reviews_enabled
?>

<script>
document.getElementById('reviewForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = 'Envoi…';
  const fd   = new FormData(this);
  const key  = '<?= e($cfg_rv['captcha_site_key'] ?? '') ?>';
  const B    = window.SITE_BASE || '../';

  // Obtenir le token reCAPTCHA v3 si disponible
  let cap = '';
  if (key && typeof grecaptcha !== 'undefined') {
    try {
      cap = await new Promise((resolve, reject) => {
        grecaptcha.ready(() => {
          grecaptcha.execute(key, {action: 'review'}).then(resolve).catch(reject);
        });
      });
    } catch(e) { cap = ''; }
  }

  try {
    const r = await fetch(B+'api/submit_review.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        author:        fd.get('author'),
        email:         fd.get('email'),
        rating:        parseInt(fd.get('rating') || '5'),
        content:       fd.get('content'),
        target:        'product',
        captcha_token: cap,
      })
    });
    const d = await r.json();
    const msg = document.getElementById('reviewMsg');
    msg.innerHTML = d.ok
      ? '<div class="review-ok">✓ Merci ! Votre avis sera publié après modération.</div>'
      : '<div class="review-err">✗ ' + (d.error || 'Erreur') + '</div>';
    if (d.ok) this.reset();
  } catch(err) {
    document.getElementById('reviewMsg').innerHTML =
      '<div class="review-err">✗ Erreur réseau. Réessayez.</div>';
  }
  btn.disabled = false; btn.textContent = 'Envoyer mon avis';
});
</script>

<!-- Quiz suggestion légère -->
<section style="background:var(--bk);padding:3rem 0">
  <div class="wrap tc">
    <p style="color:rgba(255,255,255,.5);font-size:.78rem;text-transform:uppercase;letter-spacing:.15em;margin-bottom:.6rem">Vous ne savez pas quoi choisir ?</p>
    <h3 style="font-family:var(--f1);color:#fff;font-size:1.8rem;margin-bottom:1rem">Trouvez votre café idéal en 6 questions</h3>
    <a href="./quiz.php" class="btn btn-or">Faire le quiz →</a>
  </div>
</section>
<?php include ROOT . '/includes/footer.php'; ?>
