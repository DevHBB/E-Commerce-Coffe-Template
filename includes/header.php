<?php
if (!defined('ROOT')) require_once __DIR__ . '/../includes/config.php';

if (!is_logged() && !defined('SKIP_MAINTENANCE')) {
    $cfg_m = cfg_get();
    if (!empty($cfg_m['maintenance'])) {
        include ROOT . '/pages/maintenance.php'; exit;
    }
}

security_headers();
$cfg = cfg_get();
$sn  = $cfg['site_name'] ?? 'Café Maison';
$cur = $cur ?? '';
$B   = base_path();

$isHome    = (basename($_SERVER['SCRIPT_FILENAME']) === 'index.php'
              && realpath(dirname($_SERVER['SCRIPT_FILENAME'])) === realpath(ROOT));

$logo_mode = $cfg['logo_mode'] ?? 'text';
$logo_url  = $cfg['logo_url']  ?? '';
$delivery  = !empty($cfg['delivery_enabled']);
$pickup    = !empty($cfg['pickup_enabled']);
$ecom      = !empty($cfg['ecommerce_enabled']); // false = site vitrine

// $ecom défini avant bodyClass
$bodyClass = $isHome ? 'home' : '';
if (!$ecom) $bodyClass .= ' vitrine-mode';

// ── Calcul Ouvert/Fermé ──────────────────────────────────────────────
$is_open = false;
$oh_data = json_decode($cfg['open_hours']??'[]', true) ?: [];
if ($oh_data) {
    $days_map = ['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>0];
    $cur_dow  = (int)date('w'); // 0=dim
    $cur_time = date('H:i');
    foreach ($oh_data as $h) {
        $day_n = $days_map[$h['day']] ?? -1;
        if ($day_n !== $cur_dow) continue;
        // Support multi-créneaux (slots) et ancien format (open/close)
        $slots = $h['slots'] ?? (isset($h['open']) ? [['open'=>$h['open'],'close'=>$h['close']]] : []);
        foreach ($slots as $sl) {
            if ($cur_time >= ($sl['open']??'') && $cur_time < ($sl['close']??'')) {
                $is_open = true; break 2;
            }
        }
    }
}
$open_text   = $cfg['open_open_text']   ?? 'Ouvert actuellement';
$closed_text = $cfg['open_closed_text'] ?? 'Fermée actuellement';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php
// SEO Meta tags
$seo_desc = !empty($cfg['seo_meta_desc']) ? $cfg['seo_meta_desc'] : ($cfg['tagline']??'');
$seo_kw   = $cfg['seo_keywords'] ?? '';
$seo_img  = $cfg['seo_og_image'] ?? '';
$page_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.($_SERVER['HTTP_HOST']??'').$_SERVER['REQUEST_URI'];
$robots_content = !empty($cfg['seo_robots_index']??true) ? 'index,follow' : 'noindex,nofollow';
?>
<meta name="description" content="<?= e($pt ? ($pt.' — '.$seo_desc) : $seo_desc) ?>">
<?php if ($seo_kw): ?><meta name="keywords" content="<?= e($seo_kw) ?>"><?php endif; ?>
<meta name="robots" content="<?= $robots_content ?>">
<meta name="theme-color" content="#C8561E">
<link rel="canonical" href="<?= e($page_url) ?>">
<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e(isset($pt)?$pt.' — '.$sn:$sn) ?>">
<meta property="og:description" content="<?= e($seo_desc) ?>">
<meta property="og:url" content="<?= e($page_url) ?>">
<meta property="og:site_name" content="<?= e($sn) ?>">
<?php if ($seo_img): ?><meta property="og:image" content="<?= e($seo_img) ?>"><?php endif; ?>
<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e(isset($pt)?$pt.' — '.$sn:$sn) ?>">
<meta name="twitter:description" content="<?= e($seo_desc) ?>">
<?php if ($seo_img): ?><meta name="twitter:image" content="<?= e($seo_img) ?>"><?php endif; ?>
<!-- Schema.org LocalBusiness -->
<script type="application/ld+json">
<?= json_encode(['@context'=>'https://schema.org','@type'=>'CafeOrCoffeeShop','name'=>$sn,'description'=>$seo_desc,'address'=>['@type'=>'PostalAddress','streetAddress'=>$cfg['address']??'','addressLocality'=>'Paris','addressCountry'=>'FR'],'telephone'=>$cfg['phone']??'','email'=>$cfg['email']??'','url'=>(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']?'https':'http').'://'.($_SERVER['HTTP_HOST']??''),'openingHours'=>[$cfg['hours_week']??'',$cfg['hours_weekend']??''],'currenciesAccepted'=>'EUR','paymentAccepted'=>'Cash, Credit Card'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) ?>
</script>
<?php if (!empty($cfg['seo_google_verify'])): ?>
<meta name="google-site-verification" content="<?= e($cfg['seo_google_verify']) ?>">
<?php endif; ?>
<title><?= isset($pt) ? e($pt).' — ' : '' ?><?= e($sn) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $B ?>assets/css/style.css">
</head>
<body class="<?= $bodyClass ?>">

<?php if (!empty($cfg['page_transition'])): ?>
<div id="pageLoader" style="position:fixed;inset:0;background:#0C0C0C;z-index:9999;display:flex;align-items:center;justify-content:center;transition:opacity .5s ease,visibility .5s ease">
  <div style="text-align:center">
    <svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
      <style>@keyframes s{0%,100%{transform:translateY(0) scaleX(1);opacity:.7}50%{transform:translateY(-6px) scaleX(1.2);opacity:0}}@keyframes b{0%,100%{transform:translateY(0)}40%{transform:translateY(-8px)}70%{transform:translateY(-4px)}}.st{animation:s 1.2s ease infinite}.cu{animation:b .9s ease infinite}</style>
      <path class="st" d="M22 18 Q24 12 22 6" stroke="#C8561E" stroke-width="1.5" fill="none" stroke-linecap="round" style="animation-delay:.1s"/>
      <path class="st" d="M30 18 Q32 11 30 4" stroke="#C8561E" stroke-width="1.5" fill="none" stroke-linecap="round" style="animation-delay:.3s"/>
      <path class="st" d="M38 18 Q40 12 38 6" stroke="#C8561E" stroke-width="1.5" fill="none" stroke-linecap="round" style="animation-delay:.5s"/>
      <g class="cu">
        <path d="M15 24 L45 24 L42 44 Q41 46 39 46 L21 46 Q19 46 18 44 Z" fill="#C8561E"/>
        <path d="M45 28 Q54 28 54 35 Q54 42 45 42" stroke="#8C3A10" stroke-width="3" fill="none" stroke-linecap="round"/>
        <ellipse cx="30" cy="26" rx="13" ry="3" fill="#1A0800"/>
        <ellipse cx="30" cy="25" rx="11" ry="2" fill="#8C3A10"/>
        <ellipse cx="30" cy="46" rx="17" ry="3" fill="#8C3A10"/>
      </g>
    </svg>
    <div style="color:rgba(255,255,255,.28);font-size:.62rem;text-transform:uppercase;letter-spacing:.2em;margin-top:.8rem;font-family:Jost,sans-serif"><?= e($sn) ?></div>
  </div>
</div>
<script>
(function(){
  function hideLoader(){var l=document.getElementById('pageLoader');if(l){l.style.opacity='0';l.style.visibility='hidden';setTimeout(function(){if(l.parentNode)l.parentNode.removeChild(l);},400);}}
  // DOMContentLoaded est beaucoup plus rapide que 'load' (pas besoin d'attendre les images/fonts)
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',hideLoader);}
  else{hideLoader();}
  // Sécurité: forcer la disparition après 1.5s maximum
  setTimeout(hideLoader, 1500);
})();
</script>
<?php endif; ?>

<script>
window.SITE_BASE = <?= json_encode($B) ?>;
window.SITE_CFG  = {
  shipping_free:    <?= (float)($cfg['shipping_free_from'] ?? 35) ?>,
  shipping:         <?= (float)($cfg['shipping_cost'] ?? 4.90) ?>,
  currency:         <?= json_encode($cfg['currency'] ?? 'EUR') ?>,
  delivery_enabled: <?= $delivery ? 'true' : 'false' ?>,
  ecommerce:        <?= $ecom ? 'true' : 'false' ?>,
  pickup_enabled:   <?= $pickup   ? 'true' : 'false' ?>,
  pickup_address:   <?= json_encode($cfg['pickup_address'] ?? '') ?>,
  pickup_hours:     <?= json_encode($cfg['pickup_hours'] ?? '') ?>,
  promo_url:        <?= json_encode($B.'api/check_promo.php') ?>
};
<?php if (!empty($cfg['stripe_pk'])): ?>window.STRIPE_PK = <?= json_encode($cfg['stripe_pk']) ?>;<?php endif; ?>
<?php if (!empty($cfg['paypal_client_id'])): ?>window.PAYPAL_CLIENT_ID = <?= json_encode($cfg['paypal_client_id']) ?>;<?php endif; ?>
</script>
<?php if (!empty($cfg['stripe_pk'])): ?><script src="https://js.stripe.com/v3/" defer></script><?php endif; ?>
<?php if (!empty($cfg['paypal_client_id'])): ?><script src="https://www.paypal.com/sdk/js?client-id=<?= e($cfg['paypal_client_id']) ?>&currency=<?= e($cfg['currency'] ?? 'EUR') ?>" defer></script><?php endif; ?>

<header id="hdr">
  <nav class="nav wrap">

    <!-- ── Logo : image OU texte ─────────────────────────── -->
    <a href="<?= $B ?>" class="logo">
      <?php if ($logo_mode === 'image' && $logo_url): ?>
        <img src="<?= e($logo_url) ?>" alt="<?= e($sn) ?>" class="logo-img">
      <?php else: ?>
        <span class="logo-dot"></span><?= e($sn) ?>
      <?php endif; ?>
    </a>

    <ul class="nav-desktop">
      <?php if (!empty($cfg['nav_show_home']??true)): ?>
      <li><a href="<?= $B ?>#about" class="<?= $cur==='about'?'act':'' ?>"><?= e($cfg['nav_label_home']??'La Maison') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['page_story_enabled'])): ?>
      <li><a href="<?= $B ?>pages/story.php" class="<?= $cur==='story'?'act':'' ?>"><?= e($cfg['page_story_title']??'Notre Histoire') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['nav_show_shop']??true)): ?>
      <li><a href="<?= $B ?>pages/shop.php" class="<?= $cur==='shop'?'act':'' ?>"><?= e($cfg['nav_label_shop']??'Boutique') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['nav_show_atelier']??true)): ?>
      <li><a href="<?= $B ?>pages/atelier.php" class="<?= $cur==='atelier'?'act':'' ?>"><?= e($cfg['nav_label_atelier']??'Atelier') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['nav_show_blog']??true)): ?>
      <li><a href="<?= $B ?>pages/blog.php" class="<?= $cur==='blog'?'act':'' ?>"><?= e($cfg['nav_label_blog']??'Journal') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['nav_show_giftcard']??true)): ?>
      <li><a href="<?= $B ?>pages/giftcard.php" class="<?= $cur==='giftcard'?'act':'' ?>"><?= e($cfg['nav_label_giftcard']??'🎁') ?></a></li>
      <?php endif; ?>
      <?php if (!empty($cfg['nav_show_contact']??true)): ?>
      <li><a href="<?= $B ?>index.php#contact"><?= e($cfg['nav_label_contact']??'Contact') ?></a></li>
      <?php endif; ?>
    </ul>

    <div class="nav-right">
      <?php if ($oh_data): ?>
      <div class="open-badge <?= $is_open?'open-yes':'open-no' ?>">
        <span class="open-dot"></span>
        <span class="open-txt"><?= e($is_open?$open_text:$closed_text) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($ecom): ?>
      <button class="cart-trigger" aria-label="Panier">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span class="cart-count">0</span>
      </button>
      <?php endif; ?>
      <?php if ($ecom): ?>
      <button class="btn nav-cta" onclick="window.location='<?= $B ?>pages/shop.php'">Commander</button>
      <?php endif; ?>
      <button class="hbg" id="hbg" aria-label="Menu">
        <span class="hbg-bar"></span><span class="hbg-bar"></span><span class="hbg-bar"></span>
      </button>
    </div>
  </nav>
