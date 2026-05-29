<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows190 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 9, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 11, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => null, 'bytes' => 6, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 18, 'option_value' => 'theme'],
];

$tables190 = ['wp_options' => $rows190];
$unique190 = [['blog_id', 'option_name']];

$releaseUpdate190 = "UPDATE wp_options SET (status, option_value, bytes) = ('released190', option_value || ':released190', bytes + 5) WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (1, 'home'), (2, 'siteurl'), (2, 'home')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) NOT IN ((1, 'siteurl'), (1, 'home')) AS outside_main ORDER BY option_id";
$releaseDelete190 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'zzzz') AND autoload = 'no' RETURNING option_id, blog_id, option_name, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'zzzz') AS outside_cleanup ORDER BY option_id";
$rollbackUpdate190 = "UPDATE wp_options SET (status, option_value, bytes) = ('speculative190', option_value || ':speculative190', bytes + 7) WHERE (blog_id, option_name) NOT IN ((3, 'rewrite_rules'), (4, 'theme_mods')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) NOT IN ((3, 'rewrite_rules'), (4, 'theme_mods')) AS will_discard ORDER BY option_id";
$rollbackDelete190 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT BETWEEN (1, 'home') AND (3, 'zzzz') AND autoload = 'no' RETURNING option_id, blog_id, option_name, (blog_id, option_name) NOT BETWEEN (1, 'home') AND (3, 'zzzz') AS will_discard ORDER BY option_id";
$retryUpdate190 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry190', option_value || ':retry190', bytes + 3) WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (2, 'siteurl'), (3, 'rewrite_rules')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) NOT IN ((3, 'rewrite_rules')) AS retry_kept ORDER BY option_id";
$retryDelete190 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (1, 'zzzz') AND autoload = 'no' RETURNING option_id, blog_id, option_name, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (1, 'zzzz') AS retry_delete ORDER BY option_id";

$releaseUpdateResult190 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseUpdate190, $tables190, 'option_id', $unique190);
$releaseDeleteResult190 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseDelete190, $releaseUpdateResult190()['tables'], 'option_id', $unique190);
$rollbackUpdateResult190 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($rollbackUpdate190, $releaseDeleteResult190()['tables'], 'option_id', $unique190);
$rollbackDeleteResult190 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($rollbackDelete190, $rollbackUpdateResult190()['tables'], 'option_id', $unique190);
$retryUpdateResult190 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate190, $releaseDeleteResult190()['tables'], 'option_id', $unique190);
$plan190 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint(
    $tables190,
    [$releaseUpdate190, $releaseDelete190],
    [$rollbackUpdate190, $rollbackDelete190],
    [$retryUpdate190, $retryDelete190],
    $unique190,
);

