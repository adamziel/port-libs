<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows214 = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'landing_page', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://landing.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 1, 'key_name' => 'cache_timeout_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 13, 'key_value' => 'timeout'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 25, 'key_value' => 'https://two.test'],
    ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'landing_page', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 26, 'key_value' => 'https://two-landing.test'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
    ['setting_id' => 10, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
    ['setting_id' => 11, 'tenant_id' => 4, 'key_name' => 'landing_page', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 31, 'key_value' => 'https://four-landing.test'],
    ['setting_id' => 12, 'tenant_id' => 5, 'key_name' => 'stale_cache', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 8, 'key_value' => 'cache'],
];

$meta214 = [
    ['target_id' => 101, 'target_setting_id' => 7, 'target_batch' => 'activation_batch', 'target_key_name' => 'pending_profile', 'priority' => 30, 'tenant_id' => 2],
    ['target_id' => 102, 'target_setting_id' => 8, 'target_batch' => 'activation_batch', 'target_key_name' => 'route_rules', 'priority' => 50, 'tenant_id' => 3],
    ['target_id' => 103, 'target_setting_id' => 9, 'target_batch' => 'activation_batch', 'target_key_name' => 'module_batch', 'priority' => 40, 'tenant_id' => 3],
    ['target_id' => 104, 'target_setting_id' => 12, 'target_batch' => 'activation_batch', 'target_key_name' => 'stale_cache', 'priority' => 10, 'tenant_id' => 5],
    ['target_id' => 105, 'target_setting_id' => 3, 'target_batch' => 'cleanup_batch', 'target_key_name' => 'cache_feed', 'priority' => 20, 'tenant_id' => 1],
    ['target_id' => 106, 'target_setting_id' => 4, 'target_batch' => 'cleanup_batch', 'target_key_name' => 'cache_timeout_feed', 'priority' => 10, 'tenant_id' => 1],
    ['target_id' => 107, 'target_setting_id' => null, 'target_batch' => 'cleanup_batch', 'target_key_name' => null, 'priority' => 0, 'tenant_id' => 99],
    ['target_id' => 108, 'target_setting_id' => 10, 'target_batch' => 'archive_batch', 'target_key_name' => 'base_url', 'priority' => 90, 'tenant_id' => 4],
    ['target_id' => 109, 'target_setting_id' => 11, 'target_batch' => 'archive_batch', 'target_key_name' => 'landing_page', 'priority' => 70, 'tenant_id' => 4],
    ['target_id' => 110, 'target_setting_id' => 5, 'target_batch' => 'archive_batch', 'target_key_name' => 'base_url', 'priority' => 60, 'tenant_id' => 2],
];

$tables214 = ['app_settings' => $rows214, 'app_setting_targets' => $meta214];
$unique214 = [['tenant_id', 'key_name']];

$attemptUpdate214 = "UPDATE app_settings SET (status, key_value, bytes) = ('attempt214', key_value || ':attempt214', bytes + 4) WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 2) RETURNING setting_id, tenant_id, key_name, status, key_value, (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 2) AS picked_top ORDER BY setting_id";
$attemptDelete214 = "DELETE FROM app_settings WHERE (setting_id, key_name) NOT IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'cleanup_batch' ORDER BY priority DESC LIMIT 2) AND load_policy = 'no' RETURNING setting_id, tenant_id, key_name, status, (setting_id, key_name) NOT IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'cleanup_batch' ORDER BY priority DESC LIMIT 2) AS outside_cleanup ORDER BY setting_id";
$retryUpdate214 = "UPDATE app_settings SET (status, key_value, bytes) = ('retry214', key_value || ':retry214', bytes + 2) WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 1, 2) RETURNING setting_id, tenant_id, key_name, status, key_value, (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 1, 2) AS picked_window ORDER BY setting_id DESC";
$retryDelete214 = "DELETE FROM app_settings WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'archive_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";

$attemptUpdateResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate214, $tables214, 'setting_id', $unique214);
$attemptDeleteResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDelete214, $attemptUpdateResult214()['tables'], 'setting_id', $unique214);
$retryUpdateResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate214, $tables214, 'setting_id', $unique214);
$retryDeleteResult214 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete214, $retryUpdateResult214()['tables'], 'setting_id', $unique214);
$plan214 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(
    $tables214,
    [$attemptUpdate214, $attemptDelete214],
    [$retryUpdate214, $retryDelete214],
    $unique214,
);
$customPlan214 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(
    $tables214,
    [$attemptUpdate214],
    [$retryUpdate214],
    $unique214,
    'app_custom_ordered_subquery214',
);

