<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg  = cfg_get();
$errs = [];
$ok   = false;
$tab  = clean($_GET['t'] ?? 'home', 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = clean($_POST['tab'] ?? 'home', 20);

    if ($t === 'home') {
        $cfg['hero_pill_text']   = clean($_POST['hero_pill_text']   ?? '', 150);
        $cfg['hero_title_line1'] = clean($_POST['hero_title_line1'] ?? "L'art du", 100);
        $cfg['hero_title_line2'] = clean($_POST['hero_title_line2'] ?? 'café,', 100);
        $cfg['hero_title_line3'] = clean($_POST['hero_title_line3'] ?? 'cultivé', 100);
        $cfg['hero_title_line4'] = clean($_POST['hero_title_line4'] ?? 'avec soin.', 100);
        $cfg['hero_sub']         = clean($_POST['hero_sub']         ?? '', 600);
        $cfg['hero_bg_color1']        = clean($_POST['hero_bg_color1']        ?? '#C8561E', 20);
        $cfg['hero_bg_color2']        = clean($_POST['hero_bg_color2']        ?? '#2A4C1E', 20);
        $cfg['hero_bg_solid_color']   = clean($_POST['hero_bg_solid_color']   ?? '#1A0E08', 20);
        $cfg['hero_bg_image']         = clean($_POST['hero_bg_image']         ?? '', 500);
        $cfg['hero_overlay_enabled']  = isset($_POST['hero_overlay_enabled']);
        $cfg['hero_overlay_color']    = clean($_POST['hero_overlay_color']    ?? '#0C0C0C', 20);
        $cfg['hero_overlay_color2']   = clean($_POST['hero_overlay_color2']   ?? '#2A1200', 20);
        $cfg['hero_overlay_opacity']  = min(1.0, max(0, (float)($_POST['hero_overlay_opacity'] ?? 0.5)));
        $cfg['hero_overlay_gradient'] = !empty($_POST['hero_overlay_gradient']);
        $cfg['hero_overlay_direction']= clean($_POST['hero_overlay_direction'] ?? '135deg', 10);
        $cfg['hero_anim_orbs']        = isset($_POST['hero_anim_orbs']);
        $cfg['hero_anim_particles']   = isset($_POST['hero_anim_particles']);
        $cfg['hero_anim_speed']       = in_array($_POST['hero_anim_speed']??'normal',['slow','normal','fast'],true) ? $_POST['hero_anim_speed'] : 'normal';
        $cfg['hero_anim_intensity']   = in_array($_POST['hero_anim_intensity']??'medium',['low','medium','high'],true) ? $_POST['hero_anim_intensity'] : 'medium';
        $cfg['hero_cta_text']    = clean($_POST['hero_cta_text']    ?? 'Découvrir la boutique', 80);
        $cfg['hero_cta_url']     = clean($_POST['hero_cta_url']     ?? './pages/shop.php', 200);
        $cfg['about']            = clean($_POST['about']            ?? '', 2000);
        $cfg['stats_years']      = clean($_POST['stats_years']      ?? '6', 10);
        $cfg['stats_origins']    = clean($_POST['stats_origins']    ?? '18+', 10);
        $cfg['stats_clients']    = clean($_POST['stats_clients']    ?? '200+', 10);
        $cfg['stats_label1']     = clean($_POST['stats_label1']     ?? "ans d'expertise", 40);
        $cfg['stats_label2']     = clean($_POST['stats_label2']     ?? 'origines', 40);
        $cfg['stats_label3']     = clean($_POST['stats_label3']     ?? 'clients', 40);
        $cfg['about_badge_n']    = clean($_POST['about_badge_n']    ?? '6', 10);
        $cfg['about_badge_l']    = clean($_POST['about_badge_l']    ?? 'ans de passion', 40);
    }
    if ($t === 'ticker') {
        $cfg['ticker_enabled']  = isset($_POST['ticker_enabled']);
        $cfg['ticker_scroll']   = isset($_POST['ticker_scroll']);
        $cfg['ticker_text']     = clean($_POST['ticker_text'] ?? '', 500);
        $cfg['ticker_color1']   = clean($_POST['ticker_color1'] ?? '#C8561E', 20);
        $cfg['ticker_color2']   = clean($_POST['ticker_color2'] ?? '#2A4C1E', 20);
        $cfg['ticker_gradient'] = isset($_POST['ticker_gradient']);
    }
    if ($t === 'nav') {
        $cfg['nav_label_home']    = clean($_POST['nav_label_home']    ?? 'La Maison', 60);
        $cfg['nav_label_shop']    = clean($_POST['nav_label_shop']    ?? 'Boutique', 60);
        $cfg['nav_label_atelier'] = clean($_POST['nav_label_atelier'] ?? 'Atelier', 60);
        $cfg['nav_label_blog']    = clean($_POST['nav_label_blog']    ?? 'Journal', 60);
        $cfg['nav_label_contact'] = clean($_POST['nav_label_contact'] ?? 'Contact', 60);
        $cfg['nav_label_giftcard']= clean($_POST['nav_label_giftcard']?? '🎁', 60);
        $cfg['nav_show_home']     = isset($_POST['nav_show_home']);
        $cfg['nav_show_shop']     = isset($_POST['nav_show_shop']);
        $cfg['nav_show_atelier']  = isset($_POST['nav_show_atelier']);
        $cfg['nav_show_blog']     = isset($_POST['nav_show_blog']);
        $cfg['nav_show_giftcard'] = isset($_POST['nav_show_giftcard']);
        $cfg['nav_show_contact']  = isset($_POST['nav_show_contact']);
    }
    if ($t === 'shop') {
        $cfg['page_shop_title'] = clean($_POST['page_shop_title'] ?? '', 150);
        $cfg['page_shop_desc']  = clean($_POST['page_shop_desc']  ?? '', 500);
    }
    if ($t === 'atelier') {
        $cfg['page_atelier_title']  = clean($_POST['page_atelier_title']  ?? '', 150);
        $cfg['page_atelier_desc']   = clean($_POST['page_atelier_desc']   ?? '', 500);
        $cfg['no_session_msg']      = clean($_POST['no_session_msg']      ?? '', 300);
    }
    if ($t === 'blog') {
        $cfg['page_blog_title'] = clean($_POST['page_blog_title'] ?? '', 150);
        $cfg['page_blog_desc']  = clean($_POST['page_blog_desc']  ?? '', 500);
    }
    if ($t === 'story') {
        $cfg['page_story_title']   = clean($_POST['page_story_title'] ?? 'Notre Histoire', 150);
        $cfg['page_story_enabled'] = isset($_POST['page_story_enabled']);
        $cfg['story_hero_img']     = clean($_POST['story_hero_img']   ?? '', 500);
        $cfg['story_subtitle']     = clean($_POST['story_subtitle']   ?? '', 300);
        $cfg['story_text1_title']  = clean($_POST['story_text1_title']?? '', 120);
        $cfg['story_text1']        = clean($_POST['story_text1']      ?? '', 2000);
        $cfg['story_text2_title']  = clean($_POST['story_text2_title']?? '', 120);
        $cfg['story_text2']        = clean($_POST['story_text2']      ?? '', 2000);
        $cfg['story_text3_title']  = clean($_POST['story_text3_title']?? '', 120);
        $cfg['story_text3']        = clean($_POST['story_text3']      ?? '', 2000);
        $cfg['story_img2']         = clean($_POST['story_img2']       ?? '', 500);
        $cfg['story_quote']        = clean($_POST['story_quote']      ?? '', 300);
    }
    if (in_array($t, ['legal','cgv','shipping','faq','info'])) {
        $cfg['page_'.$t.'_title']   = clean($_POST['page_title']   ?? '', 150);
        $cfg['page_'.$t.'_content'] = $_POST['page_content']        ?? '';
    }
    if ($t === 'footer') {
        $cfg['footer_show_legal']    = isset($_POST['footer_show_legal']);
        $cfg['footer_show_cgv']      = isset($_POST['footer_show_cgv']);
        $cfg['footer_show_shipping'] = isset($_POST['footer_show_shipping']);
        $cfg['footer_show_faq']      = isset($_POST['footer_show_faq']);
        $cfg['footer_show_story']    = isset($_POST['footer_show_story']);
        $cfg['footer_show_giftcard'] = isset($_POST['footer_show_giftcard']);
        $cfg['footer_show_compare']  = isset($_POST['footer_show_compare']);
        $cfg['cookies_enabled']      = isset($_POST['cookies_enabled']);
        $cfg['cookies_text']         = clean($_POST['cookies_text'] ?? '', 400);
        $custom = [];
        foreach (explode("\n", $_POST['custom_links'] ?? '') as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) $custom[] = ['label'=>clean($parts[0],80),'url'=>clean($parts[1],200)];
        }
        $cfg['footer_custom_links'] = json_encode($custom);
    }
    if ($t === 'popup') {
        $cfg['popup_enabled'] = isset($_POST['popup_enabled']);
        $cfg['popup_type']    = clean($_POST['popup_type']    ?? 'custom', 20);
        $cfg['popup_title']   = clean($_POST['popup_title']   ?? '', 150);
        $cfg['popup_message'] = clean($_POST['popup_message'] ?? '', 500);
        $cfg['popup_promo']   = clean($_POST['popup_promo']   ?? '', 40);
        $cfg['popup_image']   = clean($_POST['popup_image']   ?? '', 500);
        $cfg['popup_cta_text']= clean($_POST['popup_cta_text']?? '', 100);
        $cfg['popup_cta_url'] = clean($_POST['popup_cta_url'] ?? '', 300);
        $cfg['popup_delay']   = clean_int($_POST['popup_delay'] ?? 2, 0, 30);
        $cfg['popup_once']    = isset($_POST['popup_once']);
    }

    // Utiliser cfg_patch() pour éviter le double chiffrement des clés API
    // (cfg_get déchiffre, cfg_save rechiffre = double chiffrement)
    cfg_patch($cfg);
    $cfg = cfg_get();
    $ok  = true;
    $tab = $t;
    admin_log('pages_save', "Onglet '$t' mis à jour");
}

