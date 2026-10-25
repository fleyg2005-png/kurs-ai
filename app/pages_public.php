<?php
/* Публичные страницы: каталог, курс, урок, сертификат, код доступа, политика, прогресс. */
defined('APP') || exit;

function page_catalog(): void {
    $f = ['q' => trim((string)get('q')), 'category' => trim((string)get('category')), 'access' => (string)get('access')];
    $page = max(1, (int)get('page', 1)); $per = 24;
    [$items, $total] = catalog($f, $page, $per);
    $cats = categories();
    $u = current_user();

    $h = '<section class="cat-hero"><h1>' . e(setting('site_title', 'Курсы')) . '</h1>'
       . '<p class="lead">' . e(setting('site_tagline', 'Практические курсы. Бесплатные открываются сразу, без регистрации.')) . '</p>'
       . '<form class="cat-search" method="get" action="' . e(url()) . '"><input type="search" name="q" value="' . e($f['q']) . '" placeholder="Найти курс" aria-label="Поиск курса">'
       . ($f['category'] !== '' ? '<input type="hidden" name="category" value="' . e($f['category']) . '">' : '')
       . '<button class="btn primary" type="submit">Найти</button></form></section>';

    $chip = function (string $label, array $q, bool $active) { return '<a class="chip' . ($active ? ' active' : '') . '" href="' . e(url() . ($q ? '?' . http_build_query($q) : '')) . '">' . e($label) . '</a>'; };
    $h .= '<div class="chips cat-filters">' . $chip('Все', array_filter(['q' => $f['q']]), $f['category'] === '' && $f['access'] === '');
    foreach ($cats as $cat => $n) $h .= $chip($cat . ' · ' . $n, array_filter(['q' => $f['q'], 'category' => $cat]), $f['category'] === $cat);
    $h .= $chip('Открытые', array_filter(['q' => $f['q'], 'access' => 'open']), $f['access'] === 'open');
    $h .= '</div>';

    if (!$items) {
        $h .= '<div class="card empty"><h3 class="mt0">Ничего не нашлось</h3><p>Попробуйте другой запрос или <a href="' . e(url()) . '">посмотрите все курсы</a>.</p></div>';
    } else {
        $h .= '<div class="course-grid">';
        foreach ($items as $c) {
            $badge = $c['access'] === 'open' ? '<span class="badge ok">' . e($c['price_label'] ?: 'Бесплатно') . '</span>'
                   : ($c['access'] === 'login' ? '<span class="badge">' . e($c['price_label'] ?: 'Бесплатно') . ' · по регистрации</span>'
                   : '<span class="badge warn">🔒 ' . e($c['price_label'] ?: 'Закрытый') . '</span>');
            $min = (int)$c['minutes_total'];
            $dur = $min <= 0 ? '' : ($min < 60 ? $min . ' мин' : '≈ ' . str_replace('.', ',', (string)($min < 600 ? round($min / 60, 1) : round($min / 60))) . ' ч');
            $h .= '<a class="course-card" href="' . e(course_url($c)) . '"><div class="cc-top"><span class="cc-emoji">' . e($c['emoji'] ?: '📘') . '</span>' . $badge . '</div>'
                . '<h3>' . e($c['title']) . '</h3><p>' . e(mb_strimwidth((string)$c['description'], 0, 180, '…')) . '</p>'
                . '<div class="cc-meta">' . ($c['category'] ? '<span>' . e($c['category']) . '</span>' : '') . '<span>' . (int)$c['lessons_count'] . ' ' . plural((int)$c['lessons_count'], 'урок', 'урока', 'уроков') . '</span>' . ($dur !== '' ? '<span>' . e($dur) . '</span>' : '') . '</div></a>';
        }
        $h .= '</div>';
        $pages = (int)ceil($total / $per);
        if ($pages > 1) {
            $h .= '<nav class="pager">';
            for ($i = 1; $i <= $pages; $i++) $h .= '<a class="' . ($i === $page ? 'active' : '') . '" href="' . e(url() . '?' . http_build_query(array_filter($f + ['page' => $i]))) . '">' . $i . '</a>';
            $h .= '</nav>';
        }
    }
    render_site_page(setting('site_title', 'Курсы'), $h, ['description' => setting('site_tagline', '')]);
}

function page_course_home(array $c): void {
    $u = current_user();
    $banner = trim((string)$c['home_html']) !== '' ? access_banner($c, $u) : '';
    render_course_page($c, $u, 'index', ['title' => $c['title'], 'banner' => $banner]);
}

function page_lesson(array $c, string $slug): void {
    $u = current_user();
    $l = lesson_by_slug((int)$c['id'], $slug);
    if (!$l) not_found();
    $body = lesson_open($c, $l, $u) ? null : gate_body($c, $u);
    render_course_page($c, $u, 'lesson', ['lesson' => $l, 'title' => $l['title'] . ' — ' . ($c['brand'] ?: $c['title']), 'body' => $body]);
}

function page_certificate(array $c): void {
    $u = current_user();
    if (!can_access($c, $u)) redirect(course_url($c));
    render_course_page($c, $u, 'certificate', ['title' => 'Сертификат — ' . ($c['brand'] ?: $c['title'])]);
}

function page_unlock(array $c): void {
    if (!is_post()) redirect(course_url($c));
    csrf_check();
    $u = require_login();
    $code = trim((string)post('code'));
    if ($c['access'] !== 'closed') redirect(course_url($c));
    if ($c['access_code'] !== null && $c['access_code'] !== '' && hash_equals((string)$c['access_code'], $code)) {
        enroll((int)$u['id'], (int)$c['id'], 'code');
        flash('Курс открыт. Приятной учёбы!');
        redirect(course_url($c));
    }
    login_failed('code:' . $c['slug']);
    usleep(400000);
    flash('Код не подошёл. Проверьте его и попробуйте ещё раз.', 'error');
    redirect(course_url($c));
}

function page_privacy(): void {
    $text = (string)setting('privacy_html', '');
    if (trim($text) === '') $text = '<p>Политика обработки персональных данных ещё не заполнена. Администратор может добавить её в разделе «Настройки».</p>';
    render_site_page('Политика обработки данных', '<article class="card prose"><h1 class="mt0">Политика обработки персональных данных</h1>' . $text . '</article>', ['narrow' => true]);
}

/* Сохранение прогресса вошедшего читателя */
function api_progress(): void {
    $u = current_user();
    if (!$u) json_out(['ok' => false, 'error' => 'auth'], 401);
    $c = course_by_slug((string)get('course'));
    if (!$c) json_out(['ok' => false, 'error' => 'course'], 404);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $p = row('SELECT data, ts FROM ' . t('progress') . ' WHERE user_id = ? AND course_id = ?', [$u['id'], $c['id']]);
        json_out(['ok' => true, 'ts' => $p ? (int)$p['ts'] : 0, 'data' => $p ? json_decode($p['data'], true) : new stdClass()]);
    }
    if (!csrf_ok((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? get('csrf')))) json_out(['ok' => false, 'error' => 'csrf'], 419);
    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in) || !isset($in['data']) || !is_array($in['data'])) json_out(['ok' => false, 'error' => 'format'], 400);
    $clean = [];
    foreach ($in['data'] as $k => $v) {
        if (!is_string($k) || strlen($k) > 120 || !is_string($v) || strlen($v) > 64000) continue;
        $clean[$k] = $v;
    }
    try { save_progress((int)$u['id'], (int)$c['id'], $clean, (int)($in['ts'] ?? 0)); }
    catch (RuntimeException $e) { json_out(['ok' => false, 'error' => 'size'], 413); }
    json_out(['ok' => true]);
}
