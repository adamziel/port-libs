<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext226Plan
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
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next226',
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

        $plan = self::replaceMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next226';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependency_closure_next226'] = 'no new support component needed; next226 reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded SELECT DISTINCT tuple-source handling';
        $plan['non_overlap_next226'] = 'adds SELECT DISTINCT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next217 OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-distinct-select-subquery-next226',
            'sqlite-rowvalue-delete-returning-distinct-select-subquery-next226',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next226',
        ];

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next226', 'distinct-subquery'], $value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = self::replaceMarker($entry);
        }

        return $value;
    }
}
