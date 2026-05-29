<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows182 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables182 = ['wp_options' => $rows182];
$unique182 = [['blog_id', 'option_name']];

$outerUpdate182 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer182', option_value || ':outer182', bytes + 2) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer182', 'pending_theme') AS pending_outer ORDER BY option_id";
$innerDelete182 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS transient_pair ORDER BY option_id";
$innerReplace182 = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'released182', option_value || ':released182', bytes + 40) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$retryUpdate182 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry182', option_value || ':retry182', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry182', 'pending_theme') AS pending_retry ORDER BY option_id";
$retryDelete182 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$outer182 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate182, $tables182, 'option_id', $unique182);
$innerDeleteAfterOuter182 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete182, $outer182()['tables'], 'option_id', $unique182);
$innerReplaceAfterDelete182 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerReplace182, $innerDeleteAfterOuter182()['tables'], 'option_id', $unique182);
$retryUpdateAfterRollback182 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate182, $tables182, 'option_id', $unique182);
$plan182 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182(
    $tables182,
    [$outerUpdate182],
    [$innerDelete182, $innerReplace182],
    [$retryUpdate182, $retryDelete182],
    $unique182,
);

$cases182 = [
    'parser outer row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($outerUpdate182)['assignments']), ['status', 'option_value', 'bytes']],
    'parser inner delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDelete182)['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'parser inner replace conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerReplace182)['conflict_action'], 'replace'],
    'parser inner replace row value assignments' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($innerReplace182)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate182)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'))"],
    'outer selected ids' => [static fn (): mixed => $outer182()['plan']->selectedIds, [7, 8]],
    'outer returning ids' => [static fn (): mixed => array_column($outer182()['returning'], 'option_id'), [7, 8]],
    'outer returning row value predicate true' => [static fn (): mixed => $outer182()['returning'][0]['pending_outer'], 1],
    'outer mutates row seven before inner' => [static fn (): mixed => array_column($outer182()['tables']['wp_options'], 'status', 'option_id')[7], 'outer182'],
    'inner delete yields transient ids' => [static fn (): mixed => array_column($innerDeleteAfterOuter182()['returning'], 'option_id'), [3, 4]],
    'inner delete removes transient ids' => [static fn (): mixed => array_intersect([3, 4], array_column($innerDeleteAfterOuter182()['tables']['wp_options'], 'option_id')), []],
    'inner delete returning tuple predicate true' => [static fn (): mixed => array_column($innerDeleteAfterOuter182()['returning'], 'transient_pair'), [1, 1]],
    'inner replace deletes conflicting row ten' => [static fn (): mixed => array_column($innerReplaceAfterDelete182()['deleted_conflict_rows'], 'option_id'), [10]],
    'inner replace returns row seven' => [static fn (): mixed => array_column($innerReplaceAfterDelete182()['returning'], 'option_id'), [7]],
    'inner replace row seven takes released key' => [static fn (): mixed => array_column($innerReplaceAfterDelete182()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'inner release image lacks row ten before outer rollback' => [static fn (): mixed => in_array(10, array_column($innerReplaceAfterDelete182()['tables']['wp_options'], 'option_id'), true), false],
    'retry update from outer image sees original row seven value' => [static fn (): mixed => $retryUpdateAfterRollback182()['returning'][0]['option_value'], 'theme:retry182'],
    'retry update returns rows seven and nine' => [static fn (): mixed => array_column($retryUpdateAfterRollback182()['returning'], 'option_id'), [7, 9]],

    'plan status' => [static fn (): mixed => $plan182()['status'], 'released-inner-returning-suppressed-by-outer-rollback-next182'],
    'plan outer savepoint' => [static fn (): mixed => $plan182()['outer_savepoint'], 'wp_options_outer_rowvalue_next182'],
    'plan inner savepoint' => [static fn (): mixed => $plan182()['inner_savepoint'], 'wp_options_inner_rowvalue_next182'],
    'plan inner released before rollback' => [static fn (): mixed => $plan182()['inner_released_into_outer_before_rollback'], true],
    'plan rolled back to outer' => [static fn (): mixed => $plan182()['rolled_back_to_outer_savepoint'], true],
    'plan outer preserved after rollback to' => [static fn (): mixed => $plan182()['outer_savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan182()['outer_released_after_retry'], true],
    'plan outer image original table' => [static fn (): mixed => $plan182()['outer_savepoint_image_tables'], $tables182],
    'plan outer current source row seven status' => [static fn (): mixed => array_column($plan182()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer182'],
    'plan inner image equals outer current source' => [static fn (): mixed => $plan182()['inner_savepoint_image_tables'], $plan182()['outer_current_source_tables']],
    'plan inner released row ten absent' => [static fn (): mixed => in_array(10, array_column($plan182()['inner_released_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback to outer restores original row seven status' => [static fn (): mixed => array_column($plan182()['rollback_to_outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback to outer restores transients' => [static fn (): mixed => array_intersect([3, 4], array_column($plan182()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id')), [3, 4]],
    'plan rollback to outer restores row ten' => [static fn (): mixed => array_column($plan182()['rollback_to_outer_current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan outer returning count' => [static fn (): mixed => $plan182()['outer_returning_before_rollback_count'], 2],
    'plan inner returning count' => [static fn (): mixed => $plan182()['inner_returning_released_before_rollback_count'], 3],
    'plan suppressed returning count' => [static fn (): mixed => $plan182()['suppressed_by_outer_rollback_count'], 5],
    'plan yielded retry count' => [static fn (): mixed => $plan182()['yielded_after_retry_count'], 4],
    'plan outer changes before rollback' => [static fn (): mixed => $plan182()['outer_changes_before_rollback'], 2],
    'plan inner changes before rollback' => [static fn (): mixed => $plan182()['inner_changes_released_before_rollback'], 4],
    'plan retry changes after rollback' => [static fn (): mixed => $plan182()['retry_changes_after_outer_rollback'], 4],
    'plan outer statement phase' => [static fn (): mixed => $plan182()['outer_statements'][0]['phase'], 'outer-before-inner-release'],
    'plan inner statement phases' => [static fn (): mixed => array_column($plan182()['inner_released_statements'], 'phase'), ['inner-released-into-outer', 'inner-released-into-outer']],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan182()['retry_statements'], 'phase'), ['retry-after-outer-rollback', 'retry-after-outer-rollback']],
    'plan inner delete source rows see outer current' => [static fn (): mixed => array_column($plan182()['inner_released_statements'][0]['source_rows'], 'status'), ['stale', 'stale']],
    'plan inner replace source row sees outer status' => [static fn (): mixed => array_column($plan182()['inner_released_statements'][1]['source_rows'], 'status'), ['outer182']],
    'plan suppressed outer ids' => [static fn (): mixed => array_column($plan182()['suppressed_by_outer_rollback_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan suppressed inner delete ids' => [static fn (): mixed => array_column($plan182()['suppressed_by_outer_rollback_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan suppressed inner replace id' => [static fn (): mixed => array_column($plan182()['suppressed_by_outer_rollback_returning'][2]['rows'], 'option_id'), [7]],
    'plan retry update ids' => [static fn (): mixed => array_column($plan182()['yielded_after_retry_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan retry delete ids' => [static fn (): mixed => array_column($plan182()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan final row seven retry value starts from original' => [static fn (): mixed => array_column($plan182()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry182'],
    'plan final row eight outer rollback undone' => [static fn (): mixed => array_column($plan182()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan final row nine retry status' => [static fn (): mixed => array_column($plan182()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry182'],
    'plan final row ten restored' => [static fn (): mixed => array_column($plan182()['current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan final deletes transients after retry' => [static fn (): mixed => array_intersect([3, 4], array_column($plan182()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan next source equals current' => [static fn (): mixed => $plan182()['next_source_tables'], $plan182()['current_source_tables']],
    'plan row count after retry' => [static fn (): mixed => $plan182()['row_counts']['wp_options'], 8],
    'plan changed tables after retry' => [static fn (): mixed => $plan182()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency release inner merge' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-release-inner-merge-next182', $plan182()['dependencies'], true), true],
    'plan dependency suppresses released returning' => [static fn (): mixed => in_array('sqlite-rollback-to-outer-suppresses-released-inner-returning-next182', $plan182()['dependencies'], true), true],
    'plan dependency retries from outer image' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-starts-from-outer-image-next182', $plan182()['dependencies'], true), true],

    'malformed empty outer statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182($tables182, [], [$innerDelete182], [$retryUpdate182], $unique182), InvalidArgumentException::class],
    'malformed empty inner statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182($tables182, [$outerUpdate182], [], [$retryUpdate182], $unique182), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182($tables182, [$outerUpdate182], [$innerDelete182], [], $unique182), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182($tables182, [$outerUpdate182], [$innerDelete182], [$retryUpdate182], []), InvalidArgumentException::class],
    'malformed same savepoint names rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182($tables182, [$outerUpdate182], [$innerDelete182], [$retryUpdate182], $unique182, 'same', 'same'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext182(['wp_options' => ['bad']], [$outerUpdate182], [$innerDelete182], [$retryUpdate182], $unique182), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases182 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next182 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
