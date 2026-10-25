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

if (!installed()) {
    require APP_DIR . '/installer.php';
    installer_run();
    exit;
}

require APP_DIR . '/router.php';
require APP_DIR . '/migrations.php';
try {
    migrate();
    dispatch($path);
} catch (Throwable $e) {
    error_log('[kurs] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    render_message('Ошибка на сервере', 'Что-то пошло не так. Попробуйте обновить страницу чуть позже.');
}
