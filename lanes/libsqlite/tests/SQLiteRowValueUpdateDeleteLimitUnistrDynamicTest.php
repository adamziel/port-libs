<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    return [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 10, 'key_value' => 'A'],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 20, 'key_value' => 'B'],
            ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 30, 'key_value' => 'C'],
            ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 40, 'key_value' => 'D'],
            ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 50, 'key_value' => 'E'],
            ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 60, 'key_value' => 'F'],
            ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'alpha', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 70, 'key_value' => 'G'],
            ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'beta', 'load_policy' => 'lazy', 'state' => 'queued', 'bytes' => 80, 'key_value' => 'H'],
        ],
        'app_setting_targets' => [
            ['target_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'action' => 'refresh', 'priority' => 80],
            ['target_id' => 2, 'tenant_id' => 2, 'key_name' => 'alpha', 'action' => 'refresh', 'priority' => 70],
            ['target_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'action' => 'refresh', 'priority' => 60],
            ['target_id' => 4, 'tenant_id' => 2, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 50],
            ['target_id' => 5, 'tenant_id' => 3, 'key_name' => 'beta', 'action' => 'cleanup', 'priority' => 90],
            ['target_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'action' => 'cleanup', 'priority' => 80],
            ['target_id' => 7, 'tenant_id' => 1, 'key_name' => 'beta', 'action' => 'cleanup', 'priority' => 70],
            ['target_id' => 8, 'tenant_id' => 3, 'key_name' => 'alpha', 'action' => 'cleanup', 'priority' => 60],
        ],
    ];
};

$execute = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);
$stateById = static fn (array $result): array => array_column($result['tables']['app_settings'], 'state', 'setting_id');
$remainingIds = static fn (array $result): array => array_column($result['tables']['app_settings'], 'setting_id');

$updateOuterSql = "UPDATE app_settings SET state = 'decoded' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes DESC LIMIT length(unistr('\\u03b1\\u03b2\\u03b3')) OFFSET typeof(unistr_quote(unistr('\\u000a')))='text'";
$deleteCommaSql = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT unicode(unistr('\\0001')), unicode(unistr('\\u0002'))";
$updateSubquerySql = "UPDATE app_settings SET state = 'tuple_decoded' WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'refresh' ORDER BY priority DESC LIMIT length(unistr('\\+000041\\u0042')) OFFSET length(unistr('\\u2603'))) RETURNING setting_id, tenant_id, key_name, state ORDER BY setting_id LIMIT -1";
$deleteSubquerySql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE action = 'cleanup' ORDER BY priority DESC LIMIT unicode(unistr('\\0003')) OFFSET typeof(unistr_quote(unistr('\\u0009')))='text') RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
$updateNullSql = "UPDATE app_settings SET state = 'null_fallback' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT coalesce(unicode(unistr(NULL)), unicode(unistr('\\0003'))) OFFSET coalesce(length(unistr(NULL)), unicode(unistr('\\0001')))";
$deleteQuoteSql = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes DESC LIMIT (unistr_quote(unistr('G\\u00e4ste')) = quote(unistr('G\\u00e4ste'))) + unicode(unistr('\\0001')) OFFSET unicode(substr(unistr('\\0001ABC'), 1, 1))";

$tests = [];

$tests['rowvalue update delete limit unistr dynamic cites upstream scalar source'] = static function (TestRunner $t): void {
    $t->contains('/test/func9.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/func9.test');
    $t->contains('func9-200', "func9-200 SELECT unistr('G\\u00e4ste')");
    $t->contains('func9-210', "func9-210 SELECT unistr_quote(unistr('G\\u00e4ste'))");
    $t->contains('/src/func.c', '/home/claude/port-libs/.upstream-cache/libsqlite/src/func.c');
    $t->contains('invalid Unicode escape', 'unistr_error invalid Unicode escape');
};

$tests['rowvalue update outer limit uses decoded unistr text length'] = static function (TestRunner $t) use ($execute, $stateById, $updateOuterSql): void {
    $parsed = SQLiteUpdateDeleteReturningSql::parse($updateOuterSql);
    $result = $execute($updateOuterSql);

    $t->same(3, $parsed['limit']);
    $t->same(1, $parsed['offset']);
    $t->same([7, 6, 5], $result['plan']->selectedIds);
    $t->same([5, 6, 7], $result['plan']->mutationIds);
    $t->same([5, 6, 7], array_column($result['returning'], 'setting_id'));
    $t->same(['decoded', 'decoded', 'decoded'], array_column($result['returning'], 'state'));
    $t->same('queued', $stateById($result)[8]);
    $t->same('decoded', $stateById($result)[7]);
    $t->same(3, $result['plan']->toArray()['limit']);
    $t->same(1, $result['plan']->toArray()['offset']);
};

