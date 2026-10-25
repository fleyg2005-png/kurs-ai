<?php
/* Первичное наполнение базы: настройки сайта, администратор, курсы из content/.
   Запуск: php bin/seed.php. Повторный запуск ничего не ломает: существующее не трогается,
   пароль администратора не меняется. */
defined('APP') || exit;

require_once APP_DIR . '/importer.php';

/* $log получает строки отчёта; $ask — вопрос в терминале, если он есть */
function run_seeds(callable $log, ?callable $ask = null): void {
    $email = seed_admin($log, $ask);
    seed_settings($log, $email);
    seed_courses($log);
}

/* Настройки по умолчанию — только те, которых ещё нет */
function seed_settings(callable $log, string $adminEmail): void {
    $site = trim((string)env('SITE_NAME', '')) ?: 'Курсы';
    $defaults = [
        'site_name' => $site,
        'site_title' => $site,
        'site_tagline' => 'Практические курсы. Бесплатные открываются сразу, без регистрации.',
        'registration' => '1',
        'default_course' => in_array('kurs-ai', content_dirs(), true) ? 'kurs-ai' : '',
        'mail_from' => trim((string)env('MAIL_FROM', '')),
        'privacy_html' => default_privacy($site, $adminEmail),
    ];
    $added = [];
    foreach ($defaults as $k => $v) {
        if (val('SELECT COUNT(*) FROM ' . t('settings') . ' WHERE k = ?', [$k])) continue;
        set_setting($k, $v);
        $added[] = $k;
    }
    $log($added ? 'настройки: ' . implode(', ', $added) : 'настройки уже есть');
}

/* Администратор из ADMIN_EMAIL / ADMIN_NAME / ADMIN_PASSWORD. Возвращает его почту. */
function seed_admin(callable $log, ?callable $ask): string {
    $email = mb_strtolower(trim((string)env('ADMIN_EMAIL', '')));
    if ($email === '') {
        $first = val('SELECT email FROM ' . t('users') . " WHERE role = 'admin' ORDER BY id LIMIT 1");
        if ($first) { $log('администратор уже есть: ' . $first); return (string)$first; }
        if ($ask) $email = mb_strtolower(trim($ask('Почта администратора: ')));
        if ($email === '') throw new RuntimeException('Укажи ADMIN_EMAIL в .env — почту, под которой будешь входить в админку.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('ADMIN_EMAIL: «' . $email . '» не похоже на почту.');

    $user = row('SELECT id, role FROM ' . t('users') . ' WHERE email = ?', [$email]);
    if ($user) {
        if ($user['role'] !== 'admin') {
            q('UPDATE ' . t('users') . " SET role = 'admin' WHERE id = ?", [$user['id']]);
            $log('администратор: ' . $email . ' (был учеником, пароль прежний)');
        } else {
            $log('администратор уже есть: ' . $email . ', пароль не меняю');
        }
        return $email;
    }

    $pass = (string)env('ADMIN_PASSWORD', '');
    $generated = $pass === '';
    if ($generated) $pass = seed_password();
    if (mb_strlen($pass) < PASSWORD_MIN) throw new RuntimeException('ADMIN_PASSWORD: не короче ' . PASSWORD_MIN . ' символов.');
    $name = trim((string)env('ADMIN_NAME', '')) ?: 'Администратор';
    q('INSERT INTO ' . t('users') . " (email, name, password_hash, role, consent_at, created_at) VALUES (?, ?, ?, 'admin', ?, ?)",
      [$email, $name, password_hash($pass, PASSWORD_DEFAULT), now(), now()]);
    $log('создан администратор: ' . $email);
    if ($generated) $log('пароль: ' . $pass . '   ← сохрани, больше он нигде не показывается');
    return $email;
}

function seed_password(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $p = '';
    for ($i = 0; $i < 16; $i++) $p .= $abc[random_int(0, strlen($abc) - 1)];
    return $p;
}

/* Курсы из content/: загружаются те, которых ещё нет в базе. Дальше их правят в админке. */
function seed_courses(callable $log): void {
    $dirs = content_dirs();
    if (!$dirs) { $log('курсов в content/ нет'); return; }
    foreach ($dirs as $dir) {
        $meta = json_decode((string)file_get_contents(ROOT_DIR . '/content/' . $dir . '/course.json'), true);
        $slug = (string)($meta['slug'] ?? $dir);
        if (course_by_slug($slug)) { $log('курс уже есть: /' . $slug . '/'); continue; }
        $id = import_course_dir($dir);
        $log('загружен курс /' . $slug . '/: ' . lesson_count($id) . ' ' . plural(lesson_count($id), 'урок', 'урока', 'уроков'));
    }
}

function default_privacy(string $site, string $email): string {
    $contact = $email !== '' ? e($email) : 'почту, указанную на сайте';
    return '<p>Сайт «' . e($site) . '» обрабатывает персональные данные пользователей: имя и адрес электронной почты, указанные при регистрации, а также сведения о прохождении курсов.</p>'
        . '<p><b>Цель обработки</b> — предоставление доступа к курсам и сохранение прогресса обучения. Данные не передаются третьим лицам, кроме случаев, предусмотренных законом.</p>'
        . '<p><b>Срок хранения</b> — до удаления аккаунта. Удалить аккаунт и все связанные данные можно в личном кабинете или по запросу на ' . $contact . '.</p>'
        . '<p>Регистрируясь, пользователь даёт согласие на обработку указанных данных в соответствии с Федеральным законом № 152-ФЗ «О персональных данных».</p>'
        . '<p class="small">Это шаблон. Перед запуском проверьте текст с юристом и укажите оператора данных: наименование, ИНН и адрес.</p>';
}
