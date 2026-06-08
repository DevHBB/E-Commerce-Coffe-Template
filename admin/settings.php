<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg  = cfg_get();
$errs = [];
$ok   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $text_fields = [
        'site_url'=>300,'site_name'=>150,'tagline'=>200,'address'=>250,'phone'=>30,'email'=>150,
        'instagram'=>200,'facebook'=>200,'twitter'=>200,
        'hours_week'=>80,'hours_weekend'=>80,'pickup_address'=>300,'pickup_hours'=>120,
        'open_open_text'=>60,'open_closed_text'=>60,
        'currency'=>10,'logo_url'=>500,
        'site_url'=>300,
        'smtp_host'=>100,'smtp_user'=>150,'smtp_from'=>150,'smtp_from_name'=>100,
        'seo_meta_desc'=>300,'seo_keywords'=>300,'seo_og_image'=>500,'seo_google_verify'=>100,
        'company_name'=>150,'company_legal'=>80,'company_siret'=>20,'company_siren'=>10,
        'company_vat'=>30,'company_rcs'=>80,'company_capital'=>40,'company_ape'=>10,
        'invoice_prefix'=>10,'invoice_note'=>300,'invoice_payment_terms'=>300,
        'invoice_footer'=>300,'invoice_logo'=>500,'invoice_color'=>20,
        'ticker_text'=>500,'ticker_color1'=>20,'ticker_color2'=>20,
        'hero_pill_text'=>150,'hero_title_line1'=>100,'hero_title_line2'=>100,
        'hero_title_line3'=>100,'hero_title_line4'=>100,'hero_sub'=>600,
        'hero_bg_color1'=>20,'hero_bg_color2'=>20,'hero_cta_text'=>80,'hero_cta_url'=>200,
        'about'=>2000,'about_badge_n'=>10,'about_badge_l'=>40,
        'stats_years'=>10,'stats_origins'=>10,'stats_clients'=>10,
        'stats_label1'=>40,'stats_label2'=>40,'stats_label3'=>40,
        'atelier_carousel_title'=>150,'atelier_carousel_link'=>200,'atelier_carousel_link_text'=>80,
        'paypal_client_id'=>200,'sumup_merchant'=>80,'review_cron_key'=>60,'captcha_site_key'=>100,
        'footer_custom_links'=>2000,'cookies_text'=>400,
        'maintenance_msg'=>300,
        'popup_title'=>150,'popup_message'=>500,'popup_cta_text'=>100,
        'popup_cta_url'=>300,'popup_promo'=>40,'popup_image'=>500,
        'nav_label_home'=>60,'nav_label_shop'=>60,'nav_label_atelier'=>60,
        'nav_label_blog'=>60,'nav_label_contact'=>60,'nav_label_giftcard'=>60,
        'no_session_msg'=>300,
    ];
    foreach ($text_fields as $field => $max) {
        if (isset($_POST[$field])) $cfg[$field] = clean($_POST[$field], $max);
    }

    $cfg['smtp_port']               = clean_int($_POST['smtp_port']??587, 1, 65535);
    $cfg['tva_default']             = clean_int($_POST['tva_default']??20, 0, 100);
    $cfg['loyalty_points_per_euro'] = clean_int($_POST['loyalty_points_per_euro']??10, 1, 1000);
    $cfg['email_review_delay_days'] = clean_int($_POST['email_review_delay_days']??7, 1, 90);
    // Livraison gérée dans admin/shipping.php
    if (!empty($_POST['invoice_counter']) && (int)$_POST['invoice_counter'] >= 1)
        $cfg['invoice_counter'] = clean_int($_POST['invoice_counter'], 1, 999999);

    // Popup
    $cfg['popup_enabled'] = isset($_POST['popup_enabled']);
    $cfg['popup_once']    = isset($_POST['popup_once']);
    $cfg['popup_type']    = clean($_POST['popup_type']??'custom', 20);
    $cfg['popup_delay']   = clean_int($_POST['popup_delay']??2, 0, 30);
    // Nav labels: gérés par pages.php, ne pas écraser ici
    // (sinon toutes les checkboxes reviennent à false car absentes du form settings)
    foreach (['nav_show_home','nav_show_shop','nav_show_atelier','nav_show_blog','nav_show_giftcard','nav_show_contact'] as $ns) {
        // Conserver la valeur existante si le champ n'est pas dans ce form
        if (!array_key_exists($ns, $_POST)) {
            // Ne pas modifier - cfg_get() a déjà la bonne valeur dans $cfg
        } else {
            $cfg[$ns] = isset($_POST[$ns]);
        }
    }
    // Autres toggles
    // Toggles présents dans le form settings -> mettre à jour selon la checkbox
    // Si le form settings a été soumis (_settings_form=1), on peut traiter
    // TOUTES les checkboxes du form: cochée = true, absente du POST = false (décoché).
    // Les checkboxes d'autres pages (payment, shipping) ne sont PAS dans cette liste
    // donc elles ne seront pas écrasées.
    $all_toggles_in_form = [
        'delivery_enabled','pickup_enabled','ecommerce_enabled','booking_enabled','booking_paid',
        'maintenance','email_order_enabled','email_booking_enabled','email_contact_enabled',
        'email_review_enabled','reviews_enabled','reviews_products','reviews_workshops','reviews_blog',
        'captcha_enabled','captcha_reviews','captcha_booking','captcha_contact','invoice_show_tva','seo_robots_index','page_transition',
        'page_story_enabled','cookies_enabled','atelier_carousel_enabled','quiz_enabled','loyalty_enabled',
    ];
    if (isset($_POST['_settings_form'])) {
        // Le form settings a été soumis: checkbox cochée = present, décochée = absent → false
        foreach ($all_toggles_in_form as $cb) {
            $cfg[$cb] = isset($_POST[$cb]);
        }
    } else {
        // Form soumis depuis ailleurs: ne pas toucher ces toggles
        foreach ($all_toggles_in_form as $cb) {
            // Conserver la valeur existante (ne rien changer)
        }
    }    // Toggles footer: gérés par pages.php (onglet Footer), ne pas écraser si absents ici
    foreach (['footer_show_legal','footer_show_cgv','footer_show_shipping',
              'footer_show_faq','footer_show_story','footer_show_giftcard','footer_show_compare'] as $fk) {
        if (array_key_exists($fk, $_POST)) $cfg[$fk] = isset($_POST[$fk]);
        // Sinon: conserver la valeur existante dans $cfg (venant de cfg_get())
    }

    // Horaires structurés
    $oh_raw = [];
    // Popup
    $cfg['popup_enabled'] = isset($_POST['popup_enabled']);
    $cfg['popup_once']    = isset($_POST['popup_once']);
    $cfg['popup_type']    = clean($_POST['popup_type']??'custom', 20);
    $cfg['popup_delay']   = clean_int($_POST['popup_delay']??2, 0, 30);
    // Nav labels: gérés par pages.php, ne pas écraser ici
    // (sinon toutes les checkboxes reviennent à false car absentes du form settings)
    foreach (['nav_show_home','nav_show_shop','nav_show_atelier','nav_show_blog','nav_show_giftcard','nav_show_contact'] as $ns) {
        // Conserver la valeur existante si le champ n'est pas dans ce form
        if (!array_key_exists($ns, $_POST)) {
            // Ne pas modifier - cfg_get() a déjà la bonne valeur dans $cfg
        } else {
            $cfg[$ns] = isset($_POST[$ns]);
        }
    }
    // Autres toggles
    foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $code) {
        $slots_open  = $_POST['oh_open'][$code]  ?? [];
        $slots_close = $_POST['oh_close'][$code] ?? [];
        if (!is_array($slots_open)) { $slots_open = [$slots_open]; $slots_close = [$slots_close]; }
        $day_slots = [];
        foreach ($slots_open as $si => $o) {
            $c2 = trim($slots_close[$si] ?? '');
            $o  = trim($o);
            if ($o && $c2) $day_slots[] = ['open'=>$o,'close'=>$c2];
        }
        if ($day_slots) $oh_raw[] = ['day'=>$code,'slots'=>$day_slots];
    }
    if ($oh_raw) $cfg['open_hours'] = json_encode($oh_raw, JSON_UNESCAPED_UNICODE);

    $cfg['logo_mode'] = in_array(($_POST['logo_mode'] ?? $cfg['logo_mode'] ?? 'text'), ['text','image'], true) ? ($_POST['logo_mode'] ?? 'text') : 'text';

    // Clés chiffrées
    // Popup
    $cfg['popup_enabled'] = isset($_POST['popup_enabled']);
    $cfg['popup_once']    = isset($_POST['popup_once']);
    $cfg['popup_type']    = clean($_POST['popup_type']??'custom', 20);
    $cfg['popup_delay']   = clean_int($_POST['popup_delay']??2, 0, 30);
    // Nav labels: gérés par pages.php, ne pas écraser ici
    // (sinon toutes les checkboxes reviennent à false car absentes du form settings)
    foreach (['nav_show_home','nav_show_shop','nav_show_atelier','nav_show_blog','nav_show_giftcard','nav_show_contact'] as $ns) {
        // Conserver la valeur existante si le champ n'est pas dans ce form
        if (!array_key_exists($ns, $_POST)) {
            // Ne pas modifier - cfg_get() a déjà la bonne valeur dans $cfg
        } else {
            $cfg[$ns] = isset($_POST[$ns]);
        }
    }
    // Autres toggles
    // Clés API et mots de passe — conservés si champ vide ou contient '•'
    foreach ([
        'stripe_pk', 'stripe_sk', 'stripe_webhook_secret',
        'paypal_client_id', 'paypal_client_secret',
        'sumup_key', 'smtp_pass', 'captcha_secret_key',
    ] as $key) {
        $val = trim($_POST[$key] ?? '');
        if ($val === '' || strpos($val, '•') !== false) continue; // Inchangé
        $cfg[$key] = $val; // Stocké en clair
    }

    // Mot de passe admin
    $pw1 = $_POST['new_pw'] ?? ''; $pw2 = $_POST['new_pw2'] ?? '';
    if ($pw1 || $pw2) {
        if (strlen($pw1) < 8) $errs[] = 'Mot de passe : 8 caractères minimum.';
        elseif ($pw1 !== $pw2) $errs[] = 'Les mots de passe ne correspondent pas.';
        else {
            $hash = password_hash($pw1, PASSWORD_BCRYPT, ['cost'=>10]);
            file_put_contents(DATA_DIR.'/.admin_hash', $hash, LOCK_EX);
            @chmod(DATA_DIR.'/.admin_hash', 0600);
            admin_log('password_changed','Mot de passe admin modifié');
        }
    }

    if (!$errs) {
        cfg_save($cfg);
        $cfg = cfg_get();
        $ok  = true;
        admin_log('settings_saved','Paramètres enregistrés');
    }
}

