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

/* Урок из папки курса: текст из файла, заголовок и минуты из course.json */
function content_lesson(string $dir, array $l): array {
    $file = $dir . '/lessons/' . basename((string)$l['id']) . '.html';
    $ex = is_file($file) ? extract_lesson((string)file_get_contents($file)) : ['title' => '', 'body' => '<p class="lead">Текст урока ещё не написан.</p>' . "\n"];
    return ['title' => (string)($l['title'] ?? $ex['title']), 'minutes' => (int)($l['minutes'] ?? 5), 'control' => !empty($l['control']) ? 1 : 0,
            'preview' => !empty($l['preview']) ? 1 : 0, 'body' => $ex['body']];
}

/* Отпечаток урока: меняется, если урок поправили в админке */
function lesson_hash(string $title, int $minutes, int $control, string $body): string { return sha1($title . "\n" . $minutes . "\n" . $control . "\n" . $body); }

function insert_content_lesson(int $courseId, string $slug, array $cl): int {
    q('INSERT INTO ' . t('lessons') . ' (course_id, slug, title, minutes, is_control, is_preview, body, source_hash, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [$courseId, $slug, $cl['title'], $cl['minutes'], $cl['control'], $cl['preview'], $cl['body'], lesson_hash($cl['title'], $cl['minutes'], $cl['control'], $cl['body']), now()]);
    return last_id();
}

/* Пути, модули и порядок уроков по course.json. $lessonId(урок из манифеста) возвращает id урока в базе. */
function build_structure(int $courseId, array $meta, callable $lessonId): void {
    $addModules = function (array $mods, ?int $trackId) use ($courseId, $lessonId) {
        foreach (array_values($mods) as $mi => $m) {
            q('INSERT INTO ' . t('modules') . ' (course_id, track_id, title, position) VALUES (?, ?, ?, ?)', [$courseId, $trackId, (string)$m['title'], $mi]);
            $moduleId = last_id();
            foreach (array_values($m['lessons'] ?? []) as $li => $l) {
                q('INSERT IGNORE INTO ' . t('module_lessons') . ' (module_id, lesson_id, position) VALUES (?, ?, ?)', [$moduleId, $lessonId($l), $li]);
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
}

/* Возвращает id курса или бросает исключение */
function import_course_dir(string $slugDir, ?string $newSlug = null, bool $publish = true): int {
    $dir = ROOT_DIR . '/content/' . basename($slugDir);
    $meta = json_decode((string)@file_get_contents($dir . '/course.json'), true);
    if (!is_array($meta)) throw new RuntimeException('Нет или испорчен course.json в content/' . $slugDir);
    $slug = $newSlug ?: (string)($meta['slug'] ?? $slugDir);
    if ($why = slug_problem($slug)) throw new RuntimeException($why);

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
        build_structure($courseId, $meta, function (array $l) use ($courseId, $dir, &$lessonIds): int {
            $id = (string)$l['id'];
            return $lessonIds[$id] ??= insert_content_lesson($courseId, $id, content_lesson($dir, $l));
        });
        $pdo->commit();
        set_setting(content_marker($slugDir), (string)$courseId);
        content_baseline($slugDir, $courseId);
        return $courseId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* Отметка, из какой папки content/ загружен курс: повторные сиды не создают его заново,
   даже если адрес курса потом поменяли в админке */
function content_marker(string $dir): string { return 'content_course_' . substr(basename($dir), 0, 48); }

/* Что стало с курсом из content/$dir: ['course' => курс или null, 'deleted' => удалён в админке] */
function content_import_state(string $dir): array {
    $id = (int)(val('SELECT v FROM ' . t('settings') . ' WHERE k = ?', [content_marker($dir)]) ?? 0);
    if ($id) {
        $c = course_by_id($id);
        return ['course' => $c, 'deleted' => $c === null];
    }
    $meta = json_decode((string)@file_get_contents(ROOT_DIR . '/content/' . basename($dir) . '/course.json'), true);
    $c = course_by_slug((string)($meta['slug'] ?? basename($dir)));
    if ($c) set_setting(content_marker($dir), (string)$c['id']);   /* курс из прежней установки, без отметки */
    return ['course' => $c, 'deleted' => false];
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
    if ($why = slug_problem($slug)) throw new RuntimeException($why);
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

/* ---------- Обновление курса из content/ при каждом ./deploy.sh ----------
   Уроки, которые не правили в админке, получают новый текст из файлов; новые уроки добавляются.
   Пути и модули перестраиваются по course.json, если их не меняли в админке. Правки из админки не трогаются. */

function structure_from_meta(array $meta): array {
    $mods = function ($list): array {
        $out = [];
        foreach ((array)$list as $m) $out[] = ['title' => (string)$m['title'], 'lessons' => array_map(function ($l) { return (string)$l['id']; }, array_values((array)($m['lessons'] ?? [])))];
        return $out;
    };
    $tracks = [];
    foreach ((array)($meta['tracks'] ?? []) as $key => $tr) {
        $tracks[] = ['slug' => (string)($tr['id'] ?? $key), 'name' => (string)$tr['name'], 'emoji' => (string)($tr['emoji'] ?? ''), 'tagline' => (string)($tr['tagline'] ?? ''),
                     'desc' => (string)($tr['desc'] ?? ''), 'skills' => lines(implode("\n", (array)($tr['skills'] ?? []))), 'modules' => $mods($tr['modules'] ?? [])];
    }
    return ['common' => $mods($meta['common'] ?? []), 'tracks' => $tracks];
}

function structure_from_db(int $courseId): array {
    $common = []; $byTrack = [];
    foreach (modules_of($courseId) as $m) {
        $entry = ['title' => (string)$m['title'], 'lessons' => array_map('strval', array_column(module_lessons((int)$m['id']), 'slug'))];
        if ($m['track_id'] === null) $common[] = $entry; else $byTrack[(int)$m['track_id']][] = $entry;
    }
    $tracks = [];
    foreach (tracks_of($courseId) as $tr) {
        $tracks[] = ['slug' => (string)$tr['slug'], 'name' => (string)$tr['name'], 'emoji' => (string)$tr['emoji'], 'tagline' => (string)$tr['tagline'],
                     'desc' => (string)$tr['description'], 'skills' => lines($tr['skills']), 'modules' => $byTrack[(int)$tr['id']] ?? []];
    }
    return ['common' => $common, 'tracks' => $tracks];
}

function structure_hash(array $s): string { return sha1((string)json_encode($s, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)); }

function content_key(string $what, string $dir): string { return 'content_' . $what . '_' . substr(basename($dir), 0, 40); }

/* Запомнить, какими структура и главная страница были после загрузки или обновления */
function content_baseline(string $dir, int $courseId): void {
    set_setting(content_key('struct', $dir), structure_hash(structure_from_db($courseId)));
    set_setting(content_key('home', $dir), sha1((string)val('SELECT home_html FROM ' . t('courses') . ' WHERE id = ?', [$courseId])));
}

/* Курс правили в админке? Для прежних установок без отметок — по времени: правки позже загрузки */
function edited_after_import(?string $updatedAt, array $course): bool {
    return strtotime((string)$updatedAt) > strtotime((string)$course['created_at']) + 300;
}

function sync_content(callable $log): void {
    foreach (content_dirs() as $dir) {
        $state = content_import_state($dir);
        if (!$state['course']) continue;   /* ещё не загружен — это сделают сиды */
        $c = $state['course']; $cid = (int)$c['id'];
        $path = ROOT_DIR . '/content/' . $dir;
        $meta = json_decode((string)file_get_contents($path . '/course.json'), true);
        if (!is_array($meta)) { $log('content/' . $dir . ': испорчен course.json, пропускаю'); continue; }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            /* 1. Уроки */
            $manifest = [];
            foreach (array_merge((array)($meta['common'] ?? []), ...array_map(function ($t) { return (array)($t['modules'] ?? []); }, array_values((array)($meta['tracks'] ?? [])))) as $m)
                foreach ((array)($m['lessons'] ?? []) as $l) $manifest[(string)$l['id']] ??= $l;
            $added = $updated = 0; $kept = [];
            foreach ($manifest as $slug => $l) {
                $cl = content_lesson($path, $l);
                $row = lesson_by_slug($cid, $slug);
                if (!$row) { insert_content_lesson($cid, $slug, $cl); $added++; continue; }
                $untouched = $row['source_hash'] !== null
                    ? hash_equals((string)$row['source_hash'], lesson_hash((string)$row['title'], (int)$row['minutes'], (int)$row['is_control'], (string)$row['body']))
                    : !edited_after_import($row['updated_at'], $c);
                if (!$untouched) { $kept[] = $slug; continue; }
                $newHash = lesson_hash($cl['title'], $cl['minutes'], $cl['control'], $cl['body']);
                if ($row['source_hash'] === $newHash) continue;
                q('UPDATE ' . t('lessons') . ' SET title = ?, minutes = ?, is_control = ?, body = ?, source_hash = ?, updated_at = ? WHERE id = ?',
                  [$cl['title'], $cl['minutes'], $cl['control'], $cl['body'], $newHash, now(), $row['id']]);
                if ($row['source_hash'] !== null || $row['body'] !== $cl['body'] || $row['title'] !== $cl['title'] || (int)$row['minutes'] !== $cl['minutes'] || (int)$row['is_control'] !== $cl['control']) $updated++;
            }

            /* 2. Пути и модули */
            $want = structure_hash(structure_from_meta($meta));
            $now = structure_hash(structure_from_db($cid));
            $base = val('SELECT v FROM ' . t('settings') . ' WHERE k = ?', [content_key('struct', $dir)]);
            $struct = 'без изменений';
            $structBase = $now;   /* что запомнить как «записанное из content/»; null — не менять отметку */
            if ($now !== $want) {
                if ($base === null || $base === $now) {
                    q('DELETE FROM ' . t('modules') . ' WHERE course_id = ?', [$cid]);
                    q('DELETE FROM ' . t('tracks') . ' WHERE course_id = ?', [$cid]);
                    build_structure($cid, $meta, function (array $l) use ($cid) { return (int)lesson_by_slug($cid, (string)$l['id'])['id']; });
                    $struct = 'обновлены по course.json';
                    $structBase = structure_hash(structure_from_db($cid));
                } else {
                    $struct = 'меняли в админке, не трогаю';
                    $structBase = null;
                }
            }
            if ($structBase !== null) set_setting(content_key('struct', $dir), $structBase);

            /* 3. Главная страница курса */
            $home = is_file($path . '/home.html') ? extract_home((string)file_get_contents($path . '/home.html')) : '';
            $homeBase = val('SELECT v FROM ' . t('settings') . ' WHERE k = ?', [content_key('home', $dir)]);
            $homeNow = (string)$c['home_html'];
            $homeMsg = '';
            if ($home !== '' && $homeNow !== $home) {
                $homeUntouched = $homeBase !== null ? hash_equals((string)$homeBase, sha1($homeNow)) : !edited_after_import($c['updated_at'], $c);
                if ($homeUntouched) {
                    q('UPDATE ' . t('courses') . ' SET home_html = ? WHERE id = ?', [$home, $cid]);
                    set_setting(content_key('home', $dir), sha1($home));
                    $homeMsg = ', главная обновлена';
                } else {
                    $homeMsg = ', главную правили в админке, не трогаю';
                }
            } elseif ($homeNow === $home) {
                set_setting(content_key('home', $dir), sha1($home));
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $log('курс /' . $c['slug'] . '/ из content/' . $dir . ': уроков обновлено ' . $updated . ', добавлено ' . $added
            . ($kept ? ', правили в админке и не тронуты: ' . implode(', ', array_slice($kept, 0, 8)) . (count($kept) > 8 ? '…' : '') : '')
            . '; пути и модули ' . $struct . $homeMsg);
    }
}
