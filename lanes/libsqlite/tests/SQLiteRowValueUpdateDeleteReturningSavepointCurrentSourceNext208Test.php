<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows208 = [
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

$tables208 = ['wp_options' => $rows208];
$unique208 = [['blog_id', 'option_name']];
$outerUpdate208 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer208', option_value || ':outer208', bytes + 1) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preFailDelete208 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$preFailUpdate208 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre208', option_value || ':pre208', bytes + 2) WHERE (blog_id, option_name) IN (VALUES (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$failUpdate208 = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (5, 'shared_fail', 'fail208', option_value || ':fail208', bytes + 20) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (5, 'shared_fail') AS shared_key ORDER BY option_id";
$retryDelete208 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((5, 'shared_fail')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdate208 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry208', option_value || ':retry208', bytes + 5) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";

$outerResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate208, $tables208, 'option_id', $unique208);
$preFailDeleteResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preFailDelete208, $outerResult208()['tables'], 'option_id', $unique208);
$preFailUpdateResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preFailUpdate208, $preFailDeleteResult208()['tables'], 'option_id', $unique208);
$failResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate208, $preFailUpdateResult208()['tables'], 'option_id', $unique208, true);
$retryDeleteResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete208, $failResult208()['tables'], 'option_id', $unique208);
$retryUpdateResult208 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate208, $retryDeleteResult208()['tables'], 'option_id', $unique208);
$plan208 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208(
    $tables208,
    [$outerUpdate208],
    [$preFailDelete208, $preFailUpdate208],
    $failUpdate208,
    [$retryDelete208, $retryUpdate208],
    $unique208,
);
$customPlan208 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208(
    $tables208,
    [$outerUpdate208],
    [$preFailUpdate208],
    $failUpdate208,
    [$retryDelete208],
    $unique208,
    'wp_custom_fail208',
);

