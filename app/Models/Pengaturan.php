<?php
declare(strict_types=1);
namespace App\Models;
use PDO;

/**
 * Key/value application settings. Only the late-return tariff is configurable
 * for now. Fine amounts already stored on pengembalian rows are historical and
 * never recalculated when the tariff changes.
 */
final class Pengaturan
{
    public const TARIF_DENDA = 'tarif_denda';
    public const TARIF_MAX = 1000000;

    public function __construct(private PDO $db, private ActivityLog $logs)
    {
    }

    /** Rupiah per late day per unit. Missing/invalid value falls back to 0. */
    public function tarifDenda(): int
    {
        $stmt = $this->db->prepare('SELECT nilai FROM pengaturan WHERE kunci = ? LIMIT 1');
        $stmt->execute([self::TARIF_DENDA]);
        $value = $stmt->fetchColumn();

        return is_string($value) && preg_match('/^\d{1,7}$/', $value) === 1 ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function parse(array $input): array
    {
        $raw = is_string($input['tarif_denda'] ?? null) ? trim($input['tarif_denda']) : '';
        $values = ['tarif_denda' => $raw];
        $errors = [];
        if ($raw === '') {
            $errors['tarif_denda'] = 'Tarif denda wajib diisi.';
        } elseif (preg_match('/^\d{1,7}$/', $raw) !== 1 || (int) $raw > self::TARIF_MAX) {
            $errors['tarif_denda'] = 'Tarif harus bilangan bulat 0–1.000.000 rupiah per hari per unit.';
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public function save(int $tarif, int $actorId): void
    {
        $old = $this->tarifDenda();
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
            )->execute([self::TARIF_DENDA, (string) $tarif]);
            $this->logs->record(
                $actorId,
                'update',
                'pengaturan',
                null,
                'Tarif denda per hari per unit: Rp ' . $old . ' -> Rp ' . $tarif
            );
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
