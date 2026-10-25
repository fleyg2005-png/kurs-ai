<?php
/* Админка: курсы, структура, уроки, пользователи, настройки, ИИ-помощник. */
defined('APP') || exit;

require_once APP_DIR . '/importer.php';
require_once APP_DIR . '/ai.php';

function admin_dispatch(array $p): void {
    $me = require_admin();
    $a = $p[0] ?? '';
    $id = isset($p[1]) && ctype_digit($p[1]) ? (int)$p[1] : 0;
    $sub = $p[2] ?? '';
    if ($a === '') { adm_dashboard(); return; }
    if ($a === 'courses' && !$id) { adm_courses(); return; }
    if ($a === 'courses' && $id && $sub === '') { adm_course($id); return; }
    if ($a === 'courses' && $id && $sub === 'structure') { adm_structure($id); return; }
    if ($a === 'courses' && $id && $sub === 'export') { adm_export($id); return; }
    if ($a === 'lessons' && $id && $sub === '') { adm_lesson($id); return; }
    if ($a === 'lessons' && $id && $sub === 'preview') { adm_preview($id); return; }
    if ($a === 'users' && !$id) { adm_users(); return; }
    if ($a === 'users' && $id) { adm_user($id, $me); return; }
    if ($a === 'settings') { adm_settings(); return; }
    if ($a === 'ai') { adm_ai(); return; }
    not_found();
}

/* ---------- Каркас страницы ---------- */
function adm_page(string $title, string $body, string $active = '', array $crumbs = []): void {
    $nav = [['', '📊', 'Обзор'], ['courses', '📚', 'Курсы'], ['users', '👥', 'Пользователи'], ['settings', '⚙️', 'Настройки']];
    $n = '';
    foreach ($nav as [$path, $ico, $label]) $n .= '<a class="' . ($active === $path ? 'active' : '') . '" href="' . e(url('admin/' . $path)) . '"><span>' . $ico . '</span>' . e($label) . '</a>';
    $cr = '';
    if ($crumbs) { $parts = []; foreach ($crumbs as [$label, $href]) $parts[] = $href ? '<a href="' . e($href) . '">' . e($label) . '</a>' : '<span>' . e($label) . '</span>'; $cr = '<div class="adm-crumbs">' . implode(' / ', $parts) . '</div>'; }
    echo head_html($title . ' — админка', '', ['admin.css']);
    echo '<body class="admin"><aside class="adm-nav"><a class="adm-brand" href="' . e(url('admin')) . '"><span class="logo">' . e(mb_strtoupper(mb_substr((string)setting('site_name', 'К'), 0, 1))) . '</span>Админка</a>'
        . '<nav>' . $n . '</nav><div class="adm-nav-foot"><a href="' . e(url()) . '" target="_blank" rel="noopener">↗ Открыть сайт</a><a href="' . e(url('account')) . '">👤 ' . e(current_user()['name']) . '</a>'
        . '<button class="icon-btn theme-btn" type="button" aria-label="Тема">🌙</button></div></aside>'
        . '<main class="adm-main">' . $cr . flash_html() . $body . '</main>'
        . '<script src="' . e(asset('site.js')) . '"></script><script src="' . e(asset('admin.js')) . '"></script></body></html>';
}

