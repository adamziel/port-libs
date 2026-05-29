<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows218 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables218 = ['wp_options' => $rows218];
$unique218 = [['blog_id', 'option_name']];

$savepointUpdate218 = "UPDATE wp_options SET (status, option_value, bytes) = ('saved218', option_value || ':saved218', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IS ('saved218', 'pending_theme') AS saved_pending ORDER BY option_id";
$savepointDelete218 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$attemptUpdate218 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt218', option_value || ':attempt218', bytes + 5) WHERE (status, option_name) IN (('saved218', 'pending_theme'), ('saved218', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) AS attempted_tuple ORDER BY option_id DESC";
$attemptDelete218 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id";
$retryUpdate218 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry218', option_value || ':retry218', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry218', 'rewrite_rules') AS retry_rewrite ORDER BY option_id";
$retryDelete218 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((4, 'home')) AS kept_network ORDER BY option_id";

$savepointUpdateResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointUpdate218, $tables218, 'option_id', $unique218);
$savepointDeleteResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointDelete218, $savepointUpdateResult218()['tables'], 'option_id', $unique218);
$attemptUpdateResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate218, $savepointDeleteResult218()['tables'], 'option_id', $unique218);
$attemptDeleteResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete218, $attemptUpdateResult218()['tables'], 'option_id', $unique218);
$retryUpdateResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate218, $tables218, 'option_id', $unique218);
$retryDeleteResult218 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete218, $retryUpdateResult218()['tables'], 'option_id', $unique218);
$plan218 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource(
    $tables218,
    [$savepointUpdate218, $savepointDelete218],
    [$attemptUpdate218, $attemptDelete218],
    [$retryUpdate218, $retryDelete218],
    $unique218,
);
$customPlan218 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource(
    $tables218,
    [$savepointUpdate218],
    [$attemptUpdate218],
    [$retryUpdate218],
    $unique218,
    'wp_custom_next218',
);

