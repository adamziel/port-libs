<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$project = static fn (array $rows): array => array_values(array_map('array_values', $rows));

$schemaVariants = [
    'rowid-primary' => [['a'], ['c']],
    'int-primary' => [['a'], ['c']],
    'without-rowid-primary' => [['a'], ['c']],
    'reordered-unique' => [['c'], ['a']],
];

$dynamicRows = [];
for ($i = 0; $i < 280; $i++) {
    $base = 10000 + ($i * 20);
    $dynamicRows[] = [
        'name' => sprintf('variant %03d', $i),
        'before' => [
            ['a' => $base + 1, 'b' => 10 + $i, 'c' => 'one-' . $i],
            ['a' => $base + 2, 'b' => 20 + $i, 'c' => 'two-' . $i],
            ['a' => $base + 3, 'b' => 30 + $i, 'c' => 'three-' . $i],
        ],
        'c_update' => [$base + 4, 200 + $i, 'two-' . $i],
        'a_update' => [$base + 1, 300 + $i, 'fresh-' . $i],
        'skip' => [$base + 5, 400 + $i, 'three-' . $i],
        'where_low' => [$base + 2, 5 + $i, 'candidate-low-' . $i],
        'where_high' => [$base + 2, 500 + $i, 'candidate-high-' . $i],
        'insert' => [$base + 9, 900 + $i, 'nine-' . $i],
    ];
}

foreach ($schemaVariants as $schemaName => $uniqueConstraints) {
    foreach ($dynamicRows as $row) {
        $name = "real upstream upsert4/upsert2/returning1 {$schemaName} {$row['name']}";

        $tests[$name . ' c conflict updates from excluded and returns post image'] = static function (TestRunner $t) use ($row, $uniqueConstraints, $project): void {
            [$a, $b, $c] = $row['c_update'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(c) DO UPDATE SET b=excluded.b RETURNING a,b,c,b+1 AS next_b,c||'-hit' AS marker",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([[$row['before'][1]['a'], $b, $c, $b + 1, $c . '-hit']], $project($result['returning']));
            $t->same([[$row['before'][1]['a'], $b, $c]], $project($result['updated_rows']));
            $t->same(1, $result['changes']);
        };

        $tests[$name . ' primary conflict updates c from excluded and preserves row identity'] = static function (TestRunner $t) use ($row, $uniqueConstraints, $project): void {
            [$a, $b, $c] = $row['a_update'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(a) DO UPDATE SET c=excluded.c,b=excluded.b RETURNING app_settings.a,b,c",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([[$a, $b, $c]], $project($result['returning']));
            $t->same([[$a, $b, $c]], $project($result['updated_rows']));
            $t->same([], $result['inserted_rows']);
        };

        $tests[$name . ' do nothing suppresses returning and leaves table stable'] = static function (TestRunner $t) use ($row, $uniqueConstraints): void {
            [$a, $b, $c] = $row['skip'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(c) DO NOTHING RETURNING *",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([], $result['returning']);
            $t->same($row['before'], $result['after']);
            $t->same(0, $result['changes']);
        };

        $tests[$name . ' where gate skips low excluded value'] = static function (TestRunner $t) use ($row, $uniqueConstraints): void {
            [$a, $b, $c] = $row['where_low'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(a) DO UPDATE SET b=excluded.b,c=excluded.c WHERE app_settings.b < excluded.b RETURNING a,b,c",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([], $result['returning']);
            $t->same([$result['incoming_rows'][0]], $result['skipped_rows']);
            $t->same($row['before'], $result['after']);
        };

        $tests[$name . ' where gate accepts high excluded value'] = static function (TestRunner $t) use ($row, $uniqueConstraints, $project): void {
            [$a, $b, $c] = $row['where_high'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(a) DO UPDATE SET b=excluded.b,c=excluded.c WHERE app_settings.b < excluded.b RETURNING a,b,c",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([[$a, $b, $c]], $project($result['returning']));
            $t->same([[$a, $b, $c]], $project($result['updated_rows']));
            $t->same(1, $result['changes']);
        };

        $tests[$name . ' non-conflicting row inserts and returns once'] = static function (TestRunner $t) use ($row, $uniqueConstraints, $project): void {
            [$a, $b, $c] = $row['insert'];
            $result = SQLiteUpsertReturningSql::execute(
                "INSERT INTO app_settings(a,b,c) VALUES({$a},{$b},'{$c}') ON CONFLICT(a) DO UPDATE SET b=excluded.b RETURNING a,b,c",
                ['app_settings' => $row['before']],
                $uniqueConstraints,
            );

            $t->same([[$a, $b, $c]], $project($result['returning']));
            $t->same([[$a, $b, $c]], $project($result['inserted_rows']));
            $t->same(4, count($result['after']));
        };
    }
}

return $tests;