</header>

<!-- Drawer mobile -->
<nav class="nav-drawer" id="navDrawer">
  <div class="drawer-top">
    <div class="drawer-logo"><?= e($sn) ?> <span>.</span></div>
    <button class="drawer-close" id="drawerClose">×</button>
  </div>
  <div class="drawer-links">
    <?php if (!empty($cfg['nav_show_home']??true)): ?>
    <a href="<?= $B ?>" class="<?= $isHome?'act':'' ?>"><?= e($cfg['nav_label_home']??'La Maison') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['page_story_enabled'])): ?>
    <a href="<?= $B ?>pages/story.php" class="<?= $cur==='story'?'act':'' ?>"><?= e($cfg['page_story_title']??'Notre Histoire') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['nav_show_shop']??true)): ?>
    <a href="<?= $B ?>pages/shop.php" class="<?= $cur==='shop'?'act':'' ?>"><?= e($cfg['nav_label_shop']??'Boutique') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['nav_show_atelier']??true)): ?>
    <a href="<?= $B ?>pages/atelier.php" class="<?= $cur==='atelier'?'act':'' ?>"><?= e($cfg['nav_label_atelier']??'Atelier') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['nav_show_blog']??true)): ?>
    <a href="<?= $B ?>pages/blog.php" class="<?= $cur==='blog'?'act':'' ?>"><?= e($cfg['nav_label_blog']??'Journal') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['nav_show_giftcard']??true)): ?>
    <a href="<?= $B ?>pages/giftcard.php" class="<?= $cur==='giftcard'?'act':'' ?>">🎁 <?= e($cfg['nav_label_giftcard']??'Carte Cadeau') ?></a>
    <?php endif; ?>
    <?php if (!empty($cfg['nav_show_contact']??true)): ?>
    <a href="<?= $B ?>index.php#contact"><?= e($cfg['nav_label_contact']??'Contact') ?></a>
    <?php endif; ?>
  </div>
  <div class="drawer-bottom">
    <div class="drawer-info">
      <?= e($cfg['address'] ?? '') ?><br>
      <?= e($cfg['hours_week'] ?? '') ?><br>
      <a href="tel:<?= e($cfg['phone'] ?? '') ?>"><?= e($cfg['phone'] ?? '') ?></a>
    </div>
  </div>
