<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows209 = [
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

$tables209 = ['wp_options' => $rows209];
$unique209 = [['blog_id', 'option_name']];

$preUpdate209 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre209', option_value || ':pre209', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preDelete209 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN (VALUES (1, 'siteurl')) AS not_siteurl ORDER BY option_id";
$failUpdate209 = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_migrated' ELSE 'siteurl' END, 'fail209', option_value || ':fail209', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_conflict ORDER BY option_id";
$retryUpdate209 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry209', option_value || ':retry209', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme_migrated'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete209 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$preUpdateResult209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preUpdate209, $tables209, 'option_id', $unique209);
$preDeleteResult209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preDelete209, $preUpdateResult209()['tables'], 'option_id', $unique209);
$failProbe209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate209, $preDeleteResult209()['tables'], 'option_id', [], true);
$failPreserve209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate209, $preDeleteResult209()['tables'], 'option_id', $unique209, true);
$retryUpdateResult209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate209, $failPreserve209()['tables'], 'option_id', $unique209);
$retryDeleteResult209 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete209, $retryUpdateResult209()['tables'], 'option_id', $unique209);
$plan209 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry(
    $tables209,
    [$preUpdate209, $preDelete209],
    $failUpdate209,
    [$retryUpdate209, $retryDelete209],
    $unique209,
);
$customPlan209 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry(
    $tables209,
    [$preUpdate209],
    $failUpdate209,
    [$retryUpdate209],
    $unique209,
    'wp_custom_fail209',
);

