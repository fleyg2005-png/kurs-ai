<?php
/* Отрисовка страниц: шапка документа, страницы курса (через движок), страницы сайта. */
defined('APP') || exit;

/* Адрес статики с версией по дате файла — после git pull браузер сразу берёт новое */
function asset(string $file): string {
    $path = ROOT_DIR . '/assets/' . $file;
    return url('assets/' . $file) . (is_file($path) ? '?v=' . filemtime($path) : '');
}

function head_html(string $title, string $description = '', array $extraCss = []): string {
    $css = '<link rel="stylesheet" href="' . e(asset('style.css')) . '">';
    foreach ($extraCss as $f) $css .= '<link rel="stylesheet" href="' . e(asset($f)) . '">';
    return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title>'
        . ($description !== '' ? '<meta name="description" content="' . e($description) . '">' : '')
        . $css . '<link rel="icon" href="' . e(asset('favicon.svg')) . '" type="image/svg+xml">'
        . "<script>try{var t=localStorage.getItem('aikurs:theme');if(t)document.documentElement.setAttribute('data-theme',JSON.parse(t));}catch(e){}</script>"
        . '</head>';
}

function engine_scripts(array $data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    return '<script>window.AIKURS_DATA=' . $json . ';</script><script src="' . e(asset('app.js')) . '"></script>';
}

/* ---------- Страницы курса (их собирает движок app.js) ---------- */
function course_notice(array $c, ?array $u): string {
    $out = '';
    if (!$c['is_published']) $out .= '<div class="warn no-print"><p><strong>Черновик.</strong> Курс не опубликован: его видят только администраторы. <a href="' . e(url('admin/courses/' . $c['id'])) . '">Настройки курса</a></p></div>';
    return $out;
}

function render_course_page(array $c, ?array $u, string $kind, array $o = []): void {
    $data = engine_data($c, $u);
    $root = course_url($c);
    $title = $o['title'] ?? $c['title'];
    $attrs = 'data-root="' . e($root) . '"';
    if ($kind === 'lesson') $attrs .= ' data-lesson="' . e($o['lesson']['slug']) . '"';
    else $attrs .= ' data-page="' . e($kind) . '"';
    echo head_html($title, $o['description'] ?? (string)$c['description']);
    echo '<body ' . $attrs . '>';
    if ($kind === 'index') {
        echo '<main class="content wide">' . flash_html() . course_notice($c, $u) . ($o['banner'] ?? '') . (trim((string)$c['home_html']) !== '' ? $c['home_html'] : default_home($c, $u)) . '</main>';
    } elseif ($kind === 'certificate') {
        echo '<main class="content"><article><p class="kicker no-print">Финиш</p><h1 class="no-print">Сертификат</h1>'
            . '<p class="lead no-print">Отметьте все уроки пройденными — и здесь появится сертификат, который можно распечатать или сохранить в PDF.</p>'
            . '<div id="cert-area"></div></article></main>';
    } else {
        $l = $o['lesson'];
        echo '<main class="content">' . flash_html() . course_notice($c, $u) . '<article><h1>' . e($l['title']) . '</h1>' . ($o['body'] ?? $l['body']) . '</article></main>';
    }
    echo engine_scripts($data) . '</body></html>';
}

/* Главная курса, если в админке не задан свой HTML */
function default_home(array $c, ?array $u): string {
    $tracks = tracks_of((int)$c['id']);
    $access = ACCESS_LABELS[$c['access']] ?? '';
    $h = '<section class="hero"><p class="kicker">' . e(trim(($c['category'] ? $c['category'] . ' · ' : '') . $c['price_label'] . ' · ' . $access, ' ·')) . '</p>'
       . '<h1>' . e($c['title']) . '</h1>'
       . ($c['description'] !== '' ? '<p class="lead">' . nl2br(e($c['description'])) . '</p>' : '')
       . '<div class="hero-actions"><a class="btn primary" id="continue-btn" href="#map">▶ Начать курс</a><a class="btn ghost" href="#map">Содержание</a></div>'
       . '<div class="stats" id="stats"></div><div class="progress" id="index-progress"><span style="width:0%"></span></div></section>';
    $h .= access_banner($c, $u);
    if ($tracks) $h .= '<section id="track"><h2>Выберите путь</h2><p>Общие модули проходят все, дальше — путь на выбор. Выбор можно поменять в любой момент.</p><div id="track-chooser"></div></section>';
    $h .= '<section id="map"><h2>Содержание</h2><div class="module-list" id="course-map"></div></section>';
    return $h;
}

