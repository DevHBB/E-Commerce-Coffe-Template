<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$logs = array_reverse(db('admin_log'));
$search = clean($_GET['q']??'',100);
$flt_action = clean($_GET['a']??'all',30);

// Types d'actions uniques
$action_types = array_unique(array_column($logs,'action'));
sort($action_types);

// Filtrage
if ($search) $logs = array_filter($logs, fn($l)=>(strpos(strtolower(($l['action']??'').' '.($l['detail']??'').' '.($l['ip']??'')), strtolower($search) !== false)));
if ($flt_action!=='all') $logs = array_filter($logs, fn($l)=>($l['action']??'')===$flt_action);
$logs = array_values($logs);

// Export CSV
if (isset($_GET['export'])) {
    csrf_check_get();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_log_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputs($out,"\xEF\xBB\xBF");
    fputcsv($out,['Date','Admin','Action','Détail','IP','URL'],';');
    foreach (array_reverse(db('admin_log')) as $l) fputcsv($out,[$l['ts']??'',$l['admin']??'admin',$l['action']??'',$l['detail']??'',$l['ip']??'',$l['url']??''],';');
    fclose($out); exit;
}

$at = 'Journal admin'; $cur_adm = 'log';
include ROOT . '/admin/inc/layout.php';

$action_colors = [
    'login_ok'=>'bdg-g','login_fail'=>'bdg-r','login_blocked'=>'bdg-r',
    'logout'=>'bdg-gy','settings_saved'=>'bdg-o','order_status'=>'bdg-g',
    'order_edit_customer'=>'bdg-o','order_delete'=>'bdg-r','order_bulk_status'=>'bdg-g',
    'order_bulk_delete'=>'bdg-r','client_edit'=>'bdg-o','client_add'=>'bdg-g',
    'quiz_questions'=>'bdg-o','quiz_profiles'=>'bdg-o','password_changed'=>'bdg-r',
];
?>
<div class="adm-top">
  <div><h2>Journal d'administration</h2><p><?= count($logs) ?> entrée(s)</p></div>
  <div class="adm-acts">
    <a href="?export=1&_csrf=<?= urlencode(csrf_token()) ?>" class="a-btn">⬇ CSV</a>
  </div>
</div>
<div class="adm-content">
  <form method="GET" style="display:flex;gap:.6rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <input type="text" name="q" class="frm-i" value="<?= e($search) ?>" placeholder="Rechercher…" style="flex:1;min-width:180px">
    <select name="a" class="frm-sel" style="width:200px">
      <option value="all">Toutes les actions</option>
      <?php foreach ($action_types as $at2): ?><option value="<?= e($at2) ?>" <?= $flt_action===$at2?'selected':'' ?>><?= e($at2) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="a-btn prim">🔍</button>
    <?php if ($search||$flt_action!=='all'): ?><a href="./log.php" class="a-btn">✕</a><?php endif; ?>
  </form>

  <?php if (!$logs): ?>
  <div class="empty-st"><div class="empty-ico">📋</div><h4>Aucune entrée</h4></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Détail</th><th>IP</th><th>URL</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($logs,0,500) as $l): ?>
    <tr>
      <td style="font-size:.78rem;white-space:nowrap;color:var(--gy2)"><?= e($l['ts']??'') ?></td>
      <td style="font-size:.75rem;font-weight:600;color:var(--or)"><?= e($l['admin'] ?? $l['user'] ?? 'admin') ?></td>
      <td><span class="bdg <?= $action_colors[$l['action']??'']??'bdg-gy' ?>" style="font-size:.65rem"><?= e($l['action']??'') ?></span></td>
      <td style="font-size:.82rem;max-width:300px"><?= e($l['detail']??'') ?></td>
      <td style="font-size:.72rem;color:var(--gy2);font-family:monospace"><?= e($l['ip']??'') ?></td>
      <td style="font-size:.7rem;color:var(--gy3);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($l['url']??'') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (count($logs)>500): ?><p style="font-size:.75rem;color:var(--gy2);margin-top:.8rem">Affichage des 500 entrées les plus récentes. Exportez en CSV pour l'historique complet.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
