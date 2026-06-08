<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$cfg = cfg_get();
$ok  = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $raw = file_get_contents(DATA_DIR . '/settings.json');
    $data = json_decode($raw, true) ?: [];

    // Toggles
    $data['shipping_use_weight']              = isset($_POST['shipping_use_weight']);
    $data['shipping_international_enabled']   = isset($_POST['shipping_international_enabled']);
    $data['shipping_free_from']               = max(0, (float)($_POST['shipping_free_from'] ?? 35));
    $data['shipping_packaging_g']             = max(0, (int)($_POST['shipping_packaging_g'] ?? 150));
    $data['shipping_intl_blocked_msg']        = trim($_POST['shipping_intl_blocked_msg'] ?? '');

    // Tranches France
    $tiers_fr = [];
    $fr_max   = $_POST['fr_max']   ?? [];
    $fr_price = $_POST['fr_price'] ?? [];
    foreach ($fr_max as $i => $max) {
        $max   = (int)$max;
        $price = round((float)($fr_price[$i] ?? 0), 2);
        if ($max > 0 && $price > 0) $tiers_fr[] = ['max_g' => $max, 'price' => $price];
    }
    usort($tiers_fr, fn($a,$b) => $a['max_g'] <=> $b['max_g']);
    $data['shipping_tiers_fr'] = json_encode($tiers_fr);

    // Tranches internationales A/B/C
    foreach (['a','b','c'] as $z) {
        $tiers = [];
        $maxes = $_POST["intl_{$z}_max"]   ?? [];
        $prices= $_POST["intl_{$z}_price"] ?? [];
        foreach ($maxes as $i => $max) {
            $max   = (int)$max;
            $price = round((float)($prices[$i] ?? 0), 2);
            if ($max > 0 && $price > 0) $tiers[] = ['max_g' => $max, 'price' => $price];
        }
        usort($tiers, fn($a,$b) => $a['max_g'] <=> $b['max_g']);
        $data["shipping_tiers_intl_{$z}"] = json_encode($tiers);
    }

    // API Colissimo
    $data['colissimo_api_enabled']  = isset($_POST['colissimo_api_enabled']);
    $data['colissimo_api_login']    = trim($_POST['colissimo_api_login'] ?? '');
    $data['colissimo_sender_zip']   = trim($_POST['colissimo_sender_zip'] ?? '');
    $pass = trim($_POST['colissimo_api_password'] ?? '');
    if ($pass && strpos($pass, '•') === false) {
        $data['colissimo_api_password'] = $pass;
    }

    $bytes = file_put_contents(DATA_DIR . '/settings.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX);

    // Champs API Colissimo
    $data['colissimo_api_enabled']  = isset($_POST['colissimo_api_enabled']);
    $data['colissimo_api_login']    = trim($_POST['colissimo_api_login'] ?? '');
    $data['colissimo_api_password'] = trim($_POST['colissimo_api_password'] ?? '');

    if ($bytes === false) {
        $err = 'Impossible d\'écrire settings.json. Vérifiez les permissions.';
    } else {
        $ok = 'Paramètres de livraison enregistrés.';
        $cfg = json_decode(file_get_contents(DATA_DIR . '/settings.json'), true) ?: [];
        admin_log('shipping_settings', 'Paramètres livraison mis à jour');
    }
}

// Lire les tranches
$tiers_fr = json_decode($cfg['shipping_tiers_fr'] ?? '[]', true) ?: [
    ['max_g'=>250,'price'=>5.35],['max_g'=>500,'price'=>6.99],['max_g'=>1000,'price'=>8.05],
    ['max_g'=>2000,'price'=>10.70],['max_g'=>5000,'price'=>13.90],['max_g'=>30000,'price'=>34.80],
];
$tiers_a = json_decode($cfg['shipping_tiers_intl_a'] ?? '[]', true) ?: [
    ['max_g'=>500,'price'=>14.90],['max_g'=>1000,'price'=>18.50],['max_g'=>2000,'price'=>24.80],
];
$tiers_b = json_decode($cfg['shipping_tiers_intl_b'] ?? '[]', true) ?: [
    ['max_g'=>500,'price'=>18.50],['max_g'=>1000,'price'=>24.00],['max_g'=>2000,'price'=>34.00],
];
$tiers_c = json_decode($cfg['shipping_tiers_intl_c'] ?? '[]', true) ?: [
    ['max_g'=>500,'price'=>22.50],['max_g'=>1000,'price'=>32.00],['max_g'=>2000,'price'=>46.00],
];

