<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows195 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 31, 'option_value' => 'a:0:{}'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 42, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 33, 'option_value' => 'network-plugins'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 10, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rules', 'bytes' => 17, 'option_value' => 'rules'],
];

$tables195 = ['wp_options' => $rows195];
$attemptUpdate195 = "UPDATE wp_options SET status = 'attempt195', option_value = option_value || ':attempt195', match_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')), plugin_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, NULL)), not_plugin_flag = NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) WHERE NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) RETURNING option_id, blog_id, option_name, status, option_value, match_flag, plugin_flag, not_plugin_flag, NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) AS returning_match ORDER BY option_id";
$attemptDelete195 = "DELETE FROM wp_options WHERE NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) AND autoload = 'no' RETURNING option_id, blog_id, option_name, NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) AS delete_match ORDER BY option_id";
$retryUpdate195 = "UPDATE wp_options SET status = 'retry195', option_value = option_value || ':retry195', match_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')), plugin_flag = NOT ((blog_id, status) IS DISTINCT FROM (1, NULL)), not_plugin_flag = NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) WHERE NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) RETURNING option_id, blog_id, option_name, status, option_value, match_flag, plugin_flag, not_plugin_flag, NOT ((blog_id, status) IS DISTINCT FROM (1, 'live')) AS returning_match ORDER BY option_id";
$retryDelete195 = $attemptDelete195;

$attemptUpdateResult195 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate195, $tables195);
$attemptDeleteAfterUpdate195 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete195, $attemptUpdateResult195()['tables']);
$retryUpdateResult195 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate195, $tables195);
$retryDeleteAfterUpdate195 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete195, $retryUpdateResult195()['tables']);
$plan195 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRowValuePredicateRollbackRetrySavepoint(
    $tables195,
    [$attemptUpdate195, $attemptDelete195],
    [$retryUpdate195, $retryDelete195],
    'app_settings_rowvalue_unary_not_distinct_next195',
);

