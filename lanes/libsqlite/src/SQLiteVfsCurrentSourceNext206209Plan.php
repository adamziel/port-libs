<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext206209Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if ($op['kind'] === 'snapshot') {
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('snapshot', 'missing-source', $source, $before, self::summary($state), []);
                    continue;
                }
                $snapshot = self::snapshotName((string) ($op['snapshot'] ?? $source . '-snapshot'));
                $sourceState = $state['sources'][$source];
                $dirtyCount = count($sourceState['dirty_pages']);
                $lastCheckpoint = self::lastCheckpoint($sourceState);
                $status = $dirtyCount === 0 && $lastCheckpoint !== null ? 'captured' : 'blocked-unpublished';
                if ($status === 'captured') {
                    $state['snapshots'][$snapshot] = [
                        'source' => $source,
                        'handle' => $sourceState['handle'],
                        'path' => $sourceState['path'],
                        'owner' => $sourceState['owner'],
                        'data_version' => $sourceState['data_version'],
                        'durable_count' => count($sourceState['durable_receipts']),
                        'checkpoint_token' => $lastCheckpoint['token'],
                    ];
                }
                $events[] = self::event('snapshot', $status, $source, $before, self::summary($state), [
                    'snapshot' => $snapshot,
                    'dirty_count' => $dirtyCount,
                    'checkpoint_token' => $lastCheckpoint['token'] ?? null,
                ]);
                continue;
            }

            if ($op['kind'] === 'reuse') {
                $snapshot = self::snapshotName((string) ($op['snapshot'] ?? ''));
                if (!isset($state['snapshots'][$snapshot])) {
                    $events[] = self::event('reuse', 'missing-snapshot', $source, $before, self::summary($state), ['snapshot' => $snapshot]);
                    continue;
                }
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('reuse', 'missing-source', $source, $before, self::summary($state), ['snapshot' => $snapshot]);
                    continue;
                }
                $snapshotState = $state['snapshots'][$snapshot];
                $sourceState = $state['sources'][$source];
                $reasons = self::reuseBlockers($snapshotState, $sourceState);
                $events[] = self::event('reuse', $reasons === [] ? 'reused' : 'blocked-stale', $source, $before, self::summary($state), [
                    'snapshot' => $snapshot,
                    'blocked_reasons' => $reasons,
                    'snapshot_data_version' => $snapshotState['data_version'],
                    'source_data_version' => $sourceState['data_version'],
                ]);
                continue;
            }

            if ($op['kind'] === 'publish') {
                if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                    $events[] = self::event('publish', 'missing-source', $source, $before, self::summary($state), []);
                    continue;
                }
                $token = self::token((string) ($op['token'] ?? 'publish'), 'publish token');
                $sourceState = $state['sources'][$source];
                $dirtyCount = count($sourceState['dirty_pages']);
                if ($dirtyCount > 0) {
                    $events[] = self::event('publish', 'blocked-dirty', $source, $before, self::summary($state), [
                        'token' => $token,
                        'dirty_count' => $dirtyCount,
                    ]);
                    continue;
                }
                $state['sources'][$source]['published'][] = [
                    'token' => $token,
                    'data_version' => $sourceState['data_version'],
                    'durable_count' => count($sourceState['durable_receipts']),
                ];
                $events[] = self::event('publish', 'published', $source, $before, self::summary($state), [
                    'token' => $token,
                    'published_count' => count($state['sources'][$source]['published']),
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-dirty-flush-checkpoint-next198-201',
                'vfs-current-source-ready-next202-205',
                'vfs-current-source-snapshot-reuse-next206-209',
            ],
            'non_overlap' => 'next206-209 fences clean current-source snapshot reuse after next198-201 dirty flush/checkpoint and ready next202-205 publication; it does not repeat open/write/flush/checkpoint mechanics.',
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = ['current_source' => null, 'sources' => [], 'snapshots' => []];
        if (!is_array($current)) {
            return $state;
        }
        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::sourceName((string) $name);
            $state['sources'][$sourceName] = [
                'handle' => self::token((string) ($source['handle'] ?? $sourceName), 'handle'),
                'path' => self::pathName((string) ($source['path'] ?? '')),
                'owner' => self::pathName((string) ($source['owner'] ?? $source['path'] ?? '')),
                'closed' => (bool) ($source['closed'] ?? false),
                'data_version' => self::nonNegativeInt($source['data_version'] ?? 0, 'data version'),
                'dirty_pages' => self::dirtyPageMap($source['dirty_pages'] ?? []),
                'durable_receipts' => self::receiptList($source['durable_receipts'] ?? []),
                'checkpoints' => self::checkpointList($source['checkpoints'] ?? []),
                'published' => self::checkpointList($source['published'] ?? []),
            ];
        }
        foreach (is_array($current['snapshots'] ?? null) ? $current['snapshots'] : [] as $name => $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $state['snapshots'][self::snapshotName((string) $name)] = [
                'source' => self::sourceName((string) ($snapshot['source'] ?? 'main')),
                'handle' => self::token((string) ($snapshot['handle'] ?? $name), 'snapshot handle'),
                'path' => self::pathName((string) ($snapshot['path'] ?? '')),
                'owner' => self::pathName((string) ($snapshot['owner'] ?? $snapshot['path'] ?? '')),
                'data_version' => self::nonNegativeInt($snapshot['data_version'] ?? 0, 'snapshot data version'),
                'durable_count' => self::nonNegativeInt($snapshot['durable_count'] ?? 0, 'snapshot durable count'),
                'checkpoint_token' => self::token((string) ($snapshot['checkpoint_token'] ?? 'checkpoint'), 'snapshot checkpoint token'),
            ];
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::sourceName((string) $current['current_source']);
        }
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'capturesnapshot', 'snapshotcapture' => 'snapshot',
                'reusesnapshot', 'snapshotreuse' => 'reuse',
                'publishsource', 'sourcepublish' => 'publish',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^snapshot\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'snapshot', 'snapshot' => $matches['snapshot']];
        }
        if (preg_match('/^reuse\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'reuse', 'snapshot' => $matches['snapshot']];
        }
        if (preg_match('/^publish\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'publish', 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next206-209 operation is unsupported');
    }

    private static function summary(array $state): array
    {
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'snapshot_count' => count($state['snapshots']),
            'sources' => $state['sources'],
            'snapshots' => $state['snapshots'],
        ];
    }

    /** @return array<string> */
    private static function reuseBlockers(array $snapshot, array $source): array
    {
        $reasons = [];
        if ($snapshot['handle'] !== $source['handle']) {
            $reasons[] = 'handle-changed';
        }
        if ($snapshot['path'] !== $source['path'] || $snapshot['owner'] !== $source['owner']) {
            $reasons[] = 'source-owner-changed';
        }
        if ($snapshot['data_version'] !== $source['data_version']) {
            $reasons[] = 'data-version-changed';
        }
        if ($snapshot['durable_count'] !== count($source['durable_receipts'])) {
            $reasons[] = 'durable-count-changed';
        }
        if (count($source['dirty_pages']) > 0) {
            $reasons[] = 'dirty-pages-present';
        }
        return $reasons;
    }

    private static function event(string $kind, string $status, string $source, array $before, array $next, array $extra): array
    {
        return ['kind' => $kind, 'status' => $status, 'source' => $source, 'before' => $before, 'next' => $next] + $extra;
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::sourceName((string) $source);
        }
        if (is_string($state['current_source'])) {
            return $state['current_source'];
        }
        return 'main';
    }

    private static function lastCheckpoint(array $source): ?array
    {
        if ($source['checkpoints'] === []) {
            return null;
        }
        return $source['checkpoints'][array_key_last($source['checkpoints'])];
    }

    private static function sourceName(string $name): string
    {
        return self::token($name, 'source name');
    }

    private static function snapshotName(string $name): string
    {
        return self::token($name, 'snapshot name');
    }

    private static function pathName(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 requires valid paths');
        }
        return $path;
    }

    private static function token(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:\/-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 requires valid ' . $label);
        }
        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 requires non-negative ' . $label);
        }
        return $value;
    }

    /** @return array<int, array{page:int, bytes:int, digest:string}> */
    private static function dirtyPageMap(mixed $pages): array
    {
        $map = [];
        foreach (self::receiptList($pages) as $receipt) {
            $map[$receipt['page']] = $receipt;
        }
        ksort($map);
        return $map;
    }

    /** @return list<array{page:int, bytes:int, digest:string}> */
    private static function receiptList(mixed $receipts): array
    {
        $result = [];
        foreach (is_array($receipts) ? $receipts : [] as $receipt) {
            if (!is_array($receipt)) {
                continue;
            }
            $result[] = [
                'page' => self::positiveInt($receipt['page'] ?? null, 'receipt page'),
                'bytes' => self::positiveInt($receipt['bytes'] ?? null, 'receipt bytes'),
                'digest' => self::token((string) ($receipt['digest'] ?? ''), 'receipt digest'),
            ];
        }
        return $result;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next206-209 requires positive ' . $label);
        }
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private static function checkpointList(mixed $checkpoints): array
    {
        $result = [];
        foreach (is_array($checkpoints) ? $checkpoints : [] as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }
            $result[] = [
                'token' => self::token((string) ($checkpoint['token'] ?? ''), 'checkpoint token'),
                'data_version' => self::nonNegativeInt($checkpoint['data_version'] ?? 0, 'checkpoint data version'),
                'durable_count' => self::nonNegativeInt($checkpoint['durable_count'] ?? 0, 'checkpoint durable count'),
            ];
        }
        return $result;
    }
}
