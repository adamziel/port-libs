<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows200 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables200 = ['wp_options' => $rows200];
$unique200 = [['blog_id', 'option_name']];
$outerSql200 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer200', option_value || ':outer200', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$savepointUpdateSql200 = "UPDATE wp_options SET (status, option_value, bytes) = ('saved200', option_value || ':saved200', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('saved200', 'pending_theme') AS saved_pending ORDER BY option_id";
$savepointDeleteSql200 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$abortSql200 = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'abort200', option_value || ':abort200', bytes + 20) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdateSql200 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry200', option_value || ':retry200', bytes + 5) WHERE (status, option_name) IN (('saved200', 'pending_theme'), ('saved200', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDeleteSql200 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'home')) AS dropped_network_home ORDER BY option_id";

$outerResult200 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql200, $tables200, 'option_id', $unique200);
$savepointUpdateResult200 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointUpdateSql200, $outerResult200()['tables'], 'option_id', $unique200);
$savepointDeleteResult200 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointDeleteSql200, $savepointUpdateResult200()['tables'], 'option_id', $unique200);
$retryUpdateResult200 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql200, $savepointDeleteResult200()['tables'], 'option_id', $unique200);
$retryDeleteResult200 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteSql200, $retryUpdateResult200()['tables'], 'option_id', $unique200);
$plan200 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200(
    $tables200,
    [$outerSql200],
    [$savepointUpdateSql200, $savepointDeleteSql200],
    [$abortSql200],
    [$retryUpdateSql200, $retryDeleteSql200],
    $unique200,
);
$customPlan200 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200(
    $tables200,
    [$outerSql200],
    [$savepointUpdateSql200],
    [$abortSql200],
    [$retryUpdateSql200],
    $unique200,
    'wp_custom_next200',
);

