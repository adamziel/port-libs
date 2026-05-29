<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows181 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 20, 'option_value' => 'a:0:{}'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 21, 'option_value' => 'rules'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 10, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 31, 'option_value' => 'network'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 32, 'option_value' => 'network-rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'pending', 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'core', 'bytes' => 40, 'option_value' => 'https://queued.test'],
];

$tables181 = ['wp_options' => $rows181];
$unique181 = [['blog_id', 'option_name']];

$deleteInSql181 = "DELETE FROM wp_options WHERE (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, blog_id, status, option_name, (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_in, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id";
$deleteNotInSql181 = "DELETE FROM wp_options WHERE (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id";
$stageSql181 = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage181', status, option_value || ':stage181', bytes + 1) WHERE (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id";
$rollbackSql181 = "UPDATE OR FAIL wp_options SET (blog_id, option_name) = (1, 'active_plugins') WHERE option_id = 7 RETURNING option_id, blog_id, option_name";
$retryDeleteSql181 = "DELETE FROM wp_options WHERE (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_in, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id";
$retryUpdateSql181 = "UPDATE wp_options SET (status, option_value) = ('kept181', option_value || ':kept181') WHERE (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, option_value, (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) AS tuple_in ORDER BY option_id";

$deleteIn181 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteInSql181, $tables181, 'option_id', $unique181);
$deleteNotIn181 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteNotInSql181, $tables181, 'option_id', $unique181);
$plan181 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173(
    $tables181,
    [$stageSql181, $rollbackSql181],
    [$retryDeleteSql181, $retryUpdateSql181],
    $unique181,
    'wp_options_rowvalue_in_retry_next181',
);

