<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

$msgs = db('messages');
$view = clean($_GET['id'] ?? '', 20);
$msg  = null;

// Mark as read
if ($view) {
    foreach ($msgs as &$m) { if ($m['id'] === $view) { $m['read'] = true; $msg = $m; } } unset($m);
    db_save('messages', $msgs);
}

// Delete
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['pa']??'')==='delete') {
    csrf_check();
    $did = clean($_POST['did'] ?? '', 20);
    db_save('messages', array_values(array_filter($msgs, fn($m) => $m['id'] !== $did)));
    header('Location: ./messages.php?del=1'); exit;
}

$at = 'Messages'; $cur_adm = 'msg';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div><h2>Messages</h2><p><?= count($msgs) ?> message(s) · <?= count(array_filter($msgs, fn($m) => !($m['read']??false))) ?> non lu(s)</p></div>
  <?php if ($msg): ?><div class="adm-acts"><a href="./messages.php" class="a-btn">← Liste</a></div><?php endif; ?>
</div>
<div class="adm-content">

<?php if ($msg): ?>
  <!-- Vue message -->
  <div class="msg-full">
    <div class="msg-hd">
      <h3 style="font-family:var(--f1);font-size:1.5rem;margin-bottom:.4rem;"><?= e($msg['name']) ?></h3>
      <div style="font-size:.82rem;color:var(--gy2);">
        <a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a> · <?= e($msg['date'] ?? '') ?>
      </div>
      <div style="margin-top:.5rem;font-size:.82rem;color:var(--gy);"><strong>Sujet :</strong> <?= e($msg['sujet'] ?? 'N/A') ?></div>
    </div>
    <div class="msg-body"><?= nl2br(e($msg['message'])) ?></div>
    <div style="margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--cr);display:flex;gap:.8rem;">
      <a href="mailto:<?= e($msg['email']) ?>?subject=Re: <?= urlencode($msg['sujet'] ?? 'Votre message') ?>" class="a-btn prim">Répondre par email</a>
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="pa" value="delete"><input type="hidden" name="did" value="<?= e($msg['id']) ?>">
        <button class="a-btn" style="color:#c0392b;border-color:#c0392b;" data-confirm="Supprimer ce message ?" type="submit">Supprimer</button>
      </form>
    </div>
  </div>

<?php elseif (!$msgs): ?>
  <div class="empty-st"><div class="empty-ico">✉</div><h4>Aucun message reçu</h4></div>

<?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Lu</th><th>Nom</th><th>Email</th><th>Sujet</th><th>Date</th><th>—</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($msgs) as $m): ?>
        <tr>
          <td><div style="width:8px;height:8px;border-radius:50%;background:<?= !($m['read']??false)?'var(--or)':'transparent;border:1px solid var(--gy3)' ?>;margin:auto;"></div></td>
          <td style="font-weight:<?= !($m['read']??false)?'600':'400' ?>;"><?= e($m['name']) ?></td>
          <td style="font-size:.82rem;color:var(--gy2);"><?= e($m['email']) ?></td>
          <td style="font-size:.82rem;color:var(--gy2);"><?= e($m['sujet'] ?? '') ?></td>
          <td style="font-size:.78rem;color:var(--gy2);"><?= e($m['date'] ?? '') ?></td>
          <td>
            <div class="act-btns">
              <a href="./messages.php?id=<?= e($m['id']) ?>" class="act-btn">Lire</a>
              <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="pa" value="delete"><input type="hidden" name="did" value="<?= e($m['id']) ?>"><button class="act-btn del" data-confirm="Supprimer ce message ?" type="submit">✕</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<?php include ROOT . '/admin/inc/layout_end.php'; ?>
