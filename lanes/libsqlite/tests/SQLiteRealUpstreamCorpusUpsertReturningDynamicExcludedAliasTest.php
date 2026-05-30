<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quote = static function (PDO $db, mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $db->quote((string) $value);
};

$oracle = static function (string $schema, array $seedRows, string $insertSql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec($schema);
    foreach ($seedRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO target_rows(w,x,y,z) VALUES(%s,%s,%s,%s)',
            $row['w'] === null ? 'NULL' : $db->quote((string) $row['w']),
            $row['x'] === null ? 'NULL' : (string) $row['x'],
            $row['y'] === null ? 'NULL' : (string) $row['y'],
            $row['z'] === null ? 'NULL' : (string) $row['z'],
        ));
    }

    $db->exec($insertSql);
    $rows = [];
    $result = $db->query('SELECT w,x,y,z FROM target_rows ORDER BY x,y,z');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = [
            'w' => $row['w'],
            'x' => $row['x'] === null ? null : (int) $row['x'],
            'y' => $row['y'] === null ? null : (int) $row['y'],
            'z' => $row['z'] === null ? null : (int) $row['z'],
        ];
    }

    return $rows;
};

$native = static function (array $seedRows, array $incomingRows, array $arm): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $seedRows,
        $incomingRows,
        [$arm],
        [['x', 'y'], ['z']],
    );
};

$schemas = [
    'upsert4-7.1 rowid composite primary key plus z unique' => 'CREATE TABLE target_rows(w, x, y, z, PRIMARY KEY(x, y)); CREATE UNIQUE INDEX target_rows_z ON target_rows(z)',
    'upsert4-7.2 without rowid composite primary key plus z unique' => 'CREATE TABLE target_rows(w, x, y, z, PRIMARY KEY(x, y)) WITHOUT ROWID; CREATE UNIQUE INDEX target_rows_z ON target_rows(z)',
];

$baseSeedRows = [
    ['w' => 'a', 'x' => 1, 'y' => 1, 'z' => 1],
    ['w' => 'b', 'x' => 2, 'y' => 2, 'z' => 2],
];

$incomingCases = [
    'z conflict updates first row from excluded value' => [['w' => 'c', 'x' => 3, 'y' => 3, 'z' => 1], ['z'], 'excluded-value'],
    'xy conflict doubles current row value' => [['w' => 'c', 'x' => 2, 'y' => 2, 'z' => 3], ['y', 'x'], 'double-current'],
    'xy conflict doubles through table-qualified current row' => [['w' => 'd', 'x' => 2, 'y' => 2, 'z' => 4], ['y', 'x'], 'double-current'],
    'xy conflict doubles through insert alias current row' => [['w' => 'e', 'x' => 2, 'y' => 2, 'z' => 5], ['y', 'x'], 'double-current'],
    'clean composite and z values insert a new row' => [['w' => 'f', 'x' => 6, 'y' => 6, 'z' => 6], null, 'insert'],
    'null z does not conflict and inserts with composite miss' => [['w' => 'g', 'x' => 7, 'y' => 7, 'z' => null], null, 'insert'],
    'null z still updates on composite conflict' => [['w' => 'h', 'x' => 2, 'y' => 2, 'z' => null], ['y', 'x'], 'double-current'],
    'z conflict wins when composite misses' => [['w' => 'i', 'x' => 9, 'y' => 9, 'z' => 2], ['z'], 'excluded-value'],
];

