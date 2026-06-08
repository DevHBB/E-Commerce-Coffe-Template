<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$sessions  = db('sessions');
$workshops = db('workshops');
$bookings  = db('bookings');
$act = clean($_GET['a'] ?? 'list', 10);
$id  = clean($_GET['id'] ?? '', 20);
$errs = [];

// ── Index ateliers ──────────────────────────────────────────
$wk_idx = [];
foreach ($workshops as $w) $wk_idx[$w['id']] = $w;

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 20);

    if ($pa === 'delete') {
        $did = clean($_POST['did'] ?? '', 20);
        // Supprimer aussi les réservations liées
        $bk2 = array_values(array_filter($bookings, fn($b) => $b['session_id'] !== $did));
        db_save('bookings', $bk2);
        db_save('sessions', array_values(array_filter($sessions, fn($s) => $s['id'] !== $did)));
        header('Location: ./calendar.php?del=1'); exit;
    }

    if ($pa === 'save') {
        $eid     = clean($_POST['eid']       ?? '', 20);
        $wid     = clean($_POST['workshop_id']?? '', 30);
        $date    = clean($_POST['date']      ?? '', 12);
        $time    = clean($_POST['time']      ?? '', 8);
        $seats   = clean_int($_POST['seats'] ?? 8, 1, 200);
        $price_o = clean_float($_POST['price_override'] ?? 0, 0, 9999);
        $notes   = clean($_POST['notes']    ?? '', 300);
        $active  = isset($_POST['active']);

        if (!$wid || !$date || !$time) $errs[] = 'Atelier, date et heure sont requis.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errs[] = 'Date invalide.';

        if (!$errs) {
            $row = [
                'workshop_id'    => $wid,
                'date'           => $date,
                'time'           => $time,
                'seats'          => $seats,
                'price_override' => $price_o,
                'notes'          => $notes,
                'active'         => $active,
            ];
            if ($eid) {
                foreach ($sessions as &$s) {
                    if ($s['id'] === $eid) $s = array_merge($s, $row);
                }
                unset($s);
            } else {
                $row['id']      = new_id();
                $row['created'] = date('Y-m-d');
                $sessions[]     = $row;
            }
            db_save('sessions', $sessions);
            header('Location: ./calendar.php?saved=1'); exit;
        }
    }

    // Statut réservation
    if ($pa === 'booking_status') {
        $bid     = clean($_POST['bid']  ?? '', 20);
        $stat    = in_array($_POST['bstat']??'', ['pending','confirmed','cancelled','waitlist'], true) ? $_POST['bstat'] : 'pending';
        $sess_id = clean($_POST['sess_id'] ?? '', 20); // pour rediriger vers la bonne vue

        $bk_found = null;
        $old_stat = '';
        foreach ($bookings as &$b) {
            if ($b['id'] === $bid) {
                $old_stat      = $b['status'] ?? 'pending';
                $b['status']   = $stat;
                $b['updated']  = date('Y-m-d H:i');
                $bk_found      = $b;
            }
        }
        unset($b);
        db_save('bookings', $bookings);

        // Envoyer email si statut change
        if ($bk_found && $stat !== $old_stat) {
            $bk_sess = null;
            foreach ($sessions as $s) { if ($s['id'] === ($bk_found['session_id']??'')) { $bk_sess = $s; break; } }
            $bk_wk   = $wk_idx[$bk_found['workshop_id'] ?? ($bk_sess['workshop_id'] ?? '')] ?? [];

            if ($stat === 'confirmed') {
                mail_booking_confirmed($bk_found, $bk_sess ?? [], $bk_wk);
            } elseif ($stat === 'cancelled') {
                mail_booking_cancelled($bk_found, $bk_wk);
            }
        }

        admin_log('booking_status', "Réservation $bid : $old_stat -> $stat");
        // Rediriger vers la liste des réservations de la même session
        $redir = $sess_id ? "./calendar.php?a=bookings&id={$sess_id}&saved=1" : "./calendar.php?saved=1";
        header("Location: $redir"); exit;
    }
}

$ed = null;
if ($act === 'edit' && $id) foreach ($sessions as $s) { if ($s['id'] === $id) { $ed = $s; break; } }