$at = 'Livraison'; $cur_adm = 'shipping';
include ROOT . '/admin/inc/layout.php';

function testColissimoApi(array $cfg): array {
    $login    = $cfg['colissimo_api_login'] ?? '';
    $password = $cfg['colissimo_api_password'] ?? '';
    if (!$login || !$password) return ['ok'=>false,'msg'=>'Identifiants manquants.'];
    // Test avec l'API Colissimo (endpoint de tarification)
    $url = 'https://ws.colissimo.fr/sls-ws/SlsServiceWS/2.0?wsdl';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'CafeMaison/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok'=>false,'msg'=>"Erreur réseau: $err"];
    if ($code !== 200) return ['ok'=>false,'msg'=>"Erreur HTTP $code — vérifiez l'accès au service Colissimo."];
    return ['ok'=>true,'msg'=>"Service Colissimo accessible (HTTP 200). Testez un calcul de tarif réel."];
}

function render_tiers(string $prefix, array $tiers): void { ?>
<div id="tbl_<?= $prefix ?>" style="margin-bottom:.5rem">
  <?php foreach ($tiers as $i => $t): ?>
  <div class="tier-row" style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
    <span style="font-size:.72rem;color:var(--gy2);width:40px">≤</span>
    <input class="frm-i" type="number" name="<?= $prefix ?>_max[]" value="<?= (int)$t['max_g'] ?>"
      min="1" max="30000" step="1" placeholder="grammes" style="max-width:90px">
    <span style="font-size:.72rem;color:var(--gy2)">g →</span>
    <input class="frm-i" type="number" name="<?= $prefix ?>_price[]" value="<?= number_format((float)$t['price'],2,'.','')?>"
      min="0" step="0.01" placeholder="€" style="max-width:80px">
    <span style="font-size:.72rem;color:var(--gy2)">€</span>
    <button type="button" onclick="this.closest('.tier-row').remove()"
      style="background:none;border:none;color:var(--or);cursor:pointer;font-size:1rem;line-height:1">×</button>
  </div>
  <?php endforeach; ?>
</div>
<button type="button" class="act-btn" style="font-size:.72rem"
  onclick="addTier('tbl_<?= $prefix ?>','<?= $prefix ?>')">+ Ajouter une tranche</button>
<?php }
?>

<div class="adm-top">
  <div><h2>🚚 Paramètres de livraison</h2><p>Frais de port par poids et par zone géographique</p></div>
</div>
<div class="adm-content">

<?php if ($ok): ?><div class="alert-ok" style="margin-bottom:1rem">✓ <?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-err" style="margin-bottom:1rem">✗ <?= e($err) ?></div><?php endif; ?>

<form method="POST">
<?= csrf_field() ?>

