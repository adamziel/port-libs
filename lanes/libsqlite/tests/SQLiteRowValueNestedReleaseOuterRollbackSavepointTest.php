<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows230 = [
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
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
    ['option_id' => 12, 'blog_id' => 5, 'option_name' => 'migration_lock', 'autoload' => 'no', 'status' => null, 'bytes' => 2, 'option_value' => 'lock'],
];

$meta230 = [
    ['meta_id' => 201, 'meta_option_id' => 7, 'meta_key' => 'migration_batch', 'meta_value' => 'pending_theme', 'blog_id' => 2],
    ['meta_id' => 202, 'meta_option_id' => 8, 'meta_key' => 'migration_batch', 'meta_value' => 'rewrite_rules', 'blog_id' => 3],
    ['meta_id' => 203, 'meta_option_id' => 3, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_feed', 'blog_id' => 1],
    ['meta_id' => 204, 'meta_option_id' => 4, 'meta_key' => 'cleanup_batch', 'meta_value' => '_transient_timeout_feed', 'blog_id' => 1],
    ['meta_id' => 205, 'meta_option_id' => 10, 'meta_key' => 'network_drop', 'meta_value' => 'siteurl', 'blog_id' => 4],
    ['meta_id' => 206, 'meta_option_id' => null, 'meta_key' => 'null_guard', 'meta_value' => null, 'blog_id' => 99],
];

$tables230 = ['wp_options' => $rows230, 'wp_optionmeta' => $meta230];
$unique230 = [['blog_id', 'option_name']];

$preSql230 = "UPDATE wp_options SET (status, option_value, bytes) = ('pre', option_value || ':pre', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$innerUpdateSql230 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner', option_value || ':inner', bytes + 4) WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_id DESC LIMIT 2) RETURNING option_id, blog_id, option_name, status, option_value, (status, option_name) IS ('inner', 'pending_theme') AS inner_pending ORDER BY option_id";
$innerDeleteSql230 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_id) RETURNING option_id, blog_id, option_name, status, (option_id, option_name) IS DISTINCT FROM (4, '_transient_timeout_feed') AS not_timeout ORDER BY option_id";
$afterReleaseDeleteSql230 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'network_drop') RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdateSql230 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 2) WHERE (option_id, option_name) IN (SELECT DISTINCT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_id) RETURNING option_id, blog_id, option_name, status, option_value, (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch') AS in_retry_batch ORDER BY option_id";
$retryDeleteSql230 = "DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_id LIMIT 1) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$preResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($preSql230, $tables230, 'option_id', $unique230);
$innerUpdateResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql230, $preResult230()['tables'], 'option_id', $unique230);
$innerDeleteResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql230, $innerUpdateResult230()['tables'], 'option_id', $unique230);
$afterReleaseDeleteResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($afterReleaseDeleteSql230, $innerDeleteResult230()['tables'], 'option_id', $unique230);
$retryUpdateResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql230, $preResult230()['tables'], 'option_id', $unique230);
$retryDeleteResult230 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteSql230, $retryUpdateResult230()['tables'], 'option_id', $unique230);
$plan230 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint(
    $tables230,
    [$preSql230],
    [$innerUpdateSql230, $innerDeleteSql230],
    [$afterReleaseDeleteSql230],
    [$retryUpdateSql230, $retryDeleteSql230],
    $unique230,
);
$customPlan230 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint(
    $tables230,
    [$preSql230],
    [$innerUpdateSql230],
    [$afterReleaseDeleteSql230],
    [$retryUpdateSql230],
    $unique230,
    'wp_outer_custom',
    'wp_inner_custom',
);

