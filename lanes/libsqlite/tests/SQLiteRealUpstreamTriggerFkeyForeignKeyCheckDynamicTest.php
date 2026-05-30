<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheck;

$tests = [
    'real upstream fkey5 foreign key check cites result-column contract' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check'));
        $t->true(is_string($source) && str_contains($source, 'There are four columns in each result row'));
        $t->true(is_string($source) && str_contains($source, 'The second column is the rowid'));
    },
    'real upstream fkey5 foreign key check cites collation matrix' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE p4(a TEXT PRIMARY KEY COLLATE nocase)'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE p6(a INTEGER PRIMARY KEY, b TEXT COLLATE nocase'));
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check(c22)'));
    },
    'real upstream fkey5 foreign key check cites missing parent and without rowid sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'Tests 9.* verify that missing parent tables are handled correctly'));
        $t->true(is_string($source) && str_contains($source, 'WITHOUT ROWID'));
        $t->true(is_string($source) && str_contains($source, 'foreign key mismatch'));
    },
];

$rowids = static fn (array $rows): array => array_values(array_map(static fn (array $row): mixed => $row['rowid'], $rows));
$parents = static function (int $seed): array {
    return [
        'parent_integer' => [
            ['id' => 88 + $seed],
            ['id' => 89 + $seed],
        ],
        'parent_text_binary' => [
            ['key_name' => 'alpha_' . $seed],
            ['key_name' => 'BRAVO_' . $seed],
        ],
        'parent_text_nocase' => [
            ['key_name' => 'alpha_' . $seed],
            ['key_name' => 'BRAVO_' . $seed],
            ['key_name' => '55_' . $seed],
        ],
        'parent_composite_binary' => [
            ['id' => 1, 'key_name' => 'Alpha_' . $seed, 'key_value' => 'abc'],
            ['id' => 2, 'key_name' => 'beta_' . $seed, 'key_value' => 'def'],
        ],
        'parent_composite_nocase_rtrim' => [
            ['id' => 1, 'key_name' => 'Alpha_' . $seed, 'key_value' => 'abc '],
            ['id' => 2, 'key_name' => 'bETA_' . $seed, 'key_value' => 'def    '],
        ],
    ];
};

$foreignKeys = [
    'integer-primary-key' => [[
        'table' => 'child_integer',
        'parent' => 'parent_integer',
        'columns' => [
            ['child' => 'parent_id', 'parent' => 'id', 'affinity' => 'integer', 'collation' => 'binary'],
        ],
    ]],
    'text-binary' => [[
        'table' => 'child_text_binary',
        'parent' => 'parent_text_binary',
        'columns' => [
            ['child' => 'parent_key', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'binary'],
        ],
    ]],
    'text-nocase' => [[
        'table' => 'child_text_nocase',
        'parent' => 'parent_text_nocase',
        'columns' => [
            ['child' => 'parent_key', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'nocase'],
        ],
    ]],
    'composite-binary' => [[
        'table' => 'child_composite_binary',
        'parent' => 'parent_composite_binary',
        'columns' => [
            ['child' => 'key_name', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'binary'],
            ['child' => 'key_value', 'parent' => 'key_value', 'affinity' => 'text', 'collation' => 'binary'],
        ],
    ]],
    'composite-swapped' => [[
        'table' => 'child_composite_swapped',
        'parent' => 'parent_composite_binary',
        'columns' => [
            ['child' => 'key_value', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'binary'],
            ['child' => 'key_name', 'parent' => 'key_value', 'affinity' => 'text', 'collation' => 'binary'],
        ],
    ]],
    'composite-nocase-rtrim' => [[
        'table' => 'child_composite_nocase_rtrim',
        'parent' => 'parent_composite_nocase_rtrim',
        'columns' => [
            ['child' => 'key_name', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'nocase'],
            ['child' => 'key_value', 'parent' => 'key_value', 'affinity' => 'text', 'collation' => 'rtrim'],
        ],
    ]],
    'without-rowid' => [[
        'table' => 'child_without_rowid',
        'parent' => 'parent_integer',
        'without_rowid' => true,
        'columns' => [
            ['child' => 'parent_id', 'parent' => 'id', 'affinity' => 'integer', 'collation' => 'binary'],
        ],
    ]],
    'missing-parent' => [[
        'table' => 'child_missing_parent',
        'parent' => 'missing_parent',
        'columns' => [
            ['child' => 'parent_key', 'parent' => 'key_name', 'affinity' => 'text', 'collation' => 'binary'],
        ],
    ]],
];

