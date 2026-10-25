<?php
/* Роутер для встроенного сервера PHP (только для разработки): повторяет правила .htaccess.
   Запуск: php -S localhost:8790 tools/dev-router.php */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = dirname(__DIR__);
if (preg_match('~(^|/)\.(?!well-known/)~', $path) || preg_match('~^/(app|bin|database|content|tools|tests|vendor)(/|$)~', $path) || preg_match('~^/(config\.php|composer\.(json|lock)|.+\.(md|sql|log|sh))$~', $path)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
/* Как в .htaccess: настоящие файлы и папки отдаются как есть, .php — только через index.php */
if ($path !== '/' && (is_dir($root . $path) || (is_file($root . $path) && !str_ends_with($path, '.php')))) return false;
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
return true;
