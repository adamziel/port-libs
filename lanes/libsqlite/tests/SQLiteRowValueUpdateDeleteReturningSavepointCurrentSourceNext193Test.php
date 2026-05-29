<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows193 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 1, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'installed', 'bytes' => 31, 'option_value' => 'plugin-existing'],
];

$tables193 = ['wp_options' => $rows193];
$unique193 = [['blog_id', 'option_name']];
$outerSql193 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer193', option_value || ':outer193', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$failSql193 = "UPDATE OR FAIL wp_options SET (blog_id, status, option_value, bytes) = (1, 'fail193', option_value || ':fail193', bytes + 10) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (3, option_name) AS moved_key ORDER BY option_id";
$retryUpdateSql193 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry193', option_value || ':retry193', bytes + 5) WHERE (blog_id, option_name) IS (3, 'rewrite_rules') OR (blog_id, option_name) IS (3, 'plugin_batch') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDeleteSql193 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (1, 'plugin_batch')) AS plugin_key ORDER BY option_id";

$outerResult193 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql193, $tables193, 'option_id', $unique193);
$failResult193 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql193, $outerResult193()['tables'], 'option_id', $unique193, true);
$retryUpdateResult193 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql193, $outerResult193()['tables'], 'option_id', $unique193);
$retryDeleteResult193 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteSql193, $retryUpdateResult193()['tables'], 'option_id', $unique193);
$plan193 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint(
    $tables193,
    [$outerSql193],
    [$failSql193],
    [$retryUpdateSql193, $retryDeleteSql193],
    $unique193,
);
$customPlan193 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint(
    $tables193,
    [$outerSql193],
    [$failSql193],
    [$retryUpdateSql193],
    $unique193,
    'wp_custom_next193',
);

