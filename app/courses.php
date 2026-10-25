<?php
/* Курсы: выборки, манифест для движка, доступ, прогресс. */
defined('APP') || exit;

const RESERVED_SLUGS = ['admin', 'account', 'api', 'assets', 'install', 'privacy', 'content', 'app', 'bin', 'database', 'tools', 'tests', 'vendor', 'lessons', 'index.html', 'certificate.html', 'c', 'catalog', 'courses'];
const ACCESS_LABELS = ['open' => 'Открытый', 'login' => 'По регистрации', 'closed' => 'Закрытый'];

function course_by_slug(string $slug): ?array { return row('SELECT * FROM ' . t('courses') . ' WHERE slug = ?', [$slug]); }
function course_by_id(int $id): ?array { return row('SELECT * FROM ' . t('courses') . ' WHERE id = ?', [$id]); }
function course_url(array $c, string $path = ''): string { return url($c['slug'] . '/' . ltrim($path, '/')); }
function lesson_url(array $c, string $lessonSlug): string { return course_url($c, 'lessons/' . $lessonSlug . '.html'); }
function storage_key(array $c): string { return $c['storage_key'] !== '' ? $c['storage_key'] : 'kurs:' . $c['slug'] . ':'; }
/* Почему курсу нельзя дать этот адрес; null — можно. $exceptId — курс, которому адрес уже принадлежит. */
function slug_problem(string $slug, ?int $exceptId = null): ?string {
    if (!valid_slug($slug)) return 'Адрес «' . $slug . '» недопустим: латиница, цифры и дефис.';
    if (in_array($slug, RESERVED_SLUGS, true)) return 'Адрес «' . $slug . '» занят разделом сайта.';
    $id = val('SELECT id FROM ' . t('courses') . ' WHERE slug = ?', [$slug]);
    if ($id) return (int)$id === (int)$exceptId ? null : 'Адрес «' . $slug . '» уже занят другим курсом.';
    /* Настоящая папка или файл в корне сайта отдаются сервером напрямую и перекрыли бы курс */
    if (file_exists(ROOT_DIR . '/' . $slug)) return 'Адрес «' . $slug . '» занят папкой «' . $slug . '» в корне сайта на сервере.';
    return null;
}
function slug_free(string $slug, ?int $exceptId = null): bool { return slug_problem($slug, $exceptId) === null; }

function tracks_of(int $courseId): array { return rows('SELECT * FROM ' . t('tracks') . ' WHERE course_id = ? ORDER BY position, id', [$courseId]); }
function modules_of(int $courseId): array { return rows('SELECT * FROM ' . t('modules') . ' WHERE course_id = ? ORDER BY (track_id IS NOT NULL), track_id, position, id', [$courseId]); }
function module_lessons(int $moduleId): array {
    return rows('SELECT l.*, ml.position AS ml_pos FROM ' . t('module_lessons') . ' ml JOIN ' . t('lessons') . ' l ON l.id = ml.lesson_id WHERE ml.module_id = ? ORDER BY ml.position, l.id', [$moduleId]);
}
function lesson_by_slug(int $courseId, string $slug): ?array { return row('SELECT * FROM ' . t('lessons') . ' WHERE course_id = ? AND slug = ?', [$courseId, $slug]); }
function lesson_count(int $courseId): int { return (int)val('SELECT COUNT(*) FROM ' . t('lessons') . ' WHERE course_id = ?', [$courseId]); }

/* ---------- Доступ ---------- */
function enrolled(int $userId, int $courseId): bool { return (bool)val('SELECT 1 FROM ' . t('enrollments') . ' WHERE user_id = ? AND course_id = ?', [$userId, $courseId]); }
function enroll(int $userId, int $courseId, string $source = 'admin'): void {
    q('INSERT IGNORE INTO ' . t('enrollments') . ' (user_id, course_id, source, created_at) VALUES (?, ?, ?, ?)', [$userId, $courseId, $source, now()]);
}
function can_access(array $c, ?array $u): bool {
    if ($c['access'] === 'open') return true;
    if (!$u) return false;
    if ($c['access'] === 'login' || $u['role'] === 'admin') return true;
    return enrolled((int)$u['id'], (int)$c['id']);
}
function lesson_open(array $c, array $lesson, ?array $u): bool { return (bool)$lesson['is_preview'] || can_access($c, $u); }
function can_view_course(array $c, ?array $u): bool { return (bool)$c['is_published'] || is_admin($u); }

/* ---------- Манифест курса для движка (window.AIKURS_DATA) ---------- */
function lesson_item(array $l, bool $locked, ?int &$counter): array {
    $num = null;
    if (!$l['is_control']) {
        if (preg_match('~^[a-z]?(\d+)-~', $l['slug'], $m)) $num = (int)$m[1];
        else { $counter++; $num = $counter; }
    }
    $item = ['id' => $l['slug'], 'title' => $l['title'], 'minutes' => (int)$l['minutes']];
    if ($l['is_control']) $item['control'] = true;
    if ($num !== null && !preg_match('~^[a-z]?\d+-~', $l['slug'])) $item['num'] = $num;
    if ($locked) $item['locked'] = true;
    return $item;
}

