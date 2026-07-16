<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rowspreFailRetry = [
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

$tablespreFailRetry = ['wp_options' => $rowspreFailRetry];
$uniquepreFailRetry = [['blog_id', 'option_name']];
$outerUpdatepreFailRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('outerpreFailRetry', option_value || ':outerpreFailRetry', bytes + 1) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$preFailDeletepreFailRetry = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$preFailUpdatepreFailRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('prepreFailRetry', option_value || ':prepreFailRetry', bytes + 2) WHERE (blog_id, option_name) IN (VALUES (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$failUpdatepreFailRetry = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (5, 'shared_fail', 'failpreFailRetry', option_value || ':failpreFailRetry', bytes + 20) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (5, 'shared_fail') AS shared_key ORDER BY option_id";
$retryDeletepreFailRetry = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((5, 'shared_fail')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdatepreFailRetry = "UPDATE wp_options SET (status, option_value, bytes) = ('retrypreFailRetry', option_value || ':retrypreFailRetry', bytes + 5) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";

$outerResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdatepreFailRetry, $tablespreFailRetry, 'option_id', $uniquepreFailRetry);
$preFailDeleteResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preFailDeletepreFailRetry, $outerResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry);
$preFailUpdateResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preFailUpdatepreFailRetry, $preFailDeleteResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry);
$failResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdatepreFailRetry, $preFailUpdateResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry, true);
$retryDeleteResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeletepreFailRetry, $failResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry);
$retryUpdateResultpreFailRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdatepreFailRetry, $retryDeleteResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry);
$planpreFailRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry(
    $tablespreFailRetry,
    [$outerUpdatepreFailRetry],
    [$preFailDeletepreFailRetry, $preFailUpdatepreFailRetry],
    $failUpdatepreFailRetry,
    [$retryDeletepreFailRetry, $retryUpdatepreFailRetry],
    $uniquepreFailRetry,
);
$customPlanpreFailRetry = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry(
    $tablespreFailRetry,
    [$outerUpdatepreFailRetry],
    [$preFailUpdatepreFailRetry],
    $failUpdatepreFailRetry,
    [$retryDeletepreFailRetry],
    $uniquepreFailRetry,
    'wp_custom_failpreFailRetry',
);

