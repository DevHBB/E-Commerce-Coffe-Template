<?php
/**
 * admin/update.php — Mise à jour automatique depuis GitHub
 * Télécharge le ZIP du repo, extrait UNIQUEMENT les fichiers code (jamais data/)
 * Les fichiers JSON et uploads sont préservés intégralement.
 */
require_once __DIR__ . '/../includes/config.php';
require_auth();

// ── Configuration ─────────────────────────────────────────────────────────
define('UPDATE_GITHUB_USER', 'DevHBB');
define('UPDATE_GITHUB_REPO', 'E-Commerce-Coffe-Template');
define('UPDATE_VERSION_FILE', ROOT . '/version.txt');

// Dossiers/fichiers JAMAIS écrasés
const PROTECTED_PATHS = [
    'data/',
    'assets/uploads/',
    '.htaccess',
    'install.php',
];

$cfg     = cfg_get();
$gh_user = clean($cfg['update_github_user'] ?? '', 80) ?: 'DevHBB';
$gh_repo = clean($cfg['update_github_repo'] ?? '', 80) ?: 'E-Commerce-Coffe-Template';
$branch  = clean($cfg['update_github_branch'] ?? 'main', 30);
$api_url = "https://api.github.com/repos/{$gh_user}/{$gh_repo}";
$zip_url = "https://github.com/{$gh_user}/{$gh_repo}/archive/refs/heads/{$branch}.zip";

$local_version  = file_exists(UPDATE_VERSION_FILE) ? trim(file_get_contents(UPDATE_VERSION_FILE)) : '—';
$sha_file  = ROOT . '/.last_commit_sha';
$local_sha = file_exists($sha_file) ? trim(file_get_contents($sha_file)) : '';
$action         = $_POST['action'] ?? '';
$msg_ok         = '';
$msg_err        = '';
$remote_version = null;
$remote_info    = null;

// ── Fonctions utilitaires ──────────────────────────────────────────────────
function gh_fetch(string $url): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'CafeMaison-Updater/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw ? json_decode($raw, true) : null;
}

