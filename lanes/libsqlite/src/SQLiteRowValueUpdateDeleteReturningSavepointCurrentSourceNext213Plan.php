<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext213Plan
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
        string $savepoint = 'wp_options_rowvalue_order_limit_next213',
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
        $plan['status'] = 'rowvalue-update-delete-returning-order-limit-subquery-savepoint-current-source-next213';
        $plan['ordered_limited_subquery_source'] = true;
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-order-limit-next213',
            'sqlite-rowvalue-delete-returning-in-select-order-limit-next213',
            'sqlite-rowvalue-order-limit-subquery-savepoint-current-source-next213',
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
            return str_replace(['next212', 'subquery'], ['next213', 'order-limit-subquery'], $value);
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