$cases190 = [
    'parser release update where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($releaseUpdate190)['where'], "(blog_id, option_name) NOT IN ((1, 'siteurl'), (1, 'home'), (2, 'siteurl'), (2, 'home')) AND autoload = 'yes'"],
    'parser release delete where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($releaseDelete190)['where'], "(blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'zzzz') AND autoload = 'no'"],
    'parser retry update assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($retryUpdate190)['assignments']), ['status', 'option_value', 'bytes']],
    'release update selected ids' => [static fn (): mixed => $releaseUpdateResult190()['plan']->selectedIds, [8, 10]],
    'release update returning ids' => [static fn (): mixed => array_column($releaseUpdateResult190()['returning'], 'option_id'), [8, 10]],
    'release update returning not in flags' => [static fn (): mixed => array_column($releaseUpdateResult190()['returning'], 'outside_main'), [1, 1]],
    'release update row eight status' => [static fn (): mixed => array_column($releaseUpdateResult190()['tables']['wp_options'], 'status', 'option_id')[8], 'released190'],
    'release update row ten value' => [static fn (): mixed => array_column($releaseUpdateResult190()['tables']['wp_options'], 'option_value', 'option_id')[10], 'theme:released190'],
    'release delete selected only blog three orphan' => [static fn (): mixed => $releaseDeleteResult190()['plan']->selectedIds, [9]],
    'release delete returning outside flag' => [static fn (): mixed => $releaseDeleteResult190()['returning'][0]['outside_cleanup'], 1],
    'release delete removes row nine' => [static fn (): mixed => array_column($releaseDeleteResult190()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 10]],
    'rollback update selected ids before discard' => [static fn (): mixed => $rollbackUpdateResult190()['plan']->selectedIds, [1, 2, 5, 6]],
    'rollback update returning discard flags' => [static fn (): mixed => array_column($rollbackUpdateResult190()['returning'], 'will_discard'), [1, 1, 1, 1]],
    'rollback delete selects local transients before rollback' => [static fn (): mixed => $rollbackDeleteResult190()['plan']->selectedIds, [3, 4]],
    'retry update selected home and theme rows' => [static fn (): mixed => $retryUpdateResult190()['plan']->selectedIds, [2, 6, 10]],
    'retry update starts from release value for theme' => [static fn (): mixed => $retryUpdateResult190()['returning'][2]['option_value'], 'theme:released190:retry190'],

    'plan status' => [static fn (): mixed => $plan190()['status'], 'rowvalue-negated-predicate-release-rollback-retry-next190'],
    'plan release savepoint name' => [static fn (): mixed => $plan190()['release_savepoint'], 'wp_options_rowvalue_release_next190'],
    'plan rollback savepoint name' => [static fn (): mixed => $plan190()['rollback_savepoint'], 'wp_options_rowvalue_rollback_next190'],
    'plan release savepoint released' => [static fn (): mixed => $plan190()['release_savepoint_released'], true],
    'plan rollback to second savepoint' => [static fn (): mixed => $plan190()['rollback_to_second_savepoint'], true],
    'plan rollback savepoint preserved' => [static fn (): mixed => $plan190()['rollback_savepoint_preserved_after_rollback_to'], true],
    'plan retry released' => [static fn (): mixed => $plan190()['retry_released_after_rollback'], true],
    'plan transaction image ids' => [static fn (): mixed => array_column($plan190()['transaction_image_tables']['wp_options'], 'option_id'), range(1, 10)],
    'plan release current ids' => [static fn (): mixed => array_column($plan190()['release_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 10]],
    'plan release row eight status' => [static fn (): mixed => array_column($plan190()['release_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'released190'],
    'plan release row ten bytes' => [static fn (): mixed => array_column($plan190()['release_current_source_tables']['wp_options'], 'bytes', 'option_id')[10], 23],
    'plan rollback image equals release current' => [static fn (): mixed => $plan190()['rollback_image_tables'], $plan190()['release_current_source_tables']],
    'plan speculative row one changed' => [static fn (): mixed => array_column($plan190()['speculative_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'speculative190'],
    'plan speculative row six changed' => [static fn (): mixed => array_column($plan190()['speculative_current_source_tables']['wp_options'], 'option_value', 'option_id')[6], 'https://network-home.test:speculative190'],
    'plan rollback to restores row one' => [static fn (): mixed => array_column($plan190()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan rollback to keeps released row ten' => [static fn (): mixed => array_column($plan190()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[10], 'released190'],
    'plan release statement phases' => [static fn (): mixed => array_column($plan190()['release_statements'], 'phase'), ['release-savepoint', 'release-savepoint']],
    'plan rollback statement phases' => [static fn (): mixed => array_column($plan190()['rollback_statements'], 'phase'), ['rollback-savepoint-speculative', 'rollback-savepoint-speculative']],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan190()['retry_statements'], 'phase'), ['retry-after-rollback', 'retry-after-rollback']],
    'plan release statement actions' => [static fn (): mixed => array_column($plan190()['release_statements'], 'action'), ['update', 'delete']],
    'plan rollback statement actions' => [static fn (): mixed => array_column($plan190()['rollback_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan190()['retry_statements'], 'action'), ['update', 'delete']],
    'plan release update selected ids' => [static fn (): mixed => $plan190()['release_statements'][0]['selected_ids'], [8, 10]],
    'plan release delete selected ids' => [static fn (): mixed => $plan190()['release_statements'][1]['selected_ids'], [9]],
    'plan rollback update selected ids' => [static fn (): mixed => $plan190()['rollback_statements'][0]['selected_ids'], [1, 2, 5, 6]],
    'plan rollback delete selected ids' => [static fn (): mixed => $plan190()['rollback_statements'][1]['selected_ids'], [3, 4]],
    'plan retry update selected ids' => [static fn (): mixed => $plan190()['retry_statements'][0]['selected_ids'], [2, 6, 10]],
    'plan retry delete selected ids' => [static fn (): mixed => $plan190()['retry_statements'][1]['selected_ids'], [7]],
    'plan release returning count' => [static fn (): mixed => $plan190()['yielded_release_count'], 3],
    'plan suppressed returning count' => [static fn (): mixed => $plan190()['suppressed_by_rollback_count'], 6],
    'plan retry returning count' => [static fn (): mixed => $plan190()['yielded_after_retry_count'], 4],
    'plan release changes' => [static fn (): mixed => $plan190()['release_changes'], 3],
    'plan rollback attempted changes' => [static fn (): mixed => $plan190()['rollback_attempted_changes'], 6],
    'plan retry changes' => [static fn (): mixed => $plan190()['retry_changes'], 4],
    'plan release returning phases' => [static fn (): mixed => array_column($plan190()['yielded_release_returning'], 'phase'), ['release-savepoint', 'release-savepoint']],
    'plan suppressed returning phases' => [static fn (): mixed => array_column($plan190()['suppressed_by_rollback_returning'], 'phase'), ['rollback-savepoint-speculative', 'rollback-savepoint-speculative']],
    'plan retry returning phases' => [static fn (): mixed => array_column($plan190()['yielded_after_retry_returning'], 'phase'), ['retry-after-rollback', 'retry-after-rollback']],
    'plan suppressed update returning row one' => [static fn (): mixed => $plan190()['suppressed_by_rollback_returning'][0]['rows'][0]['option_id'], 1],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan190()['yielded_after_retry_returning'][0]['rows'], 'option_id'), [2, 6, 10]],
    'plan retry delete returning id' => [static fn (): mixed => $plan190()['yielded_after_retry_returning'][1]['rows'][0]['option_id'], 7],
    'plan final ids' => [static fn (): mixed => array_column($plan190()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 8, 10]],
    'plan final row one restored live' => [static fn (): mixed => array_column($plan190()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan final row two retry' => [static fn (): mixed => array_column($plan190()['current_source_tables']['wp_options'], 'status', 'option_id')[2], 'retry190'],
    'plan final row eight released only' => [static fn (): mixed => array_column($plan190()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'released190'],
    'plan final row ten retry from released' => [static fn (): mixed => array_column($plan190()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'theme:released190:retry190'],
    'plan final row seven deleted by retry' => [static fn (): mixed => in_array(7, array_column($plan190()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan190()['next_source_tables'], $plan190()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan190()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan190()['row_counts']['wp_options'], 8],
    'plan dependency not in release' => [static fn (): mixed => in_array('sqlite-rowvalue-not-in-returning-savepoint-release-next190', $plan190()['dependencies'], true), true],
    'plan dependency not between rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-not-between-delete-returning-rollback-next190', $plan190()['dependencies'], true), true],
    'plan dependency retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-negated-predicate-retry-reads-rollback-image-next190', $plan190()['dependencies'], true), true],
    'malformed empty release rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint($tables190, [], [$rollbackUpdate190], [$retryUpdate190], $unique190), InvalidArgumentException::class],
    'malformed empty rollback rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint($tables190, [$releaseUpdate190], [], [$retryUpdate190], $unique190), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint($tables190, [$releaseUpdate190], [$rollbackUpdate190], [], $unique190), InvalidArgumentException::class],
    'malformed unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint($tables190, [$releaseUpdate190], [$rollbackUpdate190], [$retryUpdate190], []), InvalidArgumentException::class],
    'malformed savepoint name rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint($tables190, [$releaseUpdate190], [$rollbackUpdate190], [$retryUpdate190], $unique190, 'bad-name'), InvalidArgumentException::class],
    'malformed row rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint(['wp_options' => ['bad']], [$releaseUpdate190], [$rollbackUpdate190], [$retryUpdate190], $unique190), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases190 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next190 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
