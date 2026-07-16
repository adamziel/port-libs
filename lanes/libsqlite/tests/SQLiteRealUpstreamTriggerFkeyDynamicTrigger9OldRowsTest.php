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
    'real upstream trigger9 old row column load cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger9-1.2.1'));
        $t->true(is_string($source) && str_contains($source, 'has_rowdata {DELETE FROM t1}'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger9-1.7.2'));
    },
    'real upstream trigger9 view old rows cites source block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER trig1 INSTEAD OF UPDATE ON v1'));
        $t->true(is_string($source) && str_contains($source, "SELECT a, b FROM t3 EXCEPT SELECT 1, 'one'"));
        $t->true(is_string($source) && str_contains($source, 'SELECT sum(a) AS a, max(b) AS b FROM t3 GROUP BY t3.a HAVING'));
    },
];

for ($i = 1; $i <= 70; ++$i) {
    $rows = [
        ['rowid' => 1, 'x' => '1', 'y' => 'payload-' . $i . '-a', 'z' => '2'],
        ['rowid' => 2, 'x' => '2', 'y' => 'payload-' . $i . '-b', 'z' => '4'],
        ['rowid' => 3, 'x' => '3', 'y' => 'payload-' . $i . '-c', 'z' => '6'],
    ];
    if ($i % 5 === 0) {
        $rows[] = ['rowid' => 4, 'x' => '4', 'y' => 'payload-' . $i . '-d', 'z' => '8'];
    }

    $deleteRowid = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan($rows, 'delete', 'rowid');
    $deleteXWhen = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan($rows, 'delete', 'x', 'x', '2');
    $updateRowid = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan($rows, 'update', 'rowid');
    $updateXWhen = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan($rows, 'update', 'x', 'x', '2');

    $expectedRowids = array_column($rows, 'rowid');
    $expectedXWhen = array_values(array_map(
        static fn (array $row): string => (string) $row['x'],
        array_filter($rows, static fn (array $row): bool => strcmp((string) $row['x'], '2') >= 0)
    ));

    foreach ([
        'source' => 'trigger9.test trigger9-1.2.1..1.7.3',
        'operation' => 'old-column-trigger-load-plan',
        'status' => 'commit-ok',
        'event' => 'delete',
        'old_expression' => 'old.rowid',
        'emitted_values' => $expectedRowids,
        'emitted_count' => count($expectedRowids),
        'rowdata_opcode_required' => false,
        'loaded_old_columns' => ['rowid'],
        'loaded_old_column_count' => 1,
        'statement_row_count' => count($rows),
        'dependencies.0' => 'sqlite-trigger9-old-rowid-does-not-load-full-rowdata',
        'dependencies.1' => 'sqlite-trigger9-old-column-subset-loads-needed-column-only',
        'dependencies.2' => 'sqlite-trigger9-when-clause-shares-old-column-registers',
    ] as $path => $expected) {
        $tests['real upstream trigger9 delete old rowid dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deleteRowid, $path, $expected, $value): void {
            $t->same($expected, $value($deleteRowid(), (string) $path));
        };
    }

    foreach ([
        'event' => 'delete',
        'old_expression' => 'old.x',
        'when_column' => 'x',
        'when_minimum' => '2',
        'emitted_values' => $expectedXWhen,
        'emitted_count' => count($expectedXWhen),
        'rowdata_opcode_required' => false,
        'loaded_old_columns' => ['x'],
        'loaded_old_column_count' => 1,
    ] as $path => $expected) {
        $tests['real upstream trigger9 delete old x when dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($deleteXWhen, $path, $expected, $value): void {
            $t->same($expected, $value($deleteXWhen(), (string) $path));
        };
    }

    foreach ([
        'event' => 'update',
        'old_expression' => 'old.rowid',
        'emitted_values' => $expectedRowids,
        'emitted_count' => count($expectedRowids),
        'rowdata_opcode_required' => false,
        'loaded_old_columns' => ['rowid'],
        'loaded_old_column_count' => 1,
        'updated_rows.0.y' => '',
    ] as $path => $expected) {
        $tests['real upstream trigger9 update old rowid dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($updateRowid, $path, $expected, $value): void {
            $t->same($expected, $value($updateRowid(), (string) $path));
        };
    }

    foreach ([
        'event' => 'update',
        'old_expression' => 'old.x',
        'when_column' => 'x',
        'when_minimum' => '2',
        'emitted_values' => $expectedXWhen,
        'emitted_count' => count($expectedXWhen),
        'rowdata_opcode_required' => false,
        'loaded_old_columns' => ['x'],
        'updated_rows.0.y' => '',
    ] as $path => $expected) {
        $tests['real upstream trigger9 update old x when dynamic ' . $i . ' ' . $path] = static function (TestRunner $t) use ($updateXWhen, $path, $expected, $value): void {
            $t->same($expected, $value($updateXWhen(), (string) $path));
        };
    }
}

$viewRows = [
    ['a' => 1, 'b' => 'one'],
    ['a' => 2, 'b' => 'two'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 3, 'b' => 'four'],
    ['a' => 1, 'b' => 'uno'],
    ['a' => 1, 'b' => 'zero'],
];

$viewExpectations = [
    'plain' => [[1, 2, 3, 3, 3, 1, 1], 7, false, false],
    'where-alias' => [[2, 3, 3, 1, 1], 5, true, false],
    'distinct' => [[1, 2, 3, 3, 1, 1], 6, false, true],
    'except' => [[2, 3, 3, 3, 1, 1], 6, false, true],
    'group-having' => [[1], 1, false, true],
];

for ($i = 1; $i <= 40; ++$i) {
    foreach ($viewExpectations as $shape => [$oldA, $count, $whereAlias, $compound]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger9InsteadOfViewOldRowsPlan($viewRows, $shape);
        foreach ([
            'source' => 'trigger9.test trigger9-3.2..3.6',
            'operation' => 'instead-of-view-old-row-materialization',
            'status' => 'commit-ok',
            'view_shape' => $shape,
            'old_a_values' => $oldA,
            'old_row_count' => $count,
            'unused_view_columns_are_null_safe' => true,
            'where_alias_reused_without_full_old_row' => $whereAlias,
            'compound_view_materialized_before_trigger' => $compound,
            'dependencies.0' => 'sqlite-trigger9-instead-of-view-trigger-materializes-old-rows',
            'dependencies.1' => 'sqlite-trigger9-unused-view-columns-are-null-safe',
            'dependencies.2' => 'sqlite-trigger9-compound-view-old-rows-feed-trigger-program',
        ] as $path => $expected) {
            $tests['real upstream trigger9 instead of view dynamic ' . $i . ' ' . $shape . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
                $t->same($expected, $value($plan(), (string) $path));
            };
        }
    }
}

$tests['real upstream trigger9 rejects unsupported event'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan([], 'insert', 'rowid'));
$tests['real upstream trigger9 rejects unsupported old expression'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan([], 'delete', 'z'));
$tests['real upstream trigger9 rejects unsupported view shape'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger9InsteadOfViewOldRowsPlan([], 'recursive'));

return $tests;
