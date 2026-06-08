<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/shipping.php';
security_headers();
$cfg  = cfg_get();
$B    = base_path();
$pt   = 'Commander';
$cur  = 'checkout';

// Vérifications
$no_delivery = empty($cfg['delivery_enabled']);
$no_pickup   = empty($cfg['pickup_enabled']);
$no_shipping = $no_delivery && $no_pickup;
$stripe_ok   = !empty($cfg['stripe_pk']) && !empty($cfg['stripe_enabled']);
$paypal_ok   = !empty($cfg['paypal_client_id']) && !empty($cfg['paypal_enabled']);
$manual_ok   = !empty($cfg['manual_payment_enabled']);
$any_payment = $stripe_ok || $paypal_ok || $manual_ok;
$intl_enabled= !empty($cfg['shipping_international_enabled']);

include ROOT . '/includes/header.php';

// Lire les tranches pour les passer au JS
$tiers_fr = json_decode($cfg['shipping_tiers_fr'] ?? '[]', true) ?: [];
$tiers_a  = json_decode($cfg['shipping_tiers_intl_a'] ?? '[]', true) ?: [];
$tiers_b  = json_decode($cfg['shipping_tiers_intl_b'] ?? '[]', true) ?: [];
$tiers_c  = json_decode($cfg['shipping_tiers_intl_c'] ?? '[]', true) ?: [];
?>

