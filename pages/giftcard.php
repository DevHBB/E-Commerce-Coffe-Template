<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();
$cfg  = cfg_get();
$B    = base_path();
$ecom = !empty($cfg['ecommerce_enabled']);
$pt   = 'Carte Cadeau';
$cur  = 'giftcard';
include ROOT . '/includes/header.php';

$wks = array_values(array_filter(db('workshops'), fn($w) => $w['active'] ?? true));
?>

<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl">Idée cadeau</p>
    <h1>Carte <em>Cadeau</em></h1>
    <p>Offrez une expérience café unique. Boutique, atelier, dégustation — à eux de choisir.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start">

      <!-- Formulaire carte cadeau -->
      <div class="fade-up">
        <h2 style="font-family:var(--f1);font-size:1.4rem;margin-bottom:1.5rem">Configurer la carte</h2>

        <!-- Type -->
        <div class="frm-g" style="margin-bottom:1.5rem">
          <label class="frm-l">Type de carte cadeau</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem" id="gcTypeGrid">
            <label class="gc-type-lbl gc-type-active" data-type="libre">
              <input type="radio" name="gc_type_radio" value="libre" checked style="display:none">
              <div style="font-size:1.5rem;margin-bottom:.3rem">🛒</div>
              <strong style="font-size:.85rem">Libre</strong>
              <p style="font-size:.72rem;color:var(--gy2);margin:.2rem 0 0">Utilisable sur tout le site</p>
            </label>
            <?php foreach ($wks as $wk): ?>
            <label class="gc-type-lbl" data-type="<?= e($wk['id']) ?>" data-price="<?= (float)($wk['price']??0) ?>">
              <input type="radio" name="gc_type_radio" value="<?= e($wk['id']) ?>" style="display:none">
              <div style="font-size:1.5rem;margin-bottom:.3rem"><?= e($wk['emoji']??'☕') ?></div>
              <strong style="font-size:.85rem"><?= e($wk['name']) ?></strong>
              <p style="font-size:.72rem;color:var(--gy2);margin:.2rem 0 0"><?= number_format((float)($wk['price']??0),2,',',' ') ?> €</p>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Montant -->
        <div class="frm-g" id="gcAmountBlock">
          <label class="frm-l">Montant (€)</label>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.5rem">
            <?php foreach ([25,50,75,100,150,200] as $a): ?>
            <button type="button" class="act-btn gc-amt-btn" data-amt="<?= $a ?>"><?= $a ?> €</button>
            <?php endforeach; ?>
          </div>
          <input class="frm-i" type="number" id="gcAmount" min="5" max="500" step="5"
            value="50" placeholder="Montant libre (min. 5 €)" style="max-width:160px">
        </div>

        <!-- Infos destinataire -->
        <div style="background:var(--cr);border-radius:var(--r);padding:1rem;margin-bottom:1rem">
          <p style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gy2);margin-bottom:.7rem">Informations (optionnelles)</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:.6rem">
            <div class="frm-g" style="margin:0"><label class="frm-l">De la part de</label>
              <input class="frm-i" id="gcFrom" maxlength="80" placeholder="Votre prénom"></div>
            <div class="frm-g" style="margin:0"><label class="frm-l">Pour</label>
              <input class="frm-i" id="gcTo" maxlength="80" placeholder="Prénom du destinataire"></div>
          </div>
          <div class="frm-g" style="margin-bottom:.6rem"><label class="frm-l">Email destinataire <small style="color:var(--gy2)">(pour recevoir le code)</small></label>
            <input class="frm-i" type="email" id="gcEmail" maxlength="150" placeholder="destinataire@email.fr"></div>
          <div class="frm-g" style="margin:0"><label class="frm-l">Message</label>
            <textarea class="form-ta" id="gcMessage" maxlength="500" rows="2" placeholder="Votre message personnel..."></textarea></div>
        </div>

        <!-- Bouton ajouter au panier -->
        <?php if ($ecom): ?>
        <button type="button" id="gcAddToCart" class="btn btn-or" style="width:100%;justify-content:center;font-size:1rem;padding:.9rem">
          🛒 Ajouter au panier — <span id="gcPriceDisplay">50,00 €</span>
        </button>
        <p style="font-size:.72rem;color:var(--gy2);margin-top:.5rem;text-align:center">
          Paiement sécurisé via Stripe — Carte, Apple Pay, Google Pay
        </p>
        <?php else: ?>
        <div style="background:var(--cr);border-radius:var(--r);padding:1rem;text-align:center;font-size:.85rem;color:var(--gy2)">
          La boutique est actuellement fermée.
        </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar info -->
      <div class="fade-up" data-delay="2">
        <div style="background:var(--bk);border-radius:14px;padding:2rem;color:#fff;position:sticky;top:calc(var(--hh)+1rem)">
          <h3 style="font-family:var(--f1);color:#fff;margin-bottom:1.2rem">🎁 Bon à savoir</h3>
          <?php foreach ([
            ['✓','Valable 1 an','À utiliser dans les 12 mois'],
            ['📧','Envoi par email','Code unique après paiement'],
            ['🔄','Utilisable partout','Boutique, ateliers ou combiné'],
            ['💳','Paiement sécurisé','Stripe — certifié PCI-DSS'],
          ] as [$ico,$titre,$desc]): ?>
          <div style="display:flex;gap:.9rem;padding:.7rem 0;border-bottom:1px solid rgba(255,255,255,.07)">
            <div style="color:var(--or)"><?= $ico ?></div>
            <div><strong style="display:block;font-size:.85rem;color:#fff"><?= $titre ?></strong>
              <span style="font-size:.76rem;color:rgba(255,255,255,.4)"><?= $desc ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
