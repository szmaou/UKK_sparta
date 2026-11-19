<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Alat;
use App\Models\Peminjaman;
use DateTimeImmutable;

/**
 * Validation and orchestration for borrower requests. Owner identity is always
 * supplied by the controller from the session, never from request input.
 */
final class PeminjamanService
{
    private const MAX_UNSIGNED_INT = 4294967295;

    public function __construct(private Alat $alat, private Peminjaman $loans)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>, tool: array<string, mixed>|null}
     */
    public function validate(array $input): array
    {
        $values = [
            'alat_id' => is_string($input['alat_id'] ?? null) ? trim($input['alat_id']) : '',
            'jumlah' => is_string($input['jumlah'] ?? null) ? trim($input['jumlah']) : '',
            'tanggal_pinjam' => is_string($input['tanggal_pinjam'] ?? null) ? trim($input['tanggal_pinjam']) : '',
            'tanggal_jatuh_tempo' => is_string($input['tanggal_jatuh_tempo'] ?? null) ? trim($input['tanggal_jatuh_tempo']) : '',
            'tujuan' => is_string($input['tujuan'] ?? null) ? trim($input['tujuan']) : '',
        ];
        $errors = [];

        $alatId = $this->alat->positiveId($values['alat_id']);
        $tool = $alatId === null ? null : $this->alat->find($alatId);
        if ($alatId === null || $tool === null) {
            $errors['alat_id'] = 'Alat tidak ditemukan.';
        }

        $jumlah = null;
        if ($values['jumlah'] === '' || preg_match('/^[1-9]\d{0,9}$/', $values['jumlah']) !== 1
            || (int) $values['jumlah'] > self::MAX_UNSIGNED_INT) {
            $errors['jumlah'] = 'Jumlah harus bilangan bulat lebih dari 0.';
        } else {
            $jumlah = (int) $values['jumlah'];
        }

        if (!self::isDate($values['tanggal_pinjam'])) {
            $errors['tanggal_pinjam'] = 'Tanggal pinjam tidak valid.';
        }
        if (!self::isDate($values['tanggal_jatuh_tempo'])) {
            $errors['tanggal_jatuh_tempo'] = 'Tanggal jatuh tempo tidak valid.';
        }
        if (!isset($errors['tanggal_pinjam']) && !isset($errors['tanggal_jatuh_tempo'])
            && $values['tanggal_jatuh_tempo'] < $values['tanggal_pinjam']) {
            $errors['tanggal_jatuh_tempo'] = 'Jatuh tempo tidak boleh sebelum tanggal pinjam.';
        }

        if ($values['tujuan'] === '') {
            $errors['tujuan'] = 'Tujuan peminjaman wajib diisi.';
        } elseif (mb_strlen($values['tujuan']) > 1000) {
            $errors['tujuan'] = 'Tujuan maksimal 1000 karakter.';
        }

        if (is_array($tool)) {
            if ((int) $tool['is_active'] !== 1) {
                $errors['alat_id'] = 'Alat sedang nonaktif dan tidak dapat diajukan.';
            } elseif ($jumlah !== null && (int) $tool['stok_tersedia'] < $jumlah) {
                $errors['jumlah'] = 'Jumlah melebihi stok tersedia saat ini (' . (int) $tool['stok_tersedia'] . ' unit).';
            }
        }

        return ['values' => $values, 'errors' => $errors, 'tool' => is_array($tool) ? $tool : null];
    }

    /**
     * @param array{alat_id: string, jumlah: string, tanggal_pinjam: string, tanggal_jatuh_tempo: string, tujuan: string} $values
     * @return array{id: int, kode: string}
     */
    public function create(int $userId, array $values, int $actorId): array
    {
        return $this->loans->create($userId, [
            'alat_id' => (int) $values['alat_id'],
            'jumlah' => (int) $values['jumlah'],
            'tanggal_pinjam' => $values['tanggal_pinjam'],
            'tanggal_jatuh_tempo' => $values['tanggal_jatuh_tempo'],
            'tujuan' => $values['tujuan'],
        ], $actorId);
    }

    /**
     * Admin correction of a pending request. Tool, owner and status are fixed;
     * only quantity, dates and note may change.
     *
     * @param array<string, mixed> $input
     * @return array{values: array{jumlah: int, tanggal_pinjam: string, tanggal_jatuh_tempo: string, catatan: ?string}, errors: array<string, string>}
     */
    public function validateAdmin(array $input): array
    {
        $raw = [
            'jumlah' => is_string($input['jumlah'] ?? null) ? trim($input['jumlah']) : '',
            'tanggal_pinjam' => is_string($input['tanggal_pinjam'] ?? null) ? trim($input['tanggal_pinjam']) : '',
            'tanggal_jatuh_tempo' => is_string($input['tanggal_jatuh_tempo'] ?? null) ? trim($input['tanggal_jatuh_tempo']) : '',
            'catatan' => is_string($input['catatan'] ?? null) ? trim($input['catatan']) : '',
        ];
        $errors = [];
        $jumlah = 0;

        if ($raw['jumlah'] === '' || preg_match('/^[1-9]\d{0,9}$/', $raw['jumlah']) !== 1
            || (int) $raw['jumlah'] > self::MAX_UNSIGNED_INT) {
            $errors['jumlah'] = 'Jumlah harus bilangan bulat lebih dari 0.';
        } else {
            $jumlah = (int) $raw['jumlah'];
        }
        if (!self::isDate($raw['tanggal_pinjam'])) {
            $errors['tanggal_pinjam'] = 'Tanggal pinjam tidak valid.';
        }
        if (!self::isDate($raw['tanggal_jatuh_tempo'])) {
            $errors['tanggal_jatuh_tempo'] = 'Tanggal jatuh tempo tidak valid.';
        }
        if (!isset($errors['tanggal_pinjam']) && !isset($errors['tanggal_jatuh_tempo'])
            && $raw['tanggal_jatuh_tempo'] < $raw['tanggal_pinjam']) {
            $errors['tanggal_jatuh_tempo'] = 'Jatuh tempo tidak boleh sebelum tanggal pinjam.';
        }
        if (mb_strlen($raw['catatan']) > 1000) {
            $errors['catatan'] = 'Catatan maksimal 1000 karakter.';
        }

        return [
            'values' => [
                'jumlah' => $jumlah,
                'tanggal_pinjam' => $raw['tanggal_pinjam'],
                'tanggal_jatuh_tempo' => $raw['tanggal_jatuh_tempo'],
                'catatan' => $raw['catatan'] === '' ? null : $raw['catatan'],
            ],
            'errors' => $errors,
        ];
    }

    /** Optional rejection note: trimmed, empty becomes null, max 1000 characters. */
    public function note(mixed $value): ?string
    {
        $note = is_string($value) ? trim($value) : '';
        if ($note === '') {
            return null;
        }
        if (mb_strlen($note) > 1000) {
            throw new \DomainException('Catatan penolakan maksimal 1000 karakter.');
        }
        return $note;
    }

    private static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
