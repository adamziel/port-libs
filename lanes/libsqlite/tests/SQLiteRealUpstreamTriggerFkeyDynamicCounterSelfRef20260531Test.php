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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests = [
    'real upstream fkey2 counter selfref cites fkey2-15 scan block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'fkey2-15.*, test that unnecessary FK related scans'));
        $t->true(is_string($source) && str_contains($source, 'set ::sqlite_search_count 0'));
        $t->true(is_string($source) && str_contains($source, 'execsqlS { INSERT INTO pp VALUES(3,'));
    },
    'real upstream fkey2 counter selfref cites fkey2-16 self reference block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'fkey2-16.*, test that rows that refer to'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TABLE self(a INTEGER PRIMARY KEY, b REFERENCES self(a))'));
        $t->true(is_string($source) && str_contains($source, 'catchsql { INSERT INTO self VALUES(20, 21) }'));
    },
];

for ($i = 1; $i <= 80; ++$i) {
    $parents = [
        ['id' => 1, 'label' => 'one'],
        ['id' => 2, 'label' => 'two'],
    ];
    $children = [
        ['name' => 'neung-' . $i, 'parent_id' => 1],
        ['name' => 'song-' . $i, 'parent_id' => 2],
    ];
    $ops = [
        ['case' => 'fkey2-15.1.2', 'operation' => 'insert-parent', 'row' => ['id' => 3 + $i, 'label' => 'three-' . $i]],
        ['case' => 'fkey2-15.1.3', 'operation' => 'insert-child', 'row' => ['name' => 'see-' . $i, 'parent_id' => 4000 + $i], 'deferred_violation' => true],
        ['case' => 'fkey2-15.1.3-parent', 'operation' => 'insert-parent', 'row' => ['id' => 5000 + $i, 'label' => 'five-' . $i]],
        ['case' => 'fkey2-15.1.4-delete', 'operation' => 'delete-child', 'where' => ['name' => 'see-' . $i]],
        ['case' => 'fkey2-15.1.4-parent', 'operation' => 'insert-parent', 'row' => ['id' => 6000 + $i, 'label' => 'six-' . $i]],
        ['case' => 'fkey2-15.1.6-delete', 'operation' => 'delete-child', 'where' => ['name' => 'neung-' . $i]],
        ['case' => 'fkey2-15.1.6-rollback', 'operation' => 'rollback'],
        ['case' => 'fkey2-15.1.7-delete-parent', 'operation' => 'delete-parent', 'where' => ['id' => 2], 'deferred_violation' => true],
        ['case' => 'fkey2-15.1.7-delete-child', 'operation' => 'delete-child', 'where' => ['name' => 'song-' . $i]],
        ['case' => 'fkey2-15.1.7-rollback', 'operation' => 'rollback'],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCounterScanPlan($parents, $children, $ops);
    $case = 'real upstream fkey2-15 counter scan dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-15.1.1..15.1.7',
        'operation' => 'foreign-key-counter-scan-elision',
        'trace.0.combined_scan_count' => 0,
        'trace.1.combined_scan_count' => 2,
        'trace.2.combined_scan_count' => 2,
        'trace.3.combined_scan_count' => 1,
        'trace.4.combined_scan_count' => 0,
        'trace.6.rolled_back' => true,
        'trace.7.deferred_violation_count_after' => 1,
        'trace.9.rolled_back' => true,
        'deferred_violation_count' => 0,
        'dependencies.0' => 'sqlite-fkey2-zero-deferred-counter-elides-parent-insert-scan',
        'dependencies.1' => 'sqlite-fkey2-nonzero-deferred-counter-keeps-parent-insert-scan',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9] as $traceIndex) {
        $tests[$case . ' trace ordinal ' . $traceIndex] = static function (TestRunner $t) use ($plan, $traceIndex, $ops): void {
            $t->same($ops[$traceIndex]['case'], $plan()['trace'][$traceIndex]['case']);
        };
        $tests[$case . ' trace operation ' . $traceIndex] = static function (TestRunner $t) use ($plan, $traceIndex, $ops): void {
            $t->same($ops[$traceIndex]['operation'], $plan()['trace'][$traceIndex]['operation']);
        };
    }
}

$schemaVariants = [
    ['a', 'b'],
    ['setting_id', 'parent_setting_id'],
    ['tenant_id', 'root_tenant_id'],
];

for ($i = 1; $i <= 90; ++$i) {
    [$primaryKey, $foreignKey] = $schemaVariants[$i % count($schemaVariants)];
    $start = 13 + $i;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyPlan(
        ['primary_key' => $primaryKey, 'foreign_key' => $foreignKey],
        $start,
        $start,
        [
            [$primaryKey => $start + 1, $foreignKey => $start + 1],
            [$foreignKey => $start + 2],
            [$primaryKey => $start + 2],
            [$primaryKey => $start + 4, $foreignKey => $start + 4],
        ]
    );
    $case = 'real upstream fkey2-16 self reference dynamic ' . $i;

    foreach ([
        'source' => 'fkey2.test fkey2-16.1.*',
        'operation' => 'self-referential-foreign-key-row',
        'schema.primary_key' => $primaryKey,
        'schema.foreign_key' => $foreignKey,
        'initial_row.' . $primaryKey => $start,
        'initial_row.' . $foreignKey => $start,
        'trace.0.ok' => true,
        'trace.1.ok' => false,
        'trace.1.error' => 'FOREIGN KEY constraint failed',
        'trace.2.ok' => false,
        'trace.2.error' => 'FOREIGN KEY constraint failed',
        'trace.3.ok' => true,
        'final_row.' . $primaryKey => $start + 4,
        'final_row.' . $foreignKey => $start + 4,
        'delete_self_reference_ok' => true,
        'orphan_insert.ok' => false,
        'orphan_insert.error' => 'FOREIGN KEY constraint failed',
        'dependencies.0' => 'sqlite-fkey2-self-reference-insert-is-valid',
        'dependencies.1' => 'sqlite-fkey2-self-reference-update-must-remain-self-consistent',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream fkey2-15 counter scan rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCounterScanPlan([], [], [
        ['case' => 'bad', 'operation' => 'vacuum'],
    ]));
};

$tests['real upstream fkey2-16 self reference rejects bad column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::selfReferentialForeignKeyPlan(
        ['primary_key' => 'id', 'foreign_key' => 'parent_id'],
        1,
        1,
        [['bad-column' => 2]]
    ));
};

return $tests;