$casespreFailRetry = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdatepreFailRetry)['conflict_action'], 'fail'],
    'parser fail row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdatepreFailRetry)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser fail where ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdatepreFailRetry)['where'], 'option_id IN (7, 8)'],
    'parser pre fail values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($preFailUpdatepreFailRetry)['where'] ?? '', 'VALUES'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdatepreFailRetry)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'outer selected ids' => [static fn (): mixed => $outerResultpreFailRetry()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResultpreFailRetry()['returning'], 'option_id'), [1, 2]],
    'outer row one changed' => [static fn (): mixed => array_column($outerResultpreFailRetry()['tables']['wp_options'], 'status', 'option_id')[1], 'outerpreFailRetry'],
    'pre fail delete selected id' => [static fn (): mixed => $preFailDeleteResultpreFailRetry()['plan']->selectedIds, [3]],
    'pre fail delete removes transient' => [static fn (): mixed => in_array(3, array_column($preFailDeleteResultpreFailRetry()['tables']['wp_options'], 'option_id'), true), false],
    'pre fail update selected id' => [static fn (): mixed => $preFailUpdateResultpreFailRetry()['plan']->selectedIds, [9]],
    'pre fail update status' => [static fn (): mixed => array_column($preFailUpdateResultpreFailRetry()['tables']['wp_options'], 'status', 'option_id')[9], 'prepreFailRetry'],
    'direct fail selected ids' => [static fn (): mixed => $failResultpreFailRetry()['plan']->selectedIds, [7, 8]],
    'direct fail returning preserved first row only' => [static fn (): mixed => array_column($failResultpreFailRetry()['returning'], 'option_id'), [7]],
    'direct fail returning shared key flag' => [static fn (): mixed => array_column($failResultpreFailRetry()['returning'], 'shared_key'), [1]],
    'direct fail row seven partial current' => [static fn (): mixed => array_column($failResultpreFailRetry()['tables']['wp_options'], 'option_name', 'option_id')[7], 'shared_fail'],
    'direct fail row eight restored after conflict' => [static fn (): mixed => array_column($failResultpreFailRetry()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'direct fail conflict row id' => [static fn (): mixed => $failResultpreFailRetry()['failed_conflict']['row_id'] ?? null, 8],
    'direct fail conflicting row id' => [static fn (): mixed => $failResultpreFailRetry()['failed_conflict']['conflicting_row_ids'] ?? [], [7]],
    'direct fail without preservation throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($failUpdatepreFailRetry, $preFailUpdateResultpreFailRetry()['tables'], 'option_id', $uniquepreFailRetry), InvalidArgumentException::class],
    'retry delete reads partial fail row' => [static fn (): mixed => $retryDeleteResultpreFailRetry()['plan']->selectedIds, [7]],
    'retry delete removes partial fail row' => [static fn (): mixed => in_array(7, array_column($retryDeleteResultpreFailRetry()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected after partial delete' => [static fn (): mixed => $retryUpdateResultpreFailRetry()['plan']->selectedIds, [9, 8]],
    'retry update returning final order' => [static fn (): mixed => array_column($retryUpdateResultpreFailRetry()['returning'], 'option_id'), [8, 9]],
    'retry update row nine includes pre then retry' => [static fn (): mixed => array_column($retryUpdateResultpreFailRetry()['tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:prepreFailRetry:retrypreFailRetry'],

    'plan status' => [static fn (): mixed => $planpreFailRetry()['status'], 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-pre-fail-rollback-retry'],
    'plan savepoint' => [static fn (): mixed => $planpreFailRetry()['savepoint'], 'app_settings_rowvalue_fail_statement_pre_fail_rollback_retry'],
    'plan or fail preserved prior rows' => [static fn (): mixed => $planpreFailRetry()['or_fail_statement_preserved_prior_rows'], true],
    'plan or fail stopped at conflict' => [static fn (): mixed => $planpreFailRetry()['or_fail_statement_stopped_at_conflict'], true],
    'plan returning visible before rollback' => [static fn (): mixed => $planpreFailRetry()['or_fail_statement_stopped_at_conflict'], true],
    'plan retry reads partial current source' => [static fn (): mixed => $planpreFailRetry()['retry_reads_partial_fail_current_source'], true],
    'plan rolled back to savepoint' => [static fn (): mixed => $planpreFailRetry()['rolled_back_to_savepoint_after_retry'], true],
    'plan released after rollback' => [static fn (): mixed => $planpreFailRetry()['savepoint_released_after_rollback'], true],
    'plan initial tables' => [static fn (): mixed => $planpreFailRetry()['initial_tables'], $tablespreFailRetry],
    'plan outer current row two changed' => [static fn (): mixed => array_column($planpreFailRetry()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[2], 'outerpreFailRetry'],
    'plan savepoint image equals outer current' => [static fn (): mixed => $planpreFailRetry()['savepoint_image_tables'], $planpreFailRetry()['outer_current_source_tables']],
    'plan pre fail row three deleted' => [static fn (): mixed => in_array(3, array_column($planpreFailRetry()['pre_fail_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan pre fail row nine changed' => [static fn (): mixed => array_column($planpreFailRetry()['pre_fail_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'prepreFailRetry'],
    'plan fail current row seven shared' => [static fn (): mixed => array_column($planpreFailRetry()['fail_statement_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'shared_fail'],
    'plan fail current row eight original' => [static fn (): mixed => array_column($planpreFailRetry()['fail_statement_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan retry delete source row' => [static fn (): mixed => array_column($planpreFailRetry()['retry_statements'][0]['source_rows'], 'option_id'), [7]],
    'plan retry update source rows' => [static fn (): mixed => array_column($planpreFailRetry()['retry_statements'][1]['source_rows'], 'option_id'), [8, 9]],
    'plan retry before rollback removed row seven' => [static fn (): mixed => in_array(7, array_column($planpreFailRetry()['retry_current_source_before_rollback_tables']['wp_options'], 'option_id'), true), false],
    'plan retry before rollback row eight retry' => [static fn (): mixed => array_column($planpreFailRetry()['retry_current_source_before_rollback_tables']['wp_options'], 'status', 'option_id')[8], 'retrypreFailRetry'],
    'plan rollback source equals savepoint image' => [static fn (): mixed => $planpreFailRetry()['rollback_to_savepoint_current_source_tables'], $planpreFailRetry()['savepoint_image_tables']],
    'plan current row one keeps outer after rollback to savepoint' => [static fn (): mixed => array_column($planpreFailRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outerpreFailRetry'],
    'plan current row three restored by rollback' => [static fn (): mixed => in_array(3, array_column($planpreFailRetry()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan current row seven restored by rollback' => [static fn (): mixed => array_column($planpreFailRetry()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan current row nine restored before pre fail' => [static fn (): mixed => array_column($planpreFailRetry()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'plan next source equals current' => [static fn (): mixed => $planpreFailRetry()['next_source_tables'], $planpreFailRetry()['current_source_tables']],
    'plan outer phase' => [static fn (): mixed => $planpreFailRetry()['outer_statements'][0]['phase'], 'outer-before-fail-savepoint-pre-fail-rollback-retry'],
    'plan pre fail phases' => [static fn (): mixed => array_column($planpreFailRetry()['pre_fail_statements'], 'phase'), ['savepoint-before-or-fail-pre-fail-rollback-retry', 'savepoint-before-or-fail-pre-fail-rollback-retry']],
    'plan fail phase' => [static fn (): mixed => $planpreFailRetry()['fail_statement']['phase'], 'or-fail-partial-current-source-pre-fail-rollback-retry'],
    'plan fail selected ids' => [static fn (): mixed => $planpreFailRetry()['fail_statement']['selected_ids'], [7, 8]],
    'plan fail returning rows' => [static fn (): mixed => array_column($planpreFailRetry()['fail_statement']['returning_rows'], 'option_id'), [7]],
    'plan fail conflict columns' => [static fn (): mixed => $planpreFailRetry()['failed_conflict']['columns'] ?? [], ['blog_id', 'option_name']],
    'plan fail conflict key' => [static fn (): mixed => $planpreFailRetry()['failed_conflict']['key'] ?? null, '5|shared_fail'],
    'plan or fail returning count' => [static fn (): mixed => $planpreFailRetry()['or_fail_returning_count'], 1],
    'plan pre fail yielded count' => [static fn (): mixed => $planpreFailRetry()['pre_fail_yielded_count'], 2],
    'plan retry yielded count' => [static fn (): mixed => $planpreFailRetry()['retry_yielded_count_before_rollback'], 3],
    'plan changes preserved by fail' => [static fn (): mixed => $planpreFailRetry()['changes_preserved_by_or_fail'], 1],
    'plan changes after retry before rollback' => [static fn (): mixed => $planpreFailRetry()['changes_after_retry_before_rollback'], 3],
    'plan discarded changes' => [static fn (): mixed => $planpreFailRetry()['changes_discarded_by_rollback_to_savepoint'], 4],
    'plan changed tables after rollback' => [static fn (): mixed => $planpreFailRetry()['changed_tables_after_rollback'], ['wp_options']],
    'plan row count after rollback' => [static fn (): mixed => $planpreFailRetry()['row_counts']['wp_options'], 10],
    'plan dependency fail' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-returning-preserves-prior-rows-pre-fail-rollback-retry', $planpreFailRetry()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-reads-partial-or-fail-current-source-pre-fail-rollback-retry', $planpreFailRetry()['dependencies'], true), true],
    'plan dependency rollback' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-or-fail-returning-current-source-pre-fail-rollback-retry', $planpreFailRetry()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlanpreFailRetry()['savepoint'], 'wp_custom_failpreFailRetry'],
    'custom retry count' => [static fn (): mixed => $customPlanpreFailRetry()['retry_yielded_count_before_rollback'], 1],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [], [$preFailDeletepreFailRetry], $failUpdatepreFailRetry, [$retryDeletepreFailRetry], $uniquepreFailRetry), InvalidArgumentException::class],
    'malformed empty pre fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [$outerUpdatepreFailRetry], [], $failUpdatepreFailRetry, [$retryDeletepreFailRetry], $uniquepreFailRetry), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [$outerUpdatepreFailRetry], [$preFailDeletepreFailRetry], '', [$retryDeletepreFailRetry], $uniquepreFailRetry), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [$outerUpdatepreFailRetry], [$preFailDeletepreFailRetry], $failUpdatepreFailRetry, [], $uniquepreFailRetry), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [$outerUpdatepreFailRetry], [$preFailDeletepreFailRetry], $failUpdatepreFailRetry, [$retryDeletepreFailRetry], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry($tablespreFailRetry, [$outerUpdatepreFailRetry], [$preFailDeletepreFailRetry], $failUpdatepreFailRetry, [$retryDeletepreFailRetry], $uniquepreFailRetry, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreFailRollbackRetry(['wp_options' => ['bad']], [$outerUpdatepreFailRetry], [$preFailDeletepreFailRetry], $failUpdatepreFailRetry, [$retryDeletepreFailRetry], $uniquepreFailRetry), InvalidArgumentException::class],
];

$tests = [];
foreach ($casespreFailRetry as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source pre-fail-rollback-retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