</nav>

<!-- Panier sidebar (masqué en mode vitrine) -->
<?php if ($ecom): ?>
<div class="cart-overlay" id="cartOverlay"></div>
<div class="cart-sidebar" id="cartSidebar">
  <div class="cart-hd">
    <h3>Mon panier</h3>
    <button class="cart-close" id="cartClose">×</button>
  </div>
  <div class="cart-items" id="cartItems"></div>
  <div class="cart-ft">
    <!-- Code promo -->
    <div style="display:flex;gap:.5rem;margin-bottom:.8rem">
      <input type="text" id="promoInput" placeholder="Code promo"
        style="flex:1;padding:.5rem .8rem;border:1.5px solid var(--gy3);border-radius:var(--r);font-size:.82rem;background:var(--wh);color:var(--bk);text-transform:uppercase"
        maxlength="30">
      <button id="applyPromo" class="btn btn-out-or btn-sm" style="white-space:nowrap">Appliquer</button>
    </div>
    <div id="promoMsg" style="font-size:.78rem;margin-bottom:.6rem"></div>
    <button id="removePromo" style="display:none;font-size:.7rem;color:var(--or);background:none;border:none;cursor:pointer;margin-bottom:.5rem">✕ Retirer le code</button>
    <div class="cart-line"><span>Sous-total</span><span id="cartSub">0,00 €</span></div>
    <div class="cart-line" id="cartPromoLine" style="display:none;color:var(--vr2)"><span>🏷 Réduction</span><span id="cartPromoAmt"></span></div>
    <div class="cart-line" id="cartShipLine"><span>Livraison</span><span id="cartShip">—</span></div>
    <div class="cart-total"><span>Total</span><span id="cartTotal">0,00 €</span></div>
    <button class="btn btn-or" id="cartCheckoutBtn" disabled>Passer commande →</button>
    <button class="btn btn-out btn-sm" onclick="closeCart?.();window.location='<?= $B ?>pages/shop.php'"
      style="margin-top:.5rem;width:100%;justify-content:center">Continuer mes achats</button>
    <div class="pay-icons">
      Paiement sécurisé ·
      <span class="pay-icon">Visa</span><span class="pay-icon">MC</span>
      <span class="pay-icon">PayPal</span><span class="pay-icon">Stripe</span>
    </div>
  </div>
