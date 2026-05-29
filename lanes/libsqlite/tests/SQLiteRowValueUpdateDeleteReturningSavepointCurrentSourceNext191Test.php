<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows191 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => null, 'bytes' => 28, 'option_value' => 'https://network.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables191 = ['wp_options' => $rows191];
$attemptUpdate191 = "UPDATE wp_options SET status = 'attempt191', option_value = option_value || ':attempt191', keep_flag = (blog_id, option_name) IN (VALUES (1, 'siteurl'), (2, 'siteurl')), outside_flag = (blog_id, option_name) NOT IN ((1, 'home'), (3, 'rewrite_rules')), range_flag = (blog_id, bytes) BETWEEN (1, 10) AND (2, 30), same_flag = (blog_id, status) IS (1, 'live'), distinct_flag = (blog_id, status) IS DISTINCT FROM (2, NULL), unknown_flag = (blog_id, status) = (2, NULL) WHERE autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, keep_flag, outside_flag, range_flag, same_flag, distinct_flag, unknown_flag ORDER BY option_id";
$attemptDelete191 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, 'pending_theme')) RETURNING option_id, blog_id, option_name ORDER BY option_id";
$retryUpdate191 = "UPDATE wp_options SET status = 'retry191', option_value = option_value || ':retry191', keep_flag = (blog_id, option_name) IN (VALUES (1, 'siteurl'), (2, 'siteurl')), outside_flag = (blog_id, option_name) NOT IN ((1, 'home'), (3, 'rewrite_rules')), range_flag = (blog_id, bytes) BETWEEN (1, 10) AND (2, 30), same_flag = (blog_id, status) IS (1, 'live'), distinct_flag = (blog_id, status) IS DISTINCT FROM (2, NULL), unknown_flag = (blog_id, status) = (2, NULL) WHERE autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, keep_flag, outside_flag, range_flag, same_flag, distinct_flag, unknown_flag ORDER BY option_id";
$retryDelete191 = $attemptDelete191;

$attemptUpdateResult191 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate191, $tables191);
$attemptDeleteAfterUpdate191 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete191, $attemptUpdateResult191()['tables']);
$retryUpdateResult191 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate191, $tables191);
$retryDeleteAfterUpdate191 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete191, $retryUpdateResult191()['tables']);
$plan191 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext188(
    $tables191,
    [$attemptUpdate191, $attemptDelete191],
    [$retryUpdate191, $retryDelete191],
    'wp_options_rowvalue_assignment_predicates_next191',
);

