<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/captcha.php';
require_once ROOT . '/includes/mailer.php';
security_headers();
$cfg = cfg_get();
$B   = base_path();

$wid      = clean($_GET['w'] ?? '', 30);
$sid      = clean($_GET['s'] ?? '', 30);
$sessions = db('sessions');
$workshops= db('workshops');

$wk_idx = [];
foreach ($workshops as $w) $wk_idx[$w['id']] = $w;

$avail = array_values(array_filter($sessions, function($s) use ($wid) {
    return ($s['active'] ?? true)
        && ($s['date'] ?? '') >= date('Y-m-d')
        && (!$wid || $s['workshop_id'] === $wid);
}));
usort($avail, fn($a,$b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time']));

// Compter les places occupées (par personnes, pas par réservations)
$bookings = db('bookings');
$bk_cts   = [];
foreach ($bookings as $b) {
    $s2 = $b['session_id'] ?? '';
    $bk_cts[$s2] = ($bk_cts[$s2] ?? 0) + ($b['status'] !== 'cancelled' ? (int)($b['guests']??1) : 0);
}

$stripe_ok   = !empty($cfg['stripe_pk']) && !empty($cfg['stripe_enabled']);
$manual_ok   = !empty($cfg['manual_payment_enabled']) || !empty($cfg['booking_paid']);
$booking_paid= !empty($cfg['booking_paid']);

$ok = false; $err = ''; $result_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!rate_ok('booking_'.$ip, 5, 300)) {
        $err = 'Trop de soumissions. Réessayez dans quelques minutes.';
    } else {
        $sess_id  = clean($_POST['session_id'] ?? '', 30);
        $fname    = clean($_POST['fname']       ?? '', 80);
        $lname    = clean($_POST['lname']       ?? '', 80);
        $email    = filter_var($_POST['email']  ?? '', FILTER_VALIDATE_EMAIL);
        $phone    = clean($_POST['phone']       ?? '', 20);
        $guests   = clean_int($_POST['guests']  ?? 1, 1, 20);
        $msg      = clean($_POST['message']     ?? '', 500);
        $nl       = isset($_POST['newsletter']);
        $terms    = isset($_POST['terms']);
        $pay_mode = clean($_POST['pay_mode'] ?? 'manual', 20); // stripe | manual (sur place)

        // Collecter les emails des participants supplémentaires
        $extra_emails = [];
        foreach ($_POST['extra_email'] ?? [] as $em) {
            $em = trim($em);
            if ($em && filter_var($em, FILTER_VALIDATE_EMAIL)) $extra_emails[] = $em;
        }

        $sess = null;
        foreach ($sessions as $s) { if ($s['id'] === $sess_id) { $sess = $s; break; } }

        if (!$fname || !$lname || !$email)   $err = 'Nom, prénom et email sont requis.';
        elseif (!$phone)                      $err = 'Le numéro de téléphone est requis.';
        elseif (!$terms)                      $err = 'Vous devez accepter les conditions générales.';
        elseif (!$sess)                       $err = 'Créneau introuvable.';

        if (!$err) {
            $booked_ct = $bk_cts[$sess_id] ?? 0;
            $seats     = (int)($sess['seats'] ?? 8);
            $remaining = max(0, $seats - $booked_ct);
            $wk_data   = $wk_idx[$sess['workshop_id'] ?? ''] ?? [];
            $price_u   = (float)(($sess['price_override']??0) ?: ($wk_data['price']??0));
            $total     = round($price_u * $guests, 2);

            // Calculer combien de places disponibles vs liste d'attente
            $confirmed_guests = min($guests, $remaining);
            $waitlist_guests  = max(0, $guests - $remaining);

            $bk_row = [
                'id'              => new_id(),
                'session_id'      => $sess_id,
                'workshop_id'     => $sess['workshop_id'] ?? '',
                'fname'           => $fname,
                'lname'           => $lname,
                'email'           => (string)$email,
                'phone'           => $phone,
                'guests'          => $guests,
                'confirmed_places'=> $confirmed_guests,
                'waitlist_places' => $waitlist_guests,
                'extra_emails'    => $extra_emails,
                'message'         => $msg,
                'status'          => $remaining === 0 ? 'waitlist' : 'pending',
                'pay_mode'        => $pay_mode,
                'price_unit'      => $price_u,
                'total'           => $total,
                'paid'            => false,
                'newsletter'      => $nl,
                'created'         => date('Y-m-d H:i'),
            ];

            $bk_all   = db('bookings');
            $bk_all[] = $bk_row;
            db_save('bookings', $bk_all);

            // Newsletter opt-in
            if ($nl) {
                $nl_all = db('newsletter');
                $exists = false;
                foreach ($nl_all as $sub) { if ($sub['email'] === (string)$email) { $exists = true; break; } }
                if (!$exists) {
                    $nl_all[] = ['id'=>new_id(),'email'=>(string)$email,'name'=>"$fname $lname",
                                 'source'=>'booking','date'=>date('Y-m-d'),'active'=>true];
                    db_save('newsletter', $nl_all);
                }
            }

            // Email selon le cas
            if ($remaining === 0) {
                // Tout le monde en liste d'attente
                mail_booking_waitlist($bk_row, $wk_data);
            } elseif ($waitlist_guests > 0) {
                // Mix: certains confirmés, d'autres en attente
                mail_booking_mixed($bk_row, $sess, $wk_data, $confirmed_guests, $waitlist_guests);
            } else {
                // Tous confirmés
                mail_booking($bk_row, $sess, $wk_data);
            }

            // Envoyer un email aux participants extras
            foreach ($extra_emails as $extra_em) {
                mail_booking_participant($extra_em, $bk_row, $sess, $wk_data);
            }

            $ok = true;
            $result_data = [
                'waitlist'        => $remaining === 0,
                'mixed'           => $waitlist_guests > 0 && $remaining > 0,
                'confirmed'       => $confirmed_guests,
                'waitlist_places' => $waitlist_guests,
                'total'           => $total,
                'price_u'         => $price_u,
                'guests'          => $guests,
                'pay_mode'        => $pay_mode,
                'booking'         => $bk_row,
                'workshop'        => $wk_data,
                'session'         => $sess,
            ];
        }
    }
}

