<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfPackageZipSourceRecordByteBucketInventory
{
    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(array $parts): array
    {
        $buckets = [];

        foreach ($parts as $name => $part) {
            if (!is_array($part) || ($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
                continue;
            }

            $entryName = self::entryName($part, (string) $name);
            $sourceRecordBytes = self::intField($part, 'zipSourceRecordBytes');
            $bucket = self::bucket($sourceRecordBytes);
            $bucketKey = $bucket['sourceRecordByteBucket'];
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'sourceRecordByteBucket' => $bucketKey,
                    'minSourceRecordBytes' => $bucket['minSourceRecordBytes'],
                    'maxSourceRecordBytes' => $bucket['maxSourceRecordBytes'],
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'manifestDeclaredEntryCount' => 0,
                    'undeclaredEntryCount' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'localHeaderBytes' => 0,
                    'localHeaderFixedHeaderBytes' => 0,
                    'localHeaderVariableFieldBytes' => 0,
                    'localHeaderRawNameBytes' => 0,
                    'localHeaderExtraFieldBytes' => 0,
                    'localHeaderReviewFieldBytes' => 0,
                    'compressedDataBytes' => 0,
                    'dataDescriptorBytes' => 0,
                    'dataDescriptorEntryCount' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'centralDirectoryFixedHeaderBytes' => 0,
                    'centralDirectoryVariableFieldBytes' => 0,
                    'centralDirectoryRawNameBytes' => 0,
                    'centralDirectoryExtraFieldBytes' => 0,
                    'centralDirectoryRawCommentBytes' => 0,
                    'centralDirectoryReviewFieldBytes' => 0,
                    'compressedByteLength' => 0,
                    'uncompressedByteLength' => 0,
                    'sourceRecordIssueEntryCount' => 0,
                    'sourceRecordIssueCount' => 0,
                    'directoryRootCounts' => [],
                    'packagePartExtensionCounts' => [],
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'entryNames' => [],
                    'largestSourceRecordEntry' => null,
                ];
            }

            $localRecordBytes = self::intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = self::intField($part, 'zipLocalHeaderBytes');
            $localHeaderFixedHeaderBytes = self::intField($part, 'zipLocalHeaderFixedHeaderBytes');
            $localHeaderVariableFieldBytes = self::intField($part, 'zipLocalHeaderVariableFieldBytes');
            $localHeaderRawNameBytes = self::intField($part, 'zipLocalHeaderRawNameBytes');
            $localHeaderExtraFieldBytes = self::intField($part, 'zipLocalHeaderExtraFieldBytes');
            $localHeaderReviewFieldBytes = self::intField($part, 'zipLocalHeaderReviewFieldBytes');
            $compressedDataBytes = self::intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = self::intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = self::intField($part, 'zipCentralDirectoryRecordBytes');
            $centralDirectoryFixedHeaderBytes = self::intField($part, 'zipCentralDirectoryFixedHeaderBytes');
            $centralDirectoryVariableFieldBytes = self::intField($part, 'zipCentralDirectoryVariableFieldBytes');
            $centralDirectoryRawNameBytes = self::intField($part, 'zipCentralDirectoryRawNameBytes');
            $centralDirectoryExtraFieldBytes = self::intField($part, 'zipCentralDirectoryExtraFieldBytes');
            $centralDirectoryRawCommentBytes = self::intField($part, 'zipCentralDirectoryRawCommentBytes');
            $centralDirectoryReviewFieldBytes = self::intField($part, 'zipCentralDirectoryReviewFieldBytes');
            $compressedByteLength = self::intField($part, 'compressedByteLength');
            $uncompressedByteLength = self::intField($part, 'byteLength');
            $directoryRoot = self::directoryRoot($part, $entryName);
            $packagePartExtension = self::packagePartExtension($part, $entryName);
            $packagePartExtensionKey = $packagePartExtension ?? '(extensionless)';
            $compressionMethodKey = is_int($part['compressionMethod'] ?? null)
                ? (string) $part['compressionMethod']
                : '(missing)';
            $byteExposurePolicy = self::stringKey($part['byteExposurePolicy'] ?? null);
            $manifestMediaFamily = self::stringKey($part['manifestMediaFamily'] ?? null);
            $manifestMediaTypeBase = self::stringKey($part['manifestMediaTypeBase'] ?? null);
            $roles = self::roles($part);
            $sourceRecordIssues = self::sourceRecordIssueCodes($part);
            $sourceRecordIssueCount = count($sourceRecordIssues);
            $isDirectory = ($part['isDirectory'] ?? false) === true;
            $usesDataDescriptor = $dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true;

            ++$buckets[$bucketKey]['entryCount'];
            if ($isDirectory) {
                ++$buckets[$bucketKey]['directoryEntryCount'];
            } else {
                ++$buckets[$bucketKey]['fileEntryCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$buckets[$bucketKey]['manifestDeclaredEntryCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$buckets[$bucketKey]['undeclaredEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$buckets[$bucketKey]['exposableEntryCount'];
            } else {
                ++$buckets[$bucketKey]['blockedEntryCount'];
            }
            if ($usesDataDescriptor) {
                ++$buckets[$bucketKey]['dataDescriptorEntryCount'];
            }
            if ($sourceRecordIssueCount > 0) {
                ++$buckets[$bucketKey]['sourceRecordIssueEntryCount'];
            }

            $buckets[$bucketKey]['sourceRecordBytes'] += $sourceRecordBytes;
            $buckets[$bucketKey]['localRecordBytes'] += $localRecordBytes;
            $buckets[$bucketKey]['localHeaderBytes'] += $localHeaderBytes;
            $buckets[$bucketKey]['localHeaderFixedHeaderBytes'] += $localHeaderFixedHeaderBytes;
            $buckets[$bucketKey]['localHeaderVariableFieldBytes'] += $localHeaderVariableFieldBytes;
            $buckets[$bucketKey]['localHeaderRawNameBytes'] += $localHeaderRawNameBytes;
            $buckets[$bucketKey]['localHeaderExtraFieldBytes'] += $localHeaderExtraFieldBytes;
            $buckets[$bucketKey]['localHeaderReviewFieldBytes'] += $localHeaderReviewFieldBytes;
            $buckets[$bucketKey]['compressedDataBytes'] += $compressedDataBytes;
            $buckets[$bucketKey]['dataDescriptorBytes'] += $dataDescriptorBytes;
            $buckets[$bucketKey]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $buckets[$bucketKey]['centralDirectoryFixedHeaderBytes'] += $centralDirectoryFixedHeaderBytes;
            $buckets[$bucketKey]['centralDirectoryVariableFieldBytes'] += $centralDirectoryVariableFieldBytes;
            $buckets[$bucketKey]['centralDirectoryRawNameBytes'] += $centralDirectoryRawNameBytes;
            $buckets[$bucketKey]['centralDirectoryExtraFieldBytes'] += $centralDirectoryExtraFieldBytes;
            $buckets[$bucketKey]['centralDirectoryRawCommentBytes'] += $centralDirectoryRawCommentBytes;
            $buckets[$bucketKey]['centralDirectoryReviewFieldBytes'] += $centralDirectoryReviewFieldBytes;
            $buckets[$bucketKey]['compressedByteLength'] += $compressedByteLength;
            $buckets[$bucketKey]['uncompressedByteLength'] += $uncompressedByteLength;
            $buckets[$bucketKey]['sourceRecordIssueCount'] += $sourceRecordIssueCount;
            $buckets[$bucketKey]['entryNames'][] = $entryName;
            $buckets[$bucketKey]['directoryRootCounts'][$directoryRoot] =
                ($buckets[$bucketKey]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
            $buckets[$bucketKey]['packagePartExtensionCounts'][$packagePartExtensionKey] =
                ($buckets[$bucketKey]['packagePartExtensionCounts'][$packagePartExtensionKey] ?? 0) + 1;
            $buckets[$bucketKey]['compressionMethodCounts'][$compressionMethodKey] =
                ($buckets[$bucketKey]['compressionMethodCounts'][$compressionMethodKey] ?? 0) + 1;
            $buckets[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($buckets[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $buckets[$bucketKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($buckets[$bucketKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $buckets[$bucketKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($buckets[$bucketKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;

            foreach ($roles as $role) {
                $buckets[$bucketKey]['roleCounts'][$role] = ($buckets[$bucketKey]['roleCounts'][$role] ?? 0) + 1;
            }

            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $entrySummary = [
                'entryName' => $entryName,
                'sourceRecordByteBucket' => $bucketKey,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packageBasename' => is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null),
                'packagePartExtension' => $packagePartExtension,
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'isDirectory' => $isDirectory,
                'byteLength' => $uncompressedByteLength,
                'compressedByteLength' => $compressedByteLength,
                'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
                'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'sourceRecordIssueCount' => $sourceRecordIssueCount,
                'sourceRecordIssues' => $sourceRecordIssues,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];

            $largestEntry = $buckets[$bucketKey]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $buckets[$bucketKey]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $orderedSummaries = [];
        $orderedBuckets = [];
        $bucketCounts = [];
        $bucketBytes = [];
        $entryNamesByBucket = [];
        $dataDescriptorEntryCount = 0;
        $issueEntryCount = 0;

        foreach (['up-to-127-bytes', '128-to-511-bytes', '512-to-2047-bytes', '2048-plus-bytes'] as $bucketKey) {
            if (!isset($buckets[$bucketKey])) {
                continue;
            }

            $summary = $buckets[$bucketKey];
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['directoryRootCounts'], SORT_STRING);
            ksort($summary['packagePartExtensionCounts'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);

            $orderedBuckets[] = $bucketKey;
            $bucketCounts[$bucketKey] = $summary['entryCount'];
            $bucketBytes[$bucketKey] = $summary['sourceRecordBytes'];
            $entryNamesByBucket[$bucketKey] = $summary['entryNames'];
            $dataDescriptorEntryCount += $summary['dataDescriptorEntryCount'];
            $issueEntryCount += $summary['sourceRecordIssueEntryCount'];
            $orderedSummaries[] = $summary;
        }

        return [
            'packageZipSourceRecordByteBucketCount' => count($orderedSummaries),
            'packageZipSourceRecordByteBuckets' => $orderedBuckets,
            'packageZipSourceRecordByteBucketCounts' => $bucketCounts,
            'packageZipSourceRecordByteBucketBytes' => $bucketBytes,
            'entryNamesByPackageZipSourceRecordByteBucket' => $entryNamesByBucket,
            'packageZipSourceRecordByteBucketDataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'packageZipSourceRecordByteBucketIssueEntryCount' => $issueEntryCount,
            'packageZipSourceRecordByteBucketSummaries' => $orderedSummaries,
        ];
    }

    /**
     * @return array{sourceRecordByteBucket:string,minSourceRecordBytes:int,maxSourceRecordBytes:int|null}
     */
    private static function bucket(int $sourceRecordBytes): array
    {
        if ($sourceRecordBytes <= 127) {
            return [
                'sourceRecordByteBucket' => 'up-to-127-bytes',
                'minSourceRecordBytes' => 0,
                'maxSourceRecordBytes' => 127,
            ];
        }
        if ($sourceRecordBytes <= 511) {
            return [
                'sourceRecordByteBucket' => '128-to-511-bytes',
                'minSourceRecordBytes' => 128,
                'maxSourceRecordBytes' => 511,
            ];
        }
        if ($sourceRecordBytes <= 2047) {
            return [
                'sourceRecordByteBucket' => '512-to-2047-bytes',
                'minSourceRecordBytes' => 512,
                'maxSourceRecordBytes' => 2047,
            ];
        }

        return [
            'sourceRecordByteBucket' => '2048-plus-bytes',
            'minSourceRecordBytes' => 2048,
            'maxSourceRecordBytes' => null,
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

    /**
     * @param array<string, mixed> $part
     */
    private static function directoryRoot(array $part, string $entryName): string
    {
        if (is_string($part['zipPackageManifestDirectoryRoot'] ?? null) && $part['zipPackageManifestDirectoryRoot'] !== '') {
            return $part['zipPackageManifestDirectoryRoot'];
        }

        $position = strpos($entryName, '/');
        if ($position === false) {
            return '/';
        }

        return substr($entryName, 0, $position + 1);
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function packagePartExtension(array $part, string $entryName): ?string
    {
        if (is_string($part['packagePartExtension'] ?? null) && $part['packagePartExtension'] !== '') {
            return $part['packagePartExtension'];
        }

        $basename = basename($entryName);
        $position = strrpos($basename, '.');
        if ($position === false || $position === strlen($basename) - 1) {
            return null;
        }

        return strtolower(substr($basename, $position + 1));
    }

    /**
     * @param array<string, mixed> $part
     * @return list<string>
     */
    private static function roles(array $part): array
    {
        $roles = array_values(array_unique(array_filter(
            array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
            static fn (string $role): bool => $role !== ''
        )));

        return $roles === [] ? ['package-part'] : $roles;
    }

    /**
     * @param array<string, mixed> $part
     * @return list<string>
     */
    private static function sourceRecordIssueCodes(array $part): array
    {
        $issues = [];
        foreach ([
            'zipLocalHeaderMetadataIssues',
            'zipGeneralPurposeFlagIssues',
            'zipTimestampIssues',
            'zipEntryCommentIssues',
            'zipNameHygieneIssueCodes',
            'zipPackageManifestCreatorHostSystemIssues',
            'zipPackageManifestCaseInsensitiveNameCollisionIssues',
            'creatorHostIssues',
            'platformMetadataIssues',
        ] as $field) {
            foreach (is_array($part[$field] ?? null) ? $part[$field] : [] as $issue) {
                if (is_string($issue) && $issue !== '' && !in_array($issue, $issues, true)) {
                    $issues[] = $issue;
                }
            }
        }

        sort($issues, SORT_STRING);

        return $issues;
    }

    private static function stringKey(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '(missing)';
    }
}
