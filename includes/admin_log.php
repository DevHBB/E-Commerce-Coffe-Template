<?php
/**
 * Journal de toutes les actions administrateur
 * Stocké dans data/admin_log.json
 * Appelé via admin_log('action', 'détail', $extra_data)
 */
if (!function_exists('admin_log')) {
function admin_log(string $action, string $detail = '', array $extra = []): void {
    $log   = db('admin_log');
    $entry = [
        'id'       => new_id(),
        'ts'       => date('Y-m-d H:i:s'),
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'ua'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
        'admin'    => $_SESSION['_admin_display'] ?? ($_SESSION['_admin_user'] ?? ADMIN_USER),
        'user'     => $_SESSION['_admin_user'] ?? ADMIN_USER,
        'action'   => $action,
        'detail'   => $detail,
        'url'      => $_SERVER['REQUEST_URI'] ?? '',
    ];
    if ($extra) $entry['data'] = $extra;
    
    // Garder seulement les 5000 dernières entrées
    if (count($log) >= 5000) {
        $log = array_slice($log, -4900);
    }
    $log[] = $entry;
    db_save('admin_log', $log);
}
}
