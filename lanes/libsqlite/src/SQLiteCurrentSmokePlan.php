<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCurrentSmokePlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $stagedRows
     * @param array{begin?:string,database_path?:string,page_size?:int,fail_on_error?:bool,statement_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function keyValueImport(array $currentRows, array $stagedRows, array $options = []): array
    {
        $plan = SQLiteImportTransactionErrorYieldPlan::plan($currentRows, $stagedRows, $options);

        $yieldedStatuses = [];
        $yieldedEvents = [];
        $errorCodes = [];
        foreach ($plan['yielded'] as $yielded) {
            $yieldedStatuses[] = $yielded['status'];
            $yieldedEvents[] = $yielded['event'];
            if (is_array($yielded['error'] ?? null)) {
                $errorCodes[] = $yielded['error']['code'];
            }
        }

        $finalNames = [];
        $loadPolicyByName = [];
        foreach ($plan['final_rows'] as $row) {
            $name = (string) $row['key_name'];
            $finalNames[] = $name;
            $loadPolicyByName[$name] = (string) $row['load_policy'];
        }
        sort($finalNames, SORT_STRING);

        return [
            'status' => $plan['status'],
            'begin_mode' => $plan['begin']['mode'] ?? null,
            'write_lock_acquired' => $plan['begin']['write_lock_acquired'] ?? null,
            'current_count' => $plan['current_count'],
            'staged_count' => $plan['staged_count'],
            'applied_count' => $plan['applied_count'],
            'error_count' => $plan['error_count'],
            'yielded_statuses' => $yieldedStatuses,
            'yielded_events' => $yieldedEvents,
            'error_codes' => $errorCodes,
            'final_key_names' => $finalNames,
            'load_policy_by_name' => $loadPolicyByName,
            'dirty_pages' => $plan['dirty_pages'],
            'rollback' => $plan['rollback'],
            'dependencies' => array_values(array_unique(array_merge($plan['dependencies'], [
                'sqlite-application-current-smoke-key-value-import',
                'sqlite-application-current-smoke-yield-summary',
            ]))),
        ];
    }
}
