<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows210 = [
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

$tables210 = ['wp_options' => $rows210];
$unique210 = [['blog_id', 'option_name']];

$attemptUpdate210 = "UPDATE wp_options SET (status, option_value, bytes) = ('ignore210', option_value || ':ignore210', bytes + 4) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('ignore210', 'pending_theme') AS pending_touched ORDER BY option_id";
$ignoreUpdate210 = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'ignore210_conflict', option_value || ':ignore210_conflict', bytes + 4) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id";
$attemptDelete210 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN ((3, 'plugin_batch')) AS plugin_delete ORDER BY option_id";
$retryUpdate210 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry210', option_value || ':retry210', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete210 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResult210 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate210, $tables210, 'option_id', $unique210);
$ignoreResult210 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreUpdate210, $attemptUpdateResult210()['tables'], 'option_id', $unique210);
$attemptDeleteResult210 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete210, $ignoreResult210()['tables'], 'option_id', $unique210);
$retryUpdateResult210 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate210, $tables210, 'option_id', $unique210);
$retryDeleteResult210 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete210, $retryUpdateResult210()['tables'], 'option_id', $unique210);
$plan210 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210(
    $tables210,
    [$attemptUpdate210, $ignoreUpdate210, $attemptDelete210],
    [$retryUpdate210, $retryDelete210],
    $unique210,
);
$customPlan210 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210(
    $tables210,
    [$attemptUpdate210, $ignoreUpdate210],
    [$retryUpdate210],
    $unique210,
    'wp_custom_ignore210',
);

