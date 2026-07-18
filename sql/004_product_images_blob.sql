-- Migriert Produktbilder von Datei-basiert (Container-Dateisystem, geht bei jedem
-- Deploy verloren, da der Container jedes Mal frisch aus dem Git-Repo gebaut wird)
-- zu DB-basiert (BLOB), damit sie garantiert erhalten bleiben.
-- Einspielen wie schema.sql: per phpMyAdmin (Datenbank auswählen, dann Tab "SQL")

ALTER TABLE product_images
  ADD COLUMN mime_type VARCHAR(64) NULL AFTER filename,
  ADD COLUMN data MEDIUMBLOB NULL AFTER mime_type;

-- Alte Bild-Datensätze verweisen auf Dateien, die durch vorherige Deploys bereits
-- gelöscht wurden und sind nicht mehr nutzbar — werden entfernt, damit im Admin
-- keine kaputten Bildverweise stehen bleiben. Danach einfach neu hochladen.
DELETE FROM product_images WHERE data IS NULL;
