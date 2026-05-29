<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext216Plan
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
        string $savepoint = 'wp_options_rowvalue_distinct_subquery_next216',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext212(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $plan = self::replaceMarker($plan);
        $plan['status'] = 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next216';
        $plan['savepoint'] = $savepoint;
        $plan['distinct_subquery_source'] = true;
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-distinct-subquery-next216',
            'sqlite-rowvalue-delete-returning-in-select-distinct-subquery-next216',
            'sqlite-rowvalue-distinct-subquery-savepoint-current-source-next216',
        ];
        $plan['dependency_closure_next216'] = 'no new support component needed; next216 reuses native PHP row-value UPDATE/DELETE RETURNING, SELECT subquery tuple materialization, and savepoint current-source retry images';
        $plan['non_overlap_next216'] = 'adds SELECT DISTINCT tuple de-duplication for row-value UPDATE/DELETE RETURNING subqueries; avoids next212 plain subqueries, next213 ORDER/LIMIT subqueries, next210 OR IGNORE, next176 NULL inequality, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters';

        return $plan;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function replaceMarker(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(['next212', 'subquery'], ['next216', 'distinct-subquery'], $value);
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
