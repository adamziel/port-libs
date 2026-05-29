<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext234237Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if ($op['kind'] === 'ack') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot name');
                $receipt = self::token((string) ($op['receipt'] ?? ''), 'publish receipt');
                $events[] = self::ack($state, $source, $snapshot, $receipt, $before);
                continue;
            }

            if ($op['kind'] === 'republish') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot name');
                $token = self::token((string) ($op['token'] ?? 'republish'), 'publish token');
                $events[] = self::republish($state, $source, $snapshot, $token, $before);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 operation is unsupported');
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
                'vfs-current-source-reuse-lease-publish-next226-229',
                'vfs-current-source-reuse-ack-publish-next234-237',
            ],
            'non_overlap' => 'next234-237 follows ready next226-229 reuse leases and adds consumer acknowledgement before republishing a reused current-source snapshot; it does not overlap parallel next230-233 ownership, next206-209 capture/reuse, next214-217 readiness, next218-221 publication fencing, or next226-229 lease validation.',
        ];
    }

    private static function ack(array &$state, string $source, string $snapshot, string $receipt, array $before): array
    {
        if (!isset($state['sources'][$source]) || !isset($state['snapshots'][$snapshot])) {
            return self::event('ack', 'missing-source-or-snapshot', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'receipt' => $receipt,
            ]);
        }

        $blockers = self::reuseBlockers($state['snapshots'][$snapshot], $state['sources'][$source]);
        if (!self::hasPublishReceipt($state['sources'][$source], $receipt)) {
            $blockers[] = 'missing-publish-receipt';
        }

        if ($blockers === []) {
            $state['sources'][$source]['reuse_acks'][] = [
                'snapshot' => $snapshot,
                'receipt' => $receipt,
                'data_version' => $state['sources'][$source]['data_version'],
                'published_count' => count($state['sources'][$source]['published']),
                'receipt_digest' => self::receiptDigest($state['sources'][$source]['published']),
            ];
        }

        return self::event('ack', $blockers === [] ? 'acknowledged-reuse-publish' : 'blocked-stale', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'receipt' => $receipt,
            'reuse_ack_count' => count($state['sources'][$source]['reuse_acks']),
            'blocked_reasons' => $blockers,
        ]);
    }

    private static function republish(array &$state, string $source, string $snapshot, string $token, array $before): array
    {
        if (!isset($state['sources'][$source]) || !isset($state['snapshots'][$snapshot])) {
            return self::event('republish', 'missing-source-or-snapshot', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'token' => $token,
            ]);
        }

        $sourceState = $state['sources'][$source];
        $snapshotState = $state['snapshots'][$snapshot];
        $blockers = self::reuseBlockers($snapshotState, $sourceState);
        $ack = self::lastReuseAck($sourceState, $snapshot);
        if ($ack === null) {
            $blockers[] = 'missing-reuse-ack';
        } elseif (
            $ack['data_version'] !== $sourceState['data_version']
            || $ack['published_count'] !== count($sourceState['published'])
            || $ack['receipt_digest'] !== self::receiptDigest($sourceState['published'])
        ) {
            $blockers[] = 'stale-reuse-ack';
        }

        if ($blockers !== []) {
            return self::event('republish', 'blocked-stale', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'token' => $token,
                'blocked_reasons' => $blockers,
            ]);
        }

        $state['sources'][$source]['published'][] = [
            'token' => $token,
            'data_version' => $sourceState['data_version'],
            'source_snapshot' => $snapshot,
            'reuse_ack' => $ack['receipt'],
        ];

        return self::event('republish', 'republished-current-source', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'token' => $token,
            'reuse_ack' => $ack['receipt'],
            'published_count' => count($state['sources'][$source]['published']),
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
                'published' => self::receiptMetadataList($source['published'] ?? []),
                'reuse_leases' => self::reuseLeaseList($source['reuse_leases'] ?? []),
                'reuse_acks' => self::reuseAckList($source['reuse_acks'] ?? []),
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
                'ack', 'reuseack', 'ackreuse', 'ackpublish' => 'ack',
                'republish', 'publishagain', 'reusepublish' => 'republish',
                default => $kind,
            }];
        }

        $trimmed = trim($operation);
        if (preg_match('/^ack\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<receipt>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'ack', 'snapshot' => $matches['snapshot'], 'receipt' => $matches['receipt']];
        }
        if (preg_match('/^republish\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'republish', 'snapshot' => $matches['snapshot'], 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next234-237 operation is unsupported');
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
    private static function reuseBlockers(array $snapshot, array $source): array
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

    private static function hasPublishReceipt(array $source, string $receipt): bool
    {
        foreach ($source['published'] as $published) {
            if ($published['token'] === $receipt) {
                return true;
            }
        }
        return false;
    }

    private static function lastReuseAck(array $source, string $snapshot): ?array
    {
        for ($i = count($source['reuse_acks']) - 1; $i >= 0; --$i) {
            if ($source['reuse_acks'][$i]['snapshot'] === $snapshot) {
                return $source['reuse_acks'][$i];
            }
        }
        return null;
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

    private static function receiptDigest(array $receipts): string
    {
        $tokens = array_map(static fn (array $receipt): string => (string) $receipt['token'], $receipts);
        return $tokens === [] ? 'empty' : hash('sha256', implode('|', $tokens));
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 requires valid paths');
        }
        return $path;
    }

    private static function token(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:\/-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 requires valid ' . $label);
        }
        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 requires non-negative ' . $label);
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

    /** @return list<array{token:string, snapshot:string, data_version:int, published_count:int, receipt_digest:string}> */
    private static function reuseLeaseList(mixed $leases): array
    {
        $result = [];
        foreach (is_array($leases) ? $leases : [] as $lease) {
            if (!is_array($lease)) {
                continue;
            }
            $result[] = [
                'token' => self::token((string) ($lease['token'] ?? ''), 'reuse lease token'),
                'snapshot' => self::token((string) ($lease['snapshot'] ?? ''), 'reuse lease snapshot'),
                'data_version' => self::nonNegativeInt($lease['data_version'] ?? 0, 'reuse lease data version'),
                'published_count' => self::nonNegativeInt($lease['published_count'] ?? 0, 'reuse lease published count'),
                'receipt_digest' => self::token((string) ($lease['receipt_digest'] ?? 'empty'), 'reuse lease receipt digest'),
            ];
        }
        return $result;
    }

    /** @return list<array{snapshot:string, receipt:string, data_version:int, published_count:int, receipt_digest:string}> */
    private static function reuseAckList(mixed $acks): array
    {
        $result = [];
        foreach (is_array($acks) ? $acks : [] as $ack) {
            if (!is_array($ack)) {
                continue;
            }
            $result[] = [
                'snapshot' => self::token((string) ($ack['snapshot'] ?? ''), 'reuse ack snapshot'),
                'receipt' => self::token((string) ($ack['receipt'] ?? ''), 'reuse ack receipt'),
                'data_version' => self::nonNegativeInt($ack['data_version'] ?? 0, 'reuse ack data version'),
                'published_count' => self::nonNegativeInt($ack['published_count'] ?? 0, 'reuse ack published count'),
                'receipt_digest' => self::token((string) ($ack['receipt_digest'] ?? 'empty'), 'reuse ack receipt digest'),
            ];
        }
        return $result;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next234-237 requires positive ' . $label);
        }
        return $value;
    }
}
