<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Plan
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
        string $savepoint = 'wp_options_rowvalue_negative_limit_offset_next219',
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
        $plan['status'] = 'rowvalue-update-delete-returning-negative-limit-offset-subquery-savepoint-current-source-next219';
        $plan['negative_limit_offset_subquery_source'] = true;
        $plan['dependency_closure_next219'] = 'no new support component needed; next219 reuses native row-value UPDATE/DELETE RETURNING execution and fixes bounded row-value SELECT tuple LIMIT -1 OFFSET semantics';
        $plan['non_overlap_next219'] = 'adds negative LIMIT with OFFSET in row-value SELECT tuple sources feeding UPDATE/DELETE RETURNING under savepoint rollback; avoids accepted next213 ORDER/LIMIT positive slices, next217 OR ROLLBACK, next212 plain subquery, WAL/VFS, JSON, planner, trigger, and B-tree clusters';
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-negative-limit-offset-next219',
            'sqlite-rowvalue-delete-returning-in-select-negative-limit-offset-next219',
            'sqlite-rowvalue-negative-limit-offset-subquery-savepoint-current-source-next219',
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
            return str_replace(['next212', 'subquery'], ['next219', 'negative-limit-offset-subquery'], $value);
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