<section class="page-hero" style="padding:4rem 0 2rem">
  <div class="wrap">
    <h1 style="font-size:2rem">Commander</h1>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <?php if ($no_shipping): ?>
    <div class="editor-wrap" style="text-align:center;padding:3rem">
      <strong>Modes de livraison non configurés</strong><br>
      <small>Activez la livraison ou le retrait dans Admin → Paramètres.</small>
    </div>
    <?php elseif (!$any_payment): ?>
    <div class="editor-wrap" style="text-align:center;padding:3rem">
      <strong>💳 Aucun moyen de paiement configuré</strong><br>
      <a href="<?= $B ?>admin/payment.php" style="color:var(--or)">Configurer →</a>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 360px;gap:3rem;align-items:start">

      <!-- FORMULAIRE -->
      <div>
        <form id="checkoutForm" novalidate>
          <?= csrf_field() ?>

          <!-- MODE LIVRAISON -->
          <div class="editor-wrap" style="margin-bottom:1.2rem">
            <h3 style="font-family:var(--f1);font-size:1rem;margin-bottom:.8rem">Livraison</h3>
            <div style="display:flex;flex-direction:column;gap:.4rem">
              <?php if (!$no_delivery): ?>
              <label style="display:flex;align-items:center;gap:.7rem;padding:.7rem;border:2px solid var(--gy3);border-radius:var(--r);cursor:pointer" id="lbl_delivery">
                <input type="radio" name="delivery_mode" value="delivery" checked onchange="onModeChange()">
                <div>
                  <strong>📦 Livraison à domicile</strong>
                  <div id="ship_label" style="font-size:.75rem;color:var(--gy2)">Calcul en cours…</div>
                </div>
              </label>
              <?php endif; ?>
              <?php if (!$no_pickup): ?>
              <label style="display:flex;align-items:center;gap:.7rem;padding:.7rem;border:2px solid var(--gy3);border-radius:var(--r);cursor:pointer" id="lbl_pickup">
                <input type="radio" name="delivery_mode" value="pickup" <?= $no_delivery?'checked':'' ?> onchange="onModeChange()">
                <div>
                  <strong>🏪 Retrait en boutique — Gratuit</strong>
                  <div style="font-size:.75rem;color:var(--gy2)"><?= e($cfg['pickup_address']??'') ?></div>
                </div>
              </label>
              <?php endif; ?>
            </div>
          </div>

          <!-- COORDONNÉES -->
          <div class="editor-wrap" style="margin-bottom:1.2rem">
            <h3 style="font-family:var(--f1);font-size:1rem;margin-bottom:.8rem">Vos coordonnées</h3>
            <div class="frm-row">
              <div class="frm-g"><label class="frm-l">Prénom *</label><input class="frm-i" id="fname" name="fname" required maxlength="80"></div>
              <div class="frm-g"><label class="frm-l">Nom *</label><input class="frm-i" id="lname" name="lname" required maxlength="80"></div>
            </div>
            <div class="frm-row">
              <div class="frm-g"><label class="frm-l">Email *</label><input class="frm-i" type="email" id="email" name="email" required maxlength="150"></div>
              <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" type="tel" id="phone" name="phone" maxlength="20"></div>
            </div>
          </div>

          <!-- ADRESSE (masquée en retrait) -->
          <div class="editor-wrap" id="addrBlock" style="margin-bottom:1.2rem">
            <h3 style="font-family:var(--f1);font-size:1rem;margin-bottom:.8rem">Adresse de livraison</h3>

            <!-- Pays en premier = détermine les frais -->
            <div class="frm-g">
              <label class="frm-l">Pays</label>
              <select class="frm-i" id="country" name="country" onchange="onCountryChange()">
                <?php foreach (shipping_countries() as $code => $label):
                  if (str_starts_with($code, '_')) {
                    echo '<option disabled>'.$label.'</option>';
                    continue;
                  }
                  if ($code === 'FR' || $intl_enabled):
                    $sel = $code === 'FR' ? 'selected' : '';
                    echo "<option value=\"$code\" $sel>".htmlspecialchars($label).'</option>';
                  endif;
                endforeach; ?>
              </select>
            </div>

            <!-- Alerte international bloqué -->
            <div id="intlBlockMsg" style="display:none;background:rgba(200,86,30,.07);border:1px solid var(--or);border-radius:var(--r);padding:.7rem 1rem;font-size:.82rem;margin-bottom:.6rem">
              ⚠️ <?= e($cfg['shipping_intl_blocked_msg'] ?? 'Les livraisons hors France ne sont actuellement pas disponibles.') ?>
            </div>

            <div class="frm-g"><label class="frm-l">Adresse *</label><input class="frm-i" id="addr" name="addr" required maxlength="200"></div>
            <div class="frm-row">
              <div class="frm-g"><label class="frm-l">Code postal *</label><input class="frm-i" id="zip" name="zip" required maxlength="10"></div>
              <div class="frm-g"><label class="frm-l">Ville *</label><input class="frm-i" id="city" name="city" required maxlength="100"></div>
            </div>
          </div>

          <!-- PAIEMENT -->
          <div class="editor-wrap" style="margin-bottom:1.2rem">
            <h3 style="font-family:var(--f1);font-size:1rem;margin-bottom:.8rem">Paiement</h3>
            <?php if ($stripe_ok): ?>
            <div id="stripeWrap">
              <div id="payment-element" style="margin-bottom:1rem"></div>
            </div>
            <?php endif; ?>
            <?php if ($paypal_ok): ?>
            <div id="paypalWrap" style="margin-bottom:.6rem">
              <div id="paypal-button-container"></div>
            </div>
            <?php endif; ?>
            <?php if ($manual_ok): ?>
            <label style="display:flex;align-items:center;gap:.7rem;padding:.7rem;border:2px solid var(--gy3);border-radius:var(--r);cursor:pointer;margin-top:.5rem">
              <input type="radio" name="payment_method" value="manual" <?= !$stripe_ok&&!$paypal_ok?'checked':'' ?>>
              <div><strong>🏦 <?= e($cfg['manual_payment_label']??'Paiement manuel') ?></strong>
                <div style="font-size:.75rem;color:var(--gy2)"><?= e($cfg['manual_payment_instructions']??'') ?></div>
              </div>
            </label>
            <?php endif; ?>
          </div>

          <button type="submit" id="payBtn" class="btn btn-or" style="width:100%;justify-content:center;font-size:1rem;padding:.9rem">
            Confirmer la commande
          </button>
          <div id="checkoutErr" style="color:var(--or);font-size:.82rem;margin-top:.5rem;text-align:center;display:none"></div>
        </form>
      </div>

      <!-- RÉCAPITULATIF -->
      <div style="position:sticky;top:calc(var(--hh)+1rem)">
        <div class="editor-wrap">
          <h3 style="font-family:var(--f1);font-size:1rem;margin-bottom:1rem">Votre commande</h3>
          <div id="summaryItems" style="font-size:.85rem;border-bottom:1px solid var(--gy3);padding-bottom:.8rem;margin-bottom:.8rem"></div>
          <div id="summaryTotals" style="font-size:.88rem"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- Données configuration pour JS -->
