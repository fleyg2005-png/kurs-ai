<?php
/* Миграции базы: файлы database/migrations/NNN_название.sql применяются по порядку, каждый один раз.
   Применённые записываются в таблицу {p}migrations. Запуск: php bin/migrate.php
   Новое изменение базы — новый файл со следующим номером; старые файлы не правим. */
defined('APP') || exit;

const MIGRATIONS_DIR = ROOT_DIR . '/database/migrations';

/* Ошибки MySQL, которые значат «это уже сделано»: повторный запуск после сбоя не ломается */
const MIGRATION_ALREADY_DONE = [1050, 1060, 1061, 1091];

function migration_files(): array {
    $files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $f) $out[basename($f, '.sql')] = $f;
    return $out;
}

function migrations_applied(PDO $pdo): array {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . t('migrations') . ' (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)'
        . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = [];
    foreach ($pdo->query('SELECT name, applied_at FROM ' . t('migrations')) as $r) $done[$r['name']] = $r['applied_at'];
    return $done;
}

/* Запросы файла: разделитель — «;» в конце строки, строки-комментарии «--» отбрасываются */
function sql_statements(string $sql): array {
    $sql = (string)preg_replace('~^\s*--.*$~m', '', $sql);
    return array_values(array_filter(array_map('trim', preg_split('~;\s*$~m', $sql) ?: []), 'strlen'));
}

/* Применить новые миграции. $log получает строки отчёта. Возвращает число применённых. */
function run_migrations(callable $log): int {
    $pdo = db();
    $lock = $pdo->quote(t('migrate'));
    if ((int)$pdo->query("SELECT GET_LOCK($lock, 30)")->fetchColumn() !== 1) throw new RuntimeException('Миграции уже запущены в другом процессе.');
    try {
        $done = migrations_applied($pdo);
        $count = 0;
        foreach (migration_files() as $name => $file) {
            if (isset($done[$name])) continue;
            foreach (sql_statements(str_replace('{p}', t(''), (string)file_get_contents($file))) as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    if (!in_array((int)($e->errorInfo[1] ?? 0), MIGRATION_ALREADY_DONE, true)) throw new RuntimeException($name . ': ' . $e->getMessage(), 0, $e);
                    $log('   уже есть, пропускаю: ' . mb_substr((string)preg_replace('~\s+~', ' ', $stmt), 0, 80));
                }
            }
            $pdo->prepare('INSERT INTO ' . t('migrations') . ' (name, applied_at) VALUES (?, ?)')->execute([$name, now()]);
            $log('применена ' . $name);
            $count++;
        }
        return $count;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK($lock)");
    }
}

/* Список миграций и их состояние для php bin/migrate.php --status */
function migrations_status(): array {
    $done = migrations_applied(db());
    $out = [];
    foreach (migration_files() as $name => $_) $out[] = ['name' => $name, 'applied_at' => $done[$name] ?? null];
    return $out;
}

