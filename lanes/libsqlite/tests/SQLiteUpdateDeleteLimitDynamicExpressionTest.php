<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'load_policy' => 'lazy', 'status' => 'queued', 'bytes' => 5, 'key_value' => 'a'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'load_policy' => 'lazy', 'status' => 'queued', 'bytes' => 6, 'key_value' => 'b'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'load_policy' => 'eager', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'c'],
    ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'load_policy' => 'lazy', 'status' => 'queued', 'bytes' => 8, 'key_value' => 'd'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'load_policy' => 'lazy', 'status' => 'done', 'bytes' => 9, 'key_value' => 'e'],
    ['setting_id' => 6, 'tenant_id' => 3, 'key_name' => 'delta', 'load_policy' => 'lazy', 'status' => 'queued', 'bytes' => 10, 'key_value' => 'f'],
];

$tables = ['app_settings' => $rows];

$deleteSql = "DELETE FROM app_settings WHERE (load_policy, status) = ('lazy', 'queued') RETURNING setting_id, key_name ORDER BY bytes DESC LIMIT length('abcd') - CASE 1 WHEN 1 THEN 2 ELSE 0 END OFFSET length('x')";
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables, 'setting_id');

$updateSql = "UPDATE app_settings SET (status, key_value) = ('active', key_name || ':active') WHERE (tenant_id, load_policy) IN ((1, 'lazy'), (2, 'lazy'), (3, 'lazy')) RETURNING setting_id, status, key_value ORDER BY bytes DESC LIMIT CASE 2 WHEN 2 THEN length('xx') ELSE 1 END OFFSET CASE 0 WHEN 1 THEN 9 ELSE 1 END";
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables, 'setting_id');

$commaDeleteSql = "DELETE FROM app_settings WHERE (load_policy, status) = ('lazy', 'queued') RETURNING setting_id ORDER BY setting_id LIMIT CASE 1 WHEN 1 THEN 1 ELSE 0 END, length('abc') - 1";
$commaDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($commaDeleteSql, $tables, 'setting_id');

$cases = [
    'delete dynamic limit parses limit value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['limit'], 2],
    'delete dynamic limit parses offset value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($deleteSql)['offset'], 1],
    'delete dynamic limit selected ids honor ordered offset' => [static fn (): mixed => $delete()['plan']->selectedIds, [4, 2]],
    'delete dynamic limit mutation ids remain source order' => [static fn (): mixed => $delete()['plan']->mutationIds, [2, 4]],
    'delete dynamic limit returning rows use mutation source order' => [static fn (): mixed => array_column($delete()['returning'], 'setting_id'), [2, 4]],
    'delete dynamic limit leaves skipped highest priority row' => [static fn (): mixed => in_array(6, array_column($delete()['tables']['app_settings'], 'setting_id'), true), true],
    'delete dynamic limit removes selected rows only' => [static fn (): mixed => array_column($delete()['tables']['app_settings'], 'setting_id'), [1, 3, 5, 6]],
    'delete dynamic limit plan summary keeps limit' => [static fn (): mixed => $delete()['plan']->toArray()['limit'], 2],
    'delete dynamic limit plan summary keeps offset' => [static fn (): mixed => $delete()['plan']->toArray()['offset'], 1],
    'delete dynamic limit order expression remains bytes desc' => [static fn (): mixed => $delete()['plan']->toArray()['order_by'], [['column' => 'bytes', 'direction' => 'DESC']]],
    'update dynamic limit parses limit value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['limit'], 2],
    'update dynamic limit parses offset value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($updateSql)['offset'], 1],
    'update dynamic limit selected ids honor ordered offset' => [static fn (): mixed => $update()['plan']->selectedIds, [5, 4]],
    'update dynamic limit mutation ids remain source order' => [static fn (): mixed => $update()['plan']->mutationIds, [4, 5]],
    'update dynamic limit returning ids use mutation source order' => [static fn (): mixed => array_column($update()['returning'], 'setting_id'), [4, 5]],
    'update dynamic limit returning statuses are updated' => [static fn (): mixed => array_column($update()['returning'], 'status'), ['active', 'active']],
    'update dynamic limit assignment sees source key names' => [static fn (): mixed => array_column($update()['returning'], 'key_value'), ['alpha:active', 'beta:active']],
    'update dynamic limit leaves skipped highest bytes unchanged' => [static fn (): mixed => array_column($update()['tables']['app_settings'], 'status', 'setting_id')[6], 'queued'],
    'update dynamic limit mutates selected tenant two rows' => [static fn (): mixed => [array_column($update()['tables']['app_settings'], 'status', 'setting_id')[4], array_column($update()['tables']['app_settings'], 'status', 'setting_id')[5]], ['active', 'active']],
    'update dynamic limit plan summary keeps callable assignments' => [static fn (): mixed => $update()['plan']->toArray()['assignments'], ['status' => 'callable', 'key_value' => 'callable']],
    'comma dynamic limit parses limit value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($commaDeleteSql)['limit'], 2],
    'comma dynamic limit parses offset value' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($commaDeleteSql)['offset'], 1],
    'comma dynamic limit selected ids apply offset count order' => [static fn (): mixed => $commaDelete()['plan']->selectedIds, [2, 4]],
    'comma dynamic limit returning ids use source order' => [static fn (): mixed => array_column($commaDelete()['returning'], 'setting_id'), [2, 4]],
    'dynamic limit null result rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE status = 'queued' RETURNING setting_id LIMIT CASE 1 WHEN 1 THEN NULL ELSE 1 END", $tables, 'setting_id'), InvalidArgumentException::class],
    'dynamic limit noninteger string rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM app_settings WHERE status = 'queued' RETURNING setting_id LIMIT length('abc') / 2", $tables, 'setting_id'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['update delete limit dynamic expression ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