</div>
<div class="cart-toast" id="cartToast"></div>
<?php endif; // $ecom — fin sidebar panier ?>

<?php
// ── Popup festif (sans bugs) ──────────────────────────────────────────
if (!empty($cfg['popup_enabled']) && !empty($cfg['popup_type'])):
    $pop_type  = $cfg['popup_type']    ?? 'custom';
    $pop_delay = (int)($cfg['popup_delay'] ?? 2);
    $pop_once  = !empty($cfg['popup_once'] ?? true);
    $pop_title = $cfg['popup_title']    ?? '';
    $pop_msg   = $cfg['popup_message']  ?? '';
    $pop_cta   = $cfg['popup_cta_text'] ?? '';
    $pop_url   = $cfg['popup_cta_url']  ?? '';
    $pop_promo = $cfg['popup_promo']    ?? '';
    $pop_image = $cfg['popup_image']    ?? '';
    $pop_themes = [
        'valentine' => ['bg'=>'linear-gradient(135deg,#ff6b8a,#c41e5c)', 'emoji'=>'💝', 'accent'=>'#e91e63'],
        'halloween' => ['bg'=>'linear-gradient(135deg,#1a0a00,#e65c00)', 'emoji'=>'🎃', 'accent'=>'#ff6600'],
        'christmas' => ['bg'=>'linear-gradient(135deg,#0d2e0d,#b71c1c)', 'emoji'=>'🎄', 'accent'=>'#e53935'],
        'easter'    => ['bg'=>'linear-gradient(135deg,#6a1b9a,#f9a825)', 'emoji'=>'🐣', 'accent'=>'#f9a825'],
        'summer'    => ['bg'=>'linear-gradient(135deg,#01579b,#e65100)', 'emoji'=>'☀️', 'accent'=>'#ff6d00'],
        'epiphanie' => ['bg'=>'linear-gradient(135deg,#3e2723,#f9a825)', 'emoji'=>'👑', 'accent'=>'#f9a825'],
        'custom'    => ['bg'=>'linear-gradient(135deg,#C8561E,#2A4C1E)', 'emoji'=>'☕', 'accent'=>'#C8561E'],
    ];
    $th = $pop_themes[$pop_type] ?? $pop_themes['custom'];
    $pop_key = 'fp_' . substr(md5($pop_type . $pop_title), 0, 12);
    $pop_promo_js = json_encode($pop_promo); // Encodage JS sécurisé
