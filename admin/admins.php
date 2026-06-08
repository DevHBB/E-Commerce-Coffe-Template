<?php
require_once __DIR__ . '/../includes/config.php';
require_auth();

// Seul le super_admin peut gérer les comptes
if (!admin_can('all')) {
    http_response_code(403);
    die('<div style="padding:2rem;font-family:sans-serif">⛔ Accès réservé au super administrateur.</div>');
}

$admins = db('admins');
$ok = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pa = clean($_POST['pa'] ?? '', 20);

    if ($pa === 'add') {
        $username = preg_replace('/[^a-z0-9_]/', '', strtolower(clean($_POST['username'] ?? '', 30)));
        $display  = clean($_POST['display_name'] ?? '', 80);
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $role     = in_array($_POST['role'] ?? '', ['super_admin','admin','editor'], true) ? $_POST['role'] : 'editor';
        $pass1    = $_POST['password'] ?? '';
        $pass2    = $_POST['password2'] ?? '';
        $perms    = db('roles')[$role] ?? ['dashboard'];

        if (!$username || !$display) $err = 'Nom d\'utilisateur et nom d\'affichage requis.';
        elseif (strlen($pass1) < 8)  $err = 'Mot de passe : 8 caractères minimum.';
        elseif ($pass1 !== $pass2)   $err = 'Les mots de passe ne correspondent pas.';
        else {
            // Vérifier unicité du username
            foreach ($admins as $a) {
                if ($a['username'] === $username) { $err = "Nom d'utilisateur déjà utilisé."; break; }
            }
        }
        if (!$err) {
            $new_id = new_id();
            $hash_file = DATA_DIR . '/.admin_hash_' . $new_id;
            file_put_contents($hash_file, password_hash($pass1, PASSWORD_BCRYPT, ['cost'=>10]), LOCK_EX);
            chmod($hash_file, 0600);
            $admins[] = [
                'id'           => $new_id,
                'username'     => $username,
                'display_name' => $display,
                'email'        => $email,
                'role'         => $role,
                'permissions'  => $perms,
                'active'       => true,
                'created'      => date('Y-m-d H:i'),
                'last_login'   => '',
            ];
            db_save('admins', $admins);
            admin_log('admin_add', "Nouveau compte admin: $username ($role)");
            $ok = "Compte « $display » créé.";
        }
    }

    if ($pa === 'toggle') {
        $aid = clean($_POST['aid'] ?? '', 40);
        // Ne pas désactiver son propre compte
        if ($aid === ($_SESSION['_admin_id'] ?? '')) {
            $err = 'Vous ne pouvez pas désactiver votre propre compte.';
        } else {
            foreach ($admins as &$a) {
                if ($a['id'] === $aid) {
                    $a['active'] = !($a['active'] ?? true);
                    $ok = "Compte " . ($a['active'] ? 'activé' : 'désactivé') . ".";
                    admin_log('admin_toggle', "Compte {$a['username']} " . ($a['active'] ? 'activé' : 'désactivé'));
                }
            } unset($a);
            db_save('admins', $admins);
        }
        header('Location: ./admins.php?saved=1'); exit;
    }

    if ($pa === 'delete') {
        $aid = clean($_POST['aid'] ?? '', 40);
        if ($aid === ($_SESSION['_admin_id'] ?? '')) {
            $err = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $del_user = '';
            foreach ($admins as $a) {
                if ($a['id'] === $aid) { $del_user = $a['username']; break; }
            }
            $admins = array_values(array_filter($admins, fn($a) => $a['id'] !== $aid));
            db_save('admins', $admins);
            // Supprimer le fichier hash
            @unlink(DATA_DIR . '/.admin_hash_' . $aid);
            admin_log('admin_delete', "Compte supprimé: $del_user");
            header('Location: ./admins.php?saved=1'); exit;
        }
    }

    if ($pa === 'change_pass') {
        $aid   = clean($_POST['aid'] ?? '', 40);
        $pass1 = $_POST['new_pass']  ?? '';
        $pass2 = $_POST['new_pass2'] ?? '';
        if (strlen($pass1) < 8) $err = 'Mot de passe : 8 caractères minimum.';
        elseif ($pass1 !== $pass2) $err = 'Les mots de passe ne correspondent pas.';
        else {
            $hash_file = DATA_DIR . '/.admin_hash_' . $aid;
            file_put_contents($hash_file, password_hash($pass1, PASSWORD_BCRYPT, ['cost'=>10]), LOCK_EX);
            chmod($hash_file, 0600);
            admin_log('admin_pass_change', "Mot de passe changé pour admin id=$aid");
            $ok = 'Mot de passe mis à jour.';
        }
    }

    $admins = db('admins'); // relire
}

$roles_map = ['super_admin'=>'Super Admin','admin'=>'Administrateur','editor'=>'Éditeur'];
$role_colors = ['super_admin'=>'var(--or)','admin'=>'var(--vr2)','editor'=>'var(--gy2)'];

$at = 'Comptes admin'; $cur_adm = 'admins';
include ROOT . '/admin/inc/layout.php';
?>
<div class="adm-top">
  <div>
    <h2>👥 Comptes administrateurs</h2>
    <p>Gérez les accès à l'administration du site</p>
  </div>