function is_protected(string $rel_path): bool {
    foreach (PROTECTED_PATHS as $p) {
        if (strncmp($rel_path, $p, strlen($p)) === 0 || $rel_path === rtrim($p, '/')) {
            return true;
        }
    }
    return false;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = "$dir/$item";
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

// ── Récupérer infos GitHub ─────────────────────────────────────────────────
if ($gh_user !== 'VOTRE_USERNAME') {
    $commits     = gh_fetch("{$api_url}/commits/{$branch}");
    $releases    = gh_fetch("{$api_url}/releases/latest");
    $remote_info = $commits;

    // Version = tag du dernier release s'il existe, sinon SHA court du commit
    if ($releases && isset($releases['tag_name'])) {
        $remote_version = $releases['tag_name'];
        $remote_sha     = $releases['tag_name'];
    } elseif ($commits && isset($commits['sha'])) {
        $remote_sha     = $commits['sha']; // SHA complet pour comparaison exacte
        $remote_version = 'commit ' . substr($commits['sha'], 0, 7);
    }
}

// ── Action: Mise à jour ────────────────────────────────────────────────────
if ($action === 'do_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($gh_user === 'VOTRE_USERNAME' || $gh_repo === 'VOTRE_REPO') {
        $msg_err = 'Configurez d\'abord le dépôt GitHub dans les paramètres.';
    } elseif (!function_exists('curl_init')) {
        $msg_err = 'cURL non disponible sur ce serveur.';
    } elseif (!class_exists('ZipArchive')) {
        $msg_err = 'Extension ZIP PHP non disponible.';
    } else {
        // 1. Télécharger le ZIP
        $tmp_zip = sys_get_temp_dir() . '/cafe_update_' . time() . '.zip';
        $ch = curl_init($zip_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'CafeMaison-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $zip_content = curl_exec($ch);
        $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err    = curl_error($ch);
        curl_close($ch);

        if (!$zip_content || $http_code !== 200) {
            $msg_err = "Téléchargement échoué (HTTP $http_code). $curl_err";
        } else {
            file_put_contents($tmp_zip, $zip_content);

            // 2. Extraire dans un dossier temporaire
            $tmp_dir = sys_get_temp_dir() . '/cafe_update_' . time();
            mkdir($tmp_dir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($tmp_zip) !== true) {
                $msg_err = 'Impossible d\'ouvrir le fichier ZIP téléchargé.';
            } else {
                $zip->extractTo($tmp_dir);
                $zip->close();

                // 3. Trouver le dossier racine dans le ZIP (ex: repo-main/)
                $extracted = array_diff(scandir($tmp_dir), ['.', '..']);
                $src_dir   = $tmp_dir . '/' . reset($extracted);

                if (!is_dir($src_dir)) {
                    $msg_err = 'Structure ZIP inattendue.';
                } else {
                    // 4. Backup de sécurité des fichiers actuels
                    $backup_dir = ROOT . '/data/backup_' . date('Ymd_His');
                    mkdir($backup_dir, 0755, true);

                    // 5. Copier récursivement, en sautant les dossiers protégés
                    $updated = 0;
                    $skipped = 0;
                    $errors  = [];

                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($src_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($iterator as $src_path => $file_info) {
                        $rel = ltrim(substr($src_path, strlen($src_dir)), '/\\');
                        if (!$rel) continue;

                        // Sauter les chemins protégés
                        if (is_protected($rel)) {
                            $skipped++;
                            continue;
                        }

                        $dst = ROOT . '/' . $rel;

                        if ($file_info->isDir()) {
                            if (!is_dir($dst)) mkdir($dst, 0755, true);
                        } else {
                            // Backup du fichier existant avant écrasement
                            if (file_exists($dst)) {
                                $bk = $backup_dir . '/' . dirname($rel);
                                if (!is_dir($bk)) mkdir($bk, 0755, true);
                                copy($dst, $backup_dir . '/' . $rel);
                            }
                            if (copy($src_path, $dst)) {
                                $updated++;
                            } else {
                                $errors[] = $rel;
                            }
                        }
                    }

                    // 6. Lire la version depuis le ZIP
                    $new_version_file = $src_dir . '/version.txt';
                    if (file_exists($new_version_file)) {
                        $new_ver = trim(file_get_contents($new_version_file));
                        file_put_contents(UPDATE_VERSION_FILE, $new_ver);
                        $local_version = $new_ver;
                    }
                    // Sauvegarder le SHA du commit installé pour comparaison précise
                    if ($remote_sha) {
                        file_put_contents(ROOT . '/.last_commit_sha', $remote_sha);
                        $local_sha = $remote_sha;
                    }

                    // 7. Nettoyer
                    rrmdir($tmp_dir);
                    unlink($tmp_zip);

                    if ($errors) {
                        $msg_err = count($errors) . ' fichier(s) non mis à jour : ' . implode(', ', array_slice($errors, 0, 5));
                    } else {
                        $msg_ok = "$updated fichier(s) mis à jour. Données préservées (skipped: $skipped). Backup dans data/backup_" . date('Ymd_His') . '/';
                        admin_log('update', "Mise à jour depuis GitHub: {$remote_version} → $updated fichiers");
                    }
                }
            }
        }
    }
}

// ── Action: Sauvegarder la config GitHub ──────────────────────────────────
if ($action === 'save_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = cfg_get();
    $s['update_github_user']   = clean($_POST['gh_user'] ?? '', 80);
    $s['update_github_repo']   = clean($_POST['gh_repo'] ?? '', 80);
    $s['update_github_branch'] = clean($_POST['gh_branch'] ?? 'main', 30);
    file_put_contents(DATA_DIR . '/settings.json',
        json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX);
    $msg_ok = 'Configuration sauvegardée.';
    // Recharger
    $gh_user = $s['update_github_user'];
    $gh_repo = $s['update_github_repo'];
    $branch  = $s['update_github_branch'];
}

$at = 'Mise à jour'; $cur_adm = 'update';
include ROOT . '/admin/inc/layout.php';
?>

<div class="adm-top">
  <div>
    <h2>🔄 Mise à jour du site</h2>
    <p>Mise à jour automatique depuis GitHub — les données JSON ne sont jamais effacées.</p>
  </div>
</div>

<div class="adm-content">

<?php if ($msg_ok): ?>
<div class="alert-ok" style="margin-bottom:1rem">✓ <?= e($msg_ok) ?></div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="alert-err" style="margin-bottom:1rem">✗ <?= e($msg_err) ?></div>
<?php endif; ?>

<!-- ── CONFIG GITHUB ── -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:1rem">⚙️ Configuration GitHub</h4>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_config">
    <div class="frm-row">
      <div class="frm-g">
        <label class="frm-l">Utilisateur / Organisation GitHub</label>
        <input class="frm-i" name="gh_user" value="<?= e($cfg['update_github_user'] ?? 'DevHBB') ?>" placeholder="votre-username">
      </div>
      <div class="frm-g">
        <label class="frm-l">Nom du dépôt</label>
        <input class="frm-i" name="gh_repo" value="<?= e($cfg['update_github_repo'] ?? 'E-Commerce-Coffe-Template') ?>" placeholder="cafe-maison">
      </div>
      <div class="frm-g">
        <label class="frm-l">Branche</label>
        <input class="frm-i" name="gh_branch" value="<?= e($cfg['update_github_branch'] ?? 'main') ?>" placeholder="main" style="max-width:120px">
      </div>
    </div>
    <button type="submit" class="act-btn prim">💾 Sauvegarder</button>
  </form>
</div>

<!-- ── STATUT VERSIONS ── -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:1rem">📦 Versions</h4>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    <div style="background:var(--cr);border-radius:var(--r);padding:1rem">
      <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gy2);margin-bottom:.4rem">Version installée</div>
      <div style="font-size:1.4rem;font-family:var(--f1)"><?= e($local_version) ?></div>
    </div>
    <div style="background:var(--cr);border-radius:var(--r);padding:1rem">
      <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gy2);margin-bottom:.4rem">Dernière version GitHub</div>
      <div style="font-size:1.4rem;font-family:var(--f1)">
        <?php if ($gh_user === 'VOTRE_USERNAME'): ?>
        <span style="color:var(--gy2);font-size:.9rem">Configurez le dépôt →</span>
        <?php elseif ($remote_version): ?>
        <?= e($remote_version) ?>
        <?php else: ?>
        <span style="color:var(--gy2);font-size:.9rem">Impossible de contacter GitHub</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php
    // Comparer: si on a un SHA local et remote → comparaison exacte
    $is_uptodate = false;
    if ($remote_sha && $local_sha) {
        $is_uptodate = ($local_sha === $remote_sha);
    } elseif ($remote_version && $local_version !== '—') {
        $is_uptodate = ($local_version === $remote_version);
    }
  ?>
  <?php if ($is_uptodate): ?>
  <div style="background:rgba(42,76,30,.08);border:1px solid rgba(42,76,30,.2);border-radius:var(--r);padding:.7rem 1rem;font-size:.83rem;color:#2a4c1e">
    ✅ Le site est à jour.
  </div>
  <?php elseif ($remote_version): ?>
  <div style="background:rgba(200,86,30,.08);border:1px solid rgba(200,86,30,.25);border-radius:var(--r);padding:.7rem 1rem;font-size:.83rem;color:var(--or)">
    🔄 Mise à jour disponible.
  </div>
  <?php endif; ?>
  <?php if ($remote_info && isset($remote_info['commit'])): ?>
  <div style="font-size:.78rem;color:var(--gy2);background:var(--cr);border-radius:var(--r);padding:.6rem .9rem;margin-top:.6rem">
    📝 Dernier commit :
    <strong><?= e(substr($remote_info['commit']['message'] ?? '', 0, 80)) ?></strong>
    <span style="color:var(--gy3)">— <?= e($remote_info['commit']['author']['name'] ?? '') ?> · <?= e(substr($remote_info['commit']['committer']['date'] ?? '', 0, 10)) ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- ── MISE À JOUR ── -->