require_once ROOT . '/includes/captcha.php';
$use_captcha = captcha_enabled($cfg);
$pt  = 'Réserver un atelier';
$cur = 'atelier';
include ROOT . '/includes/header.php';
?>

<?php if ($use_captcha): ?>
<?= captcha_script_tag($cfg) ?>
<?php endif; ?>
<?php if ($stripe_ok): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<section class="page-hero">
  <div class="wrap" style="position:relative;z-index:1">
    <p class="lbl">Réservation</p>
    <h1>Réserver un <em>atelier</em></h1>
    <p>Choisissez votre créneau et réservez en ligne en 2 minutes.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap">

    <?php if ($ok && $result_data): ?>
    <!-- ── CONFIRMATION ── -->
    <?php
    $rd = $result_data;
    $is_waitlist = $rd['waitlist'];
    $is_mixed    = $rd['mixed'];
    ?>
    <div style="max-width:600px;margin:0 auto;padding:3rem 2rem;text-align:center;background:<?= $is_waitlist?'rgba(200,86,30,.05)':($is_mixed?'rgba(200,150,30,.05)':'rgba(42,76,30,.05)') ?>;border:1px solid <?= $is_waitlist?'var(--or)':($is_mixed?'#C87820':'var(--vr3)') ?>;border-radius:16px">
      <div style="font-size:3.5rem;margin-bottom:1rem"><?= $is_waitlist?'📋':($is_mixed?'⚠️':'🎉') ?></div>
      <h2 style="font-family:var(--f1)">
        <?= $is_waitlist ? "Liste d'attente" : ($is_mixed ? "Réservation partielle" : "Réservation enregistrée !") ?>
      </h2>

      <?php if ($is_waitlist): ?>
      <p style="color:var(--gy);margin-bottom:1.5rem">
        Cet atelier est complet. Vous êtes inscrit(e) sur la liste d'attente.<br>
        Vous serez contacté(e) dès qu'une place se libère.
      </p>
      <?php elseif ($is_mixed): ?>
      <p style="color:var(--gy);margin-bottom:1.5rem">
        Vous avez demandé <strong><?= $rd['guests'] ?> place<?= $rd['guests']>1?'s':'' ?></strong>,
        mais seulement <strong><?= $rd['confirmed'] ?> place<?= $rd['confirmed']>1?'s':'' ?></strong>
        <?= $rd['confirmed']>1?'sont disponibles':'est disponible' ?>.<br>
        <strong><?= $rd['waitlist_places'] ?> participant<?= $rd['waitlist_places']>1?'s':'' ?></strong>
        <?= $rd['waitlist_places']>1?'sont placés':'est placé' ?> sur <strong>liste d'attente</strong>.<br>
        Un email récapitulatif vous a été envoyé.
      </p>
      <?php else: ?>
      <p style="color:var(--gy);margin-bottom:1.5rem">
        Votre réservation pour <strong><?= e($rd['workshop']['name']??'') ?></strong>
        (<?= $rd['guests'] ?> participant<?= $rd['guests']>1?'s':'' ?>) est bien enregistrée.
        Un email de confirmation vous a été envoyé.
      </p>
      <?php endif; ?>

      <?php if (!$is_waitlist && $booking_paid): ?>
      <div style="background:rgba(200,86,30,.08);border:1px solid var(--or);border-radius:var(--r);padding:1rem 1.5rem;margin-bottom:1.5rem;font-size:.9rem">
        <strong>💳 Règlement</strong><br>
        <?php if ($rd['pay_mode'] === 'onsite'): ?>
        Vous avez choisi de régler <strong>sur place</strong> le jour de l'atelier.<br>
        Montant à prévoir : <strong><?= number_format($rd['total'],2,',',' ') ?> €</strong>
        (<?= number_format($rd['price_u'],2,',',' ') ?> € × <?= $rd['guests'] ?> personne<?= $rd['guests']>1?'s':'' ?>)
        <?php else: ?>
        Paiement de <strong><?= number_format($rd['total'],2,',',' ') ?> €</strong> à effectuer.
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <a href="./atelier.php" class="btn btn-or">← Voir tous les ateliers</a>
    </div>

    <?php else: ?>
    <!-- ── FORMULAIRE ── -->
    <div style="display:grid;grid-template-columns:1.1fr 1fr;gap:4rem;align-items:start">
      <div class="fade-up">
        <?php if ($err): ?><div class="alert-err" style="margin-bottom:1rem">✗ <?= e($err) ?></div><?php endif; ?>

        <form method="POST" id="bookingForm">
          <?= csrf_field() ?>

          <!-- CRÉNEAUX -->
          <div class="form-g" style="margin-bottom:1.8rem">
            <label class="form-l">Choisir un créneau *</label>
            <?php if (!$avail): ?>
            <div style="background:var(--cr);padding:1rem;border-radius:var(--r);font-size:.88rem;color:var(--gy)">
              <?= e($cfg['no_session_msg'] ?? "Aucun atelier n'est prévu pour le moment.") ?>
            </div>
            <?php else: ?>
            <div style="display:grid;gap:.6rem;max-height:340px;overflow-y:auto;padding-right:.3rem">
              <?php foreach ($avail as $sess):
                $booked = $bk_cts[$sess['id']] ?? 0;
                $seats  = (int)($sess['seats'] ?? 8);
                $rem    = max(0, $seats - $booked);
                $wk2    = $wk_idx[$sess['workshop_id']??''] ?? ['name'=>'Atelier','emoji'=>'☕','price'=>0];
                $price2 = (float)(($sess['price_override']??0) ?: ($wk2['price']??0));
                $sel    = ($sid === $sess['id']) || (($_POST['session_id']??'') === $sess['id']);
              ?>
              <label style="cursor:pointer;display:block">
                <input type="radio" name="session_id" value="<?= e($sess['id']) ?>" required
                  <?= $sel?'checked':'' ?> style="display:none" class="sess-radio"
                  data-seats="<?= $rem ?>" data-price="<?= $price2 ?>">
                <div class="sess-card <?= $rem===0?'sess-full':'' ?>" data-id="<?= e($sess['id']) ?>">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                      <strong><?= e($wk2['emoji']??'☕') ?> <?= e($wk2['name']) ?></strong><br>
                      <span style="font-size:.82rem;color:var(--gy2)">
                        📅 <?= e(date('l d F Y', strtotime($sess['date']))) ?> · ⏰ <?= e($sess['time']) ?>
                      </span>
                    </div>
                    <div style="text-align:right">
                      <div style="font-family:var(--f1);font-size:1.2rem"><?= number_format($price2,2,',',' ') ?> € <small style="font-size:.65rem;font-weight:400">/ pers.</small></div>
                      <div style="font-size:.72rem;color:<?= $rem===0?'var(--or)':($rem<=3?'var(--or2)':'var(--vr2)') ?>">
                        <?= $rem===0 ? '⚠ Complet' : "$rem place".($rem>1?'s':'').' restante'.($rem>1?'s':'') ?>
                      </div>
                    </div>
                  </div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- COORDONNÉES -->
          <div class="form-2">
            <div class="form-g"><label class="form-l">Prénom *</label>
              <input class="form-i" name="fname" required maxlength="80" value="<?= e($_POST['fname']??'') ?>" placeholder="Jean"></div>
            <div class="form-g"><label class="form-l">Nom *</label>
              <input class="form-i" name="lname" required maxlength="80" value="<?= e($_POST['lname']??'') ?>" placeholder="Dupont"></div>
          </div>
          <div class="form-2">
            <div class="form-g"><label class="form-l">Email *</label>
              <input class="form-i" type="email" name="email" required maxlength="150" value="<?= e($_POST['email']??'') ?>" placeholder="vous@mail.fr"></div>
            <div class="form-g"><label class="form-l">Téléphone *</label>
              <input class="form-i" type="tel" name="phone" required maxlength="20" value="<?= e($_POST['phone']??'') ?>" placeholder="06 12 34 56 78"></div>
          </div>

          <!-- NOMBRE DE PARTICIPANTS -->
          <div class="form-g" style="margin-bottom:.5rem">
            <label class="form-l">Nombre de participants</label>
            <select class="form-sel" name="guests" id="guestsSelect" onchange="updateBooking()">
              <?php for ($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= (($_POST['guests']??1)==$i)?'selected':'' ?>><?= $i ?> participant<?= $i>1?'s':'' ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <!-- ALERTE PLACES INSUFFISANTES -->
          <div id="placesAlert" style="display:none;background:rgba(200,130,30,.09);border:1px solid #C87820;border-radius:var(--r);padding:.8rem 1rem;font-size:.83rem;margin-bottom:.8rem;line-height:1.6">
          </div>

          <!-- EMAILS PARTICIPANTS SUPPLÉMENTAIRES -->
          <div class="form-g" id="extraEmailsBlock" style="display:none;margin-bottom:.8rem">
            <label class="form-l">
              Emails des participants
              <small style="color:var(--gy2);font-weight:400">(optionnel — pour leur envoyer la confirmation)</small>
            </label>
            <div id="extraEmailsList"></div>
            <button type="button" id="addEmailBtn" onclick="addExtraEmail()"
              style="font-size:.75rem;color:var(--or);background:none;border:1px dashed var(--or);border-radius:var(--r);padding:.3rem .7rem;cursor:pointer;margin-top:.3rem">
              + Ajouter un email participant
            </button>
          </div>

          <!-- TOTAL DYNAMIQUE -->
          <div id="totalBlock" style="background:var(--cr);border-radius:var(--r);padding:.8rem 1rem;margin-bottom:1rem;font-size:.85rem;display:none">
            <div style="display:flex;justify-content:space-between">
              <span id="totalLabel"></span>
              <strong id="totalAmount"></strong>
            </div>
          </div>

          <!-- PAIEMENT -->
          <?php if ($booking_paid): ?>
          <div class="form-g" style="margin-bottom:1rem">
            <label class="form-l">Mode de règlement</label>
            <div style="display:flex;flex-direction:column;gap:.4rem">
              <?php if ($stripe_ok): ?>
              <label style="display:flex;align-items:center;gap:.7rem;padding:.6rem .9rem;border:2px solid var(--gy3);border-radius:var(--r);cursor:pointer">
                <input type="radio" name="pay_mode" value="stripe" <?= !($manual_ok)?'checked':'' ?> onchange="updatePayMode()">
                <span>💳 Payer maintenant par carte (Stripe)</span>
              </label>
              <?php endif; ?>
              <label style="display:flex;align-items:center;gap:.7rem;padding:.6rem .9rem;border:2px solid var(--gy3);border-radius:var(--r);cursor:pointer">
                <input type="radio" name="pay_mode" value="onsite" <?= (!$stripe_ok || !($stripe_ok))?'checked':'' ?> onchange="updatePayMode()">
                <span>🏪 Régler sur place le jour de l'atelier</span>
              </label>
            </div>
          </div>
          <!-- Stripe element (si sélectionné) -->
          <?php if ($stripe_ok): ?>
          <div id="stripeElement" style="display:none;margin-bottom:1rem">
            <div id="payment-element"></div>
          </div>
          <?php endif; ?>
          <?php endif; ?>

          <div class="form-g"><label class="form-l">Message (optionnel)</label>
            <textarea class="form-ta" name="message" maxlength="500" rows="2" placeholder="Questions, besoins particuliers…"><?= e($_POST['message']??'') ?></textarea></div>

          <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.3rem">
            <label style="display:flex;align-items:flex-start;gap:.7rem;cursor:pointer;font-size:.85rem">
              <input type="checkbox" name="newsletter" style="margin-top:.2rem" <?= isset($_POST['newsletter'])?'checked':'' ?>>
              J'accepte de recevoir les actualités de <?= e($cfg['site_name']??'Café Maison') ?>
            </label>
            <label style="display:flex;align-items:flex-start;gap:.7rem;cursor:pointer;font-size:.85rem">
              <input type="checkbox" name="terms" required style="margin-top:.2rem" <?= isset($_POST['terms'])?'checked':'' ?>>
              J'accepte les <a href="./cgv.php" style="color:var(--or)" target="_blank">conditions générales</a> *
            </label>
          </div>

          <?php if ($avail): ?>
          <?php if ($use_captcha): ?>
          <?= captcha_hidden_field() ?>
          <div style="font-size:.68rem;color:var(--gy2);text-align:center;margin-bottom:.5rem">
            🔒 Protégé par reCAPTCHA v3 ·
            <a href="https://policies.google.com/privacy" style="color:var(--gy2)" target="_blank">Confidentialité</a>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-or" style="width:100%;justify-content:center" id="submitBtn">
            Réserver maintenant →
          </button>
          <?php else: ?>
          <p style="text-align:center;color:var(--gy2);font-size:.85rem">La réservation n'est pas disponible pour le moment.</p>
          <?php endif; ?>
        </form>
      </div>

      <!-- Sidebar -->
      <div class="fade-up" data-delay="2">
        <div style="background:var(--bk);border-radius:14px;padding:2rem;color:#fff;position:sticky;top:calc(var(--hh)+1rem)">
          <h3 style="font-family:var(--f1);color:#fff;margin-bottom:1.2rem">🎓 Nos ateliers</h3>
          <?php foreach ($workshops as $wk):
            if (!($wk['active']??true)) continue; ?>
          <div style="padding:.7rem 0;border-bottom:1px solid rgba(255,255,255,.07)">
            <strong style="display:block;font-size:.85rem;color:#fff"><?= e($wk['emoji']??'☕') ?> <?= e($wk['name']) ?></strong>
            <span style="font-size:.75rem;color:rgba(255,255,255,.45)"><?= number_format((float)($wk['price']??0),2,',',' ') ?> € / personne · <?= (int)($wk['duration']??60) ?> min</span>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:1rem;font-size:.75rem;color:rgba(255,255,255,.35)">
            📍 <?= e($cfg['address']??'') ?><br>
            📞 <?= e($cfg['phone']??'') ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<script>
// Données des créneaux
const sessions = {};
<?php foreach ($avail as $s):
  $w2 = $wk_idx[$s['workshop_id']??''] ?? [];
  $pr = (float)(($s['price_override']??0) ?: ($w2['price']??0));
  $rm = max(0, (int)($s['seats']??8) - ($bk_cts[$s['id']]??0));
?>
sessions['<?= $s['id'] ?>'] = { remaining: <?= $rm ?>, price: <?= $pr ?> };
<?php endforeach; ?>

let currentSession = null;
let extraCount = 0;

// Sélection créneau → mettre à jour l'affichage
document.querySelectorAll('.sess-radio').forEach(r => {
  r.addEventListener('change', function() {
    currentSession = sessions[this.value] || null;
    updateBooking();
  });
});

function getSelectedSession() {
  const r = document.querySelector('.sess-radio:checked');
  return r ? (sessions[r.value] || null) : null;
}

function updateBooking() {
  const sess = getSelectedSession();
  const guests = parseInt(document.getElementById('guestsSelect').value) || 1;
  const alert  = document.getElementById('placesAlert');
  const total  = document.getElementById('totalBlock');
  const extBlk = document.getElementById('extraEmailsBlock');

  if (!sess) {
    alert.style.display = 'none';
    total.style.display = 'none';
    return;
  }

  const rem  = sess.remaining;
  const conf = Math.min(guests, rem);
  const wait = Math.max(0, guests - rem);

  // Alerte si places insuffisantes
  if (wait > 0 && rem > 0) {
    alert.style.display = 'block';
    alert.innerHTML = `⚠️ <strong>Attention :</strong> Vous demandez <strong>${guests} place${guests>1?'s':''}</strong>,
      mais seulement <strong>${conf} place${conf>1?'s':''}</strong> ${conf>1?'sont disponibles':'est disponible'}.<br>
      <strong>${wait} participant${wait>1?'s':''}</strong> ${wait>1?'seront placés':'sera placé'} sur <strong>liste d'attente</strong>.`;
  } else if (rem === 0) {
    alert.style.display = 'block';
    alert.innerHTML = `📋 Cet atelier est complet. Vous serez placé(e) sur <strong>liste d'attente</strong>.`;
  } else {
    alert.style.display = 'none';
  }

  // Total
  const t = sess.price * guests;
  if (sess.price > 0 && rem > 0) {
    total.style.display = 'block';
    document.getElementById('totalLabel').textContent =
      `${sess.price.toFixed(2).replace('.',',')} € × ${guests} personne${guests>1?'s':''}`;
    document.getElementById('totalAmount').textContent =
      t.toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' €';
  } else {
    total.style.display = 'none';
  }

  // Emails supplémentaires si plusieurs participants
  if (guests > 1 && rem > 0) {
    extBlk.style.display = 'block';
    adjustExtraEmails(guests - 1);
  } else {
    extBlk.style.display = 'none';
  }
}

function adjustExtraEmails(needed) {
  const list = document.getElementById('extraEmailsList');
  // Ajouter des champs jusqu'au nombre de participants supplémentaires
  while (list.children.length < needed) {
    addExtraEmail();
  }
}

function addExtraEmail() {
  const list = document.getElementById('extraEmailsList');
  extraCount++;
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:.4rem;margin-bottom:.3rem;align-items:center';
  row.innerHTML = `<input class="form-i" type="email" name="extra_email[]"
      placeholder="participant${extraCount}@email.fr" style="flex:1">
    <button type="button" onclick="this.closest('div').remove()"
      style="background:none;border:none;color:var(--or);cursor:pointer;font-size:1.1rem;line-height:1">×</button>`;
  list.appendChild(row);
}

function updatePayMode() {
  const mode = document.querySelector('[name=pay_mode]:checked')?.value;
  const stripeEl = document.getElementById('stripeElement');
  if (stripeEl) stripeEl.style.display = mode === 'stripe' ? 'block' : 'none';
}

// Init au chargement
const preselected = document.querySelector('.sess-radio:checked');
if (preselected) updateBooking();
</script>

<?php if ($use_captcha): ?>
<?= captcha_js_handler($cfg['captcha_site_key'], 'bookingForm', 'booking') ?>
<?php endif; ?>
<?php include ROOT . '/includes/footer.php'; ?>
