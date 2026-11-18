<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;

/**
 * Input validation for the returns module. Quantity and fine are never read
 * from request input; they are derived from the locked loan and the tariff.
 */
final class PengembalianService
{
    public const KONDISI = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];
    private const STATUS_FILTERS = ['active', 'return_requested', 'returned', 'all'];

    /**
     * @param array<string, mixed> $input
     * @return array{values: array<string, string>, errors: array<string, string>}
     */
    public function validateVerify(array $input): array
    {
        $values = [
            'tanggal_diterima' => is_string($input['tanggal_diterima'] ?? null)
                ? trim($input['tanggal_diterima']) : '',
            'kondisi' => is_string($input['kondisi'] ?? null) ? trim($input['kondisi']) : '',
        ];
        $errors = [];
        if (!self::isDate($values['tanggal_diterima'])) {
            $errors['tanggal_diterima'] = 'Tanggal diterima tidak valid.';
        }
        if (!in_array($values['kondisi'], self::KONDISI, true)) {
            $errors['kondisi'] = 'Kondisi alat tidak valid.';
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public function statusFilter(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';
        return in_array($status, self::STATUS_FILTERS, true) ? $status : 'active';
    }

    public function search(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
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