$cases210 = [
    'parser ignore conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate210)['conflict_action'], 'ignore'],
    'parser attempt row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate210)['assignments']), ['status', 'option_value', 'bytes']],
    'parser ignore row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate210)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser ignore where row value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreUpdate210)['where'], "(blog_id, option_name) IN ((3, 'rewrite_rules'))"],
    'parser retry delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete210)['where'] ?? '', 'VALUES'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate210)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'direct attempt selected ids' => [static fn (): mixed => $attemptUpdateResult210()['plan']->selectedIds, [7, 9]],
    'direct attempt returning touched pending flag' => [static fn (): mixed => array_column($attemptUpdateResult210()['returning'], 'pending_touched'), [1, 0]],
    'direct attempt row seven value' => [static fn (): mixed => array_column($attemptUpdateResult210()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:ignore210'],
    'direct ignore selected ids' => [static fn (): mixed => $ignoreResult210()['plan']->selectedIds, [8]],
    'direct ignore mutation ids' => [static fn (): mixed => $ignoreResult210()['plan']->mutationIds, [8]],
    'direct ignore returning excludes conflict row eight' => [static fn (): mixed => array_column($ignoreResult210()['returning'], 'option_id'), []],
    'direct ignore returning flags not siteurl' => [static fn (): mixed => array_column($ignoreResult210()['returning'], 'not_siteurl'), []],
    'direct ignored row is row eight' => [static fn (): mixed => array_column($ignoreResult210()['ignored_rows'], 'option_id'), [8]],
    'direct ignored row has attempted siteurl conflict' => [static fn (): mixed => $ignoreResult210()['ignored_rows'][0]['option_name'], 'siteurl'],
    'direct ignore conflict key' => [static fn (): mixed => $ignoreResult210()['conflicts'][0]['key'], '1|siteurl'],
    'direct ignore conflict row id' => [static fn (): mixed => $ignoreResult210()['conflicts'][0]['row_id'], 8],
    'direct ignore keeps row eight original' => [static fn (): mixed => array_column($ignoreResult210()['tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'direct ignore keeps row seven attempted status' => [static fn (): mixed => array_column($ignoreResult210()['tables']['wp_options'], 'status', 'option_id')[7], 'ignore210'],
    'direct ignore keeps row nine attempted status' => [static fn (): mixed => array_column($ignoreResult210()['tables']['wp_options'], 'status', 'option_id')[9], 'ignore210'],
    'attempt delete selected released ids before rollback' => [static fn (): mixed => $attemptDeleteResult210()['plan']->selectedIds, [6, 9]],
    'attempt delete returning plugin flag' => [static fn (): mixed => array_column($attemptDeleteResult210()['returning'], 'plugin_delete'), [0, 1]],
    'attempt delete removes plugin row nine before rollback' => [static fn (): mixed => in_array(9, array_column($attemptDeleteResult210()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from savepoint image' => [static fn (): mixed => $retryUpdateResult210()['plan']->selectedIds, [9, 8, 7]],
    'retry update returning order desc' => [static fn (): mixed => array_column($retryUpdateResult210()['returning'], 'option_id'), [7, 8, 9]],
    'retry update row seven uses original value' => [static fn (): mixed => $retryUpdateResult210()['returning'][0]['option_value'], 'theme:retry210'],
    'retry update row eight uses original value' => [static fn (): mixed => $retryUpdateResult210()['returning'][1]['option_value'], 'rules:retry210'],
    'retry delete selected transient ids' => [static fn (): mixed => $retryDeleteResult210()['plan']->selectedIds, [3, 4]],
    'retry delete returning transient ids' => [static fn (): mixed => array_column($retryDeleteResult210()['returning'], 'option_id'), [3, 4]],

    'plan status' => [static fn (): mixed => $plan210()['status'], 'rowvalue-update-delete-returning-ignore-rollback-current-source-next210'],
    'plan savepoint' => [static fn (): mixed => $plan210()['savepoint'], 'wp_options_rowvalue_ignore_next210'],
    'plan ignore preserves statement' => [static fn (): mixed => $plan210()['ignore_conflict_preserves_statement'], true],
    'plan ignored rows do not yield returning' => [static fn (): mixed => $plan210()['ignored_rows_do_not_yield_returning'], true],
    'plan rollback discards successful ignore rows' => [static fn (): mixed => $plan210()['rollback_to_savepoint_discards_successful_ignore_statement_rows'], true],
    'plan rollback discards ignored metadata' => [static fn (): mixed => $plan210()['rollback_to_savepoint_discards_ignored_row_metadata'], true],
    'plan retry reads savepoint image' => [static fn (): mixed => $plan210()['retry_reads_savepoint_image'], true],
    'plan released after retry' => [static fn (): mixed => $plan210()['savepoint_released_after_retry'], true],
    'plan savepoint image original' => [static fn (): mixed => $plan210()['savepoint_image_tables'], $tables210],
    'plan attempt row seven touched' => [static fn (): mixed => array_column($plan210()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'ignore210'],
    'plan attempt row eight original after ignore' => [static fn (): mixed => array_column($plan210()['attempt_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan attempt row six deleted' => [static fn (): mixed => in_array(6, array_column($plan210()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row six' => [static fn (): mixed => in_array(6, array_column($plan210()['rollback_to_savepoint_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan rollback restores row seven status' => [static fn (): mixed => array_column($plan210()['rollback_to_savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'plan rollback restores row nine status' => [static fn (): mixed => array_column($plan210()['rollback_to_savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan210()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry210'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan210()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry210'],
    'plan current row nine retry' => [static fn (): mixed => array_column($plan210()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry210'],
    'plan current transient deletes gone' => [static fn (): mixed => array_intersect([3, 4], array_column($plan210()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan210()['next_source_tables'], $plan210()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan210()['attempt_statements'], 'phase'), ['attempt-before-ignore-rollback-next210', 'attempt-before-ignore-rollback-next210', 'attempt-before-ignore-rollback-next210']],
    'plan attempt actions' => [static fn (): mixed => array_column($plan210()['attempt_statements'], 'action'), ['update', 'update', 'delete']],
    'plan ignore statement conflict action' => [static fn (): mixed => $plan210()['attempt_statements'][1]['conflict_action'], 'ignore'],
    'plan attempt update source rows' => [static fn (): mixed => array_column($plan210()['attempt_statements'][0]['source_rows'], 'option_id'), [7, 9]],
    'plan ignore source rows' => [static fn (): mixed => array_column($plan210()['attempt_statements'][1]['source_rows'], 'option_id'), [8]],
    'plan ignore returning ids' => [static fn (): mixed => array_column($plan210()['attempt_statements'][1]['returning_rows'], 'option_id'), []],
    'plan ignored ids' => [static fn (): mixed => array_column($plan210()['ignored_rows_before_rollback'], 'option_id'), [8]],
    'plan ignored attempted status' => [static fn (): mixed => $plan210()['ignored_rows_before_rollback'][0]['status'], 'ignore210_conflict'],
    'plan delete source rows after ignore' => [static fn (): mixed => array_column($plan210()['attempt_statements'][2]['source_rows'], 'option_id'), [6, 9]],
    'plan suppressed returning phases' => [static fn (): mixed => array_column($plan210()['suppressed_by_rollback_returning'], 'phase'), ['attempt-before-ignore-rollback-next210', 'attempt-before-ignore-rollback-next210', 'attempt-before-ignore-rollback-next210']],
    'plan attempt yielded count' => [static fn (): mixed => $plan210()['attempt_yielded_count'], 4],
    'plan ignored row count' => [static fn (): mixed => $plan210()['ignored_row_count'], 1],
    'plan suppressed rollback count' => [static fn (): mixed => $plan210()['suppressed_by_rollback_count'], 4],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan210()['attempt_changes_before_rollback_to'], 4],
    'plan retry phases' => [static fn (): mixed => array_column($plan210()['retry_statements'], 'phase'), ['retry-after-ignore-rollback-next210', 'retry-after-ignore-rollback-next210']],
    'plan retry update source rows' => [static fn (): mixed => array_column($plan210()['retry_statements'][0]['source_rows'], 'option_id'), [7, 8, 9]],
    'plan retry delete source rows' => [static fn (): mixed => array_column($plan210()['retry_statements'][1]['source_rows'], 'option_id'), [3, 4]],
    'plan retry yielded count' => [static fn (): mixed => $plan210()['yielded_after_retry_count'], 5],
    'plan retry changes count' => [static fn (): mixed => $plan210()['changes_after_retry_release'], 5],
    'plan changed tables' => [static fn (): mixed => $plan210()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan210()['row_counts']['wp_options'], 8],
    'plan dependency ignore' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-ignore-returning-suppresses-conflict-next210', $plan210()['dependencies'], true), true],
    'plan dependency rollback' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-ignore-returning-stream-next210', $plan210()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-after-ignore-rollback-reads-savepoint-image-next210', $plan210()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan210()['dependency_closure_next210'], 'no new support component needed'), true],
    'plan non overlap note' => [static fn (): mixed => str_contains($plan210()['non_overlap_next210'], 'avoids next209/next208 OR FAIL'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan210()['savepoint'], 'wp_custom_ignore210'],
    'custom attempt count' => [static fn (): mixed => $customPlan210()['attempt_yielded_count'], 2],
    'custom retry count' => [static fn (): mixed => $customPlan210()['yielded_after_retry_count'], 3],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210($tables210, [], [$retryUpdate210], $unique210), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210($tables210, [$ignoreUpdate210], [], $unique210), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210($tables210, [$ignoreUpdate210], [$retryUpdate210], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210($tables210, [$ignoreUpdate210], [$retryUpdate210], $unique210, 'bad-name'), InvalidArgumentException::class],
    'malformed no ignored conflict rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210($tables210, [$retryUpdate210], [$retryDelete210], $unique210), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext210(['wp_options' => ['bad']], [$ignoreUpdate210], [$retryUpdate210], $unique210), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases210 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next210 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
