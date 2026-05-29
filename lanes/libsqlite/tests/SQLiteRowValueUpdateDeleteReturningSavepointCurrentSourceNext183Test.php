<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows183 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables183 = ['wp_options' => $rows183];
$unique183 = [['blog_id', 'option_name']];

$outerDeleteSql183 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id";
$innerUpdateSql183 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner183', option_value || ':inner183', bytes + 3) WHERE (blog_id, option_name) BETWEEN (2, 'pending_theme') AND (3, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, status) IS (3, 'inner183') AS blog_three_inner ORDER BY option_id";
$innerDeleteSql183 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdateSql183 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry183', option_value || ':retry183', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) IS ('retry183', 'rewrite_rules') AS rewrite_retry ORDER BY option_id";
$retryDeleteSql183 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'home'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id LIMIT 1";

$outerDelete183 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerDeleteSql183, $tables183, 'option_id', $unique183);
$innerUpdate183 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql183, $outerDelete183()['tables'], 'option_id', $unique183);
$innerDelete183 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql183, $innerUpdate183()['tables'], 'option_id', $unique183);
$retryUpdate183 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql183, $outerDelete183()['tables'], 'option_id', $unique183);
$retryDelete183 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteSql183, $retryUpdate183()['tables'], 'option_id', $unique183);
$plan183 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183(
    $tables183,
    [$outerDeleteSql183],
    [$innerUpdateSql183, $innerDeleteSql183],
    [$retryUpdateSql183, $retryDeleteSql183],
    $unique183,
);
$customPlan183 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183(
    $tables183,
    [$outerDeleteSql183],
    [$innerUpdateSql183],
    [$retryUpdateSql183],
    $unique183,
    'wp_outer_custom183',
    'wp_inner_custom183',
);

