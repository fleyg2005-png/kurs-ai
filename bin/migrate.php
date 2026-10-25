<?php
/* Миграции базы: php bin/migrate.php              — применить новые и обновить курсы из content/
                  php bin/migrate.php --status     — показать, что применено
                  php bin/migrate.php --no-content — только миграции, курсы не трогать */
require __DIR__ . '/cli.php';
require APP_DIR . '/migrate.php';
require APP_DIR . '/importer.php';

try {
    if (in_array('--status', $argv, true)) {
        foreach (migrations_status() as $m) echo '   ' . ($m['applied_at'] ? '[x] ' : '[ ] ') . $m['name'] . ($m['applied_at'] ? '  ' . $m['applied_at'] : '  — не применена') . "\n";
        exit(0);
    }
    $n = run_migrations('cli_log');
    cli_log($n ? 'готово, применено: ' . $n : 'база в актуальном состоянии');
} catch (Throwable $e) {
    cli_fail('Миграция не прошла: ' . $e->getMessage());
}

if (in_array('--no-content', $argv, true)) exit(0);
try {
    sync_content('cli_log');
} catch (Throwable $e) {
    cli_fail('Курсы из content/ не обновились: ' . $e->getMessage());
}
