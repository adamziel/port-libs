<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
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
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 40, 'option_value' => 'a:0:{}'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$outerStageSql = "UPDATE wp_options SET (status, option_value, bytes) = ('outer', option_value || ':outer', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer', 'pending_theme') AS staged_pending ORDER BY option_id";
$innerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS transient_match ORDER BY option_id";
$innerUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('inner', option_value || ':inner', bytes + 5) WHERE option_id IN (9, 10) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$afterRollbackSql = "UPDATE wp_options SET (status, option_value, bytes) = ('after', option_value || ':after', bytes + 2) WHERE (status, option_name) IN (('outer', 'pending_theme'), ('outer', 'orphaned_cache')) RETURNING option_id, option_name, status, option_value, bytes ORDER BY option_id";
$afterCleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, option_name ORDER BY option_id";
$cleanAfterSql = "UPDATE wp_options SET (status, option_value) = ('clean', option_value || ':clean') WHERE option_id IN (7, 8) RETURNING option_id, status, option_value ORDER BY option_id";

$parsedOuter = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($outerStageSql);
$parsedInnerDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($innerDeleteSql);
$parsedInnerUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql);
$parsedAfter = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($afterRollbackSql);
$outerOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerStageSql, $tables, 'option_id', $unique);
$innerDeleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql, $outerOnly()['tables'], 'option_id', $unique);
$innerUpdateOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql, $innerDeleteOnly()['tables'], 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch(
    $tables,
    [$outerStageSql],
    [$innerDeleteSql, $innerUpdateSql],
    [$afterRollbackSql, $afterCleanupSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch(
    $tables,
    [$outerStageSql],
    [$innerUpdateSql],
    [$cleanAfterSql],
    $unique,
    'app_outer_clean',
    'app_inner_clean',
);

$cases = [
    'parse outer row-value IN where' => [static fn (): mixed => $parsedOuter()['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache'))"],
    'parse outer assignment columns' => [static fn (): mixed => array_keys($parsedOuter()['assignments']), ['status', 'option_value', 'bytes']],
    'parse outer returning expression retained' => [static fn (): mixed => str_contains($parsedOuter()['returning'], 'staged_pending'), true],
    'parse inner delete row-value IN where' => [static fn (): mixed => $parsedInnerDelete()['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'parse inner delete returning transient expression' => [static fn (): mixed => str_contains($parsedInnerDelete()['returning'], 'transient_match'), true],
    'parse inner update order desc' => [static fn (): mixed => $parsedInnerUpdate()['order_by'][0]['direction'], 'DESC'],
    'parse after row-value IN source predicate' => [static fn (): mixed => $parsedAfter()['where'], "(status, option_name) IN (('outer', 'pending_theme'), ('outer', 'orphaned_cache'))"],
    'parse after assignment value appends after' => [static fn (): mixed => $parsedAfter()['assignments']['option_value'], "option_value || ':after'"],

    'outer only selected row ids' => [static fn (): mixed => $outerOnly()['plan']->selectedIds, [7, 8]],
    'outer only returning ids' => [static fn (): mixed => array_column($outerOnly()['returning'], 'option_id'), [7, 8]],
    'outer only tuple returning true for pending' => [static fn (): mixed => $outerOnly()['returning'][0]['staged_pending'], 1],
    'outer only row seven value staged' => [static fn (): mixed => array_column($outerOnly()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer'],
    'outer only row eight bytes incremented' => [static fn (): mixed => array_column($outerOnly()['tables']['wp_options'], 'bytes', 'option_id')[8], 6],

    'inner delete selected transients from outer current source' => [static fn (): mixed => $innerDeleteOnly()['plan']->selectedIds, [3, 4]],
    'inner delete returning transient flags' => [static fn (): mixed => array_column($innerDeleteOnly()['returning'], 'transient_match'), [1, 1]],
    'inner delete current source omits transients' => [static fn (): mixed => array_column($innerDeleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'inner update selected desc ids' => [static fn (): mixed => $innerUpdateOnly()['plan']->selectedIds, [10, 9]],
    'inner update returning source ids' => [static fn (): mixed => array_column($innerUpdateOnly()['returning'], 'option_id'), [9, 10]],
    'inner update row ten value inner' => [static fn (): mixed => array_column($innerUpdateOnly()['tables']['wp_options'], 'option_value', 'option_id')[10], 'a:0:{}:inner'],

    'plan status inner rolled back' => [static fn (): mixed => $plan()['status'], 'inner-rolled-back-outer-current-source-preserved'],
    'plan outer savepoint name' => [static fn (): mixed => $plan()['outer_savepoint'], 'app_outer_rowvalue_import'],
    'plan inner savepoint name' => [static fn (): mixed => $plan()['inner_savepoint'], 'app_inner_returning_batch'],
    'plan rolled back inner true' => [static fn (): mixed => $plan()['rolled_back_inner_savepoint'], true],
    'plan outer statements one' => [static fn (): mixed => count($plan()['outer_statements']), 1],
    'plan inner statements two before rollback' => [static fn (): mixed => count($plan()['inner_statements_before_rollback']), 2],
    'plan after rollback statements two' => [static fn (): mixed => count($plan()['after_rollback_statements']), 2],
    'plan outer selected ids' => [static fn (): mixed => $plan()['outer_statements'][0]['selected_ids'], [7, 8]],
    'plan inner actions delete update' => [static fn (): mixed => array_column($plan()['inner_statements_before_rollback'], 'action'), ['delete', 'update']],
    'plan inner delete source sees outer row seven' => [static fn (): mixed => array_column($plan()['inner_statements_before_rollback'][0]['source_rows'], 'option_id'), [3, 4]],
    'plan inner update source rows desc' => [static fn (): mixed => array_column($plan()['inner_statements_before_rollback'][1]['source_rows'], 'option_id'), [9, 10]],
    'plan outer returning row ids' => [static fn (): mixed => array_column($plan()['outer_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan inner returning streams discarded ids delete' => [static fn (): mixed => array_column($plan()['inner_returning_before_rollback'][0]['rows'], 'option_id'), [3, 4]],
    'plan inner returning streams discarded ids update' => [static fn (): mixed => array_column($plan()['inner_returning_before_rollback'][1]['rows'], 'option_id'), [9, 10]],
    'plan discarded inner returning count' => [static fn (): mixed => $plan()['discarded_inner_returning_count'], 4],
    'plan yielded streams exclude inner' => [static fn (): mixed => array_column($plan()['yielded_returning'], 'action'), ['update', 'update', 'delete']],
    'plan yielded ids after rollback update' => [static fn (): mixed => array_column($plan()['after_rollback_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan yielded ids after rollback cleanup' => [static fn (): mixed => array_column($plan()['after_rollback_returning'][1]['rows'], 'option_id'), [3]],
    'plan pre inner rollback has deleted transients' => [static fn (): mixed => array_column($plan()['pre_inner_rollback_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan pre inner rollback row ten inner' => [static fn (): mixed => array_column($plan()['pre_inner_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[10], 'inner'],
    'plan post inner rollback restores transient four' => [static fn (): mixed => array_column($plan()['post_inner_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan post inner rollback restores row ten' => [static fn (): mixed => array_column($plan()['post_inner_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[10], 'live'],
    'plan post inner rollback preserves outer row seven' => [static fn (): mixed => array_column($plan()['post_inner_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer'],
    'plan inner image equals outer result' => [static fn (): mixed => $plan()['inner_savepoint_image_tables'], $outerOnly()['tables']],
    'plan transaction image retains original row seven' => [static fn (): mixed => array_column($plan()['transaction_image_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan final row seven after status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'after'],
    'plan final row seven value includes outer and after' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer:after'],
    'plan final row eight bytes include outer and after' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'bytes', 'option_id')[8], 8],
    'plan final row three deleted by after cleanup' => [static fn (): mixed => in_array(3, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final row four restored after inner rollback' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan final row ten not inner' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[10], 'live'],
    'plan next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan outer changes two' => [static fn (): mixed => $plan()['outer_changes'], 2],
    'plan inner attempted changes four counts deleted and updated rows' => [static fn (): mixed => $plan()['inner_attempted_changes'], 4],
    'plan after rollback changes three' => [static fn (): mixed => $plan()['after_rollback_changes'], 3],
    'plan committed changes five' => [static fn (): mixed => $plan()['changes'], 5],
    'plan row count after cleanup' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 9],
    'plan changed table wp options' => [static fn (): mixed => $plan()['changed_tables'], ['wp_options']],
    'plan failed inner absent' => [static fn (): mixed => $plan()['failed_inner_statement'], null],
    'plan dependency marker unsuffixed' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-nested-savepoint-rollback-batch', $plan()['dependencies'], true), true],
    'plan dependency returning discard' => [static fn (): mixed => in_array('sqlite-rollback-to-inner-savepoint-discards-returning-stream', $plan()['dependencies'], true), true],
    'plan dependency outer survives' => [static fn (): mixed => in_array('sqlite-outer-savepoint-current-source-survives-inner-rollback', $plan()['dependencies'], true), true],

    'clean plan custom outer savepoint' => [static fn (): mixed => $cleanPlan()['outer_savepoint'], 'app_outer_clean'],
    'clean plan custom inner savepoint' => [static fn (): mixed => $cleanPlan()['inner_savepoint'], 'app_inner_clean'],
    'clean plan discards one inner stream' => [static fn (): mixed => count($cleanPlan()['inner_returning_before_rollback']), 1],
    'clean plan after starts from outer source not inner row ten' => [static fn (): mixed => array_column($cleanPlan()['post_inner_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[10], 'live'],
    'clean plan final row seven clean from outer source' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:outer:clean'],
    'clean plan final row ten live' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[10], 'live'],
    'clean plan changes four' => [static fn (): mixed => $cleanPlan()['changes'], 4],

    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [], [$innerUpdateSql], [$afterRollbackSql], $unique), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [$outerStageSql], [], [$afterRollbackSql], $unique), InvalidArgumentException::class],
    'malformed empty after rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [$outerStageSql], [$innerUpdateSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [$outerStageSql], [$innerUpdateSql], [$afterRollbackSql], []), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [$outerStageSql], [$innerUpdateSql], [$afterRollbackSql], $unique, 'same_name', 'same_name'), InvalidArgumentException::class],
    'malformed bad savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch($tables, [$outerStageSql], [$innerUpdateSql], [$afterRollbackSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointRollbackBatch(['wp_options' => ['bad']], [$outerStageSql], [$innerUpdateSql], [$afterRollbackSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue nested savepoint rollback batch ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
