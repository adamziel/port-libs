<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream fkey5 corpus cites foreign key check table block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_key_check(c1);'));
        $t->true(is_string($source) && str_contains($source, 'EVIDENCE-OF: R-45728-08709 There are four columns in each result row'));
    },
    'real upstream fkey5 corpus cites without rowid and mismatch blocks' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, 'PRIMARY KEY(master)'));
        $t->true(is_string($source) && str_contains($source, 'foreign key mismatch - "c11" referencing "tt"'));
    },
    'real upstream fkey5 corpus cites schema scoped pragma virtual table block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test');
        $t->true(is_string($source) && str_contains($source, "PRAGMA foreign_key_check=t1;"));
        $t->true(is_string($source) && str_contains($source, "pragma_foreign_key_check('t1','aux')"));
    },
];

for ($i = 1; $i <= 80; ++$i) {
    $schema = $i % 5 === 0 ? 'aux' : 'main';
    $parents = [
        [
            'name' => 'p_scalar',
            'schema' => $schema,
            'columns' => [['name' => 'id', 'collation' => 'binary']],
            'primary_key' => ['id'],
            'rows' => [
                ['id' => '88'],
                ['id' => '89'],
                ['id' => 'alpha'],
            ],
        ],
        [
            'name' => 'p_nocase',
            'schema' => $schema,
            'columns' => [['name' => 'id', 'collation' => 'nocase']],
            'primary_key' => ['id'],
            'rows' => [
                ['id' => 'Alpha'],
                ['id' => 'BRAVO'],
            ],
        ],
        [
            'name' => 'p_composite',
            'schema' => $schema,
            'columns' => [
                ['name' => 'k1', 'collation' => 'nocase'],
                ['name' => 'k2', 'collation' => 'rtrim'],
                ['name' => 'payload', 'collation' => 'binary'],
            ],
            'unique' => [['k1', 'k2']],
            'rows' => [
                ['k1' => 'Alpha', 'k2' => 'abc   ', 'payload' => 'one'],
                ['k1' => 'Beta', 'k2' => 'def', 'payload' => 'two'],
            ],
        ],
        [
            'name' => 'tt',
            'schema' => $schema,
            'columns' => [['name' => 'y', 'collation' => 'binary']],
            'rows' => [['y' => 'not-unique']],
        ],
    ];

    $children = [
        [
            'name' => 'c_scalar',
            'schema' => $schema,
            'columns' => [['name' => 'x']],
            'rows' => [
                ['rowid' => 1, 'x' => '90'],
                ['rowid' => 2, 'x' => null],
                ['rowid' => 3, 'x' => '88'],
                ['rowid' => 4, 'x' => $i % 2 === 0 ? '89' : '87'],
            ],
            'foreign_key' => [
                'parent_table' => 'p_scalar',
                'parent_schema' => $schema,
                'child_columns' => ['x'],
                'parent_columns' => ['id'],
            ],
        ],
        [
            'name' => 'c_nocase',
            'schema' => $schema,
            'columns' => [['name' => 'x', 'collation' => 'binary']],
            'rows' => [
                ['rowid' => 1, 'x' => 'alpha'],
                ['rowid' => 2, 'x' => 'bravo'],
                ['rowid' => 3, 'x' => 'foxtrot'],
            ],
            'foreign_key' => [
                'parent_table' => 'p_nocase',
                'parent_schema' => $schema,
                'child_columns' => ['x'],
                'parent_columns' => ['id'],
            ],
        ],
        [
            'name' => 'c_composite',
            'schema' => $schema,
            'columns' => [['name' => 'x'], ['name' => 'y']],
            'rows' => [
                ['rowid' => 1, 'x' => 'alpha', 'y' => 'abc'],
                ['rowid' => 2, 'x' => 'BETA', 'y' => 'def     '],
                ['rowid' => 3, 'x' => 'alpha', 'y' => 'zzz'],
                ['rowid' => 4, 'x' => null, 'y' => 'abc'],
            ],
            'foreign_key' => [
                'parent_table' => 'p_composite',
                'parent_schema' => $schema,
                'child_columns' => ['x', 'y'],
                'parent_columns' => ['k1', 'k2'],
            ],
        ],
        [
            'name' => 'c_missing',
            'schema' => $schema,
            'columns' => [['name' => 'x']],
            'rows' => [
                ['rowid' => 1, 'x' => null],
                ['rowid' => 2, 'x' => 'missing-' . $i],
            ],
            'foreign_key' => [
                'parent_table' => 's1',
                'parent_schema' => $schema,
                'child_columns' => ['x'],
                'parent_columns' => ['id'],
            ],
        ],
        [
            'name' => 'c_without_rowid',
            'schema' => $schema,
            'columns' => [['name' => 'master'], ['name' => 'line']],
            'without_rowid' => true,
            'rows' => [
                ['master' => '88', 'line' => 999],
                ['master' => '45', 'line' => 45],
            ],
            'foreign_key' => [
                'parent_table' => 'p_scalar',
                'parent_schema' => $schema,
                'child_columns' => ['master'],
                'parent_columns' => ['id'],
            ],
        ],
    ];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children);
    $tablePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children, 'c_nocase', $schema);
    $missingPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children, 'c_missing', $schema);
    $case = 'fkey5 dynamic foreign key check corpus ' . $i;
    $scalarViolations = $i % 2 === 0 ? 1 : 2;
    $expectedTotal = $scalarViolations + 1 + 1 + 1 + 1;

    foreach ([
        'source' => 'fkey5.test fkey5-1.1..13.12',
        'operation' => 'pragma-foreign-key-check-corpus',
        'status' => 'check-ok',
        'schema_filter' => null,
        'table_filter' => null,
        'mismatch_error' => null,
        'violation_count' => $expectedTotal,
        'dependencies.0' => 'sqlite-fkey5-foreign-key-check-row-shape',
        'dependencies.1' => 'sqlite-fkey5-parent-key-unique-validation',
        'dependencies.2' => 'sqlite-fkey5-without-rowid-null-rowid',
        'dependencies.3' => 'sqlite-fkey5-schema-scoped-pragma-argument',
    ] as $path => $expected) {
        $tests[$case . ' full check ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' full check row shape has four pragma columns'] = static function (TestRunner $t) use ($plan): void {
        foreach ($plan()['violation_rows'] as $row) {
            $t->same(['table', 'rowid', 'parent', 'fkid'], array_keys($row));
        }
    };
    $tests[$case . ' binary scalar parent rejects missing values only'] = static function (TestRunner $t) use ($plan, $scalarViolations): void {
        $rows = array_values(array_filter($plan()['violation_rows'], static fn (array $row): bool => $row['table'] === 'c_scalar'));
        $t->same($scalarViolations, count($rows));
        $t->same('p_scalar', $rows[0]['parent']);
    };
    $tests[$case . ' nocase parent collation accepts child case variants'] = static function (TestRunner $t) use ($tablePlan): void {
        $rows = $tablePlan()['violation_rows'];
        $t->same(1, count($rows));
        $t->same(3, $rows[0]['rowid']);
        $t->same('p_nocase', $rows[0]['parent']);
    };
    $tests[$case . ' composite parent applies parent collations by column'] = static function (TestRunner $t) use ($plan): void {
        $rows = array_values(array_filter($plan()['violation_rows'], static fn (array $row): bool => $row['table'] === 'c_composite'));
        $t->same(1, count($rows));
        $t->same(3, $rows[0]['rowid']);
    };
    $tests[$case . ' missing parent table ignores all-null child key'] = static function (TestRunner $t) use ($missingPlan): void {
        $rows = $missingPlan()['violation_rows'];
        $t->same(1, count($rows));
        $t->same(2, $rows[0]['rowid']);
        $t->same('s1', $rows[0]['parent']);
    };
    $tests[$case . ' without rowid violation reports null rowid'] = static function (TestRunner $t) use ($plan): void {
        $rows = array_values(array_filter($plan()['violation_rows'], static fn (array $row): bool => $row['table'] === 'c_without_rowid'));
        $t->same(1, count($rows));
        $t->same(null, $rows[0]['rowid']);
    };
    $tests[$case . ' schema scoped table filter mirrors pragma argument'] = static function (TestRunner $t) use ($tablePlan, $schema): void {
        $t->same($schema, $tablePlan()['schema_filter']);
        $t->same('c_nocase', $tablePlan()['table_filter']);
        $t->same(1, $tablePlan()['violation_count']);
    };
}

for ($i = 1; $i <= 30; ++$i) {
    $parents = [[
        'name' => 'tt',
        'columns' => [['name' => 'y']],
        'rows' => [['y' => 'duplicate'], ['y' => 'duplicate']],
    ]];
    $children = [[
        'name' => 'c11',
        'columns' => [['name' => 'x']],
        'rows' => [['rowid' => 1, 'x' => 'duplicate']],
        'foreign_key' => [
            'parent_table' => 'tt',
            'child_columns' => ['x'],
            'parent_columns' => ['y'],
        ],
    ]];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus($parents, $children);
    $case = 'fkey5-11 foreign key mismatch dynamic ' . $i;
    foreach ([
        'source' => 'fkey5.test fkey5-1.1..13.12',
        'operation' => 'pragma-foreign-key-check-corpus',
        'status' => 'schema-mismatch',
        'mismatch_error' => 'foreign key mismatch - "c11" referencing "tt"',
        'violation_count' => 0,
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
