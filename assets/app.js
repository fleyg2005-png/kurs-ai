/* ============================================================
   Курс по ИИ — движок сайта (без зависимостей, без сборки)
   - манифест курса (COURSE)
   - боковая навигация, верхняя панель, навигация между уроками
   - прогресс в localStorage
   - интерактивные виджеты: .stepper .quiz .checklist .reveal .tabs
     .prompt (копирование), #prompt-builder, #git-sim
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Манифест курса ----------
     Модули 0–3 общие. Дальше читатель выбирает путь: «Сайт» или «Юрист».
     Чтобы добавить урок: добавьте объект в lessons нужного модуля и создайте
     файл lessons/<id>.html (скопируйте любой урок как шаблон). Урок с одним id
     может входить в несколько путей (например, регистрация на GitHub). */
  const COMMON = [
    { id: 'm0', title: 'Старт', lessons: [
      { id: '00-start', title: 'Как устроен курс и что вы получите', minutes: 5 },
    ]},
    { id: 'm1', title: 'Минимум теории', lessons: [
      { id: '01-what-is-ai', title: 'Что такое ИИ-ассистент: 7 минут без воды', minutes: 7 },
      { id: '02-rules', title: '7 правил, без которых ИИ бесполезен', minutes: 6 },
      { id: 'm1-control', title: 'Контрольная 1: факт или выдумка', minutes: 10, control: true },
    ]},
    { id: 'm2', title: 'Какие есть модели', lessons: [
      { id: '03-claude', title: 'Claude: модели, интерфейс, тарифы', minutes: 8 },
      { id: '04-chatgpt', title: 'ChatGPT: модели, интерфейс, тарифы', minutes: 8 },
      { id: '05-choose', title: 'Что выбрать под задачу', minutes: 6 },
      { id: 'm2-control', title: 'Контрольная 2: подобрать модель и тариф', minutes: 10, control: true },
    ]},
    { id: 'm3', title: 'Как обращаться к ИИ', lessons: [
      { id: '06-prompt', title: 'Анатомия запроса + конструктор', minutes: 12 },
      { id: '07-dialog', title: 'Диалог, а не запрос', minutes: 8 },
      { id: '08-documents', title: 'Документы, таблицы, длинные тексты', minutes: 10 },
      { id: '09-verify', title: 'Проверка, галлюцинации, безопасность данных', minutes: 8 },
      { id: '10-projects', title: 'Проекты и постоянные инструкции', minutes: 6 },
      { id: 'm3-control', title: 'Контрольная 3: рабочий запрос по документу', minutes: 20, control: true },
    ]},
  ];
  const TRACKS = {
    site: {
      id: 'site', name: 'Сайт', emoji: '🌐',
      tagline: 'Свой инструмент как веб-страница',
      desc: 'GitHub без установки, редактор и терминал в браузере, ИИ-агенты пишут код, результат публикуется на GitHub Pages и открывается по ссылке. Для тех, кто хочет понимать, как устроена работа с кодом.',
      modules: [
        { id: 'm4', title: 'GitHub без установки', lessons: [
          { id: '11-why-github', title: 'Зачем GitHub не-программисту', minutes: 6 },
          { id: '12-signup', title: 'Регистрация на GitHub: шаг за шагом', minutes: 8 },
          { id: '13-first-repo', title: 'Первый репозиторий и первый коммит', minutes: 10 },
          { id: '14-pages', title: 'Публикуем сайт за 10 минут', minutes: 12 },
          { id: 'm4-control', title: 'Контрольная 4: репозиторий и сайт', minutes: 20, control: true },
        ]},
        { id: 'm5', title: 'Git из браузера', lessons: [
          { id: '15-githubdev', title: 'github.dev: нажмите точку', minutes: 8 },
          { id: '16-codespaces', title: 'Codespaces: компьютер в облаке', minutes: 10 },
          { id: '17-branches', title: 'Коммиты, ветки, pull request — на симуляторе', minutes: 12 },
          { id: '18-broken', title: 'Когда что-то сломалось', minutes: 6 },
          { id: 'm5-control', title: 'Контрольная 5: ветка и pull request', minutes: 20, control: true },
        ]},
        { id: 'm6', title: 'ИИ пишет код за вас', lessons: [
          { id: '19-agents', title: 'Агенты: что это и какие есть', minutes: 8 },
          { id: '20-claude-code', title: 'Claude Code в браузере', minutes: 12 },
          { id: '21-codex-copilot', title: 'Codex и Copilot', minutes: 10 },
          { id: '22-review', title: 'Как проверять код, не будучи программистом', minutes: 10 },
          { id: 'm6-control', title: 'Контрольная 6: задача агенту и ревью', minutes: 30, control: true },
        ]},
        { id: 'm7', title: 'От юриста до программиста', lessons: [
          { id: '23-first-tool', title: 'Проект: инструмент для своей профессии', minutes: 15 },
          { id: '24-ideas', title: 'Следующие проекты и шаблон ТЗ', minutes: 8 },
          { id: 'm7-control', title: 'Финальная контрольная: ваш инструмент в интернете', minutes: 60, control: true },
          { id: '25-finish', title: 'Финал: что дальше', minutes: 4 },
        ]},
      ],
    },
    docs: {
      id: 'docs', name: 'Юрист', emoji: '⚖️',
      tagline: 'Документы в репозитории, git делает ИИ',
      desc: 'GitHub как рабочая папка с договорами и шаблонами. Вы ставите задачи, ИИ сам пишет и запускает программы, делает коммиты и pull request, вы проверяете и принимаете. Никакого ручного git и никаких сайтов. Документы — только обезличенные: без ФИО, реквизитов и тайны клиента.',
      modules: [
        { id: 'dm4', title: 'GitHub: аккаунт и подключение к ИИ', lessons: [
          { id: 'd11-why-github', title: 'Зачем GitHub, если код пишет ИИ', minutes: 6 },
          { id: '12-signup', title: 'Регистрация на GitHub: шаг за шагом', minutes: 8 },
          { id: 'd13-first-repo', title: 'Репозиторий с документами для ИИ', minutes: 8 },
          { id: 'd14-connect', title: 'Подключить GitHub к Claude и ChatGPT', minutes: 12 },
          { id: 'dm4-control', title: 'Контрольная 4: GitHub подключён к ИИ', minutes: 15, control: true },
        ]},
        { id: 'dm5', title: 'ИИ работает в вашем репозитории', lessons: [
          { id: 'd15-first-task', title: 'Первая задача: ИИ сам делает коммит и pull request', minutes: 12 },
          { id: 'd16-codex-copilot', title: 'То же в ChatGPT (Codex) и Copilot', minutes: 10 },
          { id: 'd17-review', title: 'Проверить PR и оставить замечания', minutes: 10 },
          { id: 'd18-broken', title: 'Когда ИИ сделал не то', minutes: 6 },
          { id: 'dm5-control', title: 'Контрольная 5: два PR от ИИ и один откат', minutes: 20, control: true },
        ]},
        { id: 'dm6', title: 'Инструмент для документов', lessons: [
          { id: 'd19-tools', title: 'Какие инструменты для документов может сделать ИИ', minutes: 8 },
          { id: 'd20-project', title: 'Проект: проверка договоров по чеклисту', minutes: 15 },
          { id: 'd21-iterate', title: 'Доработки и повторные запуски', minutes: 8 },
          { id: 'd22-verify-tool', title: 'Как проверять результат инструмента', minutes: 8 },
          { id: 'dm6-control', title: 'Контрольная 6: инструмент по вашему ТЗ', minutes: 30, control: true },
        ]},
        { id: 'dm7', title: 'От юриста до программиста', lessons: [
          { id: 'd23-next', title: 'Следующие инструменты и шаблон ТЗ', minutes: 8 },
          { id: 'd24-grow', title: 'Как расти: читать код, который пишет ИИ', minutes: 6 },
          { id: 'dm7-control', title: 'Финальная контрольная: ваш инструмент в работе', minutes: 60, control: true },
          { id: 'd25-finish', title: 'Финал: что дальше', minutes: 4 },
        ]},
      ],
    },
    mp: {
      id: 'mp', name: 'Маркетплейсы', emoji: '📦',
      tagline: 'Карточки и отзывы в репозитории, git делает ИИ',
      desc: 'GitHub как рабочая папка менеджера маркетплейсов: карточки товаров, отзывы, шаблоны ответов. Вы ставите задачи, ИИ сам пишет и запускает программы, делает коммиты и pull request, вы проверяете и принимаете. Никакого ручного git и никакого кода. Обезличивать не нужно: карточки и цены можно брать реальные, убрать только персональные данные покупателей.',
      modules: [
        { id: 'pm4', title: 'GitHub: аккаунт и подключение к ИИ', lessons: [
          { id: 'p11-why-github', title: 'Зачем GitHub менеджеру маркетплейсов', minutes: 6 },
          { id: '12-signup', title: 'Регистрация на GitHub: шаг за шагом', minutes: 8 },
          { id: 'p13-first-repo', title: 'Репозиторий с карточками товаров для ИИ', minutes: 8 },
          { id: 'p14-connect', title: 'Подключить GitHub к Claude и ChatGPT', minutes: 12 },
          { id: 'pm4-control', title: 'Контрольная 4: GitHub подключён к ИИ', minutes: 15, control: true },
        ]},
        { id: 'pm5', title: 'ИИ работает в вашем репозитории', lessons: [
          { id: 'p15-first-task', title: 'Первая задача: ИИ сам делает коммит и pull request', minutes: 12 },
          { id: 'p16-codex-copilot', title: 'То же в ChatGPT (Codex) и Copilot', minutes: 10 },
          { id: 'p17-review', title: 'Проверить PR и оставить замечания', minutes: 10 },
          { id: 'p18-broken', title: 'Когда ИИ сделал не то', minutes: 6 },
          { id: 'pm5-control', title: 'Контрольная 5: два PR от ИИ и один откат', minutes: 20, control: true },
        ]},
        { id: 'pm6', title: 'Инструмент для маркетплейса', lessons: [
          { id: 'p19-tools', title: 'Какие инструменты для маркетплейса может сделать ИИ', minutes: 8 },
          { id: 'p20-project', title: 'Проект: проверка карточек товаров по чеклисту', minutes: 15 },
          { id: 'p21-iterate', title: 'Доработки и повторные запуски', minutes: 8 },
          { id: 'p22-verify-tool', title: 'Как проверять результат инструмента', minutes: 8 },
          { id: 'pm6-control', title: 'Контрольная 6: инструмент по вашему ТЗ', minutes: 30, control: true },
        ]},
        { id: 'pm7', title: 'От менеджера до программиста', lessons: [
          { id: 'p23-next', title: 'Следующие инструменты и шаблон ТЗ', minutes: 8 },
          { id: 'p24-grow', title: 'Как расти: читать код, который пишет ИИ', minutes: 6 },
          { id: 'pm7-control', title: 'Финальная контрольная: ваш инструмент в работе', minutes: 60, control: true },
          { id: 'p25-finish', title: 'Финал: что дальше', minutes: 4 },
        ]},
      ],
    },
    mkt: {
      id: 'mkt', name: 'Маркетолог', emoji: '📣',
      tagline: 'Контент и отчёты в репозитории, git делает ИИ',
      desc: 'GitHub как рабочая папка маркетолога: посты, выгрузки рекламных кампаний, шаблоны отчётов. Вы ставите задачи, ИИ сам пишет и запускает программы, делает коммиты и pull request, вы проверяете и принимаете. Никакого ручного git и никакого кода. Обезличивать не нужно: посты и статистику можно брать реальные, убрать только персональные данные клиентов и подписчиков.',
      modules: [
        { id: 'km4', title: 'GitHub: аккаунт и подключение к ИИ', lessons: [
          { id: 'k11-why-github', title: 'Зачем GitHub маркетологу', minutes: 6 },
          { id: '12-signup', title: 'Регистрация на GitHub: шаг за шагом', minutes: 8 },
          { id: 'k13-first-repo', title: 'Репозиторий с контентом и отчётами для ИИ', minutes: 8 },
          { id: 'k14-connect', title: 'Подключить GitHub к Claude и ChatGPT', minutes: 12 },
          { id: 'km4-control', title: 'Контрольная 4: GitHub подключён к ИИ', minutes: 15, control: true },
        ]},
        { id: 'km5', title: 'ИИ работает в вашем репозитории', lessons: [
          { id: 'k15-first-task', title: 'Первая задача: ИИ сам делает коммит и pull request', minutes: 12 },
          { id: 'k16-codex-copilot', title: 'То же в ChatGPT (Codex) и Copilot', minutes: 10 },
          { id: 'k17-review', title: 'Проверить PR и оставить замечания', minutes: 10 },
          { id: 'k18-broken', title: 'Когда ИИ сделал не то', minutes: 6 },
          { id: 'km5-control', title: 'Контрольная 5: два PR от ИИ и один откат', minutes: 20, control: true },
        ]},
        { id: 'km6', title: 'Инструмент для маркетинга', lessons: [
          { id: 'k19-tools', title: 'Какие инструменты для маркетинга может сделать ИИ', minutes: 8 },
          { id: 'k20-project', title: 'Проект: проверка постов по чеклисту', minutes: 15 },
          { id: 'k21-iterate', title: 'Доработки и повторные запуски', minutes: 8 },
          { id: 'k22-verify-tool', title: 'Как проверять результат инструмента', minutes: 8 },
          { id: 'km6-control', title: 'Контрольная 6: инструмент по вашему ТЗ', minutes: 30, control: true },
        ]},
        { id: 'km7', title: 'От маркетолога до программиста', lessons: [
          { id: 'k23-next', title: 'Следующие инструменты и шаблон ТЗ', minutes: 8 },
          { id: 'k24-grow', title: 'Как расти: читать код, который пишет ИИ', minutes: 6 },
          { id: 'km7-control', title: 'Финальная контрольная: ваш инструмент в работе', minutes: 60, control: true },
          { id: 'k25-finish', title: 'Финал: что дальше', minutes: 4 },
        ]},
      ],
    },
  };

  const ROOT = document.body.getAttribute('data-root') || './';
  const PAGE = document.body.getAttribute('data-page') || 'lesson';
  const LESSON_ID = document.body.getAttribute('data-lesson') || '';

  /* ---------- Хранилище ---------- */
  const PREFIX = 'aikurs:';
  const TS_KEY = PREFIX + '_ts';
  function touch() { try { localStorage.setItem(TS_KEY, String(Date.now())); } catch (e) {} }
  const store = {
    get(k, d) { try { const v = localStorage.getItem(PREFIX + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(PREFIX + k, JSON.stringify(v)); touch(); } catch (e) {} },
    del(k) { try { localStorage.removeItem(PREFIX + k); touch(); } catch (e) {} },
    clearAll() { try { Object.keys(localStorage).filter(k => k.startsWith(PREFIX) && k !== PREFIX + 'theme').forEach(k => localStorage.removeItem(k)); touch(); } catch (e) {} },
  };

  /* ---------- Курс открыт из файлов (file://) ----------
     Часть браузеров (например, Firefox) даёт каждой локальной странице или папке свою память,
     и выбор пути с главной не виден в уроке. Поэтому при открытии из файлов состояние курса
     едет вместе с адресом при переходе по ссылкам (?aikurs=…); побеждает более свежее.
     На сайте (http/https) это не нужно и не включается. */
  const FILE_MODE = location.protocol === 'file:';
  function snapshot() { const o = {}; try { Object.keys(localStorage).filter(k => k.startsWith(PREFIX)).forEach(k => { o[k] = localStorage.getItem(k); }); } catch (e) {} return o; }
  if (FILE_MODE) {
    try {
      const u = new URL(location.href), raw = u.searchParams.get('aikurs');
      if (raw) {
        const snap = JSON.parse(raw);
        if (+(snap[TS_KEY] || 0) >= +(localStorage.getItem(TS_KEY) || 0)) {
          Object.keys(localStorage).filter(k => k.startsWith(PREFIX)).forEach(k => localStorage.removeItem(k));
          Object.keys(snap).forEach(k => { if (k.startsWith(PREFIX)) localStorage.setItem(k, snap[k]); });
        }
        u.searchParams.delete('aikurs');
        try { history.replaceState(null, '', u.href); } catch (e) {}
      }
    } catch (e) {}
  }
  function withState(href) {
    if (!FILE_MODE) return href;
    try {
      const u = new URL(href, location.href);
      if (u.protocol !== 'file:' || !/\.html$/i.test(u.pathname)) return href;
      u.searchParams.set('aikurs', JSON.stringify(snapshot()));
      return u.href;
    } catch (e) { return href; }
  }
  function go(href) { location.href = withState(href); }
  if (FILE_MODE) document.addEventListener('click', e => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank') return;
    const href = a.getAttribute('href') || '';
    if (/^(https?:|mailto:|#)/i.test(href)) return;
    const next = withState(a.href);
    if (next !== a.href) { e.preventDefault(); location.href = next; }
  }, true);

  /* ---------- Путь и активный список уроков ---------- */
  let TRACK = store.get('track', null);
  if (TRACK && !TRACKS[TRACK]) TRACK = null;
  let COURSE = [], LESSONS = [], CURRENT = null, TOTAL_MIN = 0;
  function rebuild() {
    COURSE = COMMON.concat(TRACK ? TRACKS[TRACK].modules : []);
    LESSONS = [];
    COURSE.forEach((m, mi) => m.lessons.forEach((l, li) => {
      LESSONS.push(Object.assign({}, l, { module: m, mi, li, index: LESSONS.length }));
    }));
    CURRENT = LESSONS.find(l => l.id === LESSON_ID) || null;
    TOTAL_MIN = LESSONS.reduce((a, l) => a + l.minutes, 0);
  }
  function tracksOf(id) { return Object.keys(TRACKS).filter(t => TRACKS[t].modules.some(m => m.lessons.some(l => l.id === id))); }
  /* Урок из другого пути (или путь ещё не выбран): показываем урок в контексте его пути,
     но сохранённый выбор не трогаем. Путь меняется только кнопкой. */
  let FOREIGN = null;
  if (LESSON_ID) {
    const inTracks = tracksOf(LESSON_ID);
    if (inTracks.length && !inTracks.includes(TRACK)) {
      FOREIGN = { mine: TRACK, view: inTracks[0] }; TRACK = inTracks[0];
    }
  }
  rebuild();
  function setTrack(t) { if (!TRACKS[t]) return; TRACK = t; store.set('track', t); rebuild(); }
  function allLessonIds() { const ids = []; COMMON.concat(...Object.values(TRACKS).map(t => t.modules)).forEach(m => m.lessons.forEach(l => { if (!ids.includes(l.id)) ids.push(l.id); })); return ids; }
  const COMMON_COUNT = COMMON.reduce((a, m) => a + m.lessons.length, 0);
  function trackCount(t) { return TRACKS[t].modules.reduce((a, m) => a + m.lessons.length, 0); }

  /* ---------- Утилиты ---------- */
  function el(tag, cls, html) { const e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
  function url(l) { return ROOT + 'lessons/' + l.id + '.html'; }
  function lessonNum(l) { const m = l.id.match(/^[a-z]?(\d+)-/); return m ? String(parseInt(m[1], 10)) : ''; }
  function plural(n, one, few, many) { const m10 = n % 10, m100 = n % 100; if (m10 === 1 && m100 !== 11) return one; if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few; return many; }
  let toastEl, toastTimer;
  function toast(msg) {
    if (!toastEl) { toastEl = el('div', 'toast'); document.body.appendChild(toastEl); }
    toastEl.textContent = msg; toastEl.classList.add('show');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2200);
  }
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
    return new Promise((res, rej) => {
      const ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); res(); } catch (e) { rej(e); } finally { document.body.removeChild(ta); }
    });
  }

  /* ---------- Прогресс ---------- */
  function doneSet() { return new Set(store.get('done', [])); }
  function isDone(id) { return doneSet().has(id); }
  function setDone(id, v) { const s = doneSet(); if (v) s.add(id); else s.delete(id); store.set('done', [...s]); }
  function doneCount() { return LESSONS.filter(l => isDone(l.id)).length; }
  function percent() { return Math.round(100 * doneCount() / LESSONS.length); }
  function nextUndone() { return LESSONS.find(l => !isDone(l.id)) || null; }

  /* ---------- Тема ---------- */
  function applyTheme(t) { if (t) document.documentElement.setAttribute('data-theme', t); else document.documentElement.removeAttribute('data-theme'); }
  applyTheme(store.get('theme', null));
  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }
  function toggleTheme() { const next = currentTheme() === 'dark' ? 'light' : 'dark'; store.set('theme', next); applyTheme(next); refreshThemeButtons(); }
  function refreshThemeButtons() { document.querySelectorAll('.theme-btn').forEach(b => { b.textContent = currentTheme() === 'dark' ? '☀️' : '🌙'; b.title = currentTheme() === 'dark' ? 'Светлая тема' : 'Тёмная тема'; }); }

  /* ---------- Оболочка: сайдбар, верхняя панель ---------- */
  function buildSidebar(sidebar) {
    sidebar.innerHTML = '';
    const brand = el('a', 'brand'); brand.href = ROOT + 'index.html';
    brand.innerHTML = '<span class="logo">AI</span><span>Курс по ИИ<small>от юриста до программиста</small></span>';
    sidebar.appendChild(brand);

    const prog = el('div', 'side-progress');
    const dc = doneCount();
    prog.innerHTML = `<div>Пройдено <b>${dc}</b> из ${LESSONS.length} уроков · ${percent()}%</div><div class="progress"><span style="width:${percent()}%"></span></div>`;
    sidebar.appendChild(prog);

    COURSE.forEach((m, mi) => {
      if (mi === COMMON.length) {
        const t = TRACKS[TRACK];
        sidebar.appendChild(FOREIGN
          ? el('div', 'side-track foreign', FOREIGN.mine
              ? `<span class="emoji">${t.emoji}</span><span>Вы смотрите путь <b>«${esc(t.name)}»</b>. Ваш путь — «${esc(TRACKS[FOREIGN.mine].name)}».<br><a href="${ROOT}index.html">вернуться к своему</a></span>`
              : `<span class="emoji">${t.emoji}</span><span>Вы смотрите путь <b>«${esc(t.name)}»</b>. Путь ещё не выбран.<br><a href="${ROOT}index.html#track">выбрать путь</a></span>`)
          : el('div', 'side-track', `<span class="emoji">${t.emoji}</span><span><b>Путь «${esc(t.name)}»</b><br><a href="${ROOT}index.html#track">сменить путь</a></span>`));
      }
      const box = el('div', 'side-module');
      box.appendChild(el('div', 'mod-title', `<span class="mod-num">${mi}</span><span>${esc(m.title)}</span>`));
      m.lessons.forEach(l => {
        const a = el('a', 'side-lesson' + (isDone(l.id) ? ' done' : '') + (CURRENT && CURRENT.id === l.id ? ' current' : ''));
        a.href = url(l);
        a.innerHTML = `<span class="dot">${isDone(l.id) ? '✓' : ''}</span><span>${l.control ? '📝 ' : '<span class="lnum">' + lessonNum(l) + '</span> '}${esc(l.title)}</span>`;
        box.appendChild(a);
      });
      sidebar.appendChild(box);
    });
    if (!TRACK) {
      sidebar.appendChild(el('div', 'side-choose', `<b>Модули 4–7 зависят от пути.</b><br><a class="btn small primary" href="${ROOT}index.html#track">Выбрать путь →</a>`));
    }

    const foot = el('div', 'side-foot');
    foot.innerHTML = `<a href="${ROOT}certificate.html">🎓 Сертификат</a><a href="${ROOT}index.html#faq">❓ Частые вопросы</a><span class="small">Прогресс хранится в этом браузере.</span>`;
    sidebar.appendChild(foot);
  }

  function buildShell() {
    const main = document.querySelector('main.content');
    if (!main) return;
    const layout = el('div', 'layout');
    const sidebar = el('aside', 'sidebar'); sidebar.id = 'sidebar';
    const page = el('div', 'page');
    const topbar = el('header', 'topbar');

    const menuBtn = el('button', 'icon-btn menu-btn', '☰'); menuBtn.title = 'Содержание'; menuBtn.setAttribute('aria-label', 'Открыть содержание');
    menuBtn.addEventListener('click', () => document.body.classList.toggle('nav-open'));
    topbar.appendChild(menuBtn);

    const crumbs = el('div', 'crumbs');
    if (CURRENT) crumbs.innerHTML = `<a href="${ROOT}index.html">Курс</a>${CURRENT.mi >= COMMON.length ? ' · ' + TRACKS[TRACK].emoji + ' ' + esc(TRACKS[TRACK].name) : ''} · Модуль ${CURRENT.mi}: ${esc(CURRENT.module.title)}`;
    else if (PAGE === 'certificate') crumbs.innerHTML = `<a href="${ROOT}index.html">Курс</a> · Сертификат`;
    else crumbs.innerHTML = 'Интерактивный курс по ИИ';
    topbar.appendChild(crumbs);
    topbar.appendChild(el('div', 'spacer'));

    const nxt = nextUndone();
    if (PAGE === 'lesson' && nxt && (!CURRENT || nxt.id !== CURRENT.id)) {
      const cont = el('a', 'btn small ghost', '▶ Продолжить');
      if (FOREIGN) { cont.href = ROOT + 'index.html'; cont.title = FOREIGN.mine ? 'Продолжить свой путь «' + TRACKS[FOREIGN.mine].name + '»' : 'Выбрать путь'; }
      else { cont.href = url(nxt); cont.title = nxt.title; }
      topbar.appendChild(cont);
    }
    const themeBtn = el('button', 'icon-btn theme-btn'); themeBtn.setAttribute('aria-label', 'Переключить тему');
    themeBtn.addEventListener('click', toggleTheme);
    topbar.appendChild(themeBtn);

    const backdrop = el('div', 'backdrop'); backdrop.addEventListener('click', () => document.body.classList.remove('nav-open'));

    main.parentNode.insertBefore(layout, main);
    page.appendChild(topbar); page.appendChild(main);
    layout.appendChild(sidebar); layout.appendChild(page);
    document.body.appendChild(backdrop);
    buildSidebar(sidebar);
    refreshThemeButtons();
  }

  /* ---------- Урок: шапка и навигация ---------- */
  function buildLessonChrome() {
    if (!CURRENT) return;
    const article = document.querySelector('main.content article') || document.querySelector('main.content');
    const h1 = article.querySelector('h1');
    if (h1 && !article.querySelector('.kicker')) {
      const k = el('p', 'kicker', `${CURRENT.mi >= COMMON.length ? 'Путь «' + esc(TRACKS[TRACK].name) + '» · ' : ''}Модуль ${CURRENT.mi} · ${esc(CURRENT.module.title)} · ${CURRENT.control ? 'Контрольное задание' : 'Урок ' + lessonNum(CURRENT)}`);
      h1.parentNode.insertBefore(k, h1);
    }
    if (FOREIGN && h1 && !article.querySelector('.track-foreign')) {
      const v = TRACKS[FOREIGN.view], mine = FOREIGN.mine ? TRACKS[FOREIGN.mine] : null;
      const box = el('div', 'note track-foreign', mine
        ? `<p><b>Это урок пути «${esc(v.name)}».</b> Ваш путь — «${esc(mine.name)}», выбор не изменился: прогресс и сертификат считаются по нему.</p><div class="row"><button class="btn small primary" type="button">Сделать «${esc(v.name)}» моим путём</button><a class="btn small" href="${ROOT}index.html">Вернуться к пути «${esc(mine.name)}»</a></div>`
        : `<p><b>Это урок пути «${esc(v.name)}».</b> Путь ещё не выбран: модули 4–7 у каждого пути свои.</p><div class="row"><button class="btn small primary" type="button">Выбрать путь «${esc(v.name)}»</button><a class="btn small" href="${ROOT}index.html#track">Выбрать другой путь</a></div>`);
      box.querySelector('button').addEventListener('click', () => { setTrack(FOREIGN.view); location.reload(); });
      h1.parentNode.insertBefore(box, h1);
    }
    if (!article.querySelector('.meta')) {
      const meta = el('div', 'meta', `<span>⏱ ${CURRENT.minutes} ${plural(CURRENT.minutes, 'минута', 'минуты', 'минут')}</span><span class="meta-done">${isDone(CURRENT.id) ? '✅ Урок пройден' : '○ Ещё не пройден'}</span>`);
      const lead = article.querySelector('.lead');
      const anchor = lead || h1;
      if (anchor) anchor.parentNode.insertBefore(meta, anchor.nextSibling);
    }
    document.title = CURRENT.title + ' — Курс по ИИ';

    const nav = el('nav', 'lesson-nav');
    const prev = LESSONS[CURRENT.index - 1], next = LESSONS[CURRENT.index + 1];
    const doneBox = el('div', 'done-box' + (isDone(CURRENT.id) ? ' is-done' : ''));
    function renderDone() {
      const d = isDone(CURRENT.id);
      doneBox.className = 'done-box' + (d ? ' is-done' : '');
      doneBox.innerHTML = d
        ? `<p><b>Урок пройден.</b> ${next ? 'Переходите к следующему.' : 'Это был последний урок — заберите сертификат!'}</p><button class="btn small ghost" data-act="undo">Снять отметку</button>${next ? `<a class="btn primary" href="${url(next)}">Следующий урок →</a>` : `<a class="btn primary" href="${ROOT}certificate.html">🎓 Сертификат</a>`}`
        : `<p>Разобрались с материалом? Отметьте урок — прогресс сохранится в браузере.</p><button class="btn primary" data-act="done">✓ Отметить урок пройденным</button>`;
      const m = document.querySelector('.meta-done'); if (m) m.textContent = d ? '✅ Урок пройден' : '○ Ещё не пройден';
    }
    doneBox.addEventListener('click', e => {
      const b = e.target.closest('button[data-act]'); if (!b) return;
      setDone(CURRENT.id, b.dataset.act === 'done');
      renderDone(); buildSidebar(document.getElementById('sidebar'));
      if (b.dataset.act === 'done') toast(percent() === 100 ? '🎉 Курс пройден полностью!' : `Готово! Пройдено ${percent()}% курса`);
    });
    renderDone();
    document.addEventListener('aikurs:progress', renderDone);
    nav.appendChild(doneBox);

    const links = el('div', 'nav-links');
    if (prev) links.appendChild(Object.assign(el('a', 'nav-card prev', `<small>← Предыдущий</small>${esc(prev.title)}`), { href: url(prev) }));
    else links.appendChild(Object.assign(el('a', 'nav-card prev', `<small>← В начало</small>Содержание курса`), { href: ROOT + 'index.html' }));
    if (next) links.appendChild(Object.assign(el('a', 'nav-card next', `<small>Следующий →</small>${esc(next.title)}`), { href: url(next) }));
    else links.appendChild(Object.assign(el('a', 'nav-card next', `<small>Финиш →</small>Получить сертификат`), { href: ROOT + 'certificate.html' }));
    nav.appendChild(links);
    document.querySelector('main.content').appendChild(nav);

    document.addEventListener('keydown', e => {
      if (e.altKey || e.ctrlKey || e.metaKey) return;
      const t = e.target; if (t && /INPUT|TEXTAREA|SELECT/.test(t.tagName)) return;
      if (e.key === 'ArrowRight' && next) go(url(next));
      if (e.key === 'ArrowLeft' && prev) go(url(prev));
    });
  }

  /* ---------- Виджет: копирование ---------- */
  function initCopy() {
    document.querySelectorAll('pre').forEach(pre => {
      if (pre.closest('.pb-out') || pre.closest('.gs') || pre.closest('.no-copy')) return;
      const wrap = el('div', 'copy-wrap');
      pre.parentNode.insertBefore(wrap, pre); wrap.appendChild(pre);
      const btn = el('button', 'copy-btn', 'Копировать'); btn.type = 'button';
      if (!pre.closest('.prompt')) btn.classList.add('dark');
      btn.addEventListener('click', () => {
        copyText(pre.innerText.replace(/\n$/, '')).then(() => { btn.textContent = 'Скопировано ✓'; btn.classList.add('copied'); toast('Скопировано в буфер обмена'); setTimeout(() => { btn.textContent = 'Копировать'; btn.classList.remove('copied'); }, 1800); })
          .catch(() => toast('Не удалось скопировать — выделите текст вручную'));
      });
      wrap.appendChild(btn);
    });
  }

  /* ---------- Виджет: степпер (тыкнул «дальше» — получил дальше) ---------- */
  function initSteppers() {
    document.querySelectorAll('.stepper').forEach((box, bi) => {
      const id = box.dataset.id || ('s' + bi);
      const steps = [...box.querySelectorAll(':scope > .step')];
      if (!steps.length) return;
      const saved = store.get('stepper:' + (LESSON_ID || PAGE) + ':' + id, { i: 0, done: [], finished: false });
      let i = Math.min(saved.i || 0, steps.length - 1);
      const done = new Set(saved.done || []);
      let finished = !!saved.finished;

      const head = el('div', 'stepper-head');
      const count = el('span', 'count'); const dots = el('div', 'stepper-dots');
      steps.forEach((s, k) => { const b = el('button', '', String(k + 1)); b.type = 'button'; b.title = s.dataset.title || ('Шаг ' + (k + 1)); b.addEventListener('click', () => { i = k; render(); }); dots.appendChild(b); });
      head.appendChild(count); head.appendChild(dots);
      box.insertBefore(head, box.firstChild);

      steps.forEach(s => { if (s.dataset.title && !s.querySelector(':scope > .step-title')) s.insertBefore(el('h3', 'step-title', esc(s.dataset.title)), s.firstChild); });

      const foot = el('div', 'stepper-foot');
      const back = el('button', 'btn', '← Назад'); back.type = 'button';
      const hint = el('span', 'hint');
      const next = el('button', 'btn primary', 'Сделал(а), дальше →'); next.type = 'button';
      foot.appendChild(back); foot.appendChild(hint); foot.appendChild(next);
      box.appendChild(foot);

      const fin = el('div', 'finished', `<div class="big">🎉</div><h3 class="mt0">Все шаги пройдены</h3><p class="muted">Можно вернуться к любому шагу по номеру сверху.</p><button class="btn small" type="button">Пройти заново</button>`);
      fin.querySelector('button').addEventListener('click', () => { finished = false; done.clear(); i = 0; render(); });
      box.appendChild(fin);

      back.addEventListener('click', () => { if (i > 0) { i--; render(); } });
      next.addEventListener('click', () => {
        done.add(i);
        if (i < steps.length - 1) { i++; } else { finished = true; }
        render();
      });
      function render() {
        steps.forEach((s, k) => s.classList.toggle('active', k === i));
        [...dots.children].forEach((b, k) => { b.classList.toggle('active', k === i); b.classList.toggle('done', done.has(k)); });
        count.textContent = `Шаг ${i + 1} из ${steps.length}`;
        back.disabled = i === 0;
        next.textContent = i === steps.length - 1 ? 'Готово ✓' : 'Сделал(а), дальше →';
        hint.textContent = steps[i].dataset.hint || '';
        box.classList.toggle('is-finished', finished);
        store.set('stepper:' + (LESSON_ID || PAGE) + ':' + id, { i, done: [...done], finished });
      }
      render();
    });
  }

  /* ---------- Виджет: квиз ---------- */
  function initQuizzes() {
    document.querySelectorAll('.quiz').forEach((quiz, qi) => {
      const id = quiz.dataset.id || ('q' + qi);
      const key = 'quiz:' + (LESSON_ID || PAGE) + ':' + id;
      const qs = [...quiz.querySelectorAll('.q')];
      const saved = store.get(key, {});
      const result = el('div', 'quiz-result');
      quiz.appendChild(result);
      const again = el('button', 'btn small', 'Пройти ещё раз'); again.type = 'button'; again.style.marginLeft = '12px';
      again.addEventListener('click', () => { store.del(key); qs.forEach(q => { q.classList.remove('answered'); q.querySelectorAll('li').forEach(li => li.classList.remove('correct', 'wrong')); }); update(); });

      qs.forEach((q, k) => {
        const opts = [...q.querySelectorAll('.q-options li')];
        opts.forEach((li, oi) => li.addEventListener('click', () => {
          if (q.classList.contains('answered')) return;
          answer(k, oi);
        }));
        if (saved[k] != null) paint(k, saved[k]);
      });
      function answer(k, oi) { const s = store.get(key, {}); s[k] = oi; store.set(key, s); paint(k, oi); update(); }
      function paint(k, oi) {
        const q = qs[k]; const opts = [...q.querySelectorAll('.q-options li')];
        q.classList.add('answered');
        opts.forEach((li, j) => { li.classList.toggle('correct', li.hasAttribute('data-correct')); if (j === oi && !li.hasAttribute('data-correct')) li.classList.add('wrong'); });
      }
      function update() {
        const s = store.get(key, {});
        const answered = Object.keys(s).length;
        if (answered === qs.length) {
          let correct = 0;
          qs.forEach((q, k) => { const opts = [...q.querySelectorAll('.q-options li')]; if (opts[s[k]] && opts[s[k]].hasAttribute('data-correct')) correct++; });
          result.innerHTML = `Результат: ${correct} из ${qs.length} ${correct === qs.length ? '— отлично! 🎯' : correct >= qs.length / 2 ? '— неплохо, перечитайте пояснения к ошибкам.' : '— стоит перечитать урок.'}`;
          result.appendChild(again);
          quiz.classList.add('complete');
        } else quiz.classList.remove('complete');
      }
      update();
    });
  }

  /* ---------- Виджет: чеклист ---------- */
  function initChecklists() {
    document.querySelectorAll('ul.checklist').forEach((ul, ci) => {
      const id = ul.dataset.id || ('c' + ci);
      const key = 'check:' + (LESSON_ID || PAGE) + ':' + id;
      const items = [...ul.children];
      const checked = new Set(store.get(key, []));
      const status = el('p', 'checklist-status');
      ul.parentNode.insertBefore(status, ul.nextSibling);
      items.forEach((li, k) => {
        const html = li.innerHTML;
        li.innerHTML = `<label><input type="checkbox"><span class="txt">${html}</span></label>`;
        const cb = li.querySelector('input'); cb.checked = checked.has(k); li.classList.toggle('checked', cb.checked);
        cb.addEventListener('change', () => { if (cb.checked) checked.add(k); else checked.delete(k); li.classList.toggle('checked', cb.checked); store.set(key, [...checked]); update(); if (checked.size === items.length) toast('Чеклист выполнен ✓'); });
      });
      function update() { status.textContent = `Отмечено ${checked.size} из ${items.length}` + (checked.size === items.length ? ' — всё готово ✓' : ''); }
      update();
    });
  }

  /* ---------- Виджет: раскрытие ответа ---------- */
  function initReveals() {
    document.querySelectorAll('.reveal').forEach(r => {
      const btn = el('button', 'btn small reveal-btn', r.dataset.label || 'Показать ответ'); btn.type = 'button';
      const q = r.querySelector('.reveal-q');
      (q ? q.parentNode : r).insertBefore(btn, q ? q.nextSibling : r.firstChild);
      btn.addEventListener('click', () => r.classList.add('open'));
    });
  }

  /* ---------- Виджет: вкладки ---------- */
  function initTabs() {
    const groups = {};
    document.querySelectorAll('.tabs').forEach((box, ti) => {
      const tabs = [...box.querySelectorAll(':scope > .tab')];
      if (!tabs.length) return;
      const bar = el('div', 'tabs-bar');
      const group = box.dataset.group || null;
      tabs.forEach((t, k) => {
        const b = el('button', '', esc(t.dataset.tab || ('Вкладка ' + (k + 1)))); b.type = 'button';
        b.addEventListener('click', () => { activate(k); if (group) { store.set('tabs:' + group, t.dataset.tab); (groups[group] || []).forEach(fn => fn(t.dataset.tab)); } });
        bar.appendChild(b);
      });
      box.insertBefore(bar, box.firstChild);
      function activate(k) { tabs.forEach((t, j) => t.classList.toggle('active', j === k)); [...bar.children].forEach((b, j) => b.classList.toggle('active', j === k)); }
      function activateByName(name) { const k = tabs.findIndex(t => t.dataset.tab === name); if (k >= 0) activate(k); }
      activate(0);
      if (group) { (groups[group] = groups[group] || []).push(activateByName); const pref = store.get('tabs:' + group, null); if (pref) activateByName(pref); }
    });
  }

  /* ---------- Виджет: конструктор промпта ---------- */
  function initPromptBuilder() {
    const host = document.getElementById('prompt-builder');
    if (!host) return;
    const FIELDS = [
      { k: 'role', label: 'Роль', hint: 'кем должен быть ИИ', ph: 'Ты — опытный юрист по договорному праву РФ…' },
      { k: 'task', label: 'Задача', hint: 'что именно сделать, одним глаголом', ph: 'Проверь договор аренды и найди условия, невыгодные арендатору' },
      { k: 'context', label: 'Контекст', hint: 'кто вы, для кого результат, что уже известно', ph: 'Я представляю арендатора — небольшую кофейню. Договор на 3 года, арендодатель — ТЦ…' },
      { k: 'format', label: 'Формат ответа', hint: 'список, таблица, письмо, сколько пунктов', ph: 'Таблица: пункт договора → риск → что предложить взамен. В конце — 3 главных риска' },
      { k: 'limits', label: 'Ограничения', hint: 'чего не делать, длина, тон, язык', ph: 'Не выдумывай нормы — если ссылаешься на статью, цитируй её. Без общих фраз. До 1 страницы' },
      { k: 'example', label: 'Пример (по желанию)', hint: 'образец хорошего ответа или стиля', ph: 'Пример формулировки: «п. 4.2 — штраф 0,5% в день: завышено, рынок 0,1%; предложить 0,1% и потолок 10%»' },
    ];
    const PRESETS = {
      'Юрист': { role: 'Ты — опытный юрист по договорному праву РФ, работаешь на стороне клиента.', task: 'Проверь приложенный договор и найди условия, невыгодные моему клиенту.', context: 'Клиент — арендатор, небольшой бизнес. Договор аренды нежилого помещения на 3 года. Текст договора вставлю ниже.', format: 'Таблица: пункт договора → в чём риск → что предложить взамен. После таблицы — 3 самых критичных риска одним абзацем.', limits: 'Не выдумывай нормы и судебную практику: если ссылаешься на статью, приведи её номер и суть. Если чего-то не хватает для вывода — задай мне вопросы, а не предполагай.', example: '' },
      'Бухгалтер': { role: 'Ты — главный бухгалтер с опытом в российском учёте и налогах (ОСНО и УСН).', task: 'Разбери ситуацию и объясни, как правильно её отразить в учёте и какие налоговые последствия.', context: 'ООО на УСН «доходы минус расходы». Ситуация опишу ниже.', format: 'Пошагово: 1) суть, 2) проводки или действия, 3) налоговые последствия, 4) на что обратить внимание. Коротко.', limits: 'Если норма менялась в последние годы — отдельно укажи, что нужно проверить актуальность. Не придумывай номера писем Минфина.', example: '' },
      'HR': { role: 'Ты — HR-директор с опытом найма в ИТ и сервисных компаниях.', task: 'Составь описание вакансии и список вопросов для собеседования.', context: 'Ищем офис-менеджера в компанию 40 человек, Москва, гибрид. Главное — организованность и доброжелательность.', format: 'Вакансия: до 1500 знаков, живым языком. Затем 10 вопросов для интервью, сгруппированных по компетенциям.', limits: 'Без штампов вроде «динамично развивающаяся компания». Не указывай дискриминирующие требования (возраст, пол).', example: '' },
      'Маркетолог': { role: 'Ты — маркетолог малого бизнеса, умеешь писать просто и по делу.', task: 'Напиши 5 вариантов поста для соцсетей о новой услуге.', context: 'Стоматология в спальном районе, запускаем отбеливание. Аудитория — 25–45 лет, боятся боли и цены.', format: '5 постов, каждый до 600 знаков, разный заход: польза, страх, акция, история клиента, вопрос.', limits: 'Без медицинских обещаний («навсегда», «безболезненно на 100%»). Без капслока и лишних эмодзи.', example: '' },
      'Менеджер': { role: 'Ты — опытный проектный менеджер.', task: 'Преврати мои разрозненные заметки со встречи в план действий.', context: 'Заметки вставлю ниже. Команда — 5 человек, срок проекта — 2 месяца.', format: 'Таблица: задача → ответственный → срок → что считается результатом. Ниже — список открытых вопросов.', limits: 'Не придумывай задачи, которых нет в заметках. Если ответственный неясен — поставь «?».', example: '' },
    };
    const key = 'pb:' + (LESSON_ID || PAGE);
    const saved = store.get(key, {});
    const box = el('div', 'pb');
    const presets = el('div', 'pb-presets');
    Object.keys(PRESETS).forEach(name => { const b = el('button', '', esc(name)); b.type = 'button'; b.addEventListener('click', () => { fill(PRESETS[name]); [...presets.children].forEach(x => x.classList.toggle('active', x === b)); }); presets.appendChild(b); });
    const clr = el('button', '', 'Очистить'); clr.type = 'button'; clr.addEventListener('click', () => { fill({}); [...presets.children].forEach(x => x.classList.remove('active')); });
    presets.appendChild(clr);
    box.appendChild(el('p', 'small', 'Выберите пример профессии или заполните поля сами. Текст справа собирается автоматически.'));
    box.appendChild(presets);
    const grid = el('div', 'pb-grid');
    const inputs = {};
    FIELDS.forEach(f => {
      const w = el('div');
      w.innerHTML = `<label>${f.label} <small>— ${esc(f.hint)}</small></label>`;
      const ta = el('textarea'); ta.placeholder = f.ph; ta.rows = 2; ta.value = saved[f.k] || '';
      ta.addEventListener('input', update);
      inputs[f.k] = ta; w.appendChild(ta); grid.appendChild(w);
    });
    box.appendChild(grid);
    const out = el('div', 'pb-out');
    out.innerHTML = '<label>Готовый запрос</label>';
    const pre = el('pre'); out.appendChild(pre);
    const row = el('div', 'row');
    const copy = el('button', 'btn primary', 'Копировать запрос'); copy.type = 'button';
    copy.addEventListener('click', () => copyText(pre.textContent).then(() => toast('Запрос скопирован — вставьте его в Claude или ChatGPT')));
    row.appendChild(copy);
    const score = el('span', 'pb-score');
    row.appendChild(score);
    out.appendChild(row);
    box.appendChild(out);
    host.appendChild(box);

    function fill(p) { FIELDS.forEach(f => { inputs[f.k].value = p[f.k] || ''; }); update(); }
    function update() {
      const v = {}; FIELDS.forEach(f => v[f.k] = inputs[f.k].value.trim());
      store.set(key, v);
      const parts = [];
      if (v.role) parts.push(v.role);
      if (v.task) parts.push('Задача: ' + v.task);
      if (v.context) parts.push('Контекст: ' + v.context);
      if (v.format) parts.push('Формат ответа: ' + v.format);
      if (v.limits) parts.push('Ограничения: ' + v.limits);
      if (v.example) parts.push('Пример: ' + v.example);
      pre.textContent = parts.length ? parts.join('\n\n') : '(заполните поля выше — запрос появится здесь)';
      const filled = ['role', 'task', 'context', 'format', 'limits'].filter(k => v[k]).length;
      let advice = '';
      if (!v.task) advice = 'Начните с задачи — без неё запрос не имеет смысла.';
      else if (!v.context) advice = 'Добавьте контекст: кто вы и для чего результат — ответ станет точнее.';
      else if (!v.format) advice = 'Укажите формат — не придётся переделывать.';
      else if (!v.limits) advice = 'Ограничения спасают от выдумок и воды.';
      else advice = 'Отличный запрос. Пример усилит стиль, но это по желанию.';
      score.textContent = `Заполнено ${filled} из 5 ключевых блоков. ${advice}`;
      copy.disabled = !parts.length;
    }
    update();
  }

  /* ---------- Виджет: git-симулятор ---------- */
  function initGitSim() {
    const host = document.getElementById('git-sim');
    if (!host) return;
    const TASKS = [
      { t: 'Измените текст файла справа → введите сообщение → нажмите «Сохранить версию (commit)».', check: s => s.branches.main.length > 1 },
      { t: 'Теперь создайте отдельную ветку для эксперимента: «Создать ветку».', check: s => Object.keys(s.branches).length > 1 },
      { t: 'Вы в новой ветке. Измените файл и сделайте коммит — main при этом не меняется.', check: s => Object.keys(s.branches).some(b => b !== 'main' && s.branches[b].length > s.branches.main.length) },
      { t: 'Откройте pull request: «Открыть pull request». Это предложение влить изменения в main.', check: s => s.pr !== null },
      { t: 'Проверьте diff (зелёное — добавлено, красное — удалено) и нажмите «Слить в main (merge)».', check: s => s.merged },
      { t: 'Полный цикл пройден: коммит → ветка → коммит → PR → merge. Именно так работают ИИ-агенты и команды программистов.', check: () => false },
    ];
    const INITIAL = '# Моя первая страница\n\nПривет! Это файл README.md.\nЗдесь можно писать что угодно.';
    let s;
    function reset() {
      const c0 = mk('Первый коммит', INITIAL, null, 'main');
      s = { commits: { [c0.id]: c0 }, branches: { main: [c0.id] }, cur: 'main', work: INITIAL, pr: null, merged: false, task: 0, n: 1 };
    }
    function mk(msg, content, parent, branch) { const id = Math.random().toString(16).slice(2, 9); return { id, msg, content, parent, branch, at: new Date() }; }
    function head(b) { const arr = s.branches[b]; return s.commits[arr[arr.length - 1]]; }
    reset();

    host.innerHTML = '';
    const box = el('div', 'gs');
    const task = el('div', 'gs-task');
    const top = el('div', 'gs-top');
    const left = el('div', 'gs-pane'); const right = el('div', 'gs-pane');
    left.innerHTML = '<h4>Файл README.md <span class="muted">(рабочая копия)</span></h4>';
    const branches = el('div', 'gs-branches'); left.appendChild(branches);
    const ta = el('textarea'); ta.value = s.work; ta.addEventListener('input', () => { s.work = ta.value; renderStatus(); }); left.appendChild(ta);
    const msg = el('input'); msg.placeholder = 'Сообщение коммита: что и зачем изменили'; left.appendChild(msg);
    const commitBtn = el('button', 'btn primary', 'Сохранить версию (commit)'); commitBtn.type = 'button'; left.appendChild(commitBtn);
    right.innerHTML = '<h4>История (git log)</h4>';
    const log = el('ul', 'gs-log'); right.appendChild(log);
    top.appendChild(left); top.appendChild(right);
    const status = el('div', 'gs-status');
    const pr = el('div', 'gs-pr');
    const actions = el('div', 'gs-actions');
    const branchBtn = el('button', 'btn', '🌿 Создать ветку'); branchBtn.type = 'button';
    const prBtn = el('button', 'btn', '🔀 Открыть pull request'); prBtn.type = 'button';
    const resetBtn = el('button', 'btn ghost small', 'Сбросить симулятор'); resetBtn.type = 'button';
    actions.appendChild(branchBtn); actions.appendChild(prBtn); actions.appendChild(resetBtn);
    box.appendChild(task); box.appendChild(top); box.appendChild(status); box.appendChild(pr); box.appendChild(actions);
    host.appendChild(box);

    commitBtn.addEventListener('click', () => {
      const h = head(s.cur);
      if (s.work === h.content) return say('Файл не изменился — нечего сохранять. Измените текст в файле.', true);
      if (!msg.value.trim()) return say('Напишите сообщение коммита — через месяц вы скажете себе спасибо.', true);
      const c = mk(msg.value.trim(), s.work, h.id, s.cur);
      s.commits[c.id] = c; s.branches[s.cur].push(c.id); msg.value = '';
      say(`Коммит сохранён в ветке «${s.cur}». Это снимок файла с подписью — к нему можно вернуться в любой момент.`);
      render();
    });
    branchBtn.addEventListener('click', () => {
      if (s.pr) return say('Сначала закройте открытый pull request (слейте его).', true);
      const name = 'feature-' + (s.n++);
      s.branches[name] = [...s.branches[s.cur]]; s.cur = name; s.work = head(name).content; ta.value = s.work;
      say(`Создана ветка «${name}» — копия текущего состояния. Всё, что вы сделаете здесь, не тронет main, пока вы сами не решите слить изменения.`);
      render();
    });
    prBtn.addEventListener('click', () => {
      if (s.cur === 'main') return say('Pull request открывают из отдельной ветки в main. Создайте ветку и сделайте в ней коммит.', true);
      if (s.branches[s.cur].length <= s.branches.main.length) return say('В этой ветке нет новых коммитов относительно main — нечего предлагать.', true);
      s.pr = s.cur; say(`Pull request открыт: «${s.cur}» → main. В реальном GitHub здесь коллега или вы сами смотрите diff, пишете комментарии и жмёте Merge.`);
      render();
    });
    resetBtn.addEventListener('click', () => { reset(); ta.value = s.work; msg.value = ''; say('Симулятор сброшен.'); render(); });

    function say(text, warn) { status.innerHTML = (warn ? '⚠️ ' : '✅ ') + esc(text); }
    function diff(a, b) {
      const A = a.split('\n'), B = b.split('\n');
      const setA = new Set(A), setB = new Set(B);
      const out = [];
      A.forEach(l => { if (!setB.has(l)) out.push(`<span class="del">- ${esc(l) || ' '}</span>`); });
      B.forEach(l => { if (!setA.has(l)) out.push(`<span class="add">+ ${esc(l) || ' '}</span>`); });
      return out.length ? out.join('') : '<span class="muted">(изменений нет)</span>';
    }
    function renderStatus() {
      const h = head(s.cur);
      if (s.work !== h.content && !status.textContent.startsWith('⚠️')) status.innerHTML = `✏️ Файл изменён, но версия ещё не сохранена. Введите сообщение и сделайте коммит.`;
    }
    function render() {
      branches.innerHTML = '';
      Object.keys(s.branches).forEach(b => {
        const btn = el('button', b === s.cur ? 'active' : '', esc(b)); btn.type = 'button';
        btn.addEventListener('click', () => { if (s.pr && s.cur !== b && b !== 'main') return; s.cur = b; s.work = head(b).content; ta.value = s.work; say(`Переключились на ветку «${b}». Файл показывает её последнюю версию.`); render(); });
        branches.appendChild(btn);
      });
      log.innerHTML = '';
      [...s.branches[s.cur]].reverse().forEach(id => {
        const c = s.commits[id];
        const li = el('li'); li.innerHTML = `<span class="sha">${c.id.slice(0, 7)}</span><span style="flex:1">${esc(c.msg)}</span><span class="br ${c.branch === 'main' ? 'main' : ''}">${esc(c.branch)}</span>`;
        log.appendChild(li);
      });
      if (s.pr) {
        pr.classList.add('open');
        pr.innerHTML = `<b>Pull request:</b> «${esc(s.pr)}» → main · ${s.branches[s.pr].length - s.branches.main.length} ${plural(s.branches[s.pr].length - s.branches.main.length, 'коммит', 'коммита', 'коммитов')}<div class="gs-diff">${diff(head('main').content, head(s.pr).content)}</div>`;
        const mergeBtn = el('button', 'btn ok', '✓ Слить в main (merge)'); mergeBtn.type = 'button';
        mergeBtn.addEventListener('click', () => {
          const extra = s.branches[s.pr].slice(s.branches.main.length);
          extra.forEach(id => s.branches.main.push(id));
          const m = mk(`Merge pull request: ${s.pr}`, head(s.pr).content, head('main').id, 'main');
          s.commits[m.id] = m; s.branches.main.push(m.id);
          s.cur = 'main'; s.work = m.content; ta.value = s.work; s.pr = null; s.merged = true;
          say('Слито! Изменения из ветки теперь в main. Ветку можно удалить — история сохранилась в коммитах.');
          render();
        });
        pr.appendChild(mergeBtn);
      } else { pr.classList.remove('open'); pr.innerHTML = ''; }
      while (s.task < TASKS.length - 1 && TASKS[s.task].check(s)) s.task++;
      task.innerHTML = `<b>Задание ${Math.min(s.task + 1, TASKS.length - 1)} из ${TASKS.length - 1}:</b> ${esc(TASKS[s.task].t)}` + (s.task === TASKS.length - 1 ? ' 🎉' : '');
      prBtn.disabled = !!s.pr;
    }
    say('Это учебный симулятор: ничего не ломается, можно экспериментировать.');
    render();
  }

  /* ---------- Контрольная: сводка и автозачёт ---------- */
  function initControl() {
    if (!CURRENT || !CURRENT.control) return;
    const nav = document.querySelector('.lesson-nav'); if (!nav) return;
    const box = el('div', 'control-summary');
    nav.insertBefore(box, nav.firstChild);
    let timer;
    function compute() {
      const lists = [...document.querySelectorAll('ul.checklist')];
      const items = lists.reduce((a, ul) => a + ul.children.length, 0);
      const checked = lists.reduce((a, ul) => a + ul.querySelectorAll('input:checked').length, 0);
      const qs = [...document.querySelectorAll('.quiz .q')];
      const answered = qs.filter(q => q.classList.contains('answered'));
      const correct = answered.filter(q => !q.querySelector('li.wrong')).length;
      const need = Math.ceil(qs.length * 0.7);
      const listOk = items > 0 && checked === items;
      const quizOk = qs.length === 0 || (answered.length === qs.length && correct >= need);
      const passed = listOk && quizOk;
      box.className = 'control-summary' + (passed ? ' passed' : '');
      box.innerHTML = `<h3 class="mt0">${passed ? '✅ Контрольная сдана' : '📝 Статус контрольной'}</h3>
        <div class="cs-row"><span>Критерии приёмки</span><b>${checked} / ${items}</b>${listOk ? ' ✓' : ''}</div>
        ${qs.length ? `<div class="cs-row"><span>Проверка понимания</span><b>${answered.length} / ${qs.length} отвечено, верно ${correct}</b>${quizOk ? ' ✓' : answered.length === qs.length ? ` — нужно ${need}, нажмите «Пройти ещё раз»` : ''}</div>` : ''}
        <p class="small mb0">${passed ? 'Отметка «пройдено» поставлена автоматически.' : 'Контрольная засчитывается, когда отмечены все критерии и квиз пройден не ниже 70%.'}</p>`;
      if (passed && !isDone(CURRENT.id)) {
        setDone(CURRENT.id, true);
        buildSidebar(document.getElementById('sidebar'));
        document.dispatchEvent(new CustomEvent('aikurs:progress'));
        toast('🎉 Контрольная сдана!');
      }
    }
    const schedule = () => { clearTimeout(timer); timer = setTimeout(compute, 60); };
    document.addEventListener('change', schedule);
    document.addEventListener('click', schedule);
    compute();
  }

  /* ---------- Выбор пути (блок #track-chooser на любой странице) ---------- */
  function renderTrackChooser() {
    const host = document.getElementById('track-chooser'); if (!host) return;
    host.innerHTML = '';
    const grid = el('div', 'track-chooser');
    Object.values(TRACKS).forEach(t => {
      const card = el('div', 'track-card' + (TRACK === t.id ? ' active' : ''));
      card.innerHTML = `<div class="emoji">${t.emoji}</div><h3>Путь «${esc(t.name)}»</h3><p class="tagline">${esc(t.tagline)}</p><p>${esc(t.desc)}</p><p class="small">${trackCount(t.id)} уроков в модулях 4–7</p><button class="btn ${TRACK === t.id ? 'ok' : 'primary'}" type="button">${TRACK === t.id ? '✓ Выбран' : 'Выбрать этот путь'}</button>`;
      card.addEventListener('click', () => {
        if (TRACK === t.id) return;
        setTrack(t.id);
        renderTrackChooser(); renderIndex();
        const sb = document.getElementById('sidebar'); if (sb) buildSidebar(sb);
        toast(`Путь «${t.name}» выбран. Модули 4–7 обновлены.`);
      });
      grid.appendChild(card);
    });
    host.appendChild(grid);
    host.appendChild(el('p', 'small', TRACK ? 'Путь можно сменить в любой момент: прогресс по урокам сохраняется.' : 'Модули 0–3 общие, их можно начать до выбора.'));
  }

  /* ---------- Главная страница ---------- */
  function renderIndex() {
    if (PAGE !== 'index') return;
    const map = document.getElementById('course-map');
    if (map) {
      map.innerHTML = '';
      COURSE.forEach((m, mi) => {
        if (mi === COMMON.length) map.appendChild(el('div', 'track-divider', `${TRACKS[TRACK].emoji} Путь «${esc(TRACKS[TRACK].name)}» · <a href="#track">сменить</a>`));
        const box = el('div', 'module');
        const dn = m.lessons.filter(l => isDone(l.id)).length;
        const mins = m.lessons.reduce((a, l) => a + l.minutes, 0);
        box.appendChild(el('div', 'module-head', `<span class="num">${mi}</span><h3>${esc(m.title)}</h3><span class="mprog">${dn}/${m.lessons.length} · ${mins} мин</span>`));
        const ul = el('ul', 'module-lessons');
        m.lessons.forEach(l => {
          const li = el('li', isDone(l.id) ? 'done' : '');
          li.innerHTML = `<a href="${url(l)}"><span class="dot">${isDone(l.id) ? '✓' : ''}</span><span class="t">${l.control ? '📝 ' : '<span class="lnum">' + lessonNum(l) + '</span> '}${esc(l.title)}</span><span class="min">${l.minutes} мин</span></a>`;
          ul.appendChild(li);
        });
        box.appendChild(ul); map.appendChild(box);
      });
      if (!TRACK) map.appendChild(el('div', 'track-divider', `Модули 4–7 появятся здесь после выбора пути: <a href="#track">выбрать путь</a>`));
    }
    const cont = document.getElementById('continue-btn');
    if (cont) {
      const n = nextUndone();
      if (n) { cont.href = url(n); cont.textContent = doneCount() ? `▶ Продолжить: ${n.title}` : '▶ Начать курс'; }
      else if (!TRACK) { cont.href = '#track'; cont.textContent = '▶ Выбрать путь'; }
      else { cont.href = ROOT + 'certificate.html'; cont.textContent = '🎓 Курс пройден — получить сертификат'; }
    }
    const stats = document.getElementById('stats');
    if (stats) stats.innerHTML = TRACK
      ? `<span><b>${LESSONS.length}</b> ${plural(LESSONS.length, 'урок', 'урока', 'уроков')}</span><span><b>${COURSE.length}</b> модулей</span><span>≈ <b>${Math.round(TOTAL_MIN / 60 * 10) / 10}</b> ч</span><span>путь <b>${esc(TRACKS[TRACK].name)}</b></span><span>пройдено <b>${percent()}%</b></span>`
      : `<span><b>${COMMON_COUNT}</b> общих уроков</span><span>+ <b>${trackCount('site')}</b> в выбранном пути</span><span><b>${Object.keys(TRACKS).length}</b> ${plural(Object.keys(TRACKS).length, 'путь', 'пути', 'путей')} на выбор</span><span>пройдено <b>${percent()}%</b></span>`;
    const bar = document.getElementById('index-progress');
    if (bar) bar.innerHTML = `<span style="width:${percent()}%"></span>`;
  }
  function initIndex() {
    if (PAGE !== 'index') return;
    renderIndex();
    const reset = document.getElementById('reset-progress');
    if (reset) reset.addEventListener('click', () => { if (confirm('Сбросить весь прогресс курса в этом браузере?')) { store.clearAll(); location.reload(); } });
  }

  /* ---------- Сертификат ---------- */
  function initCertificate() {
    if (PAGE !== 'certificate') return;
    const area = document.getElementById('cert-area'); if (!area) return;
    if (!TRACK) { area.innerHTML = `<div class="card"><h3 class="mt0">Сначала выберите путь</h3><p>Сертификат выдаётся за общие модули плюс один из путей на выбор — «Сайт», «Юрист», «Маркетплейсы» или «Маркетолог».</p><a class="btn primary" href="${ROOT}index.html#track">Выбрать путь →</a></div>`; return; }
    const p = percent();
    if (p < 100) {
      const n = nextUndone();
      area.innerHTML = `<div class="card"><h3 class="mt0">Пока рано 🙂</h3><p>Пройдено ${doneCount()} из ${LESSONS.length} уроков (${p}%). Сертификат появится, когда все уроки будут отмечены пройденными.</p><div class="progress" style="margin:10px 0 16px"><span style="width:${p}%"></span></div><a class="btn primary" href="${url(n)}">▶ Продолжить: ${esc(n.title)}</a></div>`;
      return;
    }
    const name = store.get('cert:name', '');
    area.innerHTML = `
      <div class="card no-print" style="margin-bottom:18px">
        <label for="cert-name"><b>Ваше имя на сертификате</b></label>
        <div class="row" style="margin-top:8px"><input id="cert-name" style="flex:1;min-width:220px;font:inherit;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--fg)" placeholder="Иван Петров" value="${esc(name)}"><button class="btn primary" id="cert-print" type="button">🖨 Распечатать / сохранить в PDF</button></div>
      </div>
      <div class="cert">
        <p class="kicker">Сертификат о прохождении</p>
        <h2>Интерактивный курс по ИИ</h2>
        <p class="sub">Путь «${esc(TRACKS[TRACK].name)}»: модели, промптинг, GitHub и ИИ-агенты в браузере</p>
        <div class="name" id="cert-name-out">${esc(name) || '______________________'}</div>
        <p class="sub">${LESSONS.length} ${plural(LESSONS.length, 'урок', 'урока', 'уроков')} · ${COURSE.length} модулей · ${new Date().toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
        <div class="skills"><b>Освоено:</b><ul>
          <li>Понимание возможностей и ограничений ИИ-ассистентов (Claude, ChatGPT)</li>
          <li>Структурированные запросы, работа с документами, проверка результатов</li>
          ${{
            site: '<li>GitHub без установки: репозитории, коммиты, ветки, pull request, GitHub Pages</li><li>Работа в браузере: github.dev и Codespaces</li><li>Постановка задач ИИ-агентам (Claude Code, Codex, Copilot) и проверка кода</li>',
            docs: '<li>GitHub как рабочая папка для ИИ: аккаунт, репозиторий с документами, подключение к Claude и ChatGPT</li><li>Постановка задач ИИ-агентам, проверка их pull request, слияние и откат</li><li>Инструмент для документов, написанный и запущенный ИИ по вашему ТЗ</li>',
            mp: '<li>GitHub как рабочая папка для ИИ: аккаунт, репозиторий с карточками товаров и отзывами, подключение к Claude и ChatGPT</li><li>Постановка задач ИИ-агентам, проверка их pull request, слияние и откат</li><li>Инструмент для маркетплейса, написанный и запущенный ИИ по вашему ТЗ</li>',
            mkt: '<li>GitHub как рабочая папка для ИИ: аккаунт, репозиторий с контентом и отчётами, подключение к Claude и ChatGPT</li><li>Постановка задач ИИ-агентам, проверка их pull request, слияние и откат</li><li>Инструмент для маркетинга, написанный и запущенный ИИ по вашему ТЗ</li>',
          }[TRACK] || ''}
        </ul></div>
      </div>`;
    const inp = document.getElementById('cert-name'), out = document.getElementById('cert-name-out');
    inp.addEventListener('input', () => { store.set('cert:name', inp.value.trim()); out.textContent = inp.value.trim() || '______________________'; });
    document.getElementById('cert-print').addEventListener('click', () => window.print());
  }

  /* ---------- Запуск ---------- */
  function init() {
    buildShell();
    if (PAGE === 'lesson' && CURRENT) buildLessonChrome();
    initCopy(); initSteppers(); initQuizzes(); initChecklists(); initReveals(); initTabs();
    initPromptBuilder(); initGitSim(); initControl(); renderTrackChooser(); initIndex(); initCertificate();
    document.querySelectorAll('dfn[data-def]').forEach(d => { d.tabIndex = 0; d.setAttribute('role', 'button'); d.setAttribute('aria-label', d.textContent + ': ' + d.dataset.def); });
    document.querySelectorAll('.theme-btn').forEach(b => b.addEventListener('click', toggleTheme));
    refreshThemeButtons();
    window.AIKURS = { get COURSE() { return COURSE; }, get LESSONS() { return LESSONS; }, get TRACK() { return TRACK; }, get FOREIGN() { return FOREIGN; }, TRACKS, COMMON, allLessonIds, setTrack, store, percent, isDone, setDone };
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
