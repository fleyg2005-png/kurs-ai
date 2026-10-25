<?php
/* Маршруты сайта. */
defined('APP') || exit;

require APP_DIR . '/pages_public.php';
require APP_DIR . '/pages_account.php';

function dispatch(string $path): void {
    $parts = $path === '' ? [] : explode('/', $path);
    $first = $parts[0] ?? '';

    if ($path === '') { page_catalog(); return; }
    if ($path === 'privacy') { page_privacy(); return; }
    if ($first === 'account') { account_dispatch(array_slice($parts, 1)); return; }
    if ($path === 'api/progress') { api_progress(); return; }
    if ($first === 'admin') { require APP_DIR . '/admin/admin.php'; admin_dispatch(array_slice($parts, 1)); return; }
    if ($first === 'install') not_found();

    /* Старые адреса статичного курса: /index.html, /lessons/x.html, /certificate.html */
    if ($path === 'index.html' || $path === 'certificate.html' || $first === 'lessons') {
        $c = course_by_slug((string)setting('default_course', 'kurs-ai'));
        if (!$c) not_found();
        redirect(course_url($c, $path === 'index.html' ? '' : $path), 301);
    }

    /* Курс: /slug/, /slug/lessons/x.html, /slug/certificate.html, /slug/unlock */
    if (!valid_slug($first)) not_found();
    $c = course_by_slug($first);
    if (!$c || !can_view_course($c, current_user())) not_found();
    $rest = implode('/', array_slice($parts, 1));
    if (count($parts) === 1) redirect(course_url($c), 301);
    if ($rest === '' || $rest === 'index.html') { page_course_home($c); return; }
    if ($rest === 'certificate.html') { page_certificate($c); return; }
    if ($rest === 'unlock') { page_unlock($c); return; }
    if (count($parts) === 3 && $parts[1] === 'lessons' && preg_match('~^([a-z0-9][a-z0-9-]{0,95})\.html$~', $parts[2], $m)) { page_lesson($c, $m[1]); return; }
    not_found();
}