$oh_days = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
$oh_data = json_decode($cfg['open_hours']??'[]', true) ?: [];
$oh_by   = [];
foreach ($oh_data as $h) $oh_by[$h['day']] = $h;

$at = 'Paramètres'; $cur_adm = 'cfg';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Paramètres</h2><p>Configuration générale — chiffrement AES-256-GCM</p></div>
</div>
<div class="adm-content">
<?php if ($ok): ?><div class="alert-ok" style="margin-bottom:1.2rem">✓ Paramètres enregistrés.</div><?php endif; ?>
<?php if ($errs): ?><div class="alert-err" style="margin-bottom:1.2rem"><?php foreach($errs as $er) echo '<div>✗ '.htmlspecialchars($er).'</div>'; ?></div><?php endif; ?>

<form method="POST">
<?= csrf_field() ?>
<input type="hidden" name="_settings_form" value="1">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
<div>

<!-- IDENTITÉ -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🏠 Identité</h4>
  <div class="frm-g"><label class="frm-l">Nom du site</label><input class="frm-i" name="site_name" maxlength="150" value="<?= e($cfg['site_name']??'') ?>"></div>
    <div class="frm-g"><label class="frm-l">URL du site <small style="color:var(--gy2)">(pour les liens dans les emails)</small></label>
      <input class="frm-i" name="site_url" maxlength="300" value="<?= e($cfg['site_url']??'') ?>" placeholder="https://votre-domaine.fr/cafe"></div>
  <div class="frm-g"><label class="frm-l">Tagline</label><input class="frm-i" name="tagline" maxlength="200" value="<?= e($cfg['tagline']??'') ?>"></div>
  <div class="frm-g"><label class="frm-l">Adresse</label><input class="frm-i" name="address" maxlength="250" value="<?= e($cfg['address']??'') ?>"></div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" name="phone" maxlength="30" value="<?= e($cfg['phone']??'') ?>"></div>
    <div class="frm-g"><label class="frm-l">Email</label><input class="frm-i" type="email" name="email" maxlength="150" value="<?= e($cfg['email']??'') ?>"></div>
    <div class="frm-g">
      <label class="frm-l">URL du site <small style="color:var(--gy2)">(pour les liens dans les emails — ex: https://bobbix.fr/storage/cafe)</small></label>
      <input class="frm-i" name="site_url" maxlength="300" value="<?= e($cfg['site_url']??'') ?>" placeholder="https://votre-domaine.fr/cafe">
    </div>
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Instagram</label><input class="frm-i" name="instagram" maxlength="200" value="<?= e($cfg['instagram']??'') ?>" placeholder="https://instagram.com/..."></div>
    <div class="frm-g"><label class="frm-l">Facebook</label><input class="frm-i" name="facebook" maxlength="200" value="<?= e($cfg['facebook']??'') ?>"></div>
  </div>
</div>

<!-- HORAIRES -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">⏰ Horaires</h4>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Texte semaine</label><input class="frm-i" name="hours_week" maxlength="80" value="<?= e($cfg['hours_week']??'') ?>" placeholder="Mar–Sam 9h–19h"></div>
    <div class="frm-g"><label class="frm-l">Texte weekend</label><input class="frm-i" name="hours_weekend" maxlength="80" value="<?= e($cfg['hours_weekend']??'') ?>" placeholder="Dim 10h–17h"></div>
  </div>
  <p style="font-size:.7rem;color:var(--gy2);margin-bottom:.8rem">
    Créneaux pour le badge Ouvert/Fermé — Laisser vide = jour fermé.<br>
    Cliquez <strong>+</strong> pour ajouter un créneau (ex&nbsp;: 08h–12h puis 14h–18h).
  </p>
  <div style="display:flex;flex-direction:column;gap:.5rem">
  <?php foreach ($oh_days as $code => $label):
    $h      = $oh_by[$code] ?? null;
    $slots  = $h['slots'] ?? (isset($h['open']) ? [['open'=>$h['open'],'close'=>$h['close']]] : [['open'=>'','close'=>'']]);
    if (!$slots) $slots = [['open'=>'','close'=>'']];
  ?>
  <div style="display:flex;align-items:flex-start;gap:.6rem;padding:.5rem .6rem;background:var(--cr);border-radius:var(--r)">
    <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--gy2);width:28px;padding-top:.55rem;flex-shrink:0"><?= $label ?></span>
    <div class="oh-day-slots" data-code="<?= $code ?>" style="flex:1;display:flex;flex-direction:column;gap:.3rem">
      <?php foreach ($slots as $si => $sl): ?>
      <div class="oh-slot-row" style="display:flex;align-items:center;gap:.4rem">
        <input type="time" name="oh_open[<?= $code ?>][]" value="<?= e($sl['open']??'') ?>"
          class="frm-i" style="padding:.3rem .4rem;font-size:.75rem;flex:1;min-width:0" title="Ouverture">
        <span style="font-size:.72rem;color:var(--gy2);flex-shrink:0">–</span>
        <input type="time" name="oh_close[<?= $code ?>][]" value="<?= e($sl['close']??'') ?>"
          class="frm-i" style="padding:.3rem .4rem;font-size:.75rem;flex:1;min-width:0" title="Fermeture">
        <?php if ($si === 0): ?>
        <button type="button" onclick="ohAddSlot(this,'<?= $code ?>')"
          style="background:var(--or);color:#fff;border:none;border-radius:4px;width:22px;height:22px;font-size:.8rem;cursor:pointer;flex-shrink:0;line-height:1" title="Ajouter créneau">+</button>
        <?php else: ?>
        <button type="button" onclick="this.closest('.oh-slot-row').remove()"
          style="background:rgba(200,86,30,.15);color:var(--or);border:none;border-radius:4px;width:22px;height:22px;font-size:.85rem;cursor:pointer;flex-shrink:0;line-height:1" title="Supprimer">×</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <div class="frm-row" style="margin-top:.8rem">
    <div class="frm-g"><label class="frm-l">Texte badge ouvert</label><input class="frm-i" name="open_open_text" maxlength="60" value="<?= e($cfg['open_open_text']??'Ouvert actuellement') ?>"></div>
    <div class="frm-g"><label class="frm-l">Texte badge fermé</label><input class="frm-i" name="open_closed_text" maxlength="60" value="<?= e($cfg['open_closed_text']??'Fermée actuellement') ?>"></div>
  </div>
