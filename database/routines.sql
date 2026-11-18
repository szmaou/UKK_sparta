-- SPARTA SQL routines (stored function, procedure, trigger).
-- Import after database/schema.sql. Non-destructive: CREATE OR REPLACE only.

DELIMITER //

-- Denda = hari terlambat x jumlah x tarif. Tarif 0 means no fine.
CREATE OR REPLACE FUNCTION fn_hitung_denda(
    p_tanggal_jatuh_tempo DATE,
    p_tanggal_kembali DATE,
    p_jumlah INT,
    p_tarif DECIMAL(12, 2)
) RETURNS DECIMAL(12, 2)
    DETERMINISTIC
BEGIN
    RETURN GREATEST(0, DATEDIFF(p_tanggal_kembali, p_tanggal_jatuh_tempo))
        * GREATEST(0, p_jumlah)
        * GREATEST(0, p_tarif);
END //

-- Report query: peminjaman in a date range with borrower, tool and category.
CREATE OR REPLACE PROCEDURE sp_rekap_peminjaman(
    IN p_tanggal_awal DATE,
    IN p_tanggal_akhir DATE
)
BEGIN
    SELECT
        p.kode,
        u.name AS peminjam,
        a.nama AS alat,
        k.nama AS kategori,
        p.jumlah,
        p.tanggal_pinjam,
        p.tanggal_jatuh_tempo,
        p.status,
        COALESCE(pg.denda, 0) AS denda
    FROM peminjaman p
    INNER JOIN users u ON u.id = p.user_id
    INNER JOIN alat a ON a.id = p.alat_id
    INNER JOIN kategori k ON k.id = a.kategori_id
    LEFT JOIN pengembalian pg ON pg.peminjaman_id = p.id
    WHERE p.tanggal_pinjam BETWEEN p_tanggal_awal AND p_tanggal_akhir
    ORDER BY p.tanggal_pinjam, p.id;
END //

-- Audit status transitions at DB level. The application may set
-- @sparta_actor_id before updating peminjaman; NULL means system/unknown.
CREATE OR REPLACE TRIGGER trg_peminjaman_audit_update
AFTER UPDATE ON peminjaman
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
        VALUES (
            @sparta_actor_id,
            'status_change',
            'peminjaman',
            NEW.id,
            CONCAT('Status ', OLD.status, ' -> ', NEW.status)
        );
    END IF;
END //

DELIMITER ;