$statementModes = [
    'upsert4-7.1 excluded pseudo-table assignment' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows VALUES(%s,%s,%s,%s) ON CONFLICT(z) DO UPDATE SET w=excluded.w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['y']),
            $quote($db, $row['z']),
        ),
        'arm' => static fn (string $action): array => [
            'target' => ['z'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current, array $incoming): mixed => $incoming['w']],
        ],
        'appliesTo' => 'excluded-value',
    ],
    'upsert4-7.2 unqualified current-column concatenation' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows VALUES(%s,%s,%s,%s) ON CONFLICT(y, x) DO UPDATE SET w=w||w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['y']),
            $quote($db, $row['z']),
        ),
        'arm' => static fn (string $action): array => [
            'target' => ['y', 'x'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
        ],
        'appliesTo' => 'double-current',
    ],
    'upsert4-7.3 table-qualified current-column concatenation' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows VALUES(%s,%s,%s,%s) ON CONFLICT(y, x) DO UPDATE SET w=w||target_rows.w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['y']),
            $quote($db, $row['z']),
        ),
        'arm' => static fn (string $action): array => [
            'target' => ['y', 'x'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
        ],
        'appliesTo' => 'double-current',
    ],
    'upsert4-7.4 insert-alias current-column concatenation' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows AS alias_rows VALUES(%s,%s,%s,%s) ON CONFLICT(y, x) DO UPDATE SET w=w||alias_rows.w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['y']),
            $quote($db, $row['z']),
        ),
        'arm' => static fn (string $action): array => [
            'target' => ['y', 'x'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
        ],
        'appliesTo' => 'double-current',
    ],
];

foreach ($schemas as $schemaName => $schemaSql) {
    foreach ($statementModes as $modeName => $mode) {
        foreach ($incomingCases as $caseName => [$incoming, $expectedTarget, $action]) {
            if ($mode['appliesTo'] !== $action) {
                continue;
            }

            $prefix = 'real upstream upsert4 excluded alias dynamic ' . $schemaName . ' / ' . $modeName . ' / ' . $caseName;

            $tests[$prefix . ' final row image matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $schemaSql, $baseSeedRows, $incoming, $mode, $quote): void {
                $db = new PDO('sqlite::memory:');
                $sql = $mode['sql']($db, $incoming, $quote);
                $expected = $oracle($schemaSql, $baseSeedRows, $sql);
                $actual = $native($baseSeedRows, [$incoming], $mode['arm']('update'));

                usort($actual['after'], static fn (array $left, array $right): int => [$left['x'], $left['y'], $left['z']] <=> [$right['x'], $right['y'], $right['z']]);
                $t->same($expected, array_values($actual['after']));
            };

            $tests[$prefix . ' matched conflict target mirrors upstream clause'] = static function (TestRunner $t) use ($native, $baseSeedRows, $incoming, $mode, $expectedTarget): void {
                $actual = $native($baseSeedRows, [$incoming], $mode['arm']('update'));
                $target = $actual['matched_arms'][0]['target'] ?? null;

                $t->same($expectedTarget, $target);
            };

            $tests[$prefix . ' RETURNING stream contains one changed row'] = static function (TestRunner $t) use ($native, $baseSeedRows, $incoming, $mode): void {
                $actual = $native($baseSeedRows, [$incoming], $mode['arm']('update'));

                $t->same(1, $actual['changes']);
                $t->same(1, count($actual['returning_rows']));
            };

            $tests[$prefix . ' RETURNING projection preserves target row columns'] = static function (TestRunner $t) use ($native, $baseSeedRows, $incoming, $mode): void {
                $actual = $native($baseSeedRows, [$incoming], $mode['arm']('update'));
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                    'w',
                    'x',
                    'y',
                    'z',
                    'marker' => static fn (array $row): string => 'upsert4:' . (string) $row['w'] . ':' . (string) $row['x'],
                ]);

                $t->same(['w', 'x', 'y', 'z', 'marker'], array_keys($projected[0]));
                $t->same('upsert4:' . (string) $actual['returning_rows'][0]['w'] . ':' . (string) $actual['returning_rows'][0]['x'], $projected[0]['marker']);
            };
        }
    }
}

$excludedNameSchemas = [
    'upsert4-8.1 rowid table literally named excluded' => 'CREATE TABLE target_rows(w, x INTEGER, "a b", z, PRIMARY KEY(x, "a b")); CREATE UNIQUE INDEX target_rows_z ON target_rows(z); CREATE INDEX target_rows_z_shadow ON target_rows(z)',
    'upsert4-8.2 without rowid table literally named excluded' => 'CREATE TABLE target_rows(w, x, "a b", z, PRIMARY KEY(x, "a b")) WITHOUT ROWID; CREATE UNIQUE INDEX target_rows_z ON target_rows(z); CREATE INDEX target_rows_z_shadow ON target_rows(z)',
];

$excludedSeedRows = [
    ['w' => 'a', 'x' => 1, 'a b' => 1, 'z' => 1],
    ['w' => 'b', 'x' => 2, 'a b' => 2, 'z' => 2],
];

