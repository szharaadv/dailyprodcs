<?php
require_once __DIR__ . '/config/db.php'; // starts the session

// Clear this app's session.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// TODO (ONE Yadin): redirect to the ONE Yadin single-logout URL instead of
// the local landing page once that URL is known, e.g.:
//   header('Location: https://one-yadin.example/logout'); exit;
header('Location: index.php');
exit;