<script>
const CHECKOUT_CFG = {
  stripe_pk:    <?= json_encode($cfg['stripe_pk'] ?? '') ?>,
  stripe_ok:    <?= $stripe_ok ? 'true' : 'false' ?>,
  paypal_id:    <?= json_encode($cfg['paypal_client_id'] ?? '') ?>,
  paypal_ok:    <?= $paypal_ok ? 'true' : 'false' ?>,
  manual_ok:    <?= $manual_ok ? 'true' : 'false' ?>,
  intl_enabled: <?= $intl_enabled ? 'true' : 'false' ?>,
  intl_msg:     <?= json_encode($cfg['shipping_intl_blocked_msg'] ?? '') ?>,
  free_from:    <?= (float)($cfg['shipping_free_from'] ?? 35) ?>,
  packaging_g:  <?= (int)($cfg['shipping_packaging_g'] ?? 150) ?>,
  use_weight:   <?= !empty($cfg['shipping_use_weight']) ? 'true' : 'false' ?>,
  tiers_fr:     <?= json_encode($tiers_fr) ?>,
  tiers_a:      <?= json_encode($tiers_a) ?>,
  tiers_b:      <?= json_encode($tiers_b) ?>,
  tiers_c:      <?= json_encode($tiers_c) ?>,
  ship_fr:      <?= (float)($cfg['shipping_cost'] ?? 4.90) ?>,
  ship_a:       <?= (float)($cfg['shipping_intl_zone_a'] ?? 14.90) ?>,
  ship_b:       <?= (float)($cfg['shipping_intl_zone_b'] ?? 22.50) ?>,
  ship_c:       <?= (float)($cfg['shipping_intl_zone_c'] ?? 32.00) ?>,
  csrf:         <?= json_encode($_SESSION['_csrf'] ?? '') ?>,
  base:         <?= json_encode($B) ?>,
};

// Zones géographiques
const ZONE_A = ['DE','AT','BE','ES','IT','LU','NL','PT','GR','IE','PL','SE','DK','FI',
                'CZ','HU','RO','BG','SK','SI','HR','EE','LV','LT','MT','CY','CH','MC','AD','LI','GB'];
const ZONE_B = ['AL','RS','XK','BA','MK','ME','MD','UA','BY','NO','IS','MA','DZ','TN','TR','AM','AZ','GE'];

function getZone(cc) {
  if (cc === 'FR') return 'FR';
  if (ZONE_A.includes(cc)) return 'A';
  if (ZONE_B.includes(cc)) return 'B';
  return 'C';
}

function costFromTiers(tiers, weightG) {
  if (!tiers || !tiers.length) return null;
  for (const t of tiers) {
    if (weightG <= t.max_g) return t.price;
  }
  return tiers[tiers.length - 1].price;
}

function calcCartWeight() {
  const _c = window.cart || [];
  if (!_c.length) return CHECKOUT_CFG.packaging_g;
  let total = CHECKOUT_CFG.packaging_g;
  const prods = window._prodWeights || {};
  for (const item of _c) {
    if (item.id && item.id.startsWith('gc-')) continue;
    const wg = prods[item.id] || item.weight_g || 250;
    total += wg * (item.qty || 1);
  }
  return total;
}

function calcShipping() {
  const mode    = document.querySelector('[name=delivery_mode]:checked')?.value || 'delivery';
  const country = document.getElementById('country')?.value || 'FR';
  if (mode === 'pickup') return { cost: 0, blocked: false, label: 'Retrait gratuit' };

  const zone = getZone(country);
  if (zone !== 'FR' && !CHECKOUT_CFG.intl_enabled) {
    return { cost: 0, blocked: true, label: CHECKOUT_CFG.intl_msg || 'Livraisons hors France non disponibles.' };
  }

  const weightG  = CHECKOUT_CFG.use_weight ? calcCartWeight() : 0;
  const _cartData = window.cart || [];
  const subTotal = _cartData.reduce((s,i)=>s+i.price*(i.qty||1),0);

  let cost;
  if (CHECKOUT_CFG.use_weight && weightG > 0) {
    const tiers = { FR: CHECKOUT_CFG.tiers_fr, A: CHECKOUT_CFG.tiers_a,
                    B: CHECKOUT_CFG.tiers_b, C: CHECKOUT_CFG.tiers_c }[zone];
    cost = costFromTiers(tiers, weightG);
    if (cost === null) {
      cost = { FR: CHECKOUT_CFG.ship_fr, A: CHECKOUT_CFG.ship_a,
               B: CHECKOUT_CFG.ship_b, C: CHECKOUT_CFG.ship_c }[zone] || CHECKOUT_CFG.ship_c;
    }
  } else {
    cost = { FR: CHECKOUT_CFG.ship_fr, A: CHECKOUT_CFG.ship_a,
             B: CHECKOUT_CFG.ship_b, C: CHECKOUT_CFG.ship_c }[zone] || CHECKOUT_CFG.ship_c;
  }

  // Brexit GB
  if (country === 'GB') cost += 3;

  // Livraison offerte France
  if (zone === 'FR' && subTotal >= CHECKOUT_CFG.free_from && CHECKOUT_CFG.free_from > 0) {
    return { cost: 0, blocked: false,
             label: 'Offerte dès '+CHECKOUT_CFG.free_from.toLocaleString('fr-FR')+'€' };
  }

  const wLabel = (CHECKOUT_CFG.use_weight && weightG > 0) ? ' ('+weightG+'g)' : '';
  const fmt    = cost.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2});
  return { cost, blocked: false, label: fmt+' €'+wLabel };
}

