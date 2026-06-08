<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

// Construire la base clients depuis les commandes + réservations + newsletter
$orders   = db('orders');
$bookings = db('bookings');
$nl       = db('newsletter');
$clients  = db('clients'); // clients ajoutés manuellement

// Indexer par email
$client_map = [];
foreach ($clients as $cl) {
    $email = strtolower($cl['email']??'');
    if ($email) $client_map[$email] = $cl;
}
// Fusionner depuis commandes
foreach ($orders as $o) {
    $c = $o['customer']??[];
    $email = strtolower($c['email']??'');
    if (!$email) continue;
    if (!isset($client_map[$email])) {
        $client_map[$email] = ['id'=>new_id(),'fname'=>$c['fname']??'','lname'=>$c['lname']??'','email'=>$email,'phone'=>$c['phone']??'','addr'=>$c['addr']??'','zip'=>$c['zip']??'','city'=>$c['city']??'','source'=>'order','created'=>$o['created_at']??date('Y-m-d'),'note'=>''];
    }
    $client_map[$email]['orders_count'] = ($client_map[$email]['orders_count']??0) + 1;
    $client_map[$email]['orders_total'] = round(($client_map[$email]['orders_total']??0) + ($o['amount']??0),2);
    $client_map[$email]['last_order']   = max($client_map[$email]['last_order']??'', $o['created_at']??'');
}
// Fusionner depuis réservations
foreach ($bookings as $b) {
    $email = strtolower($b['email']??'');
    if (!$email) continue;
    if (!isset($client_map[$email])) {
        $client_map[$email] = ['id'=>new_id(),'fname'=>$b['fname']??'','lname'=>$b['lname']??'','email'=>$email,'phone'=>$b['phone']??'','source'=>'booking','created'=>$b['created']??date('Y-m-d'),'note'=>''];
    }
    $client_map[$email]['bookings_count'] = ($client_map[$email]['bookings_count']??0)+1;
}
// Fusionner newsletter
foreach ($nl as $sub) {
    $email = strtolower($sub['email']??'');
    if (!$email) continue;
    if (isset($client_map[$email])) $client_map[$email]['newsletter'] = $sub['active']??false;
}

$all_clients = array_values($client_map);

// ── POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $pa = clean($_POST['pa']??'', 20);

    if ($pa === 'delete_client') {
        $cid = clean($_POST['cid'] ?? '', 100);
        $clients_all = db('clients');
        $clients_all = array_values(array_filter($clients_all, fn($c) =>
            ($c['id'] ?? '') !== $cid && ($c['email'] ?? '') !== $cid
        ));
        db_save('clients', $clients_all);
        admin_log('client_delete', "Client $cid supprimé");
        header('Location: ./clients.php?del=1'); exit;
    }

    if ($pa==='edit') {
        $eid   = clean($_POST['eid']??'',30);
        $email = strtolower(filter_var($_POST['email']??'', FILTER_VALIDATE_EMAIL)?:'');
        if ($email) {
            $saved = db('clients');
            $found = false;
            foreach ($saved as &$cl) {
                if ($cl['id']===$eid || strtolower($cl['email']??'')===$email) {
                    $cl = array_merge($cl, [
                        'fname'=>clean($_POST['fname']??'',80),
                        'lname'=>clean($_POST['lname']??'',80),
                        'email'=>$email,
                        'phone'=>clean($_POST['phone']??'',30),
                        'addr' =>clean($_POST['addr']??'',200),
                        'zip'  =>preg_replace('/[^0-9]/','',($_POST['zip']??'')),
                        'city' =>clean($_POST['city']??'',100),
                        'note' =>clean($_POST['note']??'',500),
                        'updated'=>date('Y-m-d'),
                    ]);
                    $found = true;
                }
            } unset($cl);
            if (!$found) {
                $saved[] = [
                    'id'    =>new_id(),
                    'fname' =>clean($_POST['fname']??'',80),
                    'lname' =>clean($_POST['lname']??'',80),
                    'email' =>$email,
                    'phone' =>clean($_POST['phone']??'',30),
                    'addr'  =>clean($_POST['addr']??'',200),
                    'zip'   =>preg_replace('/[^0-9]/','',($_POST['zip']??'')),
                    'city'  =>clean($_POST['city']??'',100),
                    'note'  =>clean($_POST['note']??'',500),
                    'source'=>'admin',
                    'created'=>date('Y-m-d'),
                ];
            }
            db_save('clients', $saved);
            admin_log('client_edit',"Client $email modifié");
        }
        header('Location: ./clients.php?saved=1'); exit;
    }
    if ($pa==='import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error']===0) {
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','txt'])) {
                $import_err = 'Format non supporté. Utilisez un fichier .csv';
            } else {
                $saved = db('clients');
                $existing_emails = array_map(fn($c)=>strtolower($c['email']??''), $saved);
                $added = 0; $skipped = 0;
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $headers = null;
                while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                    if (!$headers) {
                        // Détecter séparateur et headers
                        if (count($row) < 2) {
                            rewind($handle);
                            $row = fgetcsv($handle, 1000, ',');
                        }
                        $headers = array_map('strtolower', array_map('trim', $row));
                        continue;
                    }
                    if (empty($headers)) continue;
                    $d = array_combine(array_slice($headers, 0, count($row)), array_slice($row, 0, count($headers)));
                    $email = strtolower(filter_var($d['email']??'', FILTER_VALIDATE_EMAIL)?:'');
                    if (!$email) { $skipped++; continue; }
                    if (in_array($email, $existing_emails)) { $skipped++; continue; }
                    $saved[] = [
                        'id'    => new_id(),
                        'fname' => clean($d['prenom']??$d['firstname']??$d['prénom']??'', 80),
                        'lname' => clean($d['nom']??$d['lastname']??$d['name']??'', 80),
                        'email' => $email,
                        'phone' => clean($d['telephone']??$d['tel']??$d['phone']??'', 30),
                        'addr'  => clean($d['adresse']??$d['address']??$d['addr']??'', 200),
                        'zip'   => preg_replace('/[^0-9]/','',$d['cp']??$d['zip']??$d['code_postal']??''),
                        'city'  => clean($d['ville']??$d['city']??'', 100),
                        'note'  => clean($d['note']??'', 500),
                        'source'=> 'import_csv',
                        'created'=> date('Y-m-d'),
                    ];
                    $existing_emails[] = $email;
                    $added++;
                }
                fclose($handle);
                db_save('clients', $saved);
                admin_log('client_import_csv', "$added clients importés, $skipped ignorés");
                $import_ok = "$added client(s) importé(s). $skipped ignoré(s) (email invalide ou déjà existant).";
            }
        } else {
            $import_err = 'Erreur lors du chargement du fichier.';
        }
        header('Location: ./clients.php?saved=1&msg='.urlencode($import_ok??'')); exit;
    }

    if ($pa==='add') {
        // Ajout manuel
        $email = strtolower(filter_var($_POST['email']??'', FILTER_VALIDATE_EMAIL)?:'');
        if ($email) {
            $saved = db('clients');
            $exists = false;
            foreach ($saved as $cl) { if (strtolower($cl['email']??'')===$email) { $exists=true; break; } }
            if (!$exists) {
                $saved[] = ['id'=>new_id(),'fname'=>clean($_POST['fname']??'',80),'lname'=>clean($_POST['lname']??'',80),'email'=>$email,'phone'=>clean($_POST['phone']??'',30),'addr'=>clean($_POST['addr']??'',200),'zip'=>preg_replace('/[^0-9]/','',($_POST['zip']??'')),'city'=>clean($_POST['city']??'',100),'note'=>clean($_POST['note']??'',500),'source'=>'admin','created'=>date('Y-m-d')];
                db_save('clients',$saved);
                admin_log('client_add',"Client $email ajouté manuellement");
            }
        }
        header('Location: ./clients.php?saved=1'); exit;
    }
}

// ── Export CSV ─────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    csrf_check_get();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputs($out,"\xEF\xBB\xBF");
    fputcsv($out,['Prénom','Nom','Email','Téléphone','Adresse','CP','Ville','Commandes','CA total','Réservations','Newsletter','Source','Créé le','Note'],';');
    foreach ($all_clients as $cl) {
        fputcsv($out,[$cl['fname']??'',$cl['lname']??'',$cl['email']??'',$cl['phone']??'',$cl['addr']??'',$cl['zip']??'',$cl['city']??'',$cl['orders_count']??0,$cl['orders_total']??0,$cl['bookings_count']??0,($cl['newsletter']??false)?'Oui':'Non',$cl['source']??'','$cl["created"]'??'',$cl['note']??''],';');
    }
    fclose($out); exit;
}

// ── Recherche / filtres ───────────────────────────────────────────
$search = clean($_GET['q']??'',100);
$src    = clean($_GET['src']??'all',20);
if ($search) {
    $all_clients = array_filter($all_clients, fn($cl)=>
        (strpos(strtolower(($cl['fname']??'').' '.($cl['lname']??'').' '.($cl['email']??'').' '.($cl['city']??'')), strtolower($search) !== false)));
}
if ($src!=='all') {
    $all_clients = array_filter($all_clients, fn($cl)=>($cl['source']??'')===$src);
}
$all_clients = array_values($all_clients);

// Vue édition
$act = clean($_GET['act']??'list',10);
$eid = clean($_GET['e']??'',100); // email