$tabs = [
    'home'    => ['🏠', 'Accueil'],
    'nav'     => ['🧭', 'Navigation'],
    'popup'   => ['🎉', 'Popup annonce'],
    'ticker'  => ['📢', 'Bandeau ticker'],
    'shop'    => ['🛒', 'Boutique'],
    'atelier' => ['🎓', 'Ateliers'],
    'blog'    => ['📝', 'Journal'],
    'story'   => ['📖', 'Notre Histoire'],
    'footer'  => ['🔗', 'Footer'],
    'legal'   => ['⚖️',  'Mentions légales'],
    'cgv'     => ['📋', 'CGV'],
    'shipping'=> ['🚚', 'Livraison'],
    'faq'     => ['❓', 'FAQ'],
    'info'    => ['ℹ️',  'Informations'],
];

$at = 'Pages'; $cur_adm = 'pages';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div><h2>Modification des pages</h2><p>Contenus éditoriaux et apparence du site</p></div>
  <div class="adm-acts"><a href="../" target="_blank" class="a-btn">👁 Voir le site</a></div>
</div>
<div class="adm-content">
  <?php if ($ok): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré !</div><?php endif; ?>

  <!-- Onglets -->
  <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:1.8rem">
    <?php foreach ($tabs as $k => $tab_info): ?>
    <a href="?t=<?= $k ?>" class="flt <?= $tab === $k ? 'on' : '' ?>"><?= $tab_info[0] ?> <?= $tab_info[1] ?></a>
    <?php endforeach; ?>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">