function renderSummary() {
  const items   = document.getElementById('summaryItems');
  const totals  = document.getElementById('summaryTotals');
  if (!items || !totals) return;

  // Panier depuis localStorage (clé identique à main.js)
  const CART_KEY  = 'cafe_cart_v2';
  const PROMO_KEY = 'cafe_promo_v1';
  const localCart  = (() => { try { return JSON.parse(localStorage.getItem(CART_KEY))||[]; } catch(e){ return []; } })();
  const activeCart = (window.cart && window.cart.length) ? window.cart : localCart;

  if (!activeCart.length) {
    items.innerHTML = '<div style="color:var(--gy2);font-size:.82rem;text-align:center;padding:.5rem">Votre panier est vide</div>';
    totals.innerHTML = '';
    return;
  }

  const sub = activeCart.reduce((s,i)=>s+i.price*(i.qty||1),0);

  // Code promo depuis localStorage (persiste entre les pages)
  const promo = (() => { try { return JSON.parse(localStorage.getItem(PROMO_KEY))||null; } catch(e){ return null; } })();
  let disc = 0;
  if (promo) {
    disc = promo.type === 'percent'
      ? Math.round(sub * (promo.value||0) / 100 * 100) / 100
      : Math.min(sub, promo.discount||0);
  }
  const ship   = calcShipping();
  const total  = Math.max(0, sub - disc) + (ship.blocked ? 0 : ship.cost);

  items.innerHTML = activeCart.map(i =>
    `<div style="display:flex;justify-content:space-between;margin-bottom:.3rem">
      <span>${i.emoji||'☕'} ${i.name} ×${i.qty||1}</span>
      <span>${(i.price*(i.qty||1)).toLocaleString('fr-FR',{minimumFractionDigits:2})} €</span>
    </div>`).join('');

  const discRow = disc > 0
    ? `<div style="display:flex;justify-content:space-between;margin-bottom:.3rem;color:#2A7A2A">
        <span>🏷 Remise (${promo?.code||''})</span>
        <span>−${disc.toLocaleString('fr-FR',{minimumFractionDigits:2})} €</span>
      </div>` : '';

  const shipRow = ship.blocked
    ? `<div style="color:var(--or);font-size:.78rem;margin:.3rem 0">${ship.label}</div>`
    : `<div style="display:flex;justify-content:space-between;margin-bottom:.3rem">
        <span style="color:var(--gy2)">Livraison</span>
        <span>${ship.cost===0?'<span style="color:var(--vr2)">Offerte</span>':ship.cost.toLocaleString('fr-FR',{minimumFractionDigits:2})+' €'}</span>
      </div>`;

  totals.innerHTML = discRow + shipRow +
    `<div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem;padding-top:.5rem;border-top:2px solid var(--bk)">
      <span>Total</span>
      <span>${total.toLocaleString('fr-FR',{minimumFractionDigits:2})} €</span>
    </div>`;

  // Mettre à jour le label livraison dans le form
  const shipLabel = document.getElementById('ship_label');
  if (shipLabel) shipLabel.textContent = ship.blocked ? ship.label : 'Colissimo — '+ship.label;

  // Bloquer le bouton si international désactivé
  const btn = document.getElementById('payBtn');
  const errDiv = document.getElementById('checkoutErr');
  if (btn) btn.disabled = ship.blocked;
  if (errDiv) { errDiv.textContent = ship.blocked ? ship.label : ''; errDiv.style.display = ship.blocked ? 'block' : 'none'; }

  // Alerte international bloqué
  const alertEl = document.getElementById('intlBlockMsg');
  if (alertEl) alertEl.style.display = ship.blocked ? 'block' : 'none';
}

