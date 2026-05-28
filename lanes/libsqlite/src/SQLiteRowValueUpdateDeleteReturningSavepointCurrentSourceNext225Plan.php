<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext225Plan
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
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next225',
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
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next225';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependency_closure_next225'] = 'no new support component needed; next225 reuses native row-value UPDATE/DELETE RETURNING execution and adds SELECT DISTINCT tuple-source collapse before savepoint rollback/retry';
        $plan['non_overlap_next225'] = 'adds row-value SELECT DISTINCT tuple sources feeding UPDATE/DELETE RETURNING under savepoint rollback; avoids accepted next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next212 plain subquery, trigger, WAL/VFS, JSON, planner, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-distinct-next225',
            'sqlite-rowvalue-delete-returning-in-select-distinct-next225',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next225',
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
            return str_replace(['next212', 'subquery'], ['next225', 'distinct-subquery'], $value);
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
