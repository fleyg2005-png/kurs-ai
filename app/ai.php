<?php
/* ИИ-помощник редактора: пишет черновики уроков, квизы и чеклисты в разметке курса.
   Без SDK, через curl: хостинг ставится git pull без composer. Ответ идёт потоком,
   чтобы долгий запрос не обрывался по таймауту прокси хостинга. */
defined('APP') || exit;

const AI_DEFAULT_MODEL = 'claude-opus-5-5';
const AI_API_VERSION = '2026-10-21';
const AI_ACTIONS = ['draft', 'quiz', 'checklist', 'improve', 'free'];

final class AiRequestError extends RuntimeException {
    public int $status; public bool $streamed;
    public function __construct(string $msg, int $status = 0, bool $streamed = false) { parent::__construct($msg); $this->status = $status; $this->streamed = $streamed; }
}

function ai_config(): array {
    $provider = setting('ai_provider', 'anthropic') === 'openai' ? 'openai' : 'anthropic';
    return [
        'provider' => $provider,
        'base' => trim((string)setting('ai_base_url', '')),
        'key' => (string)setting('ai_key', ''),
        'model' => trim((string)setting('ai_model', '')) ?: AI_DEFAULT_MODEL,
    ];
}

function ai_ready(): bool { return function_exists('curl_init') && setting('ai_key', '') !== ''; }

/* Адрес запроса: пустое поле — официальный API; «…/v1» в конце можно указывать и не указывать */
function ai_endpoint(array $cfg): string {
    $base = rtrim($cfg['base'] !== '' ? $cfg['base'] : ($cfg['provider'] === 'openai' ? 'https://api.openai.com' : 'https://api.anthropic.com'), '/');
    $base = preg_replace('~/v1$~', '', $base);
    return $base . ($cfg['provider'] === 'openai' ? '/v1/chat/completions' : '/v1/messages');
}