</div>

<!-- E-COMMERCE & RÉSERVATIONS -->
<div class="editor-wrap" style="border:2px solid rgba(42,76,30,.2)">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🛒 E-commerce & Ateliers</h4>
  <div class="frm-row" style="margin-bottom:.6rem">
    <div class="tog-wrap" style="margin-bottom:0">
      <label class="tog"><input type="checkbox" name="delivery_enabled" <?= !empty($cfg['delivery_enabled']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
      <span class="tog-lbl">📦 Livraison à domicile</span>
    </div>
    <div class="tog-wrap" style="margin-bottom:0">
      <label class="tog"><input type="checkbox" name="pickup_enabled" <?= !empty($cfg['pickup_enabled']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
      <span class="tog-lbl">🏪 Retrait en boutique</span>
    </div>
  </div>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="ecommerce_enabled" <?= !empty($cfg['ecommerce_enabled']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-weight:600">E-commerce activé (boutique + panier)</span></div>
  <p style="font-size:.72rem;color:var(--gy2);margin:.2rem 0 .7rem">Désactivé = mode vitrine : prix visibles, boutons achat masqués.</p>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="booking_enabled" <?= !empty($cfg['booking_enabled']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-weight:600">Réservations ateliers actives</span></div>
  <p style="font-size:.72rem;color:var(--gy2);margin:.2rem 0 .4rem">Indépendant du e-commerce.</p>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="booking_paid" <?= !empty($cfg['booking_paid']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Paiement en ligne à la réservation</span></div>
  <p style="font-size:.72rem;color:var(--gy2);margin:.2rem 0 0">Désactivé = règlement sur place.</p>
</div>

<!-- AVIS & CAPTCHA -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">⭐ Avis & reCAPTCHA</h4>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="reviews_enabled" <?= !empty($cfg['reviews_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Avis clients activés</span></div>
  <div style="margin-left:1.5rem;margin-top:.3rem;display:flex;gap:1rem;flex-wrap:wrap">
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="reviews_products" <?= !empty($cfg['reviews_products'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.78rem">Produits</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="reviews_workshops" <?= !empty($cfg['reviews_workshops'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.78rem">Ateliers</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="reviews_blog" <?= !empty($cfg['reviews_blog'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.78rem">Articles</span></div>
  </div>
  <div style="background:rgba(42,76,30,.05);border-radius:var(--r);padding:.6rem .9rem;font-size:.76rem;color:var(--gy2);margin-bottom:.6rem">
    ⚙️ <strong>reCAPTCHA v3</strong> (invisible — pas de case à cocher) ·
    <a href="https://www.google.com/recaptcha/admin/create" target="_blank" style="color:var(--or)">Créer mes clés →</a><br>
    Dans Google : choisir <strong>"reCAPTCHA v3"</strong>, domaine = votre domaine, puis copier les deux clés ci-dessous.
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Clé publique (Site key)</label>
      <input class="frm-i" name="captcha_site_key" value="<?= e($cfg['captcha_site_key']??'') ?>" placeholder="6Lf... (commence par 6L)">
    </div>
    <div class="frm-g"><label class="frm-l">Clé secrète (Secret key)</label>
      <input class="frm-i" type="password" name="captcha_secret_key"
        value="<?= !empty($cfg['captcha_secret_key'])&&!str_starts_with($cfg['captcha_secret_key'],'••')? e($cfg['captcha_secret_key']) : '' ?>"
        placeholder="Laisser vide pour conserver" autocomplete="new-password">
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.4rem">
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="captcha_enabled" <?= !empty($cfg['captcha_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Connexion admin</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="captcha_reviews" <?= !empty($cfg['captcha_reviews'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Formulaires d'avis</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="captcha_booking" <?= !empty($cfg['captcha_booking'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Réservation ateliers</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="captcha_contact" <?= !empty($cfg['captcha_contact'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Formulaire contact</span></div>
  </div>
</div>


</div><!-- col gauche -->

<div><!-- col droite -->

<!-- INFORMATIONS ENTREPRISE -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🏢 Informations légales</h4>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Raison sociale</label><input class="frm-i" name="company_name" maxlength="150" value="<?= e($cfg['company_name']??'') ?>" placeholder="Café Maison SARL"></div>
    <div class="frm-g"><label class="frm-l">Forme juridique</label><input class="frm-i" name="company_legal" maxlength="80" value="<?= e($cfg['company_legal']??'') ?>" placeholder="SARL, SAS, EI…"></div>
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">SIRET</label><input class="frm-i" name="company_siret" maxlength="20" value="<?= e($cfg['company_siret']??'') ?>"></div>
    <div class="frm-g"><label class="frm-l">N° TVA</label><input class="frm-i" name="company_vat" maxlength="30" value="<?= e($cfg['company_vat']??'') ?>" placeholder="FR XX XXX XXX XXX"></div>
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">RCS</label><input class="frm-i" name="company_rcs" maxlength="80" value="<?= e($cfg['company_rcs']??'') ?>"></div>
    <div class="frm-g"><label class="frm-l">Capital social</label><input class="frm-i" name="company_capital" maxlength="40" value="<?= e($cfg['company_capital']??'') ?>" placeholder="10 000 €"></div>
  </div>
</div>


<!-- EMAIL SMTP -->
<!-- EMAIL SMTP -->
<!-- Test mail inline -->
<div id="mailTestResult" style="display:none;margin-bottom:.8rem;padding:.6rem 1rem;border-radius:var(--r);font-size:.8rem"></div>
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📧 Emails automatiques &nbsp;<a href="./smtp_test.php" class="a-btn" style="font-size:.75rem;padding:.3rem .7rem">Tester l'envoi →</a></h4>
  <div style="background:rgba(200,86,30,.07);border-radius:var(--r);padding:.6rem .8rem;font-size:.77rem;color:var(--gy2);margin-bottom:.8rem">
    ⚠️ <strong>Sans SMTP configuré, aucun email n'est envoyé.</strong>
    Configurez dans <a href="./payment.php" style="color:var(--or)">Admin → Paiement</a> pour SMTP.<br>
    Ou directement ici :
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Serveur SMTP</label><input class="frm-i" name="smtp_host" maxlength="200" value="<?= e($cfg['smtp_host']??'') ?>" placeholder="smtp.gmail.com"></div>
    <div class="frm-g"><label class="frm-l">Port</label><input class="frm-i" type="number" name="smtp_port" value="<?= e($cfg['smtp_port']??587) ?>" style="max-width:80px"></div>
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Utilisateur SMTP</label><input class="frm-i" name="smtp_user" maxlength="200" value="<?= e($cfg['smtp_user']??'') ?>" placeholder="votre@email.fr"></div>
    <div class="frm-g"><label class="frm-l">Mot de passe SMTP</label><input class="frm-i" type="password" name="smtp_pass" value="<?= e($cfg['smtp_pass']??'') ?>" placeholder="Mot de passe ou clé app"></div>
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Email expéditeur</label><input class="frm-i" type="email" name="smtp_from" maxlength="200" value="<?= e($cfg['smtp_from']??'') ?>" placeholder="bonjour@cafe.fr"></div>
    <div class="frm-g"><label class="frm-l">Nom expéditeur</label><input class="frm-i" name="smtp_from_name" maxlength="100" value="<?= e($cfg['smtp_from_name']??'Café Maison') ?>"></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.6rem">
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="email_order_enabled" <?= !empty($cfg['email_order_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Emails commande</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="email_booking_enabled" <?= !empty($cfg['email_booking_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Emails atelier</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="email_contact_enabled" <?= !empty($cfg['email_contact_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Emails contact</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="email_review_enabled" <?= !empty($cfg['email_review_enabled']??false)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Email satisfaction J+<?= (int)($cfg['email_review_delay_days']??7) ?></span></div>
  </div>
  <div class="frm-g" style="margin-top:.4rem">
    <label class="frm-l">Délai email satisfaction (jours après achat)</label>
    <input class="frm-i" type="number" name="email_review_delay_days" min="1" max="30"
      value="<?= (int)($cfg['email_review_delay_days']??7) ?>" style="max-width:70px">
  </div>
</div>

<!-- DIVERS -->
<div class="editor-wrap" style="margin-bottom:.8rem">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.7rem">⚙️ Options du site</h4>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem">
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="cookies_enabled" <?= !empty($cfg['cookies_enabled']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Bannière cookies</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="page_story_enabled" <?= !empty($cfg['page_story_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Page Notre Histoire</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="atelier_carousel_enabled" <?= !empty($cfg['atelier_carousel_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Carousel ateliers</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="quiz_enabled" <?= !empty($cfg['quiz_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Quiz guidé</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="loyalty_enabled" <?= !empty($cfg['loyalty_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Programme fidélité</span></div>
    <div class="tog-wrap"><label class="tog"><input type="checkbox" name="page_transition" <?= !empty($cfg['page_transition']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="font-size:.8rem">Transitions de page</span></div>
  </div>
</div>

<!-- SEO -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🔍 SEO</h4>
  <div class="frm-g"><label class="frm-l">Meta description (160 car. max)</label><textarea class="frm-i" name="seo_meta_desc" rows="2" maxlength="300" style="resize:vertical"><?= e($cfg['seo_meta_desc']??'') ?></textarea></div>
  <div class="frm-g"><label class="frm-l">Mots-clés (séparés par virgules)</label><input class="frm-i" name="seo_keywords" maxlength="300" value="<?= e($cfg['seo_keywords']??'') ?>" placeholder="café artisanal Paris, torréfacteur..."></div>
  <div class="frm-g"><label class="frm-l">Image Open Graph (URL)</label><input class="frm-i" name="seo_og_image" maxlength="500" value="<?= e($cfg['seo_og_image']??'') ?>"></div>
  <div class="frm-g"><label class="frm-l">Google Search Console — code de vérification</label><input class="frm-i" name="seo_google_verify" maxlength="100" value="<?= e($cfg['seo_google_verify']??'') ?>"></div>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="seo_robots_index" <?= !empty($cfg['seo_robots_index']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Autoriser l'indexation par les moteurs</span></div>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="page_transition" <?= !empty($cfg['page_transition']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Animation café entre les pages</span></div>
  <p style="font-size:.7rem;color:var(--gy2);margin-top:.6rem">
    Sitemap : <a href="../api/sitemap.php" target="_blank" style="color:var(--or)">api/sitemap.php</a> ·
    Robots.txt : <a href="../robots.txt" target="_blank" style="color:var(--or)">robots.txt</a>
  </p>
</div>

<!-- FACTURATION -->
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🧾 Facturation</h4>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Préfixe facture</label><input class="frm-i" name="invoice_prefix" maxlength="10" value="<?= e($cfg['invoice_prefix']??'CM') ?>"></div>
    <div class="frm-g"><label class="frm-l">Prochain numéro</label><input class="frm-i" type="number" name="invoice_counter" min="1" value="<?= e($cfg['invoice_counter']??1) ?>"></div>
  </div>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="invoice_show_tva" <?= !empty($cfg['invoice_show_tva']??true)?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl">Afficher la TVA sur les factures</span></div>
  <div class="frm-g" style="margin-top:.6rem"><label class="frm-l">Couleur principale</label>
    <div style="display:flex;gap:.5rem;align-items:center">
      <input type="color" name="invoice_color" value="<?= e($cfg['invoice_color']??'#C8561E') ?>" style="width:44px;height:36px;border:none;border-radius:4px;cursor:pointer">
   <input class="frm-i" maxlength="20" value="<?= e($cfg['invoice_color']??'#C8561E') ?>" style="font-family:monospace;width:100px">
    </div></div>
  <div class="frm-g"><label class="frm-l">Logo (URL)</label><input class="frm-i" name="invoice_logo" maxlength="500" value="<?= e($cfg['invoice_logo']??'') ?>"></div>
  <div class="frm-g"><label class="frm-l">Note de bas de facture</label><textarea class="frm-i" name="invoice_note" rows="2" maxlength="300"><?= e($cfg['invoice_note']??'Merci pour votre commande !') ?></textarea></div>
  <div class="frm-g"><label class="frm-l">Pied de page facture (mentions légales)</label><textarea class="frm-i" name="invoice_footer" rows="2" maxlength="300"><?= e($cfg['invoice_footer']??'') ?></textarea></div>
</div>

<!-- MOT DE PASSE -->
<div class="editor-wrap" style="border:2px solid rgba(200,86,30,.3)">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🔑 Mot de passe admin</h4>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Nouveau mot de passe</label><input class="frm-i" type="password" name="new_pw" minlength="8" placeholder="8 caractères minimum" autocomplete="new-password"></div>
    <div class="frm-g"><label class="frm-l">Confirmer</label><input class="frm-i" type="password" name="new_pw2" placeholder="Répéter" autocomplete="new-password"></div>
  </div>
  <p style="font-size:.72rem;color:var(--gy2)">Laisser vide pour conserver le mot de passe actuel.</p>
</div>

<!-- MAINTENANCE -->
<div class="editor-wrap" style="border:2px solid rgba(200,86,30,.3)">
  <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">🔧 Maintenance</h4>
  <div class="tog-wrap"><label class="tog"><input type="checkbox" name="maintenance" <?= !empty($cfg['maintenance'])?'checked':'' ?>><span class="tog-sl"></span></label><span class="tog-lbl" style="color:#c0392b;font-weight:600">Mode maintenance (site inaccessible)</span></div>
  <div class="frm-g" style="margin-top:.6rem"><label class="frm-l">Message maintenance</label><textarea class="frm-i" name="maintenance_msg" rows="2" maxlength="300"><?= e($cfg['maintenance_msg']??'Site en maintenance, revenez bientôt !') ?></textarea></div>
</div>



</div><!-- col droite -->

</div><!-- grid -->

<div style="position:sticky;bottom:0;background:rgba(250,246,241,.95);backdrop-filter:blur(8px);padding:1rem;margin:1.5rem -1.5rem -1.5rem;border-top:1px solid #EAE3D9;display:flex;gap:.8rem;justify-content:flex-end;z-index:10">
  <a href="./index.php" class="a-btn">Annuler</a>
  <button type="submit" class="save-btn" style="min-width:200px">💾 Enregistrer tous les paramètres</button>
</div>
</form>
</div>
<script>
function ohAddSlot(btn, code) {
    var container = btn.closest('.oh-day-slots');
    var row   = document.createElement('div');
    row.className  = 'oh-slot-row';
    row.style.cssText = 'display:flex;align-items:center;gap:.4rem';

    var iOpen  = document.createElement('input');
    iOpen.type = 'time'; iOpen.name = 'oh_open['+code+'][]';
    iOpen.className = 'frm-i';
    iOpen.style.cssText = 'padding:.3rem .4rem;font-size:.75rem;flex:1;min-width:0';

    var sep = document.createElement('span');
    sep.textContent = '–';
    sep.style.cssText = 'font-size:.72rem;color:#999;flex-shrink:0';

    var iClose  = document.createElement('input');
    iClose.type = 'time'; iClose.name = 'oh_close['+code+'][]';
    iClose.className = 'frm-i';
    iClose.style.cssText = 'padding:.3rem .4rem;font-size:.75rem;flex:1;min-width:0';

    var del = document.createElement('button');
    del.type = 'button'; del.textContent = '×'; del.title = 'Supprimer';
    del.style.cssText = 'background:rgba(200,86,30,.15);color:#C8561E;border:none;border-radius:4px;width:22px;height:22px;font-size:.85rem;cursor:pointer;flex-shrink:0;line-height:1';
    del.addEventListener('click', function(){ row.remove(); });

    row.appendChild(iOpen); row.appendChild(sep); row.appendChild(iClose); row.appendChild(del);
    container.appendChild(row);
    iOpen.focus();
}
</script>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>