function adm_field(string $name, string $label, $value = '', string $type = 'text', string $attr = '', string $hint = ''): string {
    $h = $hint !== '' ? '<small>' . $hint . '</small>' : '';
    if ($type === 'textarea') return '<label class="field"><span>' . e($label) . '</span><textarea name="' . e($name) . '" ' . $attr . '>' . e((string)$value) . '</textarea>' . $h . '</label>';
    return '<label class="field"><span>' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e((string)$value) . '" ' . $attr . '>' . $h . '</label>';
}
function adm_select(string $name, string $label, array $options, $value, string $hint = ''): string {
    $o = '';
    foreach ($options as $k => $v) $o .= '<option value="' . e((string)$k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . e($v) . '</option>';
    return '<label class="field"><span>' . e($label) . '</span><select name="' . e($name) . '">' . $o . '</select>' . ($hint ? '<small>' . $hint . '</small>' : '') . '</label>';
}
function adm_check(string $name, string $label, bool $on, string $hint = ''): string {
    return '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '> <span>' . e($label) . ($hint ? '<br><small class="muted">' . $hint . '</small>' : '') . '</span></label>';
}
function adm_post_btn(string $action, string $label, array $fields = [], string $cls = 'btn small', string $confirm = ''): string {
    $h = '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
    return $h . '<button class="' . e($cls) . '" type="submit"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . $label . '</button></form>';
}

/* ---------- Обзор ---------- */
function adm_dashboard(): void {
    $stats = [
        ['Курсов', (int)val('SELECT COUNT(*) FROM ' . t('courses'))],
        ['Опубликовано', (int)val('SELECT COUNT(*) FROM ' . t('courses') . ' WHERE is_published = 1')],
        ['Уроков', (int)val('SELECT COUNT(*) FROM ' . t('lessons'))],
        ['Учеников', (int)val('SELECT COUNT(*) FROM ' . t('users') . " WHERE role = 'student'")],
        ['Новых за 7 дней', (int)val('SELECT COUNT(*) FROM ' . t('users') . ' WHERE created_at >= ?', [date('Y-m-d H:i:s', time() - 7 * 86400)])],
        ['Активных за 7 дней', (int)val('SELECT COUNT(DISTINCT user_id) FROM ' . t('progress') . ' WHERE updated_at >= ?', [date('Y-m-d H:i:s', time() - 7 * 86400)])],
    ];
    $h = '<div class="adm-head"><h1>Обзор</h1><a class="btn primary" href="' . e(url('admin/courses')) . '#new">+ Новый курс</a></div><div class="stat-grid">';
    foreach ($stats as [$l, $n]) $h .= '<div class="stat"><b>' . $n . '</b><span>' . e($l) . '</span></div>';
    $h .= '</div>';
    $top = rows('SELECT c.id, c.title, c.slug, COUNT(p.user_id) n FROM ' . t('courses') . ' c LEFT JOIN ' . t('progress') . ' p ON p.course_id = c.id GROUP BY c.id ORDER BY n DESC, c.position LIMIT 8');
    $h .= '<div class="grid2"><section class="panel"><h2>Курсы по числу учеников</h2><table><thead><tr><th>Курс</th><th>Учеников с прогрессом</th></tr></thead><tbody>';
    foreach ($top as $r) $h .= '<tr><td><a href="' . e(url('admin/courses/' . $r['id'])) . '">' . e($r['title']) . '</a></td><td>' . (int)$r['n'] . '</td></tr>';
    $h .= '</tbody></table></section>';
    $new = rows('SELECT id, name, email, created_at FROM ' . t('users') . ' ORDER BY id DESC LIMIT 8');
    $h .= '<section class="panel"><h2>Новые пользователи</h2><table><thead><tr><th>Имя</th><th>Почта</th><th>Когда</th></tr></thead><tbody>';
    foreach ($new as $r) $h .= '<tr><td><a href="' . e(url('admin/users/' . $r['id'])) . '">' . e($r['name']) . '</a></td><td>' . e($r['email']) . '</td><td>' . e(date('d.m H:i', strtotime($r['created_at']))) . '</td></tr>';
    $h .= '</tbody></table></section></div>';
    adm_page('Обзор', $h, '');
}

/* ---------- Курсы ---------- */
function adm_courses(): void {
    if (is_post()) {
        csrf_check();
        $act = post('action');
        try {
            if ($act === 'create') {
                $title = trim((string)post('title'));
                $slug = trim((string)post('slug')) ?: slugify($title);
                if ($title === '') throw new RuntimeException('Укажите название курса.');
                if (!slug_free($slug)) throw new RuntimeException('Адрес «' . $slug . '» занят или недопустим: латиница, цифры и дефис.');
                q('INSERT INTO ' . t('courses') . ' (slug, title, brand, description, access, price_label, is_published, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
                  [$slug, $title, mb_substr($title, 0, 80), '', 'open', 'Бесплатно', (int)val('SELECT COALESCE(MAX(position),0)+1 FROM ' . t('courses')), now(), now()]);
                $id = last_id();
                q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, NULL, ?, 0)', [$id, 'Модуль 1']);
                flash('Курс создан. Заполните описание и добавьте уроки.');
                redirect(url('admin/courses/' . $id . '/structure'));
            }
            if ($act === 'import_dir') {
                $id = import_course_dir((string)post('dir'), trim((string)post('slug')) ?: null, false);
                flash('Курс загружен из папки. Он пока черновик — проверьте и опубликуйте.');
                redirect(url('admin/courses/' . $id));
            }
            if ($act === 'import_file') {
                if (empty($_FILES['pack']['tmp_name']) || $_FILES['pack']['size'] > 20 * 1024 * 1024) throw new RuntimeException('Выберите файл экспорта до 20 МБ.');
                $pack = json_decode((string)file_get_contents($_FILES['pack']['tmp_name']), true);
                if (!is_array($pack)) throw new RuntimeException('Файл не похож на экспорт курса.');
                $slug = trim((string)post('slug')) ?: (string)($pack['course']['slug'] ?? 'course');
                while (!slug_free($slug) && valid_slug($slug)) $slug .= '-2';
                $id = import_course_json($pack, $slug);
                flash('Курс загружен из файла как черновик.');
                redirect(url('admin/courses/' . $id));
            }
            if ($act === 'move') {
                adm_move('courses', (int)post('id'), (string)post('dir'), '1=1', []);
                redirect(url('admin/courses'));
            }
        } catch (Throwable $e) { flash($e->getMessage(), 'error'); redirect(url('admin/courses')); }
    }
    $f = ['q' => trim((string)get('q'))];
    [$items, $total] = catalog($f, 1, 500, true);
    $h = '<div class="adm-head"><h1>Курсы <small>' . $total . '</small></h1><form class="adm-search" method="get"><input type="search" name="q" value="' . e($f['q']) . '" placeholder="Поиск по курсам"></form></div>';
    $h .= '<section class="panel"><table class="adm-table"><thead><tr><th></th><th>Курс</th><th>Адрес</th><th>Доступ</th><th>Уроков</th><th>Статус</th><th></th></tr></thead><tbody>';
    foreach ($items as $c) {
        $h .= '<tr><td class="nowrap">' . adm_post_btn('move', '↑', ['id' => $c['id'], 'dir' => 'up'], 'btn tiny ghost') . adm_post_btn('move', '↓', ['id' => $c['id'], 'dir' => 'down'], 'btn tiny ghost') . '</td>'
            . '<td><span class="cc-mini">' . e($c['emoji']) . '</span> <a href="' . e(url('admin/courses/' . $c['id'])) . '"><b>' . e($c['title']) . '</b></a>' . ($c['category'] ? '<br><small class="muted">' . e($c['category']) . '</small>' : '') . '</td>'
            . '<td><code>/' . e($c['slug']) . '/</code></td><td>' . e(ACCESS_LABELS[$c['access']]) . '<br><small class="muted">' . e($c['price_label']) . '</small></td><td>' . (int)$c['lessons_count'] . '</td>'
            . '<td>' . ($c['is_published'] ? '<span class="badge ok">Опубликован</span>' : '<span class="badge">Черновик</span>') . '</td>'
            . '<td class="nowrap right"><a class="btn small" href="' . e(url('admin/courses/' . $c['id'] . '/structure')) . '">Уроки</a> <a class="btn small ghost" href="' . e(course_url($c)) . '" target="_blank" rel="noopener">↗</a></td></tr>';
    }
    if (!$items) $h .= '<tr><td colspan="7" class="muted">Курсов пока нет.</td></tr>';
    $h .= '</tbody></table></section>';

    $h .= '<div class="grid2" id="new"><section class="panel"><h2>Новый курс</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="create">'
        . adm_field('title', 'Название', '', 'text', 'required maxlength="200" data-slug-source="slug"')
        . adm_field('slug', 'Адрес', '', 'text', 'pattern="[a-z0-9][a-z0-9\-]*" maxlength="64"', 'Латиница, цифры, дефис. Курс будет по адресу <code>' . e(url('')) . 'адрес/</code>')
        . '<button class="btn primary" type="submit">Создать курс</button></form></section>';
    $dirs = array_filter(content_dirs(), function ($d) { $m = json_decode((string)@file_get_contents(ROOT_DIR . '/content/' . $d . '/course.json'), true); return !course_by_slug((string)($m['slug'] ?? $d)); });
    $h .= '<section class="panel"><h2>Импорт</h2><form method="post" enctype="multipart/form-data" class="form">' . csrf_field() . '<input type="hidden" name="action" value="import_file">'
        . '<label class="field"><span>Файл экспорта курса (.json)</span><input type="file" name="pack" accept=".json,application/json" required></label>'
        . adm_field('slug', 'Адрес нового курса', '', 'text', 'pattern="[a-z0-9][a-z0-9\-]*"', 'Пусто — возьмётся из файла') . '<button class="btn" type="submit">Загрузить</button></form>';
    if ($dirs) {
        $opts = []; foreach ($dirs as $d) $opts[$d] = 'content/' . $d;
        $h .= '<form method="post" class="form" style="margin-top:18px">' . csrf_field() . '<input type="hidden" name="action" value="import_dir">' . adm_select('dir', 'Или из папки content на сервере', $opts, '') . '<button class="btn" type="submit">Загрузить из папки</button></form>';
    }
    $h .= '</section></div>';
    adm_page('Курсы', $h, 'courses');
}

/* Сдвиг элемента вверх/вниз внутри набора (by position) */
function adm_move(string $table, int $id, string $dir, string $scopeSql, array $scopeParams): void {
    $items = rows('SELECT id FROM ' . t($table) . " WHERE $scopeSql ORDER BY position, id", $scopeParams);
    $ids = array_map('intval', array_column($items, 'id'));
    $i = array_search($id, $ids, true);
    if ($i === false) return;
    $j = $dir === 'up' ? $i - 1 : $i + 1;
    if ($j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $pos => $rid) q('UPDATE ' . t($table) . ' SET position = ? WHERE id = ?', [$pos, $rid]);
}