?>
<div id="festivePop" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.65);backdrop-filter:blur(5px);align-items:center;justify-content:center;padding:1rem">
  <div style="max-width:500px;width:100%;border-radius:20px;overflow:hidden;box-shadow:0 30px 100px rgba(0,0,0,.5);animation:popIn .45s cubic-bezier(.16,1,.3,1)">
    <div style="background:<?= $th['bg'] ?>;padding:2.5rem 2rem;text-align:center;position:relative">
      <button onclick="closePop()" style="position:absolute;top:.8rem;right:.8rem;background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center">✕</button>
      <?php if ($pop_image): ?>
      <img src="<?= e($pop_image) ?>" alt="" style="max-height:90px;border-radius:10px;margin-bottom:.8rem;object-fit:cover">
      <?php else: ?>
      <div style="font-size:4rem;margin-bottom:.5rem"><?= $th['emoji'] ?></div>
      <?php endif; ?>
      <?php if ($pop_title): ?>
      <h2 style="color:#fff;font-family:var(--f1);font-size:1.8rem;margin:0;text-shadow:0 2px 12px rgba(0,0,0,.3)"><?= e($pop_title) ?></h2>
      <?php endif; ?>
    </div>
    <div style="background:#fff;padding:2rem;text-align:center">
      <?php if ($pop_msg): ?>
      <p style="color:#555;font-size:.95rem;line-height:1.7;margin-bottom:1.2rem"><?= nl2br(e($pop_msg)) ?></p>
      <?php endif; ?>
      <?php if ($pop_promo): ?>
      <div style="background:rgba(200,86,30,.07);border:2px dashed <?= $th['accent'] ?>;border-radius:10px;padding:.8rem 1.4rem;margin-bottom:1.2rem;display:inline-block">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:<?= $th['accent'] ?>;margin-bottom:.2rem">Code promo</div>
        <div style="font-size:1.5rem;font-weight:800;font-family:monospace;color:<?= $th['accent'] ?>;letter-spacing:.1em"><?= e($pop_promo) ?></div>
        <button onclick="navigator.clipboard&&navigator.clipboard.writeText(<?= $pop_promo_js ?>).then(function(){this.textContent='✓ Copié !';var b=this;setTimeout(function(){b.textContent='📋 Copier'},1500)}.bind(this))" style="margin-top:.3rem;background:none;border:1px solid <?= $th['accent'] ?>;color:<?= $th['accent'] ?>;padding:.2rem .7rem;border-radius:4px;font-size:.68rem;cursor:pointer">📋 Copier</button>
      </div>
      <?php endif; ?>
      <?php if ($pop_cta && $pop_url): ?>
      <div style="margin-bottom:.8rem">
        <a href="<?= e($pop_url) ?>" onclick="closePop()" style="display:inline-block;background:<?= $th['accent'] ?>;color:#fff;padding:.8rem 2rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:.9rem">
          <?= e($pop_cta) ?>
        </a>
      </div>
      <?php endif; ?>
      <button onclick="closePop()" style="background:none;border:none;color:#bbb;font-size:.78rem;cursor:pointer;text-decoration:underline">Continuer sans code</button>
    </div>
  </div>
</div>
<style>@keyframes popIn{from{opacity:0;transform:scale(.88) translateY(24px)}to{opacity:1;transform:scale(1) translateY(0)}}</style>
<script>
var POP_KEY='<?= $pop_key ?>';
var POP_DELAY=<?= $pop_delay ?>;
var POP_ONCE=<?= $pop_once?'true':'false' ?>;
(function(){
  if(POP_ONCE&&sessionStorage.getItem(POP_KEY))return;
  setTimeout(function(){
    var el=document.getElementById('festivePop');
    if(el)el.style.display='flex';
  },POP_DELAY*1000);
})();
function closePop(){
  var el=document.getElementById('festivePop');
  if(el){el.style.opacity='0';el.style.transition='opacity .25s';setTimeout(function(){el.style.display='none';},260);}
  if(POP_ONCE)sessionStorage.setItem(POP_KEY,'1');
}
var fp=document.getElementById('festivePop');
if(fp)fp.addEventListener('click',function(e){if(e.target===this)closePop();});
</script>
<?php endif; // fin popup ?>