$cases183 = [
    'parser outer delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerDeleteSql183)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'parser outer returning distinct expression' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($outerDeleteSql183)['returning'], 'IS DISTINCT FROM'), true],
    'parser inner update row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql183)['assignments']), ['status', 'option_value', 'bytes']],
    'parser inner update between predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql183)['where'], "(blog_id, option_name) BETWEEN (2, 'pending_theme') AND (3, 'zzzz')"],
    'parser inner delete row value in' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDeleteSql183)['where'], "(blog_id, option_name) IN ((2, 'home'), (4, 'siteurl'))"],
    'parser retry limit one' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeleteSql183)['limit'], 1],
    'outer delete selected transient ids' => [static fn (): mixed => $outerDelete183()['plan']->selectedIds, [3, 4]],
    'outer delete returning ids' => [static fn (): mixed => array_column($outerDelete183()['returning'], 'option_id'), [3, 4]],
    'outer delete returning distinct flags true' => [static fn (): mixed => array_column($outerDelete183()['returning'], 'not_siteurl'), [1, 1]],
    'outer current omits deleted transients' => [static fn (): mixed => array_column($outerDelete183()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'outer current keeps home row six' => [static fn (): mixed => array_column($outerDelete183()['tables']['wp_options'], 'option_name', 'option_id')[6], 'home'],
    'inner update selected ids after outer delete' => [static fn (): mixed => $innerUpdate183()['plan']->selectedIds, [5, 7, 8, 9]],
    'inner update returning ids' => [static fn (): mixed => array_column($innerUpdate183()['returning'], 'option_id'), [5, 7, 8, 9]],
    'inner update blog three flags' => [static fn (): mixed => array_column($innerUpdate183()['returning'], 'blog_three_inner'), [0, 0, 1, 1]],
    'inner update row eight value' => [static fn (): mixed => array_column($innerUpdate183()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:inner183'],
    'inner delete selected ids after update' => [static fn (): mixed => $innerDelete183()['plan']->selectedIds, [6, 10]],
    'inner delete returns home and siteurl' => [static fn (): mixed => array_column($innerDelete183()['returning'], 'option_id'), [6, 10]],
    'inner delete current omits row ten' => [static fn (): mixed => in_array(10, array_column($innerDelete183()['tables']['wp_options'], 'option_id'), true), false],
    'retry update starts from outer source values' => [static fn (): mixed => array_column($retryUpdate183()['returning'], 'option_value'), ['theme:retry183', 'rules:retry183', 'plugin:retry183']],
    'retry update selected ids' => [static fn (): mixed => $retryUpdate183()['plan']->selectedIds, [7, 8, 9]],
    'retry update rewrite flag only row eight' => [static fn (): mixed => array_column($retryUpdate183()['returning'], 'rewrite_retry'), [0, 1, 0]],
    'retry delete selected ids after retry' => [static fn (): mixed => $retryDelete183()['plan']->selectedIds, [6]],
    'retry delete leaves row ten because limit one' => [static fn (): mixed => in_array(10, array_column($retryDelete183()['tables']['wp_options'], 'option_id'), true), true],

    'plan status' => [static fn (): mixed => $plan183()['status'], 'outer-delete-preserved-inner-rowvalue-rollback-retry-next183'],
    'plan outer savepoint' => [static fn (): mixed => $plan183()['outer_savepoint'], 'wp_options_outer_delete_next183'],
    'plan inner savepoint' => [static fn (): mixed => $plan183()['inner_savepoint'], 'wp_options_inner_retry_next183'],
    'plan rolled back to inner' => [static fn (): mixed => $plan183()['rolled_back_to_inner_savepoint'], true],
    'plan outer delete preserved' => [static fn (): mixed => $plan183()['outer_delete_preserved_after_inner_rollback_to'], true],
    'plan inner preserved' => [static fn (): mixed => $plan183()['inner_savepoint_preserved_after_rollback_to'], true],
    'plan releases savepoints' => [static fn (): mixed => [$plan183()['inner_released_after_retry'], $plan183()['outer_released_after_inner_retry']], [true, true]],
    'plan outer image original rows' => [static fn (): mixed => $plan183()['outer_savepoint_image_tables'], $tables183],
    'plan outer current ids omit transients' => [static fn (): mixed => array_column($plan183()['outer_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan inner image equals post delete source' => [static fn (): mixed => $plan183()['inner_savepoint_image_tables'], $plan183()['outer_current_source_tables']],
    'plan inner attempt row eight changed' => [static fn (): mixed => array_column($plan183()['inner_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'inner183'],
    'plan inner attempt row ten deleted' => [static fn (): mixed => in_array(10, array_column($plan183()['inner_attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback keeps outer delete' => [static fn (): mixed => array_column($plan183()['rollback_to_inner_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan rollback restores row eight queued' => [static fn (): mixed => array_column($plan183()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan rollback restores row ten' => [static fn (): mixed => array_column($plan183()['rollback_to_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan current ids final' => [static fn (): mixed => array_column($plan183()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 7, 8, 9, 10]],
    'plan final row six deleted by retry limit' => [static fn (): mixed => in_array(6, array_column($plan183()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row ten retained' => [static fn (): mixed => array_column($plan183()['current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan183()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry183'],
    'plan final row eight value from original retry' => [static fn (): mixed => array_column($plan183()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry183'],
    'plan next source equals current' => [static fn (): mixed => $plan183()['next_source_tables'], $plan183()['current_source_tables']],
    'plan outer statement phase' => [static fn (): mixed => $plan183()['outer_delete_statements'][0]['phase'], 'outer-delete-before-inner'],
    'plan inner attempt phases' => [static fn (): mixed => array_column($plan183()['inner_attempt_statements'], 'phase'), ['inner-attempt-before-rollback', 'inner-attempt-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan183()['inner_retry_statements'], 'phase'), ['inner-retry-after-rollback', 'inner-retry-after-rollback']],
    'plan outer returning ids' => [static fn (): mixed => array_column($plan183()['outer_yielded_returning'][0]['rows'], 'option_id'), [3, 4]],
    'plan inner attempt returning stream ids' => [static fn (): mixed => [array_column($plan183()['inner_attempt_returning'][0]['rows'], 'option_id'), array_column($plan183()['inner_attempt_returning'][1]['rows'], 'option_id')], [[5, 7, 8, 9], [6, 10]]],
    'plan suppressed stream equals attempt stream' => [static fn (): mixed => $plan183()['inner_suppressed_by_rollback_returning'], $plan183()['inner_attempt_returning']],
    'plan retry returning ids' => [static fn (): mixed => [array_column($plan183()['inner_yielded_after_retry_returning'][0]['rows'], 'option_id'), array_column($plan183()['inner_yielded_after_retry_returning'][1]['rows'], 'option_id')], [[7, 8, 9], [6]]],
    'plan retry source rows restored not inner' => [static fn (): mixed => array_column($plan183()['inner_retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules', 'plugin']],
    'plan outer yielded count two' => [static fn (): mixed => $plan183()['outer_yielded_returning_count'], 2],
    'plan inner attempt count six' => [static fn (): mixed => $plan183()['inner_attempt_returning_count'], 6],
    'plan suppressed count six' => [static fn (): mixed => $plan183()['inner_suppressed_by_rollback_count'], 6],
    'plan retry count four' => [static fn (): mixed => $plan183()['inner_yielded_after_retry_count'], 4],
    'plan outer delete changes preserved' => [static fn (): mixed => $plan183()['outer_delete_changes_preserved'], 2],
    'plan inner attempted changes' => [static fn (): mixed => $plan183()['inner_attempted_changes_before_rollback_to'], 6],
    'plan inner retry changes' => [static fn (): mixed => $plan183()['inner_changes_after_retry_release'], 4],
    'plan row count seven' => [static fn (): mixed => $plan183()['row_counts']['wp_options'], 7],
    'plan changed tables' => [static fn (): mixed => $plan183()['changed_tables_after_inner_retry'], ['wp_options']],
    'plan dependency outer delete' => [static fn (): mixed => in_array('sqlite-outer-delete-returning-current-source-preserved-next183', $plan183()['dependencies'], true), true],
    'plan dependency rollback stream' => [static fn (): mixed => in_array('sqlite-inner-rowvalue-update-delete-returning-rollback-discards-stream-next183', $plan183()['dependencies'], true), true],
    'plan dependency retry source' => [static fn (): mixed => in_array('sqlite-inner-retry-reads-post-delete-current-source-next183', $plan183()['dependencies'], true), true],
    'custom plan savepoint names' => [static fn (): mixed => [$customPlan183()['outer_savepoint'], $customPlan183()['inner_savepoint']], ['wp_outer_custom183', 'wp_inner_custom183']],
    'custom plan retry count three' => [static fn (): mixed => $customPlan183()['inner_yielded_after_retry_count'], 3],
    'custom plan retains row six without retry delete' => [static fn (): mixed => in_array(6, array_column($customPlan183()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183($tables183, [], [$innerUpdateSql183], [$retryUpdateSql183], $unique183), InvalidArgumentException::class],
    'malformed empty inner attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183($tables183, [$outerDeleteSql183], [], [$retryUpdateSql183], $unique183), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183($tables183, [$outerDeleteSql183], [$innerUpdateSql183], [], $unique183), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183($tables183, [$outerDeleteSql183], [$innerUpdateSql183], [$retryUpdateSql183], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext183(['wp_options' => ['bad']], [$outerDeleteSql183], [$innerUpdateSql183], [$retryUpdateSql183], $unique183), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases183 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next183 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
