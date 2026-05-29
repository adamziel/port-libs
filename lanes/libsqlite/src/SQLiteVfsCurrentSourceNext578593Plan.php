<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext578593Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $current = self::summary($state);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if ($op['kind'] === 'snapshot') {
                $events[] = self::snapshot(
                    $state,
                    $source,
                    self::token((string) ($op['snapshot'] ?? ''), 'snapshot name'),
                    self::token((string) ($op['ack'] ?? ''), 'reuse acknowledgement'),
                    $before
                );
                continue;
            }

            if ($op['kind'] === 'claim') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot name');
                $events[] = self::claim(
                    $state,
                    $source,
                    $snapshot,
                    self::token((string) ($op['ack'] ?? ''), 'reuse acknowledgement'),
                    self::token((string) ($op['claim'] ?? $snapshot . '-claim'), 'reuse claim'),
                    $before
                );
                continue;
            }

            if ($op['kind'] === 'publish') {
                $events[] = self::publish(
                    $state,
                    $source,
                    self::token((string) ($op['snapshot'] ?? ''), 'snapshot name'),
                    self::token((string) ($op['claim'] ?? ''), 'reuse claim'),
                    self::token((string) ($op['token'] ?? 'publish'), 'publish token'),
                    $before
                );
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 operation is unsupported');
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
                'vfs-current-source-snapshot-reuse-publish-next254-257',
                'vfs-current-source-snapshot-reuse-publish-next258-265',
                'vfs-current-source-snapshot-reuse-publish-next266-273',
                'vfs-current-source-snapshot-reuse-publish-next274-281',
                'vfs-current-source-snapshot-reuse-publish-next282-289',
                'vfs-current-source-snapshot-reuse-publish-next290-297',
                'vfs-current-source-snapshot-reuse-publish-next298-305',
                'vfs-current-source-snapshot-reuse-publish-next306-313',
                'vfs-current-source-snapshot-reuse-publish-next314-321',
                'vfs-current-source-snapshot-reuse-publish-next322-337',
                'vfs-current-source-snapshot-reuse-publish-next338-353',
                'vfs-current-source-snapshot-reuse-publish-next354-369',
                'vfs-current-source-snapshot-reuse-publish-next370-385',
                'vfs-current-source-snapshot-reuse-publish-next386-401',
                'vfs-current-source-snapshot-reuse-publish-next402-417',
                'vfs-current-source-snapshot-reuse-publish-next418-433',
                'vfs-current-source-snapshot-reuse-publish-next434-449',
                'vfs-current-source-snapshot-reuse-publish-next450-465',
                'vfs-current-source-snapshot-reuse-publish-next466-481',
                'vfs-current-source-snapshot-reuse-publish-next482-497',
                'vfs-current-source-snapshot-reuse-publish-next498-513',
                'vfs-current-source-snapshot-reuse-publish-next514-529',
                'vfs-current-source-snapshot-reuse-publish-next530-545',
                'vfs-current-source-snapshot-reuse-publish-next546-561',
                'vfs-current-source-snapshot-reuse-publish-next562-577',
                'vfs-current-source-snapshot-reuse-publish-next578-593',
            ],
            'non_overlap' => 'next578-593 follows merged next562-577 by requiring the shared-cache-next577 receipt before creating a fresh current-source snapshot and publishing shared-cache-next593; it does not modify prior next562-577 files, earlier capture/readiness/lease gates, dirty flushing, VFS locking, WAL checkpointing, or B-tree behavior.',
        ];
    }

    private static function snapshot(array &$state, string $source, string $snapshot, string $ack, array $before): array
    {
        if (!isset($state['sources'][$source])) {
            return self::event('snapshot', 'missing-source', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'ack' => $ack,
            ]);
        }

        $sourceState = $state['sources'][$source];
        $blockers = [];
        if ($sourceState['closed']) {
            $blockers[] = 'source-closed';
        }
        if ($sourceState['dirty_pages'] !== []) {
            $blockers[] = 'dirty-pages-present';
        }
        if (self::lastReceiptToken($sourceState) !== $ack) {
            $blockers[] = 'ack-not-latest-publish';
        }
        if (isset($state['snapshots'][$snapshot])) {
            $blockers[] = 'snapshot-already-exists';
        }

        if ($blockers === []) {
            $state['snapshots'][$snapshot] = [
                'source' => $source,
                'handle' => $sourceState['handle'],
                'path' => $sourceState['path'],
                'owner' => $sourceState['owner'],
                'data_version' => $sourceState['data_version'],
                'published_count' => count($sourceState['published']),
                'receipt_digest' => self::receiptDigest($sourceState['published']),
            ];
            $state['sources'][$source]['reuse_acks'][] = [
                'snapshot' => $snapshot,
                'receipt' => $ack,
                'data_version' => $sourceState['data_version'],
                'published_count' => count($sourceState['published']),
                'receipt_digest' => self::receiptDigest($sourceState['published']),
            ];
        }

        return self::event('snapshot', $blockers === [] ? 'snapshotted-current-source' : 'blocked-stale', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'ack' => $ack,
            'blocked_reasons' => $blockers,
            'snapshot_count' => count($state['snapshots']),
        ]);
    }

    private static function claim(array &$state, string $source, string $snapshot, string $ack, string $claim, array $before): array
    {
        if (!isset($state['sources'][$source]) || !isset($state['snapshots'][$snapshot])) {
            return self::event('claim', 'missing-source-or-snapshot', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'ack' => $ack,
                'claim' => $claim,
            ]);
        }

        $sourceState = $state['sources'][$source];
        $snapshotState = $state['snapshots'][$snapshot];
        $blockers = self::reuseBlockers($snapshotState, $sourceState);
        $ackState = self::lastReuseAck($sourceState, $snapshot);
        if ($ackState === null || $ackState['receipt'] !== $ack) {
            $blockers[] = 'missing-reuse-ack';
        } elseif (
            $ackState['data_version'] !== $sourceState['data_version']
            || $ackState['published_count'] !== $snapshotState['published_count']
            || $ackState['receipt_digest'] !== $snapshotState['receipt_digest']
        ) {
            $blockers[] = 'stale-reuse-ack';
        }

        if ($blockers === []) {
            $state['sources'][$source]['reuse_claims'][] = [
                'token' => $claim,
                'snapshot' => $snapshot,
                'ack' => $ack,
                'data_version' => $sourceState['data_version'],
                'published_count' => count($sourceState['published']),
                'receipt_digest' => self::receiptDigest($sourceState['published']),
            ];
        }

        return self::event('claim', $blockers === [] ? 'claimed-reusable-current-source' : 'blocked-stale', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'ack' => $ack,
            'claim' => $claim,
            'reuse_claim_count' => count($state['sources'][$source]['reuse_claims'] ?? []),
            'blocked_reasons' => $blockers,
        ]);
    }

    private static function publish(array &$state, string $source, string $snapshot, string $claim, string $token, array $before): array
    {
        if (!isset($state['sources'][$source]) || !isset($state['snapshots'][$snapshot])) {
            return self::event('publish', 'missing-source-or-snapshot', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'claim' => $claim,
                'token' => $token,
            ]);
        }

        $sourceState = $state['sources'][$source];
        $snapshotState = $state['snapshots'][$snapshot];
        $blockers = self::reuseBlockers($snapshotState, $sourceState);
        $claimState = self::lastReuseClaim($sourceState, $snapshot);
        if ($claimState === null || $claimState['token'] !== $claim) {
            $blockers[] = 'missing-reuse-claim';
        } elseif (
            $claimState['data_version'] !== $sourceState['data_version']
            || $claimState['published_count'] !== count($sourceState['published'])
            || $claimState['receipt_digest'] !== self::receiptDigest($sourceState['published'])
        ) {
            $blockers[] = 'stale-reuse-claim';
        }

        if ($blockers !== []) {
            return self::event('publish', 'blocked-stale', $source, $before, self::summary($state), [
                'snapshot' => $snapshot,
                'claim' => $claim,
                'token' => $token,
                'blocked_reasons' => $blockers,
            ]);
        }

        $state['sources'][$source]['published'][] = [
            'token' => $token,
            'data_version' => $sourceState['data_version'],
            'source_snapshot' => $snapshot,
            'reuse_claim' => $claim,
            'reuse_ack' => $claimState['ack'],
        ];

        return self::event('publish', 'published-reused-current-source', $source, $before, self::summary($state), [
            'snapshot' => $snapshot,
            'claim' => $claim,
            'token' => $token,
            'reuse_ack' => $claimState['ack'],
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
                'reuse_acks' => self::reuseAckList($source['reuse_acks'] ?? []),
                'reuse_claims' => self::reuseClaimList($source['reuse_claims'] ?? []),
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
                'snapshot', 'capture', 'capturesnapshot' => 'snapshot',
                'claim', 'reuseclaim', 'claimreuse' => 'claim',
                'publish', 'reusepublish', 'publishreuse' => 'publish',
                default => $kind,
            }];
        }

        $trimmed = trim($operation);
        if (preg_match('/^claim\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<ack>[A-Za-z0-9_.:-]+)\s*,\s*(?<claim>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'claim', 'snapshot' => $matches['snapshot'], 'ack' => $matches['ack'], 'claim' => $matches['claim']];
        }
        if (preg_match('/^snapshot\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<ack>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'snapshot', 'snapshot' => $matches['snapshot'], 'ack' => $matches['ack']];
        }
        if (preg_match('/^publish\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<claim>[A-Za-z0-9_.:-]+)\s*,\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'publish', 'snapshot' => $matches['snapshot'], 'claim' => $matches['claim'], 'token' => $matches['token']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next578-593 operation is unsupported');
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

    private static function lastReuseAck(array $source, string $snapshot): ?array
    {
        for ($i = count($source['reuse_acks']) - 1; $i >= 0; --$i) {
            if ($source['reuse_acks'][$i]['snapshot'] === $snapshot) {
                return $source['reuse_acks'][$i];
            }
        }
        return null;
    }

    private static function lastReuseClaim(array $source, string $snapshot): ?array
    {
        for ($i = count($source['reuse_claims']) - 1; $i >= 0; --$i) {
            if ($source['reuse_claims'][$i]['snapshot'] === $snapshot) {
                return $source['reuse_claims'][$i];
            }
        }
        return null;
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::token((string) $source, 'source name');
        }
        return is_string($state['current_source']) ? $state['current_source'] : 'main';
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

    private static function lastReceiptToken(array $source): ?string
    {
        $last = $source['published'][array_key_last($source['published'])] ?? null;
        return is_array($last) ? (string) $last['token'] : null;
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 requires valid paths');
        }
        return $path;
    }

    private static function token(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:\/-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 requires valid ' . $label);
        }
        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 requires non-negative ' . $label);
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

    /** @return list<array{token:string, snapshot:string, ack:string, data_version:int, published_count:int, receipt_digest:string}> */
    private static function reuseClaimList(mixed $claims): array
    {
        $result = [];
        foreach (is_array($claims) ? $claims : [] as $claim) {
            if (!is_array($claim)) {
                continue;
            }
            $result[] = [
                'token' => self::token((string) ($claim['token'] ?? ''), 'reuse claim token'),
                'snapshot' => self::token((string) ($claim['snapshot'] ?? ''), 'reuse claim snapshot'),
                'ack' => self::token((string) ($claim['ack'] ?? ''), 'reuse claim ack'),
                'data_version' => self::nonNegativeInt($claim['data_version'] ?? 0, 'reuse claim data version'),
                'published_count' => self::nonNegativeInt($claim['published_count'] ?? 0, 'reuse claim published count'),
                'receipt_digest' => self::token((string) ($claim['receipt_digest'] ?? 'empty'), 'reuse claim receipt digest'),
            ];
        }
        return $result;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next578-593 requires positive ' . $label);
        }
        return $value;
    }
}