// Compter places occupées par session (en nombre de personnes, pas de réservations)
$bk_counts = [];
foreach ($bookings as $b) {
    $sid = $b['session_id'] ?? '';
    if (!isset($bk_counts[$sid])) $bk_counts[$sid] = 0;
    if (($b['status']??'') !== 'cancelled') {
        $bk_counts[$sid] += max(1, (int)($b['guests'] ?? 1));
    }
}

// Mois affiché
$month = clean($_GET['m'] ?? date('Y-m'), 8);
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
[$Y, $M] = explode('-', $month);
$prevM = date('Y-m', mktime(0,0,0,(int)$M-1,1,(int)$Y));
$nextM = date('Y-m', mktime(0,0,0,(int)$M+1,1,(int)$Y));

// Sessions du mois affiché
$month_sessions = array_filter($sessions, fn($s) => (strpos($s['date']??'', $month) === 0));

$at = 'Calendrier'; $cur_adm = 'calendar';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act === 'new' || $act === 'edit'): ?>
<div class="adm-top">
  <div><h2><?= $act==='new'?'Nouveau créneau':'Modifier le créneau' ?></h2></div>
  <div class="adm-acts"><a href="./calendar.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <?php if ($errs): ?><div class="alert-err"><?php foreach($errs as $e) echo '<div>✗ '.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>
  <form method="POST" style="max-width:620px">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="save">
    <input type="hidden" name="eid" value="<?= e($ed['id']??'') ?>">
    <div class="editor-wrap">
      <div class="frm-g"><label class="frm-l">Atelier *</label>
        <select class="frm-sel" name="workshop_id" required>
          <option value="">— Choisir un atelier —</option>
          <?php foreach ($workshops as $w): ?>
          <option value="<?= e($w['id']) ?>" <?= ($ed['workshop_id']??'')===$w['id']?'selected':'' ?>>
            <?= e($w['emoji']??'☕') ?> <?= e($w['name']) ?> (<?= number_format($w['price'],2,',',' ') ?> €)
          </option>
          <?php endforeach; ?>
        </select></div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Date *</label>
          <input class="frm-i" type="date" name="date" required value="<?= e($ed['date']??date('Y-m-d')) ?>"></div>
        <div class="frm-g"><label class="frm-l">Heure *</label>
          <input class="frm-i" type="time" name="time" required value="<?= e($ed['time']??'10:00') ?>"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Nombre de places</label>
          <input class="frm-i" type="number" name="seats" min="1" max="200" value="<?= e($ed['seats']??8) ?>"></div>
        <div class="frm-g"><label class="frm-l">Prix spécial (0 = prix atelier)</label>
          <input class="frm-i" type="number" step="0.01" name="price_override" value="<?= e($ed['price_override']??0) ?>"></div>
      </div>
      <div class="frm-g"><label class="frm-l">Notes internes</label>
        <textarea class="frm-i" name="notes" rows="3" maxlength="300" style="resize:vertical"><?= e($ed['notes']??'') ?></textarea></div>
      <div class="tog-wrap"><label class="tog"><input type="checkbox" name="active" <?= ($ed['active']??true)?'checked':'' ?>><span class="tog-sl"></span></label>
        <span class="tog-lbl">Créneau actif (visible à la réservation)</span></div>
      <button type="submit" class="save-btn" style="margin-top:1rem">💾 Enregistrer</button>
    </div>
  </form>
</div>

