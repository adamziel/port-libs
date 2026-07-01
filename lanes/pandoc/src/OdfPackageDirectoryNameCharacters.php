<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfPackageDirectoryNameCharacters
{
    /**
     * @return list<string>
     */
    public static function flags(string $directory): array
    {
        if ($directory === '' || $directory === '/') {
            return [];
        }

        $flags = [];
        if (preg_match('/[A-Z]/', $directory) === 1) {
            $flags[] = 'uppercase';
        }
        if (preg_match('/[ \t\r\n\f\v]/', $directory) === 1) {
            $flags[] = 'whitespace';
        }
        if (preg_match('/%[0-9A-Fa-f]{2}/', $directory) === 1) {
            $flags[] = 'percent-encoded-octet';
        }
        if (preg_match('/[^\x00-\x7F]/', $directory) === 1) {
            $flags[] = 'non-ascii';
        }

        return $flags;
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(array $parts, string $entryNameKey, string $pathShapeKey): array
    {
        $directories = [];
        $flagEntryCounts = [];
        $flagDirectories = [];
        $flagEntryNames = [];
        $entryNames = [];

        foreach ($parts as $fallbackName => $part) {
            if (!is_array($part)) {
                continue;
            }

            $entryName = self::entryName($part, $entryNameKey, (string) $fallbackName);
            if ($entryName === '') {
                continue;
            }

            $pathShape = is_array($part[$pathShapeKey] ?? null) ? $part[$pathShapeKey] : [];
            $directory = self::directory($part, $pathShape, $entryName);
            if ($directory === null || $directory === '' || $directory === '/') {
                continue;
            }

            $flags = is_array($part['directoryNameCharacterFlags'] ?? null)
                ? array_values(array_filter(
                    array_map('strval', $part['directoryNameCharacterFlags']),
                    static fn (string $flag): bool => $flag !== ''
                ))
                : self::flags($directory);
            if ($flags === []) {
                continue;
            }

            $byteLength = self::intField($part, 'byteLength');
            $compressedByteLength = self::intField($part, 'compressedByteLength');
            $sourceRecordBytes = self::intField($part, 'zipSourceRecordBytes');
            $directoryDepth = self::directoryDepth($part, $pathShape, $directory);
            $basename = self::basename($part, $pathShape, $entryName);
            $packagePartExtension = self::nullableString($part['packagePartExtension'] ?? null);
            $packagePartExtensionKey = $packagePartExtension === null || $packagePartExtension === ''
                ? '(none)'
                : $packagePartExtension;
            $byteExposurePolicy = self::stringKey($part['byteExposurePolicy'] ?? null);
            $manifestMediaFamily = self::stringKey($part['manifestMediaFamily'] ?? null);
            $manifestMediaTypeBase = self::stringKey($part['manifestMediaTypeBase'] ?? null);
            $roles = self::roles($part);

            if (!isset($directories[$directory])) {
                $directories[$directory] = [
                    'directory' => $directory,
                    'directoryDepth' => $directoryDepth,
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'sourceRecordBytes' => 0,
                    'declaredEntryCount' => 0,
                    'undeclaredEntryCount' => 0,
                    'encryptedEntryCount' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'flags' => $flags,
                    'flagEntryCounts' => [],
                    'basenameCounts' => [],
                    'packagePartExtensionCounts' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'entryNames' => [],
                    'largestEntry' => null,
                    'reviewPolicy' => 'odf-package-directory-name-character-metadata-only',
                    'byteExposurePolicy' => 'odf-package-directory-name-character-metadata-only',
                    'canExposeBytes' => false,
                ];
            }

            ++$directories[$directory]['entryCount'];
            $directories[$directory]['byteLength'] += $byteLength;
            $directories[$directory]['compressedByteLength'] += $compressedByteLength;
            $directories[$directory]['sourceRecordBytes'] += $sourceRecordBytes;
            $entryNames[$entryName] = true;

            if (($part['isDirectory'] ?? false) === true) {
                ++$directories[$directory]['directoryEntryCount'];
            } else {
                ++$directories[$directory]['fileEntryCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$directories[$directory]['declaredEntryCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$directories[$directory]['undeclaredEntryCount'];
            }
            if (($part['encrypted'] ?? false) === true) {
                ++$directories[$directory]['encryptedEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$directories[$directory]['exposableEntryCount'];
            } else {
                ++$directories[$directory]['blockedEntryCount'];
            }

            foreach ($flags as $flag) {
                $flagEntryCounts[$flag] = ($flagEntryCounts[$flag] ?? 0) + 1;
                $flagDirectories[$flag][$directory] = true;
                $flagEntryNames[$flag][$entryName] = true;
                $directories[$directory]['flagEntryCounts'][$flag] =
                    ($directories[$directory]['flagEntryCounts'][$flag] ?? 0) + 1;
            }

            $directories[$directory]['basenameCounts'][$basename] =
                ($directories[$directory]['basenameCounts'][$basename] ?? 0) + 1;
            $directories[$directory]['packagePartExtensionCounts'][$packagePartExtensionKey] =
                ($directories[$directory]['packagePartExtensionCounts'][$packagePartExtensionKey] ?? 0) + 1;
            $directories[$directory]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($directories[$directory]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $directories[$directory]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($directories[$directory]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $directories[$directory]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($directories[$directory]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            foreach ($roles as $role) {
                $directories[$directory]['roleCounts'][$role] =
                    ($directories[$directory]['roleCounts'][$role] ?? 0) + 1;
            }
            $directories[$directory]['entryNames'][$entryName] = true;

            $entrySummary = [
                'entryName' => $entryName,
                'directory' => $directory,
                'directoryDepth' => $directoryDepth,
                'basename' => $basename,
                'packagePartExtension' => $packagePartExtension,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'sourceRecordBytes' => $sourceRecordBytes,
                'crc32' => self::nullableString($part['crc32'] ?? null),
                'manifestMediaTypeBase' => self::nullableString($part['manifestMediaTypeBase'] ?? null),
                'manifestMediaFamily' => self::nullableString($part['manifestMediaFamily'] ?? null),
                'byteExposurePolicy' => self::nullableString($part['byteExposurePolicy'] ?? null),
                'roles' => $roles,
            ];
            $largestEntry = $directories[$directory]['largestEntry'];
            if (
                !is_array($largestEntry)
                || $entrySummary['byteLength'] > (int) ($largestEntry['byteLength'] ?? 0)
                || (
                    $entrySummary['byteLength'] === (int) ($largestEntry['byteLength'] ?? 0)
                    && strcmp($entrySummary['entryName'], (string) ($largestEntry['entryName'] ?? '')) < 0
                )
            ) {
                $directories[$directory]['largestEntry'] = $entrySummary;
            }
        }

        ksort($flagEntryCounts, SORT_STRING);
        $flagDirectories = self::stringSetMapToLists($flagDirectories);
        $flagEntryNames = self::stringSetMapToLists($flagEntryNames);

        ksort($directories, SORT_STRING);
        foreach ($directories as &$directory) {
            ksort($directory['flagEntryCounts'], SORT_STRING);
            ksort($directory['basenameCounts'], SORT_STRING);
            ksort($directory['packagePartExtensionCounts'], SORT_STRING);
            ksort($directory['roleCounts'], SORT_STRING);
            ksort($directory['byteExposurePolicyCounts'], SORT_STRING);
            ksort($directory['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($directory['manifestMediaTypeBaseCounts'], SORT_STRING);
            sort($directory['flags'], SORT_STRING);
            $directory['entryNames'] = array_keys($directory['entryNames']);
            sort($directory['entryNames'], SORT_STRING);
        }
        unset($directory);

        return [
            'directoryCount' => count($directories),
            'entryCount' => count($entryNames),
            'directoryNames' => array_keys($directories),
            'flagEntryCounts' => $flagEntryCounts,
            'flagDirectories' => $flagDirectories,
            'flagEntryNames' => $flagEntryNames,
            'directories' => array_values($directories),
        ];
    }

    /**
     * @param array<string, array<string, bool>> $sets
     * @return array<string, list<string>>
     */
    private static function stringSetMapToLists(array $sets): array
    {
        ksort($sets, SORT_STRING);
        $lists = [];
        foreach ($sets as $key => $set) {
            $values = array_keys($set);
            sort($values, SORT_STRING);
            $lists[$key] = $values;
        }

        return $lists;
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function entryName(array $part, string $entryNameKey, string $fallbackName): string
    {
        foreach ([$entryNameKey, 'path', 'part'] as $key) {
            if (is_string($part[$key] ?? null) && $part[$key] !== '') {
                return $part[$key];
            }
        }

        return $fallbackName;
    }

    /**
     * @param array<string, mixed> $part
     * @param array<string, mixed> $pathShape
     */
    private static function directory(array $part, array $pathShape, string $entryName): ?string
    {
        foreach ([$part['packageDirectory'] ?? null, $pathShape['directory'] ?? null] as $directory) {
            if (is_string($directory) && $directory !== '') {
                return $directory;
            }
        }

        $position = strrpos($entryName, '/');
        if ($position === false) {
            return null;
        }

        return substr($entryName, 0, $position + 1);
    }

    /**
     * @param array<string, mixed> $part
     * @param array<string, mixed> $pathShape
     */
    private static function directoryDepth(array $part, array $pathShape, string $directory): int
    {
        if (is_int($pathShape['directorySegmentCount'] ?? null)) {
            return $pathShape['directorySegmentCount'];
        }
        if (is_int($part['packagePathDepth'] ?? null)) {
            return max(0, $part['packagePathDepth'] - (($part['isDirectory'] ?? false) === true ? 0 : 1));
        }

        $trimmed = trim($directory, '/');

        return $trimmed === '' ? 0 : substr_count($trimmed, '/') + 1;
    }

    /**
     * @param array<string, mixed> $part
     * @param array<string, mixed> $pathShape
     */
    private static function basename(array $part, array $pathShape, string $entryName): string
    {
        foreach ([$part['packageBasename'] ?? null, $pathShape['basename'] ?? null] as $basename) {
            if (is_string($basename) && $basename !== '') {
                return $basename;
            }
        }

        $trimmed = rtrim($entryName, '/');
        $position = strrpos($trimmed, '/');

        return $position === false ? $trimmed : substr($trimmed, $position + 1);
    }

    /**
     * @param array<string, mixed> $part
     * @return list<string>
     */
    private static function roles(array $part): array
    {
        $roles = is_array($part['roles'] ?? null) ? $part['roles'] : [];

        return array_values(array_filter(
            array_map('strval', $roles),
            static fn (string $role): bool => $role !== ''
        ));
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function intField(array $part, string $field): int
    {
        return is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function stringKey(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '(missing)';
    }
}