$cases193 = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failSql193)['conflict_action'], 'fail'],
    'parser fail returning expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($failSql193)['returning'], 'IS DISTINCT FROM'), true],
    'parser retry update rowvalue is predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdateSql193)['where'] ?? '', "IS (3, 'plugin_batch')"), true],
    'parser retry delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDeleteSql193)['where'] ?? '', 'VALUES'), true],
    'outer selected ids' => [static fn (): mixed => $outerResult193()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult193()['returning'], 'option_id'), [1, 2]],
    'outer row one status' => [static fn (): mixed => array_column($outerResult193()['tables']['wp_options'], 'status', 'option_id')[1], 'outer193'],
    'outer row two value' => [static fn (): mixed => array_column($outerResult193()['tables']['wp_options'], 'option_value', 'option_id')[2], 'https://home.test:outer193'],
    'fail selected ids' => [static fn (): mixed => $failResult193()['plan']->selectedIds, [8, 9]],
    'fail mutation ids' => [static fn (): mixed => $failResult193()['plan']->mutationIds, [8, 9]],
    'fail returns first row before conflict' => [static fn (): mixed => array_column($failResult193()['returning'], 'option_id'), [8]],
    'fail returning moved key' => [static fn (): mixed => $failResult193()['returning'][0]['moved_key'], 1],
    'fail row eight preserved in fail attempt image' => [static fn (): mixed => array_column($failResult193()['tables']['wp_options'], 'status', 'option_id')[8], 'fail193'],
    'fail row nine restored after conflict' => [static fn (): mixed => array_column($failResult193()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'fail conflict row id' => [static fn (): mixed => $failResult193()['failed_conflict']['row_id'] ?? null, 9],
    'fail conflict columns' => [static fn (): mixed => $failResult193()['failed_conflict']['columns'] ?? null, ['blog_id', 'option_name']],
    'fail conflict key' => [static fn (): mixed => $failResult193()['failed_conflict']['key'] ?? null, '1|plugin_batch'],
    'fail conflict existing row' => [static fn (): mixed => $failResult193()['failed_conflict']['conflicting_row_ids'] ?? null, [11]],
    'retry update selected ids' => [static fn (): mixed => $retryUpdateResult193()['plan']->selectedIds, [8, 9]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult193()['returning'], 'option_id'), [8, 9]],
    'retry update starts from restored row eight value' => [static fn (): mixed => $retryUpdateResult193()['returning'][0]['option_value'], 'rules:retry193'],
    'retry update starts from restored row nine value' => [static fn (): mixed => $retryUpdateResult193()['returning'][1]['option_value'], 'plugin:retry193'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult193()['plan']->selectedIds, [3, 4, 11]],
    'retry delete returning plugin flag' => [static fn (): mixed => array_column($retryDeleteResult193()['returning'], 'plugin_key'), [0, 0, 1]],
    'retry delete removes plugin conflict source' => [static fn (): mixed => in_array(11, array_column($retryDeleteResult193()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan193()['status'], 'rowvalue-update-delete-returning-fail-stream-savepoint-current-source-next193'],
    'plan savepoint' => [static fn (): mixed => $plan193()['savepoint'], 'wp_options_rowvalue_fail_stream_next193'],
    'plan rolled back' => [static fn (): mixed => $plan193()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved' => [static fn (): mixed => $plan193()['savepoint_preserved_after_rollback_to'], true],
    'plan savepoint released' => [static fn (): mixed => $plan193()['savepoint_released_after_retry'], true],
    'plan initial tables' => [static fn (): mixed => $plan193()['initial_tables'], $tables193],
    'plan savepoint image equals outer' => [static fn (): mixed => $plan193()['savepoint_image_tables'], $plan193()['outer_current_source_tables']],
    'plan fail attempt row eight changed' => [static fn (): mixed => array_column($plan193()['fail_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'fail193'],
    'plan rollback restores row eight queued' => [static fn (): mixed => array_column($plan193()['rollback_to_savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan rollback preserves outer row one' => [static fn (): mixed => array_column($plan193()['rollback_to_savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer193'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan193()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry193'],
    'plan current row nine retry' => [static fn (): mixed => array_column($plan193()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry193'],
    'plan current row eleven deleted' => [static fn (): mixed => in_array(11, array_column($plan193()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan193()['next_source_tables'], $plan193()['current_source_tables']],
    'plan outer statement phase' => [static fn (): mixed => $plan193()['outer_statements'][0]['phase'], 'outer-before-fail-savepoint-next193'],
    'plan fail statement phase' => [static fn (): mixed => $plan193()['fail_attempt_statements'][0]['phase'], 'fail-attempt-before-rollback-next193'],
    'plan retry phases' => [static fn (): mixed => array_column($plan193()['retry_statements'], 'phase'), ['retry-after-rollback-next193', 'retry-after-rollback-next193']],
    'plan fail source rows' => [static fn (): mixed => array_column($plan193()['fail_attempt_statements'][0]['source_rows'], 'option_id'), [8, 9]],
    'plan fail returning rows' => [static fn (): mixed => array_column($plan193()['fail_attempt_statements'][0]['returning_rows'], 'option_id'), [8]],
    'plan failed conflict surfaced' => [static fn (): mixed => $plan193()['failed_conflicts'][0]['row_id'], 9],
    'plan failed conflict existing source' => [static fn (): mixed => $plan193()['failed_conflicts'][0]['conflicting_row_ids'], [11]],
    'plan fail stream suppressed ids' => [static fn (): mixed => array_column($plan193()['suppressed_by_rollback_returning'][0]['rows'], 'option_id'), [8]],
    'plan retry update source restored ids' => [static fn (): mixed => array_column($plan193()['retry_statements'][0]['source_rows'], 'option_id'), [8, 9]],
    'plan retry delete source rows' => [static fn (): mixed => array_column($plan193()['retry_statements'][1]['source_rows'], 'option_id'), [3, 4, 11]],
    'plan outer returning count' => [static fn (): mixed => $plan193()['outer_yielded_returning_count'], 2],
    'plan fail returning count' => [static fn (): mixed => $plan193()['fail_yielded_before_conflict_count'], 1],
    'plan suppressed count' => [static fn (): mixed => $plan193()['suppressed_by_rollback_count'], 1],
    'plan retry returning count' => [static fn (): mixed => $plan193()['yielded_after_retry_count'], 5],
    'plan changed tables' => [static fn (): mixed => $plan193()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan193()['row_counts']['wp_options'], 8],
    'plan dependency fail stream' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-returning-partial-stream-next193', $plan193()['dependencies'], true), true],
    'plan dependency rollback suppression' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-suppresses-fail-returning-stream-next193', $plan193()['dependencies'], true), true],
    'plan dependency retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-delete-retry-reads-restored-current-source-next193', $plan193()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan193()['savepoint'], 'wp_custom_next193'],
    'custom plan retry count' => [static fn (): mixed => $customPlan193()['yielded_after_retry_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint($tables193, [], [$failSql193], [$retryUpdateSql193], $unique193), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint($tables193, [$outerSql193], [], [$retryUpdateSql193], $unique193), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint($tables193, [$outerSql193], [$failSql193], [], $unique193), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint($tables193, [$outerSql193], [$failSql193], [$retryUpdateSql193], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint($tables193, [$outerSql193], [$failSql193], [$retryUpdateSql193], $unique193, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeFailStreamSavepoint(['wp_options' => ['bad']], [$outerSql193], [$failSql193], [$retryUpdateSql193], $unique193), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases193 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next193 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
