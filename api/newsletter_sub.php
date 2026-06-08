<?php
require_once __DIR__ . '/../includes/config.php';
security_headers();

$B   = base_path();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';

// Accepte GET ou POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_ok('nl_sub_'.$ip, 5, 300)) {
        // Redirect avec erreur
        header('Location: ' . $B . '?nl=rate'); exit;
    }
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        header('Location: ' . $B . '?nl=invalid'); exit;
    }

    $subs = db('newsletter');
    $found = false;
    foreach ($subs as &$s) {
        if ($s['email'] === (string)$email) {
            $s['active'] = true;
            $found = true;
        }
    } unset($s);
    if (!$found) {
        $subs[] = ['id'=>new_id(),'email'=>(string)$email,'name'=>'','source'=>'footer','date'=>date('Y-m-d'),'active'=>true];
    }
    db_save('newsletter', $subs);
    header('Location: ' . $B . '?nl=ok'); exit;
}

// Accès direct GET → rediriger
header('Location: ' . $B . '?nl=ok'); exit;