<div class="editor-wrap" style="margin-bottom:1.2rem">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.8rem">🚀 Lancer la mise à jour</h4>

  <div style="background:rgba(42,76,30,.06);border:1px solid rgba(42,76,30,.2);border-radius:var(--r);padding:.8rem 1rem;margin-bottom:1rem;font-size:.82rem">
    <strong>🔒 Ce qui est préservé (jamais écrasé) :</strong><br>
    <code style="font-size:.75rem">data/</code> (tous vos JSON) ·
    <code style="font-size:.75rem">assets/uploads/</code> (vos images) ·
    <code style="font-size:.75rem">.htaccess</code><br><br>
    <strong>📁 Un backup automatique</strong> est créé dans <code style="font-size:.75rem">data/backup_YYYYMMDD_HHiiss/</code> avant chaque mise à jour.
  </div>

  <?php if ($gh_user !== 'VOTRE_USERNAME'): ?>
  <form method="POST" onsubmit="return confirm('Lancer la mise à jour depuis GitHub ?\n\nVos données JSON ne seront pas modifiées.')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="do_update">
    <button type="submit" class="save-btn" style="background:var(--vr2)">
      🔄 Mettre à jour depuis GitHub
    </button>
  </form>
  <?php else: ?>
  <p style="color:var(--gy2);font-size:.85rem">Configurez d'abord le dépôt GitHub ci-dessus.</p>
  <?php endif; ?>
</div>

<!-- ── BACKUPS ── -->
<?php
$backups = [];
if (is_dir(DATA_DIR)) {
    foreach (scandir(DATA_DIR) as $f) {
        if (strncmp($f, 'backup_', 7) === 0 && is_dir(DATA_DIR . '/' . $f)) {
            $backups[] = $f;
        }
    }
    rsort($backups);
}
?>
<?php if ($backups): ?>
<div class="editor-wrap">
  <h4 style="font-family:var(--f1);font-size:1.05rem;margin-bottom:.8rem">🗄️ Backups disponibles (<?= count($backups) ?>)</h4>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Date</th><th>Dossier</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_slice($backups, 0, 10) as $bk):
        $date = preg_replace('/backup_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', '$1-$2-$3 $4:$5:$6', $bk);
        $bk_dir = DATA_DIR . '/' . $bk;
        $count  = iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bk_dir, RecursiveDirectoryIterator::SKIP_DOTS)));
      ?>
      <tr>
        <td style="font-size:.8rem"><?= e($date) ?></td>
        <td><code style="font-size:.72rem">data/<?= e($bk) ?>/</code></td>
        <td style="color:var(--gy2);font-size:.75rem"><?= $count ?> fichier(s)</td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="font-size:.72rem;color:var(--gy2);margin-top:.5rem">
    Supprimez les anciens backups manuellement via FTP si l'espace disque devient limité.
  </p>
</div>
<?php endif; ?>

</div>
<?php include ROOT . '/admin/inc/layout_end.php'; ?>