function onModeChange() {
  const mode = document.querySelector('[name=delivery_mode]:checked')?.value || 'delivery';
  const addr = document.getElementById('addrBlock');
  if (addr) {
    addr.style.display = mode === 'pickup' ? 'none' : '';
    // Activer/désactiver required selon le mode
    addr.querySelectorAll('[required]').forEach(function(el) {
      el.required = mode !== 'pickup';
    });
  }
  renderSummary();
}

function onCountryChange() { renderSummary(); }

// Charger les poids produits depuis l'API ou le cart
window._prodWeights = {};
<?php
$prods_w = [];
foreach (db('products') as $p) {
    $prods_w[$p['id']] = (int)($p['weight_g'] ?? 250);
}
echo 'window._prodWeights = '.json_encode($prods_w).";\n";
?>

document.addEventListener('DOMContentLoaded', function() {
  // Re-valider le code promo au chargement (peut avoir expiré ou atteint sa limite)
  const savedPromo = (() => { try { return JSON.parse(localStorage.getItem('cafe_promo_v1'))||null; } catch(e){ return null; } })();
  if (savedPromo && savedPromo.code) {
    fetch(CHECKOUT_CFG.base + 'api/check_promo.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ code: savedPromo.code, total: 9999 })
    }).then(r => r.json()).then(d => {
      if (!d.ok) {
        // Code invalide ou expiré → supprimer du localStorage
        localStorage.removeItem('cafe_promo_v1');
        if (typeof window.clearPromo === 'function') window.clearPromo();
        console.info('Code promo expiré ou invalide, supprimé.');
      }
      renderSummary();
    }).catch(() => renderSummary());
  } else {
    renderSummary();
  }
  // Réécouter si le panier change depuis une autre page
  window.addEventListener('storage', function(e) {
    if (e.key && e.key.includes('cafe_cart')) renderSummary();
  });
  // Initialiser Stripe
  <?php if ($stripe_ok): ?>
  initStripeCheckout();
  <?php endif; ?>
  // Initialiser PayPal
  <?php if ($paypal_ok): ?>
  loadPayPal();
  <?php endif; ?>
});

// Écouter les changements de panier
document.addEventListener('cartUpdated', renderSummary);

// Soumission du form
document.getElementById('checkoutForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const ship = calcShipping();
  if (ship.blocked) return;

  const _savedPromo = (() => { try { return JSON.parse(localStorage.getItem('cafe_promo_v1'))||null; } catch(e){ return null; } })();
  const data = {
    cart:          window.cart || [],
    delivery_mode: document.querySelector('[name=delivery_mode]:checked')?.value || 'delivery',
    country:       document.getElementById('country')?.value || 'FR',
    promo_code:    _savedPromo?.code || null,
    fname:         document.getElementById('fname')?.value || '',
    lname:         document.getElementById('lname')?.value || '',
    email:         document.getElementById('email')?.value || '',
    phone:         document.getElementById('phone')?.value || '',
    addr:          document.getElementById('addr')?.value || '',
    zip:           document.getElementById('zip')?.value || '',
    city:          document.getElementById('city')?.value || '',
    _csrf:         CHECKOUT_CFG.csrf,
  };

  if (!data.fname || !data.lname || !data.email) {
    const err = document.getElementById('checkoutErr');
    if (err) { err.textContent = 'Veuillez remplir tous les champs obligatoires.'; err.style.display = 'block'; }
    return;
  }

  // Déterminer la méthode de paiement
  const selectedMethod = document.querySelector('[name=payment_method]:checked')?.value;
  
  // Manuel: radio sélectionné OU c'est la seule méthode disponible
  const isManual = selectedMethod === 'manual' || 
                   (CHECKOUT_CFG.manual_ok && !CHECKOUT_CFG.stripe_ok && !CHECKOUT_CFG.paypal_ok);
  
  if (isManual) {
    await submitManualOrder(data);
    return;
  }

  // Stripe
  if (CHECKOUT_CFG.stripe_ok && _stripeReady) {
    await submitStripePayment(data);
    return;
  }
  
  // Si rien ne correspond, afficher une erreur claire
  if (CHECKOUT_CFG.manual_ok) {
    showErr('Veuillez sélectionner un mode de paiement.');
  } else {
    showErr('Aucun mode de paiement disponible. Contactez-nous.');
  }
});

