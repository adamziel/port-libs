<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rowsfailStatementRetry = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tablesfailStatementRetry = ['wp_options' => $rowsfailStatementRetry];
$uniquefailStatementRetry = [['blog_id', 'option_name']];

$preUpdatefailStatementRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('prefailStatementRetry', option_value || ':prefailStatementRetry', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preDeletefailStatementRetry = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN (VALUES (1, 'siteurl')) AS not_siteurl ORDER BY option_id";
$failUpdatefailStatementRetry = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_migrated' ELSE 'siteurl' END, 'failfailStatementRetry', option_value || ':failfailStatementRetry', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_conflict ORDER BY option_id";
$retryUpdatefailStatementRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('retryfailStatementRetry', option_value || ':retryfailStatementRetry', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme_migrated'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDeletefailStatementRetry = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$preUpdateResultfailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdatefailStatementRetry, $tablesfailStatementRetry, 'option_id', $uniquefailStatementRetry);
$preDeleteResultfailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDeletefailStatementRetry, $preUpdateResultfailStatementRetry()['tables'], 'option_id', $uniquefailStatementRetry);
$failProbefailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdatefailStatementRetry, $preDeleteResultfailStatementRetry()['tables'], 'option_id', [], true);
$failPreservefailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdatefailStatementRetry, $preDeleteResultfailStatementRetry()['tables'], 'option_id', $uniquefailStatementRetry, true);
$retryUpdateResultfailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdatefailStatementRetry, $failPreservefailStatementRetry()['tables'], 'option_id', $uniquefailStatementRetry);
$retryDeleteResultfailStatementRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeletefailStatementRetry, $retryUpdateResultfailStatementRetry()['tables'], 'option_id', $uniquefailStatementRetry);
$planfailStatementRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry(
    $tablesfailStatementRetry,
    [$preUpdatefailStatementRetry, $preDeletefailStatementRetry],
    $failUpdatefailStatementRetry,
    [$retryUpdatefailStatementRetry, $retryDeletefailStatementRetry],
    $uniquefailStatementRetry,
);
$customPlanfailStatementRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry(
    $tablesfailStatementRetry,
    [$preUpdatefailStatementRetry],
    $failUpdatefailStatementRetry,
    [$retryUpdatefailStatementRetry],
    $uniquefailStatementRetry,
    'wp_custom_failfailStatementRetry',
);

