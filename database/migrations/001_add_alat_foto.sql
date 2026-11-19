-- Add optional photo to alat (nullable for existing rows and seed fixtures).
ALTER TABLE alat
    ADD COLUMN foto VARCHAR(255) NULL AFTER deskripsi;
