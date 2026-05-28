<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_returning_window_next238',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $discarded = self::tagSourceRows(
            $plan['discarded_attempt_window_rows_next235'],
            'discarded-current-source-next238',
            $rowIdColumn,
        );
        $yielded = self::tagSourceRows(
            $plan['yielded_retry_window_rows_next235'],
            'yielded-next-source-next238',
            $rowIdColumn,
        );
        $pairs = self::pairRows($discarded, $yielded, $rowIdColumn);
        $summary = self::summary($pairs);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next238';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next238'] = true;
        $plan['current_source_window_rows_next238'] = $discarded;
        $plan['next_source_window_rows_next238'] = $yielded;
        $plan['window_pair_rows_next238'] = $pairs;
        $plan['window_pair_count_next238'] = count($pairs);
        $plan['window_pair_summary_next238'] = $summary;
        $plan['window_current_source_ids_next238'] = array_column($discarded, $rowIdColumn);
        $plan['window_next_source_ids_next238'] = array_column($yielded, $rowIdColumn);
        $plan['window_replayed_rowids_next238'] = self::idsForClass($pairs, 'replayed-after-rollback');
        $plan['window_restart_only_rowids_next238'] = self::idsForClass($pairs, 'restart-only');
        $plan['window_discarded_only_rowids_next238'] = self::idsForClass($pairs, 'discarded-only');
        $plan['window_source_fence_next238'] = [
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
            'current_source_digest' => self::digest($discarded, $rowIdColumn),
            'next_source_digest' => self::digest($yielded, $rowIdColumn),
            'pair_digest' => self::digest($pairs, 'pair_key_next238'),
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-current-source-fence-next238',
            'sqlite-rowvalue-update-returning-window-replay-next238',
            'sqlite-rowvalue-delete-returning-window-restart-next238',
        ];
        $plan['dependency_closure_next238'] = 'no new support component needed; next238 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, and next235 RETURNING-window rows.';
        $plan['non_overlap_next238'] = 'adds current-source/next-source RETURNING window pair classification after rollback; avoids accepted nullable row-value savepoint cases, next232-next235 window materialization, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, and encoding clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagSourceRows(array $rows, string $source, string $rowIdColumn): array
    {
        $tagged = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} must be int or string");
            }
            $action = $row['window_action_next235'] ?? null;
            if (!is_string($action) || $action === '') {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 rows need next235 action metadata');
            }

            $row['window_source_next238'] = $source;
            $row['window_source_key_next238'] = $action . ':' . $id;
            $row['window_current_source_candidate_next238'] = $source === 'discarded-current-source-next238';
            $row['window_yielded_after_retry_next238'] = $source === 'yielded-next-source-next238';
            $tagged[] = $row;
        }

        return $tagged;
    }

    /**
     * @param list<array<string,mixed>> $discarded
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function pairRows(array $discarded, array $yielded, string $rowIdColumn): array
    {
        $discardedByKey = self::rowsByKey($discarded);
        $yieldedByKey = self::rowsByKey($yielded);
        $keys = array_values(array_unique(array_merge(array_keys($discardedByKey), array_keys($yieldedByKey))));
        usort($keys, static function (string $left, string $right): int {
            [$leftAction, $leftId] = self::splitPairKey($left);
            [$rightAction, $rightId] = self::splitPairKey($right);
            $action = $leftAction <=> $rightAction;
            if ($action !== 0) {
                return $action;
            }
            if (ctype_digit($leftId) && ctype_digit($rightId)) {
                return (int) $leftId <=> (int) $rightId;
            }

            return $leftId <=> $rightId;
        });

        $pairs = [];
        foreach ($keys as $ordinal => $key) {
            $current = $discardedByKey[$key] ?? null;
            $next = $yieldedByKey[$key] ?? null;
            $class = self::pairClass($current, $next);
            $rowId = self::pairRowId($current, $next, $rowIdColumn);
            $action = self::pairAction($current, $next);

            $pairs[] = [
                'pair_ordinal_next238' => $ordinal,
                'pair_key_next238' => $key,
                'rowid_next238' => $rowId,
                'action_next238' => $action,
                'pair_class_next238' => $class,
                'current_window_row_number_next238' => $current['window_row_number_next235'] ?? null,
                'next_window_row_number_next238' => $next['window_row_number_next235'] ?? null,
                'current_partition_row_number_next238' => $current['window_partition_row_number_next235'] ?? null,
                'next_partition_row_number_next238' => $next['window_partition_row_number_next235'] ?? null,
                'current_status_next238' => $current['status'] ?? null,
                'next_status_next238' => $next['status'] ?? null,
                'current_option_value_next238' => $current['option_value'] ?? null,
                'next_option_value_next238' => $next['option_value'] ?? null,
                'current_present_next238' => $current !== null,
                'next_present_next238' => $next !== null,
                'rollback_preserved_current_next238' => $current !== null && $next === null,
                'retry_replayed_next238' => $current !== null && $next !== null,
                'retry_restart_only_next238' => $current === null && $next !== null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function rowsByKey(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = $row['window_source_key_next238'] ?? null;
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 rows need a source key');
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairClass(?array $current, ?array $next): string
    {
        if ($current !== null && $next !== null) {
            return 'replayed-after-rollback';
        }
        if ($current !== null) {
            return 'discarded-only';
        }
        if ($next !== null) {
            return 'restart-only';
        }

        throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 empty pair is invalid');
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitPairKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 pair key is malformed');
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairRowId(?array $current, ?array $next, string $rowIdColumn): int|string
    {
        $row = $current ?? $next;
        if ($row === null || !array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairAction(?array $current, ?array $next): string
    {
        $row = $current ?? $next;
        $action = $row['window_action_next235'] ?? null;
        if (!is_string($action) || $action === '') {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 row action is missing');
        }

        return $action;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return array<string,int>
     */
    private static function summary(array $pairs): array
    {
        $summary = [
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'update' => 0,
            'delete' => 0,
        ];
        foreach ($pairs as $pair) {
            $class = (string) $pair['pair_class_next238'];
            $action = (string) $pair['action_next238'];
            $summary[$class] = ($summary[$class] ?? 0) + 1;
            $summary[$action] = ($summary[$action] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return list<int|string>
     */
    private static function idsForClass(array $pairs, string $class): array
    {
        $ids = [];
        foreach ($pairs as $pair) {
            if ($pair['pair_class_next238'] === $class) {
                $id = $pair['rowid_next238'];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows, string $keyColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) ($row[$keyColumn] ?? ''),
                (string) ($row['window_source_next238'] ?? $row['pair_class_next238'] ?? ''),
                (string) ($row['window_row_number_next235'] ?? $row['pair_ordinal_next238'] ?? ''),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }
}
