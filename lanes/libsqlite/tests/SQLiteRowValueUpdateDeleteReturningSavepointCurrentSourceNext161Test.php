<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', option_name || ':fail', option_value || ':fail', bytes + 100) WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS (4, 'siteurl') AS target_tuple ORDER BY option_id DESC";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry', 'retry', option_value || ':retry', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:retry') AS pending_retry ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$cleanSql = "UPDATE OR FAIL wp_options SET (option_name, status) = (option_name || ':clean', 'clean') WHERE option_id IN (7, 8) RETURNING option_id, option_name, status ORDER BY option_id";

$parsedFail = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($failSql);
$plainFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $tables, 'option_id', $unique);
$preservedFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $tables, 'option_id', $unique, true);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161(
    $tables,
    [$failSql, $discardDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161(
    $tables,
    [$cleanSql],
    [$retryUpdateSql],
    $unique,
    'wp_clean_retry',
);

$cases = [
    'parser records fail conflict action' => [static fn (): mixed => $parsedFail()['conflict_action'], 'fail'],
    'parser records row-value assignment columns' => [static fn (): mixed => array_keys($parsedFail()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser returning keeps row-value IS expression' => [static fn (): mixed => str_contains($parsedFail()['returning'], 'target_tuple'), true],
    'parser selected where remains option id list' => [static fn (): mixed => $parsedFail()['where'], 'option_id IN (8, 7)'],
    'plain fail throws without preserve mode' => [$plainFail, InvalidArgumentException::class],
    'preserved fail selected ids descending' => [static fn (): mixed => $preservedFail()['plan']->selectedIds, [8, 7]],
    'preserved fail mutation ids follow table write order' => [static fn (): mixed => $preservedFail()['plan']->mutationIds, [7, 8]],
    'preserved fail returns first successful row only' => [static fn (): mixed => array_column($preservedFail()['returning'], 'option_id'), [7]],
    'preserved fail row seven tuple true' => [static fn (): mixed => $preservedFail()['returning'][0]['target_tuple'], 1],
    'preserved fail row seven current source changed' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'preserved fail row eight restored after failed conflict' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'option_name', 'option_id')[8], 'orphaned_cache'],
    'preserved fail conflict row id' => [static fn (): mixed => $preservedFail()['failed_conflict']['row_id'], 8],
    'preserved fail conflict peer id' => [static fn (): mixed => $preservedFail()['failed_conflict']['conflicting_row_ids'], [7]],
    'preserved fail conflict key' => [static fn (): mixed => $preservedFail()['failed_conflict']['key'], '4|siteurl'],

    'plan status fail rollback retry' => [static fn (): mixed => $plan()['status'], 'failed-rolled-back-to-savepoint-retried'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_fail_retry_batch'],
    'plan failed before rollback true' => [static fn (): mixed => $plan()['failed_before_rollback'], true],
    'plan failed statement ordinal zero' => [static fn (): mixed => $plan()['failed_statement_ordinal'], 0],
    'plan failed conflict row eight' => [static fn (): mixed => $plan()['failed_conflict']['row_id'], 8],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback to' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan()['released_after_retry'], true],
    'plan pre rollback statement count stops at fail' => [static fn (): mixed => count($plan()['pre_rollback_statements']), 1],
    'plan pre rollback phase' => [static fn (): mixed => $plan()['pre_rollback_statements'][0]['phase'], 'before-rollback'],
    'plan pre rollback selected ids descending' => [static fn (): mixed => $plan()['pre_rollback_statements'][0]['selected_ids'], [8, 7]],
    'plan pre rollback returning one row' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'][0]['returning_rows'], 'option_id'), [7]],
    'plan pre rollback failed conflict recorded' => [static fn (): mixed => $plan()['pre_rollback_statements'][0]['failed_conflict']['row_id'], 8],
    'plan discard delete not executed after fail' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'action'), ['update']],
    'plan failed current source includes row seven change' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'pending_theme:fail'],
    'plan failed current source keeps row eight original' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'plan rollback current source restores row eight' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'orphaned_cache'],
    'plan rollback current source restores all ids' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan discarded returning one' => [static fn (): mixed => $plan()['discarded_returning_count'], 1],
    'plan discarded returning row seven' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [7]],
    'plan failed changes before rollback one' => [static fn (): mixed => $plan()['failed_changes_before_rollback_to'], 1],
    'plan retry statement actions update delete' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-rollback', 'after-rollback']],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'orphaned_cache']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan retry row seven predicate true' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry row eight value from original' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['option_value'], 'cache:retry'],
    'plan retry delete source rows restored' => [static fn (): mixed => array_column($plan()['retry_statements'][1]['source_rows'], 'option_name'), ['_transient_feed', '_transient_timeout_feed']],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan yielded returning count four' => [static fn (): mixed => $plan()['yielded_returning_count'], 4],
    'plan changes after release four' => [static fn (): mixed => $plan()['changes_after_release'], 4],
    'plan final row ids omit transients' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven retry name' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry'],
    'plan final row eight retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry'],
    'plan final row eight bytes original plus retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'bytes', 'option_id')[8], 15],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan savepoint image equals original' => [static fn (): mixed => $plan()['savepoint_image_tables'], $tables],
    'plan row count seven' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan changed table wp options' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency fail preserved until rollback' => [static fn (): mixed => in_array('sqlite-update-or-fail-preserves-prior-rowvalue-returning-until-rollback-to', $plan()['dependencies'], true), true],
    'plan dependency rollback discards fail returning' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-fail-returning-stream', $plan()['dependencies'], true), true],
    'plan dependency retry restored source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-reads-restored-current-source-next161', $plan()['dependencies'], true), true],

    'clean plan status released clean retry' => [static fn (): mixed => $cleanPlan()['status'], 'released-after-clean-retry'],
    'clean plan no failed conflict' => [static fn (): mixed => $cleanPlan()['failed_conflict'], null],
    'clean plan pre rollback returns two rows' => [static fn (): mixed => array_column($cleanPlan()['discarded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'clean plan discarded count two' => [static fn (): mixed => $cleanPlan()['discarded_returning_count'], 2],
    'clean plan retry starts from original row seven' => [static fn (): mixed => array_column($cleanPlan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'orphaned_cache']],
    'clean plan final row seven retry not clean' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry'],
    'clean plan custom savepoint' => [static fn (): mixed => $cleanPlan()['savepoint'], 'wp_clean_retry'],

    'malformed empty before statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161($tables, [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161($tables, [$failSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161($tables, [$failSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext161(['wp_options' => ['bad']], [$failSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next161 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
