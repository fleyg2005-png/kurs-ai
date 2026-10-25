<?php
/* Работа с базой: PDO, префикс таблиц, короткие запросы, настройки сайта. */
defined('APP') || exit;

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $pdo = db_connect($c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['user'], $c['pass']);
    }
    return $pdo;
}

function db_connect(string $host, int $port, string $name, string $user, string $pass): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/* Имя таблицы с префиксом: t('users') → kurs_users */
function t(string $name): string { return config('db')['prefix'] . $name; }

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function val(string $sql, array $params = []) { $v = q($sql, $params)->fetchColumn(); return $v === false ? null : $v; }
function last_id(): int { return (int)db()->lastInsertId(); }

/* ---------- Настройки сайта (таблица settings) ---------- */
function setting(string $k, $default = '') {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (rows('SELECT k, v FROM ' . t('settings')) as $r) $cache[$r['k']] = $r['v']; }
    return array_key_exists($k, $cache) ? $cache[$k] : $default;
}
function set_setting(string $k, ?string $v): void {
    q('INSERT INTO ' . t('settings') . ' (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}
