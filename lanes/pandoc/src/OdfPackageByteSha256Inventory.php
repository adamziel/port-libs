<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfPackageByteSha256Inventory
{
    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(array $parts): array
    {
        $groups = [];

        foreach ($parts as $name => $part) {
            if (!is_array($part) || ($part['isDirectory'] ?? false) === true) {
                continue;
            }

            $sha256 = is_string($part['byteSha256'] ?? null) && $part['byteSha256'] !== ''
                ? strtolower($part['byteSha256'])
                : null;
            if ($sha256 === null) {
                continue;
            }

            $entryName = self::entryName($part, (string) $name);
            $byteLength = self::intField($part, 'byteLength');
            $compressedByteLength = self::intField($part, 'compressedByteLength');
            $sourceRecordBytes = self::intField($part, 'zipSourceRecordBytes');
            $compressionMethodKey = is_int($part['compressionMethod'] ?? null)
                ? (string) $part['compressionMethod']
                : '(missing)';
            $byteExposurePolicy = self::stringKey($part['byteExposurePolicy'] ?? null);
            $manifestMediaFamily = self::stringKey($part['manifestMediaFamily'] ?? null);
            $manifestMediaTypeBase = self::stringKey($part['manifestMediaTypeBase'] ?? null);
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));

            if (!isset($groups[$sha256])) {
                $groups[$sha256] = [
                    'byteSha256' => $sha256,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'sourceRecordBytes' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'manifestDeclaredEntryCount' => 0,
                    'undeclaredEntryCount' => 0,
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                ];
            }

            ++$groups[$sha256]['entryCount'];
            $groups[$sha256]['entryNames'][] = $entryName;
            $groups[$sha256]['byteLength'] += $byteLength;
            $groups[$sha256]['compressedByteLength'] += $compressedByteLength;
            $groups[$sha256]['sourceRecordBytes'] += $sourceRecordBytes;
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$groups[$sha256]['exposableEntryCount'];
            } else {
                ++$groups[$sha256]['blockedEntryCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$groups[$sha256]['manifestDeclaredEntryCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$groups[$sha256]['undeclaredEntryCount'];
            }

            $groups[$sha256]['compressionMethodCounts'][$compressionMethodKey] =
                ($groups[$sha256]['compressionMethodCounts'][$compressionMethodKey] ?? 0) + 1;
            $groups[$sha256]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($groups[$sha256]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $groups[$sha256]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($groups[$sha256]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $groups[$sha256]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($groups[$sha256]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;

            foreach ($roles as $role) {
                if ($role === '') {
                    continue;
                }

                $groups[$sha256]['roleCounts'][$role] = ($groups[$sha256]['roleCounts'][$role] ?? 0) + 1;
            }
        }

        $counts = [];
        $byteLengths = [];
        $compressedByteLengths = [];
        $sourceRecordBytes = [];
        $entryNamesBySha256 = [];
        $duplicateGroups = [];
        $duplicateEntryCount = 0;

        ksort($groups, SORT_STRING);
        foreach ($groups as $sha256 => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $groups[$sha256] = $summary;

            $counts[$sha256] = $summary['entryCount'];
            $byteLengths[$sha256] = $summary['byteLength'];
            $compressedByteLengths[$sha256] = $summary['compressedByteLength'];
            $sourceRecordBytes[$sha256] = $summary['sourceRecordBytes'];
            $entryNamesBySha256[$sha256] = $summary['entryNames'];

            if ($summary['entryCount'] > 1) {
                $duplicateGroups[] = $summary;
                $duplicateEntryCount += $summary['entryCount'];
            }
        }

        return [
            'packageByteSha256EntryCount' => array_sum($counts),
            'packageByteSha256Count' => count($groups),
            'packageDuplicateByteSha256Count' => count($duplicateGroups),
            'packageDuplicateByteSha256EntryCount' => $duplicateEntryCount,
            'packageByteSha256Counts' => $counts,
            'packageByteSha256ByteLengths' => $byteLengths,
            'packageByteSha256CompressedByteLengths' => $compressedByteLengths,
            'packageByteSha256SourceRecordBytes' => $sourceRecordBytes,
            'entryNamesByPackageByteSha256' => $entryNamesBySha256,
            'packageByteSha256Summaries' => array_values($groups),
            'packageDuplicateByteSha256Summaries' => $duplicateGroups,
        ];
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function intField(array $part, string $field): int
    {
        $value = $part[$field] ?? null;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function entryName(array $part, string $fallback): string
    {
        if (is_string($part['path'] ?? null) && $part['path'] !== '') {
            return $part['path'];
        }
        if (is_string($part['part'] ?? null) && $part['part'] !== '') {
            return $part['part'];
        }

        return $fallback;
    }

    private static function stringKey(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '(missing)';
    }
}