function adm_course(int $id): void {
    $c = course_by_id($id) ?? not_found();
    if (is_post()) {
        csrf_check();
        $act = post('action');
        if ($act === 'delete') {
            if (post('confirm') !== $c['slug']) { flash('Для удаления введите адрес курса точно.', 'error'); redirect(url('admin/courses/' . $id)); }
            q('DELETE FROM ' . t('courses') . ' WHERE id = ?', [$id]);
            flash('Курс удалён.'); redirect(url('admin/courses'));
        }
        if ($act === 'save') {
            $slug = trim((string)post('slug'));
            if (!slug_free($slug, $id)) { flash('Адрес «' . $slug . '» занят или недопустим.', 'error'); redirect(url('admin/courses/' . $id)); }
            $access = in_array(post('access'), ['open', 'login', 'closed'], true) ? post('access') : 'open';
            q('UPDATE ' . t('courses') . ' SET slug = ?, title = ?, brand = ?, brand_sub = ?, logo = ?, emoji = ?, category = ?, description = ?, access = ?, access_code = ?, price_label = ?, cert_title = ?, cert_sub = ?, common_skills = ?, home_html = ?, module_base = ?, is_published = ?, updated_at = ? WHERE id = ?', [
                $slug, trim((string)post('title')), trim((string)post('brand')), trim((string)post('brand_sub')), mb_substr(trim((string)post('logo')), 0, 8), mb_substr(trim((string)post('emoji')), 0, 16),
                trim((string)post('category')), trim((string)post('description')), $access, trim((string)post('access_code')) ?: null, trim((string)post('price_label')),
                trim((string)post('cert_title')), trim((string)post('cert_sub')), trim((string)post('common_skills')), (string)post('home_html'), post('module_base') === '0' ? 0 : 1, post('is_published') === '1' ? 1 : 0, now(), $id]);
            flash('Сохранено.'); redirect(url('admin/courses/' . $id));
        }
    }
    $h = '<div class="adm-head"><h1>' . e($c['title']) . '</h1><div class="row"><a class="btn" href="' . e(url('admin/courses/' . $id . '/structure')) . '">Уроки и модули</a><a class="btn ghost" href="' . e(course_url($c)) . '" target="_blank" rel="noopener">Открыть ↗</a><a class="btn ghost" href="' . e(url('admin/courses/' . $id . '/export')) . '">Экспорт</a></div></div>';
    $h .= '<form method="post" class="form adm-form">' . csrf_field() . '<input type="hidden" name="action" value="save">'
        . '<section class="panel"><h2>Основное</h2><div class="form-grid">' . adm_field('title', 'Название курса', $c['title'], 'text', 'required maxlength="200"') . adm_field('slug', 'Адрес', $c['slug'], 'text', 'required pattern="[a-z0-9][a-z0-9\-]*" maxlength="64"', 'Сейчас: <code>' . e(course_url($c)) . '</code>') . '</div>'
        . adm_field('description', 'Описание для каталога и главной курса', $c['description'], 'textarea', 'rows="3"')
        . '<div class="form-grid">' . adm_field('category', 'Категория', $c['category'], 'text', 'maxlength="80" list="cats"') . adm_field('emoji', 'Значок', $c['emoji'], 'text', 'maxlength="16"') . '</div>'
        . '<datalist id="cats">' . implode('', array_map(function ($x) { return '<option value="' . e($x) . '">'; }, array_keys(categories()))) . '</datalist>'
        . adm_check('is_published', 'Опубликован — виден в каталоге', (bool)$c['is_published']) . '</section>'
        . '<section class="panel"><h2>Доступ</h2><div class="form-grid">' . adm_select('access', 'Кто может проходить', ['open' => 'Открытый: все, без регистрации', 'login' => 'По регистрации: бесплатно после входа', 'closed' => 'Закрытый: по коду доступа или выдаче'], $c['access'])
        . adm_field('price_label', 'Подпись цены', $c['price_label'], 'text', 'maxlength="64"', 'Например: Бесплатно, 2 990 ₽, По подписке') . '</div>'
        . adm_field('access_code', 'Код доступа для закрытого курса', (string)$c['access_code'], 'text', 'maxlength="64" autocomplete="off"', 'Ученик вводит код на странице курса. Выдать доступ вручную можно в карточке пользователя.')
        . '<p class="small muted">Отдельные уроки можно сделать бесплатным предпросмотром — галочка в редакторе урока.</p></section>'
        . '<section class="panel"><h2>Оформление</h2><div class="form-grid">' . adm_field('brand', 'Короткое название (в меню урока)', $c['brand'], 'text', 'maxlength="80"') . adm_field('brand_sub', 'Подзаголовок в меню', $c['brand_sub'], 'text', 'maxlength="120"') . adm_field('logo', 'Буквы на значке', $c['logo'], 'text', 'maxlength="8"', 'Пусто — первые буквы названия') . '</div>'
        . '<div class="form-grid">' . adm_field('cert_title', 'Заголовок сертификата', $c['cert_title'], 'text', 'maxlength="200"') . adm_field('cert_sub', 'Подпись сертификата', $c['cert_sub'], 'text', 'maxlength="255"') . '</div>'
        . adm_field('common_skills', 'Навыки на сертификате (по одному в строке)', (string)$c['common_skills'], 'textarea', 'rows="3"')
        . adm_select('module_base', 'Нумерация модулей', ['1' => 'С первого: Модуль 1, 2, 3…', '0' => 'С нулевого: вводный модуль — Модуль 0'], (string)$c['module_base'])
        . adm_field('home_html', 'Своя главная страница курса (HTML)', (string)$c['home_html'], 'textarea', 'rows="10" class="code" spellcheck="false"', 'Пусто — главная соберётся сама: описание, выбор пути, содержание. Блоки <code>id="track-chooser"</code>, <code>id="course-map"</code>, <code>id="continue-btn"</code> заполняются автоматически.')
        . '</section><div class="sticky-save"><button class="btn primary" type="submit">Сохранить</button></div></form>';
    $h .= '<details class="panel danger-zone"><summary>Удалить курс</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="delete"><p>Удалятся все уроки, модули, пути и прогресс учеников этого курса.</p>' . adm_field('confirm', 'Введите адрес курса: ' . $c['slug'], '', 'text', 'required') . '<button class="btn danger" type="submit">Удалить курс навсегда</button></form></details>';
    adm_page($c['title'], $h, 'courses', [['Курсы', url('admin/courses')], [$c['title'], null]]);
}

