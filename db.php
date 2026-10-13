<?php
require_once __DIR__.'/config.php';

function db() {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:'.DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages(
            id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT, direction TEXT, type TEXT,
            body TEXT, media_path TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS seen(wamid TEXT PRIMARY KEY)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT)");
    }
    return $pdo;
}
function setting($k, $d = '') {
    $s = db()->prepare("SELECT v FROM settings WHERE k=?"); $s->execute([$k]);
    $r = $s->fetch(); return $r ? $r['v'] : $d;
}
function set_setting($k, $v) {
    db()->prepare("INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")->execute([$k, $v]);
}
function log_msg($phone, $dir, $type, $body, $media = null) {
    db()->prepare("INSERT INTO messages(phone,direction,type,body,media_path) VALUES(?,?,?,?,?)")
        ->execute([$phone, $dir, $type, $body, $media]);
}
function get_history($phone, $limit = HISTORY_LIMIT) {
    $s = db()->prepare("SELECT direction, body FROM messages WHERE phone=? ORDER BY id DESC LIMIT ?");
    $s->bindValue(1, $phone); $s->bindValue(2, (int)$limit, PDO::PARAM_INT); $s->execute();
    return array_reverse($s->fetchAll());
}