$cases191 = [
    'parser keeps row value assignment expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate191)['assignments']['keep_flag'], "(blog_id, option_name) IN (VALUES (1, 'siteurl'), (2, 'siteurl'))"],
    'parser keeps not in assignment expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate191)['assignments']['outside_flag'], "(blog_id, option_name) NOT IN ((1, 'home'), (3, 'rewrite_rules'))"],
    'parser keeps between assignment expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate191)['assignments']['range_flag'], '(blog_id, bytes) BETWEEN (1, 10) AND (2, 30)'],
    'parser keeps distinct assignment expression' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate191)['assignments']['distinct_flag'], '(blog_id, status) IS DISTINCT FROM (2, NULL)'],
    'attempt update selected autoload rows' => [static fn (): mixed => $attemptUpdateResult191()['plan']->selectedIds, [1, 2, 4, 6]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'option_id'), [1, 2, 4, 6]],
    'attempt update keep flags use row value in values' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'keep_flag'), [1, 0, 1, 0]],
    'attempt update outside flags use row value not in' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'outside_flag'), [1, 0, 1, 0]],
    'attempt update range flags use row value between' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'range_flag'), [1, 1, 1, 0]],
    'attempt update same flags use row value is original status' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'same_flag'), [1, 1, 0, 0]],
    'attempt update distinct flags use row value distinct from' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'distinct_flag'), [1, 1, 0, 1]],
    'attempt update nullable equality flags preserve sqlite truth table' => [static fn (): mixed => array_column($attemptUpdateResult191()['returning'], 'unknown_flag'), [0, 0, null, 0]],
    'attempt update stores numeric predicate flags' => [static fn (): mixed => array_column($attemptUpdateResult191()['tables']['wp_options'], 'keep_flag', 'option_id')[4], 1],
    'attempt update does not store literal predicate sql' => [static fn (): mixed => is_string(array_column($attemptUpdateResult191()['tables']['wp_options'], 'keep_flag', 'option_id')[1]), false],
    'attempt update leaves non selected row without flag' => [static fn (): mixed => array_key_exists('keep_flag', array_column($attemptUpdateResult191()['tables']['wp_options'], null, 'option_id')[3]), false],
    'attempt delete after update removes transient rows' => [static fn (): mixed => array_column($attemptDeleteAfterUpdate191()['returning'], 'option_id'), [3, 5]],
    'attempt delete after update remaining ids' => [static fn (): mixed => array_column($attemptDeleteAfterUpdate191()['tables']['wp_options'], 'option_id'), [1, 2, 4, 6]],
    'retry update starts from original value' => [static fn (): mixed => $retryUpdateResult191()['returning'][0]['option_value'], 'https://old.test:retry191'],
    'retry update keep flags match attempt' => [static fn (): mixed => array_column($retryUpdateResult191()['returning'], 'keep_flag'), [1, 0, 1, 0]],
    'retry update status differs from attempt' => [static fn (): mixed => array_column($retryUpdateResult191()['returning'], 'status'), ['retry191', 'retry191', 'retry191', 'retry191']],
    'retry delete after update removes same rows' => [static fn (): mixed => array_column($retryDeleteAfterUpdate191()['returning'], 'option_id'), [3, 5]],
    'retry final ids after delete' => [static fn (): mixed => array_column($retryDeleteAfterUpdate191()['tables']['wp_options'], 'option_id'), [1, 2, 4, 6]],

    'plan status reuses savepoint retry model' => [static fn (): mixed => $plan191()['status'], 'rowvalue-empty-in-returning-rolled-back-retried-next188'],
    'plan savepoint name' => [static fn (): mixed => $plan191()['savepoint'], 'wp_options_rowvalue_assignment_predicates_next191'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan191()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback' => [static fn (): mixed => $plan191()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan191()['released_after_retry'], true],
    'plan savepoint image original ids' => [static fn (): mixed => array_column($plan191()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'plan attempt current has deleted rows removed' => [static fn (): mixed => array_column($plan191()['attempt_current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 6]],
    'plan attempt current row one attempted value' => [static fn (): mixed => array_column($plan191()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:attempt191'],
    'plan attempt current row four flag numeric' => [static fn (): mixed => array_column($plan191()['attempt_current_source_tables']['wp_options'], 'keep_flag', 'option_id')[4], 1],
    'plan rollback restores deleted rows' => [static fn (): mixed => array_column($plan191()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6]],
    'plan rollback removes attempted flag from row one' => [static fn (): mixed => array_key_exists('keep_flag', array_column($plan191()['rollback_to_current_source_tables']['wp_options'], null, 'option_id')[1]), false],
    'plan attempt statement actions' => [static fn (): mixed => array_column($plan191()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan191()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt update selected ids' => [static fn (): mixed => $plan191()['attempt_statements'][0]['selected_ids'], [1, 2, 4, 6]],
    'plan attempt delete selected ids' => [static fn (): mixed => $plan191()['attempt_statements'][1]['selected_ids'], [3, 5]],
    'plan retry update selected ids' => [static fn (): mixed => $plan191()['retry_statements'][0]['selected_ids'], [1, 2, 4, 6]],
    'plan retry delete selected ids' => [static fn (): mixed => $plan191()['retry_statements'][1]['selected_ids'], [3, 5]],
    'plan attempt returning count' => [static fn (): mixed => $plan191()['attempt_returning_count'], 6],
    'plan suppressed returning count' => [static fn (): mixed => $plan191()['suppressed_by_rollback_count'], 6],
    'plan yielded after retry count' => [static fn (): mixed => $plan191()['yielded_after_retry_count'], 6],
    'plan attempt changes before rollback' => [static fn (): mixed => $plan191()['attempt_changes_before_rollback_to'], 6],
    'plan retry changes after release' => [static fn (): mixed => $plan191()['changes_after_retry_release'], 6],
    'plan retry update yielded flags numeric' => [static fn (): mixed => array_column($plan191()['yielded_after_retry_returning'][0]['rows'], 'keep_flag'), [1, 0, 1, 0]],
    'plan retry delete yielded ids' => [static fn (): mixed => array_column($plan191()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [3, 5]],
    'plan final row one retry value' => [static fn (): mixed => array_column($plan191()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:retry191'],
    'plan final row four unknown flag null' => [static fn (): mixed => array_column($plan191()['current_source_tables']['wp_options'], 'unknown_flag', 'option_id')[4], null],
    'plan final ids omit deleted rows' => [static fn (): mixed => array_column($plan191()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 6]],
    'plan next source equals current' => [static fn (): mixed => $plan191()['next_source_tables'], $plan191()['current_source_tables']],
    'plan changed tables after retry' => [static fn (): mixed => $plan191()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan191()['row_counts']['wp_options'], 4],
    'malformed row value assignment arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET keep_flag = (blog_id, option_name) IN ((1)) WHERE option_id = 1 RETURNING option_id", $tables191), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases191 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next191 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
