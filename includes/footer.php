<?php // includes/footer.php
$cfg  = $cfg  ?? cfg_get();
$sn   = $cfg['site_name'] ?? 'Café Maison';
$B    = $B    ?? base_path();

// Footer links config
$show = [
    'legal'    => $cfg['footer_show_legal']    ?? true,
    'cgv'      => $cfg['footer_show_cgv']      ?? true,
    'shipping' => $cfg['footer_show_shipping'] ?? true,
    'faq'      => $cfg['footer_show_faq']      ?? true,
    'story'    => ($cfg['footer_show_story']   ?? true) && ($cfg['page_story_enabled'] ?? false),
    'giftcard' => $cfg['footer_show_giftcard'] ?? true,
    'compare'  => $cfg['footer_show_compare']  ?? true,
];
$custom_links = json_decode($cfg['footer_custom_links'] ?? '[]', true) ?: [];
?>
<footer id="ftr">
  <div class="wrap">
    <div class="ftr-grid">
      <!-- Brand -->
      <div class="ftr-brand">
        <h3><?= e($sn) ?> <span>.</span></h3>
        <p><?= e($cfg['tagline'] ?? '') ?></p>
        <p style="margin-top:.6rem;font-size:.82rem"><?= e($cfg['address'] ?? '') ?></p>
        <div class="ftr-soc">
          <a href="<?= e($cfg['instagram'] ?? '#') ?>" class="soc" aria-label="Instagram" target="_blank" rel="noopener">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
          <a href="mailto:<?= e($cfg['email'] ?? '') ?>" class="soc" aria-label="Email">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </a>
        </div>
      </div>

      <!-- Navigation -->
      <div class="ftr-col">
        <h4>Navigation</h4>
        <a href="<?= $B ?>#about">La Maison</a>
        <?php if ($show['story']): ?><a href="<?= $B ?>pages/story.php"><?= e($cfg['page_story_title']??'Notre Histoire') ?></a><?php endif; ?>
        <a href="<?= $B ?>pages/shop.php">Boutique</a>
        <a href="<?= $B ?>pages/atelier.php">Atelier</a>
        <a href="<?= $B ?>pages/booking.php">Réserver</a>
        <a href="<?= $B ?>pages/blog.php">Journal</a>
        <?php if ($show['giftcard']): ?><a href="<?= $B ?>pages/giftcard.php">🎁 Carte cadeau</a><?php endif; ?>
        <a href="<?= $B ?>#contact">Contact</a>
      </div>

      <!-- Infos légales -->
      <div class="ftr-col">
        <h4>Informations</h4>
        <?php if ($show['legal']):    ?><a href="<?= $B ?>pages/legal.php">Mentions légales</a><?php endif; ?>
        <?php if ($show['cgv']):      ?><a href="<?= $B ?>pages/cgv.php">CGV</a><?php endif; ?>
        <?php if ($show['shipping']): ?><a href="<?= $B ?>pages/shipping.php">Livraison & Retours</a><?php endif; ?>
        <?php if ($show['faq']):      ?><a href="<?= $B ?>pages/faq.php">FAQ</a><?php endif; ?>
        <?php if ($show['compare']):  ?><a href="<?= $B ?>pages/compare.php">Comparer les cafés</a><?php endif; ?>
        <?php foreach ($custom_links as $cl): if (!empty($cl['label']) && !empty($cl['url'])): ?>
        <a href="<?= e($cl['url']) ?>"><?= e($cl['label']) ?></a>
        <?php endif; endforeach; ?>
      </div>

      <!-- Horaires + newsletter -->
      <div class="ftr-col">
        <h4>Horaires</h4>
        <p style="color:rgba(255,255,255,.45);font-size:.85rem;line-height:2"><?= nl2br(e(($cfg['hours_week']??'')."\n".($cfg['hours_weekend']??''))) ?></p>
        <p style="color:rgba(255,255,255,.45);font-size:.82rem;margin-top:.5rem"><?= e($cfg['phone']??'') ?></p>
        <p style="color:var(--or);font-size:.82rem;margin-top:.2rem"><?= e($cfg['email']??'') ?></p>
        <!-- Newsletter footer -->
        <div id="nlBlock" style="margin-top:1.2rem">
          <p style="font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.3);margin-bottom:.5rem">Newsletter</p>
          <div style="display:flex;gap:.4rem">
            <input type="email" id="nlEmail" placeholder="votre@email.fr" required maxlength="150"
              style="flex:1;padding:.45rem .7rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:var(--r);color:#fff;font-size:.8rem;min-width:0">
            <button id="nlBtn" onclick="submitNewsletter()"
              style="background:var(--or);color:#fff;border:none;padding:.45rem .9rem;border-radius:var(--r);font-size:.78rem;font-weight:600;cursor:pointer;white-space:nowrap">OK</button>
          </div>
          <div id="nlMsg" style="font-size:.82rem;margin-top:.6rem;display:none"></div>
        </div>
      </div>
    </div>

    <!-- Mentions légales entreprise -->
    <?php
    $has_legal = ($cfg['company_siret']??'')||($cfg['company_vat']??'')||($cfg['company_rcs']??'')||($cfg['company_legal']??'');
    if ($has_legal): ?>
    <div style="border-top:1px solid rgba(255,255,255,.05);padding:.8rem 0;margin-top:0;text-align:center;font-size:.65rem;color:rgba(255,255,255,.2);line-height:2">
      <?= e($cfg['company_name']?:$sn) ?>
      <?php if ($cfg['company_legal']??''): ?> · <?= e($cfg['company_legal']) ?><?php endif; ?>
      <?php if ($cfg['company_siret']??''): ?> · SIRET <?= e($cfg['company_siret']) ?><?php endif; ?>
      <?php if ($cfg['company_rcs']??''): ?> · <?= e($cfg['company_rcs']) ?><?php endif; ?>
      <?php if ($cfg['company_vat']??''): ?> · TVA <?= e($cfg['company_vat']) ?><?php endif; ?>
      <?php if ($cfg['company_capital']??''): ?> · Capital <?= e($cfg['company_capital']) ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="ftr-bot">
      <span>© <?= date('Y') ?> <?= e($sn) ?> — Tous droits réservés</span>
      <span>Torréfacteur artisanal · Paris <span style="color:var(--or)">♥</span></span>
    </div>
  </div>