async function submitManualOrder(data) {
  const btn = document.getElementById('payBtn');
  btn.disabled = true; btn.textContent = 'Envoi…';
  try {
    const res = await fetch(CHECKOUT_CFG.base + 'api/manual_order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(data),
    });
    if (!res.ok) {
      showErr('Erreur serveur (' + res.status + '). Réessayez.');
      btn.disabled = false; btn.textContent = 'Confirmer la commande';
      return;
    }
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch(e) {
      showErr('Réponse invalide du serveur. Réessayez.');
      btn.disabled = false; btn.textContent = 'Confirmer la commande';
      return;
    }
    if (json.success) {
      try { localStorage.removeItem('cafe_cart_v2'); localStorage.removeItem('cafe_promo_v1'); } catch(e) {}
      window.location = CHECKOUT_CFG.base + 'pages/order_success.php?id=' + json.order_id;
    } else {
      showErr(json.error || 'Erreur lors de la commande.');
      btn.disabled = false; btn.textContent = 'Confirmer la commande';
    }
  } catch(e) {
    showErr('Erreur réseau: ' + e.message);
    btn.disabled = false; btn.textContent = 'Confirmer la commande';
  }
}

function showErr(msg) {
  const err = document.getElementById('checkoutErr');
  if (err) { err.textContent = msg; err.style.display = 'block'; }
}

<?php if ($stripe_ok): ?>
let _stripe      = null;
let _stripeEl    = null;
let _intentId    = null;
let _stripeReady = false;

// ÉTAPE 1: Dès le chargement, créer l'intent et monter le widget carte
async function initStripeCheckout() {
  if (!CHECKOUT_CFG.stripe_pk) return;
  const _savedPromo = (() => { try { return JSON.parse(localStorage.getItem('cafe_promo_v1'))||null; } catch(e){ return null; } })();
  const CART_KEY = 'cafe_cart_v2';
  const cart = (() => { try { return JSON.parse(localStorage.getItem(CART_KEY))||[]; } catch(e){ return []; } })();
  if (!cart.length) return;

  try {
    const r = await fetch(CHECKOUT_CFG.base + 'api/stripe_intent.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        cart, delivery_mode: 'delivery', country: 'FR',
        promo_code: _savedPromo?.code || null,
        _csrf: CHECKOUT_CFG.csrf,
        _preflight: true, // Signal: juste créer l'intent, pas encore la commande
      }),
    });
    const d = await r.json();
    if (!d.ok || !d.client_secret) return;

    _intentId = d.intent_id;
    _stripe   = Stripe(CHECKOUT_CFG.stripe_pk);
    _stripeEl = _stripe.elements({
      clientSecret: d.client_secret,
      appearance: { theme: 'stripe', variables: { colorPrimary: '#C8561E' } }
    });
    const payEl = _stripeEl.create('payment');
    payEl.mount('#payment-element');
    document.getElementById('stripeWrap').style.display = 'block';
    _stripeReady = true;
  } catch(e) {
    console.error('Stripe init:', e);
  }
}

// ÉTAPE 2: Au submit, juste confirmer avec les données déjà saisies dans le widget
async function submitStripePayment(data) {
  if (!_stripeReady || !_stripe || !_stripeEl || !_intentId) {
    showErr('Le formulaire de paiement n'est pas encore chargé. Patientez quelques secondes.');
    return;
  }
  const btn = document.getElementById('payBtn');
  btn.disabled = true; btn.textContent = 'Traitement…';
  try {
    // Mettre à jour l'intent avec les vraies infos de livraison (pays, promo, etc.)
    const r2 = await fetch(CHECKOUT_CFG.base + 'api/stripe_intent.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({...data, _update_intent: _intentId}),
    });
    const d2 = await r2.json();
    if (!d2.ok) { showErr(d2.error || 'Erreur de calcul.'); btn.disabled = false; btn.textContent = 'Confirmer la commande'; return; }

    const { error } = await _stripe.confirmPayment({
      elements: _stripeEl,
      confirmParams: {
        return_url: window.location.origin + CHECKOUT_CFG.base + 'pages/order_success.php?id=' + _intentId,
        payment_method_data: {
          billing_details: {
            name:  (data.fname||'') + ' ' + (data.lname||''),
            email: data.email || '',
          }
        }
      },
    });
    if (error) { showErr(error.message); btn.disabled = false; btn.textContent = 'Confirmer la commande'; }
  } catch(e) { showErr('Erreur réseau.'); btn.disabled = false; btn.textContent = 'Confirmer la commande'; }
}
<?php endif; ?>
</script>

