<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Deterministic model of OneDrive Object.deleteVersions/deleteVersion.
 *
 * Upstream keeps the first Graph version entry as the current object and
 * deletes only older entries. This helper exposes the same order and error
 * boundary without Graph calls or provider credentials.
 */
final class OneDriveVersionCleaner
{
    /**
     * @param list<array{id?: string, ID?: string}|string> $versions
     * @param array{dryRun?: bool, deleteErrors?: array<string, string>, listError?: string} $options
     * @return array{remote: string, fetchedVersions: int, keptVersion: ?string, deletedVersions: list<string>, skippedVersions: list<string>, sequence: list<string>, error: ?string}
     */
    public static function deleteOldVersions(string $remote, array $versions, array $options = []): array
    {
        $flow = [
            'remote' => $remote,
            'fetchedVersions' => count($versions),
            'keptVersion' => null,
            'deletedVersions' => [],
            'skippedVersions' => [],
            'sequence' => [
                'get-versions',
            ],
            'error' => null,
        ];

        $listError = self::optionalString($options['listError'] ?? null);
        if ($listError !== null && $listError !== '') {
            $flow['error'] = $listError;

            return $flow;
        }

        if (count($versions) < 2) {
            return $flow;
        }

        $flow['keptVersion'] = self::versionId($versions[0]);
        $deleteErrors = is_array($options['deleteErrors'] ?? null) ? $options['deleteErrors'] : [];
        $dryRun = (bool) ($options['dryRun'] ?? false);

        foreach (array_slice($versions, 1) as $version) {
            $id = self::versionId($version);
            $flow['sequence'][] = 'delete-version:' . $id;

            if ($dryRun) {
                $flow['skippedVersions'][] = $id;
                continue;
            }

            $deleteError = self::optionalString($deleteErrors[$id] ?? null);
            if ($deleteError !== null && $deleteError !== '') {
                $flow['error'] = $deleteError;

                return $flow;
            }

            $flow['deletedVersions'][] = $id;
        }

        return $flow;
    }

    /**
     * @param array{id?: string, ID?: string}|string $version
     */
    private static function versionId(array|string $version): string
    {
        if (is_string($version)) {
            return $version;
        }

        return (string) ($version['id'] ?? $version['ID'] ?? '');
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
