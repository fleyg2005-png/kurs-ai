<?php
/* Кабинет: вход, регистрация, выход, восстановление пароля, мои курсы. */
defined('APP') || exit;

function account_dispatch(array $p): void {
    $a = $p[0] ?? '';
    if ($a === '') { page_account(); return; }
    if ($a === 'login') { page_login(); return; }
    if ($a === 'register') { page_register(); return; }
    if ($a === 'logout') { page_logout(); return; }
    if ($a === 'reset' && !isset($p[1])) { page_reset_request(); return; }
    if ($a === 'reset' && isset($p[1])) { page_reset_form((string)$p[1]); return; }
    not_found();
}

function auth_card(string $title, string $inner, string $sub = ''): string {
    return '<div class="auth-card card"><h1 class="mt0">' . e($title) . '</h1>' . ($sub ? '<p class="muted">' . $sub . '</p>' : '') . $inner . '</div>';
}
function field(string $name, string $label, string $type = 'text', string $value = '', array $attr = []): string {
    $a = '';
    foreach ($attr as $k => $v) $a .= ' ' . $k . ($v === true ? '' : '="' . e((string)$v) . '"');
    return '<label class="field"><span>' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '></label>';
}

function page_login(): void {
    $next = safe_next((string)(post('next') ?: get('next')));
    if (current_user()) redirect($next);
    $err = ''; $email = '';
    if (is_post()) {
        csrf_check();
        $email = (string)post('email');
        $r = attempt_login($email, (string)post('password'));
        if (is_array($r)) redirect($next);
        $err = $r;
    }
    $f = '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="next" value="' . e($next) . '">'
       . ($err ? '<div class="flash flash-error">' . e($err) . '</div>' : '')
       . field('email', 'Почта', 'email', $email, ['required' => true, 'autocomplete' => 'email', 'autofocus' => true])
       . field('password', 'Пароль', 'password', '', ['required' => true, 'autocomplete' => 'current-password'])
       . '<button class="btn primary wide" type="submit">Войти</button></form>'
       . '<p class="auth-links"><a href="' . e(url('account/reset')) . '">Забыли пароль?</a> · <a href="' . e(url('account/register') . '?next=' . rawurlencode($next)) . '">Регистрация</a></p>';
    render_site_page('Вход', auth_card('Вход', $f), ['narrow' => true]);
}

function page_register(): void {
    $next = safe_next((string)(post('next') ?: get('next')));
    if (current_user()) redirect($next);
    if (setting('registration', '1') !== '1') { render_message('Регистрация закрыта', 'Сейчас новые аккаунты не создаются. Открытые курсы доступны без регистрации.'); return; }
    $err = ''; $name = ''; $email = '';
    if (is_post()) {
        csrf_check();
        $name = (string)post('name'); $email = (string)post('email');
        $r = register_user($name, $email, (string)post('password'), post('consent') === '1');
        if (is_int($r)) { login_user($r); flash('Аккаунт создан. Прогресс курсов теперь сохраняется в нём.'); redirect($next); }
        $err = $r;
    }
    $f = '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="next" value="' . e($next) . '">'
       . ($err ? '<div class="flash flash-error">' . e($err) . '</div>' : '')
       . field('name', 'Имя', 'text', $name, ['required' => true, 'maxlength' => 120, 'autocomplete' => 'name', 'autofocus' => true])
       . field('email', 'Почта', 'email', $email, ['required' => true, 'autocomplete' => 'email'])
       . field('password', 'Пароль — не короче ' . PASSWORD_MIN . ' символов', 'password', '', ['required' => true, 'minlength' => PASSWORD_MIN, 'autocomplete' => 'new-password'])
       . '<label class="check"><input type="checkbox" name="consent" value="1" required> <span>Согласен(на) на обработку персональных данных по <a href="' . e(url('privacy')) . '" target="_blank" rel="noopener">политике</a></span></label>'
       . '<button class="btn primary wide" type="submit">Создать аккаунт</button></form>'
       . '<p class="auth-links">Уже есть аккаунт? <a href="' . e(url('account/login') . '?next=' . rawurlencode($next)) . '">Войти</a></p>';
    render_site_page('Регистрация', auth_card('Регистрация', $f, 'Открытые курсы доступны и без аккаунта. Аккаунт нужен, чтобы сохранять прогресс и проходить курсы по регистрации.'), ['narrow' => true]);
}