</footer>

<!-- Cookie banner — bulle discrète bottom-right -->
<?php if (!empty($cfg['cookies_enabled'])): ?>
<div id="cookieBanner" style="display:none;position:fixed;bottom:1.2rem;right:1.2rem;z-index:2000;max-width:300px;width:calc(100vw - 2.4rem)">
  <div style="position:relative;background:#1a1a1a;border-radius:16px;padding:1.1rem 1.2rem;box-shadow:0 8px 40px rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.08)">
    <!-- Queue bulle -->
    <div style="position:absolute;bottom:-7px;right:24px;width:14px;height:14px;background:#1a1a1a;transform:rotate(45deg);border-right:1px solid rgba(255,255,255,.08);border-bottom:1px solid rgba(255,255,255,.08)"></div>
    <div style="display:flex;gap:.65rem;align-items:flex-start;margin-bottom:.8rem">
      <span style="font-size:1.1rem;flex-shrink:0;margin-top:.05rem">🍪</span>
      <p style="color:rgba(255,255,255,.75);font-size:.76rem;margin:0;line-height:1.55">
        <?= e($cfg['cookies_text'] ?? 'Ce site utilise des cookies pour améliorer votre expérience.') ?>
        <a href="<?= $B ?>pages/legal.php" style="color:var(--or)"> En savoir plus</a>
      </p>
    </div>
    <div style="display:flex;gap:.45rem">
      <button onclick="acceptCookies()" style="flex:1;background:var(--or);color:#fff;border:none;padding:.42rem;border-radius:8px;font-size:.74rem;font-weight:600;cursor:pointer">Accepter</button>
      <button onclick="rejectCookies()" style="flex:1;background:rgba(255,255,255,.06);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.1);padding:.42rem;border-radius:8px;font-size:.74rem;cursor:pointer">Refuser</button>
    </div>
  </div>
