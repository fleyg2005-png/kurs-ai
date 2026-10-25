<?php
/* Импорт курса из папки content/<slug>/: course.json + home.html + lessons/*.html.
   Так в базу попадает исходный курс при установке; так же можно завозить новые курсы. */
defined('APP') || exit;

function content_dirs(): array {
    $out = [];
    foreach (glob(ROOT_DIR . '/content/*/course.json') ?: [] as $f) $out[] = basename(dirname($f));
    return $out;
}

/* Тело урока: всё внутри <article> без первого <h1>. Заголовок — текст h1. */
function extract_lesson(string $html): array {
    $title = '';
    if (preg_match('~<article[^>]*>(.*)</article>~su', $html, $m)) $inner = $m[1];
    elseif (preg_match('~<main[^>]*>(.*)</main>~su', $html, $m)) $inner = $m[1];
    else $inner = $html;
    if (preg_match('~<h1[^>]*>(.*?)</h1>~su', $inner, $h)) {
        $title = trim(html_entity_decode(strip_tags($h[1]), ENT_QUOTES, 'UTF-8'));
        $inner = preg_replace('~<h1[^>]*>.*?</h1>~su', '', $inner, 1);
    }
    return ['title' => $title, 'body' => trim((string)$inner) . "\n"];
}

function extract_home(string $html): string {
    if (preg_match('~<main[^>]*>(.*)</main>~su', $html, $m)) return trim($m[1]) . "\n";
    return '';
}

