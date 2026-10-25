/* Страницы сайта (каталог, кабинет): переключатель темы. */
(function () {
  var KEY = 'aikurs:theme';
  function current() { return document.documentElement.getAttribute('data-theme') || (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); }
  function refresh() { document.querySelectorAll('.theme-btn').forEach(function (b) { b.textContent = current() === 'dark' ? '☀️' : '🌙'; b.title = current() === 'dark' ? 'Светлая тема' : 'Тёмная тема'; }); }
  document.querySelectorAll('.theme-btn').forEach(function (b) {
    b.addEventListener('click', function () { var n = current() === 'dark' ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', n); try { localStorage.setItem(KEY, JSON.stringify(n)); } catch (e) {} refresh(); });
  });
  refresh();
})();