function page_logout(): void {
    if (is_post()) { csrf_check(); logout_user(); }
    redirect(url());
}

function page_reset_request(): void {
    $sent = false;
    if (is_post()) {
        csrf_check();
        $email = mb_strtolower(trim((string)post('email')));
        if (!login_blocked('reset:' . $email)) {
            login_failed('reset:' . $email);
            $u = row('SELECT id, email, name FROM ' . t('users') . ' WHERE email = ?', [$email]);
            if ($u) {
                $token = create_reset_token((int)$u['id']);
                $link = site_origin() . url('account/reset/' . $token);
                send_mail($u['email'], 'Восстановление пароля', "Здравствуйте, {$u['name']}!\n\nЧтобы задать новый пароль, откройте ссылку (действует час):\n{$link}\n\nЕсли вы не запрашивали восстановление, просто удалите это письмо.");
            }
        }
        $sent = true;
    }
    $f = $sent
        ? '<div class="flash flash-ok">Если такая почта зарегистрирована, мы отправили на неё ссылку для нового пароля. Проверьте и папку «Спам».</div>'
        : '<form method="post" class="form">' . csrf_field() . field('email', 'Почта', 'email', '', ['required' => true, 'autofocus' => true]) . '<button class="btn primary wide" type="submit">Прислать ссылку</button></form>';
    render_site_page('Восстановление пароля', auth_card('Восстановление пароля', $f . '<p class="auth-links"><a href="' . e(url('account/login')) . '">Вернуться ко входу</a></p>'), ['narrow' => true]);
}

function page_reset_form(string $token): void {
    $r = find_reset($token);
    if (!$r) { render_message('Ссылка устарела', 'Ссылка для нового пароля недействительна или уже использована. Запросите новую.'); return; }
    $err = '';
    if (is_post()) {
        csrf_check();
        $pw = (string)post('password');
        if (mb_strlen($pw) < PASSWORD_MIN) $err = 'Пароль — не короче ' . PASSWORD_MIN . ' символов.';
        else { set_password((int)$r['user_id'], $pw); use_reset((int)$r['id']); login_user((int)$r['user_id']); flash('Пароль обновлён.'); redirect(url('account')); }
    }
    $f = '<form method="post" class="form">' . csrf_field() . ($err ? '<div class="flash flash-error">' . e($err) . '</div>' : '')
       . field('password', 'Новый пароль', 'password', '', ['required' => true, 'minlength' => PASSWORD_MIN, 'autocomplete' => 'new-password', 'autofocus' => true])
       . '<button class="btn primary wide" type="submit">Сохранить пароль</button></form>';
    render_site_page('Новый пароль', auth_card('Новый пароль', $f), ['narrow' => true]);
}

