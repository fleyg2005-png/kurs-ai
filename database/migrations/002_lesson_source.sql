-- Отпечаток урока, загруженного из content/: по нему ./deploy.sh отличает нетронутый урок
-- от поправленного в админке и обновляет только нетронутые.
ALTER TABLE {p}lessons ADD COLUMN source_hash CHAR(40) NULL AFTER body;
