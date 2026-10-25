<?php
/* Загрузка приложения: конфиг, база, сессия, общие функции. */
declare(strict_types=1);

define('APP', true);
define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('ENV_FILE', ROOT_DIR . '/.env');
define('LEGACY_CONFIG', ROOT_DIR . '/config.php');   /* от веб-установщика прежней версии */

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

/* .env в корне проекта: строки KEY=VALUE, «#» — комментарий. Значение с пробелами или «#»
   берут в кавычки. Переменные окружения сервера важнее файла. */
function env_file(): array {
    static $vars = null;
    if ($vars !== null) return $vars;
    $vars = [];
    if (!is_file(ENV_FILE) || !is_readable(ENV_FILE)) return $vars;
    foreach (file(ENV_FILE, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_starts_with($line, 'export ')) $line = ltrim(substr($line, 7));
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        if (!preg_match('~^[A-Za-z_][A-Za-z0-9_]*$~', $k)) continue;
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $quote = $v[0];
            $end = strpos($v, $quote, 1);
            while ($quote === '"' && $end !== false && $v[$end - 1] === '\\') $end = strpos($v, $quote, $end + 1);
            $v = $end === false ? substr($v, 1) : substr($v, 1, $end - 1);
            if ($quote === '"') $v = strtr($v, ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n"]);
        } else {
            $v = (string)preg_replace('~\s+#.*$~', '', $v);
        }
        $vars[$k] = $v;
    }
    return $vars;
}

/* Значение настройки: окружение сервера, затем .env; null — если не задано нигде */
function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v !== false) return $v;
    $f = env_file();
    return array_key_exists($key, $f) ? $f[$key] : $default;
}

function configured(): bool { return (string)env('DB_NAME', '') !== '' || is_file(LEGACY_CONFIG); }

function config(?string $key = null, $default = null) {
    static $cfg = null;
    if ($cfg === null) {
        if ((string)env('DB_NAME', '') === '' && is_file(LEGACY_CONFIG)) {
            $legacy = require LEGACY_CONFIG;
            $cfg = ['db' => (array)($legacy['db'] ?? [])];
        } else {
            $cfg = ['db' => [
                'host' => (string)env('DB_HOST', '') ?: 'localhost',
                'port' => (int)(env('DB_PORT', '') ?: 3306),
                'name' => (string)env('DB_NAME', ''),
                'user' => (string)env('DB_USER', ''),
                'pass' => (string)env('DB_PASS', ''),
                'prefix' => (string)env('DB_PREFIX', 'kurs_'),
            ]];
        }
        /* Префикс подставляется в SQL — только безопасные символы */
        if (!preg_match('~^[A-Za-z0-9_]{0,20}$~', (string)($cfg['db']['prefix'] ?? ''))) throw new RuntimeException('DB_PREFIX: только латиница, цифры и «_», до 20 символов.');
        $cfg['app_url'] = rtrim((string)env('APP_URL', ''), '/');
        $cfg['debug'] = env('APP_DEBUG', '0') === '1';
    }
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