/* Возвращает id курса или бросает исключение */
function import_course_dir(string $slugDir, ?string $newSlug = null, bool $publish = true): int {
    $dir = ROOT_DIR . '/content/' . basename($slugDir);
    $meta = json_decode((string)@file_get_contents($dir . '/course.json'), true);
    if (!is_array($meta)) throw new RuntimeException('Нет или испорчен course.json в content/' . $slugDir);
    $slug = $newSlug ?: (string)($meta['slug'] ?? $slugDir);
    if (!slug_free($slug)) throw new RuntimeException('Адрес «' . $slug . '» уже занят или недопустим.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $home = is_file($dir . '/home.html') ? extract_home((string)file_get_contents($dir . '/home.html')) : '';
        q('INSERT INTO ' . t('courses') . ' (slug, title, brand, brand_sub, logo, emoji, category, description, access, price_label, home_html, cert_title, cert_sub, common_skills, storage_key, module_base, is_published, position, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $slug, (string)$meta['title'], (string)($meta['brand'] ?? ''), (string)($meta['brandSub'] ?? ''), (string)($meta['logo'] ?? ''),
            (string)($meta['emoji'] ?? '📘'), (string)($meta['category'] ?? ''), (string)($meta['description'] ?? ''),
            in_array($meta['access'] ?? 'open', ['open', 'login', 'closed'], true) ? $meta['access'] : 'open',
            (string)($meta['priceLabel'] ?? 'Бесплатно'), $home, (string)($meta['certTitle'] ?? ''), (string)($meta['certSub'] ?? ''),
            implode("\n", (array)($meta['commonSkills'] ?? [])), (string)($meta['storageKey'] ?? ''), (int)($meta['moduleBase'] ?? 1) === 0 ? 0 : 1, $publish ? 1 : 0,
            (int)val('SELECT COALESCE(MAX(position), 0) + 1 FROM ' . t('courses')), now(), now(),
        ]);
        $courseId = last_id();

        $lessonIds = [];
        $ensureLesson = function (array $l) use ($courseId, $dir, &$lessonIds): int {
            $id = (string)$l['id'];
            if (isset($lessonIds[$id])) return $lessonIds[$id];
            $file = $dir . '/lessons/' . $id . '.html';
            $ex = is_file($file) ? extract_lesson((string)file_get_contents($file)) : ['title' => '', 'body' => '<p class="lead">Текст урока ещё не написан.</p>' . "\n"];
            q('INSERT INTO ' . t('lessons') . ' (course_id, slug, title, minutes, is_control, is_preview, body, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [$courseId, $id, (string)($l['title'] ?? $ex['title']), (int)($l['minutes'] ?? 5), !empty($l['control']) ? 1 : 0, !empty($l['preview']) ? 1 : 0, $ex['body'], now()]);
            return $lessonIds[$id] = last_id();
        };
        $addModules = function (array $mods, ?int $trackId) use ($courseId, $ensureLesson) {
            foreach (array_values($mods) as $mi => $m) {
                q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, ?, ?, ?)', [$courseId, $trackId, (string)$m['title'], $mi]);
                $moduleId = last_id();
                foreach (array_values($m['lessons'] ?? []) as $li => $l) {
                    q('INSERT IGNORE INTO ' . t('module_lessons') . ' (module_id, lesson_id, position) VALUES (?, ?, ?)', [$moduleId, $ensureLesson($l), $li]);
                }
            }
        };
        $addModules((array)($meta['common'] ?? []), null);
        $ti = 0;
        foreach ((array)($meta['tracks'] ?? []) as $key => $tr) {
            q('INSERT INTO ' . t('tracks') . ' (course_id, slug, name, emoji, tagline, description, skills, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [$courseId, (string)($tr['id'] ?? $key), (string)$tr['name'], (string)($tr['emoji'] ?? ''), (string)($tr['tagline'] ?? ''), (string)($tr['desc'] ?? ''), implode("\n", (array)($tr['skills'] ?? [])), $ti++]);
            $addModules((array)($tr['modules'] ?? []), last_id());
        }
        $pdo->commit();
        return $courseId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* Экспорт курса в тот же формат (для переноса или резервной копии) */
function export_course(int $courseId): array {
    $c = course_by_id($courseId);
    $lessonOut = function (array $l) { $o = ['id' => $l['slug'], 'title' => $l['title'], 'minutes' => (int)$l['minutes']]; if ($l['is_control']) $o['control'] = true; if ($l['is_preview']) $o['preview'] = true; return $o; };
    $common = []; $tracks = [];
    $trackRows = tracks_of($courseId);
    $bySlug = [];
    foreach ($trackRows as $tr) $bySlug[(int)$tr['id']] = $tr;
    foreach (modules_of($courseId) as $m) {
        $entry = ['title' => $m['title'], 'lessons' => array_map($lessonOut, module_lessons((int)$m['id']))];
        if ($m['track_id'] === null) $common[] = $entry; else $tracks[(int)$m['track_id']][] = $entry;
    }
    $tracksOut = [];
    foreach ($trackRows as $tr) $tracksOut[$tr['slug']] = ['id' => $tr['slug'], 'name' => $tr['name'], 'emoji' => $tr['emoji'], 'tagline' => $tr['tagline'], 'desc' => $tr['description'], 'skills' => lines($tr['skills']), 'modules' => $tracks[(int)$tr['id']] ?? []];
    $lessons = [];
    foreach (rows('SELECT slug, title, body FROM ' . t('lessons') . ' WHERE course_id = ?', [$courseId]) as $l) $lessons[$l['slug']] = ['title' => $l['title'], 'body' => $l['body']];
    return [
        'course' => ['slug' => $c['slug'], 'title' => $c['title'], 'brand' => $c['brand'], 'brandSub' => $c['brand_sub'], 'logo' => $c['logo'], 'emoji' => $c['emoji'],
            'category' => $c['category'], 'description' => $c['description'], 'access' => $c['access'], 'priceLabel' => $c['price_label'],
            'certTitle' => $c['cert_title'], 'certSub' => $c['cert_sub'], 'commonSkills' => lines($c['common_skills']), 'storageKey' => $c['storage_key'], 'moduleBase' => (int)$c['module_base'],
            'common' => $common, 'tracks' => (object)$tracksOut],
        'home_html' => $c['home_html'],
        'lessons' => $lessons,
    ];
}

/* Импорт из файла экспорта (JSON одним файлом) */
function import_course_json(array $pack, string $slug): int {
    $meta = $pack['course'] ?? null;
    if (!is_array($meta) || empty($meta['title'])) throw new RuntimeException('В файле нет описания курса.');
    if (!slug_free($slug)) throw new RuntimeException('Адрес «' . $slug . '» уже занят или недопустим.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO ' . t('courses') . ' (slug, title, brand, brand_sub, logo, emoji, category, description, access, price_label, home_html, cert_title, cert_sub, common_skills, storage_key, module_base, is_published, position, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)', [
            $slug, (string)$meta['title'], (string)($meta['brand'] ?? ''), (string)($meta['brandSub'] ?? ''), mb_substr((string)($meta['logo'] ?? ''), 0, 8), (string)($meta['emoji'] ?? '📘'),
            (string)($meta['category'] ?? ''), (string)($meta['description'] ?? ''), in_array($meta['access'] ?? 'open', ['open', 'login', 'closed'], true) ? $meta['access'] : 'open',
            (string)($meta['priceLabel'] ?? 'Бесплатно'), (string)($pack['home_html'] ?? ''), (string)($meta['certTitle'] ?? ''), (string)($meta['certSub'] ?? ''),
            implode("\n", (array)($meta['commonSkills'] ?? [])), '', (int)($meta['moduleBase'] ?? 1) === 0 ? 0 : 1, (int)val('SELECT COALESCE(MAX(position), 0) + 1 FROM ' . t('courses')), now(), now()]);
        $courseId = last_id();
        $ids = [];
        $ensure = function (array $l) use ($courseId, $pack, &$ids): int {
            $sl = (string)$l['id'];
            if (!preg_match('~^[a-z0-9][a-z0-9-]{0,95}$~', $sl)) throw new RuntimeException('Недопустимый адрес урока: ' . $sl);
            if (isset($ids[$sl])) return $ids[$sl];
            $body = (string)($pack['lessons'][$sl]['body'] ?? '<p class="lead">Текст урока ещё не написан.</p>');
            q('INSERT INTO ' . t('lessons') . ' (course_id, slug, title, minutes, is_control, is_preview, body, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [$courseId, $sl, (string)($l['title'] ?? $sl), (int)($l['minutes'] ?? 5), !empty($l['control']) ? 1 : 0, !empty($l['preview']) ? 1 : 0, $body, now()]);
            return $ids[$sl] = last_id();
        };
        $mods = function (array $list, ?int $trackId) use ($courseId, $ensure) {
            foreach (array_values($list) as $mi => $m) {
                q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, ?, ?, ?)', [$courseId, $trackId, (string)$m['title'], $mi]);
                $mid = last_id();
                foreach (array_values($m['lessons'] ?? []) as $li => $l) q('INSERT IGNORE INTO ' . t('module_lessons') . ' (module_id, lesson_id, position) VALUES (?, ?, ?)', [$mid, $ensure($l), $li]);
            }
        };
        $mods((array)($meta['common'] ?? []), null);
        $ti = 0;
        foreach ((array)($meta['tracks'] ?? []) as $key => $tr) {
            $ts = (string)($tr['id'] ?? $key);
            if (!valid_slug($ts)) throw new RuntimeException('Недопустимый адрес пути: ' . $ts);
            q('INSERT INTO ' . t('tracks') . ' (course_id, slug, name, emoji, tagline, description, skills, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [$courseId, $ts, (string)$tr['name'], (string)($tr['emoji'] ?? ''), (string)($tr['tagline'] ?? ''), (string)($tr['desc'] ?? ''), implode("\n", (array)($tr['skills'] ?? [])), $ti++]);
            $mods((array)($tr['modules'] ?? []), last_id());
        }
        $pdo->commit();
        return $courseId;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
