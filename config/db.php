<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'dailyprod');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Without this, UPDATE's rowCount() reports rows actually
            // *changed*, not rows *matched* — so re-saving a record whose
            // columns happen to already hold the same values (e.g. only one
            // item's result changed) makes our "did the lock guard match?"
            // checks (WHERE id=? AND status="draft", or an edit-request
            // unlock check) look like they found nothing and falsely report
            // "already locked", even though the row was found and is fine.
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ]);
    }
    return $pdo;
}
