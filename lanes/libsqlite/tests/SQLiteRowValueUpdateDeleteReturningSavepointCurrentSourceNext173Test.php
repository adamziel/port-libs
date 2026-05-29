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
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', option_name || ':attempt', option_value || ':attempt', bytes + 100) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (3, 'plugin_batch') AS moved_key ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('restored-retry', option_value || ':retry', bytes + 1) WHERE (blog_id, status) IS NOT DISTINCT FROM (3, 'queued') RETURNING option_id, status, option_value, bytes, (blog_id, status) IS (3, 'restored-retry') AS retried_tuple ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS deleted_tuple ORDER BY option_id LIMIT 1";
$cleanSql = "UPDATE wp_options SET (status, option_value) = ('clean-release', option_value || ':clean') WHERE option_id IN (7, 8) RETURNING option_id, status, option_value ORDER BY option_id";

$parsedFail = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($failSql);
$parsedRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($retryUpdateSql);
$plainFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $tables, 'option_id', $unique);
$preservedFail = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($failSql, $tables, 'option_id', $unique, true);
$retryUpdateOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173(
    $tables,
    [$failSql, $cleanSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173(
    $tables,
    [$cleanSql],
    [$retryDeleteSql],
    $unique,
    'wp_options_rowvalue_clean_next173',
);

$cases = [
    'parser records fail conflict action' => [static fn (): mixed => $parsedFail()['conflict_action'], 'fail'],
    'parser records row-value assignment columns' => [static fn (): mixed => array_keys($parsedFail()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser keeps distinct returning expression' => [static fn (): mixed => str_contains($parsedFail()['returning'], 'IS DISTINCT FROM'), true],
    'parser fail order by option id' => [static fn (): mixed => $parsedFail()['order_by'][0]['column'], 'option_id'],
    'parser retry keeps null-safe row predicate' => [static fn (): mixed => $parsedRetry()['where'], "(blog_id, status) IS NOT DISTINCT FROM (3, 'queued')"],
    'parser retry returning tuple expression' => [static fn (): mixed => str_contains($parsedRetry()['returning'], "IS (3, 'restored-retry')"), true],
    'plain fail throws without preserve mode' => [$plainFail, InvalidArgumentException::class],

    'preserved fail conflict action' => [static fn (): mixed => $preservedFail()['conflict_action'], 'fail'],
    'preserved fail selected ids' => [static fn (): mixed => $preservedFail()['plan']->selectedIds, [8, 9]],
    'preserved fail mutation ids' => [static fn (): mixed => $preservedFail()['plan']->mutationIds, [8, 9]],
    'preserved fail returns only prior row eight' => [static fn (): mixed => array_column($preservedFail()['returning'], 'option_id'), [8]],
    'preserved fail returning row moved key true' => [static fn (): mixed => $preservedFail()['returning'][0]['moved_key'], 1],
    'preserved fail current row eight attempted status' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'status', 'option_id')[8], 'rewrite_rules:attempt'],
    'preserved fail current row nine restored status' => [static fn (): mixed => array_column($preservedFail()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'preserved fail failed conflict row nine' => [static fn (): mixed => $preservedFail()['failed_conflict']['row_id'], 9],
    'preserved fail conflict peer attempted row eight' => [static fn (): mixed => $preservedFail()['failed_conflict']['conflicting_row_ids'], [8]],
    'preserved fail conflict key' => [static fn (): mixed => $preservedFail()['failed_conflict']['key'], '4|siteurl'],
    'preserved fail no ignored rows' => [static fn (): mixed => $preservedFail()['ignored_rows'], []],
    'preserved fail no deleted conflicts' => [static fn (): mixed => $preservedFail()['deleted_conflict_rows'], []],

    'retry update null-safe predicate selected ids' => [static fn (): mixed => $retryUpdateOnly()['plan']->selectedIds, [8, 9]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateOnly()['returning'], 'option_id'), [8, 9]],
    'retry update tuple expression true' => [static fn (): mixed => array_column($retryUpdateOnly()['returning'], 'retried_tuple'), [1, 1]],
    'retry update increments bytes' => [static fn (): mixed => array_column($retryUpdateOnly()['returning'], 'bytes'), [10, 12]],

    'plan status records rollback retry' => [static fn (): mixed => $plan()['status'], 'fail-stream-rolled-back-retried-current-source-next173'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_fail_retry_next173'],
    'plan failed ordinal zero' => [static fn (): mixed => $plan()['failed_statement_ordinal'], 0],
    'plan failed conflict row nine' => [static fn (): mixed => $plan()['failed_conflict']['row_id'], 9],
    'plan rolled back flag' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback to' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan()['released_after_retry'], true],
    'plan stops attempt before second statement' => [static fn (): mixed => count($plan()['attempt_statements']), 1],
    'plan attempted action update' => [static fn (): mixed => $plan()['attempt_statements'][0]['action'], 'update'],
    'plan attempted returning row eight' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'][0]['rows'], 'option_id'), [8]],
    'plan discarded returning row eight' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [8]],
    'plan discarded returning count one' => [static fn (): mixed => $plan()['discarded_returning_count'], 1],
    'plan failed current row eight changed before rollback' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'rewrite_rules:attempt'],
    'plan failed current row nine restored before rollback' => [static fn (): mixed => array_column($plan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'plan rollback restores row eight status' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan rollback restores row eight bytes' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'bytes', 'option_id')[8], 9],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [8, 9]],
    'plan retry update returning values' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_value'), ['rules:retry', 'plugin:retry']],
    'plan retry delete returning limited id' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3]],
    'plan retry delete tuple expression true' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'][0]['deleted_tuple'], 1],
    'plan yielded returning count three' => [static fn (): mixed => $plan()['yielded_returning_count'], 3],
    'plan attempted changes before rollback one' => [static fn (): mixed => $plan()['attempted_changes_before_rollback_to'], 1],
    'plan changes after retry release three' => [static fn (): mixed => $plan()['changes_after_retry_release'], 3],
    'plan final row ids omit only first transient' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan final row eight status retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'restored-retry'],
    'plan final row nine value retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry'],
    'plan timeout transient remains after limit one' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan changed tables wp options' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count eight' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 8],
    'plan dependency fail stream' => [static fn (): mixed => in_array('sqlite-update-or-fail-rowvalue-returning-stream-before-savepoint-rollback-next173', $plan()['dependencies'], true), true],
    'plan dependency rollback discard' => [static fn (): mixed => in_array('sqlite-rollback-to-discards-update-delete-returning-stream-next173', $plan()['dependencies'], true), true],
    'plan dependency null safe restored source' => [static fn (): mixed => in_array('sqlite-rowvalue-null-safe-retry-predicate-reads-restored-current-source-next173', $plan()['dependencies'], true), true],

    'clean plan status released clean' => [static fn (): mixed => $cleanPlan()['status'], 'released-after-clean-current-source-next173'],
    'clean plan no failed conflict' => [static fn (): mixed => $cleanPlan()['failed_conflict'], null],
    'clean plan no rollback flag' => [static fn (): mixed => $cleanPlan()['rolled_back_to_savepoint'], false],
    'clean plan no discarded returning' => [static fn (): mixed => $cleanPlan()['discarded_returning'], []],
    'clean plan attempt and retry both run' => [static fn (): mixed => [count($cleanPlan()['attempt_statements']), count($cleanPlan()['retry_statements'])], [1, 1]],
    'clean plan yielded delete row three' => [static fn (): mixed => array_column($cleanPlan()['yielded_returning'][0]['rows'], 'option_id'), [3]],
    'clean plan current row seven clean release' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'clean-release'],

    'malformed empty attempts rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173($tables, [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173($tables, [$cleanSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173($tables, [$cleanSql], [$retryDeleteSql], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173($tables, [$cleanSql], [$retryDeleteSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173(['wp_options' => ['bad']], [$cleanSql], [$retryDeleteSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next173 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