function adm_export(int $id): void {
    $c = course_by_id($id) ?? not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="course-' . $c['slug'] . '-' . date('Ymd') . '.json"');
    echo json_encode(export_course($id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/* ---------- Структура: пути, модули, уроки ---------- */
function adm_structure(int $id): void {
    $c = course_by_id($id) ?? not_found();
    $back = url('admin/courses/' . $id . '/structure');
    if (is_post()) {
        csrf_check();
        $act = (string)post('action');
        try {
            switch ($act) {
                case 'track_add':
                    $slug = trim((string)post('slug')) ?: slugify((string)post('name'));
                    if (!valid_slug($slug) || val('SELECT id FROM ' . t('tracks') . ' WHERE course_id = ? AND slug = ?', [$id, $slug])) throw new RuntimeException('Код пути занят или недопустим.');
                    q('INSERT INTO ' . t('tracks') . ' (course_id, slug, name, emoji, tagline, description, skills, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                      [$id, $slug, trim((string)post('name')), trim((string)post('emoji')), trim((string)post('tagline')), trim((string)post('description')), trim((string)post('skills')), (int)val('SELECT COALESCE(MAX(position),0)+1 FROM ' . t('tracks') . ' WHERE course_id = ?', [$id])]);
                    $tid = last_id();
                    q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, ?, ?, 0)', [$id, $tid, 'Модуль пути «' . trim((string)post('name')) . '»']);
                    flash('Путь добавлен.'); break;
                case 'track_save':
                    q('UPDATE ' . t('tracks') . ' SET name = ?, emoji = ?, tagline = ?, description = ?, skills = ? WHERE id = ? AND course_id = ?',
                      [trim((string)post('name')), trim((string)post('emoji')), trim((string)post('tagline')), trim((string)post('description')), trim((string)post('skills')), (int)post('id'), $id]);
                    flash('Путь сохранён.'); break;
                case 'track_delete':
                    q('DELETE FROM ' . t('tracks') . ' WHERE id = ? AND course_id = ?', [(int)post('id'), $id]); flash('Путь удалён вместе с его модулями. Уроки остались в курсе.'); break;
                case 'track_move':
                    adm_move('tracks', (int)post('id'), (string)post('dir'), 'course_id = ?', [$id]); break;
                case 'module_add':
                    $tid = post('track_id') === '' ? null : (int)post('track_id');
                    q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, ?, ?, ?)', [$id, $tid, trim((string)post('title')) ?: 'Новый модуль',
                        (int)val('SELECT COALESCE(MAX(position),0)+1 FROM ' . t('modules') . ' WHERE course_id = ? AND ' . ($tid === null ? 'track_id IS NULL' : 'track_id = ?'), $tid === null ? [$id] : [$id, $tid])]);
                    break;
                case 'module_save':
                    q('UPDATE ' . t('modules') . ' SET title = ? WHERE id = ? AND course_id = ?', [trim((string)post('title')), (int)post('id'), $id]); break;
                case 'module_delete':
                    q('DELETE FROM ' . t('modules') . ' WHERE id = ? AND course_id = ?', [(int)post('id'), $id]); flash('Модуль удалён. Уроки остались в курсе.'); break;
                case 'module_move':
                    $m = row('SELECT * FROM ' . t('modules') . ' WHERE id = ? AND course_id = ?', [(int)post('id'), $id]);
                    if ($m) adm_move('modules', (int)$m['id'], (string)post('dir'), 'course_id = ? AND ' . ($m['track_id'] === null ? 'track_id IS NULL' : 'track_id = ?'), $m['track_id'] === null ? [$id] : [$id, $m['track_id']]);
                    break;
                case 'lesson_add':
                    $mid = (int)post('module_id');
                    if (!val('SELECT id FROM ' . t('modules') . ' WHERE id = ? AND course_id = ?', [$mid, $id])) throw new RuntimeException('Модуль не найден.');
                    $title = trim((string)post('title'));
                    $slug = trim((string)post('slug'));
                    $existing = $slug !== '' ? lesson_by_slug($id, $slug) : null;
                    if ($existing) { $lid = (int)$existing['id']; }
                    else {
                        if ($title === '') throw new RuntimeException('Укажите название урока или адрес существующего.');
                        $slug = $slug ?: slugify($title);
                        $base = $slug; $n = 2;
                        while (lesson_by_slug($id, $slug)) $slug = $base . '-' . $n++;
                        if (!preg_match('~^[a-z0-9][a-z0-9-]{0,95}$~', $slug)) throw new RuntimeException('Адрес урока: латиница, цифры, дефис.');
                        q('INSERT INTO ' . t('lessons') . ' (course_id, slug, title, minutes, is_control, is_preview, body, updated_at) VALUES (?, ?, ?, ?, 0, 0, ?, ?)',
                          [$id, $slug, $title, max(1, (int)post('minutes', 5)), "<p class=\"lead\">Коротко: о чём урок и что будет на выходе.</p>\n\n<h2>Раздел</h2>\n<p>Текст урока.</p>\n", now()]);
                        $lid = last_id();
                    }
                    q('INSERT IGNORE INTO ' . t('module_lessons') . ' (module_id, lesson_id, position) VALUES (?, ?, ?)', [$mid, $lid, (int)val('SELECT COALESCE(MAX(position),0)+1 FROM ' . t('module_lessons') . ' WHERE module_id = ?', [$mid])]);
                    if (!$existing && post('open') === '1') redirect(url('admin/lessons/' . $lid));
                    break;
                case 'lesson_detach':
                    q('DELETE ml FROM ' . t('module_lessons') . ' ml JOIN ' . t('modules') . ' m ON m.id = ml.module_id WHERE ml.module_id = ? AND ml.lesson_id = ? AND m.course_id = ?', [(int)post('module_id'), (int)post('lesson_id'), $id]); break;
                case 'lesson_move':
                    $mid = (int)post('module_id');
                    $ids = array_map('intval', array_column(rows('SELECT lesson_id FROM ' . t('module_lessons') . ' WHERE module_id = ? ORDER BY position, lesson_id', [$mid]), 'lesson_id'));
                    $i = array_search((int)post('lesson_id'), $ids, true);
                    if ($i !== false) {
                        $j = post('dir') === 'up' ? $i - 1 : $i + 1;
                        if ($j >= 0 && $j < count($ids)) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; foreach ($ids as $pos => $lid) q('UPDATE ' . t('module_lessons') . ' SET position = ? WHERE module_id = ? AND lesson_id = ?', [$pos, $mid, $lid]); }
                    }
                    break;
            }
        } catch (Throwable $e) { flash($e->getMessage(), 'error'); }
        redirect($back . (post('anchor') ? '#' . preg_replace('~[^a-z0-9-]~', '', (string)post('anchor')) : ''));
    }

    $tracks = tracks_of($id);
    $modules = modules_of($id);
    $orphans = rows('SELECT l.* FROM ' . t('lessons') . ' l WHERE l.course_id = ? AND NOT EXISTS (SELECT 1 FROM ' . t('module_lessons') . ' ml WHERE ml.lesson_id = l.id) ORDER BY l.slug', [$id]);
    $h = '<div class="adm-head"><h1>' . e($c['title']) . ' <small>уроки и модули</small></h1><div class="row"><a class="btn" href="' . e(url('admin/courses/' . $id)) . '">Настройки курса</a><a class="btn ghost" href="' . e(course_url($c)) . '" target="_blank" rel="noopener">Открыть ↗</a></div></div>';
    $h .= '<p class="muted">Общие модули проходят все. Пути — необязательная развилка: если путей нет, курс идёт одной линией. Один урок можно поставить в несколько модулей — например, общий для всех путей.</p>';

    $renderModule = function (array $m) use ($c) {
        $ls = module_lessons((int)$m['id']);
        $anchor = 'mod-' . $m['id'];
        $out = '<div class="mod" id="' . $anchor . '"><div class="mod-head"><form method="post" class="mod-title-form">' . csrf_field() . '<input type="hidden" name="action" value="module_save"><input type="hidden" name="id" value="' . $m['id'] . '"><input type="hidden" name="anchor" value="' . $anchor . '">'
            . '<input class="mod-title-input" name="title" value="' . e($m['title']) . '" aria-label="Название модуля"><button class="btn tiny" type="submit">Сохранить</button></form>'
            . '<div class="row">' . adm_post_btn('module_move', '↑', ['id' => $m['id'], 'dir' => 'up', 'anchor' => $anchor], 'btn tiny ghost') . adm_post_btn('module_move', '↓', ['id' => $m['id'], 'dir' => 'down', 'anchor' => $anchor], 'btn tiny ghost')
            . adm_post_btn('module_delete', 'Удалить модуль', ['id' => $m['id']], 'btn tiny ghost danger-text', 'Удалить модуль? Уроки останутся в курсе.') . '</div></div><ol class="mod-lessons">';
        foreach ($ls as $l) {
            $out .= '<li><span class="ml-title"><a href="' . e(url('admin/lessons/' . $l['id'])) . '">' . ($l['is_control'] ? '📝 ' : '') . e($l['title']) . '</a> <code>' . e($l['slug']) . '</code>' . ($l['is_preview'] ? ' <span class="badge ok">предпросмотр</span>' : '') . ' <small class="muted">' . (int)$l['minutes'] . ' мин</small></span>'
                . '<span class="row">' . adm_post_btn('lesson_move', '↑', ['module_id' => $m['id'], 'lesson_id' => $l['id'], 'dir' => 'up', 'anchor' => $anchor], 'btn tiny ghost') . adm_post_btn('lesson_move', '↓', ['module_id' => $m['id'], 'lesson_id' => $l['id'], 'dir' => 'down', 'anchor' => $anchor], 'btn tiny ghost')
                . '<a class="btn tiny ghost" href="' . e(lesson_url($c, $l['slug'])) . '" target="_blank" rel="noopener">↗</a>' . adm_post_btn('lesson_detach', '✕', ['module_id' => $m['id'], 'lesson_id' => $l['id'], 'anchor' => $anchor], 'btn tiny ghost', 'Убрать урок из модуля? Сам урок останется в курсе.') . '</span></li>';
        }
        if (!$ls) $out .= '<li class="muted">В модуле пока нет уроков.</li>';
        $out .= '</ol><form method="post" class="add-lesson">' . csrf_field() . '<input type="hidden" name="action" value="lesson_add"><input type="hidden" name="module_id" value="' . $m['id'] . '"><input type="hidden" name="anchor" value="' . $anchor . '"><input type="hidden" name="open" value="1">'
            . '<input name="title" placeholder="Новый урок: название" maxlength="255"><input name="slug" placeholder="или адрес существующего" maxlength="96" list="lesson-slugs"><button class="btn small primary" type="submit">+ Урок</button></form></div>';
        return $out;
    };

    $h .= '<datalist id="lesson-slugs">' . implode('', array_map(function ($l) { return '<option value="' . e($l['slug']) . '">' . e($l['title']) . '</option>'; }, rows('SELECT slug, title FROM ' . t('lessons') . ' WHERE course_id = ? ORDER BY slug', [$id]))) . '</datalist>';
    $h .= '<section class="panel"><h2>Общие модули</h2>';
    foreach ($modules as $m) if ($m['track_id'] === null) $h .= $renderModule($m);
    $h .= '<form method="post" class="row add-module">' . csrf_field() . '<input type="hidden" name="action" value="module_add"><input type="hidden" name="track_id" value=""><input name="title" placeholder="Название нового общего модуля" required><button class="btn small" type="submit">+ Модуль</button></form></section>';

    foreach ($tracks as $tr) {
        $anchor = 'track-' . $tr['id'];
        $h .= '<section class="panel track-panel" id="' . $anchor . '"><div class="mod-head"><h2>' . e($tr['emoji']) . ' Путь «' . e($tr['name']) . '» <code>' . e($tr['slug']) . '</code></h2><div class="row">'
            . adm_post_btn('track_move', '↑', ['id' => $tr['id'], 'dir' => 'up', 'anchor' => $anchor], 'btn tiny ghost') . adm_post_btn('track_move', '↓', ['id' => $tr['id'], 'dir' => 'down', 'anchor' => $anchor], 'btn tiny ghost')
            . adm_post_btn('track_delete', 'Удалить путь', ['id' => $tr['id']], 'btn tiny ghost danger-text', 'Удалить путь и его модули? Уроки останутся в курсе.') . '</div></div>'
            . '<details><summary>Описание пути</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="track_save"><input type="hidden" name="id" value="' . $tr['id'] . '"><input type="hidden" name="anchor" value="' . $anchor . '">'
            . '<div class="form-grid">' . adm_field('name', 'Название', $tr['name'], 'text', 'required maxlength="80"') . adm_field('emoji', 'Значок', $tr['emoji'], 'text', 'maxlength="16"') . '</div>'
            . adm_field('tagline', 'Одной строкой', $tr['tagline'], 'text', 'maxlength="200"') . adm_field('description', 'Описание на карточке выбора', (string)$tr['description'], 'textarea', 'rows="3"')
            . adm_field('skills', 'Навыки на сертификате (по одному в строке)', (string)$tr['skills'], 'textarea', 'rows="3"') . '<button class="btn small" type="submit">Сохранить путь</button></form></details>';
        foreach ($modules as $m) if ((int)$m['track_id'] === (int)$tr['id']) $h .= $renderModule($m);
        $h .= '<form method="post" class="row add-module">' . csrf_field() . '<input type="hidden" name="action" value="module_add"><input type="hidden" name="track_id" value="' . $tr['id'] . '"><input type="hidden" name="anchor" value="' . $anchor . '"><input name="title" placeholder="Новый модуль пути" required><button class="btn small" type="submit">+ Модуль</button></form></section>';
    }

    $h .= '<details class="panel" id="new-track"' . ($tracks ? '' : ' open') . '><summary>+ Новый путь — развилка курса для разных профессий</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="track_add">'
        . '<div class="form-grid">' . adm_field('name', 'Название пути', '', 'text', 'required maxlength="80" data-slug-source="slug"') . adm_field('slug', 'Код', '', 'text', 'pattern="[a-z0-9][a-z0-9\-]*" maxlength="64"') . adm_field('emoji', 'Значок', '', 'text', 'maxlength="16"') . '</div>'
        . adm_field('tagline', 'Одной строкой', '', 'text', 'maxlength="200"') . adm_field('description', 'Описание', '', 'textarea', 'rows="2"') . adm_field('skills', 'Навыки на сертификате', '', 'textarea', 'rows="2"')
        . '<div><button class="btn" type="submit">+ Добавить путь</button></div></form></details>';
    if ($orphans) {
        $h .= '<section class="panel"><h2>Уроки вне модулей</h2><p class="muted">Эти уроки есть в курсе, но ни в одном модуле. Добавьте их по адресу в нужный модуль.</p><ul>';
        foreach ($orphans as $l) $h .= '<li><a href="' . e(url('admin/lessons/' . $l['id'])) . '">' . e($l['title']) . '</a> <code>' . e($l['slug']) . '</code></li>';
        $h .= '</ul></section>';
    }
    adm_page('Уроки — ' . $c['title'], $h, 'courses', [['Курсы', url('admin/courses')], [$c['title'], url('admin/courses/' . $id)], ['Уроки и модули', null]]);
}

