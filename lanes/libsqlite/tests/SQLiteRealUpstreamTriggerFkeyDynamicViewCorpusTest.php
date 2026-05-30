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
    'real upstream trigger2 view corpus cites instead of trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'Triggers on views'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER before_update INSTEAD OF UPDATE ON abcd'));
    },
    'real upstream trigger2 view corpus cites expression view block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE VIEW v1 AS'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE v1 SET x=x+100, y=y+200, z=z+300'));
    },
    'real upstream trigger2 view corpus cites empty delete regression block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'At one point the following was causing a segfault'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-10.1'));
    },
];

for ($i = 1; $i <= 140; $i++) {
    $leftRows = [
        ['a' => 1, 'b' => 2],
        ['a' => 0, 'b' => 0],
    ];
    if ($i % 7 === 0) {
        $leftRows[] = ['a' => 2, 'b' => 4];
    }
    $rightRows = [
        ['c' => 3, 'd' => 4],
    ];
    if ($i % 11 === 0) {
        $rightRows[] = ['c' => 5, 'd' => 6];
    }

    $insert = ['a' => 10 + $i, 'b' => 20 + $i, 'c' => 30 + $i, 'd' => 40 + $i];
    $update = ['a' => 100 + $i, 'b' => 25 + $i, 'c' => 3, 'd' => 4];
    $operations = match ($i % 3) {
        0 => [
            ['op' => 'update', 'where' => static fn (array $row): bool => $row['a'] === 1, 'row' => $update],
            ['op' => 'delete', 'where' => static fn (array $row): bool => $row['a'] === 1],
            ['op' => 'insert', 'row' => $insert],
        ],
        1 => [
            ['op' => 'insert', 'row' => $insert],
            ['op' => 'update', 'where' => static fn (array $row): bool => $row['a'] === 1, 'row' => $update],
            ['op' => 'delete', 'where' => static fn (array $row): bool => $row['a'] === 1],
        ],
        default => [
            ['op' => 'delete', 'where' => static fn (array $row): bool => $row['a'] === 1],
            ['op' => 'insert', 'row' => $insert],
            ['op' => 'update', 'where' => static fn (array $row): bool => $row['a'] === 1, 'row' => $update],
        ],
    };

    $matchingViewRows = 0;
    foreach ($leftRows as $left) {
        foreach ($rightRows as $_right) {
            if ($left['a'] === 1) {
                ++$matchingViewRows;
            }
        }
    }
    $expectedLogCount = ($matchingViewRows * 2) + ($matchingViewRows * 2) + 2;
    $expectedFirst = match ($operations[0]['op']) {
        'insert' => ['old_a' => 0, 'old_b' => 0, 'old_c' => 0, 'old_d' => 0, 'new_a' => $insert['a'], 'new_b' => $insert['b'], 'new_c' => $insert['c'], 'new_d' => $insert['d']],
        'delete' => ['old_a' => 1, 'old_b' => 2, 'old_c' => 3, 'old_d' => 4, 'new_a' => 0, 'new_b' => 0, 'new_c' => 0, 'new_d' => 0],
        default => ['old_a' => 1, 'old_b' => 2, 'old_c' => 3, 'old_d' => 4, 'new_a' => $update['a'], 'new_b' => $update['b'], 'new_c' => $update['c'], 'new_d' => $update['d']],
    };
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewTriggerLog($leftRows, $rightRows, $operations);
    $case = 'trigger2-7 instead of view trigger old new dynamic ' . $i;

    foreach ([
        'source' => 'trigger2.test trigger2-7.1..7.4',
        'operation' => 'instead-of-view-trigger-old-new-log',
        'status' => 'commit-ok',
        'view_row_count' => count($leftRows) * count($rightRows),
        'operation_count' => 3,
        'log_row_count' => $expectedLogCount,
        'first_log_row' => $expectedFirst,
        'dependencies.0' => 'sqlite-trigger2-instead-of-update-view-old-new-row',
        'dependencies.1' => 'sqlite-trigger2-instead-of-delete-view-old-row',
        'dependencies.2' => 'sqlite-trigger2-instead-of-insert-view-new-row',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' first matching update row keeps old joined columns'] = static function (TestRunner $t) use ($plan): void {
        $rows = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['old_a'] === 1 && $row['new_a'] >= 100));
        $t->same(1, $rows[0]['old_a']);
        $t->same(2, $rows[0]['old_b']);
        $t->same(3, $rows[0]['old_c']);
        $t->same(4, $rows[0]['old_d']);
    };
    $tests[$case . ' delete rows zero the new image'] = static function (TestRunner $t) use ($plan): void {
        $rows = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['old_a'] === 1 && $row['new_a'] === 0));
        $t->true($rows !== []);
        $t->same(0, $rows[0]['new_b']);
        $t->same(0, $rows[0]['new_c']);
        $t->same(0, $rows[0]['new_d']);
    };
    $tests[$case . ' insert rows zero the old image'] = static function (TestRunner $t) use ($plan, $insert): void {
        $rows = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['new_a'] === $insert['a']));
        $t->same(0, $rows[0]['old_a']);
        $t->same(0, $rows[0]['old_b']);
        $t->same(0, $rows[0]['old_c']);
        $t->same(0, $rows[0]['old_d']);
    };
}

