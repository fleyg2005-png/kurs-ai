<?php
/* Установка: работает, только пока нет config.php. Создаёт таблицы, администратора,
   загружает курс из content/ и записывает config.php. */
defined('APP') || exit;

function installer_page(string $body): void {
    echo head_html('Установка курсов');
    echo '<body class="site"><main class="site-main narrow">' . $body . '</main></body></html>';
}

function installer_run(): void {
    require_once APP_DIR . '/migrations.php';
    start_session();
    $path = trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', strlen(base_path())), '/');
    if ($path !== '' && $path !== 'install') { header('Location: ' . url('install')); exit; }

    $v = ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'db_prefix' => 'kurs_',
          'site_name' => 'Курсы', 'admin_name' => '', 'admin_email' => '', 'admin_pass' => '', 'import' => '1'];
    $err = ''; $configText = '';
    if (is_post()) {
        if (!csrf_ok()) $err = 'Сессия устарела, отправьте форму ещё раз.';
        foreach ($v as $k => $_) if (isset($_POST[$k])) $v[$k] = trim((string)$_POST[$k]);
        $v['import'] = isset($_POST['import']) ? '1' : '';
        if (!$err && !preg_match('~^[a-z0-9_]{0,20}$~', $v['db_prefix'])) $err = 'Префикс таблиц: латиница, цифры и «_», до 20 символов.';
        if (!$err && ($v['admin_name'] === '' || !filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL))) $err = 'Укажите имя и почту администратора.';
        if (!$err && mb_strlen((string)$_POST['admin_pass']) < PASSWORD_MIN) $err = 'Пароль администратора — не короче ' . PASSWORD_MIN . ' символов.';
        if (!$err) {
            try {
                $pdo = db_connect($v['db_host'], (int)$v['db_port'], $v['db_name'], $v['db_user'], (string)$_POST['db_pass']);
                $cfg = ['db' => ['host' => $v['db_host'], 'port' => (int)$v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => (string)$_POST['db_pass'], 'prefix' => $v['db_prefix']],
                        'secret' => bin2hex(random_bytes(32))];
                $schema = str_replace('{p}', $v['db_prefix'], (string)file_get_contents(ROOT_DIR . '/database/schema.sql'));
                foreach (array_filter(array_map('trim', explode(';', preg_replace('~^--.*$~m', '', $schema)))) as $sql) $pdo->exec($sql);
                $configText = "<?php\n/* Настройки сайта курсов. Создан установщиком " . date('Y-m-d H:i') . ". Не добавляйте в git. */\nreturn " . var_export($cfg, true) . ";\n";
                $GLOBALS['__install_cfg'] = $cfg;
                installer_finish($pdo, $cfg, $v, (string)$_POST['admin_pass']);
                if (@file_put_contents(CONFIG_FILE, $configText, LOCK_EX) === false) {
                    installer_page('<div class="card"><h1 class="mt0">Почти готово</h1><p>База настроена, но записать <code>config.php</code> в корень сайта не получилось — нет прав на запись. Создайте файл <code>config.php</code> рядом с <code>index.php</code> с таким содержимым и обновите страницу:</p><pre>' . e($configText) . '</pre></div>');
                    return;
                }
                @chmod(CONFIG_FILE, 0640);
                header('Location: ' . url('admin'));
                exit;
            } catch (Throwable $e) {
                $err = 'Не получилось: ' . $e->getMessage();
            }
        }
    }
    $f = function ($name, $label, $type = 'text', $attr = '') use ($v) { return '<label class="field"><span>' . e($label) . '</span><input type="' . $type . '" name="' . $name . '" value="' . ($type === 'password' ? '' : e($v[$name])) . '" ' . $attr . '></label>'; };
    $dirs = content_dirs_installer();
    installer_page('<div class="card"><h1 class="mt0">Установка</h1><p class="muted">Один раз: подключаем базу MySQL, создаём администратора и загружаем курс. После установки эта страница исчезнет.</p>'
        . ($err ? '<div class="flash flash-error">' . e($err) . '</div>' : '')
        . '<form method="post" class="form">' . csrf_field()
        . '<h3>База данных</h3><p class="small">Данные из панели хостинга: имя базы, пользователь, пароль. Сервер обычно localhost.</p>'
        . '<div class="form-grid">' . $f('db_host', 'Сервер', 'text', 'required') . $f('db_port', 'Порт', 'text', 'required') . '</div>'
        . $f('db_name', 'Имя базы', 'text', 'required') . '<div class="form-grid">' . $f('db_user', 'Пользователь', 'text', 'required') . $f('db_pass', 'Пароль базы', 'password') . '</div>'
        . $f('db_prefix', 'Префикс таблиц', 'text', 'pattern="[a-z0-9_]{0,20}"')
        . '<h3>Сайт и администратор</h3>' . $f('site_name', 'Название сайта', 'text', 'required')
        . '<div class="form-grid">' . $f('admin_name', 'Ваше имя', 'text', 'required') . $f('admin_email', 'Почта администратора', 'email', 'required') . '</div>'
        . $f('admin_pass', 'Пароль администратора — не короче ' . PASSWORD_MIN . ' символов', 'password', 'required minlength="' . PASSWORD_MIN . '"')
        . ($dirs ? '<label class="check"><input type="checkbox" name="import" value="1"' . ($v['import'] ? ' checked' : '') . '> <span>Загрузить готовые курсы из папки content: ' . e(implode(', ', $dirs)) . '</span></label>' : '')
        . '<button class="btn primary wide" type="submit">Установить</button></form></div>');
}

