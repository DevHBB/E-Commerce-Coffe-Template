<?php
require_once __DIR__ . '/../includes/config.php';
require_once ROOT . '/includes/mailer.php';
require_auth();

$subs = db('newsletter');
$ok   = false; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa']??'',20);

    if ($pa === 'add_manual') {
        $email = strtolower(filter_var($_POST['manual_email']??'', FILTER_VALIDATE_EMAIL)?:'');
        $name  = clean($_POST['manual_name']??'', 100);
        if ($email) {
            $subs2 = db('newsletter');
            $exists = false;
            foreach ($subs2 as &$s) {
                if ($s['email']===$email) { $s['active']=true; $exists=true; }
            } unset($s);
            if (!$exists) {
                $subs2[] = ['id'=>new_id(),'email'=>$email,'name'=>$name,'source'=>'admin','date'=>date('Y-m-d'),'active'=>true];
            }
            db_save('newsletter', $subs2);
            admin_log('newsletter_add', "Abonné ajouté manuellement: $email");
        }
        header('Location: ./newsletter.php?saved=1'); exit;
    }
    if ($pa === 'unsub') {
        $eid = clean($_POST['eid']??'',20);
        foreach ($subs as &$s) { if ($s['id']===$eid) $s['active']=false; } unset($s);
        db_save('newsletter', $subs);
        header('Location: ./newsletter.php?saved=1'); exit;
    }
    if ($pa === 'delete') {
        $eid = clean($_POST['eid']??'',20);
        db_save('newsletter', array_values(array_filter($subs, fn($s)=>$s['id']!==$eid)));
        header('Location: ./newsletter.php?del=1'); exit;
    }
    if ($pa === 'send') {
        $subj    = clean($_POST['subject']??'',200);
        $content = $_POST['content'] ?? '';
        if (!$subj || !$content) { $err = 'Sujet et contenu requis.'; }
        else {
            $active = array_filter($subs, fn($s)=>$s['active']??true);
            $sent = 0;
            $failed = 0;
            foreach ($active as $sub) {
                if (mail_newsletter($sub['email'], $sub['name']??'', $subj, nl2br(htmlspecialchars($content)))) {
                    $sent++;
                } else {
                    $failed++;
                }
                usleep(50000);
            }
            // Sauvegarder dans l'historique
            $history = db('newsletter_history');
            $history[] = [
                'id'       => new_id(),
                'date'     => date('Y-m-d H:i'),
                'subject'  => $subj,
                'content'  => mb_substr($content, 0, 5000),
                'sent'     => $sent,
                'failed'   => $failed,
                'total'    => count($active),
                'admin'    => $_SESSION['_admin_display'] ?? 'admin',
            ];
            db_save('newsletter_history', $history);
            admin_log('newsletter_send', "Envoi: $subj — $sent/$" . count($active) . " destinataires");
            $ok = "Newsletter envoyée à $sent abonné(s)" . ($failed ? " ($failed échecs)" : ".") ;
        }
    }
}

