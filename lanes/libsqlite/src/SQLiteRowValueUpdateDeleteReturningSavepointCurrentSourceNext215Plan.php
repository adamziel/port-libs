<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext215Plan
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
        string $savepoint = 'wp_options_rowvalue_subquery_limit_next215',
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

        $plan['status'] = 'rowvalue-update-delete-returning-subquery-limit-savepoint-current-source-next215';
        foreach (['attempt_statements', 'retry_statements', 'discarded_attempt_returning', 'yielded_after_retry_returning'] as $streamKey) {
            foreach ($plan[$streamKey] as $index => $entry) {
                if (isset($entry['phase']) && is_string($entry['phase'])) {
                    $plan[$streamKey][$index]['phase'] = str_replace('next212', 'next215', $entry['phase']);
                }
            }
        }
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-in-select-order-limit-next215',
            'sqlite-rowvalue-delete-returning-not-in-select-order-limit-next215',
            'sqlite-rowvalue-subquery-limit-savepoint-current-source-next215',
        ];

        return $plan;
    }
}