/* Подсказка о доступе на главной курса */
function access_banner(array $c, ?array $u): string {
    if (can_access($c, $u)) return '';
    $next = rawurlencode(course_url($c));
    if (!$u) {
        $text = $c['access'] === 'login'
            ? '<strong>Курс бесплатный, но нужен вход.</strong> Зарегистрируйтесь за минуту — и прогресс будет сохраняться в аккаунте.'
            : '<strong>Курс закрытый.</strong> Войдите — после входа понадобится код доступа или приглашение.';
        return '<div class="gate-banner"><p>' . $text . '</p><div class="row"><a class="btn primary" href="' . e(url('account/register') . '?next=' . $next) . '">Зарегистрироваться</a><a class="btn" href="' . e(url('account/login') . '?next=' . $next) . '">Войти</a></div></div>';
    }
    return '<div class="gate-banner"><p><strong>Курс закрытый.</strong> Введите код доступа — его выдаёт автор курса.</p>' . unlock_form($c) . '</div>';
}

function unlock_form(array $c): string {
    return '<form class="row unlock-form" method="post" action="' . e(course_url($c, 'unlock')) . '">' . csrf_field()
        . '<input name="code" required maxlength="64" placeholder="Код доступа" autocomplete="off"><button class="btn primary" type="submit">Открыть курс</button></form>';
}

/* Замок вместо закрытого урока */
function gate_body(array $c, ?array $u): string {
    $next = rawurlencode($_SERVER['REQUEST_URI'] ?? course_url($c));
    if (!$u) {
        $why = $c['access'] === 'login' ? 'Курс бесплатный — нужен только вход, чтобы сохранялся прогресс.' : 'Курс закрытый: после входа понадобится код доступа или приглашение.';
        return '<div class="gate"><div class="gate-ico">🔒</div><h2 class="mt0">Урок доступен после входа</h2><p>' . e($why) . '</p>'
            . '<div class="row"><a class="btn primary" href="' . e(url('account/register') . '?next=' . $next) . '">Зарегистрироваться</a><a class="btn" href="' . e(url('account/login') . '?next=' . $next) . '">Войти</a></div></div>';
    }
    return '<div class="gate"><div class="gate-ico">🔑</div><h2 class="mt0">Нужен код доступа</h2><p>Курс закрытый. Введите код, который выдал автор курса, — откроются все уроки.</p>' . unlock_form($c) . '</div>';
}

/* ---------- Страницы сайта: каталог, вход, кабинет ---------- */
function site_header(): string {
    $u = configured() ? current_user() : null;
    $name = configured() ? setting('site_name', 'Курсы') : 'Курсы';
    $nav = '<a href="' . e(url()) . '">Курсы</a>';
    if ($u && $u['role'] === 'admin') $nav .= '<a href="' . e(url('admin')) . '">Админка</a>';
    $nav .= $u ? '<a href="' . e(url('account')) . '">' . e($u['name'] ?: 'Кабинет') . '</a>' : '<a href="' . e(url('account/login')) . '">Войти</a><a class="btn small primary" href="' . e(url('account/register')) . '">Регистрация</a>';
    return '<header class="site-header"><a class="site-logo" href="' . e(url()) . '"><span class="logo">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>' . e($name) . '</a>'
        . '<nav class="site-nav">' . $nav . '</nav><button class="icon-btn theme-btn" type="button" aria-label="Переключить тему">🌙</button></header>';
}

function render_site_page(string $title, string $body, array $o = []): void {
    echo head_html($title . (configured() ? ' — ' . setting('site_name', 'Курсы') : ''), $o['description'] ?? '');
    echo '<body class="site">' . site_header() . '<main class="site-main' . (!empty($o['narrow']) ? ' narrow' : '') . '">' . flash_html() . $body . '</main>';
    echo '<footer class="site-footer"><span>© ' . date('Y') . ' ' . e(configured() ? setting('site_name', 'Курсы') : 'Курсы') . '</span><a href="' . e(url('privacy')) . '">Политика обработки данных</a></footer>';
    echo '<script src="' . e(asset('site.js')) . '"></script></body></html>';
}

function render_message(string $title, string $text, int $code = 0): void {
    if ($code) http_response_code($code);
    render_site_page($title, '<div class="card msg-card"><h1 class="mt0">' . e($title) . '</h1><p>' . e($text) . '</p><a class="btn" href="' . e(url()) . '">На главную</a></div>', ['narrow' => true]);
}

/* Страница без обращения к базе: «сайт настраивается» и ошибки сервера */
function plain_page(int $code, string $title, string $text): void {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        if ($code === 503) header('Retry-After: 120');
    }
    echo head_html($title) . '<body class="site"><main class="site-main narrow"><div class="card msg-card"><h1 class="mt0">' . e($title) . '</h1><p>' . e($text) . '</p></div></main></body></html>';
}

function not_found(): void { render_message('Страница не найдена', 'Такой страницы нет. Возможно, адрес изменился или курс снят с публикации.', 404); exit; }