</div>
<div class="adm-content">
  <?php if ($ok): ?><div class="alert-ok" style="margin-bottom:1rem">✓ <?= e($ok) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-err" style="margin-bottom:1rem">✗ <?= e($err) ?></div><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><div class="alert-ok" style="margin-bottom:1rem">✓ Modification enregistrée.</div><?php endif; ?>

  <!-- Liste des comptes -->
  <div class="editor-wrap" style="margin-bottom:1.5rem">
    <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:1rem">Comptes existants</h4>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr>
          <th>Nom affiché</th><th>Identifiant</th><th>Email</th>
          <th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($admins as $adm): ?>
        <tr>
          <td><strong><?= e($adm['display_name']??'') ?></strong></td>
          <td><code><?= e($adm['username']??'') ?></code></td>
          <td style="font-size:.78rem"><?= e($adm['email']??'—') ?></td>
          <td><span style="font-size:.72rem;font-weight:600;color:<?= $role_colors[$adm['role']??'editor'] ?>"><?= $roles_map[$adm['role']??'editor'] ?></span></td>
          <td style="font-size:.75rem;color:var(--gy2)"><?= $adm['last_login'] ? e($adm['last_login']) : 'Jamais' ?></td>
          <td>
            <?php if ($adm['id'] === ($_SESSION['_admin_id'] ?? '')): ?>
            <span class="badge bdg-g" style="font-size:.65rem">Vous</span>
            <?php else: ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="pa" value="toggle">
              <input type="hidden" name="aid" value="<?= e($adm['id']) ?>">
              <button type="submit" class="act-btn" style="font-size:.65rem">
                <?= ($adm['active']??true) ? '⏸ Désactiver' : '▶ Activer' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
          <td>
            <div class="act-btns">
              <button type="button" class="act-btn"
                onclick="document.getElementById('chpass_<?= e($adm['id']) ?>').style.display='block'">
                🔑 MDP
              </button>
              <?php if ($adm['id'] !== ($_SESSION['_admin_id'] ?? '')): ?>
              <form method="POST" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="pa" value="delete">
                <input type="hidden" name="aid" value="<?= e($adm['id']) ?>">
                <button type="submit" class="act-btn del"
                  onclick="return confirm('Supprimer ce compte ?')">✕</button>
              </form>
              <?php endif; ?>
            </div>
            <!-- Changement mot de passe inline -->
            <div id="chpass_<?= e($adm['id']) ?>" style="display:none;margin-top:.5rem">
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="pa" value="change_pass">
                <input type="hidden" name="aid" value="<?= e($adm['id']) ?>">
                <input type="password" name="new_pass" class="frm-i" placeholder="Nouveau mot de passe"
                  style="font-size:.72rem;padding:.3rem .5rem;margin-bottom:.2rem" required minlength="8">
                <input type="password" name="new_pass2" class="frm-i" placeholder="Confirmer"
                  style="font-size:.72rem;padding:.3rem .5rem;margin-bottom:.3rem" required>
                <button type="submit" class="act-btn prim" style="font-size:.7rem">✓ Changer</button>
                <button type="button" class="act-btn" style="font-size:.7rem"
                  onclick="this.closest('div').style.display='none'">Annuler</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Créer un compte -->
  <div class="editor-wrap">
    <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:1rem">➕ Créer un compte</h4>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="pa" value="add">
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Identifiant (login) *</label>
          <input class="frm-i" name="username" maxlength="30" placeholder="employe1"
            pattern="[a-z0-9_]+" title="Minuscules, chiffres et _ uniquement">
        </div>
        <div class="frm-g">
          <label class="frm-l">Nom affiché *</label>
          <input class="frm-i" name="display_name" maxlength="80" placeholder="Marie Dupont">
        </div>
      </div>
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Email</label>
          <input class="frm-i" type="email" name="email" maxlength="150" placeholder="marie@cafe.fr">
        </div>
        <div class="frm-g">
          <label class="frm-l">Rôle</label>
          <select name="role" class="frm-sel">
            <option value="editor">Éditeur — articles, newsletter</option>
            <option value="admin" selected>Administrateur — tout sauf créer des admins</option>
            <option value="super_admin">Super Admin — accès complet</option>
          </select>
        </div>
      </div>
      <div class="frm-row">
        <div class="frm-g">
          <label class="frm-l">Mot de passe *</label>
          <input class="frm-i" type="password" name="password" minlength="8" placeholder="8 caractères minimum">
        </div>
        <div class="frm-g">
          <label class="frm-l">Confirmer *</label>
          <input class="frm-i" type="password" name="password2" minlength="8">
        </div>
      </div>
      <div style="background:var(--cr);border-radius:var(--r);padding:.7rem 1rem;font-size:.77rem;color:var(--gy2);margin-bottom:.8rem">
        🔐 <strong>Rôles :</strong><br>
        <strong style="color:var(--or)">Super Admin</strong> — accès total, gestion des comptes<br>
        <strong style="color:var(--vr2)">Administrateur</strong> — tout sauf gestion des comptes admin<br>
        <strong style="color:var(--gy2)">Éditeur</strong> — articles, newsletter uniquement
      </div>
      <button type="submit" class="save-btn">➕ Créer le compte</button>
    </form>
  </div>
</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
