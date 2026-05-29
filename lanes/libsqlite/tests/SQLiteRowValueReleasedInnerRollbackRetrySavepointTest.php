<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows206 = [
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

$tables206 = ['wp_options' => $rows206];
$unique206 = [['blog_id', 'option_name']];
$outerUpdate206 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer206', option_value || ':outer206', bytes + 1) WHERE (blog_id, option_name) IN ((1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerUpdate206 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner206', option_value || ':inner206', bytes + 2) WHERE (blog_id, option_name) IN (VALUES (2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner206', 'pending_theme') AS inner_pending ORDER BY option_id";
$innerDelete206 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS not_siteurl ORDER BY option_id DESC";
$retryUpdate206 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry206', option_value || ':retry206', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete206 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'siteurl')) AS dropped_network_siteurl ORDER BY option_id";

$outerResult206 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate206, $tables206, 'option_id', $unique206);
$innerUpdateResult206 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate206, $outerResult206()['tables'], 'option_id', $unique206);
$innerDeleteResult206 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete206, $innerUpdateResult206()['tables'], 'option_id', $unique206);
$retryUpdateResult206 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate206, $tables206, 'option_id', $unique206);
$retryDeleteResult206 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete206, $retryUpdateResult206()['tables'], 'option_id', $unique206);
$plan206 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry(
    $tables206,
    [$outerUpdate206],
    [$innerUpdate206, $innerDelete206],
    [$retryUpdate206, $retryDelete206],
    $unique206,
);
$customPlan206 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry(
    $tables206,
    [$outerUpdate206],
    [$innerUpdate206],
    [$retryUpdate206],
    $unique206,
    'wp_outer_custom206',
    'wp_inner_custom206',
);

