/* Админка: подтверждения, автокод из названия, редактор урока, ИИ-помощник. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Подтверждение опасных действий */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (b && !window.confirm(b.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* Код (адрес) из названия, пока код не правили руками */
  var MAP = { 'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya' };
  function slugify(s) {
    return s.toLowerCase().trim().split('').map(function (c) { return MAP[c] !== undefined ? MAP[c] : c; }).join('')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64);
  }
  $$('[data-slug-source]').forEach(function (src) {
    var form = src.form; if (!form) return;
    var dst = form.elements[src.getAttribute('data-slug-source')]; if (!dst) return;
    var touched = dst.value !== '';
    dst.addEventListener('input', function () { touched = dst.value !== ''; });
    src.addEventListener('input', function () { if (!touched) dst.value = slugify(src.value); });
  });

  /* ---------- Редактор урока ---------- */
  var form = $('#lesson-form');
  if (!form) return;
  var body = $('#body');
  var state = $('#save-state');
  var previewBtn = $('#preview-btn');
  var iframe = $('#preview');
  var lessonSlug = form.getAttribute('data-slug') || 'lesson';
  var dirty = false;

  function setState(text, cls) { state.textContent = text; state.className = 'save-state muted' + (cls ? ' ' + cls : ''); }
  function markDirty() { if (!dirty) { dirty = true; setState('Есть несохранённые изменения', 'dirty'); } schedulePreview(); }
  form.addEventListener('input', markDirty);
  form.addEventListener('change', markDirty);
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* Сохранение без перезагрузки: кнопка «Сохранить» и Ctrl+S */
  var saving = false;
  function save() {
    if (saving) return;
    if (!form.reportValidity()) return;
    saving = true; setState('Сохраняю…');
    var fd = new FormData(form); fd.set('ajax', '1'); fd.set('action', 'save');
    fetch(form.getAttribute('action') || location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { throw new Error('Сервер ответил не JSON (' + r.status + ')'); }); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'не сохранилось');
        dirty = false; setState('Сохранено в ' + j.saved, 'ok');
        var t = form.elements.title.value.trim(); var h1 = $('.adm-head h1', form); if (h1 && t) h1.textContent = t;
        form.setAttribute('data-slug', form.elements.slug.value); lessonSlug = form.elements.slug.value;
      })
      .catch(function (err) { setState('Ошибка сохранения: ' + err.message + '. Попробуйте ещё раз.', 'error'); })
      .then(function () { saving = false; });
  }
  form.addEventListener('submit', function (e) {
    if (e.submitter && e.submitter === previewBtn) return;   /* предпросмотр идёт обычной отправкой в iframe */
    e.preventDefault(); save();
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 's' || e.key === 'S' || e.key === 'ы' || e.key === 'Ы')) { e.preventDefault(); save(); }
  });

  /* Предпросмотр: обновляется сам через секунду после правки, прокрутка сохраняется */
  var previewTimer = null, previewScroll = 0;
  function refreshPreview() {
    if (!previewBtn || !iframe) return;
    try { previewScroll = iframe.contentWindow ? iframe.contentWindow.scrollY : 0; } catch (e) { previewScroll = 0; }
    if (form.requestSubmit) form.requestSubmit(previewBtn); else previewBtn.click();
  }
  function schedulePreview() { clearTimeout(previewTimer); previewTimer = setTimeout(refreshPreview, 900); }
  if (iframe) {
    iframe.addEventListener('load', function () { try { if (previewScroll) iframe.contentWindow.scrollTo(0, previewScroll); } catch (e) {} });
    refreshPreview();
  }

  /* Tab в тексте — два пробела, Shift+Tab — убрать отступ */
  body.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab' || e.ctrlKey || e.metaKey || e.altKey) return;
    e.preventDefault();
    var s = body.selectionStart, en = body.selectionEnd, v = body.value;
    var ls = v.lastIndexOf('\n', s - 1) + 1;
    if (s === en && !e.shiftKey) { insertAt(s, en, '  ', 2); return; }
    var block = v.slice(ls, en);
    var out = e.shiftKey ? block.replace(/^ {1,2}/gm, '') : block.replace(/^/gm, '  ');
    insertAt(ls, en, out); body.setSelectionRange(ls, ls + out.length);
  });

  /* Вставка через insertText, чтобы работала отмена Ctrl+Z; запасной путь — setRangeText */
  function insertAt(start, end, text, caret) {
    body.focus();
    body.setSelectionRange(start, end);
    var ok = false;
    try { ok = document.execCommand('insertText', false, text); } catch (e) { ok = false; }
    if (!ok || body.value.slice(start, start + text.length) !== text) body.setRangeText(text, start, end, 'end');
    if (typeof caret === 'number') { var p = start + caret; body.setSelectionRange(p, p); }
    markDirty();
  }
  /* Уникальный data-id внутри урока: SLUG-quiz, SLUG-quiz-2… */
  function uid(kind) {
    var base = lessonSlug + '-' + kind, id = base, n = 2;
    while (body.value.indexOf('data-id="' + id + '"') !== -1) id = base + '-' + n++;
    return id;
  }

  /* Заготовки виджетов. {sel} — выделенный текст, {cur} — место курсора после вставки */
  var SNIPPETS = {
    h2: function () { return '\n<h2>{sel|Название раздела}{cur}</h2>\n'; },
    lead: function () { return '<p class="lead">{sel|Коротко: о чём урок и что будет на выходе.}{cur}</p>\n'; },
    note: function () { return '<div class="note"><p>{sel|Пояснение.}{cur}</p></div>\n'; },
    tip: function () { return '<div class="tip"><p><b>Совет.</b> {sel|Как сделать быстрее или надёжнее.}{cur}</p></div>\n'; },
    warn: function () { return '<div class="warn"><p><b>Внимание.</b> {sel|Что может пойти не так и как этого избежать.}{cur}</p></div>\n'; },
    win: function () { return '<div class="win"><p><strong>Готово.</strong> {sel|Что ученик теперь умеет.}{cur}</p></div>\n'; },
    key: function () { return '<div class="key"><p>{sel|Самое важное в уроке — одна мысль.}{cur}</p></div>\n'; },
    prompt: function () { return '<div class="prompt"><div class="prompt-label">Запрос</div><pre>{sel|Текст запроса к нейросети}{cur}</pre></div>\n'; },
    quiz: function () {
      return '<h2>Проверьте себя</h2>\n<div class="quiz" data-id="' + uid('quiz') + '">\n  <div class="q">\n    <p class="q-text">{cur}Вопрос?</p>\n    <ul class="q-options">\n      <li>Неверный вариант</li>\n      <li data-correct>Верный вариант</li>\n      <li>Неверный вариант</li>\n    </ul>\n    <p class="q-explain">Почему верный ответ верный.</p>\n  </div>\n</div>\n';
    },
    checklist: function () {
      return '<h2>Практика</h2>\n<p>{cur}Задание на реальной рабочей задаче.</p>\n<ul class="checklist" data-id="' + uid('practice') + '">\n  <li>Первое проверяемое действие.</li>\n  <li>Второе проверяемое действие.</li>\n  <li>Результат сохранён.</li>\n</ul>\n';
    },
    stepper: function () {
      return '<div class="stepper" data-id="' + uid('steps') + '">\n  <section class="step" data-title="{cur}Первый шаг" data-hint="Как понять, что шаг сделан">\n    <p>Что сделать.</p>\n  </section>\n  <section class="step" data-title="Второй шаг" data-hint="Как понять, что шаг сделан">\n    <p>Что сделать.</p>\n  </section>\n</div>\n';
    },
    reveal: function () { return '<div class="reveal" data-label="Показать ответ">\n  <div class="reveal-q"><p>{sel|Вопрос или задание}{cur}</p></div>\n  <div class="reveal-a"><p>Ответ.</p></div>\n</div>\n'; },
    compare: function () { return '<div class="compare">\n  <div class="bad"><h4>Так не надо</h4><p>{cur}Пример и почему плохо.</p></div>\n  <div class="good"><h4>Так надо</h4><p>Пример и почему хорошо.</p></div>\n</div>\n'; },
    tabs: function () { return '<div class="tabs">\n  <div class="tab" data-tab="{cur}Вариант 1">\n    <p>Содержимое.</p>\n  </div>\n  <div class="tab" data-tab="Вариант 2">\n    <p>Содержимое.</p>\n  </div>\n</div>\n'; },
    mock: function () { return '<div class="mock" data-title="{cur}example.com">\n  <div class="mock-body">\n    <div class="mock-field"><label>Поле</label><div class="mock-input">значение</div></div>\n    <div class="mock-row"><span class="mock-btn">Отмена</span><span class="mock-btn primary">Готово</span></div>\n  </div>\n</div>\n'; },
    table: function () { return '<div class="table-wrap">\n  <table>\n    <thead><tr><th>{cur}Колонка</th><th>Колонка</th></tr></thead>\n    <tbody>\n      <tr><td>…</td><td>…</td></tr>\n      <tr><td>…</td><td>…</td></tr>\n    </tbody>\n  </table>\n</div>\n'; },
    flow: function () { return '<div class="flow"><span class="node">{cur}Шаг</span><span class="arrow">→</span><span class="node hl">Ключевой шаг</span><span class="arrow">→</span><span class="node">Результат</span></div>\n'; },
    steps: function () { return '<ol class="steps">\n  <li><p>{sel|Первый шаг.}{cur}</p></li>\n  <li><p>Второй шаг.</p></li>\n</ol>\n'; },
    task: function () { return '<div class="task-box">\n  <h3>Задание</h3>\n  <ol>\n    <li>{cur}Что сделать.</li>\n    <li>Что сдать.</li>\n  </ol>\n</div>\n'; }
  };
  $$('[data-snippet]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = SNIPPETS[b.getAttribute('data-snippet')]; if (!f) return;
      var s = body.selectionStart, en = body.selectionEnd, sel = body.value.slice(s, en);
      var t = f().replace(/\{sel\|([^}]*)\}/g, function (_, d) { return sel || d; });
      var caret = t.indexOf('{cur}'); t = t.replace('{cur}', '');
      /* Блок начинается с новой строки */
      if (s > 0 && body.value.charAt(s - 1) !== '\n' && t.charAt(0) !== '\n') { t = '\n' + t; if (caret >= 0) caret++; }
      insertAt(s, en, t, caret >= 0 ? caret : t.length);
    });
  });

  /* Скрыть или показать предпросмотр */
  var split = $('.editor-split'), tb = $('#toolbar');
  if (split && tb) {
    var tgl = document.createElement('button'); tgl.type = 'button'; tgl.className = 'btn tiny ghost'; tgl.style.marginLeft = 'auto'; tgl.textContent = '⇆ Предпросмотр';
    try { if (localStorage.getItem('adm:wide') === '1') split.classList.add('wide-editor'); } catch (e) {}
    tgl.addEventListener('click', function () {
      split.classList.toggle('wide-editor');
      try { localStorage.setItem('adm:wide', split.classList.contains('wide-editor') ? '1' : '0'); } catch (e) {}
      if (!split.classList.contains('wide-editor')) refreshPreview();
    });
    tb.appendChild(tgl);
  }

  /* ---------- ИИ-помощник ---------- */
  var panel = $('#ai-panel');
  if (!panel || !$('[data-ai]', panel)) return;
  var status = $('#ai-status'), result = $('#ai-result'), out = $('#ai-out'), promptEl = $('#ai-prompt');
  var insertBtn = $('#ai-insert'), appendBtn = $('#ai-append'), replaceBtn = $('#ai-replace');
  var lastRange = null, lastAction = '', lastHtml = '', controller = null, activeBtn = null, activeLabel = '';

  function aiStatus(text, cls) { status.textContent = text; status.className = 'muted small' + (cls ? ' ' + cls : ''); }
  function finish() {
    if (activeBtn) activeBtn.textContent = activeLabel;
    $$('[data-ai]', panel).forEach(function (b) { b.disabled = false; });
    controller = null; activeBtn = null;
  }

  $$('[data-ai]', panel).forEach(function (b) {
    b.addEventListener('click', function () {
      if (controller && activeBtn === b) { controller.abort(); return; }   /* повторное нажатие — остановить */
      if (controller) return;
      var action = b.getAttribute('data-ai');
      var s = body.selectionStart, en = body.selectionEnd;
      var selection = body.value.slice(s, en);
      if (action === 'improve' && !selection.trim()) { aiStatus('Выделите в тексте урока фрагмент, который нужно улучшить.', 'error'); body.focus(); return; }
      if (action === 'free' && !promptEl.value.trim()) { aiStatus('Напишите запрос в поле выше.', 'error'); promptEl.focus(); return; }
      lastRange = { start: s, end: en }; lastAction = action; lastHtml = '';
      controller = new AbortController(); activeBtn = b; activeLabel = b.textContent;
      $$('[data-ai]', panel).forEach(function (x) { if (x !== b) x.disabled = true; });
      b.textContent = '■ Остановить';
      out.textContent = ''; result.hidden = false; insertBtn.disabled = appendBtn.disabled = replaceBtn.disabled = true;
      insertBtn.textContent = action === 'improve' ? 'Заменить выделенное' : 'Вставить в курсор';
      aiStatus('Нейросеть думает…', 'busy');
      var started = Date.now(), gotText = false, finalMsg = null, errMsg = null;
      var tick = setInterval(function () { if (!gotText) aiStatus('Нейросеть думает… ' + Math.round((Date.now() - started) / 1000) + ' с', 'busy'); }, 1000);

      fetch(panel.getAttribute('data-endpoint'), {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': panel.getAttribute('data-csrf') },
        body: JSON.stringify({ action: action, prompt: promptEl.value, body: body.value, selection: selection, lesson: Number(panel.getAttribute('data-lesson')) })
      }).then(function (r) {
        var ct = r.headers.get('Content-Type') || '';
        if (ct.indexOf('ndjson') === -1) return r.json().then(function (j) { throw new Error(j.error || ('Ошибка ' + r.status)); }, function () { throw new Error('Сервер ответил ошибкой ' + r.status); });
        var reader = r.body.getReader(), dec = new TextDecoder(), buf = '';
        function line(l) {
          if (!l.trim()) return;
          var m; try { m = JSON.parse(l); } catch (e) { return; }
          if (m.d) { if (!gotText) { gotText = true; aiStatus('Пишет…', 'busy'); } out.textContent += m.d; out.scrollTop = out.scrollHeight; }
          if (m.error) errMsg = m.error;
          if (m.done) finalMsg = m;
        }
        function pump() {
          return reader.read().then(function (x) {
            if (x.done) { buf += dec.decode(); line(buf); return; }
            buf += dec.decode(x.value, { stream: true });
            var parts = buf.split('\n'); buf = parts.pop();
            parts.forEach(line);
            return pump();
          });
        }
        return pump();
      }).then(function () {
        if (errMsg) throw new Error(errMsg);
        if (!finalMsg) throw new Error('Ответ оборвался. Попробуйте ещё раз.');
        lastHtml = finalMsg.html || ''; out.textContent = lastHtml;
        insertBtn.disabled = appendBtn.disabled = replaceBtn.disabled = !lastHtml.trim();
        var secs = Math.round((Date.now() - started) / 1000);
        aiStatus('Готово за ' + secs + ' с' + (finalMsg.tokens ? ' · ' + finalMsg.tokens + ' токенов' : '') + (finalMsg.truncated ? '. Ответ упёрся в лимит длины — проверьте конец текста.' : '. Проверьте текст перед вставкой.'), finalMsg.truncated ? 'error' : '');
      }).catch(function (err) {
        if (err.name === 'AbortError') { aiStatus('Остановлено.'); lastHtml = out.textContent; insertBtn.disabled = appendBtn.disabled = replaceBtn.disabled = !lastHtml.trim(); }
        else aiStatus(err.message, 'error');
      }).then(function () { clearInterval(tick); finish(); });
    });
  });

  insertBtn.addEventListener('click', function () {
    if (!lastHtml) return;
    var r = lastRange || { start: body.selectionStart, end: body.selectionEnd };
    var end = lastAction === 'improve' ? r.end : r.start;
    insertAt(r.start, Math.min(end, body.value.length), lastHtml);
    aiStatus('Вставлено. Не забудьте сохранить: Ctrl+S.');
  });
  appendBtn.addEventListener('click', function () {
    if (!lastHtml) return;
    var v = body.value.replace(/\s*$/, '');
    insertAt(v.length, body.value.length, (v ? '\n\n' : '') + lastHtml);
    body.scrollTop = body.scrollHeight;
    aiStatus('Добавлено в конец урока. Не забудьте сохранить: Ctrl+S.');
  });
  replaceBtn.addEventListener('click', function () {
    if (!lastHtml || !window.confirm('Заменить весь текст урока ответом нейросети? До сохранения можно отменить через Ctrl+Z.')) return;
    insertAt(0, body.value.length, lastHtml);
    aiStatus('Текст урока заменён. Не забудьте сохранить: Ctrl+S.');
  });
})();
