<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachDetachTransactionPlan
{
    /**
     * @param array<string,array{file?:string|null, attached?:bool, dirty_pages?:list<int>, active_statements?:int, savepoint_depth?:int, wal_reader?:bool, wal_frames?:int, temp?:bool, journal_mode?:string, lock?:string|null}> $schemas
     * @return array<string,mixed>
     */
    public static function currentNext(array $schemas, string $detachSchema): array
    {
        $normalized = self::normalizeSchemas($schemas);
        $target = self::normalizeIdentifier($detachSchema);
        $currentOrder = self::databaseOrder($normalized);

        $blockedReasons = [];
        if ($target === '') {
            $blockedReasons[] = 'missing_schema_name';
        } elseif ($target === 'main' || $target === 'temp') {
            $blockedReasons[] = 'reserved_schema';
        } elseif (!isset($normalized[$target])) {
            $blockedReasons[] = 'schema_not_attached';
        }

        $targetState = $normalized[$target] ?? null;
        if ($targetState !== null) {
            if (($targetState['active_statements'] ?? 0) > 0) {
                $blockedReasons[] = 'active_statement';
            }
            if (($targetState['dirty_pages'] ?? []) !== []) {
                $blockedReasons[] = 'dirty_pager_pages';
            }
            if (($targetState['savepoint_depth'] ?? 0) > 0) {
                $blockedReasons[] = 'open_savepoint';
            }
            if (($targetState['wal_reader'] ?? false) === true) {
                $blockedReasons[] = 'wal_reader_snapshot';
            }
            if (self::lockRank($targetState['lock'] ?? null) >= self::lockRank('reserved')) {
                $blockedReasons[] = 'reserved_or_exclusive_lock';
            }
        }

        $status = $blockedReasons === [] ? 'detached' : 'blocked';
        $nextSchemas = $normalized;
        $operations = [];
        $sidecarCleanup = [];
        if ($status === 'detached') {
            unset($nextSchemas[$target]);
            $journalMode = strtolower((string) ($targetState['journal_mode'] ?? 'delete'));
            if ($journalMode === 'wal') {
                $sidecarCleanup[] = $target . '-wal';
                $sidecarCleanup[] = $target . '-shm';
                $operations[] = ['op' => 'checkpoint_before_detach', 'schema' => $target, 'reason' => 'wal_database_detach'];
            } elseif (($targetState['temp'] ?? false) === true || $journalMode === 'memory') {
                $operations[] = ['op' => 'discard_transient_pager', 'schema' => $target, 'reason' => 'temporary_or_memory_database_detach'];
            }
            $operations[] = ['op' => 'close_btree', 'schema' => $target, 'reason' => 'detach_database'];
            $operations[] = ['op' => 'renumber_database_array', 'schema' => $target, 'reason' => 'detach_compacts_aDb'];
        }

        return [
            'status' => $status,
            'operation' => 'attach-detach-transaction-current',
            'target_schema' => $target,
            'blocked' => $status === 'blocked',
            'blocked_reasons' => array_values(array_unique($blockedReasons)),
            'sqlite_error' => $status === 'blocked' ? 'database ' . ($target === '' ? '?' : $target) . ' is locked' : null,
            'current_database_list' => self::databaseList($normalized, $currentOrder),
            'next_database_list' => self::databaseList($nextSchemas, self::databaseOrder($nextSchemas)),
            'detached_schema' => $status === 'detached' ? $target : null,
            'sidecar_cleanup' => $sidecarCleanup,
            'operations' => $operations,
            'remaining_attached' => array_values(array_filter(
                self::databaseOrder($nextSchemas),
                static fn (string $schema): bool => $schema !== 'main' && $schema !== 'temp',
            )),
            'dependencies' => [
                'sqlite-attach-detach-transaction-current',
                'sqlite-detach-database-locked-admission',
                'sqlite-attached-database-array-renumber',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return array<string,array<string,mixed>>
     */
    private static function normalizeSchemas(array $schemas): array
    {
        $normalized = [
            'main' => ['file' => null, 'attached' => true, 'sequence' => 0],
            'temp' => ['file' => '', 'attached' => true, 'sequence' => 1, 'temp' => true],
        ];

        foreach ($schemas as $schema => $state) {
            if (!is_array($state)) {
                throw new \InvalidArgumentException('SQLite DETACH transaction schema state must be an array');
            }
            $name = self::normalizeIdentifier((string) $schema);
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite DETACH transaction schema names cannot be empty');
            }
            $normalized[$name] = array_merge($normalized[$name] ?? [], $state);
        }

        $sequence = 2;
        foreach (array_keys($normalized) as $name) {
            if ($name === 'main') {
                $normalized[$name]['sequence'] = 0;
            } elseif ($name === 'temp') {
                $normalized[$name]['sequence'] = 1;
            } else {
                $normalized[$name]['sequence'] = $sequence++;
            }

            $normalized[$name]['dirty_pages'] = self::positiveIntList($normalized[$name]['dirty_pages'] ?? []);
            $normalized[$name]['active_statements'] = self::nonNegativeInt($normalized[$name]['active_statements'] ?? 0, 'active statements');
            $normalized[$name]['savepoint_depth'] = self::nonNegativeInt($normalized[$name]['savepoint_depth'] ?? 0, 'savepoint depth');
            $normalized[$name]['wal_frames'] = self::nonNegativeInt($normalized[$name]['wal_frames'] ?? 0, 'WAL frame count');
            $normalized[$name]['journal_mode'] = strtolower((string) ($normalized[$name]['journal_mode'] ?? 'delete'));
            $normalized[$name]['lock'] = strtolower((string) ($normalized[$name]['lock'] ?? 'none'));
            $normalized[$name]['wal_reader'] = (bool) ($normalized[$name]['wal_reader'] ?? false);
        }

        return $normalized;
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @return list<string>
     */
    private static function databaseOrder(array $schemas): array
    {
        uasort($schemas, static fn (array $a, array $b): int => ((int) $a['sequence']) <=> ((int) $b['sequence']));
        return array_keys($schemas);
    }

    /**
     * @param array<string,array<string,mixed>> $schemas
     * @param list<string> $order
     * @return list<array<string,mixed>>
     */
    private static function databaseList(array $schemas, array $order): array
    {
        $rows = [];
        foreach ($order as $seq => $name) {
            $state = $schemas[$name];
            $rows[] = [
                'seq' => $seq,
                'name' => $name,
                'file' => $state['file'] ?? null,
                'journal_mode' => $state['journal_mode'] ?? 'delete',
                'dirty_pages' => $state['dirty_pages'] ?? [],
                'active_statements' => $state['active_statements'] ?? 0,
                'savepoint_depth' => $state['savepoint_depth'] ?? 0,
                'wal_reader' => $state['wal_reader'] ?? false,
                'lock' => $state['lock'] ?? 'none',
            ];
        }

        return $rows;
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ((str_starts_with($identifier, '"') && str_ends_with($identifier, '"'))
            || (str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))
            || (str_starts_with($identifier, '[') && str_ends_with($identifier, ']'))
        ) {
            $identifier = substr($identifier, 1, -1);
        }

        return strtolower(trim($identifier));
    }

    /**
     * @return list<int>
     */
    private static function positiveIntList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite DETACH transaction dirty pages must be an array');
        }
        $pages = [];
        foreach ($value as $page) {
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite DETACH transaction dirty pages must be one-based integers');
            }
            $pages[$page] = true;
        }
        $result = array_keys($pages);
        sort($result, SORT_NUMERIC);

        return $result;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite DETACH transaction {$label} must be a non-negative integer");
        }

        return $value;
    }

    private static function lockRank(?string $lock): int
    {
        return match (strtolower((string) $lock)) {
            'shared' => 1,
            'reserved' => 2,
            'pending' => 3,
            'exclusive' => 4,
            default => 0,
        };
    }
}