$edit_client = null;
if ($act==='edit' && $eid) {
    foreach ($client_map as $em=>$cl) { if ($em===strtolower($eid)) { $edit_client=$cl; break; } }
}

$at = 'Fiches clients'; $cur_adm = 'clients';
include ROOT . '/admin/inc/layout.php';
?>

<?php if ($act==='edit' && $edit_client): ?>
<div class="adm-top">
  <div><h2><?= e(($edit_client['fname']??'').' '.($edit_client['lname']??'')) ?: e($edit_client['email']??'') ?></h2><p>Fiche client</p></div>
  <div class="adm-acts"><a href="./clients.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="edit">
      <input type="hidden" name="eid" value="<?= e($edit_client['id']??'') ?>">
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">✏ Modifier</h4>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Prénom</label><input class="frm-i" name="fname" maxlength="80" value="<?= e($edit_client['fname']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Nom</label><input class="frm-i" name="lname" maxlength="80" value="<?= e($edit_client['lname']??'') ?>"></div>
        </div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">Email</label><input class="frm-i" type="email" name="email" maxlength="150" value="<?= e($edit_client['email']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" name="phone" maxlength="30" value="<?= e($edit_client['phone']??'') ?>"></div>
        </div>
        <div class="frm-g"><label class="frm-l">Adresse</label><input class="frm-i" name="addr" maxlength="200" value="<?= e($edit_client['addr']??'') ?>"></div>
        <div class="frm-row">
          <div class="frm-g"><label class="frm-l">CP</label><input class="frm-i" name="zip" maxlength="10" value="<?= e($edit_client['zip']??'') ?>"></div>
          <div class="frm-g"><label class="frm-l">Ville</label><input class="frm-i" name="city" maxlength="100" value="<?= e($edit_client['city']??'') ?>"></div>
        </div>
        <div class="frm-g"><label class="frm-l">Note interne</label><textarea class="frm-i" name="note" rows="4" maxlength="500"><?= e($edit_client['note']??'') ?></textarea></div>
        <button type="submit" class="save-btn">💾 Enregistrer</button>
      </div>
    </form>
    <div>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">📊 Activité</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
          <?php foreach([['Commandes',$edit_client['orders_count']??0],['CA total',(($edit_client['orders_total']??0).' €')],['Réservations',$edit_client['bookings_count']??0],['Newsletter',($edit_client['newsletter']??false)?'✓ Oui':'Non']] as [$l,$v]): ?>
          <div class="st"><div class="st-lbl"><?= $l ?></div><div style="font-family:var(--f1);font-size:1.4rem"><?= $v ?></div></div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:1rem;font-size:.78rem;color:var(--gy2)">
          <div>Source : <?= e($edit_client['source']??'') ?></div>
          <div>Client depuis : <?= e($edit_client['created']??'') ?></div>
          <?php if (!empty($edit_client['last_order'])): ?><div>Dernière commande : <?= e($edit_client['last_order']) ?></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($act==='add'): ?>
<div class="adm-top">
  <div><h2>Ajouter un client</h2></div>
  <div class="adm-acts"><a href="./clients.php" class="a-btn">← Retour</a></div>
</div>
<div class="adm-content">
  <form method="POST" style="max-width:680px">
    <?= csrf_field() ?>
    <input type="hidden" name="pa" value="add">
    <div class="editor-wrap">
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Prénom</label><input class="frm-i" name="fname" maxlength="80"></div>
        <div class="frm-g"><label class="frm-l">Nom</label><input class="frm-i" name="lname" maxlength="80"></div>
      </div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">Email *</label><input class="frm-i" type="email" name="email" required maxlength="150"></div>
        <div class="frm-g"><label class="frm-l">Téléphone</label><input class="frm-i" name="phone" maxlength="30"></div>
      </div>
      <div class="frm-g"><label class="frm-l">Adresse</label><input class="frm-i" name="addr" maxlength="200"></div>
      <div class="frm-row">
        <div class="frm-g"><label class="frm-l">CP</label><input class="frm-i" name="zip" maxlength="10"></div>
        <div class="frm-g"><label class="frm-l">Ville</label><input class="frm-i" name="city" maxlength="100"></div>
      </div>
      <div class="frm-g"><label class="frm-l">Note interne</label><textarea class="frm-i" name="note" rows="3" maxlength="500"></textarea></div>
      <button type="submit" class="save-btn">➕ Ajouter le client</button>
    </div>
  </form>
</div>

<?php else: ?>
<div class="adm-top">
  <div><h2>Fiches clients</h2><p><?= count($all_clients) ?> client(s)</p></div>
  <div class="adm-acts">
    <a href="?act=add" class="a-btn prim">+ Ajouter</a>
    <button type="button" class="a-btn" onclick="document.getElementById('csvImportBox').style.display=document.getElementById('csvImportBox').style.display==='none'?'block':'none'">⬆ Importer CSV</button>
    <a href="?export=1&_csrf=<?= urlencode(csrf_token()) ?>" class="a-btn">⬇ Exporter CSV</a>
  </div>
</div>
<div class="adm-content">
  <?php if (isset($_GET['saved'])): ?>
  <div class="alert-ok" style="margin-bottom:1rem">✓ Enregistré. <?= e($_GET['msg']??'') ?></div>
  <?php endif; ?>

  <!-- Import CSV -->
  <div id="csvImportBox" style="display:none;background:#fff;border:1px solid var(--cr);border-radius:var(--r);padding:1.4rem;margin-bottom:1.2rem">
    <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.7rem">⬆ Importer des clients via CSV</h4>
    <p style="font-size:.8rem;color:var(--gy2);margin-bottom:.8rem">
      Le fichier CSV doit avoir une ligne d'en-tête. Colonnes acceptées :<br>
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">email</code> (obligatoire),
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">prenom</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">nom</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">telephone</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">adresse</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">cp</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">ville</code>,
      <code style="background:var(--cr);padding:.1rem .4rem;border-radius:3px">note</code>.
      Séparateur ; ou , — encodage UTF-8. Les emails déjà présents sont ignorés.
    </p>
    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="import_csv">
      <div class="frm-g" style="flex:1;min-width:250px"><label class="frm-l">Fichier CSV</label>
        <input type="file" name="csv_file" accept=".csv,.txt" class="frm-i" required></div>
      <button type="submit" class="save-btn" style="white-space:nowrap">⬆ Importer</button>
    </form>
    <p style="font-size:.7rem;color:var(--gy2);margin-top:.6rem">
      💡 Exemple de format : <a href="#" onclick="downloadExample();return false" style="color:var(--or)">télécharger un exemple</a>
    </p>
  </div>
  <!-- Recherche -->
  <form method="GET" style="display:flex;gap:.6rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <input type="text" name="q" class="frm-i" value="<?= e($search) ?>" placeholder="Rechercher nom, email, ville…" style="flex:1;min-width:200px">
    <select name="src" class="frm-sel" style="width:160px">
      <option value="all" <?= $src==='all'?'selected':'' ?>>Toutes sources</option>
      <option value="order" <?= $src==='order'?'selected':'' ?>>Commandes</option>
      <option value="booking" <?= $src==='booking'?'selected':'' ?>>Réservations</option>
      <option value="admin" <?= $src==='admin'?'selected':'' ?>>Ajout manuel</option>
    </select>
    <button type="submit" class="a-btn prim">🔍 Filtrer</button>
    <?php if ($search||$src!=='all'): ?><a href="./clients.php" class="a-btn">✕ Réinitialiser</a><?php endif; ?>
  </form>
  <?php if (!$all_clients): ?>
  <div class="empty-st"><div class="empty-ico">👥</div><h4>Aucun client trouvé</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Nom</th><th>Email</th><th>Ville</th><th>Commandes</th><th>CA</th><th>NL</th><th>Source</th><th>—</th></tr></thead>
    <tbody>
    <?php foreach ($all_clients as $cl): ?>
    <tr>
      <td style="font-weight:500"><?= e(trim(($cl['fname']??'').' '.($cl['lname']??'')))?:'-' ?></td>
      <td style="font-size:.82rem"><a href="mailto:<?= e($cl['email']??'') ?>" style="color:var(--or)"><?= e($cl['email']??'') ?></a></td>
      <td style="font-size:.82rem;color:var(--gy2)"><?= e($cl['city']??'') ?></td>
      <td style="text-align:center"><?= (int)($cl['orders_count']??0) ?></td>
      <td style="font-weight:600"><?= ($cl['orders_total']??0)>0?number_format($cl['orders_total'],2,',',' ').' €':'-' ?></td>
      <td style="text-align:center"><?= !empty($cl['newsletter'])?'✓':'' ?></td>
      <td><span class="bdg bdg-gy" style="font-size:.62rem"><?= e($cl['source']??'') ?></span></td>
      <td>
        <div class="act-btns">
          <a href="?act=edit&e=<?= urlencode($cl['email']??'') ?>" class="act-btn">✏ Éditer</a>
          <form method="POST" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="pa" value="delete_client">
            <input type="hidden" name="cid" value="<?= e($cl['id']??$cl['email']??'') ?>">
            <button type="submit" class="act-btn del" onclick="return confirm('Supprimer ce client définitivement ?')">✕</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
function downloadExample() {
  const csv = "prenom;nom;email;telephone;adresse;cp;ville;note\nJean;Dupont;jean@example.com;0612345678;12 rue de Paris;75001;Paris;Client VIP";
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'exemple_import_clients.csv';
  a.click();
}
</script>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
