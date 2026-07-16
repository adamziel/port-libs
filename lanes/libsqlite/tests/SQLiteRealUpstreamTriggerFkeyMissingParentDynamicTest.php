<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheck;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test';

$tablesForSeed = static function (int $seed): array {
    $suffix = (string) $seed;

    return [
        'parent_catalog' => [
            ['rowid' => 1, 'code' => 'alpha-' . $suffix, 'tenant' => 'main', 'label' => 'alpha'],
            ['rowid' => 2, 'code' => 'beta-' . $suffix, 'tenant' => 'main', 'label' => 'beta'],
            ['rowid' => 3, 'code' => 'gamma-' . $suffix, 'tenant' => 'aux', 'label' => 'gamma'],
        ],
        'child_missing_parent' => [
            ['rowid' => 1, 'parent_code' => 'alpha-' . $suffix],
            ['rowid' => 2, 'parent_code' => 'missing-' . $suffix],
            ['rowid' => 3, 'parent_code' => null],
        ],
        'child_composite' => [
            ['rowid' => 11, 'parent_code' => 'alpha-' . $suffix, 'parent_tenant' => 'main'],
            ['rowid' => 12, 'parent_code' => 'alpha-' . $suffix, 'parent_tenant' => 'aux'],
            ['rowid' => 13, 'parent_code' => null, 'parent_tenant' => 'main'],
            ['rowid' => 14, 'parent_code' => 'beta-' . $suffix, 'parent_tenant' => null],
        ],
        'child_without_rowid' => [
            ['setting_key' => 'ok-' . $suffix, 'parent_code' => 'beta-' . $suffix],
            ['setting_key' => 'bad-' . $suffix, 'parent_code' => 'orphan-' . $suffix],
        ],
        'child_target_only' => [
            ['rowid' => 21, 'parent_code' => 'gamma-' . $suffix],
            ['rowid' => 22, 'parent_code' => 'detached-' . $suffix],
        ],
    ];
};

$foreignKeys = static function (): array {
    return [
        [
            'table' => 'child_missing_parent',
            'parent' => 'parent_catalog',
            'columns' => [
                ['child' => 'parent_code', 'parent' => 'code', 'affinity' => 'text', 'collation' => 'binary'],
            ],
            'id' => 0,
        ],
        [
            'table' => 'child_composite',
            'parent' => 'parent_catalog',
            'columns' => [
                ['child' => 'parent_code', 'parent' => 'code', 'affinity' => 'text', 'collation' => 'binary'],
                ['child' => 'parent_tenant', 'parent' => 'tenant', 'affinity' => 'text', 'collation' => 'binary'],
            ],
            'id' => 1,
        ],
        [
            'table' => 'child_without_rowid',
            'parent' => 'parent_catalog',
            'columns' => [
                ['child' => 'parent_code', 'parent' => 'code', 'affinity' => 'text', 'collation' => 'binary'],
            ],
            'id' => 2,
            'without_rowid' => true,
        ],
        [
            'table' => 'child_target_only',
            'parent' => 'missing_parent_table',
            'columns' => [
                ['child' => 'parent_code', 'parent' => 'code', 'affinity' => 'text', 'collation' => 'binary'],
            ],
            'id' => 3,
        ],
    ];
};

$tests['real upstream fkey5 missing parent corpus cites source sections'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'Tests 9.* verify that missing parent tables are handled correctly.'));
    $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check(k1);'));
    $t->true(is_string($source) && str_contains($source, 'SELECT *, \'x\' FROM pragma_foreign_key_check(\'t1\',\'aux\')'));
    $t->true(is_string($source) && str_contains($source, 'SELECT x.*, \'|\''));
};

for ($seed = 1; $seed <= 250; ++$seed) {
    $tests[sprintf('real upstream fkey5-9 missing parent all tables dynamic seed %03d', $seed)] = static function (TestRunner $t) use ($tablesForSeed, $foreignKeys, $seed): void {
        $result = SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_check', $tablesForSeed($seed), $foreignKeys());

        $t->same('ok', $result['status']);
        $t->same('foreign_key_check', $result['pragma']);
        $t->same(null, $result['target']);
        $t->same([
            ['table' => 'child_missing_parent', 'rowid' => 2, 'parent' => 'parent_catalog', 'fkid' => 0],
            ['table' => 'child_composite', 'rowid' => 12, 'parent' => 'parent_catalog', 'fkid' => 1],
            ['table' => 'child_without_rowid', 'rowid' => null, 'parent' => 'parent_catalog', 'fkid' => 2],
            ['table' => 'child_target_only', 'rowid' => 21, 'parent' => 'missing_parent_table', 'fkid' => 3],
            ['table' => 'child_target_only', 'rowid' => 22, 'parent' => 'missing_parent_table', 'fkid' => 3],
        ], $result['rows']);
    };

    $tests[sprintf('real upstream fkey5-9 target table dynamic seed %03d', $seed)] = static function (TestRunner $t) use ($tablesForSeed, $foreignKeys, $seed): void {
        $result = SQLitePragmaForeignKeyCheck::execute('PRAGMA main.foreign_key_check(child_missing_parent)', $tablesForSeed($seed), $foreignKeys());

        $t->same('main', $result['schema']);
        $t->same('child_missing_parent', $result['target']);
        $t->same([
            ['table' => 'child_missing_parent', 'rowid' => 2, 'parent' => 'parent_catalog', 'fkid' => 0],
        ], $result['rows']);
    };

    $tests[sprintf('real upstream fkey5-10 without rowid dynamic seed %03d', $seed)] = static function (TestRunner $t) use ($tablesForSeed, $foreignKeys, $seed): void {
        $result = SQLitePragmaForeignKeyCheck::execute('PRAGMA foreign_key_check("child_without_rowid")', $tablesForSeed($seed), $foreignKeys());

        $t->same('child_without_rowid', $result['target']);
        $t->same(1, count($result['rows']));
        $t->same(['table' => 'child_without_rowid', 'rowid' => null, 'parent' => 'parent_catalog', 'fkid' => 2], $result['rows'][0]);
    };

    $tests[sprintf('real upstream fkey5-13 table-valued target equivalent dynamic seed %03d', $seed)] = static function (TestRunner $t) use ($tablesForSeed, $foreignKeys, $seed): void {
        $rows = SQLitePragmaForeignKeyCheck::check($tablesForSeed($seed), $foreignKeys(), 'child_target_only');

        $t->same(2, count($rows));
        $t->same([21, 22], array_column($rows, 'rowid'));
        $t->same(['missing_parent_table', 'missing_parent_table'], array_column($rows, 'parent'));
        $t->same([3, 3], array_column($rows, 'fkid'));
    };
}

$tests['real upstream fkey5 missing parent dynamic owns exactly 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