.gc-type-lbl {
  display:block;cursor:pointer;border:2px solid var(--gy3);border-radius:var(--r);
  padding:.8rem;text-align:center;transition:border .15s,background .15s;
}
.gc-type-lbl:hover { border-color:var(--or); }
.gc-type-active { border-color:var(--or) !important; background:rgba(200,86,30,.05); }
</style>

<script>
// Gestion du type de carte
let gcType = 'libre';
let gcWorkshopPrice = 0;

document.querySelectorAll('.gc-type-lbl').forEach(lbl => {
  lbl.addEventListener('click', function() {
    document.querySelectorAll('.gc-type-lbl').forEach(l => l.classList.remove('gc-type-active'));
    this.classList.add('gc-type-active');
    this.querySelector('input').checked = true;
    gcType = this.dataset.type;
    gcWorkshopPrice = parseFloat(this.dataset.price || 0);

    const amtBlock = document.getElementById('gcAmountBlock');
    const amtInput = document.getElementById('gcAmount');
    if (gcWorkshopPrice > 0) {
      // Atelier: montant fixé au prix de l'atelier
      amtBlock.style.opacity = '.5';
      amtInput.value = gcWorkshopPrice;
    } else {
      amtBlock.style.opacity = '1';
    }
    updateDisplay();
  });
});

// Boutons montants rapides
document.querySelectorAll('.gc-amt-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    if (gcWorkshopPrice > 0) return;
    document.getElementById('gcAmount').value = this.dataset.amt;
    updateDisplay();
  });
});

document.getElementById('gcAmount').addEventListener('input', updateDisplay);

function updateDisplay() {
  const amt = parseFloat(document.getElementById('gcAmount').value) || 0;
  const fmt = amt.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2});
  document.getElementById('gcPriceDisplay').textContent = fmt + ' €';
}
updateDisplay();

// Ajouter au panier
document.getElementById('gcAddToCart').addEventListener('click', function() {
  const amt = parseFloat(document.getElementById('gcAmount').value) || 0;
  if (amt < 5) {
    alert('Montant minimum : 5 €');
    return;
  }

  const gcId   = 'gc-' + Date.now();
  const gcFrom = document.getElementById('gcFrom').value.trim();
  const gcTo   = document.getElementById('gcTo').value.trim();
  const gcEmail= document.getElementById('gcEmail').value.trim();
  const gcMsg  = document.getElementById('gcMessage').value.trim();

  // Construire l'objet panier
  const item = {
    id:         gcId,
    name:       '🎁 Carte cadeau' + (gcTo ? ' pour ' + gcTo : ''),
    price:      amt,
    emoji:      '🎁',
    gc_from:    gcFrom,
    gc_to:      gcTo,
    gc_email:   gcEmail,
    gc_message: gcMsg,
    gc_type:    gcType,
  };

  // Utiliser addToCart du main.js
  if (typeof addToCart === 'function') {
    addToCart(item);
    // Feedback visuel
    const btn = this;
    const orig = btn.innerHTML;
    btn.innerHTML = '✓ Ajouté au panier !';
    btn.style.background = '#2A4C1E';
    setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
    // Ouvrir le panier
    if (typeof openCart === 'function') setTimeout(openCart, 300);
  }
});
</script>

<?php include ROOT . '/includes/footer.php'; ?>
