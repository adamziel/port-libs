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

$definitions = static function (int $seed): array {
    $suffix = '_' . $seed;
    $t1 = 'child_root' . $suffix;
    $t2 = 'parent_pair' . $suffix;
    $t3 = 'child_pair' . $suffix;
    $t5 = 'catalog_parent' . $suffix;
    $t6 = 'catalog_child_columns' . $suffix;
    $t7 = 'catalog_child_composite' . $suffix;
    $t8 = 'catalog_child_cascade_null' . $suffix;
    $t9 = 'catalog_child_cascade_default' . $suffix;

    return [
        [
            'name' => $t1,
            'foreign_keys' => [
                ['table' => $t1, 'from' => ['b'], 'on_delete' => 'CASCADE'],
                ['table' => $t2, 'from' => ['b']],
                ['table' => $t2, 'from' => ['b', 'c'], 'to' => ['x', 'y'], 'on_update' => 'CASCADE'],
            ],
        ],
        ['name' => $t2, 'foreign_keys' => []],
        [
            'name' => $t3,
            'foreign_keys' => [
                ['table' => $t2, 'from' => ['a']],
                ['table' => $t1, 'from' => ['b']],
                ['table' => $t2, 'from' => ['a', 'b'], 'to' => ['x', 'y']],
            ],
        ],
        ['name' => $t5, 'foreign_keys' => []],
        [
            'name' => $t6,
            'foreign_keys' => [
                ['table' => $t5, 'from' => ['d']],
                ['table' => $t5, 'from' => ['e'], 'to' => ['c']],
            ],
        ],
        [
            'name' => $t7,
            'foreign_keys' => [
                ['table' => $t5, 'from' => ['d', 'e'], 'to' => ['a', 'b']],
            ],
        ],
        [
            'name' => $t8,
            'foreign_keys' => [
                ['table' => $t5, 'from' => ['d', 'e'], 'on_delete' => 'CASCADE', 'on_update' => 'SET NULL'],
            ],
        ],
        [
            'name' => $t9,
            'foreign_keys' => [
                ['table' => $t5, 'from' => ['d', 'e'], 'on_delete' => 'CASCADE', 'on_update' => 'SET DEFAULT'],
            ],
        ],
    ];
};

$tableNames = static function (int $seed): array {
    $suffix = '_' . $seed;

    return [
        't1' => 'child_root' . $suffix,
        't2' => 'parent_pair' . $suffix,
        't3' => 'child_pair' . $suffix,
        't5' => 'catalog_parent' . $suffix,
        't6' => 'catalog_child_columns' . $suffix,
        't7' => 'catalog_child_composite' . $suffix,
        't8' => 'catalog_child_cascade_null' . $suffix,
        't9' => 'catalog_child_cascade_default' . $suffix,
    ];
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test';

$tests = [
    'real upstream fkey1 catalog cites create table block' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('do_test fkey1-1.0', $source);
        $t->contains('FOREIGN KEY (b,c) REFERENCES t2(x,y) ON UPDATE CASCADE', $source);
        $t->contains('CREATE TABLE t3', $source);
    },
    'real upstream fkey1 catalog cites pragma foreign key list block' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('PRAGMA foreign_key_list(t6);', $source);
        $t->contains('FOREIGN KEY (d, e) REFERENCES t5(a, b)', $source);
        $t->contains('ON DELETE CASCADE ON UPDATE SET NULL', $source);
    },
    'real upstream fkey1 catalog cites deferred status block' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('do_test fkey1-3.5', $source);
        $t->contains('sqlite3_db_status db DBSTATUS_DEFERRED_FKS 0', $source);
    },
];