$casesfailStatementRetry = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdatefailStatementRetry)['conflict_action'], 'fail'],
    'parser fail assignments row value' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdatefailStatementRetry)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser fail where row value in' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdatefailStatementRetry)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDeletefailStatementRetry)['returning'], 'dropped_network_siteurl'), true],
    'pre update selected ids' => [static fn (): mixed => $preUpdateResultfailStatementRetry()['plan']->selectedIds, [7, 8]],
    'pre update returning ids' => [static fn (): mixed => array_column($preUpdateResultfailStatementRetry()['returning'], 'option_id'), [7, 8]],
    'pre update row seven value' => [static fn (): mixed => array_column($preUpdateResultfailStatementRetry()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:prefailStatementRetry'],
    'pre delete selected ids' => [static fn (): mixed => $preDeleteResultfailStatementRetry()['plan']->selectedIds, [3, 9]],
    'pre delete returning ids' => [static fn (): mixed => array_column($preDeleteResultfailStatementRetry()['returning'], 'option_id'), [3, 9]],
    'pre delete removes plugin batch' => [static fn (): mixed => in_array(9, array_column($preDeleteResultfailStatementRetry()['tables']['wp_options'], 'option_id'), true), false],
    'fail probe would return both ids without unique constraints' => [static fn (): mixed => array_column($failProbefailStatementRetry()['returning'], 'option_id'), [7, 8]],
    'fail preserve returns only prior successful row' => [static fn (): mixed => array_column($failPreservefailStatementRetry()['returning'], 'option_id'), [7]],
    'fail preserve records conflict row eight' => [static fn (): mixed => $failPreservefailStatementRetry()['failed_conflict']['row_id'], 8],
    'fail preserve records unique key' => [static fn (): mixed => $failPreservefailStatementRetry()['failed_conflict']['key'], '1|siteurl'],
    'fail preserves row seven mutation' => [static fn (): mixed => array_column($failPreservefailStatementRetry()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'fail restores row eight after conflict' => [static fn (): mixed => array_column($failPreservefailStatementRetry()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'fail keeps pre delete removals' => [static fn (): mixed => array_intersect([3, 9], array_column($failPreservefailStatementRetry()['tables']['wp_options'], 'option_id')), []],
    'retry update selected ids sees fail current source' => [static fn (): mixed => $retryUpdateResultfailStatementRetry()['plan']->selectedIds, [8, 7]],
    'retry update row seven keeps fail prefix' => [static fn (): mixed => $retryUpdateResultfailStatementRetry()['returning'][0]['option_value'], 'theme:prefailStatementRetry:failfailStatementRetry:retryfailStatementRetry'],
    'retry update row eight keeps pre prefix only' => [static fn (): mixed => $retryUpdateResultfailStatementRetry()['returning'][1]['option_value'], 'rules:prefailStatementRetry:retryfailStatementRetry'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResultfailStatementRetry()['plan']->selectedIds, [4, 10]],
    'retry delete network flag' => [static fn (): mixed => array_column($retryDeleteResultfailStatementRetry()['returning'], 'dropped_network_siteurl'), [0, 1]],

    'plan status' => [static fn (): mixed => $planfailStatementRetry()['status'], 'rowvalue-update-delete-returning-or-fail-current-source-fail-statement-retry'],
    'plan savepoint' => [static fn (): mixed => $planfailStatementRetry()['savepoint'], 'app_settings_rowvalue_fail_fail_statement_retry'],
    'plan savepoint preserved' => [static fn (): mixed => $planfailStatementRetry()['savepoint_preserved_after_fail'], true],
    'plan pre fail changes preserved' => [static fn (): mixed => $planfailStatementRetry()['pre_fail_changes_preserved'], true],
    'plan failing row restored' => [static fn (): mixed => $planfailStatementRetry()['failing_row_restored_to_statement_start'], true],
    'plan prior fail rows preserved' => [static fn (): mixed => $planfailStatementRetry()['failed_statement_prior_rows_preserved'], true],
    'plan failed returning suppressed' => [static fn (): mixed => $planfailStatementRetry()['failed_statement_returning_suppressed'], true],
    'plan retry reads fail current source' => [static fn (): mixed => $planfailStatementRetry()['retry_reads_fail_current_source'], true],
    'plan released after retry' => [static fn (): mixed => $planfailStatementRetry()['savepoint_released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $planfailStatementRetry()['savepoint_image_tables'], $tablesfailStatementRetry],
    'plan pre fail row seven changed' => [static fn (): mixed => array_column($planfailStatementRetry()['pre_fail_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'prefailStatementRetry'],
    'plan pre fail row nine deleted' => [static fn (): mixed => in_array(9, array_column($planfailStatementRetry()['pre_fail_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan fail row seven migrated' => [static fn (): mixed => array_column($planfailStatementRetry()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'plan fail row eight restored' => [static fn (): mixed => array_column($planfailStatementRetry()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan final row seven retry' => [static fn (): mixed => array_column($planfailStatementRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retryfailStatementRetry'],
    'plan final row eight retry' => [static fn (): mixed => array_column($planfailStatementRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retryfailStatementRetry'],
    'plan final deleted ids gone' => [static fn (): mixed => array_intersect([3, 4, 9, 10], array_column($planfailStatementRetry()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $planfailStatementRetry()['next_source_tables'], $planfailStatementRetry()['current_source_tables']],
    'plan pre fail phases' => [static fn (): mixed => array_column($planfailStatementRetry()['pre_fail_statements'], 'phase'), ['before-fail-fail-statement-retry', 'before-fail-fail-statement-retry']],
    'plan fail phase' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['phase'], 'or-fail-fail-statement-retry'],
    'plan fail selected ids' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['selected_ids'], [7, 8]],
    'plan fail mutation ids' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['mutation_ids'], [7, 8]],
    'plan fail marked failed' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['failed'], true],
    'plan fail conflict row' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['failed_conflict']['row_id'], 8],
    'plan fail conflict key' => [static fn (): mixed => $planfailStatementRetry()['fail_statement']['failed_conflict']['key'], '1|siteurl'],
    'plan fail returning ids' => [static fn (): mixed => array_column($planfailStatementRetry()['fail_statement']['returning_rows'], 'option_id'), [7]],
    'plan fail probe returning ids' => [static fn (): mixed => array_column($planfailStatementRetry()['fail_statement']['probe_returning_rows'], 'option_id'), [7, 8]],
    'plan suppressed ids' => [static fn (): mixed => array_column($planfailStatementRetry()['suppressed_by_fail_returning'], 'option_id'), [8]],
    'plan retry phases' => [static fn (): mixed => array_column($planfailStatementRetry()['retry_statements'], 'phase'), ['retry-after-fail-fail-statement-retry', 'retry-after-fail-fail-statement-retry']],
    'plan retry update source ids' => [static fn (): mixed => array_column($planfailStatementRetry()['retry_statements'][0]['source_rows'], 'option_id'), [7, 8]],
    'plan retry delete source ids' => [static fn (): mixed => array_column($planfailStatementRetry()['retry_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan pre fail yielded count' => [static fn (): mixed => $planfailStatementRetry()['pre_fail_yielded_count'], 4],
    'plan fail preserved yielded count' => [static fn (): mixed => $planfailStatementRetry()['fail_preserved_yielded_count'], 1],
    'plan suppressed count' => [static fn (): mixed => $planfailStatementRetry()['suppressed_by_fail_count'], 1],
    'plan retry yielded count' => [static fn (): mixed => $planfailStatementRetry()['yielded_after_retry_count'], 4],
    'plan pre fail changes count' => [static fn (): mixed => $planfailStatementRetry()['pre_fail_changes_preserved_count'], 4],
    'plan fail changes count' => [static fn (): mixed => $planfailStatementRetry()['fail_changes_preserved_count'], 1],
    'plan retry changes count' => [static fn (): mixed => $planfailStatementRetry()['retry_changes_after_fail'], 4],
    'plan changed tables' => [static fn (): mixed => $planfailStatementRetry()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $planfailStatementRetry()['row_counts']['wp_options'], 6],
    'plan dependency fail preserves prior' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-preserves-prior-returning-fail-statement-retry', $planfailStatementRetry()['dependencies'], true), true],
    'plan dependency fail suppresses conflict' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-suppresses-conflicting-returning-fail-statement-retry', $planfailStatementRetry()['dependencies'], true), true],
    'plan dependency retry delete' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-fail-fail-statement-retry', $planfailStatementRetry()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlanfailStatementRetry()['savepoint'], 'wp_custom_failfailStatementRetry'],
    'custom pre fail count' => [static fn (): mixed => $customPlanfailStatementRetry()['pre_fail_yielded_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlanfailStatementRetry()['yielded_after_retry_count'], 2],
    'malformed empty pre fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [], $failUpdatefailStatementRetry, [$retryUpdatefailStatementRetry], $uniquefailStatementRetry), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [$preUpdatefailStatementRetry], '', [$retryUpdatefailStatementRetry], $uniquefailStatementRetry), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [$preUpdatefailStatementRetry], $failUpdatefailStatementRetry, [], $uniquefailStatementRetry), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [$preUpdatefailStatementRetry], $failUpdatefailStatementRetry, [$retryUpdatefailStatementRetry], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [$preUpdatefailStatementRetry], $failUpdatefailStatementRetry, [$retryUpdatefailStatementRetry], $uniquefailStatementRetry, 'bad-name'), InvalidArgumentException::class],
    'malformed fail action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry($tablesfailStatementRetry, [$preUpdatefailStatementRetry], str_replace('OR FAIL', 'OR ABORT', $failUpdatefailStatementRetry), [$retryUpdatefailStatementRetry], $uniquefailStatementRetry), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailStatementRetry(['wp_options' => ['bad']], [$preUpdatefailStatementRetry], $failUpdatefailStatementRetry, [$retryUpdatefailStatementRetry], $uniquefailStatementRetry), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesfailStatementRetry as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source fail-statement-retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
