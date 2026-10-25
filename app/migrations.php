<?php
/* Обновление базы после git pull. Каждая версия — список запросов; новые шаги только дописывать в конец
   и поднимать SCHEMA_VERSION. Свежая установка сразу получает полную схему из database/schema.sql. */
defined('APP') || exit;

const SCHEMA_VERSION = 2;

function migrations(): array {
    return [
        2 => [
            'ALTER TABLE {p}courses ADD COLUMN module_base TINYINT NOT NULL DEFAULT 1 AFTER storage_key',
            "UPDATE {p}courses SET module_base = 0 WHERE storage_key = 'aikurs:'",
        ],
    ];
}

function migrate(): void {
    if ((int)setting('schema_version', '1') >= SCHEMA_VERSION) return;
    $pdo = db();
    $lock = $pdo->quote(t('migrate'));
    if ((int)$pdo->query("SELECT GET_LOCK($lock, 15)")->fetchColumn() !== 1) return;
    try {
        $cur = (int)(val('SELECT v FROM ' . t('settings') . " WHERE k = 'schema_version'") ?: 1);
        foreach (migrations() as $ver => $sqls) {
            if ($ver <= $cur) continue;
            foreach ($sqls as $sql) {
                try { $pdo->exec(str_replace('{p}', t(''), $sql)); }
                catch (PDOException $e) { if (!preg_match('~Duplicate (column|key)|already exists~i', $e->getMessage())) throw $e; }
            }
            set_setting('schema_version', (string)$ver);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK($lock)");
    }
}
