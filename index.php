<?php
/* Точка входа: все адреса сайта приходят сюда (см. .htaccess). */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
$path = str_starts_with($uri, $base) ? substr($uri, strlen($base)) : ltrim($uri, '/');
$path = rawurldecode($path);
if ($path === 'index.php') $path = '';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!configured()) {
    plain_page(503, 'Сайт настраивается', 'Администратору: скопируйте .env.example в .env, укажите доступ к базе и запустите ./deploy.sh --seed.');
    exit;
}

require APP_DIR . '/router.php';
try {
    dispatch($path);
} catch (Throwable $e) {
    error_log('[kurs] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $detail = config('debug') ? ' ' . get_class($e) . ': ' . $e->getMessage() : '';
    if ($e instanceof PDOException && in_array((string)$e->getCode(), ['42S02', '42S22'], true)) {
        /* Код уже новый, а база ещё нет: после git pull не запустили миграции */
        plain_page(503, 'Сайт обновляется', 'Зайдите через пару минут. Администратору: запустите ./deploy.sh — нужны миграции базы.' . $detail);
    } elseif ($e instanceof PDOException) {
        plain_page(503, 'База данных недоступна', 'Попробуйте обновить страницу чуть позже.' . $detail);
    } else {
        plain_page(500, 'Ошибка на сервере', 'Что-то пошло не так. Попробуйте обновить страницу чуть позже.' . $detail);
    }
}
