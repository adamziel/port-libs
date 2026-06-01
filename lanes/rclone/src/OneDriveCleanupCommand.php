<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Credential-free model of OneDrive CleanUp command behavior.
 *
 * Upstream walks every object and calls deleteVersions concurrently. Per-object
 * delete/list errors are logged and do not fail the command; traversal/type
 * errors still fail the command.
 */
final class OneDriveCleanupCommand
{
    /**
     * @param list<array{remote: string, versions: list<array{id?: string, ID?: string}|string>, type?: string, deleteErrors?: array<string, string>, listError?: string}> $entries
     * @param array{dryRun?: bool, noVersions?: bool, walkError?: string, featureAvailable?: bool, remoteArgs?: list<string>} $options
     * @return array{walkedObjects: int, versionRequests: int, deletedVersions: list<string>, skippedVersions: list<string>, logs: list<string>, error: ?string, providerCalled: bool, stoppedAt: string}
     */
    public static function run(array $entries, array $options = []): array
    {
        $flow = [
            'walkedObjects' => 0,
            'versionRequests' => 0,
            'deletedVersions' => [],
            'skippedVersions' => [],
            'logs' => [],
            'error' => null,
            'providerCalled' => false,
            'stoppedAt' => 'start',
        ];

        $argError = self::validateRemoteArgs($options['remoteArgs'] ?? ['onedrive:']);
        if ($argError !== null) {
            $flow['error'] = $argError;
            $flow['stoppedAt'] = 'command-arity';

            return $flow;
        }

        if (!(bool) ($options['noVersions'] ?? true)) {
            $flow['stoppedAt'] = 'disabled-no-versions';

            return $flow;
        }

        if (!(bool) ($options['featureAvailable'] ?? true)) {
            $flow['error'] = 'cleanup unsupported';
            $flow['stoppedAt'] = 'feature-gate';

            return $flow;
        }

        $walkError = self::optionalString($options['walkError'] ?? null);
        if ($walkError !== null && $walkError !== '') {
            $flow['error'] = $walkError;
            $flow['stoppedAt'] = 'walk';

            return $flow;
        }

        $dryRun = (bool) ($options['dryRun'] ?? false);
        foreach ($entries as $entry) {
            if (($entry['type'] ?? 'object') !== 'object') {
                $flow['error'] = 'internal error: not a onedrive object';
                $flow['stoppedAt'] = 'type-check';

                return $flow;
            }

            $flow['walkedObjects']++;
            $flow['providerCalled'] = true;
            $cleanup = OneDriveVersionCleaner::deleteOldVersions(
                $entry['remote'],
                $entry['versions'],
                [
                    'dryRun' => $dryRun,
                    'deleteErrors' => $entry['deleteErrors'] ?? [],
                    'listError' => $entry['listError'] ?? null,
                ],
            );

            $flow['versionRequests']++;
            $flow['deletedVersions'] = array_merge($flow['deletedVersions'], self::tagged($entry['remote'], $cleanup['deletedVersions']));
            $flow['skippedVersions'] = array_merge($flow['skippedVersions'], self::tagged($entry['remote'], $cleanup['skippedVersions']));

            if ($cleanup['error'] !== null) {
                $flow['logs'][] = $entry['remote'] . ': Failed to remove versions: ' . $cleanup['error'];
            }
        }

        $flow['stoppedAt'] = 'complete';

        return $flow;
    }

    /**
     * @param list<array{remote: string, versions: list<array{id?: string, ID?: string}|string>, type?: string, deleteErrors?: array<string, string>, listError?: string}> $entries
     * @param array{dryRun?: bool, noVersions?: bool, walkError?: string, featureAvailable?: bool, fs?: string, remoteArgs?: list<string>} $options
     * @return array{walkedObjects: int, versionRequests: int, deletedVersions: list<string>, skippedVersions: list<string>, logs: list<string>, error: ?string, providerCalled: bool, stoppedAt: string}
     */
    public static function runRemoteControl(array $entries, array $options = []): array
    {
        $fs = self::optionalString($options['fs'] ?? null);
        if ($fs === null || $fs === '') {
            $flow = self::emptyFlow();
            $flow['error'] = 'rc operations/cleanup requires fs';
            $flow['stoppedAt'] = 'rc-fs';

            return $flow;
        }

        $cleanupOptions = $options;
        unset($cleanupOptions['remoteArgs']);

        return self::run($entries, $cleanupOptions);
    }

    /**
     * @param list<string> $remoteArgs
     */
    public static function validateRemoteArgs(array $remoteArgs): ?string
    {
        $count = count($remoteArgs);
        if ($count === 1 && $remoteArgs[0] !== '') {
            return null;
        }

        return 'cleanup command expects exactly one remote argument';
    }

    /**
     * @return array{walkedObjects: int, versionRequests: int, deletedVersions: list<string>, skippedVersions: list<string>, logs: list<string>, error: ?string, providerCalled: bool, stoppedAt: string}
     */
    private static function emptyFlow(): array
    {
        return [
            'walkedObjects' => 0,
            'versionRequests' => 0,
            'deletedVersions' => [],
            'skippedVersions' => [],
            'logs' => [],
            'error' => null,
            'providerCalled' => false,
            'stoppedAt' => 'start',
        ];
    }

    /**
     * @param list<string> $versions
     * @return list<string>
     */
    private static function tagged(string $remote, array $versions): array
    {
        return array_map(static fn (string $version): string => $remote . '#' . $version, $versions);
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
