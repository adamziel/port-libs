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
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$stageSql = "UPDATE wp_options SET (status, option_value, bytes) = ('staged', option_value || ':staged', bytes + 2) WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$abortSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':abort', option_value || ':abort', bytes + 100) WHERE option_id IN (8, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retrySql = "UPDATE wp_options SET (status, option_value, bytes) = ('retried', option_value || ':retried', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanSql = "UPDATE wp_options SET (status, option_value) = ('clean', option_value || ':clean') WHERE option_id IN (7, 8) RETURNING option_id, status, option_value ORDER BY option_id";

$parsedAbort = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($abortSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$deleteAfterStage = static function () use ($stageSql, $deleteSql, $tables, $unique): array {
    $staged = SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($deleteSql, $staged['tables'], 'option_id', $unique);
};
$abortOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($abortSql, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170(
    $tables,
    [$stageSql, $deleteSql, $abortSql],
    [$retrySql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170(
    $tables,
    [$cleanSql, $deleteSql],
    [],
    $unique,
    'wp_options_rowvalue_abort_clean',
);

$cases = [
    'parser records abort conflict action' => [static fn (): mixed => $parsedAbort()['conflict_action'], 'abort'],
    'parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedAbort()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser abort order by desc' => [static fn (): mixed => $parsedAbort()['order_by'][0]['direction'], 'DESC'],
    'parser abort returning projection' => [static fn (): mixed => $parsedAbort()['returning'], 'option_id, blog_id, option_name, status, option_value, bytes'],
    'parser abort status expression old name' => [static fn (): mixed => $parsedAbort()['assignments']['status'], "option_name || ':abort'"],
    'stage only selected ids' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 9]],
    'stage only returning ids' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 9]],
    'stage only row seven value staged' => [static fn (): mixed => $stageOnly()['returning'][0]['option_value'], 'theme:staged'],
    'stage only row nine status staged' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'status', 'option_id')[9], 'staged'],
    'delete after stage selected transient ids' => [static fn (): mixed => $deleteAfterStage()['plan']->selectedIds, [3, 4]],
    'delete after stage returning transient ids' => [static fn (): mixed => array_column($deleteAfterStage()['returning'], 'option_id'), [3, 4]],
    'abort only throws unique conflict' => [$abortOnly, InvalidArgumentException::class],

    'plan status aborted statement preserved savepoint' => [static fn (): mixed => $plan()['status'], 'aborted-statement-preserved-savepoint'],
    'plan statement aborted flag true' => [static fn (): mixed => $plan()['statement_aborted'], true],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_abort_batch'],
    'plan not rolled back to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], false],
    'plan savepoint preserved after abort' => [static fn (): mixed => $plan()['savepoint_preserved_after_abort'], true],
    'plan released after retry' => [static fn (): mixed => $plan()['released_after_retry'], true],
    'plan abort ordinal two' => [static fn (): mixed => $plan()['aborted_statement']['ordinal'], 2],
    'plan abort reason unique conflict' => [static fn (): mixed => $plan()['aborted_statement']['reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ABORT'],
    'plan executed prior actions' => [static fn (): mixed => array_column($plan()['executed_statements'], 'action'), ['update', 'delete']],
    'plan executed prior conflict actions' => [static fn (): mixed => array_column($plan()['executed_statements'], 'conflict_action'), ['abort', 'abort']],
    'plan stage source rows original names' => [static fn (): mixed => array_column($plan()['executed_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'orphaned_cache']],
    'plan stage returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan delete source rows stale' => [static fn (): mixed => array_column($plan()['executed_statements'][1]['source_rows'], 'status'), ['stale', 'stale']],
    'plan delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan yielded count before abort' => [static fn (): mixed => $plan()['yielded_returning_count_before_abort'], 4],
    'plan changes before abort' => [static fn (): mixed => $plan()['changes_before_abort'], 4],
    'plan current source after abort omits transients' => [static fn (): mixed => array_column($plan()['current_source_after_abort_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan current source after abort keeps staged row seven' => [static fn (): mixed => array_column($plan()['current_source_after_abort_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:staged'],
    'plan current source after abort keeps staged row nine' => [static fn (): mixed => array_column($plan()['current_source_after_abort_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:staged'],
    'plan current source after abort row eight unchanged' => [static fn (): mixed => array_column($plan()['current_source_after_abort_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan aborted statement source equals current after prior statements' => [static fn (): mixed => $plan()['aborted_statement']['statement_source_tables'], $plan()['current_source_after_abort_tables']],
    'plan savepoint image still has transients' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan savepoint image row seven original' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan changed tables after abort' => [static fn (): mixed => $plan()['savepoint_changed_tables_after_abort'], ['wp_options']],
    'plan row count after abort' => [static fn (): mixed => $plan()['row_counts_after_abort']['wp_options'], 7],
    'plan retry actions' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update']],
    'plan retry selected ids include restored current rows' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [7, 8]],
    'plan retry source names' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan retry returning ids' => [static fn (): mixed => array_column($plan()['retry_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan retry returning count two' => [static fn (): mixed => $plan()['retry_returning_count'], 2],
    'plan changes after retry two' => [static fn (): mixed => $plan()['changes_after_retry'], 2],
    'plan final row ids' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven retried from staged value' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:staged:retried'],
    'plan final row eight retried from original value' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retried'],
    'plan final row nine remains staged after retry predicate misses renamed rows' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'cache:staged'],
    'plan next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan()['row_counts_after_retry']['wp_options'], 7],
    'plan dependency abort statement only' => [static fn (): mixed => in_array('sqlite-update-or-abort-rolls-back-current-rowvalue-statement-only', $plan()['dependencies'], true), true],
    'plan dependency prior streams survive' => [static fn (): mixed => in_array('sqlite-prior-update-delete-returning-streams-survive-abort-statement', $plan()['dependencies'], true), true],
    'plan dependency retry release' => [static fn (): mixed => in_array('sqlite-rowvalue-abort-savepoint-current-source-retry-release-next170', $plan()['dependencies'], true), true],

    'clean plan status released cleanly' => [static fn (): mixed => $cleanPlan()['status'], 'released-cleanly'],
    'clean plan statement aborted false' => [static fn (): mixed => $cleanPlan()['statement_aborted'], false],
    'clean plan custom savepoint' => [static fn (): mixed => $cleanPlan()['savepoint'], 'wp_options_rowvalue_abort_clean'],
    'clean plan yielded count four' => [static fn (): mixed => $cleanPlan()['yielded_returning_count_before_abort'], 4],
    'clean plan retry count zero' => [static fn (): mixed => $cleanPlan()['retry_returning_count'], 0],
    'clean plan final ids omit transients' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'clean plan row seven clean' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'clean'],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170($tables, [], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170($tables, [$stageSql], [], []), InvalidArgumentException::class],
    'malformed bad savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170($tables, [$stageSql], [], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext170(['wp_options' => ['bad']], [$stageSql], [], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next170 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
