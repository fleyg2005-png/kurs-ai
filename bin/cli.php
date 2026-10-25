<?php
/* Общее для консольных скриптов: проверка PHP и расширений, загрузка приложения.
   Этот файл и скрипты в bin/ написаны простым синтаксисом, чтобы на старом PHP
   вместо ошибки разбора появилась понятная подсказка. */
if (PHP_SAPI !== 'cli') { header('HTTP/1.0 404 Not Found'); exit; }

function cli_fail($msg) { fwrite(STDERR, '!! ' . $msg . "\n"); exit(1); }

if (PHP_VERSION_ID < 80200) {
    cli_fail('Нужен PHP 8.2 или новее, а этот php — ' . PHP_VERSION . ' (' . PHP_BINARY . ").\n"
        . '   Укажи нужный: PHP_BIN=/путь/к/php ./deploy.sh   Найти версии: ls /opt/php*/bin/php /usr/bin/php8*');
}
foreach (array('pdo_mysql' => 'php8.3-mysql', 'mbstring' => 'php8.3-mbstring') as $ext => $pkg) {
    if (!extension_loaded($ext)) cli_fail('В этом php (' . PHP_BINARY . ') нет расширения ' . $ext . '. Поставь пакет ' . $pkg . ' или укажи другой PHP_BIN.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!configured()) cli_fail('Нет .env с доступом к базе — скопируй .env.example в .env и заполни DB_NAME, DB_USER, DB_PASS.');
try {
    db();
} catch (Exception $e) {
    cli_fail('Не подключиться к базе ' . config('db')['name'] . ' на ' . config('db')['host'] . ': ' . $e->getMessage() . "\n   Проверь DB_HOST, DB_NAME, DB_USER, DB_PASS в .env.");
}

function cli_log($line) { echo '   ' . $line . "\n"; }
