<?php
define('ROOT', __DIR__);

// Premier accès : si data/.installed absent → installation requise
if (!file_exists(ROOT . '/data/.installed')) {
    header('Location: install.php');
    exit;
}

require_once ROOT . '/includes/config.php';
require_once ROOT . '/includes/captcha.php';
security_headers();

$cfg  = cfg_get();
// Catégories pour index
$cats_all_idx = [];
foreach (db('categories') as $dc) $cats_all_idx[$dc['id']] = $dc;
// Produits mis en avant
$all_prds  = array_values(array_filter(db('products'), fn($p) => $p['active'] ?? true));
$feat_raw  = db('featured');
$feat_on   = !empty($feat_raw['enabled']) && !empty($feat_raw['show_index'] ?? true);
$feat_mode = $feat_raw['mode'] ?? 'manual';
$feat_n    = (int)($feat_raw['count'] ?? 3);
if ($feat_on) {
    if ($feat_mode === 'auto') {
        // Produits les plus vendus
        $sales = [];
        foreach (db('orders') as $o) {
            foreach ($o['cart'] ?? [] as $it) {
                $sales[$it['id'] ?? ''] = ($sales[$it['id'] ?? ''] ?? 0) + ($it['qty'] ?? 1);
            }
        }
        arsort($sales);
        $top_ids = array_slice(array_keys($sales), 0, $feat_n);
        $prds = array_values(array_filter($all_prds, fn($p) => in_array($p['id'], $top_ids)));
        if (!$prds) $prds = array_slice($all_prds, 0, $feat_n);
    } else {
        // Manuel : produits avec featured=true
        $prds = array_values(array_filter($all_prds, fn($p) => !empty($p['featured'])));
        if (!$prds) $prds = array_slice($all_prds, 0, $feat_n);
    }
    $prds = array_slice($prds, 0, $feat_n);
} else {
    $prds = array_slice($all_prds, 0, 3);
}
$arts = array_slice(array_filter(db('articles'), fn($a) => $a['published'] ?? false), 0, 3);
$wks  = array_slice(array_filter(db('workshops'), fn($w) => $w['active'] ?? true), 0, 4);

// Contact form
$ok = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'contact') {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!rate_ok('ct_'.$ip, 3, 600)) {
        $err = 'Trop de messages envoyés. Réessayez dans quelques minutes.';
    } else {
        // Vérifier captcha si activé
        if (!empty($cfg['captcha_contact']) && !empty($cfg['captcha_site_key'])) {
            $cap_res = captcha_verify($cfg, $_POST['g-recaptcha-response'] ?? '');
            if (!$cap_res['ok']) { $err = $cap_res['error']; }
        }
        $name = clean($_POST['name'] ?? '', 100);
        $mail = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $suj  = clean($_POST['sujet'] ?? '', 100);
        $msg  = clean($_POST['message'] ?? '', 2000);
        if (!$err && (!$name || !$mail || !$msg)) {
            $err = 'Merci de remplir tous les champs obligatoires.';
        } else {
            $msgs = db('messages');
            $msgs[] = ['id'=>new_id(),'name'=>$name,'email'=>(string)$mail,'sujet'=>$suj,'message'=>$msg,
                       'ip'=>hash('sha256',$ip),'date'=>date('Y-m-d H:i'),'read'=>false];
            db_save('messages', $msgs);
            if (function_exists('mail_contact_auto')) mail_contact_auto((string)$mail, $name);
            $ok = true;
        }
    }
}

$sn = $cfg['site_name'] ?? 'Café Maison';
// Script captcha dans <head> si activé
include ROOT . '/includes/header.php';
?>

<!----------------------------------------------
  HERO — Immersif, coloré, vivant
----------------------------------------------->
<?php
$anim_speed   = $cfg['hero_anim_speed']     ?? 'normal';
$anim_dur     = ['slow'=>'1.5','normal'=>'1','fast'=>'0.5'][$anim_speed] ?? '1';
$anim_int     = $cfg['hero_anim_intensity'] ?? 'medium';
$orb_scale    = ['low'=>'0.7','medium'=>'1','high'=>'1.35'][$anim_int] ?? '1';
$anim_orbs_on = !isset($cfg['hero_anim_orbs'])     || !empty($cfg['hero_anim_orbs']);
$anim_part_on = !isset($cfg['hero_anim_particles']) || !empty($cfg['hero_anim_particles']);
?>
<?php
$hero_bg_solid = $cfg['hero_bg_solid_color'] ?? '#1A0E08';
$hero_bg_rgb   = hex2rgb($hero_bg_solid);  // réutilise hex2rgb déjà définie plus haut
$hero_bg_style = "background:linear-gradient(145deg, #0C0C0C 0%, $hero_bg_solid 35%, ".
                 "color-mix(in srgb, $hero_bg_solid 70%, #2A1200) 65%, #0C0C0C 100%)";