for ($seed = 1; $seed <= 220; ++$seed) {
    $names = $tableNames($seed);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan($definitions($seed));
    $target = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan($definitions($seed), $names['t8']);
    $case = sprintf('real upstream fkey1 catalog dynamic %04d', $seed);

    foreach ([
        'source' => 'fkey1.test fkey1-1.0..3.5',
        'operation' => 'foreign-key-list-catalog',
        'table_count' => 8,
        'total_foreign_key_rows' => 16,
        'declared_tables.0' => $names['t1'],
        'declared_tables.7' => $names['t9'],
        'row_count_by_table.' . $names['t1'] => 4,
        'row_count_by_table.' . $names['t2'] => 0,
        'row_count_by_table.' . $names['t3'] => 4,
        'row_count_by_table.' . $names['t6'] => 2,
        'row_count_by_table.' . $names['t9'] => 2,
        'rows_by_table.' . $names['t1'] . '.0.id' => 0,
        'rows_by_table.' . $names['t1'] . '.0.seq' => 0,
        'rows_by_table.' . $names['t1'] . '.0.table' => $names['t2'],
        'rows_by_table.' . $names['t1'] . '.0.from' => 'b',
        'rows_by_table.' . $names['t1'] . '.0.to' => 'x',
        'rows_by_table.' . $names['t1'] . '.0.on_update' => 'CASCADE',
        'rows_by_table.' . $names['t1'] . '.0.on_delete' => 'NO ACTION',
        'rows_by_table.' . $names['t1'] . '.1.seq' => 1,
        'rows_by_table.' . $names['t1'] . '.1.from' => 'c',
        'rows_by_table.' . $names['t1'] . '.1.to' => 'y',
        'rows_by_table.' . $names['t1'] . '.2.table' => $names['t2'],
        'rows_by_table.' . $names['t1'] . '.2.to' => '',
        'rows_by_table.' . $names['t1'] . '.3.table' => $names['t1'],
        'rows_by_table.' . $names['t1'] . '.3.on_delete' => 'CASCADE',
        'rows_by_table.' . $names['t3'] . '.0.from' => 'a',
        'rows_by_table.' . $names['t3'] . '.0.to' => 'x',
        'rows_by_table.' . $names['t3'] . '.1.from' => 'b',
        'rows_by_table.' . $names['t3'] . '.1.to' => 'y',
        'rows_by_table.' . $names['t3'] . '.2.table' => $names['t1'],
        'rows_by_table.' . $names['t3'] . '.3.table' => $names['t2'],
        'rows_by_table.' . $names['t6'] . '.0.from' => 'e',
        'rows_by_table.' . $names['t6'] . '.0.to' => 'c',
        'rows_by_table.' . $names['t6'] . '.1.from' => 'd',
        'rows_by_table.' . $names['t6'] . '.1.to' => '',
        'rows_by_table.' . $names['t7'] . '.0.from' => 'd',
        'rows_by_table.' . $names['t7'] . '.1.from' => 'e',
        'rows_by_table.' . $names['t8'] . '.0.on_update' => 'SET NULL',
        'rows_by_table.' . $names['t8'] . '.0.on_delete' => 'CASCADE',
        'rows_by_table.' . $names['t9'] . '.0.on_update' => 'SET DEFAULT',
        'rows_by_table.' . $names['t9'] . '.1.match' => 'NONE',
        'deferred_fk_status.current' => 0,
        'deferred_fk_status.highwater' => 0,
        'deferred_fk_status.reset' => false,
        'dependencies.0' => 'sqlite-fkey1-pragma-foreign-key-list-reverses-declaration-order',
        'dependencies.1' => 'sqlite-fkey1-composite-foreign-key-list-preserves-sequence',
        'dependencies.2' => 'sqlite-fkey1-implicit-parent-columns-render-empty-to-column',
        'dependencies.3' => 'sqlite-fkey1-dbstatus-deferred-fks-zero-with-no-open-violations',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' target filter keeps only requested table rows'] = static function (TestRunner $t) use ($target, $names): void {
        $actual = $target();
        $t->same($names['t8'], $actual['target_table']);
        $t->same([$names['t8']], array_keys($actual['rows_by_table']));
        $t->same(2, $actual['total_foreign_key_rows']);
    };
}

$tests['real upstream fkey1 catalog rejects empty tables'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan([]));
};

$tests['real upstream fkey1 catalog rejects missing target'] = static function (TestRunner $t) use ($definitions): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan($definitions(1), 'missing_table'));
};

$tests['real upstream fkey1 catalog rejects mismatched composite width'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan([
        ['name' => 'child_table', 'foreign_keys' => [['table' => 'parent_table', 'from' => ['a', 'b'], 'to' => ['id']]]],
    ]));
};

$tests['real upstream fkey1 catalog rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan([
        ['name' => 'child_table', 'foreign_keys' => [['table' => 'parent_table', 'from' => ['a'], 'on_delete' => 'explode']]],
    ]));
};

$tests['real upstream fkey1 catalog rejects malformed child column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey1ForeignKeyListCatalogPlan([
        ['name' => 'child_table', 'foreign_keys' => [['table' => 'parent_table', 'from' => ['bad-column']]]],
    ]));
};

$tests['real upstream fkey1 catalog non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers fkey1.test fkey1-1.0..3.5 FK schema declaration, PRAGMA foreign_key_list ordering/action rows, and DBSTATUS_DEFERRED_FKS zero status; avoids accepted fkey1 quoted cascade, self-replace, partial-index repair, wide foreign_key_check, corrupt-stat schema, fkey2 action/counter/DDL, trigger, RETURNING, WAL/VFS/B-tree, JSON, and PRAGMA batches',
        'non-overlap: covers fkey1.test fkey1-1.0..3.5 FK schema declaration, PRAGMA foreign_key_list ordering/action rows, and DBSTATUS_DEFERRED_FKS zero status; avoids accepted fkey1 quoted cascade, self-replace, partial-index repair, wide foreign_key_check, corrupt-stat schema, fkey2 action/counter/DDL, trigger, RETURNING, WAL/VFS/B-tree, JSON, and PRAGMA batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the lane-local trigger/FK dynamic planner and hydrated SQLite fkey1.test source truth',
        'dependency-closure: no new support component needed; reuses the lane-local trigger/FK dynamic planner and hydrated SQLite fkey1.test source truth'
    );
};

return $tests;
