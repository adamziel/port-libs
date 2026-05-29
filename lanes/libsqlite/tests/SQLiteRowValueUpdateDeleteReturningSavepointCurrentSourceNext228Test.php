<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows228 = [
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

$tables228 = ['wp_options' => $rows228];
$unique228 = [['blog_id', 'option_name']];

$outerUpdate228 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer228', option_value || ':outer228', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$outerDelete228 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS (1, '_transient_feed') AS deleted_feed ORDER BY option_id";
$innerUpdate228 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner228', option_value || ':inner228', bytes + 3) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner228', 'plugin_batch') AS plugin_inner ORDER BY option_id DESC";
$innerDelete228 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$failUpdate228 = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (CASE option_id WHEN 7 THEN 2 ELSE 1 END, CASE option_id WHEN 7 THEN 'pending_theme_inner_migrated' ELSE 'siteurl' END, 'fail228', option_value || ':fail228', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_conflict ORDER BY option_id";
$retryUpdate228 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry228', option_value || ':retry228', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete228 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$outerUpdateResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate228, $tables228, 'option_id', $unique228);
$outerDeleteResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerDelete228, $outerUpdateResult228()['tables'], 'option_id', $unique228);
$innerUpdateResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate228, $outerDeleteResult228()['tables'], 'option_id', $unique228);
$innerDeleteResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete228, $innerUpdateResult228()['tables'], 'option_id', $unique228);
$failProbe228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate228, $innerDeleteResult228()['tables'], 'option_id', [], true);
$failPreserve228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failUpdate228, $innerDeleteResult228()['tables'], 'option_id', $unique228, true);
$retryUpdateResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate228, $outerDeleteResult228()['tables'], 'option_id', $unique228);
$retryDeleteResult228 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete228, $retryUpdateResult228()['tables'], 'option_id', $unique228);
$plan228 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228(
    $tables228,
    [$outerUpdate228, $outerDelete228],
    [$innerUpdate228, $innerDelete228],
    $failUpdate228,
    [$retryUpdate228, $retryDelete228],
    $unique228,
);
$customPlan228 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228(
    $tables228,
    [$outerUpdate228],
    [$innerUpdate228],
    $failUpdate228,
    [$retryUpdate228],
    $unique228,
    'outer_custom228',
    'inner_custom228',
);

