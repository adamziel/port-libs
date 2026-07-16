<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteDatabase.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

$settingstuple = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'homepage', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://homepage.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 1, 'key_name' => 'cache_feed_timeout', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 13, 'key_value' => 'timeout'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 25, 'key_value' => 'https://two.test'],
    ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'homepage', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 26, 'key_value' => 'https://two-homepage.test'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'yes', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 11, 'key_value' => 'module'],
    ['setting_id' => 10, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
];

$targetstuple = [
    ['target_id' => 1, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'target_key' => 'import_touch', 'priority' => 10],
    ['target_id' => 2, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'target_key' => 'import_touch', 'priority' => 20],
    ['target_id' => 3, 'tenant_id' => 3, 'key_name' => 'route_rules', 'target_key' => 'import_touch', 'priority' => 30],
    ['target_id' => 4, 'tenant_id' => 3, 'key_name' => 'route_rules', 'target_key' => 'import_touch', 'priority' => 40],
    ['target_id' => 5, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'target_key' => 'delete_touch', 'priority' => 50],
    ['target_id' => 6, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'target_key' => 'delete_touch', 'priority' => 60],
    ['target_id' => 7, 'tenant_id' => 4, 'key_name' => 'base_url', 'target_key' => 'delete_touch', 'priority' => 70],
    ['target_id' => 8, 'tenant_id' => 4, 'key_name' => 'base_url', 'target_key' => 'delete_touch', 'priority' => 80],
    ['target_id' => 9, 'tenant_id' => 3, 'key_name' => 'module_batch', 'target_key' => 'retry_touch', 'priority' => 90],
    ['target_id' => 10, 'tenant_id' => 3, 'key_name' => 'module_batch', 'target_key' => 'retry_touch', 'priority' => 100],
];

$tablestuple = ['app_settings' => $settingstuple, 'app_setting_targets' => $targetstuple];
$uniquetuple = [['tenant_id', 'key_name']];

$attemptUpdatetuple = "UPDATE app_settings SET (status, key_value, bytes) = ('attempttuple', key_value || ':attempttuple', bytes + 2) WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'import_touch' ORDER BY priority LIMIT -1 OFFSET 1) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes, (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'import_touch') AS touched_tuple ORDER BY setting_id";
$attemptDeletetuple = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'delete_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status, (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'delete_touch') AS deleted_tuple ORDER BY setting_id DESC";
$retryUpdatetuple = "UPDATE app_settings SET (status, key_value, bytes) = ('retrytuple', key_value || ':retrytuple', bytes + 1) WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'import_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id DESC";
$retryDeletetuple = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT DISTINCT tenant_id, key_name FROM app_setting_targets WHERE target_key = 'retry_touch' ORDER BY priority) RETURNING setting_id, tenant_id, key_name, status ORDER BY setting_id";

$attemptUpdateResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdatetuple, $tablestuple, 'setting_id', $uniquetuple);
$attemptDeleteResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDeletetuple, $attemptUpdateResulttuple()['tables'], 'setting_id', $uniquetuple);
$retryUpdateResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdatetuple, $tablestuple, 'setting_id', $uniquetuple);
$retryDeleteResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeletetuple, $retryUpdateResulttuple()['tables'], 'setting_id', $uniquetuple);
$plantuple = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback(
    $tablestuple,
    [$attemptUpdatetuple, $attemptDeletetuple],
    [$retryUpdatetuple, $retryDeletetuple],
    $uniquetuple,
);
$customPlantuple = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback(
    $tablestuple,
    [$attemptUpdatetuple],
    [$retryUpdatetuple],
    $uniquetuple,
    'app_custom_distinct_tuple',
);