/* ---------- Редактор урока ---------- */
function adm_lesson(int $id): void {
    $l = row('SELECT * FROM ' . t('lessons') . ' WHERE id = ?', [$id]) ?? not_found();
    $c = course_by_id((int)$l['course_id']);
    if (is_post()) {
        csrf_check();
        if (post('action') === 'delete') {
            q('DELETE FROM ' . t('lessons') . ' WHERE id = ?', [$id]);
            flash('Урок удалён.'); redirect(url('admin/courses/' . $c['id'] . '/structure'));
        }
        $slug = trim((string)post('slug'));
        $dup = val('SELECT id FROM ' . t('lessons') . ' WHERE course_id = ? AND slug = ? AND id <> ?', [$c['id'], $slug, $id]);
        if (!preg_match('~^[a-z0-9][a-z0-9-]{0,95}$~', $slug) || $dup) { flash('Адрес урока занят или недопустим.', 'error'); redirect(url('admin/lessons/' . $id)); }
        q('UPDATE ' . t('lessons') . ' SET slug = ?, title = ?, minutes = ?, is_control = ?, is_preview = ?, body = ?, updated_at = ? WHERE id = ?',
          [$slug, trim((string)post('title')), max(1, min(600, (int)post('minutes'))), post('is_control') === '1' ? 1 : 0, post('is_preview') === '1' ? 1 : 0, (string)post('body'), now(), $id]);
        if (post('ajax') === '1') json_out(['ok' => true, 'saved' => date('H:i:s')]);
        flash('Урок сохранён.'); redirect(url('admin/lessons/' . $id));
    }
    $mods = rows('SELECT m.title, m.id, t.name AS track FROM ' . t('module_lessons') . ' ml JOIN ' . t('modules') . ' m ON m.id = ml.module_id LEFT JOIN ' . t('tracks') . ' t ON t.id = m.track_id WHERE ml.lesson_id = ?', [$id]);
    $where = $mods ? implode(', ', array_map(function ($m) { return ($m['track'] ? 'путь «' . $m['track'] . '» · ' : '') . $m['title']; }, $mods)) : 'не входит ни в один модуль';
    $snips = ['h2' => 'Заголовок', 'lead' => 'Вводный абзац', 'note' => 'ℹ️ Заметка', 'tip' => '💡 Совет', 'warn' => '⚠️ Внимание', 'win' => '🎉 Итог', 'key' => '⭐ Главное',
              'prompt' => 'Запрос к ИИ', 'quiz' => 'Квиз', 'checklist' => 'Чеклист', 'stepper' => 'Шаги', 'reveal' => 'Ответ по кнопке', 'compare' => 'Плохо / хорошо',
              'tabs' => 'Вкладки', 'mock' => 'Экран', 'table' => 'Таблица', 'flow' => 'Цепочка', 'steps' => 'Список шагов', 'task' => 'Задание'];
    $tb = '';
    foreach ($snips as $k => $label) $tb .= '<button type="button" class="btn tiny" data-snippet="' . $k . '">' . e($label) . '</button>';
    $ai = ai_ready();
    $h = '<form method="post" class="lesson-editor" id="lesson-form" data-slug="' . e($l['slug']) . '">' . csrf_field() . '<input type="hidden" name="action" value="save">'
        . '<div class="adm-head"><h1>' . e($l['title']) . '</h1><div class="row"><span class="save-state muted" id="save-state"></span><button class="btn" type="submit" formaction="' . e(url('admin/lessons/' . $id . '/preview')) . '" formtarget="preview" id="preview-btn">Предпросмотр</button><button class="btn primary" type="submit">Сохранить <kbd>Ctrl+S</kbd></button></div></div>'
        . '<p class="muted small">Курс: <a href="' . e(url('admin/courses/' . $c['id'] . '/structure')) . '">' . e($c['title']) . '</a> · ' . e($where) . ' · <a href="' . e(lesson_url($c, $l['slug'])) . '" target="_blank" rel="noopener">открыть на сайте ↗</a></p>'
        . '<div class="form-grid lesson-meta">' . adm_field('title', 'Заголовок', $l['title'], 'text', 'required maxlength="255"') . adm_field('slug', 'Адрес', $l['slug'], 'text', 'required pattern="[a-z0-9][a-z0-9\-]*" maxlength="96"') . adm_field('minutes', 'Минут', (string)$l['minutes'], 'number', 'min="1" max="600"') . '</div>'
        . '<div class="row">' . adm_check('is_control', 'Контрольное задание', (bool)$l['is_control'], 'Засчитывается, когда отмечен чеклист и квиз пройден на 70%') . adm_check('is_preview', 'Бесплатный предпросмотр', (bool)$l['is_preview'], 'Открыт всем, даже в закрытом курсе') . '</div>'
        . '<div class="editor-split"><div class="editor-col"><div class="toolbar" id="toolbar">' . $tb . '</div><textarea name="body" id="body" class="code" spellcheck="false">' . e($l['body']) . '</textarea></div>'
        . '<div class="preview-col"><iframe name="preview" id="preview" title="Предпросмотр урока"></iframe></div></div>'
        . '</form>';
    $h .= '<section class="panel ai-panel" id="ai-panel" data-endpoint="' . e(url('admin/ai')) . '" data-csrf="' . e(csrf_token()) . '" data-lesson="' . $id . '"><h2>🤖 ИИ-помощник</h2>'
        . ($ai ? '<div class="row ai-actions"><button class="btn small" type="button" data-ai="draft">Написать урок по теме</button><button class="btn small" type="button" data-ai="quiz">Квиз по тексту</button><button class="btn small" type="button" data-ai="checklist">Практика с чеклистом</button><button class="btn small" type="button" data-ai="improve">Улучшить выделенное</button><button class="btn small" type="button" data-ai="free">Свой запрос</button></div>'
            . '<textarea id="ai-prompt" rows="3" placeholder="Тема урока, пожелания или свой запрос. Для «Улучшить» выделите фрагмент в тексте урока."></textarea><div id="ai-status" class="muted small"></div><div id="ai-result" class="ai-result" hidden><div class="row"><button class="btn small primary" type="button" id="ai-insert">Вставить в курсор</button><button class="btn small" type="button" id="ai-append">В конец урока</button><button class="btn small" type="button" id="ai-replace">Заменить весь текст</button></div><pre id="ai-out"></pre></div>'
            : '<p class="muted">Подключите нейросеть в <a href="' . e(url('admin/settings')) . '#ai">настройках</a>: ключ API и модель. Помощник будет писать черновики уроков, квизы и чеклисты прямо в редактор.</p>')
        . '</section>';
    $h .= '<details class="panel danger-zone"><summary>Удалить урок</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="delete"><p>Урок удалится из курса и из всех модулей.</p><button class="btn danger" type="submit" data-confirm="Удалить урок навсегда?">Удалить урок</button></form></details>';
    adm_page($l['title'], $h, 'courses', [['Курсы', url('admin/courses')], [$c['title'], url('admin/courses/' . $c['id'] . '/structure')], [$l['title'], null]]);
}

