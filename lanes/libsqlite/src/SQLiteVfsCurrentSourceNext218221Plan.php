<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext218221Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if ($op['kind'] === 'snapshot') {
                $snapshot = self::token((string) ($op['snapshot'] ?? $source . '-ready'), 'snapshot name');
                $events[] = self::snapshot($state, $source, $snapshot, $before);
                continue;
            }

            if ($op['kind'] === 'publish') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot name');
                $token = self::token((string) ($op['token'] ?? 'publish'), 'publish token');
                $events[] = self::publish($state, $source, $snapshot, $token, $before);
                continue;
            }

            if ($op['kind'] === 'reuse') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot name');
                $events[] = self::reuse($state, $source, $snapshot, $before);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $current,
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-snapshot-reuse-next206-209',
                'vfs-current-source-ready-next214-217',
                'vfs-current-source-reuse-publish-next218-221',
            ],
            'non_overlap' => 'next218-221 consumes ready next214-217 current-source snapshots and adds publish receipt fencing; it does not repeat next206-209 capture/reuse mechanics, ready next214-217 hydration, dirty flush, or checkpoint creation.',
        ];
    }

    private static function snapshot(array &$state, string $source, string $snapshot, array $before): array
    {
        if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
            return self::event('snapshot', 'missing-source', $source, $before, self::summary($state), ['snapshot' => $snapshot]);
        }

        $sourceState = $state['sources'][$source];
        $readyReceipt = self::lastReadyReceipt($sourceState);
        $publishReceipt = self::lastPublishReceipt($sourceState);
        if ($readyReceipt === null || $publishReceipt === null || $sourceState['dirty_pages'] !== []) {
            return self::event('snapshot', 'blocked-not-ready', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'dirty_count' => count($sourceState['dirty_pages']),
                'ready_token' => $readyReceipt['token'] ?? null,
                'publish_token' => $publishReceipt['token'] ?? null,
            ]);
        }

        $state['snapshots'][$snapshot] = [
            'source' => $source,
            'handle' => $sourceState['handle'],
            'path' => $sourceState['path'],
            'owner' => $sourceState['owner'],
            'data_version' => $sourceState['data_version'],
            'ready_token' => $readyReceipt['token'],
            'publish_token' => $publishReceipt['token'],
            'published_count' => count($sourceState['published']),
            'receipt_digest' => self::receiptDigest($sourceState['published']),
        ];

        return self::event('snapshot', 'captured-ready', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'ready_token' => $readyReceipt['token'],
            'publish_token' => $publishReceipt['token'],
        ]);
    }

    private static function publish(array &$state, string $source, string $snapshot, string $token, array $before): array
    {
        if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
            return self::event('publish', 'missing-source', $source, $before, self::summary($state), ['snapshot' => $snapshot, 'token' => $token]);
        }
        if (!isset($state['snapshots'][$snapshot])) {
            return self::event('publish', 'missing-snapshot', $source, $before, self::summary($state), ['snapshot' => $snapshot, 'token' => $token]);
        }

        $sourceState = $state['sources'][$source];
        $snapshotState = $state['snapshots'][$snapshot];
        $blockers = self::publicationBlockers($snapshotState, $sourceState);
        if ($blockers !== []) {
            return self::event('publish', 'blocked-stale', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'token' => $token,
                'blocked_reasons' => $blockers,
            ]);
        }

        $state['sources'][$source]['published'][] = [
            'token' => $token,
            'data_version' => $sourceState['data_version'],
            'published_count' => count($sourceState['published']) + 1,
            'source_snapshot' => $snapshot,
        ];

        return self::event('publish', 'published-current-source', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'token' => $token,
            'published_count' => count($state['sources'][$source]['published']),
        ]);
    }

    private static function reuse(array $state, string $source, string $snapshot, array $before): array
    {
        if (!isset($state['sources'][$source]) || !isset($state['snapshots'][$snapshot])) {
            return self::event('reuse', 'missing-source-or-snapshot', $source, $before, self::summary($state), ['snapshot' => $snapshot]);
        }

        $blockers = self::publicationBlockers($state['snapshots'][$snapshot], $state['sources'][$source]);
        return self::event('reuse', $blockers === [] ? 'reused-current-source' : 'blocked-stale', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'blocked_reasons' => $blockers,
        ]);
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
            $sourceName = self::token((string) $name, 'source name');
            $state['sources'][$sourceName] = [
                'handle' => self::token((string) ($source['handle'] ?? $sourceName), 'handle'),
                'path' => self::path((string) ($source['path'] ?? '')),
                'owner' => self::path((string) ($source['owner'] ?? $source['path'] ?? '')),
                'closed' => (bool) ($source['closed'] ?? false),
                'data_version' => self::nonNegativeInt($source['data_version'] ?? 0, 'data version'),
                'dirty_pages' => self::receiptList($source['dirty_pages'] ?? []),
                'ready_receipts' => self::receiptMetadataList($source['ready_receipts'] ?? []),
                'published' => self::receiptMetadataList($source['published'] ?? []),
            ];
        }
        foreach (is_array($current['snapshots'] ?? null) ? $current['snapshots'] : [] as $name => $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $state['snapshots'][self::token((string) $name, 'snapshot name')] = [
                'source' => self::token((string) ($snapshot['source'] ?? 'main'), 'snapshot source'),
                'handle' => self::token((string) ($snapshot['handle'] ?? $name), 'snapshot handle'),
                'path' => self::path((string) ($snapshot['path'] ?? '')),
                'owner' => self::path((string) ($snapshot['owner'] ?? $snapshot['path'] ?? '')),
                'data_version' => self::nonNegativeInt($snapshot['data_version'] ?? 0, 'snapshot data version'),
                'ready_token' => self::token((string) ($snapshot['ready_token'] ?? 'ready'), 'ready token'),
                'publish_token' => self::token((string) ($snapshot['publish_token'] ?? 'publish'), 'publish token'),
                'published_count' => self::nonNegativeInt($snapshot['published_count'] ?? 0, 'snapshot published count'),
                'receipt_digest' => self::token((string) ($snapshot['receipt_digest'] ?? 'empty'), 'receipt digest'),
            ];
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::token((string) $current['current_source'], 'current source');
        }
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'capturesnapshot', 'snapshotcapture' => 'snapshot',
                'publishsnapshot', 'publishsource', 'sourcepublish' => 'publish',
                'reusesnapshot', 'snapshotreuse' => 'reuse',
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
        if (preg_match('/^publish\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'publish', 'snapshot' => $matches['snapshot'], 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next218-221 operation is unsupported');
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

    /** @return list<string> */
    private static function publicationBlockers(array $snapshot, array $source): array
    {
        $blockers = [];
        foreach (['handle', 'path', 'owner', 'data_version'] as $key) {
            if ($snapshot[$key] !== $source[$key]) {
                $blockers[] = str_replace('_', '-', $key) . '-changed';
            }
        }
        if ($snapshot['published_count'] !== count($source['published'])) {
            $blockers[] = 'published-count-changed';
        }
        if ($snapshot['receipt_digest'] !== self::receiptDigest($source['published'])) {
            $blockers[] = 'publish-receipt-digest-changed';
        }
        if ($source['dirty_pages'] !== []) {
            $blockers[] = 'dirty-pages-present';
        }
        return $blockers;
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::token((string) $source, 'source name');
        }
        if (is_string($state['current_source'])) {
            return $state['current_source'];
        }
        return 'main';
    }

    private static function event(string $kind, string $status, string $source, array $before, array $next, array $extra): array
    {
        return ['kind' => $kind, 'status' => $status, 'source' => $source, 'before' => $before, 'next' => $next] + $extra;
    }

    private static function lastReadyReceipt(array $source): ?array
    {
        if ($source['ready_receipts'] === []) {
            return null;
        }
        return $source['ready_receipts'][array_key_last($source['ready_receipts'])];
    }

    private static function lastPublishReceipt(array $source): ?array
    {
        if ($source['published'] === []) {
            return null;
        }
        return $source['published'][array_key_last($source['published'])];
    }

    private static function receiptDigest(array $receipts): string
    {
        $tokens = array_map(static fn (array $receipt): string => (string) $receipt['token'], $receipts);
        return $tokens === [] ? 'empty' : hash('sha256', implode('|', $tokens));
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 requires valid paths');
        }
        return $path;
    }

    private static function token(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:\/-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 requires valid ' . $label);
        }
        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 requires non-negative ' . $label);
        }
        return $value;
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

    /** @return list<array{token:string, data_version:int}> */
    private static function receiptMetadataList(mixed $receipts): array
    {
        $result = [];
        foreach (is_array($receipts) ? $receipts : [] as $receipt) {
            if (!is_array($receipt)) {
                continue;
            }
            $result[] = [
                'token' => self::token((string) ($receipt['token'] ?? ''), 'receipt token'),
                'data_version' => self::nonNegativeInt($receipt['data_version'] ?? 0, 'receipt data version'),
            ];
        }
        return $result;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next218-221 requires positive ' . $label);
        }
        return $value;
    }
}