<!-- GÉNÉRAL -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.8rem">⚙️ Général</h4>
  <div class="tog-wrap" style="margin-bottom:.7rem">
    <label class="tog"><input type="checkbox" name="shipping_use_weight" <?= !empty($cfg['shipping_use_weight'])?'checked':'' ?>><span class="tog-sl"></span></label>
    <span class="tog-lbl" style="font-weight:600">Calculer les frais selon le poids du panier</span>
  </div>
  <div style="background:rgba(42,76,30,.05);border-radius:var(--r);padding:.6rem .8rem;font-size:.77rem;color:var(--gy2);margin-bottom:.8rem">
    ℹ️ Si désactivé, un tarif fixe est appliqué (configurable dans Paramètres généraux). Si activé, le tarif est calculé selon les tranches ci-dessous.
  </div>
  <div class="frm-row">
    <div class="frm-g"><label class="frm-l">Livraison France offerte dès (€)</label>
      <input class="frm-i" type="number" name="shipping_free_from" step="1" min="0"
        value="<?= $cfg['shipping_free_from']??35 ?>" style="max-width:100px">
      <small style="color:var(--gy2);font-size:.72rem">Mettre 0 pour désactiver</small></div>
    <div class="frm-g"><label class="frm-l">Poids emballage (g)</label>
      <input class="frm-i" type="number" name="shipping_packaging_g" step="10" min="0"
        value="<?= $cfg['shipping_packaging_g']??150 ?>" style="max-width:100px">
      <small style="color:var(--gy2);font-size:.72rem">Ajouté automatiquement au poids de la commande</small></div>
  </div>
</div>

<!-- FRANCE -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.5rem">🇫🇷 France métropolitaine</h4>
  <p style="font-size:.77rem;color:var(--gy2);margin-bottom:.8rem">
    Colissimo France : tarif identique pour toute la France (pas de variation par région). Basé sur la grille La Poste 2025.
  </p>
  <?php render_tiers('fr', $tiers_fr); ?>
</div>

