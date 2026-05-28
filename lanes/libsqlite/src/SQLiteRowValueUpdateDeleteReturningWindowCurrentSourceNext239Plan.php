<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next239',
        string $rowIdColumn = 'option_id',
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryPartitions = self::statementPartitions($base['retry_returning'], $rowIdColumn);
        $suppressedPartitions = self::statementPartitions($base['suppressed_attempt_returning'], $rowIdColumn);
        $yieldPartitions = self::statementPartitions($base['yield_returning'], $rowIdColumn);
        $releaseSeal = self::releaseSeal($base, $retryPartitions, $suppressedPartitions, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next239',
            'statement_partition_window_next239' => true,
            'yield_statement_windows_next239' => $yieldPartitions,
            'suppressed_statement_windows_next239' => $suppressedPartitions,
            'retry_statement_windows_next239' => $retryPartitions,
            'retry_statement_window_ids_next239' => self::partitionIds($retryPartitions, $rowIdColumn),
            'retry_statement_window_tiles_next239' => self::partitionColumn($retryPartitions, 'ntile_2'),
            'retry_statement_window_exclude_ids_next239' => self::partitionColumn($retryPartitions, 'exclude_current_neighbor_ids'),
            'retry_statement_window_percent_rank_next239' => self::partitionColumn($retryPartitions, 'percent_rank_milli'),
            'retry_statement_window_cume_dist_next239' => self::partitionColumn($retryPartitions, 'cume_dist_milli'),
            'retry_statement_window_edges_next239' => self::partitionEdges($retryPartitions),
            'release_window_seal_next239' => $releaseSeal,
            'dependency_closure_next239' => 'no new support component needed; next239 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint current-source images, and lane-local window rows while adding statement-partitioned retry release seals.',
            'dependencies_next239' => [
                'sqlite-rowvalue-returning-statement-window-next239',
                'sqlite-returning-window-exclude-current-after-rollback-next239',
                'wordpress-rowvalue-returning-release-window-seal-next239',
            ],
            'non_overlap_next239' => 'adds statement-partitioned retry RETURNING windows, ntile/percent-rank/cume-dist receipts, and EXCLUDE CURRENT ROW neighbor frames after rollback/release; avoids accepted next233 row_number/dense_rank/count/sum windows, next236 current-row frames, row-value UPSERT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $returning
     * @return array<string,list<array<string,mixed>>>
     */
    private static function statementPartitions(array $returning, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($returning as $statement) {
            if (!isset($statement['rows']) || !is_array($statement['rows'])) {
                throw new \InvalidArgumentException('SQLite row-value statement window next239 returning rows are malformed');
            }
            $key = self::partitionKey($statement);
            $rows = $statement['rows'];
            usort($rows, static function (array $left, array $right) use ($rowIdColumn): int {
                $bytes = self::numericValue($right['bytes'] ?? null) <=> self::numericValue($left['bytes'] ?? null);
                if ($bytes !== 0) {
                    return $bytes;
                }

                return self::rowIdValue($left, $rowIdColumn) <=> self::rowIdValue($right, $rowIdColumn);
            });

            $count = count($rows);
            $sum = 0;
            foreach ($rows as $row) {
                $sum += self::numericValue($row['bytes'] ?? null);
            }

            $windowRows = [];
            $previousBytes = null;
            $rank = 1;
            $denseRank = 0;
            foreach ($rows as $index => $row) {
                $bytes = self::numericValue($row['bytes'] ?? null);
                if ($previousBytes === null || $bytes !== $previousBytes) {
                    $rank = $index + 1;
                    ++$denseRank;
                    $previousBytes = $bytes;
                }

                $windowRows[] = [
                    $rowIdColumn => self::rowIdValue($row, $rowIdColumn),
                    'option_name' => (string) ($row['option_name'] ?? ''),
                    'status' => $row['status'] ?? null,
                    'bytes' => $bytes,
                    'statement_key' => $key,
                    'statement_action' => (string) ($statement['action'] ?? ''),
                    'statement_ordinal' => (int) ($statement['ordinal'] ?? 0),
                    'row_number' => $index + 1,
                    'rank' => $rank,
                    'dense_rank' => $denseRank,
                    'partition_count' => $count,
                    'partition_sum' => $sum,
                    'ntile_2' => self::ntile($index, $count, 2),
                    'percent_rank_milli' => self::percentRankMilli($rank, $count),
                    'cume_dist_milli' => self::cumeDistMilli($rows, $bytes),
                    'first_value_name' => (string) ($rows[0]['option_name'] ?? ''),
                    'last_value_name' => (string) ($rows[$count - 1]['option_name'] ?? ''),
                    'exclude_current_neighbor_ids' => self::neighborIds($rows, $index, $rowIdColumn),
                    'window_token' => $key . ':' . self::rowIdValue($row, $rowIdColumn) . ':' . $bytes . ':' . $sum,
                ];
            }

            $partitions[$key] = $windowRows;
        }

        return $partitions;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $retryPartitions
     * @param array<string,list<array<string,mixed>>> $suppressedPartitions
     * @return array<string,mixed>
     */
    private static function releaseSeal(array $base, array $retryPartitions, array $suppressedPartitions, string $rowIdColumn): array
    {
        $retryIds = [];
        foreach ($retryPartitions as $rows) {
            array_push($retryIds, ...array_column($rows, $rowIdColumn));
        }
        $suppressedIds = [];
        foreach ($suppressedPartitions as $rows) {
            array_push($suppressedIds, ...array_column($rows, $rowIdColumn));
        }

        return [
            'savepoint' => $base['savepoint'],
            'retry_partition_keys' => array_keys($retryPartitions),
            'suppressed_partition_keys' => array_keys($suppressedPartitions),
            'retry_ids' => $retryIds,
            'suppressed_ids' => $suppressedIds,
            'suppressed_ids_excluded_from_release' => array_values(array_diff($suppressedIds, $retryIds)),
            'retry_window_tokens' => self::partitionColumn($retryPartitions, 'window_token'),
            'current_source_token' => hash('sha256', json_encode($base['current_source_tables'], JSON_THROW_ON_ERROR)),
            'next_source_matches_current' => $base['next_source_tables'] === $base['current_source_tables'],
            'attempt_tables_suppressed' => $base['attempt_current_source_tables'] !== $base['current_source_tables'],
            'rollback_source_restored' => $base['rollback_current_source_tables'] === $base['savepoint_image_tables'],
        ];
    }

    /**
     * @param array{phase?:mixed,ordinal?:mixed,action?:mixed} $statement
     */
    private static function partitionKey(array $statement): string
    {
        return (string) ($statement['phase'] ?? 'phase') . '#' . (int) ($statement['ordinal'] ?? 0) . '#' . (string) ($statement['action'] ?? 'statement');
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<int|string>>
     */
    private static function partitionIds(array $partitions, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($partitions as $key => $rows) {
            $ids[$key] = array_column($rows, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<mixed>>
     */
    private static function partitionColumn(array $partitions, string $column): array
    {
        $values = [];
        foreach ($partitions as $key => $rows) {
            $values[$key] = array_column($rows, $column);
        }

        return $values;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,array{first:?string,last:?string,count:int,sum:int}>
     */
    private static function partitionEdges(array $partitions): array
    {
        $edges = [];
        foreach ($partitions as $key => $rows) {
            $edges[$key] = [
                'first' => $rows[0]['first_value_name'] ?? null,
                'last' => $rows[0]['last_value_name'] ?? null,
                'count' => count($rows),
                'sum' => array_sum(array_column($rows, 'bytes')),
            ];
        }

        return $edges;
    }

    private static function ntile(int $index, int $count, int $buckets): int
    {
        if ($count <= 0 || $buckets <= 0) {
            return 0;
        }

        return intdiv($index * $buckets, $count) + 1;
    }

    private static function percentRankMilli(int $rank, int $count): int
    {
        if ($count <= 1) {
            return 0;
        }

        return (int) round((($rank - 1) / ($count - 1)) * 1000);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function cumeDistMilli(array $rows, int $bytes): int
    {
        $count = count($rows);
        if ($count === 0) {
            return 0;
        }
        $lessOrEqual = 0;
        foreach ($rows as $row) {
            if (self::numericValue($row['bytes'] ?? null) >= $bytes) {
                ++$lessOrEqual;
            }
        }

        return (int) round(($lessOrEqual / $count) * 1000);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function neighborIds(array $rows, int $index, string $rowIdColumn): array
    {
        $ids = [];
        foreach ([$index - 1, $index + 1] as $neighbor) {
            if (isset($rows[$neighbor])) {
                $ids[] = self::rowIdValue($rows[$neighbor], $rowIdColumn);
            }
        }

        return $ids;
    }

    private static function numericValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValue(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value statement window next239 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value statement window next239 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }
}
