<?php

declare(strict_types=1);

namespace Develate\AntigravityCli\Report;

/**
 * Reads the tab-separated report `/usage` prints.
 *
 * Every field is optional in practice, so a row missing a percentage or a reset
 * time still yields an entry with those parts null rather than being dropped.
 */
final class ReportParser
{
    public function parseQuota(string $text, string $diagnostics = '', ?int $exitCode = null): QuotaReport
    {
        if ($exitCode !== null && $exitCode !== 0) {
            return QuotaReport::unavailable($text, $diagnostics, $exitCode);
        }

        $entries = [];

        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = rtrim($line);
            if (trim($line) === '' || !str_contains($line, "\t")) {
                continue;
            }

            $columns = explode("\t", $line);
            $group = trim($columns[0] ?? '');
            if ($group === '') {
                continue;
            }

            $entries[] = new QuotaEntry(
                group: $group,
                limit: trim($columns[1] ?? ''),
                remainingPercent: self::percent($columns[2] ?? null),
                resetsAt: self::timestamp($columns[3] ?? null),
            );
        }

        return $entries === []
            ? QuotaReport::unavailable($text, $diagnostics, $exitCode)
            : new QuotaReport(true, $entries, $text, $diagnostics, $exitCode);
    }

    private static function percent(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '' || !str_ends_with($value, '%')) {
            return null;
        }

        $number = trim(substr($value, 0, -1));

        return is_numeric($number) ? (float) $number : null;
    }

    private static function timestamp(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