function adm_preview(int $id): void {
    $l = row('SELECT * FROM ' . t('lessons') . ' WHERE id = ?', [$id]) ?? not_found();
    $c = course_by_id((int)$l['course_id']);
    if (is_post()) {
        csrf_check();
        $l['title'] = trim((string)post('title')) ?: $l['title'];
        $l['body'] = (string)post('body');
        $l['is_control'] = post('is_control') === '1' ? 1 : 0;
    }
    header('X-Frame-Options: SAMEORIGIN');
    render_course_page($c, current_user(), 'lesson', ['lesson' => $l, 'title' => 'Предпросмотр: ' . $l['title']]);
}

/* ---------- Пользователи ---------- */
function adm_users(): void {
    $qs = trim((string)get('q'));
    $p = []; $w = '';
    if ($qs !== '') { $w = 'WHERE email LIKE ? OR name LIKE ?'; $p = ['%' . $qs . '%', '%' . $qs . '%']; }
    $page = max(1, (int)get('page', 1)); $per = 50; $off = ($page - 1) * $per;
    $total = (int)val('SELECT COUNT(*) FROM ' . t('users') . " $w", $p);
    $list = rows('SELECT u.*, (SELECT COUNT(*) FROM ' . t('progress') . ' p WHERE p.user_id = u.id) AS courses FROM ' . t('users') . " u $w ORDER BY u.id DESC LIMIT $per OFFSET $off", $p);
    $h = '<div class="adm-head"><h1>Пользователи <small>' . $total . '</small></h1><form class="adm-search" method="get"><input type="search" name="q" value="' . e($qs) . '" placeholder="Почта или имя"></form></div>';
    $h .= '<section class="panel"><table class="adm-table"><thead><tr><th>Имя</th><th>Почта</th><th>Роль</th><th>Курсов</th><th>Регистрация</th><th>Последний вход</th></tr></thead><tbody>';
    foreach ($list as $u) $h .= '<tr><td><a href="' . e(url('admin/users/' . $u['id'])) . '"><b>' . e($u['name']) . '</b></a></td><td>' . e($u['email']) . '</td><td>' . ($u['role'] === 'admin' ? '<span class="badge accent">админ</span>' : 'ученик') . '</td><td>' . (int)$u['courses'] . '</td><td>' . e(date('d.m.Y', strtotime($u['created_at']))) . '</td><td>' . ($u['last_login_at'] ? e(date('d.m.Y H:i', strtotime($u['last_login_at']))) : '—') . '</td></tr>';
    if (!$list) $h .= '<tr><td colspan="6" class="muted">Никого не нашлось.</td></tr>';
    $h .= '</tbody></table></section>';
    $pages = (int)ceil($total / $per);
    if ($pages > 1) { $h .= '<nav class="pager">'; for ($i = 1; $i <= $pages; $i++) $h .= '<a class="' . ($i === $page ? 'active' : '') . '" href="?' . e(http_build_query(array_filter(['q' => $qs, 'page' => $i]))) . '">' . $i . '</a>'; $h .= '</nav>'; }
    adm_page('Пользователи', $h, 'users');
}