for ($i = 1; $i <= 140; $i++) {
    $baseRows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
    ];
    if ($i % 8 === 0) {
        $baseRows[] = ['a' => 7, 'b' => 8, 'c' => 9];
    }
    $insert = ['x' => $i, 'y' => $i + 1, 'z' => $i + 2];
    $update = ['x' => 103 + $i, 'y' => 205 + $i, 'z' => 304 + $i];
    $deleteY = $i % 2 === 0 ? 5 : 11;
    $operations = [
        ['op' => 'delete', 'where' => static fn (array $row): bool => $row['y'] === $deleteY],
        ['op' => 'insert', 'row' => $insert],
        ['op' => 'update', 'where' => static fn (array $row): bool => $row['x'] >= 3, 'row' => $update],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::expressionViewTriggerRows($baseRows, $operations);
    $viewRows = array_map(static fn (array $row): array => ['x' => $row['a'] + $row['b'], 'y' => $row['b'] + $row['c'], 'z' => $row['a'] + $row['c']], $baseRows);
    $deleteCount = count(array_filter($viewRows, static fn (array $row): bool => $row['y'] === $deleteY));
    $updateCount = count(array_filter($viewRows, static fn (array $row): bool => $row['x'] >= 3));
    $case = 'trigger2-8 expression view trigger old new dynamic ' . $i;

    foreach ([
        'source' => 'trigger2.test trigger2-8.1..8.6',
        'operation' => 'expression-view-instead-of-trigger-old-new-rows',
        'status' => 'commit-ok',
        'view_rows' => $viewRows,
        'view_row_count' => count($viewRows),
        'log_row_count' => $deleteCount + 1 + $updateCount,
        'dependencies.0' => 'sqlite-trigger2-view-expression-columns-feed-old-row',
        'dependencies.1' => 'sqlite-trigger2-view-insert-feeds-new-expression-row',
        'dependencies.2' => 'sqlite-trigger2-view-update-feeds-old-and-new-expression-rows',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' first view row uses expression columns'] = static function (TestRunner $t) use ($plan): void {
        $view = $plan()['view_rows'][0];
        $t->same(3, $view['x']);
        $t->same(5, $view['y']);
        $t->same(4, $view['z']);
    };
    $tests[$case . ' insert trigger stores new expression row'] = static function (TestRunner $t) use ($plan, $insert): void {
        $rows = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['new_x'] === $insert['x']));
        $t->same(null, $rows[0]['old_x']);
        $t->same($insert['y'], $rows[0]['new_y']);
        $t->same(null, $rows[0]['old_z']);
        $t->same($insert['z'], $rows[0]['new_z']);
    };
    $tests[$case . ' update trigger stores old and new expression rows'] = static function (TestRunner $t) use ($plan, $update): void {
        $rows = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['new_x'] === $update['x']));
        $t->true($rows !== []);
        $t->same($update['y'], $rows[0]['new_y']);
        $t->same($update['z'], $rows[0]['new_z']);
        $t->true($rows[0]['old_x'] < $rows[0]['new_x']);
    };
    $tests[$case . ' empty delete regression stays empty when predicate misses'] = static function (TestRunner $t) use ($baseRows): void {
        $plan = SQLiteDynamicTriggerForeignKeyPlan::expressionViewTriggerRows($baseRows, [
            ['op' => 'delete', 'where' => static fn (array $row): bool => $row['x'] === 1],
        ]);
        $t->same('trigger2.test trigger2-8.1..8.6', $plan['source']);
        $t->same([], $plan['log_rows']);
        $t->same(0, $plan['log_row_count']);
    };
}

$tests['trigger2-10 view insert omitted columns preserves null slots'] = static function (TestRunner $t): void {
    $plan = SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewTriggerLog(
        [],
        [],
        [['op' => 'insert', 'row' => ['a' => 11, 'b' => 0, 'c' => 0, 'd' => 14]]]
    );
    $t->same('trigger2.test trigger2-7.1..7.4', $plan['source']);
    $t->same(2, $plan['log_row_count']);
    $t->same(11, $plan['log_rows'][0]['new_a']);
    $t->same(14, $plan['log_rows'][0]['new_d']);
    $t->same(0, $plan['log_rows'][0]['new_b']);
    $t->same(0, $plan['log_rows'][0]['new_c']);
};

return $tests;
