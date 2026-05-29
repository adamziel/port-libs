<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsCurrentSourceNext202205Plan
{
    /**
     * @param list<array<string, mixed>|string> $operations
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $operations, array $options = []): array
    {
        if ($operations === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next202-205 requires operations');
        }

        $base = SQLiteVfsCurrentSourceNext194197Plan::run($options['prerequisite_next198_201'] ?? ['sync(normal)'], [
            'current' => $options['current'] ?? [],
        ]);
        $state = self::hydrate($base['next']);
        $events = [];

        foreach ($operations as $operation) {
            $op = self::operation($operation);
            $source = self::sourceFor($state, $op['source'] ?? null);
            $before = self::summary($state);

            if (!isset($state['sources'][$source]) || $state['sources'][$source]['closed'] === true) {
                $events[] = self::event($op['kind'], 'missing-source', $source, $before, self::summary($state), []);
                continue;
            }

            if ($op['kind'] === 'prepare') {
                $page = self::positiveInt($op['page'] ?? null, 'prepare page');
                $expectedDurable = self::nonNegativeInt($op['durable_count'] ?? count($state['sources'][$source]['durable_receipts']), 'durable count');
                $lease = self::token((string) ($op['lease'] ?? ('lease-' . $page)), 'lease token');
                $actualDurable = count($state['sources'][$source]['durable_receipts']);
                if ($actualDurable !== $expectedDurable) {
                    $events[] = self::event('prepare_page', 'stale-durable-count', $source, $before, self::summary($state), [
                        'page' => $page,
                        'expected_durable_count' => $expectedDurable,
                        'actual_durable_count' => $actualDurable,
                    ]);
                    continue;
                }
                $state['sources'][$source]['prepared_pages'][$page] = [
                    'page' => $page,
                    'lease' => $lease,
                    'data_version' => $state['sources'][$source]['data_version'],
                    'durable_count' => $actualDurable,
                ];
                $events[] = self::event('prepare_page', 'prepared', $source, $before, self::summary($state), [
                    'page' => $page,
                    'lease' => $lease,
                    'prepared_count' => count($state['sources'][$source]['prepared_pages']),
                ]);
                continue;
            }

            if ($op['kind'] === 'publish') {
                $pages = self::pageList($op['pages'] ?? []);
                $missing = [];
                foreach ($pages as $page) {
                    if (!isset($state['sources'][$source]['prepared_pages'][$page])) {
                        $missing[] = $page;
                    }
                }
                if ($missing !== []) {
                    $events[] = self::event('publish_batch', 'missing-prepared-pages', $source, $before, self::summary($state), [
                        'missing_pages' => $missing,
                    ]);
                    continue;
                }
                $token = self::token((string) ($op['token'] ?? 'publish'), 'publish token');
                $batch = [
                    'token' => $token,
                    'pages' => $pages,
                    'data_version' => $state['sources'][$source]['data_version'],
                    'durable_count' => count($state['sources'][$source]['durable_receipts']),
                    'lease_digest' => substr(hash('sha256', implode('|', array_map(
                        static fn (int $page): string => (string) $state['sources'][$source]['prepared_pages'][$page]['lease'],
                        $pages
                    ))), 0, 20),
                ];
                $state['sources'][$source]['published_batches'][] = $batch;
                $events[] = self::event('publish_batch', 'published', $source, $before, self::summary($state), [
                    'batch' => $batch,
                    'published_count' => count($state['sources'][$source]['published_batches']),
                ]);
                continue;
            }

            if ($op['kind'] === 'ack') {
                $token = self::token((string) ($op['token'] ?? ''), 'ack token');
                $found = false;
                foreach ($state['sources'][$source]['published_batches'] as $batch) {
                    if ($batch['token'] === $token) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $events[] = self::event('checkpoint_ack', 'missing-published-batch', $source, $before, self::summary($state), [
                        'token' => $token,
                    ]);
                    continue;
                }
                $state['sources'][$source]['checkpoint_acks'][] = [
                    'token' => $token,
                    'acknowledged_at_data_version' => $state['sources'][$source]['data_version'],
                ];
                $events[] = self::event('checkpoint_ack', 'acknowledged', $source, $before, self::summary($state), [
                    'token' => $token,
                    'ack_count' => count($state['sources'][$source]['checkpoint_acks']),
                ]);
                continue;
            }

            if ($op['kind'] === 'reader') {
                $reader = self::token((string) ($op['reader'] ?? 'reader'), 'reader token');
                $page = self::positiveInt($op['page'] ?? null, 'reader page');
                $lease = self::token((string) ($op['lease'] ?? ''), 'reader lease token');
                $prepared = $state['sources'][$source]['prepared_pages'][$page] ?? null;
                $status = is_array($prepared) && $prepared['lease'] === $lease ? 'reader-retained' : 'reader-reopen-required';
                $state['sources'][$source]['reader_fences'][$reader] = [
                    'reader' => $reader,
                    'page' => $page,
                    'lease' => $lease,
                    'status' => $status,
                ];
                $events[] = self::event('reader_fence', $status, $source, $before, self::summary($state), [
                    'reader' => $reader,
                    'page' => $page,
                ]);
                continue;
            }

            throw new \InvalidArgumentException('SQLite VFS current-source next202-205 operation is unsupported');
        }

        return [
            'status' => (string) ($events[array_key_last($events)]['status'] ?? 'ok'),
            'current' => $base['current'],
            'prerequisite_next198_201' => $base['next'],
            'next' => self::summary($state),
            'events' => $events,
            'dependencies' => [
                'vfs-current-source-durable-receipts-next194-197',
                'vfs-current-source-local-prerequisite-next198-201',
                'vfs-current-source-publish-reader-fence-next202-205',
            ],
            'non_overlap' => 'adds next202-205 VFS publish and reader-fence admission after local next198-201 prerequisite; does not repeat durable receipts next194-197 or unrelated WAL, pager, btree, JSON, planner, trigger, PRAGMA, or status work',
        ];
    }

    private static function hydrate(array $summary): array
    {
        $sources = [];
        foreach ($summary['sources'] ?? [] as $name => $source) {
            if (!is_array($source)) {
                continue;
            }
            $source['prepared_pages'] = is_array($source['prepared_pages'] ?? null) ? $source['prepared_pages'] : [];
            $source['published_batches'] = is_array($source['published_batches'] ?? null) ? $source['published_batches'] : [];
            $source['checkpoint_acks'] = is_array($source['checkpoint_acks'] ?? null) ? $source['checkpoint_acks'] : [];
            $source['reader_fences'] = is_array($source['reader_fences'] ?? null) ? $source['reader_fences'] : [];
            $sources[(string) $name] = $source;
        }
        return [
            'current_source' => $summary['current_source'] ?? null,
            'owner_generations' => is_array($summary['owner_generations'] ?? null) ? $summary['owner_generations'] : [],
            'sources' => $sources,
        ];
    }

    private static function operation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? '')));
            return $operation + ['kind' => match ($kind) {
                'preparepage' => 'prepare',
                'publishbatch' => 'publish',
                'checkpointack' => 'ack',
                'readerfence' => 'reader',
                default => $kind,
            }];
        }
        $trimmed = trim($operation);
        if (preg_match('/^prepare\s*\(\s*(?<page>[0-9]+)\s*,\s*(?<lease>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'prepare', 'page' => (int) $matches['page'], 'lease' => $matches['lease']];
        }
        if (preg_match('/^publish\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*,\s*(?<pages>[0-9, ]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'publish', 'token' => $matches['token'], 'pages' => array_map('intval', preg_split('/\s*,\s*/', trim($matches['pages'])) ?: [])];
        }
        if (preg_match('/^ack\s*\(\s*(?<token>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'ack', 'token' => $matches['token']];
        }
        if (preg_match('/^reader\s*\(\s*(?<reader>[A-Za-z0-9_.:-]+)\s*,\s*(?<page>[0-9]+)\s*,\s*(?<lease>[A-Za-z0-9_.:-]+)\s*\)$/', $trimmed, $matches) === 1) {
            return ['kind' => 'reader', 'reader' => $matches['reader'], 'page' => (int) $matches['page'], 'lease' => $matches['lease']];
        }
        throw new \InvalidArgumentException('SQLite VFS current-source next202-205 operation is unsupported');
    }

    private static function sourceFor(array $state, mixed $source): string
    {
        if ($source !== null && $source !== '') {
            return self::token(strtolower((string) $source), 'source token');
        }
        if (!is_string($state['current_source'])) {
            throw new \InvalidArgumentException('SQLite VFS current-source next202-205 has no selected source');
        }
        return $state['current_source'];
    }

    /** @return list<int> */
    private static function pageList(mixed $pages): array
    {
        if (!is_array($pages) || $pages === []) {
            throw new \InvalidArgumentException('SQLite VFS current-source next202-205 page list is required');
        }
        return array_values(array_map(static fn (mixed $page): int => self::positiveInt($page, 'publish page'), $pages));
    }

    private static function token(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite VFS current-source next202-205 {$label} is unsupported");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next202-205 {$label} must be positive");
        }
        $int = (int) $value;
        if ($int <= 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next202-205 {$label} must be positive");
        }
        return $int;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit((string) $value))) {
            throw new \InvalidArgumentException("SQLite VFS current-source next202-205 {$label} must be non-negative");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException("SQLite VFS current-source next202-205 {$label} must be non-negative");
        }
        return $int;
    }

    private static function summary(array $state): array
    {
        $open = 0;
        foreach ($state['sources'] as $source) {
            $open += ($source['closed'] ?? false) === true ? 0 : 1;
        }
        return [
            'current_source' => $state['current_source'],
            'source_count' => count($state['sources']),
            'open_source_count' => $open,
            'owner_generations' => $state['owner_generations'],
            'sources' => $state['sources'],
        ];
    }

    private static function event(string $operation, string $status, string $source, array $before, array $next, array $extra): array
    {
        return array_merge([
            'operation' => $operation,
            'status' => $status,
            'source' => $source,
            'before' => $before,
            'next' => $next,
        ], $extra);
    }
}