$cases230 = [
    'parser keeps inner update ordered subquery' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql230)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'migration_batch' ORDER BY meta_id DESC LIMIT 2)"],
    'parser keeps retry distinct subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryUpdateSql230)['where'] ?? '', 'SELECT DISTINCT'), true],
    'parser keeps retry delete limit subquery' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeleteSql230)['where'], "(option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'cleanup_batch' ORDER BY meta_id LIMIT 1)"],
    'pre selected ids' => [static fn (): mixed => $preResult230()['plan']->selectedIds, [1, 2]],
    'pre returning ids' => [static fn (): mixed => array_column($preResult230()['returning'], 'option_id'), [1, 2]],
    'pre row one value' => [static fn (): mixed => array_column($preResult230()['tables']['wp_options'], 'option_value', 'option_id')[1], 'https://one.test:pre'],
    'inner update selected ids despite desc source order' => [static fn (): mixed => $innerUpdateResult230()['plan']->selectedIds, [7, 8]],
    'inner update returning ids' => [static fn (): mixed => array_column($innerUpdateResult230()['returning'], 'option_id'), [7, 8]],
    'inner update returning predicate flags' => [static fn (): mixed => array_column($innerUpdateResult230()['returning'], 'inner_pending'), [1, 0]],
    'inner delete selected cleanup ids' => [static fn (): mixed => $innerDeleteResult230()['plan']->selectedIds, [3, 4]],
    'inner delete returning distinct flags' => [static fn (): mixed => array_column($innerDeleteResult230()['returning'], 'not_timeout'), [1, 0]],
    'inner delete removes timeout before release' => [static fn (): mixed => in_array(4, array_column($innerDeleteResult230()['tables']['wp_options'], 'option_id'), true), false],
    'after release delete selected network row' => [static fn (): mixed => $afterReleaseDeleteResult230()['plan']->selectedIds, [10]],
    'after release delete removes network row' => [static fn (): mixed => in_array(10, array_column($afterReleaseDeleteResult230()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids from outer image' => [static fn (): mixed => $retryUpdateResult230()['plan']->selectedIds, [7, 8]],
    'retry update starts from original row seven' => [static fn (): mixed => $retryUpdateResult230()['returning'][0]['option_value'], 'theme:retry'],
    'retry update predicate flags' => [static fn (): mixed => array_column($retryUpdateResult230()['returning'], 'in_retry_batch'), [1, 1]],
    'retry delete limit selects first cleanup only' => [static fn (): mixed => $retryDeleteResult230()['plan']->selectedIds, [3]],
    'retry delete leaves timeout because limit one' => [static fn (): mixed => in_array(4, array_column($retryDeleteResult230()['tables']['wp_options'], 'option_id'), true), true],
    'retry delete keeps network row restored by outer rollback' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult230()['tables']['wp_options'], 'option_id'), true), true],
    'null tuple list does not match migration batch' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, meta_value FROM wp_optionmeta WHERE meta_key = 'null_guard') RETURNING option_id ORDER BY option_id", $tables230, 'option_id', $unique230)['plan']->selectedIds, []],

    'plan status' => [static fn (): mixed => $plan230()['status'], 'rowvalue-update-delete-returning-nested-release-outer-rollback-savepoint'],
    'plan savepoints' => [static fn (): mixed => [$plan230()['outer_savepoint'], $plan230()['inner_savepoint']], ['wp_options_rowvalue_outer_release_rollback', 'wp_options_rowvalue_inner_release_rollback']],
    'plan flags' => [static fn (): mixed => [$plan230()['inner_released_before_outer_rollback'], $plan230()['rolled_back_to_outer_savepoint'], $plan230()['retry_reads_outer_savepoint_image']], [true, true, true]],
    'plan release flag' => [static fn (): mixed => $plan230()['outer_savepoint_released_after_retry'], true],
    'plan initial count' => [static fn (): mixed => count($plan230()['initial_tables']['wp_options']), 12],
    'plan pre image row one' => [static fn (): mixed => array_column($plan230()['outer_savepoint_image_tables']['wp_options'], 'status', 'option_id')[1], 'pre'],
    'plan inner released row seven' => [static fn (): mixed => array_column($plan230()['inner_released_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'inner'],
    'plan inner released row three deleted' => [static fn (): mixed => in_array(3, array_column($plan230()['inner_released_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan after release row ten deleted' => [static fn (): mixed => in_array(10, array_column($plan230()['after_inner_release_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan230()['rollback_to_outer_current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme'],
    'plan rollback restores deleted cleanup row' => [static fn (): mixed => in_array(3, array_column($plan230()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan rollback restores network row' => [static fn (): mixed => in_array(10, array_column($plan230()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan230()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan230()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry'],
    'plan final cleanup feed deleted' => [static fn (): mixed => in_array(3, array_column($plan230()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan final cleanup timeout kept' => [static fn (): mixed => in_array(4, array_column($plan230()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan final network row kept' => [static fn (): mixed => in_array(10, array_column($plan230()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan230()['next_source_tables'], $plan230()['current_source_tables']],
    'plan statement phases' => [static fn (): mixed => [array_column($plan230()['inner_statements'], 'phase'), array_column($plan230()['after_release_statements'], 'phase'), array_column($plan230()['retry_statements'], 'phase')], [['inner-before-release', 'inner-before-release'], ['after-inner-release'], ['retry-after-outer-rollback', 'retry-after-outer-rollback']]],
    'plan inner selected ids' => [static fn (): mixed => [$plan230()['inner_statements'][0]['selected_ids'], $plan230()['inner_statements'][1]['selected_ids']], [[7, 8], [3, 4]]],
    'plan after release selected ids' => [static fn (): mixed => $plan230()['after_release_statements'][0]['selected_ids'], [10]],
    'plan retry selected ids' => [static fn (): mixed => [$plan230()['retry_statements'][0]['selected_ids'], $plan230()['retry_statements'][1]['selected_ids']], [[7, 8], [3]]],
    'plan retry source rows original migration values' => [static fn (): mixed => array_column($plan230()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules']],
    'plan pre returning count' => [static fn (): mixed => $plan230()['pre_returning_count'], 2],
    'plan discarded inner release returning count' => [static fn (): mixed => $plan230()['discarded_inner_release_returning_count'], 5],
    'plan yielded retry count' => [static fn (): mixed => $plan230()['yielded_after_retry_count'], 3],
    'plan changes before rollback' => [static fn (): mixed => $plan230()['changes_before_outer_rollback'], 5],
    'plan retry changes after rollback' => [static fn (): mixed => $plan230()['retry_changes_after_outer_rollback'], 3],
    'plan row counts' => [static fn (): mixed => $plan230()['row_counts'], ['wp_optionmeta' => 6, 'wp_options' => 11]],
    'plan changed tables only options' => [static fn (): mixed => $plan230()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency nested release' => [static fn (): mixed => in_array('sqlite-nested-savepoint-release-returning-discarded-by-outer-rollback', $plan230()['dependencies'], true), true],
    'plan dependency wordpress current source' => [static fn (): mixed => in_array('wordpress-rowvalue-nested-release-outer-rollback-savepoint', $plan230()['dependencies'], true), true],
    'plan non overlap mentions simple rollback' => [static fn (): mixed => str_contains($plan230()['non_overlap'], 'simple rollback'), true],
    'plan dependency closure says no new support' => [static fn (): mixed => str_contains($plan230()['dependency_closure'], 'no new support component needed'), true],
    'custom savepoints' => [static fn (): mixed => [$customPlan230()['outer_savepoint'], $customPlan230()['inner_savepoint']], ['wp_outer_custom', 'wp_inner_custom']],
    'custom yielded count' => [static fn (): mixed => $customPlan230()['yielded_after_retry_count'], 2],
    'custom discarded count' => [static fn (): mixed => $customPlan230()['discarded_inner_release_returning_count'], 3],
    'malformed empty pre rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230), InvalidArgumentException::class],
    'malformed empty after release rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [], [$retryUpdateSql230], $unique230), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [], $unique230), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230, 'bad-name', 'good_inner'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230, 'good_outer', 'bad-name'), InvalidArgumentException::class],
    'malformed duplicate savepoint names rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint($tables230, [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230, 'same_name', 'same_name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNestedReleaseOuterRollbackSavepoint(['wp_options' => ['bad']], [$preSql230], [$innerUpdateSql230], [$afterReleaseDeleteSql230], [$retryUpdateSql230], $unique230), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases230 as $name => [$callback, $expected]) {
    $tests['rowvalue nested release outer rollback savepoint ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