$tests['rowvalue delete comma limit uses bare and u-form unistr escapes'] = static function (TestRunner $t) use ($execute, $remainingIds, $deleteCommaSql): void {
    $parsed = SQLiteUpdateDeleteReturningSql::parse($deleteCommaSql);
    $result = $execute($deleteCommaSql);

    $t->same(2, $parsed['limit']);
    $t->same(1, $parsed['offset']);
    $t->same([2, 3], $result['plan']->selectedIds);
    $t->same([2, 3], $result['plan']->mutationIds);
    $t->same([2, 3], array_column($result['returning'], 'setting_id'));
    $t->same([1, 4, 5, 6, 7, 8], $remainingIds($result));
    $t->same(2, $result['plan']->toArray()['limit']);
    $t->same(1, $result['plan']->toArray()['offset']);
};

$tests['rowvalue update tuple subquery limit uses plus and u-form unistr escapes'] = static function (TestRunner $t) use ($execute, $stateById, $updateSubquerySql): void {
    $result = $execute($updateSubquerySql);

    $t->same([3, 4], $result['plan']->selectedIds);
    $t->same([3, 4], $result['plan']->mutationIds);
    $t->same([3, 4], array_column($result['returning'], 'setting_id'));
    $t->same([1, 2], array_column($result['returning'], 'tenant_id'));
    $t->same(['gamma', 'alpha'], array_column($result['returning'], 'key_name'));
    $t->same(['tuple_decoded', 'tuple_decoded'], array_column($result['returning'], 'state'));
    $t->same('tuple_decoded', $stateById($result)[3]);
    $t->same('tuple_decoded', $stateById($result)[4]);
    $t->same('queued', $stateById($result)[5]);
};

$tests['rowvalue delete tuple subquery limit uses unistr quote offset predicate'] = static function (TestRunner $t) use ($execute, $remainingIds, $deleteSubquerySql): void {
    $result = $execute($deleteSubquerySql);

    $t->same([2, 6, 7], $result['plan']->selectedIds);
    $t->same([2, 6, 7], $result['plan']->mutationIds);
    $t->same([2, 6, 7], array_column($result['returning'], 'setting_id'));
    $t->same([1, 2, 3], array_column($result['returning'], 'tenant_id'));
    $t->same(['beta', 'gamma', 'alpha'], array_column($result['returning'], 'key_name'));
    $t->same([1, 3, 4, 5, 8], $remainingIds($result));
    $t->same(-1, $result['plan']->toArray()['limit']);
    $t->same(0, $result['plan']->toArray()['offset']);
};

$tests['rowvalue update dynamic limit preserves unistr null propagation through coalesce'] = static function (TestRunner $t) use ($execute, $stateById, $updateNullSql): void {
    $parsed = SQLiteUpdateDeleteReturningSql::parse($updateNullSql);
    $result = $execute($updateNullSql);

    $t->same(3, $parsed['limit']);
    $t->same(1, $parsed['offset']);
    $t->same([2, 3, 4], $result['plan']->selectedIds);
    $t->same([2, 3, 4], array_column($result['returning'], 'setting_id'));
    $t->same(['null_fallback', 'null_fallback', 'null_fallback'], array_column($result['returning'], 'state'));
    $t->same('queued', $stateById($result)[1]);
};

$tests['rowvalue delete dynamic limit can compare unistr quote to quote output'] = static function (TestRunner $t) use ($execute, $remainingIds, $deleteQuoteSql): void {
    $parsed = SQLiteUpdateDeleteReturningSql::parse($deleteQuoteSql);
    $result = $execute($deleteQuoteSql);

    $t->same(2, $parsed['limit']);
    $t->same(1, $parsed['offset']);
    $t->same([7, 6], $result['plan']->selectedIds);
    $t->same([6, 7], array_column($result['returning'], 'setting_id'));
    $t->same([1, 2, 3, 4, 5, 8], $remainingIds($result));
    $t->same(2, $result['plan']->toArray()['limit']);
};

$tests['rowvalue dynamic limit rejects invalid unistr escapes and arity'] = static function (TestRunner $t) use ($execute): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $execute("DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id LIMIT unicode(unistr('\\x1234'))"));
    $t->throws(InvalidArgumentException::class, static fn (): array => $execute("DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id LIMIT length(unistr())"));
    $t->throws(InvalidArgumentException::class, static fn (): array => $execute("DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id LIMIT length(unistr_quote('a','b'))"));
};

return $tests;
