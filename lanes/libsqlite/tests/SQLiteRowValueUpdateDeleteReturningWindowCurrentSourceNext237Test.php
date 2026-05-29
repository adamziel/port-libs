<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows237 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 3, 'option_name' => 'theme_mods_twentysixteen', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 15, 'option_value' => 'mods'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => '_transient_cache', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'cache'],
    ['option_id' => 12, 'blog_id' => 4, 'option_name' => 'network_plugin', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 16, 'option_value' => 'network'],
];

$meta237 = [
    ['meta_id' => 201, 'meta_option_id' => 7, 'meta_key' => 'retry_batch', 'meta_value' => 'pending_theme', 'priority' => 10],
    ['meta_id' => 202, 'meta_option_id' => 8, 'meta_key' => 'retry_batch', 'meta_value' => 'rewrite_rules', 'priority' => 20],
    ['meta_id' => 203, 'meta_option_id' => 9, 'meta_key' => 'retry_batch', 'meta_value' => 'plugin_batch', 'priority' => 30],
    ['meta_id' => 204, 'meta_option_id' => 10, 'meta_key' => 'retry_batch', 'meta_value' => 'theme_mods_twentysixteen', 'priority' => 40],
    ['meta_id' => 205, 'meta_option_id' => 11, 'meta_key' => 'retry_batch', 'meta_value' => '_transient_cache', 'priority' => 50],
    ['meta_id' => 206, 'meta_option_id' => 3, 'meta_key' => 'attempt_cleanup', 'meta_value' => '_transient_feed', 'priority' => 5],
    ['meta_id' => 207, 'meta_option_id' => 4, 'meta_key' => 'attempt_cleanup', 'meta_value' => '_transient_timeout_feed', 'priority' => 15],
    ['meta_id' => 208, 'meta_option_id' => 12, 'meta_key' => 'retry_cleanup', 'meta_value' => 'network_plugin', 'priority' => 25],
];

$tables237 = ['wp_options' => $rows237, 'wp_optionmeta' => $meta237];
$unique237 = [['blog_id', 'option_name']];

$attemptUpdate237 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt237', option_value || ':attempt237', bytes + 1) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$attemptDelete237 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'attempt_cleanup' ORDER BY priority ASC) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate237 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry237', option_value || ':retry237', bytes + 3) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_batch' ORDER BY priority ASC LIMIT -1) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete237 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'retry_cleanup' ORDER BY priority ASC) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$attemptUpdateResult237 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate237, $tables237, 'option_id', $unique237);
$attemptDeleteResult237 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete237, $attemptUpdateResult237()['tables'], 'option_id', $unique237);
$retryUpdateResult237 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate237, $tables237, 'option_id', $unique237);
$retryDeleteResult237 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete237, $retryUpdateResult237()['tables'], 'option_id', $unique237);
$plan237 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237(
    $tables237,
    [$attemptUpdate237, $attemptDelete237],
    [$retryUpdate237, $retryDelete237],
    $unique237,
);
$customPlan237 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237(
    $tables237,
    [$attemptUpdate237],
    [$retryUpdate237],
    $unique237,
    'status',
    'option_name',
    'option_id',
    'wp_custom_returning_window_next237',
);