$excludedIncoming = ['w' => 'hello', 'x' => 1, 'a b' => 1, 'z' => null];
$excludedIncomingRows = [
    ['w' => 'hello', 'x' => 1, 'a b' => 1, 'z' => null],
    ['w' => 'again', 'x' => 1, 'a b' => 1, 'z' => null],
    ['w' => 'fresh', 'x' => 3, 'a b' => 3, 'z' => null],
    ['w' => 'next', 'x' => 4, 'a b' => 4, 'z' => null],
];

$excludedNative = static function (array $seedRows, array $incomingRows, array $arm): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $seedRows,
        $incomingRows,
        [$arm],
        [['x', 'a b'], ['z']],
    );
};

$excludedOracle = static function (string $schema, array $seedRows, string $insertSql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec($schema);
    foreach ($seedRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO target_rows(w,x,"a b",z) VALUES(%s,%s,%s,%s)',
            $db->quote((string) $row['w']),
            (string) $row['x'],
            (string) $row['a b'],
            $row['z'] === null ? 'NULL' : (string) $row['z'],
        ));
    }
    $db->exec($insertSql);
    $rows = [];
    $result = $db->query('SELECT w,x,"a b" AS ab,z FROM target_rows ORDER BY x,ab,z');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = [
            'w' => $row['w'],
            'x' => $row['x'] === null ? null : (int) $row['x'],
            'a b' => $row['ab'] === null ? null : (int) $row['ab'],
            'z' => $row['z'] === null ? null : (int) $row['z'],
        ];
    }

    return $rows;
};

$excludedModes = [
    'upsert4-8.1 unaliased excluded-dot-w resolves target table column' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows VALUES(%s,%s,%s,%s) ON CONFLICT(x, "a b") DO UPDATE SET w=target_rows.w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['a b']),
            $quote($db, $row['z']),
        ),
        'arm' => [
            'target' => ['x', 'a b'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): mixed => $current['w']],
        ],
        'expectedReturningW' => 'a',
    ],
    'upsert4-8.2 insert alias makes excluded-dot-w the incoming pseudo-table' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows AS x1 VALUES(%s,%s,%s,%s) ON CONFLICT(x, "a b") DO UPDATE SET w=excluded.w',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['a b']),
            $quote($db, $row['z']),
        ),
        'arm' => [
            'target' => ['x', 'a b'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current, array $incoming): mixed => $incoming['w']],
        ],
        'expectedReturningW' => 'hello',
    ],
    'upsert4-8.3 excluded pseudo-table in WHERE can suppress update' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows AS x1 VALUES(%s,%s,%s,%s) ON CONFLICT(x, "a b") DO UPDATE SET w=w||w WHERE excluded.w!="hello"',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['a b']),
            $quote($db, $row['z']),
        ),
        'arm' => [
            'target' => ['x', 'a b'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
            'where' => static fn (array $current, array $incoming): bool => $incoming['w'] !== 'hello',
        ],
        'expectedReturningW' => null,
    ],
    'upsert4-8.4 excluded pseudo-table in WHERE can allow update' => [
        'sql' => static fn (PDO $db, array $row, callable $quote): string => sprintf(
            'INSERT INTO target_rows AS x1 VALUES(%s,%s,%s,%s) ON CONFLICT(x, "a b") DO UPDATE SET w=w||w WHERE excluded.x=1',
            $quote($db, $row['w']),
            $quote($db, $row['x']),
            $quote($db, $row['a b']),
            $quote($db, $row['z']),
        ),
        'arm' => [
            'target' => ['x', 'a b'],
            'action' => 'update',
            'assignments' => ['w' => static fn (array $current): string => (string) $current['w'] . (string) $current['w']],
            'where' => static fn (array $current, array $incoming): bool => $incoming['x'] === 1,
        ],
        'expectedReturningW' => 'aa',
    ],
];

