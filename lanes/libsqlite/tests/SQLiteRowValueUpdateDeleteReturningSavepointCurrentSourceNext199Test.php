<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows199 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables199 = ['wp_options' => $rows199];
$attemptUpdate199 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt199', option_value || ':attempt199', bytes + 1) WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (3, 'rewrite_rules') AS rewrite_match ORDER BY (blog_id, option_name) IS (3, 'rewrite_rules') DESC, bytes DESC LIMIT 2";
$attemptDelete199 = "DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, (status, option_name) IS ('attempt199', 'rewrite_rules') AS deleting_rewrite ORDER BY (status, option_name) IS ('attempt199', 'rewrite_rules') DESC, bytes ASC LIMIT 1";
$retryUpdate199 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry199', option_value || ':retry199', bytes + 5) WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (3, 'rewrite_rules') AS rewrite_match ORDER BY (blog_id, option_name) IS (3, 'rewrite_rules') DESC, bytes DESC LIMIT 5";
$retryDelete199 = "DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, (status, option_name) IS ('retry199', 'plugin_batch') AS deleting_plugin ORDER BY (status, option_name) IS ('retry199', 'plugin_batch') DESC, option_id ASC LIMIT 2";

$attemptUpdateResult199 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate199, $tables199);
$attemptDeleteResult199 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete199, $attemptUpdateResult199()['tables']);
$retryUpdateResult199 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate199, $tables199);
$retryDeleteResult199 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete199, $retryUpdateResult199()['tables']);
$starUpdate199 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'star199' WHERE autoload = 'no' RETURNING * ORDER BY (blog_id, option_name) IS (3, 'rewrite_rules') DESC LIMIT 1", $tables199);
$plan199 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry(
    $tables199,
    [$attemptUpdate199, $attemptDelete199],
    [$retryUpdate199, $retryDelete199],
);
$customPlan199 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry(
    $tables199,
    [$attemptUpdate199],
    [$retryUpdate199],
    'wp_custom_order_expr_next199',
);