$cases209 = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate209)['conflict_action'], 'fail'],
    'parser fail assignments row value' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate209)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser fail where row value in' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate209)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete209)['returning'], 'dropped_network_siteurl'), true],
    'pre update selected ids' => [static fn (): mixed => $preUpdateResult209()['plan']->selectedIds, [7, 8]],
    'pre update returning ids' => [static fn (): mixed => array_column($preUpdateResult209()['returning'], 'option_id'), [7, 8]],
    'pre update row seven value' => [static fn (): mixed => array_column($preUpdateResult209()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:pre209'],
    'pre delete selected ids' => [static fn (): mixed => $preDeleteResult209()['plan']->selectedIds, [3, 9]],
    'pre delete returning ids' => [static fn (): mixed => array_column($preDeleteResult209()['returning'], 'option_id'), [3, 9]],
    'pre delete removes plugin batch' => [static fn (): mixed => in_array(9, array_column($preDeleteResult209()['tables']['wp_options'], 'option_id'), true), false],
    'fail probe would return both ids without unique constraints' => [static fn (): mixed => array_column($failProbe209()['returning'], 'option_id'), [7, 8]],
    'fail preserve returns only prior successful row' => [static fn (): mixed => array_column($failPreserve209()['returning'], 'option_id'), [7]],
    'fail preserve records conflict row eight' => [static fn (): mixed => $failPreserve209()['failed_conflict']['row_id'], 8],
    'fail preserve records unique key' => [static fn (): mixed => $failPreserve209()['failed_conflict']['key'], '1|siteurl'],
    'fail preserves row seven mutation' => [static fn (): mixed => array_column($failPreserve209()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'fail restores row eight after conflict' => [static fn (): mixed => array_column($failPreserve209()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'fail keeps pre delete removals' => [static fn (): mixed => array_intersect([3, 9], array_column($failPreserve209()['tables']['wp_options'], 'option_id')), []],
    'retry update selected ids sees fail current source' => [static fn (): mixed => $retryUpdateResult209()['plan']->selectedIds, [8, 7]],
    'retry update row seven keeps fail prefix' => [static fn (): mixed => $retryUpdateResult209()['returning'][0]['option_value'], 'theme:pre209:fail209:retry209'],
    'retry update row eight keeps pre prefix only' => [static fn (): mixed => $retryUpdateResult209()['returning'][1]['option_value'], 'rules:pre209:retry209'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult209()['plan']->selectedIds, [4, 10]],
    'retry delete network flag' => [static fn (): mixed => array_column($retryDeleteResult209()['returning'], 'dropped_network_siteurl'), [0, 1]],

    'plan status' => [static fn (): mixed => $plan209()['status'], 'rowvalue-update-delete-returning-or-fail-current-source-next209'],
    'plan savepoint' => [static fn (): mixed => $plan209()['savepoint'], 'wp_options_rowvalue_fail_next209'],
    'plan savepoint preserved' => [static fn (): mixed => $plan209()['savepoint_preserved_after_fail'], true],
    'plan pre fail changes preserved' => [static fn (): mixed => $plan209()['pre_fail_changes_preserved'], true],
    'plan failing row restored' => [static fn (): mixed => $plan209()['failing_row_restored_to_statement_start'], true],
    'plan prior fail rows preserved' => [static fn (): mixed => $plan209()['failed_statement_prior_rows_preserved'], true],
    'plan failed returning suppressed' => [static fn (): mixed => $plan209()['failed_statement_returning_suppressed'], true],
    'plan retry reads fail current source' => [static fn (): mixed => $plan209()['retry_reads_fail_current_source'], true],
    'plan released after retry' => [static fn (): mixed => $plan209()['savepoint_released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan209()['savepoint_image_tables'], $tables209],
    'plan pre fail row seven changed' => [static fn (): mixed => array_column($plan209()['pre_fail_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pre209'],
    'plan pre fail row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan209()['pre_fail_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan fail row seven migrated' => [static fn (): mixed => array_column($plan209()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_migrated'],
    'plan fail row eight restored' => [static fn (): mixed => array_column($plan209()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan209()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry209'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan209()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry209'],
    'plan final deleted ids gone' => [static fn (): mixed => array_intersect([3, 4, 9, 10], array_column($plan209()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan209()['next_source_tables'], $plan209()['current_source_tables']],
    'plan pre fail phases' => [static fn (): mixed => array_column($plan209()['pre_fail_statements'], 'phase'), ['before-fail-next209', 'before-fail-next209']],
    'plan fail phase' => [static fn (): mixed => $plan209()['fail_statement']['phase'], 'or-fail-next209'],
    'plan fail selected ids' => [static fn (): mixed => $plan209()['fail_statement']['selected_ids'], [7, 8]],
    'plan fail mutation ids' => [static fn (): mixed => $plan209()['fail_statement']['mutation_ids'], [7, 8]],
    'plan fail marked failed' => [static fn (): mixed => $plan209()['fail_statement']['failed'], true],
    'plan fail conflict row' => [static fn (): mixed => $plan209()['fail_statement']['failed_conflict']['row_id'], 8],
    'plan fail conflict key' => [static fn (): mixed => $plan209()['fail_statement']['failed_conflict']['key'], '1|siteurl'],
    'plan fail returning ids' => [static fn (): mixed => array_column($plan209()['fail_statement']['returning_rows'], 'option_id'), [7]],
    'plan fail probe returning ids' => [static fn (): mixed => array_column($plan209()['fail_statement']['probe_returning_rows'], 'option_id'), [7, 8]],
    'plan suppressed ids' => [static fn (): mixed => array_column($plan209()['suppressed_by_fail_returning'], 'option_id'), [8]],
    'plan retry phases' => [static fn (): mixed => array_column($plan209()['retry_statements'], 'phase'), ['retry-after-fail-next209', 'retry-after-fail-next209']],
    'plan retry update source ids' => [static fn (): mixed => array_column($plan209()['retry_statements'][0]['source_rows'], 'option_id'), [7, 8]],
    'plan retry delete source ids' => [static fn (): mixed => array_column($plan209()['retry_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan pre fail yielded count' => [static fn (): mixed => $plan209()['pre_fail_yielded_count'], 4],
    'plan fail preserved yielded count' => [static fn (): mixed => $plan209()['fail_preserved_yielded_count'], 1],
    'plan suppressed count' => [static fn (): mixed => $plan209()['suppressed_by_fail_count'], 1],
    'plan retry yielded count' => [static fn (): mixed => $plan209()['yielded_after_retry_count'], 4],
    'plan pre fail changes count' => [static fn (): mixed => $plan209()['pre_fail_changes_preserved_count'], 4],
    'plan fail changes count' => [static fn (): mixed => $plan209()['fail_changes_preserved_count'], 1],
    'plan retry changes count' => [static fn (): mixed => $plan209()['retry_changes_after_fail'], 4],
    'plan changed tables' => [static fn (): mixed => $plan209()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan209()['row_counts']['wp_options'], 6],
    'plan dependency fail preserves prior' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-preserves-prior-returning-next209', $plan209()['dependencies'], true), true],
    'plan dependency fail suppresses conflict' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-suppresses-conflicting-returning-next209', $plan209()['dependencies'], true), true],
    'plan dependency retry delete' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-returning-retry-after-fail-next209', $plan209()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan209()['savepoint'], 'wp_custom_fail209'],
    'custom pre fail count' => [static fn (): mixed => $customPlan209()['pre_fail_yielded_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan209()['yielded_after_retry_count'], 2],
    'malformed empty pre fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [], $failUpdate209, [$retryUpdate209], $unique209), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [$preUpdate209], '', [$retryUpdate209], $unique209), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [$preUpdate209], $failUpdate209, [], $unique209), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [$preUpdate209], $failUpdate209, [$retryUpdate209], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [$preUpdate209], $failUpdate209, [$retryUpdate209], $unique209, 'bad-name'), InvalidArgumentException::class],
    'malformed fail action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry($tables209, [$preUpdate209], str_replace('OR FAIL', 'OR ABORT', $failUpdate209), [$retryUpdate209], $unique209), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStatementRetry(['wp_options' => ['bad']], [$preUpdate209], $failUpdate209, [$retryUpdate209], $unique209), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases209 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next209 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
