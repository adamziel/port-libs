<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tests = [];

$rows = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 24, 'key_value' => 'https://old.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'home', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 24, 'key_value' => 'https://old.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 1, 'key_name' => 'cache_feed_timeout', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 13, 'key_value' => 'timeout'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 14, 'key_value' => 'tenant-feed'],
    ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 25, 'key_value' => 'https://tenant.test'],
    ['setting_id' => 7, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => null, 'bytes' => 7, 'key_value' => 'theme'],
    ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 16, 'key_value' => 'orphan'],
];

$tables = ['app_settings' => $rows];
$rowIdColumn = 'setting_id';

$deleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN ((1, 'cache_feed'), (2, 'cache_feed'), (3, '_missing')) RETURNING setting_id, key_name || ':' || status AS old_label, bytes + tenant_id + 1 AS old_weight, (tenant_id, status) = (1, 'stale') AS was_tenant_one_stale ORDER BY tenant_id DESC LIMIT 2";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables, $rowIdColumn);

$updateSql = "UPDATE app_settings SET (load_policy, status, key_value, bytes) = ('yes', 'migrated', key_name || ':migrated', bytes + tenant_id + 100) WHERE (tenant_id, key_name) IN ((1, 'base_url'), (2, 'base_url'), (2, 'pending_profile')) RETURNING setting_id, key_name || ':' || status AS next_label, bytes + tenant_id AS next_weight, (load_policy, status) = ('yes', 'migrated') AS migrated_tuple, status IS NOT NULL AS status_present ORDER BY setting_id DESC LIMIT 2";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables, $rowIdColumn);

$cases = [
    'delete returning expression keeps source mutation order' => [static fn (): mixed => array_column($delete()['returning'], 'setting_id'), [3, 5]],
    'delete returning concat expression sees old rows' => [static fn (): mixed => array_column($delete()['returning'], 'old_label'), ['cache_feed:stale', 'cache_feed:stale']],
    'delete returning addition expression can add columns and literals' => [static fn (): mixed => array_column($delete()['returning'], 'old_weight'), [14, 17]],
    'delete returning row value comparison true becomes one' => [static fn (): mixed => $delete()['returning'][0]['was_tenant_one_stale'], 1],
    'delete returning row value comparison false becomes zero' => [static fn (): mixed => $delete()['returning'][1]['was_tenant_one_stale'], 0],
    'delete returning aliases preserve projection order' => [static fn (): mixed => array_keys($delete()['returning'][0]), ['setting_id', 'old_label', 'old_weight', 'was_tenant_one_stale']],
    'delete returning selected ids still come from ORDER LIMIT' => [static fn (): mixed => $delete()['plan']->selectedIds, [5, 3]],
    'delete returning result removes row-value matches' => [static fn (): mixed => array_column($delete()['tables']['app_settings'], 'setting_id'), [1, 2, 4, 6, 7, 8]],
    'delete returning row-value IN expression exact match' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 6 RETURNING (tenant_id, key_name) IN ((2, 'base_url'), (2, 'home')) AS matched", $tables, $rowIdColumn)['returning'], [['matched' => 1]]],
    'delete returning row-value NOT IN expression false' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 6 RETURNING (tenant_id, key_name) NOT IN ((2, 'base_url'), (2, 'home')) AS not_matched", $tables, $rowIdColumn)['returning'], [['not_matched' => 0]]],
    'delete returning row-value expression unknown with null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 7 RETURNING (tenant_id, status) = (2, NULL) AS null_match", $tables, $rowIdColumn)['returning'], [['null_match' => null]]],
    'delete returning IS NULL expression after old row' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 7 RETURNING status IS NULL AS was_null", $tables, $rowIdColumn)['returning'], [['was_null' => 1]]],
    'update returning expression follows source mutation order' => [static fn (): mixed => array_column($update()['returning'], 'setting_id'), [6, 7]],
    'update returning concat expression sees new status' => [static fn (): mixed => array_column($update()['returning'], 'next_label'), ['base_url:migrated', 'pending_profile:migrated']],
    'update returning addition expression sees new bytes' => [static fn (): mixed => array_column($update()['returning'], 'next_weight'), [129, 111]],
    'update returning row-value comparison sees assigned columns' => [static fn (): mixed => array_column($update()['returning'], 'migrated_tuple'), [1, 1]],
    'update returning IS NOT NULL expression sees assigned status' => [static fn (): mixed => array_column($update()['returning'], 'status_present'), [1, 1]],
    'update returning selected ids still sorted by ORDER LIMIT' => [static fn (): mixed => $update()['plan']->selectedIds, [7, 6]],
    'update returning mutation ids remain source order after sorted selection' => [static fn (): mixed => $update()['plan']->mutationIds, [6, 7]],
    'update returning result row six bytes changed' => [static fn (): mixed => array_column($update()['tables']['app_settings'], 'bytes', 'setting_id')[6], 127],
    'update returning result row seven copied name into value' => [static fn (): mixed => array_column($update()['tables']['app_settings'], 'key_value', 'setting_id')[7], 'pending_profile:migrated'],
    'update returning expression can mix updated row columns' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET (status, bytes) = ('staged', bytes + 10) WHERE setting_id = 1 RETURNING key_name || ':' || status AS label, bytes + tenant_id AS weight", $tables, $rowIdColumn)['returning'], [['label' => 'base_url:staged', 'weight' => 35]]],
    'update returning row-value greater expression true' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET (tenant_id, status) = (3, 'migrated') WHERE setting_id = 6 RETURNING (tenant_id, key_name) > (2, 'zzzz') AS after_tenant", $tables, $rowIdColumn)['returning'], [['after_tenant' => 1]]],
    'update returning row-value less expression false' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET (tenant_id, status) = (3, 'migrated') WHERE setting_id = 6 RETURNING (tenant_id, key_name) < (2, 'zzzz') AS before_tenant", $tables, $rowIdColumn)['returning'], [['before_tenant' => 0]]],
    'update returning row-value NOT IN expression unknown with null tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE app_settings SET status = NULL WHERE setting_id = 7 RETURNING (tenant_id, status) NOT IN ((2, NULL)) AS not_unknown", $tables, $rowIdColumn)['returning'], [['not_unknown' => null]]],
    'update returning expression parse preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['returning'], "setting_id, key_name || ':' || status AS next_label, bytes + tenant_id AS next_weight, (load_policy, status) = ('yes', 'migrated') AS migrated_tuple, status IS NOT NULL AS status_present"],
    'malformed returning expression without alias rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 1 RETURNING key_name || status", $tables, $rowIdColumn), InvalidArgumentException::class],
    'malformed returning row-value arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 1 RETURNING (tenant_id, key_name) = (1) AS bad", $tables, $rowIdColumn), InvalidArgumentException::class],
    'malformed returning row-value column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 1 RETURNING (tenant_id + 1, key_name) = (1, 'base_url') AS bad", $tables, $rowIdColumn), InvalidArgumentException::class],
    'malformed returning unsupported literal rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE setting_id = 1 RETURNING json(status) AS bad", $tables, $rowIdColumn), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['row value update delete returning current source next117 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