function page_account(): void {
    $u = require_login();
    if (is_post()) {
        csrf_check();
        if (post('action') === 'profile') {
            $name = trim((string)post('name'));
            if ($name !== '' && mb_strlen($name) <= 120) { q('UPDATE ' . t('users') . ' SET name = ? WHERE id = ?', [$name, $u['id']]); flash('Имя сохранено.'); }
        } elseif (post('action') === 'password') {
            $row = row('SELECT password_hash FROM ' . t('users') . ' WHERE id = ?', [$u['id']]);
            if (!password_verify((string)post('old'), $row['password_hash'])) flash('Текущий пароль не подошёл.', 'error');
            elseif (mb_strlen((string)post('new')) < PASSWORD_MIN) flash('Новый пароль — не короче ' . PASSWORD_MIN . ' символов.', 'error');
            else { set_password((int)$u['id'], (string)post('new')); flash('Пароль обновлён.'); }
        } elseif (post('action') === 'delete' && post('confirm') === 'УДАЛИТЬ') {
            if ($u['role'] === 'admin' && (int)val('SELECT COUNT(*) FROM ' . t('users') . " WHERE role = 'admin'") <= 1) flash('Нельзя удалить последнего администратора.', 'error');
            else { q('DELETE FROM ' . t('users') . ' WHERE id = ?', [$u['id']]); logout_user(); redirect(url()); }
        }
        redirect(url('account'));
    }
    $mine = rows('SELECT c.*, p.data AS pdata, p.updated_at AS pupd FROM ' . t('courses') . ' c
        LEFT JOIN ' . t('progress') . ' p ON p.course_id = c.id AND p.user_id = ?
        LEFT JOIN ' . t('enrollments') . ' en ON en.course_id = c.id AND en.user_id = ?
        WHERE (p.user_id IS NOT NULL OR en.user_id IS NOT NULL) AND c.is_published = 1
        ORDER BY COALESCE(p.updated_at, en.created_at) DESC', [$u['id'], $u['id']]);
    $h = '<div class="acc-head"><div><h1 class="mt0">' . e($u['name']) . '</h1><p class="muted">' . e($u['email']) . '</p></div>'
       . '<form method="post" action="' . e(url('account/logout')) . '">' . csrf_field() . '<button class="btn" type="submit">Выйти</button></form></div>';
    $h .= '<h2>Мои курсы</h2>';
    if (!$mine) $h .= '<div class="card empty"><p class="mt0">Вы ещё не начали ни одного курса. <a href="' . e(url()) . '">Выбрать курс</a></p></div>';
    else {
        $h .= '<div class="course-grid">';
        foreach ($mine as $c) {
            $pct = $c['pdata'] ? progress_percent($c, json_decode($c['pdata'], true) ?: []) : 0;
            $h .= '<a class="course-card" href="' . e(course_url($c)) . '"><div class="cc-top"><span class="cc-emoji">' . e($c['emoji'] ?: '📘') . '</span><span class="badge">' . $pct . '%</span></div><h3>' . e($c['title']) . '</h3>'
                . '<div class="progress"><span style="width:' . $pct . '%"></span></div><div class="cc-meta"><span>' . ($pct >= 100 ? 'Пройден' : 'Продолжить →') . '</span></div></a>';
        }
        $h .= '</div>';
    }
    $h .= '<h2>Профиль</h2><div class="grid">'
       . '<form method="post" class="card form">' . csrf_field() . '<input type="hidden" name="action" value="profile"><h3 class="mt0">Имя</h3>' . field('name', 'Как к вам обращаться', 'text', $u['name'], ['required' => true, 'maxlength' => 120]) . '<button class="btn" type="submit">Сохранить</button></form>'
       . '<form method="post" class="card form">' . csrf_field() . '<input type="hidden" name="action" value="password"><h3 class="mt0">Пароль</h3>' . field('old', 'Текущий пароль', 'password', '', ['required' => true, 'autocomplete' => 'current-password']) . field('new', 'Новый пароль', 'password', '', ['required' => true, 'minlength' => PASSWORD_MIN, 'autocomplete' => 'new-password']) . '<button class="btn" type="submit">Сменить пароль</button></form>'
       . '</div>'
       . '<details class="card danger-zone"><summary>Удалить аккаунт</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="delete"><p>Аккаунт, прогресс и доступы к курсам удалятся безвозвратно.</p>' . field('confirm', 'Напишите УДАЛИТЬ', 'text', '', ['required' => true]) . '<button class="btn danger" type="submit">Удалить навсегда</button></form></details>';
    render_site_page('Кабинет', $h);
}