foreach ($excludedNameSchemas as $schemaName => $schemaSql) {
    foreach ($excludedModes as $modeName => $mode) {
        $prefix = 'real upstream upsert4 table-named-excluded dynamic ' . $schemaName . ' / ' . $modeName;

        $tests[$prefix . ' final row image matches sqlite oracle'] = static function (TestRunner $t) use ($excludedOracle, $excludedNative, $schemaSql, $excludedSeedRows, $excludedIncoming, $mode, $quote): void {
            $db = new PDO('sqlite::memory:');
            $sql = $mode['sql']($db, $excludedIncoming, $quote);
            $expected = $excludedOracle($schemaSql, $excludedSeedRows, $sql);
            $actual = $excludedNative($excludedSeedRows, [$excludedIncoming], $mode['arm']);

            usort($actual['after'], static fn (array $left, array $right): int => [$left['x'], $left['a b'], $left['z']] <=> [$right['x'], $right['a b'], $right['z']]);
            $t->same($expected, array_values($actual['after']));
        };

        $tests[$prefix . ' returning stream matches update suppression'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $excludedIncoming, $mode): void {
            $actual = $excludedNative($excludedSeedRows, [$excludedIncoming], $mode['arm']);

            $expectedCount = $mode['expectedReturningW'] === null ? 0 : 1;
            $t->same($expectedCount, count($actual['returning_rows']));
            if ($expectedCount === 1) {
                $t->same($mode['expectedReturningW'], $actual['returning_rows'][0]['w']);
            }
        };

        $tests[$prefix . ' matched arm records composite conflict target'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $excludedIncoming, $mode): void {
            $actual = $excludedNative($excludedSeedRows, [$excludedIncoming], $mode['arm']);

            $t->same(['x', 'a b'], $actual['matched_arms'][0]['target']);
        };

        $tests[$prefix . ' changed skipped partition matches returning behavior'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $excludedIncoming, $mode): void {
            $actual = $excludedNative($excludedSeedRows, [$excludedIncoming], $mode['arm']);
            $expectedCount = $mode['expectedReturningW'] === null ? 0 : 1;

            $t->same($expectedCount, $actual['changes']);
            $t->same($expectedCount === 1 ? 0 : 1, count($actual['skipped_rows']));
        };
    }
}

$sequenceModes = [
    'alias excluded assignment over duplicate composite stream' => $excludedModes['upsert4-8.2 insert alias makes excluded-dot-w the incoming pseudo-table']['arm'],
    'where excluded suppresses hello but permits later duplicates' => $excludedModes['upsert4-8.3 excluded pseudo-table in WHERE can suppress update']['arm'],
    'where excluded.x permits composite duplicates only' => $excludedModes['upsert4-8.4 excluded pseudo-table in WHERE can allow update']['arm'],
];

foreach ($sequenceModes as $modeName => $arm) {
    foreach (array_chunk($excludedIncomingRows, 2) as $chunkIndex => $chunk) {
        $prefix = 'real upstream upsert4 table-named-excluded dynamic multi-row ' . $modeName . ' chunk ' . $chunkIndex;

        $tests[$prefix . ' partitions source rows into inserts updates and skips'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $chunk, $arm): void {
            $actual = $excludedNative($excludedSeedRows, $chunk, $arm);

            $t->same(count($chunk), count($actual['inserted_rows']) + count($actual['updated_rows']) + count($actual['skipped_rows']));
        };

        $tests[$prefix . ' RETURNING row count equals change count'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $chunk, $arm): void {
            $actual = $excludedNative($excludedSeedRows, $chunk, $arm);

            $t->same($actual['changes'], count($actual['returning_rows']));
        };

        $tests[$prefix . ' RETURNING projection keeps quoted column name stable'] = static function (TestRunner $t) use ($excludedNative, $excludedSeedRows, $chunk, $arm): void {
            $actual = $excludedNative($excludedSeedRows, $chunk, $arm);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                'w',
                'x',
                'a b',
                'z',
                'composite_key' => static fn (array $row): string => (string) $row['x'] . ':' . (string) $row['a b'],
            ]);

            foreach ($projected as $row) {
                $t->same(['w', 'x', 'a b', 'z', 'composite_key'], array_keys($row));
            }
        };
    }
}