$cases199 = [
    'parser update order expression retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate199)['order_by'][0]['expression'], "(blog_id, option_name) IS (3, 'rewrite_rules')"],
    'parser update order expression desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate199)['order_by'][0]['direction'], 'DESC'],
    'parser update second order column' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate199)['order_by'][1], ['column' => 'bytes', 'direction' => 'DESC']],
    'parser delete order expression retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete199)['order_by'][0]['expression'], "(status, option_name) IS ('attempt199', 'rewrite_rules')"],
    'parser retry delete mixed order' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete199)['order_by'][1], ['column' => 'option_id', 'direction' => 'ASC']],

    'attempt update order selects rewrite first then largest bytes' => [static fn (): mixed => $attemptUpdateResult199()['plan']->selectedIds, [8, 4]],
    'attempt update mutation remains source order' => [static fn (): mixed => $attemptUpdateResult199()['plan']->mutationIds, [4, 8]],
    'attempt update returning remains source order' => [static fn (): mixed => array_column($attemptUpdateResult199()['returning'], 'option_id'), [4, 8]],
    'attempt update rewrite flag source order' => [static fn (): mixed => array_column($attemptUpdateResult199()['returning'], 'rewrite_match'), [0, 1]],
    'attempt update order summary hidden expression' => [static fn (): mixed => $attemptUpdateResult199()['plan']->toArray()['order_by'][0]['expression'], "(blog_id, option_name) IS (3, 'rewrite_rules')"],
    'attempt update order hidden column is internal' => [static fn (): mixed => str_starts_with($attemptUpdateResult199()['plan']->toArray()['order_by'][0]['column'], '__sqlite_udl_order_'), true],
    'attempt update row four changed' => [static fn (): mixed => array_column($attemptUpdateResult199()['tables']['wp_options'], 'status', 'option_id')[4], 'attempt199'],
    'attempt update row eight changed' => [static fn (): mixed => array_column($attemptUpdateResult199()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:attempt199'],
    'attempt update row nine untouched' => [static fn (): mixed => array_column($attemptUpdateResult199()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'attempt delete expression selects rewritten rewrite row' => [static fn (): mixed => $attemptDeleteResult199()['plan']->selectedIds, [8]],
    'attempt delete returning expression true' => [static fn (): mixed => $attemptDeleteResult199()['returning'][0]['deleting_rewrite'], 1],
    'attempt delete removes rewrite row' => [static fn (): mixed => in_array(8, array_column($attemptDeleteResult199()['tables']['wp_options'], 'option_id'), true), false],
    'attempt delete leaves timeout row' => [static fn (): mixed => in_array(4, array_column($attemptDeleteResult199()['tables']['wp_options'], 'option_id'), true), true],

    'retry update selected ids' => [static fn (): mixed => $retryUpdateResult199()['plan']->selectedIds, [8, 4, 3, 9, 7]],
    'retry update mutation source order' => [static fn (): mixed => $retryUpdateResult199()['plan']->mutationIds, [3, 4, 7, 8, 9]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult199()['returning'], 'option_id'), [3, 4, 7, 8, 9]],
    'retry update starts from original row three' => [static fn (): mixed => $retryUpdateResult199()['returning'][0]['option_value'], 'feed:retry199'],
    'retry update starts from original row eight' => [static fn (): mixed => $retryUpdateResult199()['returning'][3]['option_value'], 'rules:retry199'],
    'retry delete selected plugin first then row three' => [static fn (): mixed => $retryDeleteResult199()['plan']->selectedIds, [9, 3]],
    'retry delete mutation source order' => [static fn (): mixed => $retryDeleteResult199()['plan']->mutationIds, [3, 9]],
    'retry delete plugin flag source order' => [static fn (): mixed => array_column($retryDeleteResult199()['returning'], 'deleting_plugin'), [0, 1]],
    'retry delete removes row three' => [static fn (): mixed => in_array(3, array_column($retryDeleteResult199()['tables']['wp_options'], 'option_id'), true), false],
    'retry delete removes row nine' => [static fn (): mixed => in_array(9, array_column($retryDeleteResult199()['tables']['wp_options'], 'option_id'), true), false],
    'wildcard returning strips hidden order column' => [static fn (): mixed => array_filter(array_keys($starUpdate199()['returning'][0]), static fn (string $column): bool => str_starts_with($column, '__sqlite_udl_')), []],
    'internal user column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id ORDER BY option_id", ['wp_options' => [['option_id' => 1, '__sqlite_udl_order_0' => 1]]]), InvalidArgumentException::class],
    'malformed unknown order expression rejected at execution' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options RETURNING option_id ORDER BY missing + 1", $tables199), InvalidArgumentException::class],

    'plan status' => [static fn (): mixed => $plan199()['status'], 'rowvalue-order-expression-returning-rolled-back-retried-next199'],
    'plan savepoint' => [static fn (): mixed => $plan199()['savepoint'], 'wp_options_rowvalue_order_expr_next199'],
    'plan rolled back' => [static fn (): mixed => $plan199()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved' => [static fn (): mixed => $plan199()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan199()['released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan199()['savepoint_image_tables'], $tables199],
    'plan attempt row eight deleted' => [static fn (): mixed => in_array(8, array_column($plan199()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan199()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan199()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry199'],
    'plan current row three deleted' => [static fn (): mixed => in_array(3, array_column($plan199()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan199()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan199()['next_source_tables'], $plan199()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan199()['attempt_statements'], 'phase'), ['attempt-order-expression-before-rollback-next199', 'attempt-order-expression-before-rollback-next199']],
    'plan retry phases' => [static fn (): mixed => array_column($plan199()['retry_statements'], 'phase'), ['retry-order-expression-after-rollback-next199', 'retry-order-expression-after-rollback-next199']],
    'plan attempt update selected ids' => [static fn (): mixed => $plan199()['attempt_statements'][0]['selected_ids'], [8, 4]],
    'plan attempt delete selected ids' => [static fn (): mixed => $plan199()['attempt_statements'][1]['selected_ids'], [8]],
    'plan retry update selected ids' => [static fn (): mixed => $plan199()['retry_statements'][0]['selected_ids'], [8, 4, 3, 9, 7]],
    'plan retry delete selected ids' => [static fn (): mixed => $plan199()['retry_statements'][1]['selected_ids'], [9, 3]],
    'plan attempt order expression summary' => [static fn (): mixed => $plan199()['attempt_statements'][0]['order_by'][0]['expression'], "(blog_id, option_name) IS (3, 'rewrite_rules')"],
    'plan retry delete order expression summary' => [static fn (): mixed => $plan199()['retry_statements'][1]['order_by'][0]['expression'], "(status, option_name) IS ('retry199', 'plugin_batch')"],
    'plan attempt returning count' => [static fn (): mixed => $plan199()['attempt_returning_count'], 3],
    'plan suppressed count' => [static fn (): mixed => $plan199()['suppressed_by_rollback_count'], 3],
    'plan retry returning count' => [static fn (): mixed => $plan199()['yielded_after_retry_count'], 7],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan199()['attempt_changes_before_rollback_to'], 3],
    'plan changes after retry' => [static fn (): mixed => $plan199()['changes_after_retry_release'], 7],
    'plan changed tables' => [static fn (): mixed => $plan199()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan199()['row_counts']['wp_options'], 8],
    'plan dependency order expression' => [static fn (): mixed => in_array('sqlite-update-delete-order-by-rowvalue-expression-next199', $plan199()['dependencies'], true), true],
    'plan dependency limit before mutation' => [static fn (): mixed => in_array('sqlite-rowvalue-order-expression-limit-before-source-mutation-next199', $plan199()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan199()['savepoint'], 'wp_custom_order_expr_next199'],
    'custom plan retry count' => [static fn (): mixed => $customPlan199()['yielded_after_retry_count'], 5],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry($tables199, [], [$retryUpdate199]), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry($tables199, [$attemptUpdate199], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry($tables199, [$attemptUpdate199], [$retryUpdate199], 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry(['wp_options' => ['bad']], [$attemptUpdate199], [$retryUpdate199]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases199 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next199 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
