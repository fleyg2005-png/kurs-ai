<?php
/* Общие функции: экранирование, адреса, CSRF, сообщения, ответы. */
defined('APP') || exit;

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* Адрес внутри сайта: url('admin/courses') → /admin/courses */
function url(string $path = ''): string { return base_path() . ltrim($path, '/'); }

function redirect(string $to, int $code = 302): void {
    if (!preg_match('~^https?://~', $to) && ($to === '' || $to[0] !== '/')) $to = url($to);
    header('Location: ' . $to, true, $code);
    exit;
}

/* Безопасный адрес возврата после входа: только внутри сайта */
function safe_next(?string $next): string {
    $next = (string)$next;
    /* Только адреса этого сайта: «//host» и «/\host» браузеры считают чужим сайтом */
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || strpbrk($next, "\\\r\n\t") !== false) return url('account');
    return $next;
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function post(string $k, $d = '') { return $_POST[$k] ?? $d; }
function get(string $k, $d = '') { return $_GET[$k] ?? $d; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(?string $token = null): bool {
    start_session();
    $token = $token ?? (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}
function csrf_check(): void {
    if (!csrf_ok()) { http_response_code(419); render_message('Сессия устарела', 'Обновите страницу и попробуйте ещё раз.'); exit; }
}

/* ---------- Сообщения после действия ---------- */
function flash(string $msg, string $type = 'ok'): void { start_session(); $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { start_session(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function flash_html(): string {
    $out = '';
    foreach (flashes() as [$type, $msg]) $out .= '<div class="flash flash-' . e($type) . '">' . e($msg) . '</div>';
    return $out;
}

/* ---------- Прочее ---------- */
function slugify(string $s): string {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower(trim($s));
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s);
    return trim((string)$s, '-') ?: 'item-' . bin2hex(random_bytes(3));
}
function valid_slug(string $s): bool { return (bool)preg_match('~^[a-z0-9][a-z0-9-]{0,63}$~', $s); }
function plural(int $n, string $one, string $few, string $many): string {
    $m10 = $n % 10; $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) return $one;
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) return $few;
    return $many;
}
function lines(?string $text): array { return array_values(array_filter(array_map('trim', preg_split('~\R~u', (string)$text)), 'strlen')); }
function now(): string { return date('Y-m-d H:i:s'); }
