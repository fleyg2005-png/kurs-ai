<?php
/* Загрузка приложения: конфиг, база, сессия, общие функции. */
declare(strict_types=1);

define('APP', true);
define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('CONFIG_FILE', ROOT_DIR . '/config.php');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

require APP_DIR . '/helpers.php';
require APP_DIR . '/db.php';
require APP_DIR . '/auth.php';
require APP_DIR . '/courses.php';
require APP_DIR . '/view.php';

/* Базовый путь сайта: «/» в корне или «/папка/», если сайт лежит в подпапке. */
function base_path(): string {
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/');
        $base = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
    }
    return $base;
}

function installed(): bool { return is_file(CONFIG_FILE); }

function config(?string $key = null, $default = null) {
    static $cfg = null;
    if (!installed()) $cfg = $GLOBALS['__install_cfg'] ?? [];   /* во время установки — временная конфигурация */
    elseif ($cfg === null || isset($GLOBALS['__install_cfg'])) { $cfg = require CONFIG_FILE; unset($GLOBALS['__install_cfg']); }
    if ($key === null) return $cfg;
    return $cfg[$key] ?? $default;
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('kurs_sess');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => base_path(), 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    session_start();
}