$cases218 = [
    'parser savepoint update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($savepointUpdate218)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser attempt update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate218)['where'], "(status, option_name) IN (('saved218', 'pending_theme'), ('saved218', 'rewrite_rules'))"],
    'parser attempt delete values where' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDelete218)['where'] ?? '', 'VALUES'), true],
    'parser retry delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete218)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (4, 'home'))"],
    'savepoint update selected ids' => [static fn (): mixed => $savepointUpdateResult218()['plan']->selectedIds, [6, 7]],
    'savepoint update returning ids' => [static fn (): mixed => array_column($savepointUpdateResult218()['returning'], 'option_id'), [6, 7]],
    'savepoint update predicate flag' => [static fn (): mixed => array_column($savepointUpdateResult218()['returning'], 'saved_pending'), [1, 0]],
    'savepoint delete selected id' => [static fn (): mixed => $savepointDeleteResult218()['plan']->selectedIds, [3]],
    'savepoint delete removes transient from attempt source' => [static fn (): mixed => in_array(3, array_column($savepointDeleteResult218()['tables']['wp_options'], 'option_id'), true), false],
    'attempt update selected ids after savepoint' => [static fn (): mixed => $attemptUpdateResult218()['plan']->selectedIds, [7, 6]],
    'attempt update returning order remains table order' => [static fn (): mixed => array_column($attemptUpdateResult218()['returning'], 'option_id'), [6, 7]],
    'attempt update row six chained value' => [static fn (): mixed => array_column($attemptUpdateResult218()['returning'], 'option_value', 'option_id')[6], 'theme:saved218:attempt218'],
    'attempt update tuple flags' => [static fn (): mixed => array_column($attemptUpdateResult218()['returning'], 'attempted_tuple'), [1, 1]],
    'attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult218()['plan']->selectedIds, [4, 8]],
    'attempt delete returning ids' => [static fn (): mixed => array_column($attemptDeleteResult218()['returning'], 'option_id'), [4, 8]],
    'attempt delete row eight suppressed later' => [static fn (): mixed => in_array(8, array_column($attemptDeleteResult218()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from original savepoint image' => [static fn (): mixed => $retryUpdateResult218()['plan']->selectedIds, [6, 7]],
    'retry update row six does not include saved attempt text' => [static fn (): mixed => array_column($retryUpdateResult218()['returning'], 'option_value', 'option_id')[6], 'theme:retry218'],
    'retry update rewrite flag' => [static fn (): mixed => array_column($retryUpdateResult218()['returning'], 'retry_rewrite'), [0, 1]],
    'retry delete selected ids includes restored transient' => [static fn (): mixed => $retryDeleteResult218()['plan']->selectedIds, [3, 10]],
    'retry delete returning flags' => [static fn (): mixed => array_column($retryDeleteResult218()['returning'], 'kept_network'), [1, 0]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult218()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],

    'plan status' => [static fn (): mixed => $plan218()['status'], 'rowvalue-update-delete-returning-rollback-to-current-source-next218'],
    'plan savepoint' => [static fn (): mixed => $plan218()['savepoint'], 'wp_options_rowvalue_rollback_to_next218'],
    'plan rollback true' => [static fn (): mixed => $plan218()['rollback_to_savepoint_next218'], true],
    'plan savepoint remains active' => [static fn (): mixed => $plan218()['savepoint_remains_active_next218'], true],
    'plan attempted suppressed' => [static fn (): mixed => $plan218()['attempted_returning_suppressed_by_rollback_next218'], true],
    'plan retry reads image' => [static fn (): mixed => $plan218()['retry_reads_savepoint_image_next218'], true],
    'plan savepoint image original ids' => [static fn (): mixed => array_column($plan218()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
    'plan attempt source deleted row three' => [static fn (): mixed => in_array(3, array_column($plan218()['attempt_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt current deleted row eight' => [static fn (): mixed => in_array(8, array_column($plan218()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores original image' => [static fn (): mixed => $plan218()['rollback_current_source_tables'], $plan218()['savepoint_image_tables']],
    'plan retry source rows first are original' => [static fn (): mixed => array_column($plan218()['retry_statements'][0]['source_rows'], 'option_value', 'option_id'), [6 => 'theme', 7 => 'rules']],
    'plan retry source rows second include original transient and home' => [static fn (): mixed => array_column($plan218()['retry_statements'][1]['source_rows'], 'option_id'), [3, 10]],
    'plan current row six retry only' => [static fn (): mixed => array_column($plan218()['current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'theme:retry218'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plan218()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'rules:retry218'],
    'plan current row eight restored orphaned cache' => [static fn (): mixed => array_column($plan218()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned'],
    'plan current row three deleted by retry' => [static fn (): mixed => in_array(3, array_column($plan218()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current row ten deleted by retry' => [static fn (): mixed => in_array(10, array_column($plan218()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan218()['next_source_tables'], $plan218()['current_source_tables']],
    'plan savepoint statement phases' => [static fn (): mixed => array_column($plan218()['savepoint_statements'], 'phase'), ['savepoint-before-rollback-to-next218', 'savepoint-before-rollback-to-next218']],
    'plan attempted statement phases' => [static fn (): mixed => array_column($plan218()['attempted_statements'], 'phase'), ['attempt-before-rollback-to-next218', 'attempt-before-rollback-to-next218']],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan218()['retry_statements'], 'phase'), ['retry-after-rollback-to-next218', 'retry-after-rollback-to-next218']],
    'plan savepoint returning count' => [static fn (): mixed => $plan218()['savepoint_returning_count'], 3],
    'plan suppressed attempted returning count' => [static fn (): mixed => $plan218()['suppressed_attempted_returning_count'], 4],
    'plan retry returning count' => [static fn (): mixed => $plan218()['retry_returning_count'], 4],
    'plan attempted change count' => [static fn (): mixed => $plan218()['attempted_change_count'], 4],
    'plan retry change count' => [static fn (): mixed => $plan218()['retry_change_count'], 4],
    'plan changed tables after retry' => [static fn (): mixed => $plan218()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan218()['row_counts']['wp_options'], 8],
    'plan receipt suppressed count' => [static fn (): mixed => $plan218()['rollback_receipt_next218']['suppressed_returning_count'], 4],
    'plan receipt retry count' => [static fn (): mixed => $plan218()['rollback_receipt_next218']['retry_statement_count'], 2],
    'plan dependency rollback image' => [static fn (): mixed => in_array('sqlite-rowvalue-rollback-to-restores-savepoint-image-next218', $plan218()['dependencies'], true), true],
    'plan dependency suppressed returning' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-suppressed-after-rollback-to-next218', $plan218()['dependencies'], true), true],
    'plan non overlap mentions next211' => [static fn (): mixed => str_contains($plan218()['non_overlap_next218'], 'next211 OR IGNORE'), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan218()['savepoint'], 'wp_custom_next218'],
    'custom plan suppressed count' => [static fn (): mixed => $customPlan218()['suppressed_attempted_returning_count'], 2],
    'custom plan retry count' => [static fn (): mixed => $customPlan218()['retry_returning_count'], 2],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource($tables218, [], [$attemptUpdate218], [$retryUpdate218], $unique218), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource($tables218, [$savepointUpdate218], [], [$retryUpdate218], $unique218), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource($tables218, [$savepointUpdate218], [$attemptUpdate218], [], $unique218), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource($tables218, [$savepointUpdate218], [$attemptUpdate218], [$retryUpdate218], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource($tables218, [$savepointUpdate218], [$attemptUpdate218], [$retryUpdate218], $unique218, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackToSavepointCurrentSource(['wp_options' => ['bad']], [$savepointUpdate218], [$attemptUpdate218], [$retryUpdate218], $unique218), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases218 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next218 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