$cases181 = [
    'delete in selects only non nullable exact tuple match' => [static fn (): mixed => $deleteIn181()['plan']->selectedIds, [8]],
    'delete in returning id eight' => [static fn (): mixed => array_column($deleteIn181()['returning'], 'option_id'), [8]],
    'delete in row eight membership false against nullable rhs projection' => [static fn (): mixed => $deleteIn181()['returning'][0]['tuple_in'], 0],
    'delete in row eight not in true against nullable rhs projection' => [static fn (): mixed => $deleteIn181()['returning'][0]['tuple_not_in'], 1],
    'delete in leaves nullable tuple members and deterministic non matches' => [static fn (): mixed => array_column($deleteIn181()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'delete not in selects rows proven absent from nullable tuple list' => [static fn (): mixed => $deleteNotIn181()['plan']->selectedIds, [2, 3, 4, 5, 7]],
    'delete not in returning ids' => [static fn (): mixed => array_column($deleteNotIn181()['returning'], 'option_id'), [2, 3, 4, 5, 7]],
    'delete not in rewrite rules true through later mismatch' => [static fn (): mixed => $deleteNotIn181()['returning'][0]['tuple_not_in'], 1],
    'delete not in transient feed true through later mismatch' => [static fn (): mixed => $deleteNotIn181()['returning'][1]['tuple_not_in'], 1],
    'delete not in transient timeout true through later mismatch' => [static fn (): mixed => $deleteNotIn181()['returning'][2]['tuple_not_in'], 1],
    'delete not in network active plugins true by blog id' => [static fn (): mixed => $deleteNotIn181()['returning'][3]['tuple_not_in'], 1],
    'delete not in pending theme true by blog id' => [static fn (): mixed => $deleteNotIn181()['returning'][4]['tuple_not_in'], 1],
    'delete not in leaves tuple list members' => [static fn (): mixed => array_column($deleteNotIn181()['tables']['wp_options'], 'option_id'), [1, 6, 8]],

    'plan status rolled back retried' => [static fn (): mixed => $plan181()['status'], 'fail-stream-rolled-back-retried-current-source-next173'],
    'plan custom savepoint' => [static fn (): mixed => $plan181()['savepoint'], 'wp_options_rowvalue_in_retry_next181'],
    'plan failed ordinal after stage' => [static fn (): mixed => $plan181()['failed_statement_ordinal'], 1],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan181()['rolled_back_to_savepoint'], true],
    'plan preserves savepoint after rollback to' => [static fn (): mixed => $plan181()['savepoint_preserved_after_rollback_to'], true],
    'plan attempt stage selected nullable not in ids' => [static fn (): mixed => $plan181()['attempt_statements'][0]['selected_ids'], [2, 3, 4, 5, 7]],
    'plan attempt stage returned ids' => [static fn (): mixed => array_column($plan181()['attempted_returning_before_rollback'][0]['rows'], 'option_id'), [2, 3, 4, 5, 7]],
    'plan attempt stage rewrite rules not in true' => [static fn (): mixed => $plan181()['attempted_returning_before_rollback'][0]['rows'][0]['tuple_not_in'], 1],
    'plan attempt stage transient not in true' => [static fn (): mixed => $plan181()['attempted_returning_before_rollback'][0]['rows'][1]['tuple_not_in'], 1],
    'plan failed conflict row seven' => [static fn (): mixed => $plan181()['failed_conflict']['row_id'], 7],
    'plan failed conflict peer row one' => [static fn (): mixed => $plan181()['failed_conflict']['conflicting_row_ids'], [1]],
    'plan failed conflict key' => [static fn (): mixed => $plan181()['failed_conflict']['key'], '1|active_plugins'],
    'plan discarded attempted returning count' => [static fn (): mixed => $plan181()['discarded_returning_count'], 5],
    'plan attempted changes before rollback' => [static fn (): mixed => $plan181()['attempted_changes_before_rollback_to'], 5],
    'plan failed current source staged rewrite rules' => [static fn (): mixed => array_column($plan181()['failed_current_source_tables']['wp_options'], 'option_name', 'option_id')[2], 'rewrite_rules:stage181'],
    'plan rollback restores rewrite rules' => [static fn (): mixed => array_column($plan181()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[2], 'rewrite_rules'],
    'plan rollback restores pending theme' => [static fn (): mixed => array_column($plan181()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan retry actions delete then update' => [static fn (): mixed => array_column($plan181()['retry_statements'], 'action'), ['delete', 'update']],
    'plan retry delete selected nullable not in ids' => [static fn (): mixed => $plan181()['retry_statements'][0]['selected_ids'], [2, 3, 4, 5, 7]],
    'plan retry delete source names restored' => [static fn (): mixed => array_column($plan181()['retry_statements'][0]['source_rows'], 'option_name'), ['rewrite_rules', '_transient_feed', '_transient_timeout_feed', 'active_plugins', 'pending_theme']],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan181()['yielded_returning'][0]['rows'], 'option_id'), [2, 3, 4, 5, 7]],
    'plan retry delete rewrite tuple in false through later mismatch' => [static fn (): mixed => $plan181()['yielded_returning'][0]['rows'][0]['tuple_in'], 0],
    'plan retry delete rewrite tuple not in true through later mismatch' => [static fn (): mixed => $plan181()['yielded_returning'][0]['rows'][0]['tuple_not_in'], 1],
    'plan retry update selected non nullable tuple member' => [static fn (): mixed => $plan181()['retry_statements'][1]['selected_ids'], [8]],
    'plan retry update source row is rollback source' => [static fn (): mixed => array_column($plan181()['retry_statements'][1]['source_rows'], 'option_name'), ['siteurl']],
    'plan retry update returning id eight' => [static fn (): mixed => array_column($plan181()['yielded_returning'][1]['rows'], 'option_id'), [8]],
    'plan retry update returning expression sees updated tuple outside old list' => [static fn (): mixed => array_column($plan181()['yielded_returning'][1]['rows'], 'tuple_in'), [0]],
    'plan retry update status kept for non nullable tuple' => [static fn (): mixed => array_column($plan181()['yielded_returning'][1]['rows'], 'status'), ['kept181']],
    'plan yielded returning count' => [static fn (): mixed => $plan181()['yielded_returning_count'], 6],
    'plan changes after retry release' => [static fn (): mixed => $plan181()['changes_after_retry_release'], 6],
    'plan final ids keep only tuple members' => [static fn (): mixed => array_column($plan181()['current_source_tables']['wp_options'], 'option_id'), [1, 6, 8]],
    'plan final values keep nullable tuple members and update non nullable tuple' => [static fn (): mixed => array_column($plan181()['current_source_tables']['wp_options'], 'option_value', 'option_id'), [1 => 'a:0:{}', 6 => 'network-rules', 8 => 'https://queued.test:kept181']],
    'plan next source equals current source' => [static fn (): mixed => $plan181()['next_source_tables'], $plan181()['current_source_tables']],
    'plan changed tables reports wp options' => [static fn (): mixed => $plan181()['changed_tables_after_retry'], ['wp_options']],
    'plan row count three' => [static fn (): mixed => $plan181()['row_counts']['wp_options'], 3],
    'plan dependency includes fail stream' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-returning-stream-before-savepoint-rollback-next173', $plan181()['dependencies'], true), true],
    'plan dependency includes rollback discard' => [static fn (): mixed => in_array('sqlite-rollback-to-discards-update-delete-returning-stream-next173', $plan181()['dependencies'], true), true],
];

$tests = [];
foreach ($cases181 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next181 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