$cases195 = [
    'parser keeps unary not distinct where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate195)['where'], "NOT ((blog_id, status) IS DISTINCT FROM (1, 'live'))"],
    'parser keeps unary not distinct assignment' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate195)['assignments']['match_flag'], "NOT ((blog_id, status) IS DISTINCT FROM (1, 'live'))"],
    'parser keeps unary not not distinct assignment' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate195)['assignments']['not_plugin_flag'], 'NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL))'],
    'parser keeps delete unary not distinct where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptDelete195)['where'], "NOT ((blog_id, status) IS NOT DISTINCT FROM (1, NULL)) AND autoload = 'no'"],
    'attempt update selected only blog one live rows' => [static fn (): mixed => $attemptUpdateResult195()['plan']->selectedIds, [1, 2]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult195()['returning'], 'option_id'), [1, 2]],
    'attempt update returning unary not flag sees post update image' => [static fn (): mixed => array_column($attemptUpdateResult195()['returning'], 'returning_match'), [0, 0]],
    'attempt update assignment match flags numeric' => [static fn (): mixed => array_column($attemptUpdateResult195()['returning'], 'match_flag'), [1, 1]],
    'attempt update assignment plugin flags false' => [static fn (): mixed => array_column($attemptUpdateResult195()['returning'], 'plugin_flag'), [0, 0]],
    'attempt update assignment not plugin flags true' => [static fn (): mixed => array_column($attemptUpdateResult195()['returning'], 'not_plugin_flag'), [1, 1]],
    'attempt update row one stored numeric not result' => [static fn (): mixed => array_column($attemptUpdateResult195()['tables']['wp_options'], 'match_flag', 'option_id')[1], 1],
    'attempt update active plugins not selected' => [static fn (): mixed => array_key_exists('match_flag', array_column($attemptUpdateResult195()['tables']['wp_options'], null, 'option_id')[3]), false],
    'attempt update network live not selected by blog id distinct' => [static fn (): mixed => array_key_exists('match_flag', array_column($attemptUpdateResult195()['tables']['wp_options'], null, 'option_id')[5]), false],
    'attempt delete selected transient rows excluding blog one null tuple' => [static fn (): mixed => $attemptDeleteAfterUpdate195()['plan']->selectedIds, [7]],
    'attempt delete returning unary not not distinct true' => [static fn (): mixed => $attemptDeleteAfterUpdate195()['returning'][0]['delete_match'], 1],
    'attempt delete keeps blog one transient because tuple is not distinct' => [static fn (): mixed => in_array(4, array_column($attemptDeleteAfterUpdate195()['tables']['wp_options'], 'option_id'), true), true],
    'attempt delete removes network transient' => [static fn (): mixed => in_array(7, array_column($attemptDeleteAfterUpdate195()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected same rows from original image' => [static fn (): mixed => $retryUpdateResult195()['plan']->selectedIds, [1, 2]],
    'retry update starts from original value' => [static fn (): mixed => $retryUpdateResult195()['returning'][0]['option_value'], 'https://old.test:retry195'],
    'retry delete selected same transient' => [static fn (): mixed => $retryDeleteAfterUpdate195()['plan']->selectedIds, [7]],

    'plan status reuses rollback retry model' => [static fn (): mixed => $plan195()['status'], 'rowvalue-empty-in-returning-rolled-back-retried-next188'],
    'plan custom savepoint' => [static fn (): mixed => $plan195()['savepoint'], 'app_settings_rowvalue_unary_not_distinct_next195'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan195()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback' => [static fn (): mixed => $plan195()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan195()['released_after_retry'], true],
    'plan savepoint image ids' => [static fn (): mixed => array_column($plan195()['savepoint_image_tables']['wp_options'], 'option_id'), range(1, 8)],
    'plan attempt current row one attempted value' => [static fn (): mixed => array_column($plan195()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:attempt195'],
    'plan attempt current deletes row seven' => [static fn (): mixed => in_array(7, array_column($plan195()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row one value' => [static fn (): mixed => array_column($plan195()['rollback_to_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'plan rollback restores row seven' => [static fn (): mixed => in_array(7, array_column($plan195()['rollback_to_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan195()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan195()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt update selected ids' => [static fn (): mixed => $plan195()['attempt_statements'][0]['selected_ids'], [1, 2]],
    'plan attempt delete selected ids' => [static fn (): mixed => $plan195()['attempt_statements'][1]['selected_ids'], [7]],
    'plan retry update selected ids' => [static fn (): mixed => $plan195()['retry_statements'][0]['selected_ids'], [1, 2]],
    'plan retry delete selected ids' => [static fn (): mixed => $plan195()['retry_statements'][1]['selected_ids'], [7]],
    'plan attempt returning count' => [static fn (): mixed => $plan195()['attempt_returning_count'], 3],
    'plan suppressed returning count' => [static fn (): mixed => $plan195()['suppressed_by_rollback_count'], 3],
    'plan yielded after retry count' => [static fn (): mixed => $plan195()['yielded_after_retry_count'], 3],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan195()['attempt_changes_before_rollback_to'], 3],
    'plan retry changes after release' => [static fn (): mixed => $plan195()['changes_after_retry_release'], 3],
    'plan retry update yielded unary flags' => [static fn (): mixed => array_column($plan195()['yielded_after_retry_returning'][0]['rows'], 'match_flag'), [1, 1]],
    'plan retry delete yielded id' => [static fn (): mixed => $plan195()['yielded_after_retry_returning'][1]['rows'][0]['option_id'], 7],
    'plan final row one retry value' => [static fn (): mixed => array_column($plan195()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:retry195'],
    'plan final ids omit row seven' => [static fn (): mixed => array_column($plan195()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 8]],
    'plan final blog one transient kept' => [static fn (): mixed => in_array(4, array_column($plan195()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan195()['next_source_tables'], $plan195()['current_source_tables']],
    'plan changed tables after retry' => [static fn (): mixed => $plan195()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan195()['row_counts']['wp_options'], 7],
    'plan keeps existing dependency token' => [static fn (): mixed => in_array('sqlite-rowvalue-empty-in-returning-rollback-current-source-next188', $plan195()['dependencies'], true), true],
    'malformed unary not predicate rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE NOT () RETURNING option_id", $tables195), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases195 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next195 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