$active_ct = count(array_filter($subs, fn($s)=>$s['active']??true));
$at = 'Newsletter'; $cur_adm = 'newsletter';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div><h2>Newsletter</h2><p><?= $active_ct ?> abonné(s) actif(s) sur <?= count($subs) ?></p></div>
</div>
<div class="adm-content">
  <?php if ($err): ?><div class="alert-err">✗ <?= e($err) ?></div><?php endif; ?>
  <?php if ($ok):  ?><div class="alert-ok"><?= e($ok) ?></div><?php endif; ?>
  <?php
  $smtp_host = $cfg['smtp_host'] ?? '';
  $smtp_user = $cfg['smtp_user'] ?? '';
  $smtp_pass = $cfg['smtp_pass'] ?? '';
  $smtp_from = $cfg['smtp_from'] ?? '';
  $smtp_ok   = $smtp_host && $smtp_user && $smtp_pass && $smtp_from;
  if (!$smtp_ok):
    $missing = [];
    if (!$smtp_host) $missing[] = 'Serveur SMTP';
    if (!$smtp_user) $missing[] = 'Utilisateur';
    if (!$smtp_from) $missing[] = 'Email expéditeur';
    if (!$smtp_pass) $missing[] = 'Mot de passe';
  ?>
  <div style="background:rgba(200,86,30,.08);border:1px solid rgba(200,86,30,.3);border-radius:var(--r);padding:.8rem 1rem;margin-bottom:1rem;font-size:.82rem">
    ⚠️ <strong>SMTP incomplet</strong> — Champs manquants : <em><?= implode(', ', $missing) ?></em>
    <a href="./settings.php" style="color:var(--or);font-weight:600;margin-left:.5rem">⚙ Configurer →</a>
    <small style="display:block;margin-top:.3rem;color:var(--gy2)">
      Après configuration, sauvegardez les paramètres puis revenez ici.
      Actuellement — Serveur: <code><?= e($smtp_host ?: '—') ?></code> ·
      User: <code><?= e($smtp_user ?: '—') ?></code> ·
      From: <code><?= e($smtp_from ?: '—') ?></code> ·
      Pass: <code><?= $smtp_pass ? '••••' : '—' ?></code>
    </small>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
    <!-- Composer -->
    <div>
      <!-- Ajouter un abonné -->
      <div class="editor-wrap" style="margin-bottom:1rem">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:.8rem">➕ Ajouter un abonné</h4>
        <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="add_manual">
          <input class="frm-i" type="email" name="manual_email" required placeholder="email@exemple.fr" style="flex:1;min-width:180px">
          <input class="frm-i" name="manual_name" placeholder="Nom (optionnel)" style="width:160px">
          <button type="submit" class="a-btn prim">Ajouter</button>
        </form>
      </div>
      <div class="editor-wrap">
        <h4 style="font-family:var(--f1);font-size:1.1rem;margin-bottom:1rem">✉ Composer une newsletter</h4>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="pa" value="send">
          <div class="frm-g"><label class="frm-l">Sujet *</label>
            <input class="frm-i" name="subject" required maxlength="200" placeholder="Ex: Notre sélection de novembre"></div>
          <div class="frm-g"><label class="frm-l">Contenu (texte)</label>
            <textarea class="frm-i" name="content" rows="12" style="resize:vertical" required
              placeholder="Bonjour,&#10;&#10;...votre message...&#10;&#10;À bientôt,&#10;L'équipe Café Maison"></textarea>
          </div>
          <div style="background:rgba(200,86,30,.07);border:1px solid rgba(200,86,30,.2);border-radius:var(--r);padding:.8rem 1rem;font-size:.8rem;color:var(--gy);margin-bottom:1rem">
            ⚠ Cet envoi partira à <strong><?= $active_ct ?> abonné(s)</strong> immédiatement. Non annulable.
          </div>
          <button type="submit" class="save-btn" onclick="return confirm('Envoyer à <?= $active_ct ?> abonné(s) ?')">
            📨 Envoyer la newsletter
          </button>
        </form>
      </div>
    </div>
    <!-- Liste abonnés -->
    <div>
      <div class="tbl-wrap">
        <div class="tbl-hd"><span class="pnl-t">Abonnés</span>
          <a href="?export=csv&_csrf=<?= urlencode(csrf_token()) ?>" class="a-btn btn-sm">⬇ Export CSV</a>
        </div>
        <?php if (!$subs): ?>
        <div class="empty-st"><div class="empty-ico">📧</div><h4>Aucun abonné</h4></div>
        <?php else: ?>
        <table>
          <thead><tr><th>Email</th><th>Nom</th><th>Source</th><th>Statut</th><th>—</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($subs) as $s): ?>
          <tr>
            <td style="font-size:.82rem"><?= e($s['email']??'') ?></td>
            <td style="font-size:.82rem"><?= e($s['name']??'') ?></td>
            <td style="font-size:.75rem;color:var(--gy2)"><?= e($s['source']??'') ?></td>
            <td><span class="bdg <?= ($s['active']??true)?'bdg-g':'bdg-gy' ?>"><?= ($s['active']??true)?'Actif':'Désinscrit' ?></span></td>
            <td><div class="act-btns">
              <?php if ($s['active']??true): ?>
              <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="unsub"><input type="hidden" name="eid" value="<?= e($s['id']) ?>"><button type="submit" class="act-btn">Désinscrire</button></form>
              <?php endif; ?>
              <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="delete"><input type="hidden" name="eid" value="<?= e($s['id']) ?>"><button type="submit" class="act-btn del" data-confirm="Supprimer ?">✕</button></form>
            </div></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
// Export CSV
if (isset($_GET['export']) && $_GET['export']==='csv') {
    csrf_check_get();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputs($out,"\xEF\xBB\xBF");
    fputcsv($out,['Email','Nom','Source','Date','Statut'],';');
    foreach ($subs as $s) fputcsv($out,[$s['email']??'',$s['name']??'',$s['source']??'',$s['date']??'',($s['active']??true)?'Actif':'Désinscrit'],';');
    fclose($out); exit;
}
?>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