$cases228 = [
    'parser fail action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failUpdate228)['conflict_action'], 'fail'],
    'parser fail assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failUpdate228)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser inner update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdate228)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch'))"],
    'parser retry delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete228)['returning'], 'dropped_network_siteurl'), true],
    'outer update ids' => [static fn (): mixed => array_column($outerUpdateResult228()['returning'], 'option_id'), [7, 8]],
    'outer update row seven value' => [static fn (): mixed => array_column($outerUpdateResult228()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer228'],
    'outer delete selected feed' => [static fn (): mixed => $outerDeleteResult228()['plan']->selectedIds, [3]],
    'outer delete removes feed' => [static fn (): mixed => in_array(3, array_column($outerDeleteResult228()['tables']['wp_options'], 'option_id'), true), false],
    'inner update selected includes outer row and plugin' => [static fn (): mixed => $innerUpdateResult228()['plan']->selectedIds, [9, 7]],
    'inner update returning table order' => [static fn (): mixed => array_column($innerUpdateResult228()['returning'], 'option_id'), [7, 9]],
    'inner update row seven chains outer' => [static fn (): mixed => array_column($innerUpdateResult228()['returning'], 'option_value', 'option_id')[7], 'theme:outer228:inner228'],
    'inner delete selected timeout' => [static fn (): mixed => $innerDeleteResult228()['plan']->selectedIds, [4]],
    'inner delete removes timeout before rollback' => [static fn (): mixed => in_array(4, array_column($innerDeleteResult228()['tables']['wp_options'], 'option_id'), true), false],
    'fail probe would return row seven and eight' => [static fn (): mixed => array_column($failProbe228()['returning'], 'option_id'), [7, 8]],
    'fail preserve returns row seven only' => [static fn (): mixed => array_column($failPreserve228()['returning'], 'option_id'), [7]],
    'fail conflict row eight' => [static fn (): mixed => $failPreserve228()['failed_conflict']['row_id'], 8],
    'fail conflict key' => [static fn (): mixed => $failPreserve228()['failed_conflict']['key'], '1|siteurl'],
    'fail preserves row seven before savepoint rollback' => [static fn (): mixed => array_column($failPreserve228()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_inner_migrated'],
    'fail restores row eight before savepoint rollback' => [static fn (): mixed => array_column($failPreserve228()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'retry update reads outer current not inner' => [static fn (): mixed => array_column($retryUpdateResult228()['returning'], 'option_value', 'option_id')[7], 'theme:outer228:retry228'],
    'retry update restores plugin from inner rollback' => [static fn (): mixed => array_column($retryUpdateResult228()['returning'], 'option_value', 'option_id')[9], 'plugin:retry228'],
    'retry delete sees timeout restored' => [static fn (): mixed => $retryDeleteResult228()['plan']->selectedIds, [4, 10]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult228()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],

    'plan status' => [static fn (): mixed => $plan228()['status'], 'rowvalue-update-delete-returning-inner-fail-rollback-current-source-next228'],
    'plan savepoints' => [static fn (): mixed => [$plan228()['outer_savepoint'], $plan228()['inner_savepoint']], ['wp_options_outer_rowvalue_next228', 'wp_options_inner_rowvalue_next228']],
    'plan outer survives' => [static fn (): mixed => $plan228()['outer_changes_survive_inner_rollback_next228'], true],
    'plan inner rolled back' => [static fn (): mixed => $plan228()['inner_changes_rolled_back_after_fail_next228'], true],
    'plan fail rows rolled back' => [static fn (): mixed => $plan228()['fail_prior_rows_rolled_back_by_savepoint_next228'], true],
    'plan returning suppressed' => [static fn (): mixed => $plan228()['inner_returning_suppressed_by_rollback_next228'], true],
    'plan retry reads outer source' => [static fn (): mixed => $plan228()['retry_reads_outer_current_source_next228'], true],
    'plan outer remains active' => [static fn (): mixed => $plan228()['outer_savepoint_remains_active_next228'], true],
    'plan outer image original' => [static fn (): mixed => $plan228()['outer_savepoint_image_tables'], $tables228],
    'plan outer current row seven' => [static fn (): mixed => array_column($plan228()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer228'],
    'plan outer current feed deleted' => [static fn (): mixed => in_array(3, array_column($plan228()['outer_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan inner image equals outer current' => [static fn (): mixed => $plan228()['inner_savepoint_image_tables'], $plan228()['outer_current_source_tables']],
    'plan inner current row seven' => [static fn (): mixed => array_column($plan228()['inner_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'inner228'],
    'plan inner current timeout deleted' => [static fn (): mixed => in_array(4, array_column($plan228()['inner_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan fail row seven migrated before rollback' => [static fn (): mixed => array_column($plan228()['fail_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme_inner_migrated'],
    'plan rollback restores inner image' => [static fn (): mixed => $plan228()['after_inner_rollback_tables'], $plan228()['inner_savepoint_image_tables']],
    'plan rollback restores row seven outer only' => [static fn (): mixed => array_column($plan228()['after_inner_rollback_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer228'],
    'plan rollback restores timeout' => [static fn (): mixed => in_array(4, array_column($plan228()['after_inner_rollback_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan228()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer228:retry228'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan228()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:outer228:retry228'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan228()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry228'],
    'plan final deleted ids gone' => [static fn (): mixed => array_intersect([3, 4, 10], array_column($plan228()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan228()['next_source_tables'], $plan228()['current_source_tables']],
    'plan outer phases' => [static fn (): mixed => array_column($plan228()['outer_statements'], 'phase'), ['outer-before-inner-savepoint-next228', 'outer-before-inner-savepoint-next228']],
    'plan inner phases' => [static fn (): mixed => array_column($plan228()['inner_statements'], 'phase'), ['inner-before-fail-next228', 'inner-before-fail-next228']],
    'plan fail phase' => [static fn (): mixed => $plan228()['fail_statement']['phase'], 'inner-or-fail-before-rollback-next228'],
    'plan fail marked failed' => [static fn (): mixed => $plan228()['fail_statement']['failed'], true],
    'plan fail conflict row' => [static fn (): mixed => $plan228()['fail_statement']['failed_conflict']['row_id'], 8],
    'plan fail probe rows' => [static fn (): mixed => array_column($plan228()['fail_statement']['probe_returning_rows'], 'option_id'), [7, 8]],
    'plan fail preserved rows before rollback' => [static fn (): mixed => array_column($plan228()['fail_preserved_before_rollback_returning'][0]['rows'], 'option_id'), [7]],
    'plan fail suppressed row' => [static fn (): mixed => array_column($plan228()['fail_suppressed_conflicting_returning'], 'option_id'), [8]],
    'plan retry phases' => [static fn (): mixed => array_column($plan228()['retry_statements'], 'phase'), ['retry-after-inner-rollback-next228', 'retry-after-inner-rollback-next228']],
    'plan retry update source statuses' => [static fn (): mixed => array_column($plan228()['retry_statements'][0]['source_rows'], 'status'), ['outer228', 'outer228', 'queued']],
    'plan retry delete source ids' => [static fn (): mixed => array_column($plan228()['retry_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan outer yielded count' => [static fn (): mixed => $plan228()['outer_yielded_count'], 3],
    'plan inner suppressed count' => [static fn (): mixed => $plan228()['inner_suppressed_count'], 3],
    'plan fail preserved count' => [static fn (): mixed => $plan228()['fail_preserved_before_rollback_count'], 1],
    'plan fail suppressed count' => [static fn (): mixed => $plan228()['fail_suppressed_conflicting_count'], 1],
    'plan total suppressed count' => [static fn (): mixed => $plan228()['total_suppressed_by_inner_rollback_count'], 5],
    'plan retry count' => [static fn (): mixed => $plan228()['retry_returning_count'], 5],
    'plan outer change count' => [static fn (): mixed => $plan228()['outer_change_count'], 3],
    'plan inner change count' => [static fn (): mixed => $plan228()['inner_change_count'], 3],
    'plan fail preserved change count' => [static fn (): mixed => $plan228()['fail_preserved_change_count'], 1],
    'plan retry change count' => [static fn (): mixed => $plan228()['retry_change_count'], 5],
    'plan changed tables' => [static fn (): mixed => $plan228()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan228()['row_counts']['wp_options'], 7],
    'plan receipt suppressed' => [static fn (): mixed => $plan228()['rollback_receipt_next228']['suppressed_returning_count'], 5],
    'plan receipt conflict row' => [static fn (): mixed => $plan228()['rollback_receipt_next228']['fail_statement_conflict']['row_id'], 8],
    'plan dependency inner rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-inner-savepoint-rollback-suppresses-returning-next228', $plan228()['dependencies'], true), true],
    'plan dependency fail rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-fail-prior-rows-rolled-back-by-savepoint-next228', $plan228()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-savepoint-retry-reads-outer-current-source-next228', $plan228()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan228()['dependency_closure_next228'], 'no new support component needed'), true],
    'plan non overlap mentions next209' => [static fn (): mixed => str_contains($plan228()['non_overlap_next228'], 'next209'), true],
    'custom savepoints' => [static fn (): mixed => [$customPlan228()['outer_savepoint'], $customPlan228()['inner_savepoint']], ['outer_custom228', 'inner_custom228']],
    'custom suppressed count' => [static fn (): mixed => $customPlan228()['total_suppressed_by_inner_rollback_count'], 4],
    'custom retry count' => [static fn (): mixed => $customPlan228()['retry_returning_count'], 3],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [], [$innerUpdate228], $failUpdate228, [$retryUpdate228], $unique228), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [], $failUpdate228, [$retryUpdate228], $unique228), InvalidArgumentException::class],
    'malformed empty fail rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], '', [$retryUpdate228], $unique228), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], $failUpdate228, [], $unique228), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], $failUpdate228, [$retryUpdate228], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], $failUpdate228, [$retryUpdate228], $unique228, 'bad-name'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], $failUpdate228, [$retryUpdate228], $unique228, 'outer_good', 'bad-name'), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], $failUpdate228, [$retryUpdate228], $unique228, 'same228', 'same228'), InvalidArgumentException::class],
    'malformed fail action rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228($tables228, [$outerUpdate228], [$innerUpdate228], str_replace('OR FAIL', 'OR ABORT', $failUpdate228), [$retryUpdate228], $unique228), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext228(['wp_options' => ['bad']], [$outerUpdate228], [$innerUpdate228], $failUpdate228, [$retryUpdate228], $unique228), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases228 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next228 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