$casestuple = [
    'parser attempt update distinct subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatetuple)['where'] ?? '', 'SELECT DISTINCT'), true],
    'parser attempt update offset retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatetuple)['where'] ?? '', 'OFFSET 1'), true],
    'parser attempt delete distinct subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDeletetuple)['where'] ?? '', 'SELECT DISTINCT'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdatetuple)['order_by'], [['column' => 'setting_id', 'direction' => 'DESC']]],
    'parser retry delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeletetuple)['returning'], 'setting_id, tenant_id, key_name, status'],
    'attempt update selected after distinct offset' => [static fn (): mixed => $attemptUpdateResulttuple()['plan']->selectedIds, [8]],
    'attempt update returning id' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['returning'], 'setting_id'), [8]],
    'attempt update tuple flag' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['returning'], 'touched_tuple'), [1]],
    'attempt update row eight changed' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['tables']['app_settings'], 'status', 'setting_id')[8], 'attempttuple'],
    'attempt update row seven skipped by offset distinct' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['tables']['app_settings'], 'status', 'setting_id')[7], 'queued'],
    'attempt delete selected distinct ids' => [static fn (): mixed => $attemptDeleteResulttuple()['plan']->selectedIds, [10, 3]],
    'attempt delete returning order' => [static fn (): mixed => array_column($attemptDeleteResulttuple()['returning'], 'setting_id'), [3, 10]],
    'attempt delete tuple flags' => [static fn (): mixed => array_column($attemptDeleteResulttuple()['returning'], 'deleted_tuple'), [1, 1]],
    'attempt delete removes cache and tenant four' => [static fn (): mixed => array_intersect([3, 10], array_column($attemptDeleteResulttuple()['tables']['app_settings'], 'setting_id')), []],
    'retry update selected distinct ids' => [static fn (): mixed => $retryUpdateResulttuple()['plan']->selectedIds, [8, 7]],
    'retry update returning order' => [static fn (): mixed => array_column($retryUpdateResulttuple()['returning'], 'setting_id'), [7, 8]],
    'retry update row seven restored then changed' => [static fn (): mixed => array_column($retryUpdateResulttuple()['tables']['app_settings'], 'key_value', 'setting_id')[7], 'profile:retrytuple'],
    'retry update row eight retry only' => [static fn (): mixed => array_column($retryUpdateResulttuple()['tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules:retrytuple'],
    'retry delete selected distinct id' => [static fn (): mixed => $retryDeleteResulttuple()['plan']->selectedIds, [9]],
    'retry delete returning id' => [static fn (): mixed => array_column($retryDeleteResulttuple()['returning'], 'setting_id'), [9]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResulttuple()['tables']['app_settings'], 'setting_id'), [1, 2, 3, 4, 5, 6, 7, 8, 10]],

    'plan status' => [static fn (): mixed => $plantuple()['status'], 'rowvalue-update-delete-returning-distinct-tuple-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $plantuple()['savepoint'], 'app_settings_rowvalue_distinct_tuple'],
    'plan distinct flag' => [static fn (): mixed => $plantuple()['distinct_tuple_subquery_deduped'], true],
    'plan rollback flag' => [static fn (): mixed => $plantuple()['rollback_to_savepoint_restores_distinct_tuple_source'], true],
    'plan retry image flag' => [static fn (): mixed => $plantuple()['retry_reads_savepoint_image'], true],
    'plan savepoint active flag' => [static fn (): mixed => $plantuple()['savepoint_remains_active'], true],
    'plan savepoint image original rows' => [static fn (): mixed => $plantuple()['savepoint_image_tables'], $tablestuple],
    'plan attempt row eight changed' => [static fn (): mixed => array_column($plantuple()['attempt_current_source_tables']['app_settings'], 'status', 'setting_id')[8], 'attempttuple'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plantuple()['attempt_current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan attempt row ten deleted' => [static fn (): mixed => in_array(10, array_column($plantuple()['attempt_current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan rollback restores row three' => [static fn (): mixed => in_array(3, array_column($plantuple()['rollback_current_source_tables']['app_settings'], 'setting_id'), true), true],
    'plan rollback restores row eight status' => [static fn (): mixed => array_column($plantuple()['rollback_current_source_tables']['app_settings'], 'status', 'setting_id')[8], 'queued'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plantuple()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[7], 'profile:retrytuple'],
    'plan current row eight retry only' => [static fn (): mixed => array_column($plantuple()['current_source_tables']['app_settings'], 'key_value', 'setting_id')[8], 'rules:retrytuple'],
    'plan current row nine deleted by retry' => [static fn (): mixed => in_array(9, array_column($plantuple()['current_source_tables']['app_settings'], 'setting_id'), true), false],
    'plan current row three restored after attempt rollback' => [static fn (): mixed => in_array(3, array_column($plantuple()['current_source_tables']['app_settings'], 'setting_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plantuple()['next_source_tables'], $plantuple()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plantuple()['attempt_statements'], 'phase'), ['distinct-tuple-attempt-before-rollback', 'distinct-tuple-attempt-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plantuple()['retry_statements'], 'phase'), ['distinct-tuple-retry-after-rollback', 'distinct-tuple-retry-after-rollback']],
    'plan attempt selected ids' => [static fn (): mixed => array_column($plantuple()['attempt_statements'], 'selected_ids'), [[8], [10, 3]]],
    'plan retry selected ids' => [static fn (): mixed => array_column($plantuple()['retry_statements'], 'selected_ids'), [[8, 7], [9]]],
    'plan attempt source row eight original' => [static fn (): mixed => $plantuple()['attempt_statements'][0]['source_rows'][0]['key_value'], 'rules'],
    'plan retry source rows original' => [static fn (): mixed => array_column($plantuple()['retry_statements'][0]['source_rows'], 'key_value'), ['profile', 'rules']],
    'plan suppressed rows ids' => [static fn (): mixed => array_column($plantuple()['suppressed_attempt_rows'], 'setting_id'), [8, 3, 10]],
    'plan retry rows ids' => [static fn (): mixed => array_column($plantuple()['retry_rows'], 'setting_id'), [7, 8, 9]],
    'plan suppressed count' => [static fn (): mixed => $plantuple()['suppressed_returning_count'], 3],
    'plan retry count' => [static fn (): mixed => $plantuple()['retry_returning_count'], 3],
    'plan attempt changes' => [static fn (): mixed => $plantuple()['attempt_change_count'], 3],
    'plan retry changes' => [static fn (): mixed => $plantuple()['retry_change_count'], 3],
    'plan changed tables' => [static fn (): mixed => $plantuple()['changed_tables_after_retry'], ['app_settings']],
    'plan settings row count' => [static fn (): mixed => $plantuple()['row_counts']['app_settings'], 9],
    'plan target row count' => [static fn (): mixed => $plantuple()['row_counts']['app_setting_targets'], 10],
    'plan receipt suppressed ids' => [static fn (): mixed => $plantuple()['tuple_source_receipt']['suppressed_ids'], [8, 3, 10]],
    'plan receipt retry ids' => [static fn (): mixed => $plantuple()['tuple_source_receipt']['retry_ids'], [7, 8, 9]],
    'plan dependency distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-distinct-subquery-tuples', $plantuple()['dependencies'], true), true],
    'plan dependency application' => [static fn (): mixed => in_array('application-rowvalue-distinct-setting-targets-savepoint', $plantuple()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plantuple()['dependency_closure'], 'no new support component'), true],
    'plan non overlap mentions limit offset' => [static fn (): mixed => str_contains($plantuple()['non_overlap'], 'LIMIT -1 OFFSET'), true],
    'custom savepoint' => [static fn (): mixed => $customPlantuple()['savepoint'], 'app_custom_distinct_tuple'],
    'custom suppressed count' => [static fn (): mixed => $customPlantuple()['suppressed_returning_count'], 1],
    'custom retry count' => [static fn (): mixed => $customPlantuple()['retry_returning_count'], 2],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback($tablestuple, [], [$retryUpdatetuple], $uniquetuple), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [], $uniquetuple), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [$retryUpdatetuple], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [$retryUpdatetuple], $uniquetuple, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeDistinctTupleSavepointRollback(['app_settings' => ['bad']], [$attemptUpdatetuple], [$retryUpdatetuple], $uniquetuple), InvalidArgumentException::class],
];

$tests = [];
foreach ($casestuple as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source distinct tuple ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
