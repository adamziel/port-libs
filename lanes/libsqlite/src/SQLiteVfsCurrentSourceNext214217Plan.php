<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext214217Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 requires operations');
        }

        $state = self::hydrate($options['current'] ?? []);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if ($op['kind'] === 'publish_snapshot') {
                $snapshot = self::token((string) ($op['snapshot'] ?? ''), 'snapshot token');
                $ticket = self::token((string) ($op['ticket'] ?? ''), 'publish ticket');
                $blocked = self::publicationBlockers($state, $source, $snapshot);
                if ($blocked !== []) {
                    $events[] = self::event('publish_snapshot', 'blocked', $source, $before, self::summary($state), [
                        'snapshot' => $snapshot,
                        'ticket' => $ticket,
                        'blocked_reasons' => $blocked,
                    ]);
                    continue;
                }
                $snapshotState = $state['snapshots'][$snapshot];
                $state['publications'][$ticket] = [
                    'source' => $source,
                    'snapshot' => $snapshot,
                    'handle' => $snapshotState['handle'],
                    'path' => $snapshotState['path'],
                    'owner' => $snapshotState['owner'],
                    'data_version' => $snapshotState['data_version'],
                    'durable_count' => $snapshotState['durable_count'],
                    'checkpoint_token' => $snapshotState['checkpoint_token'],
                    'published_count' => count($state['publications']) + 1,
                ];
                $events[] = self::event('publish_snapshot', 'published', $source, $before, self::summary($state), [
                    'snapshot' => $snapshot,
                    'ticket' => $ticket,
                    'published_count' => count($state['publications']),
                ]);
                continue;
            }

            if ($op['kind'] === 'reuse_publication') {
                $ticket = self::token((string) ($op['ticket'] ?? ''), 'publish ticket');
                $reader = self::token((string) ($op['reader'] ?? 'reader'), 'reader token');
                $blocked = self::reuseBlockers($state, $source, $ticket);
                if ($blocked !== []) {
                    $events[] = self::event('reuse_publication', 'blocked-stale-publication', $source, $before, self::summary($state), [
                        'ticket' => $ticket,
                        'reader' => $reader,
                        'blocked_reasons' => $blocked,
                    ]);
                    continue;
                }
                $state['reader_reuse'][$reader] = [
                    'ticket' => $ticket,
                    'source' => $source,
                    'status' => 'publication-reused',
                    'reuse_count' => count($state['reader_reuse']) + 1,
                ];
                $events[] = self::event('reuse_publication', 'publication-reused', $source, $before, self::summary($state), [
                    'ticket' => $ticket,
                    'reader' => $reader,
                    'reuse_count' => count($state['reader_reuse']),
                ]);
                continue;
            }

            if ($op['kind'] === 'revoke_publication') {
                $ticket = self::token((string) ($op['ticket'] ?? ''), 'publish ticket');
                if (!isset($state['publications'][$ticket])) {
                    $events[] = self::event('revoke_publication', 'missing-publication', $source, $before, self::summary($state), ['ticket' => $ticket]);
                    continue;
                }
                $state['revoked'][$ticket] = true;
                $events[] = self::event('revoke_publication', 'revoked', $source, $before, self::summary($state), ['ticket' => $ticket]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-ready-next210-213',
                'vfs-current-source-snapshot-reuse-publish-next214-217',
            ],
            'non_overlap' => 'next214-217 validates publication tickets for already-ready VFS current-source snapshots after next210-213; it does not repeat snapshot capture/reuse/publish mechanics from next206-209, ready publication from next202-205, or dirty flush/checkpoint behavior.',
        ];
    }

    private static function hydrate(mixed $current): array
    {
        $state = [
            'current_source' => null,
            'sources' => [],
            'snapshots' => [],
            'publications' => [],
            'reader_reuse' => [],
            'revoked' => [],
        ];
        if (!is_array($current)) {
            return $state;
        }
        foreach (is_array($current['sources'] ?? null) ? $current['sources'] : [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourceName = self::token((string) $name, 'source token');
            $state['sources'][$sourceName] = [
                'handle' => self::token((string) ($source['handle'] ?? $sourceName), 'handle token'),
                'path' => self::path((string) ($source['path'] ?? '')),
                'owner' => self::path((string) ($source['owner'] ?? $source['path'] ?? '')),
                'closed' => (bool) ($source['closed'] ?? false),
                'data_version' => self::nonNegativeInt($source['data_version'] ?? 0, 'data version'),
                'dirty_pages' => self::pageList($source['dirty_pages'] ?? []),
                'durable_receipts' => self::pageList($source['durable_receipts'] ?? []),
            ];
        }
        foreach (is_array($current['snapshots'] ?? null) ? $current['snapshots'] : [] as $name => $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $state['snapshots'][self::token((string) $name, 'snapshot token')] = [
                'source' => self::token((string) ($snapshot['source'] ?? 'main'), 'source token'),
                'handle' => self::token((string) ($snapshot['handle'] ?? $name), 'handle token'),
                'path' => self::path((string) ($snapshot['path'] ?? '')),
                'owner' => self::path((string) ($snapshot['owner'] ?? $snapshot['path'] ?? '')),
                'data_version' => self::nonNegativeInt($snapshot['data_version'] ?? 0, 'snapshot data version'),
                'durable_count' => self::nonNegativeInt($snapshot['durable_count'] ?? 0, 'snapshot durable count'),
                'checkpoint_token' => self::token((string) ($snapshot['checkpoint_token'] ?? 'checkpoint'), 'checkpoint token'),
            ];
        }
        foreach (is_array($current['publications'] ?? null) ? $current['publications'] : [] as $ticket => $publication) {
            if (!is_array($publication)) {
                continue;
            }
            $state['publications'][self::token((string) $ticket, 'publish ticket')] = [
                'source' => self::token((string) ($publication['source'] ?? 'main'), 'source token'),
                'snapshot' => self::token((string) ($publication['snapshot'] ?? 'snapshot'), 'snapshot token'),
                'handle' => self::token((string) ($publication['handle'] ?? 'handle'), 'handle token'),
                'path' => self::path((string) ($publication['path'] ?? '')),
                'owner' => self::path((string) ($publication['owner'] ?? $publication['path'] ?? '')),
                'data_version' => self::nonNegativeInt($publication['data_version'] ?? 0, 'publication data version'),
                'durable_count' => self::nonNegativeInt($publication['durable_count'] ?? 0, 'publication durable count'),
                'checkpoint_token' => self::token((string) ($publication['checkpoint_token'] ?? 'checkpoint'), 'checkpoint token'),
            ];
        }
        foreach (is_array($current['revoked'] ?? null) ? $current['revoked'] : [] as $ticket => $revoked) {
            if ($revoked) {
                $state['revoked'][self::token((string) $ticket, 'publish ticket')] = true;
            }
        }
        if (isset($current['current_source'])) {
            $state['current_source'] = self::token((string) $current['current_source'], 'source token');
        }
        return $state;
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'publishsnapshot', 'snapshotpublish' => 'publish_snapshot',
                'reusepublication', 'publicationreuse' => 'reuse_publication',
                'revokepublication', 'publicationrevoke' => 'revoke_publication',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^publish-snapshot\s*\(\s*(?<snapshot>[A-Za-z0-9_.:-]+)\s*,\s*(?<ticket>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'publish_snapshot', 'snapshot' => $matches['snapshot'], 'ticket' => $matches['ticket']];
        }
        if (preg_match('/^reuse-publication\s*\(\s*(?<ticket>[A-Za-z0-9_.:-]+)\s*,\s*(?<reader>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'reuse_publication', 'ticket' => $matches['ticket'], 'reader' => $matches['reader']];
        }
        if (preg_match('/^revoke-publication\s*\(\s*(?<ticket>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'revoke_publication', 'ticket' => $matches['ticket']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next214-217 operation is unsupported');
    }

    /** @return list<string> */
    private static function publicationBlockers(array $state, string $source, string $snapshot): array
    {
        if (!isset($state['snapshots'][$snapshot])) {
            return ['missing-snapshot'];
        }
        if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
            return ['missing-source'];
        }
        $snapshotState = $state['snapshots'][$snapshot];
        $sourceState = $state['sources'][$source];
        $reasons = [];
        if ($snapshotState['source'] !== $source) {
            $reasons[] = 'snapshot-source-mismatch';
        }
        if ($snapshotState['handle'] !== $sourceState['handle'] || $snapshotState['path'] !== $sourceState['path'] || $snapshotState['owner'] !== $sourceState['owner']) {
            $reasons[] = 'source-identity-changed';
        }
        if ($snapshotState['data_version'] !== $sourceState['data_version']) {
            $reasons[] = 'data-version-changed';
        }
        if ($snapshotState['durable_count'] !== count($sourceState['durable_receipts'])) {
            $reasons[] = 'durable-count-changed';
        }
        if ($sourceState['dirty_pages'] !== []) {
            $reasons[] = 'dirty-pages-present';
        }
        return $reasons;
    }

    /** @return list<string> */
    private static function reuseBlockers(array $state, string $source, string $ticket): array
    {
        if (!isset($state['publications'][$ticket])) {
            return ['missing-publication'];
        }
        if (isset($state['revoked'][$ticket])) {
            return ['publication-revoked'];
        }
        if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
            return ['missing-source'];
        }
        $publication = $state['publications'][$ticket];
        $sourceState = $state['sources'][$source];
        $reasons = [];
        if ($publication['source'] !== $source) {
            $reasons[] = 'publication-source-mismatch';
        }
        if ($publication['handle'] !== $sourceState['handle'] || $publication['path'] !== $sourceState['path'] || $publication['owner'] !== $sourceState['owner']) {
            $reasons[] = 'source-identity-changed';
        }
        if ($publication['data_version'] !== $sourceState['data_version']) {
            $reasons[] = 'data-version-changed';
        }
        if ($publication['durable_count'] !== count($sourceState['durable_receipts'])) {
            $reasons[] = 'durable-count-changed';
        }
        if ($sourceState['dirty_pages'] !== []) {
            $reasons[] = 'dirty-pages-present';
        }
        return $reasons;
    }

    private static function event(string $kind, string $status, string $source, array $before, array $next, array $extra): array
    {
        return ['kind' => $kind, 'status' => $status, 'source' => $source, 'before' => $before, 'next' => $next] + $extra;
    }

    private static function summary(array $state): array
    {
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'snapshot_count' => count($state['snapshots']),
            'publication_count' => count($state['publications']),
            'reader_reuse_count' => count($state['reader_reuse']),
            'sources' => $state['sources'],
            'snapshots' => $state['snapshots'],
            'publications' => $state['publications'],
            'reader_reuse' => $state['reader_reuse'],
            'revoked' => $state['revoked'],
        ];
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::token((string) $source, 'source token');
        }
        return is_string($state['current_source']) ? $state['current_source'] : 'main';
    }

    private static function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 requires valid paths');
        }
        return $path;
    }

    private static function token(string $token, string $label): string
    {
        $token = trim($token);
        if ($token === '' || preg_match('/^[A-Za-z0-9_.:\/-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 requires valid ' . $label);
        }
        return $token;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 requires non-negative ' . $label);
        }
        return $value;
    }

    /** @return list<array{page:int, bytes:int, digest:string}> */
    private static function pageList(mixed $pages): array
    {
        $result = [];
        foreach (is_array($pages) ? $pages : [] as $page) {
            if (!is_array($page)) {
                continue;
            }
            $result[] = [
                'page' => self::positiveInt($page['page'] ?? null, 'page'),
                'bytes' => self::positiveInt($page['bytes'] ?? null, 'bytes'),
                'digest' => self::token((string) ($page['digest'] ?? ''), 'page digest'),
            ];
        }
        return $result;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite VFS current-source next214-217 requires positive ' . $label);
        }
        return $value;
    }
}