?>
<section id="hero" class="hero-full"
  style="--hero-anim-speed:<?= $anim_dur ?>;--hero-orb-scale:<?= $orb_scale ?>;<?= $hero_bg_style ?>">

  <!-- Canvas particules -->
  <?php if ($anim_part_on): ?>
  <canvas id="heroCanvas" aria-hidden="true"></canvas>
  <?php endif; ?>

  <!-- Fond en couches colorées -->
  <?php
  $hc1  = $cfg['hero_bg_color1'] ?? '#C8561E';
  $hc2  = $cfg['hero_bg_color2'] ?? '#2A4C1E';
  // Convertir hex en rgb pour rgba()
  function hex2rgb(string $hex): string {
    $hex = ltrim($hex,'#');
    if (strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
  }
  $rgb1 = hex2rgb($hc1);
  $rgb2 = hex2rgb($hc2);
  ?>
  <div class="hero-bg" aria-hidden="true">
    <?php if ($anim_orbs_on): ?>
    <div class="hbg-orb hbg-1" style="background:radial-gradient(circle, rgba(<?= $rgb1 ?>,.55) 0%, rgba(<?= $rgb1 ?>,.2) 50%, transparent 70%)"></div>
    <div class="hbg-orb hbg-2" style="background:radial-gradient(circle, rgba(<?= $rgb2 ?>,.6) 0%, rgba(<?= $rgb2 ?>,.2) 50%, transparent 70%)"></div>
    <div class="hbg-orb hbg-3" style="background:radial-gradient(circle, rgba(<?= $rgb2 ?>,.25) 0%, transparent 70%)"></div>
    <?php endif; // anim_orbs ?>
    <div class="hbg-grain"></div>
    <?php
    // Préparer l'overlay (s'applique avec ET sans image)
    $ov_color = $cfg['hero_overlay_color']    ?? '#0C0C0C';
    $ov_color2= $cfg['hero_overlay_color2']   ?? '#2A1200';
    $ov_op    = (float)($cfg['hero_overlay_opacity']  ?? 0.5);
    $ov_dir   = $cfg['hero_overlay_direction'] ?? '135deg';
    $ov_rgb   = hex2rgb($ov_color);
    $ov_rgb2  = hex2rgb($ov_color2);
    $ov_op2   = max(0, round($ov_op - 0.15, 2));
    $ov_style = !empty($cfg['hero_overlay_gradient'])
        ? "background:linear-gradient($ov_dir, rgba($ov_rgb,$ov_op) 0%, rgba($ov_rgb2,$ov_op2) 100%)"
        : "background:rgba($ov_rgb,$ov_op)";
    ?>
    <?php if (!empty($cfg['hero_bg_image'])): ?>
    <img src="<?= e($cfg['hero_bg_image']) ?>" alt="" aria-hidden="true"
      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.6">
    <?php endif; ?>
    <?php if (!empty($cfg['hero_overlay_enabled'])): ?>
    <div style="position:absolute;inset:0;z-index:1;<?= $ov_style ?>"></div>
    <?php endif; ?>
  </div>

  <div class="wrap hero-inner">

    <!-- Colonne texte -->
    <div class="hero-text">

      <div class="hero-pill">
        <span class="hero-pill-dot"></span>
        <?= e($cfg['hero_pill_text'] ?? 'Torréfacteur artisanal · Paris 11e') ?>
      </div>

      <h1 class="hero-h1">
        <span class="hero-line" style="--i:0"><?= e($cfg['hero_title_line1'] ?? "L'art du") ?></span>
        <span class="hero-line hero-line-em" style="--i:1"><?= e($cfg['hero_title_line2'] ?? 'café,') ?></span>
        <span class="hero-line hero-line-it" style="--i:2"><?= e($cfg['hero_title_line3'] ?? 'cultivé') ?></span>
        <span class="hero-line" style="--i:3"><?= e($cfg['hero_title_line4'] ?? 'avec soin.') ?></span>
      </h1>

      <p class="hero-sub"><?= e($cfg['hero_sub'] ?? 'Sélection directe producteur, torréfaction à la commande, ateliers de dégustation.') ?></p>

      <div class="hero-btns">
        <a href="<?= e($cfg['hero_cta_url'] ?? './pages/shop.php') ?>" class="hbtn hbtn-main">
          <?= e($cfg['hero_cta_text'] ?? 'Découvrir la boutique') ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
        <a href="#about" class="hbtn hbtn-ghost">Notre histoire ↓</a>
      </div>

      <div class="hero-stats">
        <?php foreach([
          [$cfg['stats_years']   ?? '6',    $cfg['stats_label1'] ?? "ans d'expertise"],
          [$cfg['stats_origins'] ?? '18+',   $cfg['stats_label2'] ?? 'origines'],
          [$cfg['stats_clients'] ?? '200+',  $cfg['stats_label3'] ?? 'clients'],
        ] as [$n,$lbl]): ?>
        <div class="hstat">
          <strong><?= e($n) ?></strong>
          <span><?= e($lbl) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Colonne visuelle — carte produit + chips flottantes -->
    <div class="hero-visual">

      <!-- Carte principale tournante au hover -->
      <?php
      $feat_raw2   = db('featured');
      $hero_prd_en = !empty($feat_raw2['hero_product_enabled']);
      $hero_prd    = null;
      if ($hero_prd_en) {
          $hero_pid = $feat_raw2['hero_product_id'] ?? '';
          foreach (db('products') as $hp) {
              if ($hp['id'] === $hero_pid && ($hp['active'] ?? true)) {
                  $hero_prd = $hp; break;
              }
          }
      }
      $hcard_label  = $feat_raw2['hero_label']    ?? 'Sélection du mois';
      $hcard_notes  = $feat_raw2['hero_notes']    ?? '';
      $hcard_btn    = $feat_raw2['hero_btn_text'] ?? '+ Panier';
      if (!$hero_prd) {
          // Fallback: premier produit actif
          foreach (db('products') as $hp) {
              if ($hp['active'] ?? true) { $hero_prd = $hp; break; }
          }
      }
      $hp_price = (float)($hero_prd['price'] ?? 0);
      $hp_tva   = (int)($hero_prd['tva'] ?? ($cfg['tva_default'] ?? 20));
      ?>
      <div class="hero-card" id="heroCard">
        <div class="hero-card-glow"></div>
        <div class="hero-card-top">
          <span class="hc-label">⭐ <?= e($hcard_label) ?></span>
          <span class="hc-origin"><?= e($hero_prd['origin'] ?? '') ?></span>
        </div>
        <div class="hc-emoji"><?= e($hero_prd['emoji'] ?? '☕') ?></div>
        <div class="hc-name"><?= e($hero_prd['name'] ?? '') ?></div>
        <?php if ($hcard_notes): ?>
        <div class="hc-notes"><?= e($hcard_notes) ?></div>
        <?php endif; ?>
        <div class="hc-footer">
          <?php if ($hp_price > 0): ?>
          <span class="hc-price"><?= number_format($hp_price,2,',',' ') ?> €<small><?= ($hero_prd['weight']??'') ? '/'.$hero_prd['weight'] : '' ?></small></span>
          <?php endif; ?>
          <?php if ($ecom && $hp_price > 0): ?>
          <button class="hc-btn btn-add"
            data-id="<?= e($hero_prd['id']??'') ?>"
            data-name="<?= e($hero_prd['name']??'') ?>"
            data-price="<?= $hp_price ?>"
            data-emoji="<?= e($hero_prd['emoji']??'☕') ?>">
            <?= e($hcard_btn) ?>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Chips flottantes colorées -->
      <?php
      // Chips: utiliser les vrais produits actifs (pas les produits mis en avant qui peuvent être vides)
      $all_active = array_values(array_filter(db('products'), fn($p) => $p['active'] ?? true));
      $chip_colors = ['#C8561E','#2A4C1E','#7AAD5C','#8C3A10'];
      $chip_pos    = [
        ['top'=>'8%',  'left'=>'-16%', 'del'=>'0s'],
        ['top'=>'72%', 'left'=>'-20%', 'del'=>'.4s'],
        ['top'=>'6%',  'left'=>'90%',  'del'=>'.2s'],
        ['top'=>'76%', 'left'=>'88%',  'del'=>'.55s'],
      ];
      $chips = [];
      foreach($chip_pos as $ci => $pos) {
          $p = $all_active[$ci] ?? null;
          $chips[] = array_merge($pos, ['color'=>$chip_colors[$ci], 'prd'=>$p]);
      }
      foreach($chips as $ci => $chip):
        $prd = $chip['prd'] ?? [];
      ?>
      <?php if ($prd): ?>
      <div class="hero-chip" style="top:<?= $chip['top'] ?>;left:<?= $chip['left'] ?>;animation-delay:<?= $chip['del'] ?>;--cc:<?= $chip['color'] ?>">
        <span class="chip-ico"><?= e($prd['emoji'] ?? '☕') ?></span>
        <div>
          <strong><?= e(mb_substr($prd['name']??'',0,14,'UTF-8')) ?></strong>
          <?php if (($prd['price']??0) > 0): ?>
          <span><?= number_format((float)($prd['price']??0),2,',',' ') ?> €</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>

      <!-- Indicateur scroll -->
      <div class="hero-scroll" aria-hidden="true">
        <div class="hero-scroll-line"></div>
        <span>Scroll</span>
      </div>

    </div>
  </div>

  <!-- Ticker défilant dynamique -->
  <?php if (!empty($cfg['ticker_enabled'])): ?>
  <?php
    $t1 = $cfg['ticker_color1'] ?? '#C8561E';
    $t2 = $cfg['ticker_color2'] ?? '#2A4C1E';
    $tgrad = !empty($cfg['ticker_gradient']);
    $tbg = $tgrad ? "linear-gradient(90deg,{$t1},{$t2})" : $t1;
    $tscroll = !empty($cfg['ticker_scroll']);
    $ttext = $cfg['ticker_text'] ?? '✦ Café Maison';
    $tparts = array_filter(array_map('trim', preg_split('/(?=✦)/u', $ttext)));
    if (empty($tparts)) $tparts = [$ttext];
    $tall = array_merge($tparts,$tparts,$tparts);
  ?>
  <div class="hero-ticker" style="background:<?= e($tbg) ?>" aria-hidden="true">
    <div class="ticker-track" style="<?= $tscroll ? '' : 'animation:none' ?>">
      <?php foreach ($tall as $s) echo '<span>'.e($s).'</span>'; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<!----------------------------------------------
  ABOUT — Bento coloré
----------------------------------------------->
<section id="about" class="sec">
  <div class="wrap">
    <div class="bento">

      <div class="bento-cell bento-story fade-up">
        <p class="lbl">Notre histoire</p>
        <h2><?= e($sn) ?></h2>
        <p><?= e($cfg['about'] ?? '') ?></p>
        <a href="./pages/atelier.php" class="btn btn-vr" style="margin-top:1.8rem;align-self:flex-start">Nos ateliers →</a>
        <div class="bento-deco" aria-hidden="true"></div>
      </div>

      <div class="bento-cell bento-orange fade-up" data-delay="1">
        <div class="bento-big"><?= e($cfg['stats_years']??'6') ?></div>
        <div class="bento-sub">ans de<br>passion</div>
        <div class="bento-wave" aria-hidden="true"><svg viewBox="0 0 200 60" preserveAspectRatio="none"><path d="M0,30 C40,5 80,55 120,30 C160,5 200,45 200,30 L200,60 L0,60 Z" fill="rgba(255,255,255,.12)"/></svg></div>
      </div>

      <div class="bento-cell bento-green fade-up" data-delay="2">
        <div class="bento-big"><?= e($cfg['stats_origins']??'18+') ?></div>
        <div class="bento-sub">origines<br>sélectionnées</div>
        <div class="bento-globe" aria-hidden="true">🌍</div>
      </div>

      <div class="bento-cell bento-process fade-up" data-delay="1">
        <p class="lbl">Notre process</p>
        <?php foreach([['01','Sélection','Directement chez les producteurs'],['02','Torréfaction','Dans notre atelier parisien'],['03','Expédition','Sous 48h chez vous']] as [$n,$t,$s]): ?>
        <div class="ps">
          <div class="ps-n"><?= $n ?></div>
          <div><strong><?= $t ?></strong><br><span><?= $s ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="bento-cell bento-dark fade-up" data-delay="3">
        <div class="bento-big" style="color:var(--or)"><?= e($cfg['stats_clients']??'200+') ?></div>
        <div class="bento-sub" style="color:rgba(255,255,255,.55)">clients<br>fidèles</div>
        <div class="bento-stars" aria-hidden="true">★★★★★</div>
      </div>

    </div>
  </div>
</section>

<!----------------------------------------------
  BOUTIQUE — Cards colorées
----------------------------------------------->
<section id="shop-prev" class="sec sec-cream">
  <div class="wrap">
    <div class="sec-head tc fade-up">
      <p class="lbl">La boutique</p>
      <h2>Nos cafés <em>du moment</em></h2>
      <span class="line"></span>
    </div>
    <div class="prd-grid" style="margin-top:3rem">
      <?php foreach ($prds as $i => $p):
        $gradients = [
          'linear-gradient(135deg, #C8561E 0%, #E07040 50%, #8C3A10 100%)',
          'linear-gradient(135deg, #2A4C1E 0%, #7AAD5C 60%, #2A4C1E 100%)',
          'linear-gradient(135deg, #0C0C0C 0%, #2E2E2E 50%, #C8561E 100%)',
        ];
        $grad = $gradients[$i % count($gradients)];
      ?>
      <?php
      // Catégorie du produit
      $p_cat_id   = $p['cat_id'] ?? $p['cat'] ?? '';
      $p_cat_name = isset($cats_all_idx[$p_cat_id]) ? $cats_all_idx[$p_cat_id]['name'] : ucfirst($p_cat_id);
      $p_stock    = (int)($p['stock'] ?? 99);
      $p_no_stock = $p_stock <= 0;
      $p_data_id  = e($p['id']);
      $p_data_nm  = e($p['name']);
      $p_data_pr  = e($p['price']);
      $p_data_em  = e($p['emoji'] ?? '☕');
      $p_data_img = e($p['image'] ?? '');
      $p_data_cat = e($p_cat_id);
      $p_data_stk = $p_stock;
      ?>
      <article class="prd-card fade-up" data-delay="<?= $i+1 ?>"
        data-id="<?= $p_data_id ?>" data-name="<?= $p_data_nm ?>"
        data-price="<?= e($p['price']??0) ?>" data-emoji="<?= $p_data_em ?>"
        data-image="<?= $p_data_img ?>" data-cat="<?= $p_data_cat ?>"
        data-stock="<?= $p_data_stk ?>">
        <div class="prd-img" style="background:<?= $grad ?>">
          <?php if (!empty($p['badge'])): ?>
          <span class="prd-badge"><?= e($p['badge']) ?></span>
          <?php elseif (!empty($p['featured'])): ?>
          <span class="prd-badge" style="background:var(--or)">⭐ Sélection</span>
          <?php endif; ?>
          <?php if (!empty($p['image'])): ?>
          <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:inherit" onerror="this.style.display='none'">
          <?php else: ?>
          <span class="prd-emoji"><?= $p['emoji'] ?? '☕' ?></span>
          <?php endif; ?>
          <?php if ($p_no_stock): ?>
          <div style="position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;border-radius:inherit"><span style="color:#fff;font-size:.78rem;font-weight:600;letter-spacing:.08em">ÉPUISÉ</span></div>
          <?php endif; ?>
        </div>
        <div class="prd-body">
          <p class="prd-cat"><?= e($p_cat_name) ?><?= ($p['origin']??'')&&($p['origin']??'')!=='-'?' · '.e($p['origin']):'' ?></p>
          <h3 class="prd-name"><?= e($p['name']) ?></h3>
          <p class="prd-desc"><?= e(mb_substr($p['desc']??'',0,90,'UTF-8')) ?><?= strlen($p['desc']??'')>90?'…':'' ?></p>
          <div class="prd-foot">
            
            <div class="prd-price"><?= number_format((float)($p['price']??0),2,',',' ') ?> €<?= ($p['weight']??'')?'<small> / '.e($p['weight']).'</small>':''  ?></div>
            <?php if ($ecom && !$p_no_stock): ?><button class="btn-add">+ Panier</button><?php elseif($ecom && $p_no_stock): ?><span style="font-size:.72rem;color:var(--gy2)">Épuisé</span><?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="tc fade-up" style="margin-top:3rem">
      <a href="./pages/shop.php" class="btn btn-or">Voir toute la boutique →</a>
    </div>
  </div>
</section>

<!----------------------------------------------
  ATELIERS — Fond sombre + strip colorée
----------------------------------------------->
<section id="atelier-prev" class="sec sec-dark">
  <div class="wrap">
    <div class="sec-head tc fade-up" style="margin-bottom:3rem">
      <p class="lbl" style="justify-content:center">Ateliers</p>
      <h2 style="color:#fff">Apprenez, dégustez,<br><em>créez avec nous</em></h2>
      <span class="line"></span>
    </div>
    <div class="atl-strip">
      <?php
      $pal = ['#C8561E','#2A4C1E','#7AAD5C','#8C3A10'];
      foreach ($wks as $i => $w):
        $col = $pal[$i % count($pal)];
      ?>
      <div class="atl-card-new fade-up" data-delay="<?= $i+1 ?>" style="--ac:<?= $col ?>">
        <div class="atl-card-top">
          <span class="atl-ico-big"><?= $w['emoji'] ?? '☕' ?></span>
          <span class="atl-num"><?= str_pad($i+1,2,'0',STR_PAD_LEFT) ?></span>
        </div>
        <h3><?= e($w['name']) ?></h3>
        <p><?= e(mb_substr($w['desc']??'',0,85,'UTF-8')) ?><?= strlen($w['desc']??'')>85?'…':'' ?></p>
        <div class="atl-meta-row">
          <span>⏱ <?= e($w['duration']??'2h') ?></span>
          <span>👤 <?= e($w['level']??'Tous niveaux') ?></span>
        </div>
        <div class="atl-card-foot">
          <strong><?= number_format((float)($w['price']??0),0,',',' ') ?> €</strong>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="tc fade-up" style="margin-top:2.5rem">
      <?php if (!empty($cfg['atelier_carousel_enabled']??true)): ?>
      <a href="<?= e($cfg['atelier_carousel_link']??'./pages/atelier.php') ?>" class="btn btn-out-or">
        <?= e($cfg['atelier_carousel_link_text']??'Tous les ateliers →') ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!----------------------------------------------
  BLOG
----------------------------------------------->
<?php if ($arts): ?>
<section id="blog-prev" class="sec sec-cream">
  <div class="wrap">
    <div class="tc fade-up" style="margin-bottom:3rem">
      <p class="lbl" style="justify-content:center">Journal</p>
      <h2>Actualités <em>&amp; conseils</em></h2>
      <span class="line"></span>
    </div>
    <div class="blog-grid">
      <?php
      $bgrads = [
        'linear-gradient(135deg,#C8561E,#E07040)',
        'linear-gradient(135deg,#2A4C1E,#7AAD5C)',
        'linear-gradient(135deg,#1A1A1A,#2A4C1E)',
      ];
      foreach ($arts as $i => $a): ?>
      <article class="blog-card fade-up" data-delay="<?= $i+1 ?>">
        <div class="blog-img" style="background:<?= $bgrads[$i%3] ?>">
          <span style="font-size:2.5rem">📖</span>
        </div>
        <div class="blog-body">
          <div class="blog-meta">
            <span class="tag"><?= e($a['cat'] ?? '') ?></span>
            <span><?= e($a['created'] ?? '') ?></span>
          </div>
          <h3 class="blog-title"><?= e($a['title']) ?></h3>
          <p class="blog-exc"><?= e($a['excerpt'] ?? '') ?></p>
          <a href="./pages/article.php?s=<?= e($a['slug']) ?>" class="blog-read">Lire l'article →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="tc fade-up" style="margin-top:3rem">
      <a href="./pages/blog.php" class="btn btn-out">Voir tous les articles</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!----------------------------------------------
  CONTACT
----------------------------------------------->
<section id="contact" class="sec">
  <div class="wrap">
    <div class="tc fade-up" style="margin-bottom:3.5rem">
      <p class="lbl" style="justify-content:center">Contact</p>
      <h2>Venez nous <em>rendre visite</em></h2>
      <span class="line"></span>
    </div>
    <div class="ct-grid">
      <div class="fade-up">
        <h3 style="font-family:var(--f1);font-size:1.75rem;margin-bottom:1rem">Où nous trouver</h3>
        <p style="color:var(--gy);margin-bottom:1.5rem">Notre boutique-atelier vous accueille du mardi au dimanche.</p>
        <div class="ct-items">
          <div class="ct-item"><div class="ct-ico">📍</div><div><div class="ct-ilbl">Adresse</div><div class="ct-ival"><?= e($cfg['address']??'') ?></div></div></div>
          <div class="ct-item"><div class="ct-ico">🕐</div><div><div class="ct-ilbl">Horaires</div><div class="ct-ival"><?= e($cfg['hours_week']??'') ?><br><?= e($cfg['hours_weekend']??'') ?></div></div></div>
          <div class="ct-item"><div class="ct-ico">📞</div><div><div class="ct-ilbl">Téléphone</div><div class="ct-ival"><a href="tel:<?= e($cfg['phone']??'') ?>"><?= e($cfg['phone']??'') ?></a></div></div></div>
          <div class="ct-item"><div class="ct-ico">✉</div><div><div class="ct-ilbl">Email</div><div class="ct-ival"><a href="mailto:<?= e($cfg['email']??'') ?>"><?= e($cfg['email']??'') ?></a></div></div></div>
        </div>
        <div class="map-box">
          <?php
          // Carte toujours générée depuis l'adresse actuelle (jamais en cache statique)
          $map_address = $cfg['address'] ?? '';
          if (!empty($cfg['map_embed'])): ?>
            <?= $cfg['map_embed'] ?>
          <?php elseif ($map_address): ?>
          <iframe
            src="https://maps.google.com/maps?q=<?= urlencode($map_address) ?>&output=embed&z=16&hl=fr"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Carte <?= e($map_address) ?>"
            style="width:100%;height:100%;border:0">
          </iframe>
          <?php else: ?>
          <div style="display:flex;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,.4);font-size:.85rem">
            Adresse à configurer dans les paramètres
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="fade-up" data-delay="2">
        <h3 style="font-family:var(--f1);font-size:1.75rem;margin-bottom:.4rem">Écrivez-nous</h3>
        <p style="margin-bottom:1.8rem;font-size:.9rem;color:var(--gy)">Question, réservation, commande spéciale — on répond sous 24h.</p>
        <?php if ($ok): ?><div class="alert-ok">✓ Message envoyé ! Nous vous répondons sous 24h.</div><?php elseif ($err): ?><div class="alert-err">✗ <?= e($err) ?></div><?php endif; ?>
        <form method="POST" action="#contact" id="contactForm">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="contact">
          <div class="form-2">
            <div class="form-g"><label class="form-l">Nom *</label><input class="form-i" name="name" required maxlength="100" value="<?= e($_POST['name']??'') ?>" placeholder="Votre nom"></div>
            <div class="form-g"><label class="form-l">Email *</label><input class="form-i" type="email" name="email" required maxlength="150" value="<?= e($_POST['email']??'') ?>" placeholder="vous@mail.fr"></div>
          </div>
          <div class="form-g">
            <label class="form-l">Sujet</label>
            <select class="form-sel" name="sujet" id="sujet">
              <option>Question générale</option><option>Réservation atelier</option>
              <option>Commande spéciale</option><option>Partenariat / B2B</option><option>Autre</option>
            </select>
          </div>
          <div class="form-g"><label class="form-l">Message *</label><textarea class="form-ta" name="message" required maxlength="2000" placeholder="Votre message…"><?= e($_POST['message']??'') ?></textarea></div>
          <button type="submit" class="btn btn-or" style="width:100%;justify-content:center">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Envoyer le message
          </button>
          <?php if (!empty($cfg['captcha_contact']) && !empty($cfg['captcha_site_key'])): ?>
          <?= captcha_hidden_field() ?>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</section>
<?php if (!empty($cfg['captcha_contact']) && !empty($cfg['captcha_site_key'])): ?>
<?= captcha_js_handler($cfg['captcha_site_key'], 'contactForm', 'contact') ?>
<?php endif; ?>
<?php include ROOT . '/includes/footer.php'; ?>