$cases208 = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate208)['conflict_action'], 'fail'],
    'parser fail row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate208)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser fail where ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate208)['where'], 'option_id IN (7, 8)'],
    'parser pre fail values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($preFailUpdate208)['where'] ?? '', 'VALUES'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate208)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'outer selected ids' => [static fn (): mixed => $outerResult208()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult208()['returning'], 'option_id'), [1, 2]],
    'outer row one changed' => [static fn (): mixed => array_column($outerResult208()['tables']['wp_options'], 'status', 'option_id')[1], 'outer208'],
    'pre fail delete selected id' => [static fn (): mixed => $preFailDeleteResult208()['plan']->selectedIds, [3]],
    'pre fail delete removes transient' => [static fn (): mixed => in_array(3, array_column($preFailDeleteResult208()['tables']['wp_options'], 'option_id'), true), false],
    'pre fail update selected id' => [static fn (): mixed => $preFailUpdateResult208()['plan']->selectedIds, [9]],
    'pre fail update status' => [static fn (): mixed => array_column($preFailUpdateResult208()['tables']['wp_options'], 'status', 'option_id')[9], 'pre208'],
    'direct fail selected ids' => [static fn (): mixed => $failResult208()['plan']->selectedIds, [7, 8]],
    'direct fail returning preserved first row only' => [static fn (): mixed => array_column($failResult208()['returning'], 'option_id'), [7]],
    'direct fail returning shared key flag' => [static fn (): mixed => array_column($failResult208()['returning'], 'shared_key'), [1]],
    'direct fail row seven partial current' => [static fn (): mixed => array_column($failResult208()['tables']['wp_options'], 'option_name', 'option_id')[7], 'shared_fail'],
    'direct fail row eight restored after conflict' => [static fn (): mixed => array_column($failResult208()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'direct fail conflict row id' => [static fn (): mixed => $failResult208()['failed_conflict']['row_id'] ?? null, 8],
    'direct fail conflicting row id' => [static fn (): mixed => $failResult208()['failed_conflict']['conflicting_row_ids'] ?? [], [7]],
    'direct fail without preservation throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($failUpdate208, $preFailUpdateResult208()['tables'], 'option_id', $unique208), InvalidArgumentException::class],
    'retry delete reads partial fail row' => [static fn (): mixed => $retryDeleteResult208()['plan']->selectedIds, [7]],
    'retry delete removes partial fail row' => [static fn (): mixed => in_array(7, array_column($retryDeleteResult208()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected after partial delete' => [static fn (): mixed => $retryUpdateResult208()['plan']->selectedIds, [9, 8]],
    'retry update returning final order' => [static fn (): mixed => array_column($retryUpdateResult208()['returning'], 'option_id'), [8, 9]],
    'retry update row nine includes pre then retry' => [static fn (): mixed => array_column($retryUpdateResult208()['tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:pre208:retry208'],

    'plan status' => [static fn (): mixed => $plan208()['status'], 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next208'],
    'plan savepoint' => [static fn (): mixed => $plan208()['savepoint'], 'wp_options_rowvalue_fail_statement_next208'],
    'plan or fail preserved prior rows' => [static fn (): mixed => $plan208()['or_fail_statement_preserved_prior_rows'], true],
    'plan or fail stopped at conflict' => [static fn (): mixed => $plan208()['or_fail_statement_stopped_at_conflict'], true],
    'plan returning visible before rollback' => [static fn (): mixed => $plan208()['or_fail_statement_stopped_at_conflict'], true],
    'plan retry reads partial current source' => [static fn (): mixed => $plan208()['retry_reads_partial_fail_current_source'], true],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan208()['rolled_back_to_savepoint_after_retry'], true],
    'plan released after rollback' => [static fn (): mixed => $plan208()['savepoint_released_after_rollback'], true],
    'plan initial tables' => [static fn (): mixed => $plan208()['initial_tables'], $tables208],
    'plan outer current row two changed' => [static fn (): mixed => array_column($plan208()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[2], 'outer208'],
    'plan savepoint image equals outer current' => [static fn (): mixed => $plan208()['savepoint_image_tables'], $plan208()['outer_current_source_tables']],
    'plan pre fail row three deleted' => [static fn (): mixed => in_array(3, array_column($plan208()['pre_fail_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan pre fail row nine changed' => [static fn (): mixed => array_column($plan208()['pre_fail_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'pre208'],
    'plan fail current row seven shared' => [static fn (): mixed => array_column($plan208()['fail_statement_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'shared_fail'],
    'plan fail current row eight original' => [static fn (): mixed => array_column($plan208()['fail_statement_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan retry delete source row' => [static fn (): mixed => array_column($plan208()['retry_statements'][0]['source_rows'], 'option_id'), [7]],
    'plan retry update source rows' => [static fn (): mixed => array_column($plan208()['retry_statements'][1]['source_rows'], 'option_id'), [8, 9]],
    'plan retry before rollback removed row seven' => [static fn (): mixed => in_array(7, array_column($plan208()['retry_current_source_before_rollback_tables']['wp_options'], 'option_id'), true), false],
    'plan retry before rollback row eight retry' => [static fn (): mixed => array_column($plan208()['retry_current_source_before_rollback_tables']['wp_options'], 'status', 'option_id')[8], 'retry208'],
    'plan rollback source equals savepoint image' => [static fn (): mixed => $plan208()['rollback_to_savepoint_current_source_tables'], $plan208()['savepoint_image_tables']],
    'plan current row one keeps outer after rollback to savepoint' => [static fn (): mixed => array_column($plan208()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer208'],
    'plan current row three restored by rollback' => [static fn (): mixed => in_array(3, array_column($plan208()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan current row seven restored by rollback' => [static fn (): mixed => array_column($plan208()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan current row nine restored before pre fail' => [static fn (): mixed => array_column($plan208()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'plan next source equals current' => [static fn (): mixed => $plan208()['next_source_tables'], $plan208()['current_source_tables']],
    'plan outer phase' => [static fn (): mixed => $plan208()['outer_statements'][0]['phase'], 'outer-before-fail-savepoint-next208'],
    'plan pre fail phases' => [static fn (): mixed => array_column($plan208()['pre_fail_statements'], 'phase'), ['savepoint-before-or-fail-next208', 'savepoint-before-or-fail-next208']],
    'plan fail phase' => [static fn (): mixed => $plan208()['fail_statement']['phase'], 'or-fail-partial-current-source-next208'],
    'plan fail selected ids' => [static fn (): mixed => $plan208()['fail_statement']['selected_ids'], [7, 8]],
    'plan fail returning rows' => [static fn (): mixed => array_column($plan208()['fail_statement']['returning_rows'], 'option_id'), [7]],
    'plan fail conflict columns' => [static fn (): mixed => $plan208()['failed_conflict']['columns'] ?? [], ['blog_id', 'option_name']],
    'plan fail conflict key' => [static fn (): mixed => $plan208()['failed_conflict']['key'] ?? null, '5|shared_fail'],
    'plan or fail returning count' => [static fn (): mixed => $plan208()['or_fail_returning_count'], 1],
    'plan pre fail yielded count' => [static fn (): mixed => $plan208()['pre_fail_yielded_count'], 2],
    'plan retry yielded count' => [static fn (): mixed => $plan208()['retry_yielded_count_before_rollback'], 3],
    'plan changes preserved by fail' => [static fn (): mixed => $plan208()['changes_preserved_by_or_fail'], 1],
    'plan changes after retry before rollback' => [static fn (): mixed => $plan208()['changes_after_retry_before_rollback'], 3],
    'plan discarded changes' => [static fn (): mixed => $plan208()['changes_discarded_by_rollback_to_savepoint'], 4],
    'plan changed tables after rollback' => [static fn (): mixed => $plan208()['changed_tables_after_rollback'], ['wp_options']],
    'plan row count after rollback' => [static fn (): mixed => $plan208()['row_counts']['wp_options'], 10],
    'plan dependency fail' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-returning-preserves-prior-rows-next208', $plan208()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-reads-partial-or-fail-current-source-next208', $plan208()['dependencies'], true), true],
    'plan dependency rollback' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-or-fail-returning-current-source-next208', $plan208()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan208()['savepoint'], 'wp_custom_fail208'],
    'custom retry count' => [static fn (): mixed => $customPlan208()['retry_yielded_count_before_rollback'], 1],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [], [$preFailDelete208], $failUpdate208, [$retryDelete208], $unique208), InvalidArgumentException::class],
    'malformed empty pre fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [$outerUpdate208], [], $failUpdate208, [$retryDelete208], $unique208), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [$outerUpdate208], [$preFailDelete208], '', [$retryDelete208], $unique208), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [$outerUpdate208], [$preFailDelete208], $failUpdate208, [], $unique208), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [$outerUpdate208], [$preFailDelete208], $failUpdate208, [$retryDelete208], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208($tables208, [$outerUpdate208], [$preFailDelete208], $failUpdate208, [$retryDelete208], $unique208, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext208(['wp_options' => ['bad']], [$outerUpdate208], [$preFailDelete208], $failUpdate208, [$retryDelete208], $unique208), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases208 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next208 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