$cases214 = [
    'parser keeps ordered update subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($attemptUpdate214)['where'], "(setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 2)"],
    'parser keeps comma limit subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate214)['where'], "(setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY priority DESC LIMIT 1, 2)"],
    'parser keeps offset subquery where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete214)['where'], "(setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'archive_batch' ORDER BY priority DESC LIMIT 2 OFFSET 1)"],
    'direct update selected top ids in table order' => [static fn (): mixed => $attemptUpdateResult214()['plan']->selectedIds, [8, 9]],
    'direct update returning ids' => [static fn (): mixed => array_column($attemptUpdateResult214()['returning'], 'setting_id'), [8, 9]],
    'direct update returning flags' => [static fn (): mixed => array_column($attemptUpdateResult214()['returning'], 'picked_top'), [1, 1]],
    'direct update excludes lower priority row seven' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['app_settings'], 'status', 'setting_id')[7], 'queued'],
    'direct update row eight value' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules:attempt214'],
    'direct update row nine bytes' => [static fn (): mixed => array_column($attemptUpdateResult214()['tables']['app_settings'], 'bytes', 'setting_id')[9], 15],
    'direct delete ordered cleanup excludes null poison' => [static fn (): mixed => $attemptDeleteResult214()['plan']->selectedIds, [7, 9, 12]],
    'direct delete returning outside cleanup flags' => [static fn (): mixed => array_column($attemptDeleteResult214()['returning'], 'outside_cleanup'), [1, 1, 1]],
    'direct delete leaves cleanup cache ids' => [static fn (): mixed => array_values(array_intersect([3, 4], array_column($attemptDeleteResult214()['tables']['app_settings'], 'setting_id'))), [3, 4]],
    'direct delete removes queued deferred-load ids' => [static fn (): mixed => array_values(array_intersect([7, 9, 12], array_column($attemptDeleteResult214()['tables']['app_settings'], 'setting_id'))), []],
    'retry update comma limit selected order' => [static fn (): mixed => $retryUpdateResult214()['plan']->selectedIds, [9, 7]],
    'retry update returning mutation order' => [static fn (): mixed => array_column($retryUpdateResult214()['returning'], 'setting_id'), [7, 9]],
    'retry update returning flags' => [static fn (): mixed => array_column($retryUpdateResult214()['returning'], 'picked_window'), [1, 1]],
    'retry update row seven starts original' => [static fn (): mixed => array_column($retryUpdateResult214()['tables']['app_settings'], 'key_value', 'setting_id')[7], 'profile:retry214'],
    'retry update row eight not retried' => [static fn (): mixed => array_column($retryUpdateResult214()['tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules'],
    'retry delete offset ids' => [static fn (): mixed => $retryDeleteResult214()['plan']->selectedIds, [5, 11]],
    'retry delete returning ids' => [static fn (): mixed => array_column($retryDeleteResult214()['returning'], 'setting_id'), [5, 11]],
    'retry delete keeps highest priority archived setting' => [static fn (): mixed => in_array(10, array_column($retryDeleteResult214()['tables']['app_settings'], 'setting_id'), true), true],
    'retry delete removes offset archive rows' => [static fn (): mixed => array_values(array_intersect([5, 11], array_column($retryDeleteResult214()['tables']['app_settings'], 'setting_id'))), []],

    'plan status' => [static fn (): mixed => $plan214()['status'], 'rowvalue-update-delete-returning-ordered-subquery-savepoint-current-source-next214'],
    'plan savepoint' => [static fn (): mixed => $plan214()['savepoint'], 'app_settings_rowvalue_ordered_subquery_next214'],
    'plan rollback flags' => [static fn (): mixed => [$plan214()['rolled_back_to_savepoint'], $plan214()['savepoint_preserved_after_rollback_to']], [true, true]],
    'plan ordered retry flags' => [static fn (): mixed => [$plan214()['ordered_subquery_limit_respected'], $plan214()['retry_reads_savepoint_image'], $plan214()['savepoint_released_after_retry']], [true, true, true]],
    'plan savepoint image row eight original' => [static fn (): mixed => array_column($plan214()['savepoint_image_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules'],
    'plan attempt row eight mutated' => [static fn (): mixed => array_column($plan214()['attempt_current_source_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules:attempt214'],
    'plan attempt row seven deleted after second attempt' => [static fn (): mixed => in_array(7, array_column($plan214()['attempt_current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan rollback restores row seven' => [static fn (): mixed => array_column($plan214()['rollback_current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'profile'],
    'plan rollback restores row eight' => [static fn (): mixed => array_column($plan214()['rollback_current_source_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules'],
    'plan final row seven retry' => [static fn (): mixed => array_column($plan214()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'profile:retry214'],
    'plan final row nine retry' => [static fn (): mixed => array_column($plan214()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[9], 'module:retry214'],
    'plan final row eight original' => [static fn (): mixed => array_column($plan214()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules'],
    'plan final keeps row ten' => [static fn (): mixed => in_array(10, array_column($plan214()['current_source_tables']['app_settings'], 'setting_id'), true), true],
    'plan final deletes row eleven' => [static fn (): mixed => in_array(11, array_column($plan214()['current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan final deletes row five' => [static fn (): mixed => in_array(5, array_column($plan214()['current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan214()['next_source_tables'], $plan214()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plan214()['attempt_statements'], 'phase'), ['attempt-ordered-subquery-before-rollback-next214', 'attempt-ordered-subquery-before-rollback-next214']],
    'plan retry phases' => [static fn (): mixed => array_column($plan214()['retry_statements'], 'phase'), ['retry-ordered-subquery-after-rollback-next214', 'retry-ordered-subquery-after-rollback-next214']],
    'plan attempt selected ids' => [static fn (): mixed => [$plan214()['attempt_statements'][0]['selected_ids'], $plan214()['attempt_statements'][1]['selected_ids']], [[8, 9], [7, 9, 12]]],
    'plan retry selected ids' => [static fn (): mixed => [$plan214()['retry_statements'][0]['selected_ids'], $plan214()['retry_statements'][1]['selected_ids']], [[9, 7], [5, 11]]],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan214()['retry_statements'][0]['source_rows'], 'key_value'), ['profile', 'module']],
    'plan retry delete source names' => [static fn (): mixed => array_column($plan214()['retry_statements'][1]['source_rows'], 'key_name'), ['base_url', 'landing_page']],
    'plan discarded returning count' => [static fn (): mixed => $plan214()['discarded_attempt_returning_count'], 5],
    'plan yielded retry count' => [static fn (): mixed => $plan214()['yielded_after_retry_count'], 4],
    'plan attempt changes count' => [static fn (): mixed => $plan214()['attempt_changes_before_rollback'], 5],
    'plan retry changes count' => [static fn (): mixed => $plan214()['retry_changes_after_rollback'], 4],
    'plan row counts' => [static fn (): mixed => $plan214()['row_counts'], ['app_setting_targets' => 10, 'app_settings' => 10]],
    'plan changed tables only settings' => [static fn (): mixed => $plan214()['changed_tables_after_retry'], ['app_settings']],
    'plan dependency update ordered subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-in-select-order-limit-update-returning-next214', $plan214()['dependencies'], true), true],
    'plan dependency delete ordered subquery' => [static fn (): mixed => in_array('sqlite-rowvalue-not-in-select-order-limit-delete-returning-next214', $plan214()['dependencies'], true), true],
    'plan dependency savepoint current source' => [static fn (): mixed => in_array('sqlite-rowvalue-ordered-subquery-savepoint-current-source-next214', $plan214()['dependencies'], true), true],
    'custom savepoint' => [static fn (): mixed => $customPlan214()['savepoint'], 'app_custom_ordered_subquery214'],
    'custom yielded count' => [static fn (): mixed => $customPlan214()['yielded_after_retry_count'], 2],
    'limit only subquery update selected ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET status = 'limit214' WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' LIMIT 1) RETURNING setting_id ORDER BY setting_id", $tables214, 'setting_id', $unique214)['plan']->selectedIds, [7]],
    'order only subquery update selected ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET status = 'order214' WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'archive_batch' ORDER BY priority ASC) RETURNING setting_id ORDER BY setting_id", $tables214, 'setting_id', $unique214)['plan']->selectedIds, [5, 10, 11]],
    'malformed missing subquery table rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($attemptUpdate214, ['app_settings' => $rows214], 'setting_id', $unique214), InvalidArgumentException::class],
    'malformed subquery order column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET status = 'bad214' WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' ORDER BY missing LIMIT 1) RETURNING setting_id", $tables214, 'setting_id', $unique214), InvalidArgumentException::class],
    'malformed subquery offset without limit rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET status = 'bad214' WHERE (setting_id, key_name) IN (SELECT target_setting_id, target_key_name FROM app_setting_targets WHERE target_batch = 'activation_batch' OFFSET 1) RETURNING setting_id", $tables214, 'setting_id', $unique214), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [], [$retryUpdate214], $unique214), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [], $unique214), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [$retryUpdate214], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry($tables214, [$attemptUpdate214], [$retryUpdate214], $unique214, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderedSubquerySavepointRetry(['app_settings' => ['bad']], [$attemptUpdate214], [$retryUpdate214], $unique214), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases214 as $name => [$callback, $expected]) {
    $tests['rowvalue ordered subquery savepoint retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
