<?php
/* Пользователи: вход, регистрация, роли, восстановление пароля, защита от перебора. */
defined('APP') || exit;

const LOGIN_MAX_ATTEMPTS = 10;     // попыток за окно
const LOGIN_WINDOW_MIN = 15;       // минут
const PASSWORD_MIN = 8;

function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    start_session();
    $id = (int)($_SESSION['uid'] ?? 0);
    $user = $id ? row('SELECT id, email, name, role, created_at FROM ' . t('users') . ' WHERE id = ?', [$id]) : null;
    if (!$user) { unset($_SESSION['uid']); $user = null; }
    return $user;
}
function is_admin(?array $u = null): bool { $u = $u ?? current_user(); return $u !== null && $u['role'] === 'admin'; }

function login_user(int $id): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    q('UPDATE ' . t('users') . ' SET last_login_at = ? WHERE id = ?', [now(), $id]);
}
function logout_user(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect(url('account/login') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? url()));
    return $u;
}
function require_admin(): array {
    $u = current_user();
    if (!$u) redirect(url('account/login') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? url('admin')));
    if ($u['role'] !== 'admin') { http_response_code(403); render_message('Нет доступа', 'Этот раздел — только для администраторов.'); exit; }
    return $u;
}

/* ---------- Защита от перебора паролей ---------- */
function login_blocked(string $email): bool {
    q('DELETE FROM ' . t('login_attempts') . ' WHERE at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MIN * 60);
    $byIp = (int)val('SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE ip = ? AND at >= ?', [client_ip(), $since]);
    $byEmail = (int)val('SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE email = ? AND at >= ?', [$email, $since]);
    return $byIp >= LOGIN_MAX_ATTEMPTS * 3 || $byEmail >= LOGIN_MAX_ATTEMPTS;
}
function login_failed(string $email): void { q('INSERT INTO ' . t('login_attempts') . ' (ip, email, at) VALUES (?, ?, ?)', [client_ip(), $email, now()]); }

/* Возвращает пользователя или текст ошибки */
function attempt_login(string $email, string $password) {
    $email = mb_strtolower(trim($email));
    if (login_blocked($email)) return 'Слишком много попыток. Подождите ' . LOGIN_WINDOW_MIN . ' минут и попробуйте снова.';
    $u = row('SELECT * FROM ' . t('users') . ' WHERE email = ?', [$email]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        login_failed($email);
        usleep(300000);
        return 'Неверная почта или пароль.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE ' . t('users') . ' SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    login_user((int)$u['id']);
    return $u;
}

/* Возвращает id нового пользователя или текст ошибки */
function register_user(string $name, string $email, string $password, bool $consent, string $role = 'student') {
    $name = trim($name); $email = mb_strtolower(trim($email));
    if ($name === '' || mb_strlen($name) > 120) return 'Укажите имя.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) return 'Проверьте адрес почты.';
    if (mb_strlen($password) < PASSWORD_MIN) return 'Пароль — не короче ' . PASSWORD_MIN . ' символов.';
    if (!$consent) return 'Нужно согласие на обработку персональных данных.';
    if (val('SELECT id FROM ' . t('users') . ' WHERE email = ?', [$email])) return 'Эта почта уже зарегистрирована. Войдите или восстановите пароль.';
    q('INSERT INTO ' . t('users') . ' (email, name, password_hash, role, consent_at, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$email, $name, password_hash($password, PASSWORD_DEFAULT), $role, now(), now()]);
    return last_id();
}

function set_password(int $userId, string $password): void {
    q('UPDATE ' . t('users') . ' SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
}

/* ---------- Восстановление пароля ---------- */
function create_reset_token(int $userId): string {
    $token = bin2hex(random_bytes(32));
    q('UPDATE ' . t('password_resets') . ' SET used = 1 WHERE user_id = ?', [$userId]);
    q('INSERT INTO ' . t('password_resets') . ' (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
    return $token;
}
function find_reset(string $token): ?array {
    if (!preg_match('~^[a-f0-9]{64}$~', $token)) return null;
    return row('SELECT * FROM ' . t('password_resets') . ' WHERE token_hash = ? AND used = 0 AND expires_at > ?', [hash('sha256', $token), now()]);
}
function use_reset(int $id): void { q('UPDATE ' . t('password_resets') . ' SET used = 1 WHERE id = ?', [$id]); }

function send_mail(string $to, string $subject, string $text): bool {
    $from = setting('mail_from', 'no-reply@' . preg_replace('~^www\.~', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
    $site = setting('site_name', 'Курсы');
    $headers = [
        'From: =?UTF-8?B?' . base64_encode($site) . '?= <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, implode("\r\n", $headers));
}