function course_structure(array $c, ?array $u): array {
    $access = can_access($c, $u);
    $tracks = tracks_of((int)$c['id']);
    $modules = modules_of((int)$c['id']);
    $common = []; $byTrack = [];
    foreach ($modules as $m) {
        $lessons = module_lessons((int)$m['id']);
        $entry = ['id' => 'm' . $m['id'], 'title' => $m['title'], 'lessons' => $lessons];
        if ($m['track_id'] === null) $common[] = $entry; else $byTrack[(int)$m['track_id']][] = $entry;
    }
    $counter = 0;
    $commonOut = [];
    foreach ($common as $m) {
        $m['lessons'] = array_map(function ($l) use ($access, &$counter) { return lesson_item($l, !$access && !$l['is_preview'], $counter); }, $m['lessons']);
        $commonOut[] = $m;
    }
    $commonCounter = $counter;
    $tracksOut = [];
    foreach ($tracks as $tr) {
        $counter = $commonCounter;
        $mods = [];
        foreach ($byTrack[(int)$tr['id']] ?? [] as $m) {
            $m['lessons'] = array_map(function ($l) use ($access, &$counter) { return lesson_item($l, !$access && !$l['is_preview'], $counter); }, $m['lessons']);
            $mods[] = $m;
        }
        $tracksOut[$tr['slug']] = [
            'id' => $tr['slug'], 'name' => $tr['name'], 'emoji' => $tr['emoji'], 'tagline' => $tr['tagline'],
            'desc' => (string)$tr['description'], 'skills' => lines($tr['skills']), 'modules' => $mods,
        ];
    }
    return ['common' => $commonOut, 'tracks' => (object)$tracksOut];
}

function engine_data(array $c, ?array $u): array {
    $s = course_structure($c, $u);
    $data = [
        'course' => [
            'slug' => $c['slug'], 'title' => $c['title'], 'brand' => $c['brand'] ?: $c['title'], 'brandSub' => $c['brand_sub'],
            'logo' => $c['logo'], 'certTitle' => $c['cert_title'] ?: $c['title'], 'certSub' => $c['cert_sub'],
            'commonSkills' => lines($c['common_skills']), 'storageKey' => storage_key($c), 'moduleBase' => (int)($c['module_base'] ?? 1),
        ],
        'common' => $s['common'],
        'tracks' => $s['tracks'],
        'urls' => ['catalog' => url(), 'account' => url('account'), 'login' => url('account/login') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? course_url($c))],
        'user' => null,
    ];
    if ($u) {
        $data['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']];
        $p = row('SELECT data, ts FROM ' . t('progress') . ' WHERE user_id = ? AND course_id = ?', [$u['id'], $c['id']]);
        $data['progress'] = $p ? ['ts' => (int)$p['ts'], 'data' => json_decode($p['data'], true) ?: (object)[]] : null;
        $data['api'] = ['progress' => url('api/progress') . '?course=' . rawurlencode($c['slug']), 'csrf' => csrf_token()];
    }
    return $data;
}

/* ---------- Прогресс ---------- */
function save_progress(int $userId, int $courseId, array $data, int $ts): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (strlen($json) > 512 * 1024) throw new RuntimeException('progress too large');
    q('INSERT INTO ' . t('progress') . ' (user_id, course_id, data, ts) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE data = IF(VALUES(ts) >= ts, VALUES(data), data), ts = GREATEST(ts, VALUES(ts))',
      [$userId, $courseId, $json, $ts]);
}

/* Процент прохождения по сохранённому снимку: учитывает выбранный путь */
function progress_percent(array $c, array $snapshot): int {
    $done = json_decode((string)($snapshot['done'] ?? '[]'), true) ?: [];
    $track = json_decode((string)($snapshot['track'] ?? 'null'), true);
    $s = course_structure($c, null);
    $ids = [];
    foreach ($s['common'] as $m) foreach ($m['lessons'] as $l) $ids[$l['id']] = true;
    $tracks = (array)$s['tracks'];
    if ($track && isset($tracks[$track])) foreach ($tracks[$track]['modules'] as $m) foreach ($m['lessons'] as $l) $ids[$l['id']] = true;
    if (!$ids) return 0;
    $n = count(array_intersect(array_keys($ids), (array)$done));
    return (int)round(100 * $n / count($ids));
}

/* ---------- Каталог ---------- */
function catalog(array $f, int $page, int $perPage, bool $withDrafts = false): array {
    $where = []; $p = [];
    if (!$withDrafts) $where[] = 'is_published = 1';
    if (($f['q'] ?? '') !== '') { $where[] = '(title LIKE ? OR description LIKE ? OR category LIKE ?)'; $like = '%' . $f['q'] . '%'; array_push($p, $like, $like, $like); }
    if (($f['category'] ?? '') !== '') { $where[] = 'category = ?'; $p[] = $f['category']; }
    if (in_array($f['access'] ?? '', ['open', 'login', 'closed'], true)) { $where[] = 'access = ?'; $p[] = $f['access']; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int)val('SELECT COUNT(*) FROM ' . t('courses') . " $w", $p);
    $off = max(0, ($page - 1) * $perPage);
    $items = rows('SELECT c.*, (SELECT COUNT(*) FROM ' . t('lessons') . ' l WHERE l.course_id = c.id) AS lessons_count, (SELECT COALESCE(SUM(minutes),0) FROM ' . t('lessons') . ' l WHERE l.course_id = c.id) AS minutes_total FROM ' . t('courses') . " c $w ORDER BY position, id DESC LIMIT $perPage OFFSET $off", $p);
    return [$items, $total];
}
function categories(): array { return array_column(rows('SELECT category, COUNT(*) n FROM ' . t('courses') . " WHERE is_published = 1 AND category <> '' GROUP BY category ORDER BY category"), 'n', 'category'); }