<!-- INTERNATIONAL -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.7rem">
    <label class="tog"><input type="checkbox" name="shipping_international_enabled" <?= !empty($cfg['shipping_international_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label>
    <h4 style="font-family:var(--f1);font-size:1.05rem;margin:0">🌍 Livraisons internationales</h4>
  </div>
  <div class="frm-g" style="margin-bottom:.8rem">
    <label class="frm-l">Message si livraisons internationales désactivées</label>
    <input class="frm-i" name="shipping_intl_blocked_msg" maxlength="200"
      value="<?= e($cfg['shipping_intl_blocked_msg']??'Les livraisons hors France ne sont actuellement pas disponibles.') ?>">
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.2rem">
    <div>
      <h5 style="font-size:.82rem;margin-bottom:.5rem">🌍 Zone A — Europe UE, Suisse, Monaco <small style="color:var(--gy2)">(+3€ UK/Brexit)</small></h5>
      <?php render_tiers('intl_a', $tiers_a); ?>
    </div>
    <div>
      <h5 style="font-size:.82rem;margin-bottom:.5rem">🗺 Zone B — Europe Est, Maghreb, Norvège</h5>
      <?php render_tiers('intl_b', $tiers_b); ?>
    </div>
    <div>
      <h5 style="font-size:.82rem;margin-bottom:.5rem">🌐 Zone C — Reste du monde</h5>
      <?php render_tiers('intl_c', $tiers_c); ?>
    </div>
  </div>
</div>

<!-- OPTION B: API Colissimo -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.8rem">🔌 API transporteur <small style="color:var(--gy2)">(Colissimo, Mondial Relay…)</small></h4>
  <div style="background:rgba(42,76,30,.05);border-radius:var(--r);padding:.7rem 1rem;font-size:.77rem;color:var(--gy2);margin-bottom:.8rem">
    ℹ️ Connectez l'API officielle La Poste pour récupérer les tarifs en temps réel. Nécessite un compte professionnel Colissimo (<a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" style="color:var(--or)">colissimo.entreprise.laposte.fr</a>).
  </div>

  <div class="tog-wrap" style="margin-bottom:.8rem">
    <label class="tog"><input type="checkbox" name="colissimo_api_enabled" <?= !empty($cfg['colissimo_api_enabled'])?'checked':'' ?>><span class="tog-sl"></span></label>
    <span class="tog-lbl" style="font-weight:600">Utiliser l'API Colissimo (remplace les tranches manuelles)</span>
  </div>

  <div class="frm-row">
    <div class="frm-g">
      <label class="frm-l">Login API Colissimo</label>
      <input class="frm-i" name="colissimo_api_login" maxlength="100"
        value="<?= e($cfg['colissimo_api_login']??'') ?>" placeholder="Votre login API La Poste">
    </div>
    <div class="frm-g">
      <label class="frm-l">Mot de passe API</label>
      <input class="frm-i" type="password" name="colissimo_api_password" maxlength="100"
        value="<?= ($cfg['colissimo_api_password']??'') ? '••••••••' : '' ?>" placeholder="Mot de passe API">
    </div>
  </div>
  <div class="frm-g">
    <label class="frm-l">Code expéditeur</label>
    <input class="frm-i" name="colissimo_sender_zip" maxlength="10"
      value="<?= e($cfg['colissimo_sender_zip']??'') ?>" placeholder="Code postal de votre boutique (ex: 75011)">
    <small style="color:var(--gy2);font-size:.72rem">Utilisé pour calculer les frais selon votre point de départ</small>
  </div>

  <?php
  // Test de connexion API Colissimo si configuré
  if (!empty($cfg['colissimo_api_enabled']) && !empty($cfg['colissimo_api_login'])):
  ?>
  <div style="margin-top:.5rem">
    <button type="button" class="act-btn" id="testColissimoBtn" onclick="testColissimoAPI()">
      🔗 Tester la connexion API
    </button>
    <span id="colissimoTestResult" style="font-size:.78rem;margin-left:.5rem"></span>
  </div>
  <?php endif; ?>

  <div style="background:var(--cr);border-radius:var(--r);padding:.7rem 1rem;margin-top:.7rem;font-size:.76rem;color:var(--gy2)">
    <strong>Comment obtenir les identifiants API :</strong><br>
    1. Créez un compte professionnel sur <a href="https://www.colissimo.entreprise.laposte.fr" target="_blank" style="color:var(--or)">colissimo.entreprise.laposte.fr</a><br>
    2. Dans votre espace client → API → Générer des identifiants<br>
    3. Copiez le login et mot de passe ci-dessus<br>
    4. Cochez "Utiliser l'API Colissimo" — les frais seront alors calculés en temps réel
  </div>
</div>

<button type="submit" class="save-btn" style="width:100%;justify-content:center">💾 Enregistrer</button>
</form>
</div>

<script>
function addTier(tableId, prefix) {
  const tbl = document.getElementById(tableId);
  const row = document.createElement('div');
  row.className = 'tier-row';
  row.style.cssText = 'display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem';
  row.innerHTML =
    '<span style="font-size:.72rem;color:var(--gy2);width:40px">≤</span>' +
    '<input class="frm-i" type="number" name="'+prefix+'_max[]" min="1" max="30000" step="1" placeholder="grammes" style="max-width:90px">' +
    '<span style="font-size:.72rem;color:var(--gy2)">g →</span>' +
    '<input class="frm-i" type="number" name="'+prefix+'_price[]" min="0" step="0.01" placeholder="€" style="max-width:80px">' +
    '<span style="font-size:.72rem;color:var(--gy2)">€</span>' +
    '<button type="button" onclick="this.closest(\'.tier-row\').remove()" style="background:none;border:none;color:var(--or);cursor:pointer;font-size:1rem;line-height:1">×</button>';
  tbl.appendChild(row);
  tbl.querySelector('input[type=number]') && tbl.lastChild.querySelector('input').focus();
}

async function testColissimoAPI() {
  const btn = document.getElementById('testColissimoBtn');
  const res = document.getElementById('colissimoTestResult');
  btn.disabled = true; res.textContent = 'Test en cours…';
  try {
    const r = await fetch('../api/colissimo_test.php', {method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({_csrf: document.querySelector('[name=_csrf]').value})
    });
    const d = await r.json();
    res.textContent = d.ok ? '✓ Connexion réussie !' : '✗ ' + (d.error || 'Erreur');
    res.style.color = d.ok ? 'var(--vr2)' : 'var(--or)';
  } catch(e) {
    res.textContent = '✗ Erreur réseau'; res.style.color = 'var(--or)';
  }
  btn.disabled = false;
}
</script>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