<?php if ($stripe_ok): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>
<?php if ($paypal_ok): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= e($cfg['paypal_client_id']) ?>&currency=<?= e($cfg['currency']??'EUR') ?>"></script>
<script>
function loadPayPal() {
  if (!window.paypal) return;
  paypal.Buttons({
    style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },

    createOrder: async function() {
      const _savedPromo = (() => { try { return JSON.parse(localStorage.getItem('cafe_promo_v1'))||null; } catch(e){ return null; } })();
      const CART_KEY = 'cafe_cart_v2';
      const cart = (() => { try { return JSON.parse(localStorage.getItem(CART_KEY))||[]; } catch(e){ return []; } })();
      const country = document.getElementById('country')?.value || 'FR';
      const mode    = document.querySelector('[name=delivery_mode]:checked')?.value || 'delivery';

      try {
        const r = await fetch(CHECKOUT_CFG.base + 'api/paypal_capture.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: 'create',
            cart, country, delivery_mode: mode,
            promo_code: _savedPromo?.code || null,
            _csrf: CHECKOUT_CFG.csrf,
          }),
        });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch(je) {
          const msg = 'Réponse serveur invalide (PayPal create). Statut: ' + r.status;
          showErr(msg); throw new Error(msg);
        }
        if (d.id) return d.id;
        const errMsg = d.error || 'Erreur PayPal.';
        showErr(errMsg); throw new Error(errMsg);
      } catch(e) {
        if (document.getElementById('checkoutErr') && !document.getElementById('checkoutErr').textContent) {
          showErr('Erreur PayPal: ' + e.message);
        }
        throw e;
      }
    },

    onApprove: async function(ppData) {
      const _savedPromo = (() => { try { return JSON.parse(localStorage.getItem('cafe_promo_v1'))||null; } catch(e){ return null; } })();
      const CART_KEY = 'cafe_cart_v2';
      const cart = (() => { try { return JSON.parse(localStorage.getItem(CART_KEY))||[]; } catch(e){ return []; } })();
      try {
        const r = await fetch(CHECKOUT_CFG.base + 'api/paypal_capture.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action:       'capture',
            orderID:      ppData.orderID,
            cart,
            country:      document.getElementById('country')?.value || 'FR',
            delivery_mode:document.querySelector('[name=delivery_mode]:checked')?.value || 'delivery',
            promo_code:   _savedPromo?.code || null,
            fname:        document.getElementById('fname')?.value || '',
            lname:        document.getElementById('lname')?.value || '',
            email:        document.getElementById('email')?.value || '',
            phone:        document.getElementById('phone')?.value || '',
            addr:         document.getElementById('addr')?.value || '',
            zip:          document.getElementById('zip')?.value || '',
            city:         document.getElementById('city')?.value || '',
            _csrf:        CHECKOUT_CFG.csrf,
          }),
        });
        const d = await r.json();
        if (d.ok) {
          try { localStorage.removeItem('cafe_cart_v2'); localStorage.removeItem('cafe_promo_v1'); } catch(e) {}
          try { localStorage.removeItem('cafe_cart_v2'); localStorage.removeItem('cafe_promo_v1'); } catch(e) {}
          window.location = CHECKOUT_CFG.base + 'pages/order_success.php?id=' + (d.order_id || '');
        } else {
          showErr(d.error || 'Paiement PayPal échoué.');
        }
      } catch(e) { showErr('Erreur réseau PayPal.'); }
    },

    onError: function(err) {
      showErr('Erreur PayPal : ' + (err.message || err));
    }
  }).render('#paypal-button-container');
}
</script>
<?php endif; ?>

<?php include ROOT . '/includes/footer.php'; ?>