<?php if ($tab === 'home'): ?>
<!-- ═══ ACCUEIL ══════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🦸 Hero — Grande bannière</h4>
      <div class="frm-g"><label class="frm-l">Badge (petite pilule en haut)</label>
        <input class="frm-i" name="hero_pill_text" maxlength="150" value="<?= e($cfg['hero_pill_text'] ?? 'Torréfacteur artisanal · Paris 11e') ?>"></div>
      <div style="background:rgba(200,86,30,.05);border:1px solid rgba(200,86,30,.15);border-radius:var(--r);padding:1rem;margin-bottom:1rem">
        <p class="frm-l" style="margin-bottom:.6rem">Titre principal — 4 lignes animées</p>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l" style="font-size:.65rem">Ligne 1</label><input class="frm-i" name="hero_title_line1" maxlength="100" value="<?= e($cfg['hero_title_line1'] ?? "L'art du") ?>"></div>
          <div class="frm-g"><label class="frm-l" style="font-size:.65rem">Ligne 2 (orange)</label><input class="frm-i" name="hero_title_line2" maxlength="100" value="<?= e($cfg['hero_title_line2'] ?? 'café,') ?>"></div>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l" style="font-size:.65rem">Ligne 3 (italique)</label><input class="frm-i" name="hero_title_line3" maxlength="100" value="<?= e($cfg['hero_title_line3'] ?? 'cultivé') ?>"></div>
          <div class="frm-g"><label class="frm-l" style="font-size:.65rem">Ligne 4</label><input class="frm-i" name="hero_title_line4" maxlength="100" value="<?= e($cfg['hero_title_line4'] ?? 'avec soin.') ?>"></div>
        </div>
      </div>
      <div class="frm-g"><label class="frm-l">Sous-titre</label>
        <textarea class="frm-i" name="hero_sub" rows="3" style="resize:vertical"><?= e($cfg['hero_sub'] ?? '') ?></textarea></div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Bouton — Texte</label><input class="frm-i" name="hero_cta_text" maxlength="80" value="<?= e($cfg['hero_cta_text'] ?? 'Découvrir la boutique') ?>"></div>
        <div class="frm-g"><label class="frm-l">Bouton — Lien</label><input class="frm-i" name="hero_cta_url" maxlength="200" value="<?= e($cfg['hero_cta_url'] ?? './pages/shop.php') ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Couleur orbe 1</label>
          <div style="display:flex;gap:.4rem;align-items:center">
            <input type="color" name="hero_bg_color1" id="c1p" value="<?= e($cfg['hero_bg_color1'] ?? '#C8561E') ?>"
              oninput="document.getElementById('c1t').value=this.value"
              style="width:40px;height:36px;border:none;border-radius:4px;cursor:pointer">
        <input class="frm-i" id="c1t" maxlength="20"
              value="<?= e($cfg['hero_bg_color1'] ?? '#C8561E') ?>" style="font-family:monospace;width:90px"
              oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/))document.getElementById('c1p').value=this.value">
          </div></div>
        <div class="frm-g"><label class="frm-l">Couleur orbe 2</label>
          <div style="display:flex;gap:.4rem;align-items:center">
            <input type="color" name="hero_bg_color2" id="c2p" value="<?= e($cfg['hero_bg_color2'] ?? '#2A4C1E') ?>"
              oninput="document.getElementById('c2t').value=this.value"
              style="width:40px;height:36px;border:none;border-radius:4px;cursor:pointer">
        <input class="frm-i" id="c2t" maxlength="20"
              value="<?= e($cfg['hero_bg_color2'] ?? '#2A4C1E') ?>" style="font-family:monospace;width:90px"
              oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/))document.getElementById('c2p').value=this.value">
          </div></div>
      </div>

      <!-- COULEUR DE FOND (sans image) -->
      <div class="frm-g" style="margin-top:.5rem">
        <label class="frm-l">Couleur de fond <small style="color:var(--gy2)">(quand pas d'image)</small></label>
        <div style="display:flex;gap:.4rem;align-items:center">
          <input type="color" id="bgSolidP" value="<?= e($cfg['hero_bg_solid_color'] ?? '#1A0E08') ?>"
            oninput="document.getElementById('bgSolidT').value=this.value"
            style="width:40px;height:36px;border:none;border-radius:4px;cursor:pointer">
          <input class="frm-i" id="bgSolidT" name="hero_bg_solid_color" maxlength="20"
            value="<?= e($cfg['hero_bg_solid_color'] ?? '#1A0E08') ?>" style="font-family:monospace;width:90px"
            oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/))document.getElementById('bgSolidP').value=this.value">
          <small style="color:var(--gy2)">← Teinte principale du dégradé de fond</small>
        </div>
      </div>

      <!-- IMAGE DE FOND -->
      <div class="frm-g" style="margin-top:.5rem">
        <label class="frm-l">Image de fond Hero <small style="color:var(--gy2)">(optionnel, remplace les orbes)</small></label>
        <img id="prev_hero_bg_image" src="<?= e($cfg['hero_bg_image'] ?? '') ?>"
          style="max-height:80px;border-radius:6px;display:<?= !empty($cfg['hero_bg_image'])?'block':'none' ?>;margin-bottom:.5rem">
        <input type="hidden" id="fld_hero_bg_image" name="hero_bg_image" value="<?= e($cfg['hero_bg_image'] ?? '') ?>">
        <div class="img-uploader" data-field="hero_bg_image" data-preview="prev_hero_bg_image"></div>
        <?php if (!empty($cfg['hero_bg_image'])): ?>
        <button type="button" class="act-btn del" style="margin-top:.3rem;font-size:.72rem"
          onclick="document.getElementById('fld_hero_bg_image').value='';document.getElementById('prev_hero_bg_image').style.display='none';this.style.display='none'">
          ✕ Supprimer l'image
        </button>
        <?php endif; ?>
      </div>

      <!-- OVERLAY -->
      <div style="background:var(--cr);border-radius:var(--r);padding:.8rem 1rem;margin-top:.7rem">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem">
          <label class="tog"><input type="checkbox" name="hero_overlay_enabled" <?= !empty($cfg['hero_overlay_enabled'] ?? true) ? 'checked' : '' ?>><span class="tog-sl"></span></label>
          <strong style="font-size:.82rem">🎨 Overlay (par-dessus le fond)</strong>
        </div>
        <div class="frm-row" style="margin-bottom:.5rem">
          <div class="frm-g">
            <label class="frm-l">Couleur 1 <small style="color:var(--gy2)">(début dégradé)</small></label>
            <div style="display:flex;gap:.4rem;align-items:center">
              <input type="color" id="ovP1" value="<?= e($cfg['hero_overlay_color'] ?? '#0C0C0C') ?>"
                oninput="document.getElementById('ovT1').value=this.value"
                style="width:36px;height:32px;border:none;border-radius:4px;cursor:pointer">
              <input class="frm-i" name="hero_overlay_color" id="ovT1" maxlength="20"
                value="<?= e($cfg['hero_overlay_color'] ?? '#0C0C0C') ?>" style="font-family:monospace;width:90px"
                oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/))document.getElementById('ovP1').value=this.value">
            </div>
          </div>
          <div class="frm-g">
            <label class="frm-l">Couleur 2 <small style="color:var(--gy2)">(fin dégradé)</small></label>
            <div style="display:flex;gap:.4rem;align-items:center">
              <input type="color" id="ovP2" value="<?= e($cfg['hero_overlay_color2'] ?? '#2A1200') ?>"
                oninput="document.getElementById('ovT2').value=this.value"
                style="width:36px;height:32px;border:none;border-radius:4px;cursor:pointer">
              <input class="frm-i" name="hero_overlay_color2" id="ovT2" maxlength="20"
                value="<?= e($cfg['hero_overlay_color2'] ?? '#2A1200') ?>" style="font-family:monospace;width:90px"
                oninput="if(this.value.match(/^#[0-9a-fA-F]{6}$/))document.getElementById('ovP2').value=this.value">
            </div>
          </div>
        </div>
        <div class="frm-row" style="margin-bottom:.5rem">
          <div class="frm-g">
            <label class="frm-l">Opacité (0=transparent · 1=opaque)</label>
            <input class="frm-i" type="number" name="hero_overlay_opacity" min="0" max="1" step="0.05"
              value="<?= e($cfg['hero_overlay_opacity'] ?? 0.5) ?>" style="max-width:80px">
          </div>
        </div>
        <div class="frm-row" style="margin-bottom:0">
          <div class="frm-g">
            <label class="frm-l">Type</label>
            <select name="hero_overlay_gradient" class="frm-sel">
              <option value="1" <?= !empty($cfg['hero_overlay_gradient'] ?? true) ? 'selected' : '' ?>>Dégradé</option>
              <option value="0" <?= empty($cfg['hero_overlay_gradient'] ?? true) ? 'selected' : '' ?>>Couleur unie</option>
            </select>
          </div>
          <div class="frm-g">
            <label class="frm-l">Direction du dégradé</label>
            <select name="hero_overlay_direction" class="frm-sel">
              <?php foreach (['135deg'=>'↗ Diagonal','180deg'=>'↓ Bas','90deg'=>'→ Droite','0deg'=>'↑ Haut','45deg'=>'↗ 45°','225deg'=>'↙ Diagonal inv.'] as $dv => $dl): ?>
              <option value="<?= $dv ?>" <?= ($cfg['hero_overlay_direction'] ?? '135deg') === $dv ? 'selected' : '' ?>><?= $dl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- ANIMATIONS -->
      <div style="background:var(--cr);border-radius:var(--r);padding:.8rem 1rem;margin-top:.7rem">
        <strong style="font-size:.82rem;display:block;margin-bottom:.6rem">✨ Animations</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.8rem;margin-bottom:.6rem">
          <label class="tog-wrap" style="margin-bottom:0">
            <label class="tog"><input type="checkbox" name="hero_anim_orbs" <?= !isset($cfg['hero_anim_orbs']) || !empty($cfg['hero_anim_orbs']) ? 'checked' : '' ?>><span class="tog-sl"></span></label>
            <span class="tog-lbl" style="font-size:.8rem">Orbes flottants</span>
          </label>
          <label class="tog-wrap" style="margin-bottom:0">
            <label class="tog"><input type="checkbox" name="hero_anim_particles" <?= !isset($cfg['hero_anim_particles']) || !empty($cfg['hero_anim_particles']) ? 'checked' : '' ?>><span class="tog-sl"></span></label>
            <span class="tog-lbl" style="font-size:.8rem">Particules</span>
          </label>
        </div>
        <div class="frm-row" style="margin-bottom:0">
          <div class="frm-g">
            <label class="frm-l">Vitesse animation</label>
            <select name="hero_anim_speed" class="frm-sel">
              <option value="slow"   <?= ($cfg['hero_anim_speed'] ?? 'normal') === 'slow'   ? 'selected' : '' ?>>🐢 Lente</option>
              <option value="normal" <?= ($cfg['hero_anim_speed'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>⚡ Normale</option>
              <option value="fast"   <?= ($cfg['hero_anim_speed'] ?? 'normal') === 'fast'   ? 'selected' : '' ?>>🚀 Rapide</option>
            </select>
          </div>
          <div class="frm-g">
            <label class="frm-l">Intensité animation</label>
            <select name="hero_anim_intensity" class="frm-sel">
              <option value="low"    <?= ($cfg['hero_anim_intensity'] ?? 'medium') === 'low'    ? 'selected' : '' ?>>🔅 Faible</option>
              <option value="medium" <?= ($cfg['hero_anim_intensity'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>☀️ Moyenne</option>
              <option value="high"   <?= ($cfg['hero_anim_intensity'] ?? 'medium') === 'high'   ? 'selected' : '' ?>>🔆 Forte</option>
            </select>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📊 Statistiques</h4>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Valeur 1</label><input class="frm-i" name="stats_years" maxlength="10" value="<?= e($cfg['stats_years']??'6') ?>"></div>
        <div class="frm-g"><label class="frm-l">Label 1</label><input class="frm-i" name="stats_label1" maxlength="40" value="<?= e($cfg['stats_label1']??"ans d'expertise") ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Valeur 2</label><input class="frm-i" name="stats_origins" maxlength="10" value="<?= e($cfg['stats_origins']??'18+') ?>"></div>
        <div class="frm-g"><label class="frm-l">Label 2</label><input class="frm-i" name="stats_label2" maxlength="40" value="<?= e($cfg['stats_label2']??'origines') ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Valeur 3</label><input class="frm-i" name="stats_clients" maxlength="10" value="<?= e($cfg['stats_clients']??'200+') ?>"></div>
        <div class="frm-g"><label class="frm-l">Label 3</label><input class="frm-i" name="stats_label3" maxlength="40" value="<?= e($cfg['stats_label3']??'clients') ?>"></div>
      </div>
    </div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.8rem">📖 Section À propos</h4>
      <div class="frm-g"><label class="frm-l">Texte de présentation</label>
        <textarea class="frm-i" name="about" rows="6" style="resize:vertical"><?= e($cfg['about']??'') ?></textarea></div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Badge chiffre</label><input class="frm-i" name="about_badge_n" maxlength="10" value="<?= e($cfg['about_badge_n']??'6') ?>"></div>
        <div class="frm-g"><label class="frm-l">Badge label</label><input class="frm-i" name="about_badge_l" maxlength="40" value="<?= e($cfg['about_badge_l']??'ans de passion') ?>"></div>
      </div>
    </div>
    <button type="submit" class="save-btn">💾 Enregistrer l'accueil</button>
  </div>
</div>

<?php elseif ($tab === 'nav'): ?>
<!-- ═══ NAVIGATION ══════════════════════════════════════════════════════ -->
<div class="editor-wrap" style="max-width:680px">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.5rem">🧭 Barre de navigation</h4>
  <p style="font-size:.82rem;color:var(--gy2);margin-bottom:1.2rem">
    Le <strong>toggle</strong> (bouton rond) active ou désactive chaque lien.<br>
    Le <strong>champ texte</strong> permet de renommer le lien comme vous le souhaitez.
  </p>
  <?php
  $nav_items = [
    'nav_show_home'     => ['nav_label_home',    'La Maison',  '→ Ancre #about sur la page d\'accueil'],
    'nav_show_shop'     => ['nav_label_shop',    'Boutique',   '→ Page boutique'],
    'nav_show_atelier'  => ['nav_label_atelier', 'Atelier',    '→ Page ateliers'],
    'nav_show_blog'     => ['nav_label_blog',    'Journal',    '→ Page blog / journal'],
    'nav_show_giftcard' => ['nav_label_giftcard','🎁',         '→ Page cartes cadeaux'],
    'nav_show_contact'  => ['nav_label_contact', 'Contact',    '→ Ancre #contact sur la page d\'accueil'],
  ];
  foreach ($nav_items as $show_key => [$label_key, $default, $hint]):
  ?>
  <div style="display:flex;align-items:center;gap:1rem;padding:.8rem 0;border-bottom:1px solid var(--cr)">
    <label class="tog" style="flex-shrink:0">
      <input type="checkbox" name="<?= $show_key ?>" <?= !empty($cfg[$show_key]??true)?'checked':'' ?>>
      <span class="tog-sl"></span>
    </label>
    <div style="flex:1">
      <input class="frm-i" name="<?= $label_key ?>" maxlength="60"
        value="<?= e($cfg[$label_key] ?? $default) ?>" placeholder="<?= $default ?>">
      <div style="font-size:.68rem;color:var(--gy2);margin-top:.2rem"><?= $hint ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <button type="submit" class="save-btn" style="margin-top:1rem">💾 Enregistrer</button>
</div>

<?php elseif ($tab === 'popup'): ?>
<!-- ═══ POPUP ANNONCE ══════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.5rem">🎉 Popup d'annonce</h4>
      <p style="font-size:.82rem;color:var(--gy2);margin-bottom:1rem">
        Ce popup apparaît automatiquement quand un visiteur arrive sur le site.
        Parfait pour annoncer un code promo, un événement ou une nouveauté.
      </p>
      <div class="tog-wrap" style="margin-bottom:1rem">
        <label class="tog"><input type="checkbox" name="popup_enabled" <?= !empty($cfg['popup_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label>
        <span class="tog-lbl" style="font-weight:600">Activer le popup (visible sur le site)</span>
      </div>
      <div class="tog-wrap" style="margin-bottom:1rem">
        <label class="tog"><input type="checkbox" name="popup_once" <?= !empty($cfg['popup_once']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
        <span class="tog-lbl">Afficher une seule fois par session (recommandé)</span>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Thème visuel</label>
          <select class="frm-sel" name="popup_type" onchange="updatePreview()">
            <option value="custom"    <?= ($cfg['popup_type']??'custom')==='custom'   ?'selected':'' ?>>✏️ Personnalisé</option>
            <option value="valentine" <?= ($cfg['popup_type']??'')==='valentine'?'selected':'' ?>>💝 Saint-Valentin</option>
            <option value="halloween" <?= ($cfg['popup_type']??'')==='halloween'?'selected':'' ?>>🎃 Halloween</option>
            <option value="christmas" <?= ($cfg['popup_type']??'')==='christmas'?'selected':'' ?>>🎄 Noël</option>
            <option value="easter"    <?= ($cfg['popup_type']??'')==='easter'   ?'selected':'' ?>>🐣 Pâques</option>
            <option value="summer"    <?= ($cfg['popup_type']??'')==='summer'   ?'selected':'' ?>>☀️ Été</option>
            <option value="epiphanie" <?= ($cfg['popup_type']??'')==='epiphanie'?'selected':'' ?>>👑 Épiphanie</option>
          </select></div>
        <div class="frm-g"><label class="frm-l">Délai avant affichage (secondes)</label>
          <input class="frm-i" type="number" name="popup_delay" min="0" max="30" value="<?= e($cfg['popup_delay']??2) ?>"></div>
      </div>
      <div class="frm-g"><label class="frm-l">Titre du popup</label>
        <input class="frm-i" name="popup_title" maxlength="150" value="<?= e($cfg['popup_title']??'') ?>" placeholder="Ex: 🌹 Offre Saint-Valentin !"></div>
      <div class="frm-g"><label class="frm-l">Message</label>
        <textarea class="frm-i" name="popup_message" rows="4" maxlength="500" placeholder="Ex: Profitez de -15% sur toute la boutique jusqu'au 14 février…"><?= e($cfg['popup_message']??'') ?></textarea></div>
      <div class="frm-g"><label class="frm-l">Code promo à afficher <small style="color:var(--gy2)">(le visiteur peut le copier d'un clic)</small></label>
        <input class="frm-i" name="popup_promo" maxlength="40" value="<?= e($cfg['popup_promo']??'') ?>" placeholder="Ex: VALENTIN15" style="font-family:monospace;font-size:1rem;font-weight:700;text-transform:uppercase"></div>
      <div class="frm-g"><label class="frm-l">Image (URL) — optionnel</label>
        <input class="frm-i" name="popup_image" maxlength="500" value="<?= e($cfg['popup_image']??'') ?>" placeholder="https://... ou laisser vide"></div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Texte du bouton</label>
          <input class="frm-i" name="popup_cta_text" maxlength="100" value="<?= e($cfg['popup_cta_text']??'') ?>" placeholder="Ex: Voir les offres →"></div>
        <div class="frm-g"><label class="frm-l">Lien du bouton</label>
          <input class="frm-i" name="popup_cta_url" maxlength="300" value="<?= e($cfg['popup_cta_url']??'') ?>" placeholder="./pages/shop.php"></div>
      </div>
      <button type="submit" class="save-btn">💾 Enregistrer le popup</button>
    </div>
  </div>
  <!-- Prévisualisation -->
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.8rem">👁 Prévisualisation</h4>
      <?php
      $themes_prev = [
        'valentine' => ['bg'=>'linear-gradient(135deg,#ff6b8a,#c41e5c)', 'emoji'=>'💝'],
        'halloween' => ['bg'=>'linear-gradient(135deg,#1a0a00,#e65c00)', 'emoji'=>'🎃'],
        'christmas' => ['bg'=>'linear-gradient(135deg,#0d2e0d,#b71c1c)',  'emoji'=>'🎄'],
        'easter'    => ['bg'=>'linear-gradient(135deg,#6a1b9a,#f9a825)',  'emoji'=>'🐣'],
        'summer'    => ['bg'=>'linear-gradient(135deg,#01579b,#e65100)',  'emoji'=>'☀️'],
        'epiphanie' => ['bg'=>'linear-gradient(135deg,#3e2723,#f9a825)',  'emoji'=>'👑'],
        'custom'    => ['bg'=>'linear-gradient(135deg,#C8561E,#2A4C1E)',  'emoji'=>'☕'],
      ];
      $cur_theme = $themes_prev[$cfg['popup_type']??'custom'] ?? $themes_prev['custom'];
      ?>
      <div style="border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.15)">
        <div style="background:<?= $cur_theme['bg'] ?>;padding:1.5rem;text-align:center">
          <div style="font-size:2.5rem;margin-bottom:.3rem"><?= $cur_theme['emoji'] ?></div>
          <div style="color:#fff;font-family:var(--f1);font-size:1.2rem"><?= e($cfg['popup_title']??'Votre titre ici') ?></div>
        </div>
        <div style="background:#fff;padding:1.2rem;text-align:center">
          <p style="font-size:.85rem;color:#555;margin-bottom:.8rem"><?= e(mb_substr($cfg['popup_message']??'Votre message ici',0,100,'UTF-8')) ?></p>
          <?php if ($cfg['popup_promo']??''): ?>
          <div style="background:#fff8f5;border:2px dashed #C8561E;border-radius:8px;padding:.5rem 1rem;display:inline-block;margin-bottom:.6rem">
            <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:#C8561E">Code promo</div>
            <div style="font-size:1.2rem;font-weight:800;font-family:monospace;color:#C8561E"><?= e($cfg['popup_promo']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($cfg['popup_cta_text']??''): ?>
          <div style="background:#C8561E;color:#fff;padding:.5rem 1.2rem;border-radius:6px;display:inline-block;font-size:.82rem;font-weight:600"><?= e($cfg['popup_cta_text']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <p style="font-size:.72rem;color:var(--gy2);margin-top:.6rem;text-align:center">
        Aperçu en temps réel · Le rendu final peut légèrement différer
      </p>
    </div>
  </div>
</div>

<?php elseif ($tab === 'ticker'): ?>
<!-- ═══ TICKER ══════════════════════════════════════════════════════════ -->
<div class="editor-wrap" style="max-width:680px">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📢 Bandeau défilant</h4>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="ticker_enabled" <?= !empty($cfg['ticker_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Afficher le bandeau</span></div>
  <div class="tog-wrap" style="margin:.5rem 0 1rem"><label class="tog"><input type="checkbox" name="ticker_scroll" <?= !empty($cfg['ticker_scroll'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Défilement animé (sinon statique)</span></div>
  <div class="frm-g"><label class="frm-l">Texte</label>
    <textarea class="frm-i" name="ticker_text" rows="3"><?= e($cfg['ticker_text']??'') ?></textarea></div>
  <div class="tog-wrap" style="margin-bottom:.7rem"><label class="tog"><input type="checkbox" name="ticker_gradient" <?= !empty($cfg['ticker_gradient'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Dégradé (sinon couleur unie)</span></div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Couleur 1</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input type="color" name="ticker_color1" value="<?= e($cfg['ticker_color1']??'#C8561E') ?>" style="width:40px;height:36px;border:none;border-radius:4px;cursor:pointer">
    <input class="frm-i" maxlength="20" value="<?= e($cfg['ticker_color1']??'#C8561E') ?>" style="font-family:monospace">
      </div></div>
    <div class="frm-g"><label class="frm-l">Couleur 2</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input type="color" name="ticker_color2" value="<?= e($cfg['ticker_color2']??'#2A4C1E') ?>" style="width:40px;height:36px;border:none;border-radius:4px;cursor:pointer">
        <input class="frm-i" maxlength="20" value="<?= e($cfg['ticker_color2']??'#2A4C1E') ?>" style="font-family:monospace">
      </div></div>
  </div>
  <button type="submit" class="save-btn">💾 Enregistrer</button>
</div>

<?php elseif (in_array($tab, ['shop','atelier','blog'])): ?>
<!-- ═══ PAGES SIMPLES ══════════════════════════════════════════════════ -->
<?php $pnames = ['shop'=>'Boutique','atelier'=>'Ateliers','blog'=>'Journal']; ?>
<div class="editor-wrap" style="max-width:680px">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">Page — <?= $pnames[$tab] ?></h4>
  <p style="font-size:.8rem;color:var(--gy2);margin-bottom:1rem">Ces textes apparaissent en haut de la page.</p>
  <div class="frm-g"><label class="frm-l">Titre (vide = titre par défaut)</label>
    <input class="frm-i" name="page_<?= $tab ?>_title" maxlength="150" value="<?= e($cfg['page_'.$tab.'_title']??'') ?>"></div>
  <div class="frm-g"><label class="frm-l">Description / sous-titre</label>
    <textarea class="frm-i" name="page_<?= $tab ?>_desc" rows="4" maxlength="500"><?= e($cfg['page_'.$tab.'_desc']??'') ?></textarea></div>
  <?php if ($tab === 'atelier'): ?>
  <div class="frm-g"><label class="frm-l">Message affiché si aucun créneau disponible</label>
    <textarea class="frm-i" name="no_session_msg" rows="2" maxlength="300" placeholder="Aucun atelier n'est prévu pour le moment…"><?= e($cfg['no_session_msg']??"Aucun atelier n'est prévu pour le moment. Revenez bientôt !") ?></textarea></div>
  <?php endif; ?>
  <button type="submit" class="save-btn">💾 Enregistrer</button>
</div>

<?php elseif ($tab === 'story'): ?>
<!-- ═══ NOTRE HISTOIRE ══════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📖 Notre Histoire</h4>
      <div class="tog-wrap" style="margin-bottom:1rem"><label class="tog"><input type="checkbox" name="page_story_enabled" <?= !empty($cfg['page_story_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Page visible dans le menu</span></div>
      <div class="frm-g"><label class="frm-l">Titre de la page</label><input class="frm-i" name="page_story_title" maxlength="150" value="<?= e($cfg['page_story_title']??'Notre Histoire') ?>"></div>
      <div class="frm-g"><label class="frm-l">Image hero (URL)</label><input class="frm-i" name="story_hero_img" maxlength="500" value="<?= e($cfg['story_hero_img']??'') ?>" placeholder="https://..."></div>
      <div class="frm-g"><label class="frm-l">Accroche sous le titre</label><input class="frm-i" name="story_subtitle" maxlength="300" value="<?= e($cfg['story_subtitle']??'') ?>"></div>
      <div class="frm-g"><label class="frm-l">Citation mise en avant</label><input class="frm-i" name="story_quote" maxlength="300" value="<?= e($cfg['story_quote']??'') ?>" placeholder="« … »"></div>
    </div>
  </div>
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">Blocs de texte</h4>
      <?php foreach ([['1','Depuis 2018'],['2','Notre engagement'],['3','L\'avenir']] as [$n,$def]): ?>
      <div class="frm-g"><label class="frm-l">Bloc <?= $n ?> — Titre</label><input class="frm-i" name="story_text<?= $n ?>_title" maxlength="120" value="<?= e($cfg['story_text'.$n.'_title']??$def) ?>"></div>
      <div class="frm-g"><label class="frm-l">Bloc <?= $n ?> — Texte</label><textarea class="frm-i" name="story_text<?= $n ?>" rows="4" style="resize:vertical"><?= e($cfg['story_text'.$n]??'') ?></textarea></div>
      <?php endforeach; ?>
      <div class="frm-g"><label class="frm-l">Image secondaire (URL)</label><input class="frm-i" name="story_img2" maxlength="500" value="<?= e($cfg['story_img2']??'') ?>"></div>
      <button type="submit" class="save-btn">💾 Enregistrer</button>
    </div>
  </div>
</div>

<?php elseif ($tab === 'footer'): ?>
<!-- ═══ FOOTER ══════════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🔗 Liens du footer</h4>
      <?php $footer_links = ['footer_show_legal'=>'Mentions légales','footer_show_cgv'=>'CGV','footer_show_shipping'=>'Livraison & Retours','footer_show_faq'=>'FAQ','footer_show_story'=>'Notre Histoire','footer_show_giftcard'=>'Carte Cadeau','footer_show_compare'=>'Comparer les cafés']; ?>
      <?php foreach ($footer_links as $key => $label): ?>
      <div class="tog-wrap" style="margin-bottom:.5rem"><label class="tog"><input type="checkbox" name="<?= $key ?>" <?= !empty($cfg[$key])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl"><?= $label ?></span></div>
      <?php endforeach; ?>
      <div class="frm-g" style="margin-top:1rem"><label class="frm-l">Liens personnalisés (un par ligne : Texte|URL)</label>
        <textarea class="frm-i" name="custom_links" rows="4" style="font-family:monospace;font-size:.8rem" placeholder="Mon lien|https://exemple.fr&#10;Autre page|https://..."><?php
        $cl = json_decode($cfg['footer_custom_links']??'[]',true)??[];
        echo e(implode("\n", array_map(fn($c)=>($c['label']??'').'|'.($c['url']??''), $cl)));
        ?></textarea></div>
    </div>
  </div>
  <div>
    <div class="editor-wrap">
      <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🍪 Bandeau cookies</h4>
      <div class="tog-wrap" style="margin-bottom:.8rem"><label class="tog"><input type="checkbox" name="cookies_enabled" <?= !empty($cfg['cookies_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Afficher le bandeau cookies</span></div>
      <div class="frm-g"><label class="frm-l">Texte du bandeau</label>
        <textarea class="frm-i" name="cookies_text" rows="3" maxlength="400"><?= e($cfg['cookies_text']??'') ?></textarea></div>
      <button type="submit" class="save-btn">💾 Enregistrer</button>
    </div>
  </div>
</div>

<?php elseif (in_array($tab, ['legal','cgv','shipping','faq','info'])): ?>
<!-- ═══ PAGES LÉGALES ══════════════════════════════════════════════════ -->
<?php $plabels = ['legal'=>'Mentions légales','cgv'=>'CGV','shipping'=>'Livraison','faq'=>'FAQ','info'=>'Informations']; ?>
<div class="editor-wrap" style="max-width:900px">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📄 <?= $plabels[$tab] ?></h4>
  <p style="font-size:.8rem;color:var(--gy2);margin-bottom:1rem">Si vide, un contenu par défaut est affiché. Vous pouvez personnaliser complètement en HTML.</p>
  <div class="frm-g"><label class="frm-l">Titre</label><input class="frm-i" name="page_title" maxlength="150" value="<?= e($cfg['page_'.$tab.'_title']??'') ?>" placeholder="<?= $plabels[$tab] ?>"></div>
  <div class="frm-g"><label class="frm-l">Contenu HTML</label>
    <div class="tbar">
      <?php foreach ([['B','<strong>','</strong>'],['I','<em>','</em>'],['H2','<h2>','</h2>'],['H3','<h3>','</h3>'],['§','<p>','</p>'],['HR','<hr>','']] as [$l,$o,$c]): ?>
      <button type="button" class="tb" data-o="<?= e($o) ?>" data-c="<?= e($c) ?>"><?= $l ?></button>
      <?php endforeach; ?>
    </div>
    <textarea class="editor" name="page_content" id="f_content" rows="20"><?= htmlspecialchars($cfg['page_'.$tab.'_content']??'') ?></textarea>
  </div>
  <button type="submit" class="save-btn">💾 Enregistrer</button>
</div>
<script>
document.querySelectorAll('.tb').forEach(btn => {
  btn.addEventListener('click', () => {
    const a=document.getElementById('f_content');
    const s=a.selectionStart,e2=a.selectionEnd;
    const sel=a.value.substring(s,e2)||'texte';
    a.value=a.value.substring(0,s)+(btn.dataset.o+sel+btn.dataset.c)+a.value.substring(e2);
    a.focus();
  });
});
</script>

<?php endif; ?>
  </form>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
