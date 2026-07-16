<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsTempFileOpenLifecycle
{
    /**
     * @param list<string|array<string,mixed>> $operations
     * @param array{temp_dir?:string,connection_id?:string,temp_store?:string,directory_writable?:bool,current?:array<string,mixed>} $options
     * @return array{status:string,current:array<string,mixed>,next:array<string,mixed>,events:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function tempFileOpenLifecycleSequence(array $operations, array $options = []): array
    {
        $tempDir = self::normalizeTempDir((string) ($options['temp_dir'] ?? sys_get_temp_dir()));
        $connectionId = self::normalizeSegment((string) ($options['connection_id'] ?? 'conn'));
        $tempStore = strtolower((string) ($options['temp_store'] ?? 'file'));
        $directoryWritable = (bool) ($options['directory_writable'] ?? true);
        $current = self::normalizeCurrent($options['current'] ?? null);
        $events = [];
        $sequence = (int) ($current['sequence'] ?? 0);

        foreach ($operations as $operation) {
            $op = self::normalizeOperation($operation);
            $kind = $op['kind'];
            $closeHandle = $op['handle'];
            $suffix = $op['suffix'];

            if ($kind === 'open') {
                $sequence++;
                $handle = self::openTempHandle($tempDir, $connectionId, $sequence, $suffix, $tempStore, $directoryWritable, $op);
                $current['handles'][$handle['id']] = $handle;
                $current['last_open'] = $handle['id'];
                $current['sequence'] = $sequence;
                $events[] = [
                    'op' => 'open',
                    'status' => $handle['status'],
                    'handle' => $handle['id'],
                    'path' => $handle['path'],
                    'delete_on_close' => $handle['delete_on_close'],
                    'memory' => $handle['memory'],
                    'exclusive' => $handle['exclusive'],
                ];
                continue;
            }

            if ($kind === 'close') {
                $handleId = $closeHandle ?? (string) ($current['last_open'] ?? '');
                if (!isset($current['handles'][$handleId])) {
                    $events[] = [
                        'op' => 'close',
                        'status' => 'missing-handle',
                        'handle' => $handleId,
                        'deleted' => false,
                    ];
                    continue;
                }

                $handle = $current['handles'][$handleId];
                unset($current['handles'][$handleId]);
                $events[] = [
                    'op' => 'close',
                    'status' => 'closed',
                    'handle' => $handleId,
                    'path' => $handle['path'],
                    'deleted' => (bool) $handle['delete_on_close'] && !$handle['memory'],
                    'memory' => $handle['memory'],
                ];
                continue;
            }

            if ($kind === 'commit' || $kind === 'rollback') {
                $events[] = [
                    'op' => $kind,
                    'status' => 'deferred-close',
                    'open_handles' => array_keys($current['handles']),
                    'delete_on_close_pending' => self::pendingDeleteCount($current['handles']),
                ];
                continue;
            }

            throw new \InvalidArgumentException("Unsupported SQLite temp-file lifecycle operation: {$kind}");
        }

        $next = self::nextSnapshot($current);

        return [
            'status' => $events === [] ? 'idle' : (string) $events[array_key_last($events)]['status'],
            'current' => $current,
            'next' => $next,
            'events' => $events,
            'dependencies' => [
                'vfs-tempfile-open-lifecycle',
                'vfs-xopen-deleteonclose',
                'vfs-temp-exclusive-lock',
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @return array{handles:array<string,array<string,mixed>>,last_open:?string,sequence:int}
     */
    private static function normalizeCurrent(mixed $current): array
    {
        if (!is_array($current)) {
            return ['handles' => [], 'last_open' => null, 'sequence' => 0];
        }

        $handles = [];
        foreach (($current['handles'] ?? []) as $id => $handle) {
            if (is_array($handle)) {
                $handles[(string) $id] = $handle;
            }
        }

        return [
            'handles' => $handles,
            'last_open' => isset($current['last_open']) ? (string) $current['last_open'] : null,
            'sequence' => max(0, (int) ($current['sequence'] ?? 0)),
        ];
    }

    /**
     * @param string|array<string,mixed> $operation
     * @return array{kind:string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool}
     */
    private static function normalizeOperation(string|array $operation): array
    {
        if (is_array($operation)) {
            $kind = strtolower(str_replace(['_', '-'], '', (string) ($operation['op'] ?? $operation['kind'] ?? 'open')));

            return [
                'kind' => $kind,
                'handle' => isset($operation['handle']) ? (string) $operation['handle'] : null,
                'suffix' => self::normalizeSuffix((string) ($operation['suffix'] ?? '')),
                'delete_on_close' => (bool) ($operation['delete_on_close'] ?? true),
                'exclusive' => (bool) ($operation['exclusive'] ?? true),
                'readonly' => (bool) ($operation['readonly'] ?? false),
            ];
        }

        $trimmed = trim($operation);
        if (preg_match('/^(open|close|commit|rollback)(?:\(([^)]*)\))?$/i', $trimmed, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite temp-file lifecycle operation must be open(), close(), commit(), or rollback()');
        }

        $kind = strtolower($matches[1]);
        $argument = trim($matches[2] ?? '');

        return [
            'kind' => $kind,
            'handle' => $kind === 'close' && $argument !== '' ? $argument : null,
            'suffix' => $kind === 'open' ? self::normalizeSuffix($argument) : '',
            'delete_on_close' => true,
            'exclusive' => true,
            'readonly' => false,
        ];
    }

    /**
     * @param array{kind:string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool} $op
     * @return array<string,mixed>
     */
    private static function openTempHandle(
        string $tempDir,
        string $connectionId,
        int $sequence,
        string $suffix,
        string $tempStore,
        bool $directoryWritable,
        array $op
    ): array {
        $memory = $tempStore === 'memory' || !$directoryWritable;
        $id = 'temp-' . $connectionId . '-' . $sequence;
        $basename = 'sqlite-' . $connectionId . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT) . ($suffix === '' ? '.tmp' : $suffix);
        $path = $memory ? '' : $tempDir . '/' . $basename;

        return [
            'id' => $id,
            'status' => $memory ? 'memory-temp-open' : 'temp-open',
            'path' => $path,
            'basename' => $memory ? '' : $basename,
            'suffix' => $suffix === '' ? '.tmp' : $suffix,
            'memory' => $memory,
            'delete_on_close' => (bool) $op['delete_on_close'],
            'exclusive' => (bool) $op['exclusive'],
            'readonly' => (bool) $op['readonly'],
            'shared_memory' => false,
            'wal_path' => '',
            'shm_path' => '',
            'journal_path' => $memory ? '' : $path,
            'flags' => self::flagsFor($op, $memory),
            'dependencies' => $memory
                ? ['vfs-tempfile-open-lifecycle', 'temp-store-memory']
                : ['vfs-tempfile-open-lifecycle', 'vfs-xopen-deleteonclose', 'vfs-temp-exclusive-lock'],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $handles
     */
    private static function pendingDeleteCount(array $handles): int
    {
        $count = 0;
        foreach ($handles as $handle) {
            if (($handle['delete_on_close'] ?? false) && !($handle['memory'] ?? false)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array{handles:array<string,array<string,mixed>>,last_open:?string,sequence:int} $current
     * @return array{open_count:int,pending_delete_count:int,paths:list<string>,memory_count:int,requires_directory_write:bool,uses_wal:bool,uses_shm:bool}
     */
    private static function nextSnapshot(array $current): array
    {
        $paths = [];
        $memoryCount = 0;
        foreach ($current['handles'] as $handle) {
            if (($handle['memory'] ?? false) === true) {
                $memoryCount++;
                continue;
            }
            $paths[] = (string) $handle['path'];
        }

        return [
            'open_count' => count($current['handles']),
            'pending_delete_count' => self::pendingDeleteCount($current['handles']),
            'paths' => $paths,
            'memory_count' => $memoryCount,
            'requires_directory_write' => self::pendingDeleteCount($current['handles']) > 0,
            'uses_wal' => false,
            'uses_shm' => false,
        ];
    }

    /**
     * @param array{kind:string,handle:?string,suffix:string,delete_on_close:bool,exclusive:bool,readonly:bool} $op
     * @return list<string>
     */
    private static function flagsFor(array $op, bool $memory): array
    {
        if ($memory) {
            return ['SQLITE_OPEN_MEMORY', 'SQLITE_OPEN_TEMP_DB'];
        }

        $flags = ['SQLITE_OPEN_READWRITE', 'SQLITE_OPEN_CREATE', 'SQLITE_OPEN_TEMP_DB'];
        if ($op['delete_on_close']) {
            $flags[] = 'SQLITE_OPEN_DELETEONCLOSE';
        }
        if ($op['exclusive']) {
            $flags[] = 'SQLITE_OPEN_EXCLUSIVE';
        }

        return $flags;
    }

    private static function normalizeTempDir(string $tempDir): string
    {
        $trimmed = rtrim(str_replace('\\', '/', trim($tempDir)), '/');
        if ($trimmed === '' || str_contains($trimmed, "\0")) {
            throw new \InvalidArgumentException('SQLite temp-file directory must be a non-empty path');
        }

        return $trimmed;
    }

    private static function normalizeSegment(string $segment): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($segment));
        $normalized = trim((string) $normalized, '-_');
        if ($normalized === '') {
            return 'conn';
        }

        return strtolower($normalized);
    }

    private static function normalizeSuffix(string $suffix): string
    {
        $suffix = trim($suffix);
        if ($suffix === '') {
            return '';
        }
        if ($suffix[0] !== '.') {
            $suffix = '.' . $suffix;
        }
        if (str_contains($suffix, '/') || str_contains($suffix, '\\') || str_contains($suffix, "\0") || preg_match('/^\.[A-Za-z0-9_.-]+$/', $suffix) !== 1) {
            throw new \InvalidArgumentException('SQLite temp-file suffix must be a plain filename suffix');
        }

        return $suffix;
    }
}