<?php elseif ($act === 'bookings'): ?>
<!-- ── Vue réservations d'une session ──────────────────────── -->
<?php
$sess = null;
foreach ($sessions as $s) { if ($s['id'] === $id) { $sess = $s; break; } }
$sess_bk = array_filter($bookings, fn($b) => ($b['session_id']??'') === $id);
$wk = $wk_idx[$sess['workshop_id']??''] ?? [];
?>
<div class="adm-top">
  <div><h2>Réservations — <?= e($wk['name']??'') ?></h2>
  <p><?= e(date('d/m/Y', strtotime($sess['date']??'now'))) ?> à <?= e($sess['time']??'') ?> · <?= array_sum(array_map(fn($b)=>max(1,(int)($b['guests']??1)), array_filter($sess_bk, fn($b)=>($b['status']??'')!=='cancelled'))) ?>/<?= $sess['seats']??0 ?> places</p></div>
  <div class="adm-acts"><a href="./calendar.php" class="a-btn">← Calendrier</a></div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?>
  <div class="alert-ok" style="margin-bottom:1rem">✓ Statut mis à jour.</div>
  <?php endif; ?>
  <?php if (!$sess_bk): ?>
  <div class="empty-st"><div class="empty-ico">👥</div><h4>Aucune réservation</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Nom</th><th>Email</th><th>Tél</th><th>Places</th><th>Message</th><th>Fidélité</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse(array_values($sess_bk)) as $b):
      $s_labels = ['pending'=>['⏳','bdg-o','En attente'],'confirmed'=>['✅','bdg-g','Confirmé'],'cancelled'=>['✗','bdg-r','Annulé'],'waitlist'=>['📋','bdg-gy','Liste att.']];
      $sl = $s_labels[$b['status']??'pending'];
    ?>
    <tr>
      <td style="font-weight:500"><?= e(($b['fname']??'').' '.($b['lname']??'')) ?></td>
      <td style="font-size:.82rem"><a href="mailto:<?= e($b['email']??'') ?>" style="color:var(--or)"><?= e($b['email']??'') ?></a></td>
      <td style="font-size:.8rem;color:var(--gy2)"><?= e($b['phone']??'') ?></td>
      <td style="text-align:center"><?= (int)($b['guests']??1) ?></td>
      <td style="font-size:.8rem;color:var(--gy2);max-width:150px"><?= e(mb_substr($b['message']??'',0,60,'UTF-8')) ?></td>
      <td style="font-size:.78rem"><?= !empty($b['loyalty_used'])?'🎖 Fidélité':'' ?></td>
      <td><span class="bdg <?= $sl[1] ?>"><?= $sl[0] ?> <?= $sl[2] ?></span></td>
      <td>
        <form method="POST" style="display:flex;gap:.3rem">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="booking_status">
          <input type="hidden" name="bid" value="<?= e($b['id']) ?>">
          <input type="hidden" name="sess_id" value="<?= e($id) ?>">
          <select name="bstat" class="frm-sel" style="font-size:.72rem;padding:.28rem .5rem" >
            <?php foreach(['pending'=>'En attente','confirmed'=>'Confirmé','cancelled'=>'Annulé','waitlist'=>'Liste att.'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($b['status']??'pending')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="act-btn prim" style="font-size:.72rem;padding:.3rem .6rem" title="Enregistrer le statut">✓</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ── Vue calendrier mensuel ──────────────────────────────── -->
<div class="adm-top">
  <div>
    <h2>Calendrier des ateliers</h2>
    <p><?= date('F Y', mktime(0,0,0,(int)$M,1,(int)$Y)) ?></p>
  </div>
  <div class="adm-acts">
    <a href="?m=<?= $prevM ?>" class="a-btn">← <?= date('M', mktime(0,0,0,(int)$M-1,1,(int)$Y)) ?></a>
    <a href="?m=<?= $nextM ?>" class="a-btn"><?= date('M', mktime(0,0,0,(int)$M+1,1,(int)$Y)) ?> →</a>
    <a href="./calendar.php?a=new" class="a-btn prim">+ Nouveau créneau</a>
  </div>
</div>
<div class="adm-content">
  <!-- Grille calendrier -->
  <?php
  $firstDay = mktime(0,0,0,(int)$M,1,(int)$Y);
  $daysInMonth = (int)date('t', $firstDay);
  $startDow = (int)date('N', $firstDay); // 1=Lun, 7=Dim
  ?>
  <div style="background:#fff;border-radius:var(--r);border:1px solid #EAE3D9;overflow:hidden;margin-bottom:1.5rem">
    <div style="display:grid;grid-template-columns:repeat(7,1fr);background:#FAF6F1">
      <?php foreach(['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'] as $d): ?>
      <div style="padding:.5rem;text-align:center;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--gy2);font-weight:600"><?= $d ?></div>
      <?php endforeach; ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr)">
      <?php
      // Jours vides avant le 1
      for ($i = 1; $i < $startDow; $i++) echo '<div style="min-height:80px;border:1px solid #FAF0E8"></div>';
      for ($d = 1; $d <= $daysInMonth; $d++):
        $dateStr = sprintf('%s-%02d', $month, $d);
        $daySess = array_filter($month_sessions, fn($s) => $s['date'] === $dateStr);
        $isToday = $dateStr === date('Y-m-d');
      ?>
      <div style="min-height:80px;border:1px solid #FAF0E8;padding:4px;<?= $isToday?'background:#FFF8F5':'' ?>">
        <div style="font-size:.78rem;font-weight:<?= $isToday?'700':'400' ?>;color:<?= $isToday?'var(--or)':'var(--gy2)' ?>;margin-bottom:3px"><?= $d ?></div>
        <?php foreach ($daySess as $sess):
          $booked = $bk_counts[$sess['id']] ?? 0;
          $seats  = $sess['seats'] ?? 8;
          $full   = $booked >= $seats;
          $wk     = $wk_idx[$sess['workshop_id']??''] ?? ['name'=>'Atelier','emoji'=>'☕'];
          $pct    = $seats > 0 ? round($booked/$seats*100) : 0;
        ?>
        <a href="./calendar.php?a=bookings&id=<?= e($sess['id']) ?>" style="display:block;background:<?= $full?'rgba(200,86,30,.12)':'rgba(42,76,30,.08)' ?>;border-left:3px solid <?= $full?'var(--or)':'var(--vr2)' ?>;border-radius:2px;padding:3px 5px;margin-bottom:2px;text-decoration:none">
          <div style="font-size:.67rem;font-weight:600;color:<?= $full?'var(--or)':'var(--vr2)' ?>"><?= e($sess['time']) ?> <?= e($wk['emoji']) ?></div>
          <div style="font-size:.62rem;color:var(--gy2)"><?= $booked ?>/<?= $seats ?> places<?= $full?' · COMPLET':'' ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (!$daySess): ?>
        <a href="./calendar.php?a=new" title="Ajouter un créneau" style="display:block;text-align:center;color:var(--gy3);font-size:1rem;line-height:2.5;text-decoration:none;opacity:0;transition:opacity .2s" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">+</a>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
      <?php $endDow = (int)date('N', mktime(0,0,0,(int)$M,$daysInMonth,(int)$Y));
      for ($i = $endDow+1; $i <= 7; $i++) echo '<div style="min-height:80px;border:1px solid #FAF0E8;background:#FAFAF8"></div>'; ?>
    </div>
  </div>

  <!-- Liste des séances du mois -->
  <?php if ($month_sessions): ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Date</th><th>Heure</th><th>Atelier</th><th>Places</th><th>Réservations</th><th>Statut</th><th>—</th></tr></thead>
    <tbody>
    <?php usort($month_sessions, fn($a,$b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time'])); foreach ($month_sessions as $sess):
      $booked = $bk_counts[$sess['id']] ?? 0;
      $seats  = $sess['seats'] ?? 8;
      $wk     = $wk_idx[$sess['workshop_id']??''] ?? ['name'=>'Atelier','emoji'=>'☕'];
    ?>
    <tr>
      <td style="font-weight:500"><?= e(date('d/m/Y', strtotime($sess['date']))) ?></td>
      <td><?= e($sess['time']) ?></td>
      <td><?= e($wk['emoji']??'☕') ?> <?= e($wk['name']??'') ?></td>
      <td><?= $seats ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:.6rem">
          <div style="flex:1;height:6px;background:#EAE3D9;border-radius:3px;max-width:80px">
            <div style="height:6px;border-radius:3px;background:<?= $booked>=$seats?'var(--or)':'var(--vr2)' ?>;width:<?= min(100,round($booked/$seats*100)) ?>%"></div>
          </div>
          <span style="font-size:.8rem"><?= $booked ?>/<?= $seats ?></span>
          <a href="./calendar.php?a=bookings&id=<?= e($sess['id']) ?>" class="act-btn">Voir</a>
        </div>
      </td>
      <td><span class="bdg <?= ($sess['active']??true)?($booked>=$seats?'bdg-o':'bdg-g'):'bdg-gy' ?>">
        <?= !($sess['active']??true)?'Inactif':($booked>=$seats?'Complet':'Disponible') ?>
      </span></td>
      <td><div class="act-btns">
        <a href="./calendar.php?a=edit&id=<?= e($sess['id']) ?>" class="act-btn">Éditer</a>
        <form method="POST" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="pa" value="delete">
          <input type="hidden" name="did" value="<?= e($sess['id']) ?>">
          <button type="submit" class="act-btn del" onclick="return confirm('Supprimer ce créneau et toutes ses réservations ?')">✕</button>
        </form>
      </div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="empty-st"><div class="empty-ico">📅</div><h4>Aucun créneau ce mois-ci</h4><p><a href="./calendar.php?a=new" style="color:var(--or)">+ Créer un créneau</a></p></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
