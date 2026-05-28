<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Plan
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
        string $savepoint = 'wp_options_rowvalue_returning_window_next235',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $discarded = self::windowRows($plan['discarded_attempt_returning'], 'discarded-attempt-window-next235', $rowIdColumn);
        $yielded = self::windowRows($plan['yielded_after_retry_returning'], 'yielded-retry-window-next235', $rowIdColumn);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next235';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next235'] = true;
        $plan['window_partition_keys_next235'] = ['phase', 'action'];
        $plan['window_order_keys_next235'] = [$rowIdColumn];
        $plan['discarded_attempt_window_rows_next235'] = $discarded;
        $plan['yielded_retry_window_rows_next235'] = $yielded;
        $plan['discarded_attempt_window_count_next235'] = count($discarded);
        $plan['yielded_retry_window_count_next235'] = count($yielded);
        $plan['discarded_attempt_window_ids_next235'] = array_column($discarded, $rowIdColumn);
        $plan['yielded_retry_window_ids_next235'] = array_column($yielded, $rowIdColumn);
        $plan['discarded_attempt_window_digest_next235'] = self::digest($discarded, $rowIdColumn);
        $plan['yielded_retry_window_digest_next235'] = self::digest($yielded, $rowIdColumn);
        $plan['window_yield_boundary_next235'] = [
            'discarded_attempt_rows' => count($discarded),
            'yielded_retry_rows' => count($yielded),
            'rollback_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-window-current-source-next235',
            'sqlite-rowvalue-delete-returning-window-current-source-next235',
            'sqlite-rowvalue-returning-window-savepoint-yield-boundary-next235',
        ];
        $plan['dependency_closure_next235'] = 'no new support component needed; next235 reuses native PHP row-value UPDATE/DELETE RETURNING savepoint execution and adds bounded RETURNING-window metadata over current-source streams';
        $plan['non_overlap_next235'] = 'adds RETURNING stream window row_number/partition metadata across discarded attempt rows and yielded retry rows; avoids accepted next231 compound subqueries, next232 current-source row-value behavior, next229 LIMIT -1 OFFSET tuple sources, trigger RETURNING, JSON table, WAL/VFS, planner, B-tree, PRAGMA, and encoding clusters';

        return $plan;
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $streams
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $streams, string $streamName, string $rowIdColumn): array
    {
        $rows = [];
        $partitionOrdinals = [];

        foreach ($streams as $stream) {
            $partition = $stream['phase'] . ':' . $stream['action'];
            foreach ($stream['rows'] as $row) {
                if (!array_key_exists($rowIdColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next235 rowid column {$rowIdColumn} is missing");
                }

                $partitionOrdinals[$partition] = ($partitionOrdinals[$partition] ?? 0) + 1;
                $windowRow = $row;
                $windowRow['window_stream_next235'] = $streamName;
                $windowRow['window_phase_next235'] = $stream['phase'];
                $windowRow['window_action_next235'] = $stream['action'];
                $windowRow['window_statement_ordinal_next235'] = $stream['ordinal'];
                $windowRow['window_partition_next235'] = $partition;
                $windowRow['window_row_number_next235'] = count($rows) + 1;
                $windowRow['window_partition_row_number_next235'] = $partitionOrdinals[$partition];
                $windowRow['window_first_in_partition_next235'] = $partitionOrdinals[$partition] === 1;
                $rows[] = $windowRow;
            }
        }

        usort($rows, static function (array $left, array $right) use ($rowIdColumn): int {
            return [$left['window_phase_next235'], $left['window_action_next235'], $left[$rowIdColumn]]
                <=> [$right['window_phase_next235'], $right['window_action_next235'], $right[$rowIdColumn]];
        });

        $partitionOrdinals = [];
        foreach ($rows as $index => $row) {
            $partition = $row['window_partition_next235'];
            $partitionOrdinals[$partition] = ($partitionOrdinals[$partition] ?? 0) + 1;
            $rows[$index]['window_row_number_next235'] = $index + 1;
            $rows[$index]['window_partition_row_number_next235'] = $partitionOrdinals[$partition];
            $rows[$index]['window_first_in_partition_next235'] = $partitionOrdinals[$partition] === 1;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows, string $rowIdColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) $row['window_action_next235'],
                (string) $row[$rowIdColumn],
                (string) $row['window_partition_row_number_next235'],
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }
}
