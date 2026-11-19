-- Demo seed data for Docker/init (equivalent to seed.php). Safe to re-run:
-- rows are inserted only when their unique keys do not yet exist.
INSERT IGNORE INTO users (name, username, password_hash, role, is_active) VALUES
('Administrator', 'admin', '$2y$12$R4k.YhwOOM/j.IZxOnEt8.VfLVfk8b2xhiZk4X9xMflQkFnEfgtuK', 'admin', 1),
('Petugas Demo', 'petugas', '$2y$12$q8/WMkKVdW1Eafksan6vM.v4uxiDZQ7L1QdWm04zsd7sMcy2RzEeG', 'petugas', 1),
('Peminjam Demo', 'peminjam', '$2y$12$r0kkirZOCg85f0zTXi/aCuSkBkgndMI.hlyZCdukqtp7jArxRNQ0q', 'peminjam', 1);

INSERT IGNORE INTO kategori (nama, deskripsi) VALUES
('Alat Tangan', 'Contoh kategori alat tangan');

INSERT INTO alat (kode, kategori_id, nama, deskripsi, stok_total, stok_tersedia)
SELECT 'HT-001', k.id, 'Tang Kombinasi 8 inci', 'Contoh alat', 10, 10
FROM kategori k
WHERE k.nama = 'Alat Tangan'
  AND NOT EXISTS (SELECT 1 FROM alat a WHERE a.kode = 'HT-001');
