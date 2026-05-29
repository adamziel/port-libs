<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan;
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

$stageSql = "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':staged', 'staged', option_value || ':staged', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('staged', 'pending_theme:staged') AS staged_pending ORDER BY option_id";
$abortSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':abort', option_value || ':abort', bytes + 100) WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$releaseSql = "UPDATE wp_options SET (blog_id, option_name, status) = (blog_id, option_name || ':ok', 'ok') WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$parsedStage = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($stageSql);
$parsedAbort = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($abortSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$abortOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($abortSql, $tables, 'option_id', $unique);
$abortPlan = static fn (): array => SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute($tables, [$stageSql, $abortSql, $cleanupSql], $unique);
$releasePlan = static fn (): array => SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute($tables, [$releaseSql, $cleanupSql], $unique);

$cases = [
    'stage parser default abort conflict action' => [static fn (): mixed => $parsedStage()['conflict_action'], 'abort'],
    'stage parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedStage()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'stage parser returning expression retained' => [static fn (): mixed => str_contains($parsedStage()['returning'], 'staged_pending'), true],
    'stage parser where ids' => [static fn (): mixed => $parsedStage()['where'], 'option_id IN (7, 8)'],
    'abort parser explicit abort conflict action' => [static fn (): mixed => $parsedAbort()['conflict_action'], 'abort'],
    'abort parser order desc' => [static fn (): mixed => $parsedAbort()['order_by'][0]['direction'], 'DESC'],
    'abort parser selected expression old option name' => [static fn (): mixed => $parsedAbort()['assignments']['status'], "option_name || ':abort'"],
    'abort parser returning projection' => [static fn (): mixed => $parsedAbort()['returning'], 'option_id, blog_id, option_name, status, option_value, bytes'],

    'stage only selected two rows' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 8]],
    'stage only mutation ids two rows' => [static fn (): mixed => $stageOnly()['plan']->mutationIds, [7, 8]],
    'stage only returns row seven and eight' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 8]],
    'stage only row seven name staged' => [static fn (): mixed => $stageOnly()['returning'][0]['option_name'], 'pending_theme:staged'],
    'stage only row seven tuple returning true' => [static fn (): mixed => $stageOnly()['returning'][0]['staged_pending'], 1],
    'stage only row eight value uses source' => [static fn (): mixed => $stageOnly()['returning'][1]['option_value'], 'cache:staged'],
    'stage only rows changed in current table' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'stage only has no conflicts' => [static fn (): mixed => $stageOnly()['conflicts'], []],

    'abort only throws unique constraint' => [$abortOnly, InvalidArgumentException::class],
    'abort plan status preserves savepoint' => [static fn (): mixed => $abortPlan()['status'], 'statement-aborted-savepoint-active'],
    'abort plan aborted true' => [static fn (): mixed => $abortPlan()['aborted'], true],
    'abort plan transaction not rolled back' => [static fn (): mixed => $abortPlan()['transaction_rolled_back'], false],
    'abort plan savepoint preserved' => [static fn (): mixed => $abortPlan()['savepoint_preserved'], true],
    'abort plan failed statement ordinal one' => [static fn (): mixed => $abortPlan()['abort_statement_ordinal'], 1],
    'abort plan reason records OR ABORT' => [static fn (): mixed => $abortPlan()['abort_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ABORT'],
    'abort plan cleanup statement skipped' => [static fn (): mixed => count($abortPlan()['executed_statements']), 1],
    'abort plan executed first statement conflict action abort' => [static fn (): mixed => $abortPlan()['executed_statements'][0]['conflict_action'], 'abort'],
    'abort plan yielded prior returning only' => [static fn (): mixed => count($abortPlan()['yielded_returning']), 1],
    'abort plan yielded row ids seven eight' => [static fn (): mixed => array_column($abortPlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'abort plan current source keeps row seven staged' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:staged'],
    'abort plan current source keeps row eight staged' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'cache:staged'],
    'abort plan current source restores abort row nine' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[9], 'rewrite_rules'],
    'abort plan current source restores abort row six' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'live'],
    'abort plan original row one remains siteurl' => [static fn (): mixed => array_column($abortPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'abort plan savepoint image preserves row seven original' => [static fn (): mixed => array_column($abortPlan()['savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'abort plan aborted statement image equals current source' => [static fn (): mixed => $abortPlan()['aborted_statement_image_tables'], $abortPlan()['current_source_tables']],
    'abort plan next source equals last successful current source' => [static fn (): mixed => $abortPlan()['next_source_tables'], $abortPlan()['current_source_tables']],
    'abort plan changes count prior returning only' => [static fn (): mixed => $abortPlan()['changes'], 2],
    'abort plan attempted changes prior returning only' => [static fn (): mixed => $abortPlan()['attempted_changes'], 2],
    'abort plan row count unchanged' => [static fn (): mixed => $abortPlan()['row_counts']['wp_options'], 9],
    'abort plan ignored rows empty' => [static fn (): mixed => $abortPlan()['ignored_rows'], []],
    'abort plan deleted conflict rows empty' => [static fn (): mixed => $abortPlan()['deleted_conflict_rows'], []],
    'abort plan conflicts empty because abort statement rolled back' => [static fn (): mixed => $abortPlan()['conflicts'], []],
    'abort plan dependency marks abort statement rollback' => [static fn (): mixed => in_array('sqlite-update-or-abort-statement-rollback', $abortPlan()['dependencies'], true), true],
    'abort plan dependency marks prior returning yields' => [static fn (): mixed => in_array('sqlite-savepoint-preserves-prior-returning-yields', $abortPlan()['dependencies'], true), true],

    'release plan status released' => [static fn (): mixed => $releasePlan()['status'], 'released'],
    'release plan not aborted' => [static fn (): mixed => $releasePlan()['aborted'], false],
    'release plan does not preserve savepoint' => [static fn (): mixed => $releasePlan()['savepoint_preserved'], false],
    'release plan executes update and delete' => [static fn (): mixed => count($releasePlan()['executed_statements']), 2],
    'release plan yielding streams two' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'], 'action'), ['update', 'delete']],
    'release plan update row ids' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'release plan cleanup row ids' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'release plan final row ids omit deleted transient rows' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'release plan row seven ok name' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:ok'],
    'release plan changes include two updates and two deletes' => [static fn (): mixed => $releasePlan()['changes'], 4],
    'release plan row count after cleanup' => [static fn (): mixed => $releasePlan()['row_counts']['wp_options'], 7],
    'release plan current source equals next source' => [static fn (): mixed => $releasePlan()['current_source_tables'], $releasePlan()['next_source_tables']],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute($tables, [$stageSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$stageSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue abort returning savepoint current source next140 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