function content_dirs_installer(): array {
    $out = [];
    foreach (glob(ROOT_DIR . '/content/*/course.json') ?: [] as $f) $out[] = basename(dirname($f));
    return $out;
}

/* Администратор, настройки и импорт — через временную конфигурацию */
function installer_finish(PDO $pdo, array $cfg, array $v, string $adminPass): void {
    $p = $cfg['db']['prefix'];
    $email = mb_strtolower($v['admin_email']);
    $exists = $pdo->prepare("SELECT id FROM {$p}users WHERE email = ?"); $exists->execute([$email]);
    if ($id = $exists->fetchColumn()) {
        $pdo->prepare("UPDATE {$p}users SET role = 'admin', password_hash = ?, name = ? WHERE id = ?")->execute([password_hash($adminPass, PASSWORD_DEFAULT), $v['admin_name'], $id]);
    } else {
        $pdo->prepare("INSERT INTO {$p}users (email, name, password_hash, role, consent_at, created_at) VALUES (?, ?, ?, 'admin', ?, ?)")
            ->execute([$email, $v['admin_name'], password_hash($adminPass, PASSWORD_DEFAULT), now(), now()]);
    }
    $set = $pdo->prepare("INSERT INTO {$p}settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    foreach (['site_name' => $v['site_name'], 'site_title' => $v['site_name'], 'site_tagline' => 'Практические курсы. Бесплатные открываются сразу, без регистрации.',
              'registration' => '1', 'default_course' => 'kurs-ai', 'schema_version' => (string)SCHEMA_VERSION, 'privacy_html' => default_privacy($v['site_name'], $email)] as $k => $val) {
        $exists = $pdo->prepare("SELECT 1 FROM {$p}settings WHERE k = ?"); $exists->execute([$k]);
        if (!$exists->fetchColumn()) $set->execute([$k, $val]);
    }
    if ($v['import'] === '1') {
        installer_use_config($cfg);
        require_once APP_DIR . '/importer.php';
        foreach (content_dirs() as $dir) {
            $meta = json_decode((string)file_get_contents(ROOT_DIR . '/content/' . $dir . '/course.json'), true);
            $slug = (string)($meta['slug'] ?? $dir);
            if (!course_by_slug($slug)) import_course_dir($dir);
        }
    }
}

/* Подставить конфигурацию до записи config.php, чтобы работали общие функции базы */
function installer_use_config(array $cfg): void { $GLOBALS['__install_cfg'] = $cfg; }

function default_privacy(string $site, string $email): string {
    return '<p>Сайт «' . e($site) . '» обрабатывает персональные данные пользователей: имя и адрес электронной почты, указанные при регистрации, а также сведения о прохождении курсов.</p>'
        . '<p><b>Цель обработки</b> — предоставление доступа к курсам и сохранение прогресса обучения. Данные не передаются третьим лицам, кроме случаев, предусмотренных законом.</p>'
        . '<p><b>Срок хранения</b> — до удаления аккаунта. Удалить аккаунт и все связанные данные можно в личном кабинете или по запросу на ' . e($email) . '.</p>'
        . '<p>Регистрируясь, пользователь даёт согласие на обработку указанных данных в соответствии с Федеральным законом № 152-ФЗ «О персональных данных».</p>'
        . '<p class="small">Это шаблон. Перед запуском проверьте текст с юристом и укажите оператора данных: наименование, ИНН и адрес.</p>';
}
