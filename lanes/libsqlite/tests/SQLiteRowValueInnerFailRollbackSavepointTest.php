<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
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

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$outerUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$outerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS (1, '_transient_feed') AS deleted_feed ORDER BY option_id";
$innerUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('inner', option_value || ':inner', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner', 'plugin_batch') AS plugin_inner ORDER BY option_id DESC";
$innerDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$failUpdate = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_inner_migrated' ELSE 'siteurl' END, 'fail', option_value || ':fail', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_conflict ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$outerUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate, $tables, 'option_id', $unique);
$outerDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerDelete, $outerUpdateResult()['tables'], 'option_id', $unique);
$innerUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate, $outerDeleteResult()['tables'], 'option_id', $unique);
$innerDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete, $innerUpdateResult()['tables'], 'option_id', $unique);
$failProbe = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate, $innerDeleteResult()['tables'], 'option_id', [], true);
$failPreserve = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate, $innerDeleteResult()['tables'], 'option_id', $unique, true);
$retryUpdateResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate, $outerDeleteResult()['tables'], 'option_id', $unique);
$retryDeleteResult = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete, $retryUpdateResult()['tables'], 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint(
    $tables,
    [$outerUpdate, $outerDelete],
    [$innerUpdate, $innerDelete],
    $failUpdate,
    [$retryUpdate, $retryDelete],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint(
    $tables,
    [$outerUpdate],
    [$innerUpdate],
    $failUpdate,
    [$retryUpdate],
    $unique,
    'outer_custom',
    'inner_custom',
);

$cases = [
    'parser fail action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate)['conflict_action'], 'fail'],
    'parser fail assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser inner update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdate)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch'))"],
    'parser retry delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete)['returning'], 'dropped_network_siteurl'), true],
    'outer update ids' => [static fn (): mixed => array_column($outerUpdateResult()['returning'], 'option_id'), [7, 8]],
    'outer update row seven value' => [static fn (): mixed => array_column($outerUpdateResult()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer'],
    'outer delete selected feed' => [static fn (): mixed => $outerDeleteResult()['plan']->selectedIds, [3]],
    'outer delete removes feed' => [static fn (): mixed => in_array(3, array_column($outerDeleteResult()['tables']['wp_options'], 'option_id'), true), false],
    'inner update selected includes outer row and plugin' => [static fn (): mixed => $innerUpdateResult()['plan']->selectedIds, [9, 7]],
    'inner update returning table order' => [static fn (): mixed => array_column($innerUpdateResult()['returning'], 'option_id'), [7, 9]],
    'inner update row seven chains outer' => [static fn (): mixed => array_column($innerUpdateResult()['returning'], 'option_value', 'option_id')[7], 'theme:outer:inner'],
    'inner delete selected timeout' => [static fn (): mixed => $innerDeleteResult()['plan']->selectedIds, [4]],
    'inner delete removes timeout before rollback' => [static fn (): mixed => in_array(4, array_column($innerDeleteResult()['tables']['wp_options'], 'option_id'), true), false],
    'fail probe would return row seven and eight' => [static fn (): mixed => array_column($failProbe()['returning'], 'option_id'), [7, 8]],
    'fail preserve returns row seven only' => [static fn (): mixed => array_column($failPreserve()['returning'], 'option_id'), [7]],
    'fail conflict row eight' => [static fn (): mixed => $failPreserve()['failed_conflict']['row_id'], 8],
    'fail conflict key' => [static fn (): mixed => $failPreserve()['failed_conflict']['key'], '1|siteurl'],
    'fail preserves row seven before savepoint rollback' => [static fn (): mixed => array_column($failPreserve()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_inner_migrated'],
    'fail restores row eight before savepoint rollback' => [static fn (): mixed => array_column($failPreserve()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'retry update reads outer current not inner' => [static fn (): mixed => array_column($retryUpdateResult()['returning'], 'option_value', 'option_id')[7], 'theme:outer:retry'],
    'retry update restores plugin from inner rollback' => [static fn (): mixed => array_column($retryUpdateResult()['returning'], 'option_value', 'option_id')[9], 'plugin:retry'],
    'retry delete sees timeout restored' => [static fn (): mixed => $retryDeleteResult()['plan']->selectedIds, [4, 10]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],

    'plan status' => [static fn (): mixed => $plan()['status'], 'rowvalue-update-delete-returning-inner-fail-rollback-current-source'],
    'plan savepoints' => [static fn (): mixed => [$plan()['outer_savepoint'], $plan()['inner_savepoint']], ['wp_options_outer_rowvalue_fail_rollback', 'wp_options_inner_rowvalue_fail_rollback']],
    'plan outer survives' => [static fn (): mixed => $plan()['outer_changes_survive_inner_rollback'], true],
    'plan inner rolled back' => [static fn (): mixed => $plan()['inner_changes_rolled_back_after_fail'], true],
    'plan fail rows rolled back' => [static fn (): mixed => $plan()['fail_prior_rows_rolled_back_by_savepoint'], true],
    'plan returning suppressed' => [static fn (): mixed => $plan()['inner_returning_suppressed_by_rollback'], true],
    'plan retry reads outer source' => [static fn (): mixed => $plan()['retry_reads_outer_current_source'], true],
    'plan outer remains active' => [static fn (): mixed => $plan()['outer_savepoint_remains_active'], true],
    'plan outer image original' => [static fn (): mixed => $plan()['outer_savepoint_image_tables'], $tables],
    'plan outer current row seven' => [static fn (): mixed => array_column($plan()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer'],
    'plan outer current feed deleted' => [static fn (): mixed => in_array(3, array_column($plan()['outer_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan inner image equals outer current' => [static fn (): mixed => $plan()['inner_savepoint_image_tables'], $plan()['outer_current_source_tables']],
    'plan inner current row seven' => [static fn (): mixed => array_column($plan()['inner_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'inner'],
    'plan inner current timeout deleted' => [static fn (): mixed => in_array(4, array_column($plan()['inner_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan fail row seven migrated before rollback' => [static fn (): mixed => array_column($plan()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_inner_migrated'],
    'plan rollback restores inner image' => [static fn (): mixed => $plan()['after_inner_rollback_tables'], $plan()['inner_savepoint_image_tables']],
    'plan rollback restores row seven outer only' => [static fn (): mixed => array_column($plan()['after_inner_rollback_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer'],
    'plan rollback restores timeout' => [static fn (): mixed => in_array(4, array_column($plan()['after_inner_rollback_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer:retry'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer:retry'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry'],
    'plan final deleted ids gone' => [static fn (): mixed => array_intersect([3, 4, 10], array_column($plan()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan outer phases' => [static fn (): mixed => array_column($plan()['outer_statements'], 'phase'), ['outer-before-inner-fail-savepoint', 'outer-before-inner-fail-savepoint']],
    'plan inner phases' => [static fn (): mixed => array_column($plan()['inner_statements'], 'phase'), ['inner-before-fail', 'inner-before-fail']],
    'plan fail phase' => [static fn (): mixed => $plan()['fail_statement']['phase'], 'inner-or-fail-before-rollback'],
    'plan fail marked failed' => [static fn (): mixed => $plan()['fail_statement']['failed'], true],
    'plan fail conflict row' => [static fn (): mixed => $plan()['fail_statement']['failed_conflict']['row_id'], 8],
    'plan fail probe rows' => [static fn (): mixed => array_column($plan()['fail_statement']['probe_returning_rows'], 'option_id'), [7, 8]],
    'plan fail preserved rows before rollback' => [static fn (): mixed => array_column($plan()['fail_preserved_before_rollback_returning'][0]['rows'], 'option_id'), [7]],
    'plan fail suppressed row' => [static fn (): mixed => array_column($plan()['fail_suppressed_conflicting_returning'], 'option_id'), [8]],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['retry-after-inner-fail-rollback', 'retry-after-inner-fail-rollback']],
    'plan retry update source statuses' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'status'), ['outer', 'outer', 'queued']],
    'plan retry delete source ids' => [static fn (): mixed => array_column($plan()['retry_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan outer yielded count' => [static fn (): mixed => $plan()['outer_yielded_count'], 3],
    'plan inner suppressed count' => [static fn (): mixed => $plan()['inner_suppressed_count'], 3],
    'plan fail preserved count' => [static fn (): mixed => $plan()['fail_preserved_before_rollback_count'], 1],
    'plan fail suppressed count' => [static fn (): mixed => $plan()['fail_suppressed_conflicting_count'], 1],
    'plan total suppressed count' => [static fn (): mixed => $plan()['total_suppressed_by_inner_rollback_count'], 5],
    'plan retry count' => [static fn (): mixed => $plan()['retry_returning_count'], 5],
    'plan outer change count' => [static fn (): mixed => $plan()['outer_change_count'], 3],
    'plan inner change count' => [static fn (): mixed => $plan()['inner_change_count'], 3],
    'plan fail preserved change count' => [static fn (): mixed => $plan()['fail_preserved_change_count'], 1],
    'plan retry change count' => [static fn (): mixed => $plan()['retry_change_count'], 5],
    'plan changed tables' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan receipt suppressed' => [static fn (): mixed => $plan()['rollback_receipt']['suppressed_returning_count'], 5],
    'plan receipt conflict row' => [static fn (): mixed => $plan()['rollback_receipt']['fail_statement_conflict']['row_id'], 8],
    'plan dependency inner rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-inner-savepoint-rollback-suppresses-returning', $plan()['dependencies'], true), true],
    'plan dependency fail rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-prior-rows-rolled-back-by-savepoint', $plan()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-savepoint-retry-reads-outer-current-source', $plan()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'plan non overlap avoids trigger' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'trigger RETURNING') && !preg_match('/next[0-9]+/i', $plan()['non_overlap']), true],
    'custom savepoints' => [static fn (): mixed => [$customPlan()['outer_savepoint'], $customPlan()['inner_savepoint']], ['outer_custom', 'inner_custom']],
    'custom suppressed count' => [static fn (): mixed => $customPlan()['total_suppressed_by_inner_rollback_count'], 4],
    'custom retry count' => [static fn (): mixed => $customPlan()['retry_returning_count'], 3],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [], [$innerUpdate], $failUpdate, [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [], $failUpdate, [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], '', [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], $failUpdate, [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], $failUpdate, [$retryUpdate], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], $failUpdate, [$retryUpdate], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], $failUpdate, [$retryUpdate], $unique, 'outer_good', 'bad-name'), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], $failUpdate, [$retryUpdate], $unique, 'same', 'same'), InvalidArgumentException::class],
    'malformed fail action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint($tables, [$outerUpdate], [$innerUpdate], str_replace('OR FAIL', 'OR ABORT', $failUpdate), [$retryUpdate], $unique), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeInnerFailRollbackSavepoint(['wp_options' => ['bad']], [$outerUpdate], [$innerUpdate], $failUpdate, [$retryUpdate], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