function ai_system_prompt(): string {
    return <<<'TXT'
Ты пишешь уроки для онлайн-курса на русском языке. Ученики — взрослые специалисты без технической подготовки. Каждый урок решает одну практическую задачу: после него человек может что-то сделать руками.

Стиль: обращение на «вы», короткие абзацы, конкретные примеры из работы вместо общих слов, без канцелярита и без воды. Каждое утверждение должно быть проверяемым. Не выдумывай цены, даты, версии программ и названия кнопок, в которых не уверен: лучше напиши, где это посмотреть.

Ответ — только HTML-фрагмент тела урока. Без ```, без <html>, <body>, <article>, без <h1> (заголовок урока задаётся отдельно), без <script>, <style> и атрибутов style и on*. Обычные теги: h2, h3, p, ul, ol, li, b, i, code, a, blockquote, table. Внешние ссылки — с target="_blank" rel="noopener".

Виджеты курса (используй точно эту разметку, движок оживляет её сам):

Вводный абзац в начале урока:
<p class="lead">…</p>

Выноски: <div class="note"><p>…</p></div> — пояснение; <div class="tip"><p>…</p></div> — совет; <div class="warn"><p>…</p></div> — предупреждение; <div class="key"><p>…</p></div> — самое важное в уроке, не больше одной на урок; <div class="win"><p><strong>Готово.</strong> …</p></div> — итог в конце урока.

Готовый запрос к нейросети, который ученик копирует кнопкой:
<div class="prompt"><div class="prompt-label">Запрос</div><pre>текст запроса</pre></div>

Квиз (2–4 вопроса, у каждого ровно один верный вариант с data-correct; неверные варианты правдоподобные; пояснение объясняет, почему верный ответ верный):
<div class="quiz" data-id="SLUG-quiz">
  <div class="q">
    <p class="q-text">Вопрос?</p>
    <ul class="q-options"><li>Вариант</li><li data-correct>Верный вариант</li><li>Вариант</li></ul>
    <p class="q-explain">Пояснение.</p>
  </div>
</div>

Чеклист практики (3–5 пунктов, каждый — проверяемое действие ученика, а не «я понял»):
<ul class="checklist" data-id="SLUG-practice"><li>…</li></ul>

Пошаговая инструкция с прогрессом:
<div class="stepper" data-id="SLUG-steps">
  <section class="step" data-title="Короткое название шага" data-hint="Как понять, что шаг сделан"><p>…</p></section>
</div>

Простой нумерованный список шагов: <ol class="steps"><li><p>…</p></li></ol>

Ответ по кнопке: <div class="reveal" data-label="Показать ответ"><div class="reveal-q"><p>Вопрос или задание</p></div><div class="reveal-a"><p>Ответ</p></div></div>

Вкладки: <div class="tabs"><div class="tab" data-tab="Название">…</div><div class="tab" data-tab="Название">…</div></div>

Плохо и хорошо: <div class="compare"><div class="bad"><h4>Так не надо</h4><p>…</p></div><div class="good"><h4>Так надо</h4><p>…</p></div></div>

Схема экрана программы: <div class="mock" data-title="адрес или название окна"><div class="mock-body"><div class="mock-row"><span class="mock-btn">Кнопка</span><span class="mock-btn primary">Главная кнопка</span></div><div class="mock-field"><label>Поле</label><div class="mock-input">значение</div></div></div></div>

Цепочка действий: <div class="flow"><span class="node">Шаг</span><span class="arrow">→</span><span class="node hl">Ключевой шаг</span></div>

Таблица: <div class="table-wrap"><table><thead><tr><th>…</th></tr></thead><tbody><tr><td>…</td></tr></tbody></table></div>

Задание контрольной работы: <div class="task-box"><h3>Задание</h3><ol><li>…</li></ol></div>

Термин с подсказкой: <dfn data-def="Короткое определение">термин</dfn>

Вместо SLUG подставляй адрес урока, который дан в запросе. data-id внутри урока не повторяются.
TXT;
}

/* Текст запроса для конкретной кнопки */
function ai_user_message(string $action, string $prompt, string $body, string $selection, ?array $lesson, ?array $course): string {
    $ctx = '';
    if ($course) $ctx .= 'Курс: «' . $course['title'] . '»' . (trim((string)$course['description']) !== '' ? ' — ' . trim((string)$course['description']) : '') . ".\n";
    if ($lesson) $ctx .= 'Урок: «' . $lesson['title'] . '», адрес урока (SLUG): ' . $lesson['slug'] . ".\n";
    $wish = trim($prompt) !== '' ? "\nПожелания автора:\n" . trim($prompt) . "\n" : '';
    $cur = trim($body) !== '' ? "\nТекущий текст урока:\n<lesson>\n" . $body . "\n</lesson>\n" : '';
    switch ($action) {
        case 'draft':
            return $ctx . "\nНапиши урок целиком: вводный абзац, 3–5 разделов с h2, где нужно — виджеты (запрос, шаги, схема экрана, плохо/хорошо), в конце раздел «Проверьте себя» с квизом, раздел «Практика» с чеклистом и итог в блоке win. Объём — на 10–15 минут чтения."
                . ($wish ?: "\nТема — по названию урока.\n") . ($cur ? $cur . "\nЕсли в текущем тексте есть полезное, сохрани и доработай это.\n" : '');
        case 'quiz':
            return $ctx . "\nСоставь квиз на 3 вопроса по тексту урока: проверяй понимание и применение, а не запоминание формулировок. Верни только блок квиза с заголовком <h2>Проверьте себя</h2> перед ним." . $wish . $cur;
        case 'checklist':
            return $ctx . "\nСоставь раздел практики по тексту урока: <h2>Практика</h2>, абзац с заданием на реальной рабочей задаче ученика и чеклист на 3–5 проверяемых пунктов. Верни только этот раздел." . $wish . $cur;
        case 'improve':
            return $ctx . "\nУлучши фрагмент урока: точнее, короче, понятнее, с конкретным примером, если его не хватает. Сохрани смысл, виджеты и их data-id. Верни только новый вариант фрагмента."
                . "\nФрагмент:\n<fragment>\n" . $selection . "\n</fragment>\n" . $wish . ($cur ? "\nВесь урок для контекста:\n<lesson>\n" . $body . "\n</lesson>\n" : '');
        default:
            return $ctx . "\nЗапрос автора:\n" . trim($prompt) . "\n" . ($selection !== '' ? "\nВыделенный фрагмент:\n<fragment>\n" . $selection . "\n</fragment>\n" : '') . $cur
                . "\nВерни HTML-фрагмент для вставки в урок.";
    }
}

/* Главная функция: собрать запрос, получить ответ потоком, вернуть очищенный HTML.
   $onDelta получает кусочки текста по мере генерации, $onPing — сигнал «модель ещё думает». */
function ai_lesson_action(string $action, string $prompt, string $body, string $selection, ?array $lesson, ?array $course, ?callable $onDelta = null, ?callable $onPing = null): array {
    if (!ai_ready()) throw new RuntimeException('Нейросеть не подключена: укажите ключ в настройках.');
    if (!in_array($action, AI_ACTIONS, true)) $action = 'free';
    if ($action === 'improve' && trim($selection) === '') throw new RuntimeException('Выделите фрагмент в тексте урока, который нужно улучшить.');
    if ($action === 'free' && trim($prompt) === '') throw new RuntimeException('Напишите запрос в поле над кнопками.');
    if (in_array($action, ['quiz', 'checklist'], true) && trim($body) === '') throw new RuntimeException('Урок пока пустой: квиз и практика собираются по его тексту.');
    $cfg = ai_config();
    $res = ai_complete($cfg, ai_system_prompt(), ai_user_message($action, $prompt, $body, $selection, $lesson, $course),
        $action === 'draft' ? 32000 : 16000, $action === 'draft' ? 'high' : 'medium', $onDelta, $onPing);
    $res['html'] = ai_clean_html($res['text']);
    return $res;
}

/* Модели Claude, которые принимают effort и fallbacks: "default" */
function ai_supports_effort(string $model): bool { return (bool)preg_match('~claude-(fable|mythos|opus-(5|4-[5-9])|sonnet-(5|4-6)|haiku-5)~', $model); }
function ai_supports_fallbacks(string $model): bool { return (bool)preg_match('~claude-(fable-5-1|opus-5-5|opus-5(?![-\d])|sonnet-5-5)~', $model); }

function ai_complete(array $cfg, string $system, string $user, int $maxTokens, string $effort, ?callable $onDelta, ?callable $onPing): array {
    if ($cfg['provider'] === 'openai') {
        $payload = ['model' => $cfg['model'], 'stream' => true, 'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]]];
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']];
        return ai_stream(ai_endpoint($cfg), $headers, $payload, 'openai', $onDelta, $onPing);
    }
    $payload = ['model' => $cfg['model'], 'max_tokens' => $maxTokens, 'stream' => true, 'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]]];
    if (ai_supports_effort($cfg['model'])) $payload['output_config'] = ['effort' => $effort];
    $headers = ['Content-Type: application/json', 'x-api-key: ' . $cfg['key'], 'anthropic-version: ' . AI_API_VERSION];
    /* Часть прокси и агрегаторов ждёт ключ в Authorization — официальному API его не шлём */
    if ($cfg['base'] !== '' && !preg_match('~^https://api\.anthropic\.com~i', $cfg['base'])) $headers[] = 'Authorization: Bearer ' . $cfg['key'];
    /* При отказе фильтра безопасности API само повторит запрос на подходящей модели */
    if (ai_supports_fallbacks($cfg['model'])) {
        try {
            return ai_stream(ai_endpoint($cfg), array_merge($headers, ['anthropic-beta: server-side-fallback-2026-07-01']), $payload + ['fallbacks' => 'default'], 'anthropic', $onDelta, $onPing);
        } catch (AiRequestError $e) {
            /* Прокси или аккаунт не знает про fallbacks — повторяем без них, пока ничего не выведено */
            if ($e->status !== 400 || $e->streamed || !preg_match('~fallback|beta~i', $e->getMessage())) throw $e;
        }
    }
    return ai_stream(ai_endpoint($cfg), $headers, $payload, 'anthropic', $onDelta, $onPing);
}

/* Потоковый запрос (SSE). Возвращает ['text', 'stop', 'model', 'usage'] */
function ai_stream(string $url, array $headers, array $payload, string $format, ?callable $onDelta, ?callable $onPing): array {
    $st = ['status' => 0, 'buf' => '', 'raw' => '', 'text' => '', 'stop' => '', 'model' => '', 'usage' => [], 'error' => '', 'lastPing' => 0];
    $emit = function (string $t) use (&$st, $onDelta) { if ($t === '') return; $st['text'] .= $t; if ($onDelta) $onDelta($t); };
    $ping = function () use (&$st, $onPing) { if ($onPing && time() - $st['lastPing'] >= 5) { $st['lastPing'] = time(); $onPing(); } };
    $handle = function (string $event, array $d) use (&$st, $emit, $format) {
        if ($format === 'openai') {
            if (isset($d['error'])) { $st['error'] = (string)($d['error']['message'] ?? 'ошибка'); return; }
            if (!empty($d['model'])) $st['model'] = (string)$d['model'];
            foreach ($d['choices'] ?? [] as $ch) {
                if (isset($ch['delta']['content']) && is_string($ch['delta']['content'])) $emit($ch['delta']['content']);
                if (!empty($ch['finish_reason'])) $st['stop'] = (string)$ch['finish_reason'];
            }
            if (!empty($d['usage'])) $st['usage'] = $d['usage'];
            return;
        }
        $type = $d['type'] ?? $event;
        if ($type === 'message_start') { $st['model'] = (string)($d['message']['model'] ?? ''); $st['usage'] = $d['message']['usage'] ?? []; }
        elseif ($type === 'content_block_delta' && ($d['delta']['type'] ?? '') === 'text_delta') $emit((string)$d['delta']['text']);
        elseif ($type === 'message_delta') { $st['stop'] = (string)($d['delta']['stop_reason'] ?? $st['stop']); if (!empty($d['usage'])) $st['usage'] = array_merge($st['usage'], $d['usage']); }
        elseif ($type === 'error') $st['error'] = (string)($d['error']['message'] ?? 'ошибка API');
    };
    $parse = function () use (&$st, $handle) {
        /* События разделены пустой строкой; внутри — строки event: и data: */
        while (preg_match("~\r?\n\r?\n~", $st['buf'], $m, PREG_OFFSET_CAPTURE)) {
            $block = substr($st['buf'], 0, $m[0][1]);
            $st['buf'] = substr($st['buf'], $m[0][1] + strlen($m[0][0]));
            $event = ''; $data = '';
            foreach (preg_split("~\r?\n~", $block) as $line) {
                if (str_starts_with($line, 'event:')) $event = trim(substr($line, 6));
                elseif (str_starts_with($line, 'data:')) $data .= ltrim(substr($line, 5));
            }
            if ($data === '' || $data === '[DONE]') continue;
            $d = json_decode($data, true);
            if (is_array($d)) $handle($event, $d);
        }
    };
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => array_merge($headers, ['Accept: text/event-stream']),
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 900,
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$st) { if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $st['status'] = (int)$m[1]; return strlen($h); },
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$st, $parse, $ping) {
            if (connection_aborted()) return 0;   /* автор закрыл страницу — не тратим токены дальше */
            if ($st['status'] >= 400) { if (strlen($st['raw']) < 65536) $st['raw'] .= $chunk; return strlen($chunk); }
            $st['buf'] .= $chunk; $parse(); $ping();
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $errNo = curl_errno($ch); $errMsg = curl_error($ch);
    curl_close($ch);
    $streamed = $st['text'] !== '';
    if ($st['status'] >= 400) throw new AiRequestError(ai_http_error($st['status'], $st['raw']), $st['status']);
    if ($ok === false || $errNo) {
        if ($errNo === CURLE_WRITE_ERROR && connection_aborted()) throw new AiRequestError('Запрос остановлен.', 0, $streamed);
        throw new AiRequestError($errNo === CURLE_OPERATION_TIMEDOUT ? 'Нейросеть не ответила вовремя. Попробуйте ещё раз или сократите запрос.'
            : 'Не удалось связаться с API (' . $errMsg . '). Проверьте адрес API: с хостинга в России официальные адреса часто недоступны.', 0, $streamed);
    }
    if ($st['buf'] !== '') { $st['buf'] .= "\n\n"; $parse(); }
    if ($st['error'] !== '') throw new AiRequestError('Ошибка API: ' . $st['error'], 0, $streamed);
    if ($st['stop'] === 'refusal') throw new AiRequestError('Нейросеть отказалась отвечать на этот запрос. Переформулируйте его.', 0, $streamed);
    if ($st['text'] === '' && $st['status'] === 200 && $st['stop'] === '') throw new AiRequestError('API вернул пустой ответ. Проверьте адрес API и формат.', 0, false);
    return ['text' => $st['text'], 'stop' => $st['stop'], 'model' => $st['model'], 'usage' => $st['usage'],
            'truncated' => in_array($st['stop'], ['max_tokens', 'length'], true)];
}

function ai_http_error(int $status, string $raw): string {
    $d = json_decode($raw, true);
    $msg = is_array($d) ? (string)($d['error']['message'] ?? $d['message'] ?? '') : '';
    $hint = [401 => 'Ключ API не подходит.', 403 => 'Доступ запрещён: проверьте ключ и права аккаунта.', 404 => 'Адрес API или модель не найдены: проверьте оба поля в настройках.',
             429 => 'Слишком много запросов или закончился лимит. Подождите минуту.', 529 => 'Сервис перегружен. Попробуйте через минуту.'][$status] ?? '';
    if ($hint === '' && $status >= 500) $hint = 'Сбой на стороне сервиса. Попробуйте ещё раз.';
    return trim('Ошибка ' . $status . '. ' . $hint . ($msg !== '' ? ' Ответ API: ' . mb_substr($msg, 0, 300) : ''));
}

/* Убрать обёртки ``` и опасное: скрипты, стили, обработчики событий, javascript:-ссылки */
function ai_clean_html(string $s): string {
    $s = trim($s);
    $s = preg_replace('~^```[a-zA-Z]*\s*\n?|\n?```\s*$~', '', $s);
    $s = preg_replace('~<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>~is', '', $s);
    $s = preg_replace('~<(script|style|iframe|object|embed|link|meta|base)\b[^>]*/?>~i', '', $s);
    $s = preg_replace('~\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $s);
    $s = preg_replace('~\s+style\s*=\s*("[^"]*"|\'[^\']*\')~i', '', $s);
    $s = preg_replace('~(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2~i', '$1="#"', $s);
    $s = preg_replace('~</?(html|body|article|main)\b[^>]*>~i', '', $s);
    return trim((string)$s) . "\n";
}
