<?php
/* Первичное наполнение: настройки, администратор (ADMIN_EMAIL и ADMIN_PASSWORD из .env),
   курсы из content/. Запуск: php bin/seed.php. Повторный запуск безопасен. */
require __DIR__ . '/cli.php';
require APP_DIR . '/migrate.php';
require APP_DIR . '/seed.php';

try {
    foreach (migrations_status() as $m) if (!$m['applied_at']) cli_fail('Сначала миграции: php bin/migrate.php (не применена ' . $m['name'] . ').');
    $ask = null;
    if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
        $ask = function ($question) { echo '   ' . $question; return (string)fgets(STDIN); };
    }
    run_seeds('cli_log', $ask);
} catch (Throwable $e) {
    cli_fail('Сиды не прошли: ' . $e->getMessage());
}
