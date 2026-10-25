#!/usr/bin/env bash
# Деплой на VDS: запускать из корня проекта на сервере.
#   ./deploy.sh          — подтянуть код и применить миграции
#   ./deploy.sh --seed   — дополнительно прогнать сиды (первый запуск)
set -euo pipefail
cd "$(dirname "$0")"

# Каким PHP выполнять миграции. На Plesk системного php может не быть —
# тогда: PHP_BIN=/opt/plesk/php/8.2/bin/php ./deploy.sh
PHP_BIN="${PHP_BIN:-php}"

echo "== git pull =="
git pull --ff-only

if [ ! -f .env ]; then
  echo "!! Нет .env — скопируй .env.example в .env и заполни доступы к БД."
  exit 1
fi

if [ -f composer.json ]; then
  echo "== composer install =="
  composer install --no-dev --prefer-dist --no-interaction
fi

echo "== миграции =="
"$PHP_BIN" bin/migrate.php

if [ "${1:-}" = "--seed" ]; then
  echo "== сиды =="
  "$PHP_BIN" bin/seed.php
fi

echo "== готово =="