function adm_user(int $id, array $me): void {
    $u = row('SELECT * FROM ' . t('users') . ' WHERE id = ?', [$id]) ?? not_found();
    if (is_post()) {
        csrf_check();
        $act = post('action');
        $admins = (int)val('SELECT COUNT(*) FROM ' . t('users') . " WHERE role = 'admin'");
        if ($act === 'role') {
            $role = post('role') === 'admin' ? 'admin' : 'student';
            if ($u['role'] === 'admin' && $role === 'student' && $admins <= 1) flash('Нельзя снять последнего администратора.', 'error');
            else { q('UPDATE ' . t('users') . ' SET role = ? WHERE id = ?', [$role, $id]); flash('Роль изменена.'); }
        } elseif ($act === 'enroll') {
            enroll($id, (int)post('course_id'), 'admin'); flash('Доступ выдан.');
        } elseif ($act === 'unenroll') {
            q('DELETE FROM ' . t('enrollments') . ' WHERE user_id = ? AND course_id = ?', [$id, (int)post('course_id')]); flash('Доступ снят.');
        } elseif ($act === 'password') {
            if (mb_strlen((string)post('password')) < PASSWORD_MIN) flash('Пароль — не короче ' . PASSWORD_MIN . ' символов.', 'error');
            else { set_password($id, (string)post('password')); flash('Пароль изменён. Сообщите его пользователю.'); }
        } elseif ($act === 'delete') {
            if ($id === (int)$me['id']) flash('Себя удалить отсюда нельзя.', 'error');
            elseif ($u['role'] === 'admin' && $admins <= 1) flash('Нельзя удалить последнего администратора.', 'error');
            else { q('DELETE FROM ' . t('users') . ' WHERE id = ?', [$id]); flash('Пользователь удалён.'); redirect(url('admin/users')); }
        }
        redirect(url('admin/users/' . $id));
    }
    $enr = rows('SELECT c.id, c.title, e.source, e.created_at FROM ' . t('enrollments') . ' e JOIN ' . t('courses') . ' c ON c.id = e.course_id WHERE e.user_id = ? ORDER BY e.created_at DESC', [$id]);
    $prog = rows('SELECT c.*, p.data AS pdata, p.updated_at AS pupd FROM ' . t('progress') . ' p JOIN ' . t('courses') . ' c ON c.id = p.course_id WHERE p.user_id = ? ORDER BY p.updated_at DESC', [$id]);
    $closed = rows('SELECT id, title FROM ' . t('courses') . " WHERE access = 'closed' ORDER BY title");
    $h = '<div class="adm-head"><h1>' . e($u['name']) . ' <small>' . e($u['email']) . '</small></h1></div><div class="grid2">';
    $h .= '<section class="panel"><h2>Прогресс</h2><table><tbody>';
    foreach ($prog as $c) $h .= '<tr><td>' . e($c['title']) . '</td><td>' . progress_percent($c, json_decode($c['pdata'], true) ?: []) . '%</td><td class="muted">' . e(date('d.m H:i', strtotime($c['pupd']))) . '</td></tr>';
    if (!$prog) $h .= '<tr><td class="muted">Пока не начинал ни одного курса.</td></tr>';
    $h .= '</tbody></table><h2>Доступ к закрытым курсам</h2><table><tbody>';
    foreach ($enr as $c) $h .= '<tr><td>' . e($c['title']) . '</td><td class="muted">' . e($c['source'] === 'code' ? 'по коду' : 'выдан') . '</td><td class="right">' . adm_post_btn('unenroll', 'Снять', ['course_id' => $c['id']], 'btn tiny ghost danger-text') . '</td></tr>';
    if (!$enr) $h .= '<tr><td class="muted">Нет.</td></tr>';
    $h .= '</tbody></table>';
    if ($closed) { $o = []; foreach ($closed as $c) $o[$c['id']] = $c['title']; $h .= '<form method="post" class="row">' . csrf_field() . '<input type="hidden" name="action" value="enroll">' . adm_select('course_id', 'Выдать доступ', $o, '') . '<button class="btn small" type="submit">Выдать</button></form>'; }
    $h .= '</section><section class="panel"><h2>Управление</h2><p class="muted small">Зарегистрирован ' . e(date('d.m.Y H:i', strtotime($u['created_at']))) . ($u['consent_at'] ? ', согласие на обработку данных ' . e(date('d.m.Y H:i', strtotime($u['consent_at']))) : '') . '</p>'
        . '<form method="post" class="row">' . csrf_field() . '<input type="hidden" name="action" value="role">' . adm_select('role', 'Роль', ['student' => 'Ученик', 'admin' => 'Администратор'], $u['role']) . '<button class="btn small" type="submit">Сохранить</button></form>'
        . '<form method="post" class="row">' . csrf_field() . '<input type="hidden" name="action" value="password">' . adm_field('password', 'Новый пароль', '', 'text', 'minlength="' . PASSWORD_MIN . '" autocomplete="off"') . '<button class="btn small" type="submit">Задать</button></form>'
        . '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="delete"><button class="btn small danger" type="submit" data-confirm="Удалить пользователя и весь его прогресс?">Удалить пользователя</button></form></section></div>';
    adm_page($u['name'], $h, 'users', [['Пользователи', url('admin/users')], [$u['name'], null]]);
}