$cases200 = [
    'parser abort conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($abortSql200)['conflict_action'], 'abort'],
    'parser savepoint update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($savepointUpdateSql200)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry update status row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdateSql200)['where'], "(status, option_name) IN (('saved200', 'pending_theme'), ('saved200', 'rewrite_rules'))"],
    'parser retry delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDeleteSql200)['where'] ?? '', 'VALUES'), true],
    'outer selected ids' => [static fn (): mixed => $outerResult200()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult200()['returning'], 'option_id'), [1, 2]],
    'outer row one status' => [static fn (): mixed => array_column($outerResult200()['tables']['wp_options'], 'status', 'option_id')[1], 'outer200'],
    'savepoint update selected ids' => [static fn (): mixed => $savepointUpdateResult200()['plan']->selectedIds, [7, 8]],
    'savepoint update returning ids' => [static fn (): mixed => array_column($savepointUpdateResult200()['returning'], 'option_id'), [7, 8]],
    'savepoint update predicate flag' => [static fn (): mixed => array_column($savepointUpdateResult200()['returning'], 'saved_pending'), [1, 0]],
    'savepoint delete selected id' => [static fn (): mixed => $savepointDeleteResult200()['plan']->selectedIds, [3]],
    'savepoint delete removes feed transient' => [static fn (): mixed => in_array(3, array_column($savepointDeleteResult200()['tables']['wp_options'], 'option_id'), true), false],
    'abort direct execution throws and rolls back statement' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($abortSql200, $savepointDeleteResult200()['tables'], 'option_id', $unique200), InvalidArgumentException::class],
    'retry update selected ids from saved current source' => [static fn (): mixed => $retryUpdateResult200()['plan']->selectedIds, [8, 7]],
    'retry update returning stays in current source order' => [static fn (): mixed => array_column($retryUpdateResult200()['returning'], 'option_id'), [7, 8]],
    'retry update row seven value includes saved then retry' => [static fn (): mixed => array_column($retryUpdateResult200()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:saved200:retry200'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult200()['plan']->selectedIds, [4, 11]],
    'retry delete network home flag' => [static fn (): mixed => array_column($retryDeleteResult200()['returning'], 'dropped_network_home'), [0, 1]],

    'plan status' => [static fn (): mixed => $plan200()['status'], 'rowvalue-update-delete-returning-abort-statement-current-source-next200'],
    'plan savepoint' => [static fn (): mixed => $plan200()['savepoint'], 'wp_options_rowvalue_abort_statement_next200'],
    'plan statement aborted' => [static fn (): mixed => $plan200()['statement_aborted'], true],
    'plan not rolled back to savepoint' => [static fn (): mixed => $plan200()['rolled_back_to_savepoint'], false],
    'plan savepoint preserved after abort' => [static fn (): mixed => $plan200()['savepoint_preserved_after_abort'], true],
    'plan savepoint released after retry' => [static fn (): mixed => $plan200()['savepoint_released_after_retry'], true],
    'plan abort ordinal' => [static fn (): mixed => $plan200()['abort_statement_ordinal'], 0],
    'plan abort reason contains unique' => [static fn (): mixed => str_contains($plan200()['abort_reason'] ?? '', 'unique constraint failed'), true],
    'plan initial tables' => [static fn (): mixed => $plan200()['initial_tables'], $tables200],
    'plan outer current row one' => [static fn (): mixed => array_column($plan200()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer200'],
    'plan savepoint image equals outer current' => [static fn (): mixed => $plan200()['savepoint_image_tables'], $plan200()['outer_current_source_tables']],
    'plan savepoint current row seven saved' => [static fn (): mixed => array_column($plan200()['savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'saved200'],
    'plan savepoint current row three deleted' => [static fn (): mixed => in_array(3, array_column($plan200()['savepoint_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan abort current equals savepoint current' => [static fn (): mixed => $plan200()['abort_current_source_tables'], $plan200()['savepoint_current_source_tables']],
    'plan abort source rows' => [static fn (): mixed => array_column($plan200()['abort_statements'][0]['source_rows'], 'option_id'), [8, 9]],
    'plan abort selected ids' => [static fn (): mixed => $plan200()['abort_statements'][0]['selected_ids'], [8, 9]],
    'plan abort mutation ids' => [static fn (): mixed => $plan200()['abort_statements'][0]['mutation_ids'], [8, 9]],
    'plan abort returning suppressed empty' => [static fn (): mixed => $plan200()['abort_statements'][0]['returning_rows'], []],
    'plan abort failed message surfaced' => [static fn (): mixed => str_contains($plan200()['abort_statements'][0]['failed_conflict']['message'] ?? '', 'OR ABORT'), true],
    'plan retry update source sees saved statuses' => [static fn (): mixed => array_column($plan200()['retry_statements'][0]['source_rows'], 'status'), ['saved200', 'saved200']],
    'plan retry delete source rows include timeout and home' => [static fn (): mixed => array_column($plan200()['retry_statements'][1]['source_rows'], 'option_id'), [4, 11]],
    'plan outer yielding count' => [static fn (): mixed => $plan200()['outer_yielded_returning_count'], 2],
    'plan savepoint yielding count' => [static fn (): mixed => $plan200()['savepoint_yielded_returning_count'], 3],
    'plan abort suppressed count' => [static fn (): mixed => $plan200()['abort_suppressed_returning_count'], 0],
    'plan retry yielded count' => [static fn (): mixed => $plan200()['yielded_after_retry_count'], 4],
    'plan changes preserved before abort' => [static fn (): mixed => $plan200()['changes_preserved_before_abort'], 5],
    'plan changes after retry' => [static fn (): mixed => $plan200()['changes_after_retry'], 4],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan200()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry200'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan200()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry200'],
    'plan current row nine not aborted' => [static fn (): mixed => array_column($plan200()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'plan current row ten still siteurl' => [static fn (): mixed => array_column($plan200()['current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan current row eleven deleted' => [static fn (): mixed => in_array(11, array_column($plan200()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan200()['next_source_tables'], $plan200()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan200()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan200()['row_counts']['wp_options'], 8],
    'plan dependency abort statement' => [static fn (): mixed => in_array('sqlite-update-or-abort-rowvalue-returning-discards-failed-statement-next200', $plan200()['dependencies'], true), true],
    'plan dependency current source survives' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-survives-abort-statement-next200', $plan200()['dependencies'], true), true],
    'plan dependency retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-reads-post-abort-current-source-next200', $plan200()['dependencies'], true), true],
    'custom plan savepoint' => [static fn (): mixed => $customPlan200()['savepoint'], 'wp_custom_next200'],
    'custom plan retry count' => [static fn (): mixed => $customPlan200()['yielded_after_retry_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [], [$savepointUpdateSql200], [$abortSql200], [$retryUpdateSql200], $unique200), InvalidArgumentException::class],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [$outerSql200], [], [$abortSql200], [$retryUpdateSql200], $unique200), InvalidArgumentException::class],
    'malformed empty abort rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [$outerSql200], [$savepointUpdateSql200], [], [$retryUpdateSql200], $unique200), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [$outerSql200], [$savepointUpdateSql200], [$abortSql200], [], $unique200), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [$outerSql200], [$savepointUpdateSql200], [$abortSql200], [$retryUpdateSql200], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200($tables200, [$outerSql200], [$savepointUpdateSql200], [$abortSql200], [$retryUpdateSql200], $unique200, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200(['wp_options' => ['bad']], [$outerSql200], [$savepointUpdateSql200], [$abortSql200], [$retryUpdateSql200], $unique200), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases200 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next200 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