$cases206 = [
    'parser outer row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerUpdate206)['where'], "(blog_id, option_name) IN ((1, 'siteurl'), (1, 'home'))"],
    'parser inner values where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdate206)['where'], "(blog_id, option_name) IN (VALUES (2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser inner delete order' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDelete206)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete206)['where'] ?? '', 'VALUES'), true],
    'outer selected ids' => [static fn (): mixed => $outerResult206()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult206()['returning'], 'option_id'), [1, 2]],
    'outer row one changed' => [static fn (): mixed => array_column($outerResult206()['tables']['wp_options'], 'status', 'option_id')[1], 'outer206'],
    'inner update selected ids' => [static fn (): mixed => $innerUpdateResult206()['plan']->selectedIds, [7, 8]],
    'inner update returning order' => [static fn (): mixed => array_column($innerUpdateResult206()['returning'], 'option_id'), [7, 8]],
    'inner update tuple flag' => [static fn (): mixed => array_column($innerUpdateResult206()['returning'], 'inner_pending'), [1, 0]],
    'inner delete selected ids' => [static fn (): mixed => $innerDeleteResult206()['plan']->selectedIds, [9, 3]],
    'inner delete returning current source order' => [static fn (): mixed => array_column($innerDeleteResult206()['returning'], 'option_id'), [3, 9]],
    'inner delete final excludes plugin batch' => [static fn (): mixed => in_array(9, array_column($innerDeleteResult206()['tables']['wp_options'], 'option_id'), true), false],
    'retry update starts from outer image selected ids' => [static fn (): mixed => $retryUpdateResult206()['plan']->selectedIds, [8, 7]],
    'retry update source did not see inner value' => [static fn (): mixed => array_column($retryUpdateResult206()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry206'],
    'retry delete selected ids' => [static fn (): mixed => $retryDeleteResult206()['plan']->selectedIds, [4, 10]],
    'retry delete network flag' => [static fn (): mixed => array_column($retryDeleteResult206()['returning'], 'dropped_network_siteurl'), [0, 1]],

    'plan status' => [static fn (): mixed => $plan206()['status'], 'rowvalue-update-delete-returning-released-inner-outer-rollback-current-source-released_inner_retry'],
    'plan outer savepoint' => [static fn (): mixed => $plan206()['outer_savepoint'], 'wp_options_outer_rowvalue_released_inner_retry'],
    'plan inner savepoint' => [static fn (): mixed => $plan206()['inner_savepoint'], 'wp_options_inner_released_rowvalue_released_inner_retry'],
    'plan inner released' => [static fn (): mixed => $plan206()['inner_released_before_outer_rollback'], true],
    'plan rolled back outer' => [static fn (): mixed => $plan206()['rolled_back_to_outer_savepoint'], true],
    'plan outer preserved' => [static fn (): mixed => $plan206()['outer_savepoint_preserved_after_rollback_to'], true],
    'plan inner unavailable' => [static fn (): mixed => $plan206()['inner_savepoint_available_after_release'], false],
    'plan retry reads outer image' => [static fn (): mixed => $plan206()['retry_reads_outer_savepoint_image'], true],
    'plan released after retry' => [static fn (): mixed => $plan206()['outer_savepoint_released_after_retry'], true],
    'plan outer image original' => [static fn (): mixed => $plan206()['outer_savepoint_image_tables'], $tables206],
    'plan outer current row one changed' => [static fn (): mixed => array_column($plan206()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer206'],
    'plan inner image equals outer current' => [static fn (): mixed => $plan206()['inner_savepoint_image_tables'], $plan206()['outer_current_source_tables']],
    'plan inner released row seven changed' => [static fn (): mixed => array_column($plan206()['inner_released_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'inner206'],
    'plan inner released row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan206()['inner_released_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback source equals outer image' => [static fn (): mixed => $plan206()['rollback_to_outer_current_source_tables'], $tables206],
    'plan retry source row seven original before retry' => [static fn (): mixed => array_column($plan206()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued']],
    'plan retry delete source rows' => [static fn (): mixed => array_column($plan206()['retry_statements'][1]['source_rows'], 'option_id'), [4, 10]],
    'plan current row one rolled back' => [static fn (): mixed => array_column($plan206()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan current row seven retry' => [static fn (): mixed => array_column($plan206()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry206'],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan206()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry206'],
    'plan current row three restored' => [static fn (): mixed => in_array(3, array_column($plan206()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan current row nine restored' => [static fn (): mixed => in_array(9, array_column($plan206()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan current row ten deleted by retry' => [static fn (): mixed => in_array(10, array_column($plan206()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan206()['next_source_tables'], $plan206()['current_source_tables']],
    'plan outer statement phase' => [static fn (): mixed => $plan206()['outer_statements'][0]['phase'], 'outer-before-inner-release-released_inner_retry'],
    'plan inner update phase' => [static fn (): mixed => $plan206()['inner_released_statements'][0]['phase'], 'inner-released-before-outer-rollback-released_inner_retry'],
    'plan inner delete source ids' => [static fn (): mixed => array_column($plan206()['inner_released_statements'][1]['source_rows'], 'option_id'), [3, 9]],
    'plan retry phase' => [static fn (): mixed => $plan206()['retry_statements'][0]['phase'], 'retry-after-outer-rollback-released_inner_retry'],
    'plan outer yielded count' => [static fn (): mixed => $plan206()['outer_yielded_count'], 2],
    'plan inner released yielded count' => [static fn (): mixed => $plan206()['inner_released_yielded_count'], 4],
    'plan discarded returning count' => [static fn (): mixed => $plan206()['discarded_by_outer_rollback_count'], 6],
    'plan retry yielded count' => [static fn (): mixed => $plan206()['yielded_after_retry_count'], 4],
    'plan discarded phases' => [static fn (): mixed => array_column($plan206()['discarded_by_outer_rollback_returning'], 'phase'), ['outer-before-inner-release-released_inner_retry', 'inner-released-before-outer-rollback-released_inner_retry', 'inner-released-before-outer-rollback-released_inner_retry']],
    'plan retry returning ids flattened' => [static fn (): mixed => array_merge(...array_map(static fn (array $stream): array => array_column($stream['rows'], 'option_id'), $plan206()['yielded_after_retry_returning'])), [7, 8, 4, 10]],
    'plan discarded changes' => [static fn (): mixed => $plan206()['changes_discarded_by_outer_rollback'], 6],
    'plan retry changes' => [static fn (): mixed => $plan206()['changes_after_retry'], 4],
    'plan changed tables' => [static fn (): mixed => $plan206()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan206()['row_counts']['wp_options'], 8],
    'plan dependency release' => [static fn (): mixed => in_array('sqlite-release-inner-savepoint-merges-rowvalue-returning-released_inner_retry', $plan206()['dependencies'], true), true],
    'plan dependency outer rollback' => [static fn (): mixed => in_array('sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-released_inner_retry', $plan206()['dependencies'], true), true],
    'plan dependency retry' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-after-outer-rollback-reads-outer-image-released_inner_retry', $plan206()['dependencies'], true), true],
    'custom outer savepoint' => [static fn (): mixed => $customPlan206()['outer_savepoint'], 'wp_outer_custom206'],
    'custom inner savepoint' => [static fn (): mixed => $customPlan206()['inner_savepoint'], 'wp_inner_custom206'],
    'custom retry count' => [static fn (): mixed => $customPlan206()['yielded_after_retry_count'], 2],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [], [$innerUpdate206], [$retryUpdate206], $unique206), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [], [$retryUpdate206], $unique206), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [$innerUpdate206], [], $unique206), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [$innerUpdate206], [$retryUpdate206], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [$innerUpdate206], [$retryUpdate206], $unique206, 'bad-name'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [$innerUpdate206], [$retryUpdate206], $unique206, 'outer_ok', 'bad-name'), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry($tables206, [$outerUpdate206], [$innerUpdate206], [$retryUpdate206], $unique206, 'same206', 'same206'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleasedInnerRollbackRetry(['wp_options' => ['bad']], [$outerUpdate206], [$innerUpdate206], [$retryUpdate206], $unique206), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases206 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source released_inner_retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