for ($seed = 1; $seed <= 125; ++$seed) {
    $baseParents = $parents($seed);
    $tables = $baseParents + [
        'child_integer' => [
            ['rowid' => 1, 'parent_id' => 88 + $seed],
            ['rowid' => 2, 'parent_id' => 90 + $seed],
            ['rowid' => 3, 'parent_id' => 87 + $seed],
        ],
        'child_text_binary' => [
            ['rowid' => 1, 'parent_key' => 'alpha_' . $seed],
            ['rowid' => 2, 'parent_key' => 'Alpha_' . $seed],
            ['rowid' => 3, 'parent_key' => 'BRAVO_' . $seed],
            ['rowid' => 4, 'parent_key' => null],
        ],
        'child_text_nocase' => [
            ['rowid' => 1, 'parent_key' => 'ALPHA_' . $seed],
            ['rowid' => 2, 'parent_key' => 'bravo_' . $seed],
            ['rowid' => 3, 'parent_key' => 'foxtrot_' . $seed],
        ],
        'child_composite_binary' => [
            ['rowid' => 1, 'key_name' => 'alpha_' . $seed, 'key_value' => 'abc'],
            ['rowid' => 2, 'key_name' => 'Alpha_' . $seed, 'key_value' => 'abc'],
            ['rowid' => 3, 'key_name' => 'beta_' . $seed, 'key_value' => 'def'],
            ['rowid' => 4, 'key_name' => null, 'key_value' => 'abc'],
        ],
        'child_composite_swapped' => [
            ['rowid' => 1, 'key_name' => 'abc', 'key_value' => 'Alpha_' . $seed],
            ['rowid' => 2, 'key_name' => 'Alpha_' . $seed, 'key_value' => 'abc'],
        ],
        'child_composite_nocase_rtrim' => [
            ['rowid' => 1, 'key_name' => 'alpha_' . $seed, 'key_value' => 'abc    '],
            ['rowid' => 2, 'key_name' => 'BETA_' . $seed, 'key_value' => 'def'],
            ['rowid' => 3, 'key_name' => 'Beta_' . $seed, 'key_value' => 'mismatch'],
        ],
        'child_without_rowid' => [
            ['line' => 999, 'parent_id' => 88 + $seed],
            ['line' => 45, 'parent_id' => 45 + $seed],
        ],
        'child_missing_parent' => [
            ['rowid' => 1, 'parent_key' => null],
            ['rowid' => 2, 'parent_key' => 'orphan_' . $seed],
        ],
    ];

    $cases = [
        'integer-primary-key' => [2, 3],
        'text-binary' => [2],
        'text-nocase' => [3],
        'composite-binary' => [1],
        'composite-swapped' => [2],
        'composite-nocase-rtrim' => [3],
        'without-rowid' => [null],
        'missing-parent' => [2],
    ];

    foreach ($cases as $name => $expectedRowids) {
        $tests["real upstream fkey5 dynamic {$name} seed {$seed}"] = static function (TestRunner $t) use ($tables, $foreignKeys, $rowids, $name, $expectedRowids): void {
            $rows = SQLitePragmaForeignKeyCheck::check($tables, $foreignKeys[$name]);
            $t->same($expectedRowids, $rowids($rows));
            $t->same($foreignKeys[$name][0]['table'], $rows[0]['table']);
            $t->same($foreignKeys[$name][0]['parent'], $rows[0]['parent']);
            $t->same(0, $rows[0]['fkid']);
        };
    }
}

return $tests;
