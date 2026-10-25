<?php
/* Миграции базы: php bin/migrate.php           — применить новые
                  php bin/migrate.php --status  — показать, что применено */
require __DIR__ . '/cli.php';
require APP_DIR . '/migrate.php';

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