/* ---------- Настройки ---------- */
function adm_settings(): void {
    $keys = ['site_name', 'site_title', 'site_tagline', 'default_course', 'mail_from', 'privacy_html', 'ai_provider', 'ai_base_url', 'ai_model'];
    if (is_post()) {
        csrf_check();
        foreach ($keys as $k) set_setting($k, trim((string)post($k)));
        set_setting('registration', post('registration') === '1' ? '1' : '0');
        if (trim((string)post('ai_key')) !== '') set_setting('ai_key', trim((string)post('ai_key')));
        if (post('ai_key_clear') === '1') set_setting('ai_key', '');
        flash('Настройки сохранены.'); redirect(url('admin/settings'));
    }
    $courses = ['' => '—'];
    foreach (rows('SELECT slug, title FROM ' . t('courses') . ' ORDER BY title') as $c) $courses[$c['slug']] = $c['title'];
    $hasKey = setting('ai_key', '') !== '';
    $h = '<div class="adm-head"><h1>Настройки</h1></div><form method="post" class="form adm-form">' . csrf_field()
        . '<section class="panel"><h2>Сайт</h2><div class="form-grid">' . adm_field('site_name', 'Название сайта (в шапке)', setting('site_name', 'Курсы'), 'text', 'maxlength="80"') . adm_field('site_title', 'Заголовок каталога', setting('site_title', 'Курсы'), 'text', 'maxlength="120"') . '</div>'
        . adm_field('site_tagline', 'Подзаголовок каталога', setting('site_tagline', ''), 'text', 'maxlength="255"')
        . '<div class="form-grid">' . adm_select('default_course', 'Курс для старых адресов (/lessons/…)', $courses, setting('default_course', '')) . adm_field('mail_from', 'Адрес отправителя писем', setting('mail_from', ''), 'email', '', 'Для писем восстановления пароля. Пусто — no-reply@ваш-домен') . '</div>'
        . adm_check('registration', 'Регистрация открыта', setting('registration', '1') === '1') . '</section>'
        . '<section class="panel"><h2>Политика обработки персональных данных</h2>' . adm_field('privacy_html', 'Текст (HTML)', setting('privacy_html', ''), 'textarea', 'rows="10" class="code"', 'Обязательна по 152-ФЗ, раз сайт хранит почты учеников. Укажите оператора: наименование, ИНН, адрес. Не забудьте уведомление Роскомнадзора.') . '</section>'
        . '<section class="panel" id="ai"><h2>🤖 Нейросеть для помощника в редакторе</h2>'
        . '<div class="form-grid">' . adm_select('ai_provider', 'Формат API', ['anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI-совместимый'], setting('ai_provider', 'anthropic'))
        . adm_field('ai_model', 'Модель', setting('ai_model', AI_DEFAULT_MODEL), 'text', 'maxlength="80"', 'Например: ' . AI_DEFAULT_MODEL) . '</div>'
        . adm_field('ai_base_url', 'Адрес API', setting('ai_base_url', ''), 'url', 'placeholder="https://api.anthropic.com"', 'Пусто — официальный адрес. С хостинга в России официальные API обычно недоступны: укажите адрес прокси или агрегатора с тем же форматом.')
        . adm_field('ai_key', 'Ключ API', '', 'password', 'autocomplete="off" placeholder="' . ($hasKey ? '•••••••• ключ сохранён' : 'вставьте ключ') . '"', 'Хранится в базе и не показывается. Оставьте пустым, чтобы не менять.')
        . ($hasKey ? adm_check('ai_key_clear', 'Удалить сохранённый ключ', false) : '')
        . '</section><div class="sticky-save"><button class="btn primary" type="submit">Сохранить настройки</button></div></form>';
    adm_page('Настройки', $h, 'settings');
}

/* ---------- ИИ ---------- */
/* Ответ — поток строк JSON: {"d":"кусок текста"}, {"p":1} пока модель думает, в конце {"done":true,"html":…} или {"error":…} */
function adm_ai(): void {
    if (!is_post() || !csrf_ok((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) json_out(['ok' => false, 'error' => 'Сессия устарела, обновите страницу.'], 419);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $lesson = isset($in['lesson']) ? row('SELECT * FROM ' . t('lessons') . ' WHERE id = ?', [(int)$in['lesson']]) : null;
    $course = $lesson ? course_by_id((int)$lesson['course_id']) : null;
    session_write_close();   /* не держать сессию, пока идёт генерация */
    @set_time_limit(900);
    ignore_user_abort(false);
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store');
    header('X-Accel-Buffering: no');
    $send = function (array $m) { echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n"; flush(); };
    $send(['p' => 1]);
    try {
        $r = ai_lesson_action((string)($in['action'] ?? 'free'), (string)($in['prompt'] ?? ''), (string)($in['body'] ?? ''), (string)($in['selection'] ?? ''), $lesson, $course,
            function (string $t) use ($send) { $send(['d' => $t]); }, function () use ($send) { $send(['p' => 1]); });
        $send(['done' => true, 'html' => $r['html'], 'truncated' => $r['truncated'], 'model' => $r['model'],
               'tokens' => (int)($r['usage']['output_tokens'] ?? $r['usage']['completion_tokens'] ?? 0)]);
    } catch (Throwable $e) {
        $send(['error' => $e->getMessage()]);
    }
    exit;
}
