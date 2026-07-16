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
    'real upstream trigger4 view corpus cites join view insert block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger4.test');
        $t->true(is_string($source) && str_contains($source, 'create trigger I_test instead of insert on test'));
        $t->true(is_string($source) && str_contains($source, 'insert into test values(1,2,3)'));
    },
    'real upstream trigger4 view corpus cites missing backing table block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger4.test');
        $t->true(is_string($source) && str_contains($source, 'drop table test2'));
        $t->true(is_string($source) && str_contains($source, 'no such table: main.test2'));
    },
    'real upstream trigger4 view corpus cites bulk simple view block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger4.test');
        $t->true(is_string($source) && str_contains($source, 'select count(*) from vw'));
        $t->true(is_string($source) && str_contains($source, 'update vw set b=b+1000'));
    },
];

for ($i = 1; $i <= 260; ++$i) {
    $leftRows = [
        ['id' => 1, 'a' => 2],
        ['id' => 4, 'a' => 5],
    ];
    $rightRows = [
        ['id' => 1, 'b' => 3],
        ['id' => 4, 'b' => 6],
    ];
    if ($i % 5 === 0) {
        $leftRows[] = ['id' => 9, 'a' => 10];
        $rightRows[] = ['id' => 9, 'b' => 11];
    }

    $insertRow = ['id' => 20 + $i, 'a' => 30 + $i, 'b' => 40 + $i];
    $updateId = $i % 2 === 0 ? 1 : 4;
    $newA = 200 + $i;
    $newB = 300 + $i;
    $operations = [
        ['op' => 'insert', 'row' => $insertRow],
        ['op' => 'update', 'where' => static fn (array $row): bool => $row['id'] === $updateId, 'set' => ['a' => $newA, 'b' => $newB]],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::viewInsteadOfTriggerRouting($leftRows, $rightRows, $operations);
    $case = 'trigger4-1-2 join view insert update routing dynamic ' . $i;

    foreach ([
        'source' => 'trigger4.test trigger4-1.1..7.2',
        'operation' => 'instead-of-view-trigger-backing-table-routing',
        'status' => 'commit-ok',
        'error_count' => 0,
        'insert_count' => 1,
        'update_count' => 1,
        'delete_count' => 0,
        'dependencies.0' => 'sqlite-trigger4-instead-of-insert-routes-to-view-base-tables',
        'dependencies.1' => 'sqlite-trigger4-instead-of-update-routes-to-view-base-tables',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' inserted row appears in both base tables and view'] = static function (TestRunner $t) use ($plan, $insertRow): void {
        $actual = $plan();
        $t->true(in_array(['id' => $insertRow['id'], 'a' => $insertRow['a']], $actual['test1_rows'], true));
        $t->true(in_array(['id' => $insertRow['id'], 'b' => $insertRow['b']], $actual['test2_rows'], true));
        $t->true(in_array($insertRow, $actual['view_rows'], true));
    };
    $tests[$case . ' updated row is routed to both base tables'] = static function (TestRunner $t) use ($plan, $updateId, $newA, $newB): void {
        $actual = $plan();
        $t->true(in_array(['id' => $updateId, 'a' => $newA], $actual['test1_rows'], true));
        $t->true(in_array(['id' => $updateId, 'b' => $newB], $actual['test2_rows'], true));
    };
}

for ($i = 1; $i <= 220; ++$i) {
    $rows = [];
    for ($n = 0; $n < 8; ++$n) {
        $rows[] = ['id' => 101 + $n + ($i * 10), 'a' => 1001 + $n + ($i * 10)];
    }
    $leftRows = array_map(static fn (array $row): array => ['id' => $row['id'], 'a' => $row['a']], $rows);
    $rightRows = array_map(static fn (array $row): array => ['id' => $row['id'], 'b' => $row['a'] + 100], $rows);
    $keepId = $leftRows[0]['id'];
    $insertRow = ['id' => 5000 + $i, 'a' => 6000 + $i, 'b' => 7000 + $i];
    $operations = [
        ['op' => 'delete', 'where' => static fn (array $row): bool => $row['id'] !== $keepId],
        ['op' => 'insert', 'row' => $insertRow],
        ['op' => 'update', 'where' => static fn (array $row): bool => $row['id'] >= 5000, 'set' => ['b' => static fn (array $row): int => $row['b'] + 1000]],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::viewInsteadOfTriggerRouting($leftRows, $rightRows, $operations);
    $case = 'trigger4-4-7 bulk view delete insert update routing dynamic ' . $i;

    foreach ([
        'source' => 'trigger4.test trigger4-1.1..7.2',
        'status' => 'commit-ok',
        'error_count' => 0,
        'insert_count' => 1,
        'update_count' => 1,
        'delete_count' => 7,
        'view_row_count' => 2,
        'log_row_count' => 9,
        'dependencies.2' => 'sqlite-trigger4-instead-of-delete-routes-to-view-base-tables',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' delete leaves only retained and reinserted view rows'] = static function (TestRunner $t) use ($plan, $keepId, $insertRow): void {
        $ids = array_column($plan()['view_rows'], 'id');
        sort($ids);
        $t->same([$keepId, $insertRow['id']], $ids);
    };
    $tests[$case . ' inserted row receives later view update'] = static function (TestRunner $t) use ($plan, $insertRow): void {
        $rows = array_values(array_filter($plan()['view_rows'], static fn (array $row): bool => $row['id'] === $insertRow['id']));
        $t->same($insertRow['a'], $rows[0]['a']);
        $t->same($insertRow['b'] + 1000, $rows[0]['b']);
    };
}

for ($i = 1; $i <= 180; ++$i) {
    $leftRows = [['id' => 1, 'a' => 22], ['id' => 4, 'a' => 5]];
    $rightRows = [['id' => 1, 'b' => 3], ['id' => 4, 'b' => 66]];
    $missingTable = $i % 2 === 0 ? 'test2' : 'test1';
    $operations = [
        ['op' => 'insert', 'row' => ['id' => 7, 'a' => 8, 'b' => 9], 'missing_table' => $missingTable],
        ['op' => 'update', 'where' => static fn (array $row): bool => $row['id'] === 1, 'set' => ['a' => 222], 'missing_table' => $missingTable],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::viewInsteadOfTriggerRouting($leftRows, $rightRows, $operations);
    $case = 'trigger4-3 missing backing table fails view trigger dynamic ' . $i;

    foreach ([
        'source' => 'trigger4.test trigger4-1.1..7.2',
        'status' => 'constraint-failed',
        'error_count' => 2,
        'insert_count' => 0,
        'update_count' => 0,
        'delete_count' => 0,
        'view_row_count' => 2,
        'errors.0.error' => 'no such table: main.' . $missingTable,
        'dependencies.3' => 'sqlite-trigger4-missing-view-backing-table-fails-trigger-program',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
    $tests[$case . ' base rows remain unchanged after failed trigger program'] = static function (TestRunner $t) use ($plan, $leftRows, $rightRows): void {
        $actual = $plan();
        $t->same($leftRows, $actual['test1_rows']);
        $t->same($rightRows, $actual['test2_rows']);
    };
}

return $tests;
