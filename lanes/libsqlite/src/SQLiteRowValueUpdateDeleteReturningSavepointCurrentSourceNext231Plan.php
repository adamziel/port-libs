<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext231Plan
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
        string $savepoint = 'wp_options_rowvalue_compound_subquery_next231',
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
        $plan['status'] = 'rowvalue-update-delete-returning-compound-subquery-savepoint-current-source-next231';
        $plan['savepoint'] = $savepoint;
        $plan['compound_subquery_source_next231'] = true;
        $plan['compound_operators_next231'] = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT'];
        $plan['dependency_closure_next231'] = 'no new support component needed; next231 reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded compound SELECT tuple-source handling';
        $plan['non_overlap_next231'] = 'adds UNION/UNION ALL/INTERSECT/EXCEPT tuple sources feeding row-value UPDATE/DELETE RETURNING under savepoint rollback and retry; avoids accepted next226 DISTINCT subqueries, next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next217 OR ROLLBACK, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-compound-select-subquery-next231',
            'sqlite-rowvalue-delete-returning-compound-select-subquery-next231',
            'sqlite-rowvalue-compound-subquery-savepoint-current-source-next231',
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
            return str_replace(['next212', 'subquery'], ['next231', 'compound-subquery'], $value);
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
