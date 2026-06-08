<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$cfg      = cfg_get();
$giftcards = db('giftcards');
$errs      = [];
$ok        = false;

// Statuts disponibles
$statuses = [
    'pending'   => ['label'=>'En attente',  'cls'=>'bdg-o'],
    'active'    => ['label'=>'Active',      'cls'=>'bdg-g'],
    'used'      => ['label'=>'Utilisée',    'cls'=>'bdg-gy'],
    'cancelled' => ['label'=>'Annulée',     'cls'=>'bdg-r'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 20);

    // Changer le statut d'une carte
    if ($pa === 'status') {
        $gid     = clean($_POST['gid'] ?? '', 30);
        $new_st  = in_array($_POST['status']??'', array_keys($statuses), true) ? $_POST['status'] : '';
        if ($gid && $new_st) {
            foreach ($giftcards as &$g) {
                if ($g['id'] === $gid) {
                    $g['status']     = $new_st;
                    $g['updated_at'] = date('Y-m-d H:i:s');
                    // Si activée -> envoyer le code au client
                    if ($new_st === 'active' && !empty($g['customer_email'])) {
                        $to   = $g['customer_email'];
                        $name = $g['customer_name'] ?? '';
                        $code = $g['code'] ?? '';
                        $val  = number_format($g['amount']??0, 2, ',', ' ');
                        $sn   = $cfg['site_name'] ?? 'Café Maison';
                        $body = "<p>Bonjour $name,</p>
                                 <p>Votre carte cadeau d'une valeur de <strong>$val €</strong> est maintenant activée !</p>
                                 <div style='text-align:center;margin:24px 0'>
                                   <div style='background:#FFF8F5;border:2px dashed #C8561E;border-radius:10px;padding:16px;display:inline-block'>
                                     <div style='font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#C8561E;margin-bottom:4px'>Votre code cadeau</div>
                                     <div style='font-size:28px;font-weight:800;font-family:monospace;color:#C8561E;letter-spacing:.15em'>$code</div>
                                   </div>
                                 </div>
                                 <p>Présentez ce code en boutique ou saisissez-le lors de votre commande en ligne.</p>
                                 <p style='color:#888;font-size:12px'>Valable jusqu'au ".date('d/m/Y', strtotime('+1 year'))."</p>";
                        $html = mail_wrap("Votre carte cadeau est prête ! 🎁", $body, 'Commander en ligne', '');
                        cm_mail($to, $name, "Votre carte cadeau $sn — Code: $code", $html);
                    }
                }
            } unset($g);
            db_save('giftcards', $giftcards);
            admin_log('giftcard_status', "Carte $gid -> $new_st");
        }
        header('Location: ./giftcards.php?saved=1'); exit;
    }

    // Vérifier un code (recherche)
    if ($pa === 'verify') {
        $code = strtoupper(clean($_POST['code'] ?? '', 30));
        $found = null;
        foreach ($giftcards as $g) {
            if (strtoupper($g['code'] ?? '') === $code) { $found = $g; break; }
        }
        $_SESSION['giftcard_verify_result'] = $found;
        header('Location: ./giftcards.php?verify=1'); exit;
    }

    // Créer manuellement une carte cadeau
    if ($pa === 'create') {
        $amount = clean_float($_POST['amount'] ?? 0, 1, 9999);
        $email  = filter_var($_POST['customer_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $name   = clean($_POST['customer_name'] ?? '', 100);
        $note   = clean($_POST['note'] ?? '', 300);
        if ($amount <= 0) $errs[] = 'Montant invalide.';
        if (!$errs) {
            // Générer un code unique
            do {
                $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $exists = false;
                foreach ($giftcards as $g) { if ($g['code'] === $code) { $exists = true; break; } }
            } while ($exists);
            $new_gc = [
                'id'             => new_id(),
                'code'           => $code,
                'amount'         => $amount,
                'balance'        => $amount,
                'status'         => 'active',
                'customer_email' => $email,
                'customer_name'  => $name,
                'note'           => $note,
                'source'         => 'admin',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
                'expires_at'     => date('Y-m-d', strtotime('+1 year')),
            ];
            $giftcards[] = $new_gc;
            db_save('giftcards', $giftcards);
            admin_log('giftcard_create', "Carte $code créée manuellement ($amount€)");
            // Envoyer par email si email fourni
            if ($email) {
                $val  = number_format($amount, 2, ',', ' ');
                $sn   = $cfg['site_name'] ?? 'Café Maison';
                $body = "<p>Bonjour $name,</p>
                         <p>Une carte cadeau d'une valeur de <strong>$val €</strong> vous a été créée.</p>
                         <div style='text-align:center;margin:24px 0'>
                           <div style='background:#FFF8F5;border:2px dashed #C8561E;border-radius:10px;padding:16px;display:inline-block'>
                             <div style='font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#C8561E;margin-bottom:4px'>Votre code cadeau</div>
                             <div style='font-size:28px;font-weight:800;font-family:monospace;color:#C8561E;letter-spacing:.15em'>$code</div>
                           </div>
                         </div>
                         <p>Présentez ce code en boutique ou lors de votre commande en ligne.</p>";
                $html = mail_wrap("Votre carte cadeau 🎁", $body, 'Commander en ligne', '');
                cm_mail($email, $name, "Votre carte cadeau $sn", $html);
            }
            $ok = true;
        }
    }
}

// Résultat vérification
$verify_result = $_SESSION['giftcard_verify_result'] ?? null;
unset($_SESSION['giftcard_verify_result']);

// Filtres
$flt_st = clean($_GET['s'] ?? 'all', 20);
$filtered = $flt_st === 'all' ? $giftcards : array_filter($giftcards, fn($g) => ($g['status']??'') === $flt_st);
$filtered = array_reverse(array_values($filtered));

$at = 'Cartes cadeaux'; $cur_adm = 'giftcards';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div><h2>Cartes cadeaux</h2><p><?= count($giftcards) ?> carte(s) · <?= count(array_filter($giftcards, fn($g)=>($g['status']??'')=='active')) ?> active(s)</p></div>
  <div class="adm-acts">
    <button type="button" onclick="document.getElementById('createBox').style.display=document.getElementById('createBox').style.display==='none'?'block':'none'" class="a-btn prim">+ Créer une carte</button>
  </div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré.</div><?php endif; ?>
  <?php if ($errs): ?><div class="alert-err" style="margin-bottom:1rem"><?php foreach($errs as $e) echo '<div>✗ '.e($e).'</div>'; ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Carte cadeau créée.</div><?php endif; ?>

  <!-- Créer une carte -->
  <div id="createBox" style="display:none;background:var(--cr);border-radius:var(--r);padding:1.2rem;margin-bottom:1.4rem">
    <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.8rem">➕ Nouvelle carte cadeau</h4>
    <form method="POST" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="create">
      <div class="frm-g" style="flex:0 0 100px"><label class="frm-l">Montant (€)*</label><input class="frm-i" type="number" name="amount" min="1" max="9999" step="0.01" required placeholder="50"></div>
      <div class="frm-g" style="flex:1;min-width:200px"><label class="frm-l">Email du bénéficiaire</label><input class="frm-i" type="email" name="customer_email" placeholder="client@email.fr"></div>
      <div class="frm-g" style="flex:1;min-width:150px"><label class="frm-l">Nom</label><input class="frm-i" name="customer_name" placeholder="Prénom Nom"></div>
      <div class="frm-g" style="flex:2;min-width:200px"><label class="frm-l">Note interne</label><input class="frm-i" name="note" maxlength="300" placeholder="Offert par…"></div>
      <button type="submit" class="save-btn">Créer</button>
    </form>
  </div>

  <!-- Vérifier un code -->
  <div style="background:var(--cr);border-radius:var(--r);padding:1rem;margin-bottom:1.4rem">
    <form method="POST" style="display:flex;gap:.5rem;align-items:flex-end">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="verify">
      <div class="frm-g" style="flex:1"><label class="frm-l">🔍 Vérifier un code carte cadeau</label>
        <input class="frm-i" name="code" maxlength="30" placeholder="Ex: A1B2C3D4" style="font-family:monospace;text-transform:uppercase;font-size:1.1rem"></div>
      <button type="submit" class="a-btn prim" style="height:42px">Vérifier</button>
    </form>
    <?php if (isset($_GET['verify'])): ?>
      <?php if ($verify_result): ?>
      <div style="margin-top:.8rem;padding:.8rem 1rem;background:<?= ($verify_result['status']??'')==='active'?'rgba(42,76,30,.1)':'rgba(200,86,30,.1)' ?>;border-radius:var(--r)">
        <strong><?= $verify_result['code'] ?></strong> ·
        <?= $statuses[$verify_result['status']??'pending']['label'] ?? $verify_result['status'] ?> ·
        <strong><?= number_format($verify_result['balance']??$verify_result['amount']??0, 2, ',', ' ') ?> €</strong>
        <?php if ($verify_result['customer_name']??''): ?> · <?= e($verify_result['customer_name']) ?><?php endif; ?>
      </div>
      <?php else: ?>
      <div style="margin-top:.8rem;padding:.8rem 1rem;background:rgba(200,86,30,.1);border-radius:var(--r)">❌ Code introuvable</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Filtres -->
  <div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:1rem">
    <a href="?" class="flt <?= $flt_st==='all'?'on':'' ?>">Toutes</a>
    <?php foreach ($statuses as $sk => $sv): ?>
    <a href="?s=<?= $sk ?>" class="flt <?= $flt_st===$sk?'on':'' ?>"><?= $sv['label'] ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$filtered): ?>
  <div class="empty-st"><div class="empty-ico">🎁</div><h4>Aucune carte cadeau</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Code</th><th>Montant</th><th>Bénéficiaire</th><th>Source</th><th>Créée</th><th>Expire</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($filtered as $g): ?>
    <tr>
      <td style="font-family:monospace;font-weight:700;font-size:1.05rem;letter-spacing:.1em"><?= e($g['code']??'') ?></td>
      <td style="font-weight:700;font-family:var(--f1)"><?= number_format($g['amount']??0,2,',',' ') ?> €</td>
      <td>
        <div style="font-weight:500;font-size:.88rem"><?= e($g['customer_name']??'—') ?></div>
        <?php if ($g['customer_email']??''): ?><div style="font-size:.72rem;color:var(--gy2)"><?= e($g['customer_email']) ?></div><?php endif; ?>
        <?php if ($g['note']??''): ?><div style="font-size:.7rem;color:var(--gy2);font-style:italic"><?= e($g['note']) ?></div><?php endif; ?>
      </td>
      <td style="font-size:.8rem"><span class="bdg bdg-gy"><?= e($g['source']??'boutique') ?></span></td>
      <td style="font-size:.8rem"><?= e(date('d/m/Y', strtotime($g['created_at']??'now'))) ?></td>
      <td style="font-size:.8rem"><?= isset($g['expires_at'])?e(date('d/m/Y',strtotime($g['expires_at']))):'—' ?></td>
      <td><span class="bdg <?= $statuses[$g['status']??'pending']['cls']??'bdg-gy' ?>"><?= $statuses[$g['status']??'pending']['label']??$g['status'] ?></span></td>
      <td>
        <form method="POST" style="display:flex;gap:.3rem">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="status">
          <input type="hidden" name="gid" value="<?= e($g['id']) ?>">
          <select name="status" class="frm-sel" style="font-size:.72rem;padding:.2rem .5rem">
            <?php foreach ($statuses as $sk => $sv): ?>
            <option value="<?= $sk ?>" <?= ($g['status']??'')===$sk?'selected':'' ?>><?= $sv['label'] ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="act-btn">✓</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