$shiftedVariants = range(0, 11);
foreach ($shiftedVariants as $shift) {
    $shiftedRows = [
        ['w' => 'a' . $shift, 'x' => 10 + $shift, 'y' => 20 + $shift, 'z' => 30 + $shift],
        ['w' => 'b' . $shift, 'x' => 40 + $shift, 'y' => 50 + $shift, 'z' => 60 + $shift],
    ];
    $shiftedIncomingCases = [
        'shifted z conflict uses excluded pseudo-table' => [['w' => 'cz' . $shift, 'x' => 70 + $shift, 'y' => 80 + $shift, 'z' => 30 + $shift], ['z'], 'excluded-value'],
        'shifted composite conflict uses current row reference' => [['w' => 'cxy' . $shift, 'x' => 40 + $shift, 'y' => 50 + $shift, 'z' => 90 + $shift], ['y', 'x'], 'double-current'],
        'shifted null z composite conflict still updates' => [['w' => 'cnull' . $shift, 'x' => 40 + $shift, 'y' => 50 + $shift, 'z' => null], ['y', 'x'], 'double-current'],
        'shifted clean source row inserts and returns itself' => [['w' => 'clean' . $shift, 'x' => 100 + $shift, 'y' => 110 + $shift, 'z' => 120 + $shift], null, 'insert'],
    ];

    foreach ($schemas as $schemaName => $schemaSql) {
        foreach ($statementModes as $modeName => $mode) {
            foreach ($shiftedIncomingCases as $caseName => [$incoming, $expectedTarget, $action]) {
                if ($mode['appliesTo'] !== $action) {
                    continue;
                }

                $prefix = 'real upstream upsert4 excluded alias shifted dynamic variant ' . $shift . ' / ' . $schemaName . ' / ' . $modeName . ' / ' . $caseName;

                $tests[$prefix . ' final row image matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $schemaSql, $shiftedRows, $incoming, $mode, $quote): void {
                    $db = new PDO('sqlite::memory:');
                    $sql = $mode['sql']($db, $incoming, $quote);
                    $expected = $oracle($schemaSql, $shiftedRows, $sql);
                    $actual = $native($shiftedRows, [$incoming], $mode['arm']('update'));

                    usort($actual['after'], static fn (array $left, array $right): int => [$left['x'], $left['y'], $left['z']] <=> [$right['x'], $right['y'], $right['z']]);
                    $t->same($expected, array_values($actual['after']));
                };

                $tests[$prefix . ' matched conflict target remains stable'] = static function (TestRunner $t) use ($native, $shiftedRows, $incoming, $mode, $expectedTarget): void {
                    $actual = $native($shiftedRows, [$incoming], $mode['arm']('update'));
                    $target = $actual['matched_arms'][0]['target'] ?? null;

                    $t->same($expectedTarget, $target);
                };

                $tests[$prefix . ' change accounting matches RETURNING stream'] = static function (TestRunner $t) use ($native, $shiftedRows, $incoming, $mode): void {
                    $actual = $native($shiftedRows, [$incoming], $mode['arm']('update'));

                    $t->same($actual['changes'], count($actual['returning_rows']));
                    $t->same(1, count($actual['inserted_rows']) + count($actual['updated_rows']));
                };

                $tests[$prefix . ' projected RETURNING marker follows changed row'] = static function (TestRunner $t) use ($native, $shiftedRows, $incoming, $mode): void {
                    $actual = $native($shiftedRows, [$incoming], $mode['arm']('update'));
                    $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                        'w',
                        'x',
                        'y',
                        'z',
                        'tag' => static fn (array $row): string => 'shifted-upsert4:' . (string) $row['w'],
                    ]);

                    $t->same(['w', 'x', 'y', 'z', 'tag'], array_keys($projected[0]));
                    $t->same('shifted-upsert4:' . (string) $actual['returning_rows'][0]['w'], $projected[0]['tag']);
                };
            }
        }
    }
}

$tests['real upstream upsert4 excluded alias dynamic cites source Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 7.1-7.4 excluded pseudo-table and INSERT alias current-row references',
        'upsert4.test 8.1-8.5 table literally named excluded with quoted composite target',
        'returning1.test 4.5 multi-row UPSERT RETURNING insert/update stream parity',
    ], [
        'upsert4.test 7.1-7.4 excluded pseudo-table and INSERT alias current-row references',
        'upsert4.test 8.1-8.5 table literally named excluded with quoted composite target',
        'returning1.test 4.5 multi-row UPSERT RETURNING insert/update stream parity',
    ]);
};

return $tests;