</div>
<script>
function ckHide(){var b=document.getElementById('cookieBanner');if(!b)return;b.style.transition='opacity .3s,transform .3s';b.style.opacity='0';b.style.transform='translateY(6px)';setTimeout(function(){b.style.display='none';},320);}
function acceptCookies(){localStorage.setItem('ck','1');ckHide();}
function rejectCookies(){localStorage.setItem('ck','0');ckHide();}
(function(){
  if(!localStorage.getItem('ck')){
    var b=document.getElementById('cookieBanner');
    if(!b)return;
    b.style.opacity='0';b.style.transform='translateY(8px)';
    b.style.display='block';
    setTimeout(function(){b.style.transition='opacity .5s,transform .5s';b.style.opacity='1';b.style.transform='translateY(0)';},600);
  }
})();
</script>
<?php endif; ?>

<script src="<?= $B ?>assets/js/main.js"></script>
<?php
$cfg_ft = cfg_get();
if (!empty($cfg_ft['captcha_site_key'])):
?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($cfg_ft['captcha_site_key']) ?>" async defer></script>
<script>
// reCAPTCHA v3 global — protège tous les forms avec class="needs-captcha"
window.RECAPTCHA_KEY = '<?= htmlspecialchars($cfg_ft['captcha_site_key']) ?>';
function getCaptchaToken(action) {
    return new Promise(function(resolve) {
        if (!window.RECAPTCHA_KEY || typeof grecaptcha === 'undefined') {
            resolve(''); return;
        }
        grecaptcha.ready(function() {
            grecaptcha.execute(window.RECAPTCHA_KEY, {action: action})
                .then(resolve)
                .catch(function() { resolve(''); });
        });
    });
}
// Intercepter tous les forms avec class="needs-captcha"
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form.needs-captcha').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!window.RECAPTCHA_KEY) return;
            var field = form.querySelector('[name="g-recaptcha-response"]');
            if (!field || field.value) return; // déjà rempli
            e.preventDefault();
            var action = form.dataset.captchaAction || 'form';
            var btn = form.querySelector('[type=submit]');
            if (btn) btn.disabled = true;
            getCaptchaToken(action).then(function(token) {
                if (field) field.value = token;
                if (btn) btn.disabled = false;
                form.submit();
            });
        });
    });
});
</script>
<?php endif; ?>
<script>
function submitNewsletter() {
  var email = document.getElementById('nlEmail');
  var btn   = document.getElementById('nlBtn');
  var msg   = document.getElementById('nlMsg');
  if (!email || !email.value || !email.validity.valid) {
    email.focus(); return;
  }
  btn.disabled = true; btn.textContent = '…';
  var fd = new FormData();
  fd.append('email', email.value);
  fd.append('_csrf', document.querySelector('meta[name=csrf]')?.content || '');
  fetch(window.SITE_BASE + 'api/newsletter_sub.php', { method: 'POST', body: fd })
    .then(function() {
      // Succès (on ne lit pas la réponse car c'est une redirection)
      document.getElementById('nlBlock').innerHTML =
        '<p style="font-size:.82rem;color:#6dbf7e">✓ Merci, vous êtes bien inscrit(e) !</p>';
    })
    .catch(function() {
      msg.style.display = 'block';
      msg.style.color = 'var(--or)';
      msg.textContent = 'Erreur. Réessayez.';
      btn.disabled = false; btn.textContent = 'OK';
    });
}
document.getElementById('nlEmail')?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { e.preventDefault(); submitNewsletter(); }
});
</script>
</body></html>
