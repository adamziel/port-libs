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
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (3, 'siteurl', option_name || ':fail', option_value || ':fail', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (3, 'siteurl') AS key_match ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'siteurl')) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$releaseSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':released', 'released', option_value || ':released', bytes + 5) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";

$preservedFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $tables, 'option_id', $unique, true);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext162($tables, [$failSql, $deleteSql], $unique);
$releasePlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext162($tables, [$releaseSql, $deleteSql], $unique, 'wp_options_no_fail_next162');

$cases = [
    'parser fail conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failSql)['conflict_action'], 'fail'],
    'parser row value columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($failSql)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser returning row value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failSql)['returning'], "option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (3, 'siteurl') AS key_match"],
    'parser order by option id' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($failSql)['order_by'][0]['column'], 'option_id'],
    'preserved fail selected ids' => [static fn (): mixed => $preservedFail()['plan']->selectedIds, [7, 8]],
    'preserved fail mutation ids' => [static fn (): mixed => $preservedFail()['plan']->mutationIds, [7, 8]],
    'preserved fail returns first successful row only' => [static fn (): mixed => array_column($preservedFail()['returning'], 'option_id'), [7]],
    'preserved fail row seven returned as siteurl' => [static fn (): mixed => $preservedFail()['returning'][0]['option_name'], 'siteurl'],
    'preserved fail row seven returned key match true' => [static fn (): mixed => $preservedFail()['returning'][0]['key_match'], 1],
    'preserved fail records one conflict' => [static fn (): mixed => count($preservedFail()['conflicts']), 1],
    'preserved fail conflict is partial row seven' => [static fn (): mixed => $preservedFail()['conflicts'][0]['conflicting_row_ids'], [7]],
    'preserved fail failed conflict is partial row seven' => [static fn (): mixed => $preservedFail()['failed_conflict']['conflicting_row_ids'], [7]],
    'preserved fail second conflict key' => [static fn (): mixed => $preservedFail()['failed_conflict']['key'], '3|siteurl'],
    'preserved fail row seven remains partially changed' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'preserved fail row seven status from old option name' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'status', 'option_id')[7], 'pending_theme:fail'],
    'preserved fail row eight restored after failed conflict' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'preserved fail deletes no conflict rows' => [static fn (): mixed => $preservedFail()['deleted_conflict_rows'], []],

    'plan rolls back after fail' => [static fn (): mixed => $plan()['status'], 'rolled-back-after-or-fail'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_fail_batch'],
    'plan rolled back flag' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan preserves savepoint' => [static fn (): mixed => $plan()['savepoint_preserved'], true],
    'plan no committed executed statements' => [static fn (): mixed => $plan()['executed_statements'], []],
    'plan attempted only fail statement' => [static fn (): mixed => count($plan()['attempted_statements_before_rollback']), 1],
    'plan delete after fail not attempted' => [static fn (): mixed => array_column($plan()['attempted_statements_before_rollback'], 'action'), ['update']],
    'plan attempted conflict action fail' => [static fn (): mixed => $plan()['attempted_statements_before_rollback'][0]['conflict_action'], 'fail'],
    'plan attempted source rows are row seven and eight' => [static fn (): mixed => array_column($plan()['attempted_statements_before_rollback'][0]['source_rows'], 'option_id'), [7, 8]],
    'plan attempted returning has row seven only' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'][0]['rows'], 'option_id'), [7]],
    'plan committed returning empty after rollback' => [static fn (): mixed => $plan()['yielded_returning'], []],
    'plan discarded returning count one' => [static fn (): mixed => $plan()['discarded_returning_count'], 1],
    'plan attempted changes one' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 1],
    'plan committed changes zero' => [static fn (): mixed => $plan()['changes'], 0],
    'plan partial fail ordinal zero' => [static fn (): mixed => $plan()['partial_fail']['ordinal'], 0],
    'plan partial fail change count one' => [static fn (): mixed => $plan()['partial_fail']['partial_change_count'], 1],
    'plan partial fail yielded row seven' => [static fn (): mixed => array_column($plan()['partial_fail']['yielded_returning'], 'option_id'), [7]],
    'plan partial fail conflict ids' => [static fn (): mixed => $plan()['partial_fail']['conflict']['conflicting_row_ids'], [7]],
    'plan partial current source kept row seven siteurl before rollback' => [static fn (): mixed => array_column($plan()['pre_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'plan partial current source kept row eight original before rollback' => [static fn (): mixed => array_column($plan()['pre_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan rollback restores original ids' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'plan rollback restores row seven pending theme' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback restores row seven null status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback leaves transient rows undeleted' => [static fn (): mixed => array_intersect([3, 4], array_column($plan()['current_source_tables']['wp_options'], 'option_id')), [3, 4]],
    'plan next source equals savepoint image' => [static fn (): mixed => $plan()['next_source_tables'], $tables],
    'plan changed tables empty' => [static fn (): mixed => $plan()['savepoint_changed_tables'], []],
    'plan row count restored' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 8],
    'plan dependency records fail preservation' => [static fn (): mixed => in_array('sqlite-update-or-fail-preserves-prior-rowvalue-changes-until-savepoint-rollback', $plan()['dependencies'], true), true],
    'plan dependency records returning discard' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-fail-stream-discarded-by-rollback-to-savepoint', $plan()['dependencies'], true), true],
    'plan dependency records delete not run' => [static fn (): mixed => in_array('sqlite-delete-returning-after-partial-fail-is-not-run-before-rollback-to', $plan()['dependencies'], true), true],
    'plan dependency records source restored' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-restored-after-rowvalue-or-fail', $plan()['dependencies'], true), true],

    'release plan status without fail' => [static fn (): mixed => $releasePlan()['status'], 'released-without-fail'],
    'release plan savepoint custom' => [static fn (): mixed => $releasePlan()['savepoint'], 'wp_options_no_fail_next162'],
    'release plan committed statements update delete' => [static fn (): mixed => array_column($releasePlan()['executed_statements'], 'action'), ['update', 'delete']],
    'release plan yielded update ids' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'release plan yielded delete ids' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][1]['rows'], 'option_id'), [1, 3, 4]],
    'release plan changes five' => [static fn (): mixed => $releasePlan()['changes'], 5],
    'release plan current row seven released' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'released'],
    'release plan deleted transients and original siteurl' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_id'), [2, 5, 6, 7, 8]],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext162($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext162($tables, [$failSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext162(['wp_options' => ['bad']], [$failSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next162 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