$cases237 = [
    'parser attempt retains subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdate237)['where'] ?? '', "meta_key = 'retry_batch'"), true],
    'parser retry retains negative limit' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdate237)['where'] ?? '', 'LIMIT -1'), true],
    'parser retry returning retained' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate237)['returning'], 'option_id, blog_id, option_name, status, option_value, bytes'],
    'attempt update selected ids' => [static fn (): mixed => $attemptUpdateResult237()['plan']->selectedIds, [7, 8]],
    'attempt update mutation ids' => [static fn (): mixed => $attemptUpdateResult237()['plan']->mutationIds, [7, 8]],
    'attempt update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult237()['returning'], 'option_id'), [7, 8]],
    'attempt update row seven value' => [static fn (): mixed => array_column($attemptUpdateResult237()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt237'],
    'attempt delete selected ids' => [static fn (): mixed => $attemptDeleteResult237()['plan']->selectedIds, [3, 4]],
    'attempt delete returning ids' => [static fn (): mixed => array_column($attemptDeleteResult237()['returning'], 'option_id'), [3, 4]],
    'attempt delete removes cleanup rows' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult237()['tables']['wp_options'], 'option_id'))), []],
    'retry update selected ids' => [static fn (): mixed => $retryUpdateResult237()['plan']->selectedIds, [7, 8, 9, 10, 11]],
    'retry update returning ids' => [static fn (): mixed => array_column($retryUpdateResult237()['returning'], 'option_id'), [7, 8, 9, 10, 11]],
    'retry update row eleven original source' => [static fn (): mixed => array_column($retryUpdateResult237()['tables']['wp_options'], 'option_value', 'option_id')[11], 'cache:retry237'],
    'retry delete selected id' => [static fn (): mixed => $retryDeleteResult237()['plan']->selectedIds, [12]],
    'retry delete removes network plugin' => [static fn (): mixed => in_array(12, array_column($retryDeleteResult237()['tables']['wp_options'], 'option_id'), true), false],

    'plan status' => [static fn (): mixed => $plan237()['status'], 'rowvalue-update-delete-returning-window-exclude-current-source-next237'],
    'plan savepoint' => [static fn (): mixed => $plan237()['savepoint'], 'wp_options_rowvalue_returning_window_next237'],
    'plan partition column' => [static fn (): mixed => $plan237()['partition_column'], 'blog_id'],
    'plan order column' => [static fn (): mixed => $plan237()['order_column'], 'bytes'],
    'plan rollback flags' => [static fn (): mixed => [$plan237()['rolled_back_to_savepoint'], $plan237()['retry_reads_savepoint_image'], $plan237()['savepoint_released_after_retry']], [true, true, true]],
    'plan attempt suppressed flag' => [static fn (): mixed => $plan237()['attempt_returning_suppressed_after_rollback'], true],
    'plan discarded attempt returning count' => [static fn (): mixed => $plan237()['discarded_attempt_returning_count'], 4],
    'plan yielded returning count' => [static fn (): mixed => $plan237()['yielded_returning_count'], 6],
    'plan exclude current window count' => [static fn (): mixed => $plan237()['exclude_current_window_count'], 6],
    'plan attempt row seven mutated' => [static fn (): mixed => array_column($plan237()['attempt_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:attempt237'],
    'plan attempt cleanup deleted' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan237()['attempt_current_source_tables']['wp_options'], 'option_id'))), []],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan237()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores cleanup row four' => [static fn (): mixed => in_array(4, array_column($plan237()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan237()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry237'],
    'plan final row ten retry' => [static fn (): mixed => array_column($plan237()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'mods:retry237'],
    'plan final row twelve deleted' => [static fn (): mixed => in_array(12, array_column($plan237()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final cleanup rows restored' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($plan237()['current_source_tables']['wp_options'], 'option_id'))), [3, 4]],
    'plan next source equals current' => [static fn (): mixed => $plan237()['next_source_tables'], $plan237()['current_source_tables']],
    'plan row counts' => [static fn (): mixed => $plan237()['row_counts'], ['wp_optionmeta' => 8, 'wp_options' => 11]],
    'plan changed tables' => [static fn (): mixed => $plan237()['changed_tables_after_retry'], ['wp_options']],
    'plan attempt actions' => [static fn (): mixed => array_column($plan237()['attempt_statements'], 'action'), ['update', 'delete']],
    'plan retry actions' => [static fn (): mixed => array_column($plan237()['retry_statements'], 'action'), ['update', 'delete']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan237()['attempt_statements'][0]['selected_ids'], $plan237()['attempt_statements'][1]['selected_ids']], [[7, 8], [3, 4]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan237()['retry_statements'][0]['selected_ids'], $plan237()['retry_statements'][1]['selected_ids']], [[7, 8, 9, 10, 11], [12]]],
    'plan retry update source rows original' => [static fn (): mixed => array_column($plan237()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules', 'plugin', 'mods', 'cache']],
    'plan yielded ids' => [static fn (): mixed => array_column($plan237()['yielded_returning'], 'option_id'), [7, 8, 9, 10, 11, 12]],
    'plan yielded statement ordinals' => [static fn (): mixed => array_column($plan237()['yielded_returning'], 'statement_ordinal'), [0, 0, 0, 0, 0, 1]],
    'plan window ids partition order' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'option_id'), [7, 8, 9, 10, 12, 11]],
    'plan window row numbers' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_row_number'), [1, 2, 1, 2, 1, 2]],
    'plan window partition sizes' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_partition_size'), [2, 2, 2, 2, 2, 2]],
    'plan peer counts exclude current' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_count_excluding_current'), [1, 1, 1, 1, 1, 1]],
    'plan peer rowids exclude current' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_rowids_excluding_current'), [[8], [7], [10], [9], [11], [12]]],
    'plan peer names exclude current' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_names_excluding_current'), [['rewrite_rules'], ['pending_theme'], ['theme_mods_twentysixteen'], ['plugin_batch'], ['_transient_cache'], ['network_plugin']]],
    'plan peer bytes exclude current' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_bytes_excluding_current'), [12, 10, 18, 14, 17, 16]],
    'plan first peer names' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_first_name'), ['rewrite_rules', 'pending_theme', 'theme_mods_twentysixteen', 'plugin_batch', '_transient_cache', 'network_plugin']],
    'plan last peer names' => [static fn (): mixed => array_column($plan237()['exclude_current_window_rows'], 'window_peer_last_name'), ['rewrite_rules', 'pending_theme', 'theme_mods_twentysixteen', 'plugin_batch', '_transient_cache', 'network_plugin']],
    'plan partition two summary' => [static fn (): mixed => $plan237()['exclude_current_partition_summary']['2'], ['count' => 2, 'rowids' => [7, 8], 'peer_counts' => [1, 1], 'peer_rowids' => [[8], [7]]]],
    'plan partition three summary' => [static fn (): mixed => $plan237()['exclude_current_partition_summary']['3'], ['count' => 2, 'rowids' => [9, 10], 'peer_counts' => [1, 1], 'peer_rowids' => [[10], [9]]]],
    'plan partition four summary' => [static fn (): mixed => $plan237()['exclude_current_partition_summary']['4'], ['count' => 2, 'rowids' => [12, 11], 'peer_counts' => [1, 1], 'peer_rowids' => [[11], [12]]]],
    'plan token lengths' => [static fn (): mixed => [strlen($plan237()['current_source_token']), strlen($plan237()['window_token'])], [64, 64]],
    'plan tokens differ' => [static fn (): mixed => $plan237()['current_source_token'] !== $plan237()['window_token'], true],
    'plan dependencies' => [static fn (): mixed => $plan237()['dependencies'], ['sqlite-rowvalue-returning-window-exclude-current-next237', 'sqlite-rowvalue-delete-returning-window-peer-frame-next237', 'wordpress-rowvalue-returning-window-current-source-next237']],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan237()['dependency_closure_next237'], 'no new support component needed'), true],
    'plan non overlap' => [static fn (): mixed => str_contains($plan237()['non_overlap_next237'], 'avoids accepted next233/next234'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan237()['savepoint'], 'wp_custom_returning_window_next237'],
    'custom partition column' => [static fn (): mixed => $customPlan237()['partition_column'], 'status'],
    'custom order column' => [static fn (): mixed => $customPlan237()['order_column'], 'option_name'],
    'custom window count' => [static fn (): mixed => $customPlan237()['exclude_current_window_count'], 5],
    'custom status partition summary' => [static fn (): mixed => $customPlan237()['exclude_current_partition_summary']['retry237']['count'], 5],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [], [$retryUpdate237], $unique237), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [$attemptUpdate237], [], $unique237), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [$attemptUpdate237], [$retryUpdate237], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [$attemptUpdate237], [$retryUpdate237], $unique237, 'blog_id', 'bytes', 'option_id', 'bad-name'), InvalidArgumentException::class],
    'malformed partition rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [$attemptUpdate237], [$retryUpdate237], $unique237, 'bad column'), InvalidArgumentException::class],
    'malformed missing partition column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237($tables237, [$attemptUpdate237], [$retryUpdate237], $unique237, 'missing_column'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext237(['wp_options' => ['bad']], [$attemptUpdate237], [$retryUpdate237], $unique237), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases237 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next237 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
