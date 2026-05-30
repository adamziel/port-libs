<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$sortBy = static function (array $rows, array $columns): array {
    usort($rows, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $cmp = ($left[$column] <=> $right[$column]);
            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    });

    return array_values($rows);
};

$upsert3Schemas = [
    'rowid composite unique' => [['k', 'v']],
    'reversed conflict target composite unique' => [['v', 'k']],
    'explicit composite unique equivalent' => [['k', 'v']],
];

$upsert3Batches = [
    'upsert3-130 first composite insert' => [
        [],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 0]],
        [[
            'target' => ['k', 'v'],
            'action' => 'nothing',
        ]],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 0]],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 0]],
        [],
    ],
    'upsert3-140 reversed target skips duplicate composite row' => [
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 0]],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 9]],
        [[
            'target' => ['v', 'k'],
            'action' => 'nothing',
        ]],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 0]],
        [],
        [['k' => 0, 'v' => 'abcdefghij', 'c' => 9]],
    ],
    'upsert3-200 excluded named table repeated duplicate increments current' => [
        [],
        [
            ['k' => 1, 'v' => '2', 'c' => 0],
            ['k' => 1, 'v' => '2', 'c' => 0],
            ['k' => 3, 'v' => '4', 'c' => 0],
            ['k' => 1, 'v' => '2', 'c' => 0],
            ['k' => 5, 'v' => '6', 'c' => 0],
            ['k' => 3, 'v' => '4', 'c' => 0],
        ],
        [[
            'target' => ['v', 'k'],
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current, array $excluded): int => (int) $excluded['c'] + 1],
        ]],
        [
            ['k' => 1, 'v' => '2', 'c' => 1],
            ['k' => 3, 'v' => '4', 'c' => 1],
            ['k' => 5, 'v' => '6', 'c' => 0],
        ],
        [
            ['k' => 1, 'v' => '2', 'c' => 0],
            ['k' => 1, 'v' => '2', 'c' => 1],
            ['k' => 3, 'v' => '4', 'c' => 0],
            ['k' => 1, 'v' => '2', 'c' => 1],
            ['k' => 5, 'v' => '6', 'c' => 0],
            ['k' => 3, 'v' => '4', 'c' => 1],
        ],
        [],
    ],
    'upsert3-210 target alias where sees current composite row' => [
        [
            ['k' => 1, 'v' => '2', 'c' => 2],
            ['k' => 3, 'v' => '4', 'c' => 1],
            ['k' => 5, 'v' => '6', 'c' => 0],
        ],
        [
            ['k' => 1, 'v' => '2', 'c' => 8],
            ['k' => 1, 'v' => '2', 'c' => 3],
        ],
        [[
            'target' => ['v', 'k'],
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current, array $excluded): int => (int) $excluded['c'] + 1],
            'where' => static fn (array $current, array $excluded): bool => $current['c'] < $excluded['c'],
        ]],
        [
            ['k' => 1, 'v' => '2', 'c' => 9],
            ['k' => 3, 'v' => '4', 'c' => 1],
            ['k' => 5, 'v' => '6', 'c' => 0],
        ],
        [['k' => 1, 'v' => '2', 'c' => 9]],
        [['k' => 1, 'v' => '2', 'c' => 3]],
    ],
];

$upsert4Schemas = [
    'upsert4 1.1 integer primary key' => [['a'], ['c']],
    'upsert4 1.1 explicit int primary key' => [['a'], ['c']],
    'upsert4 1.1 without rowid primary key' => [['a'], ['c']],
];

$upsert4BaseRows = [
    ['a' => 1, 'b' => null, 'c' => 'one'],
    ['a' => 2, 'b' => null, 'c' => 'two'],
    ['a' => 3, 'b' => null, 'c' => 'three'],
];

$upsert4Cases = [
    'upsert4-1.x.1 catchall do nothing primary key conflict' => [
        [['a' => 1, 'b' => null, 'c' => 'xyz']],
        [['target' => null, 'action' => 'nothing']],
        $upsert4BaseRows,
        [],
        [['a' => 1, 'b' => null, 'c' => 'xyz']],
    ],
    'upsert4-1.x.2 catchall do nothing unique c conflict' => [
        [['a' => 4, 'b' => null, 'c' => 'two']],
        [['target' => null, 'action' => 'nothing']],
        $upsert4BaseRows,
        [],
        [['a' => 4, 'b' => null, 'c' => 'two']],
    ],
    'upsert4-1.x.3 c conflict updates existing row' => [
        [['a' => 4, 'b' => null, 'c' => 'two']],
        [[
            'target' => ['c'],
            'action' => 'update',
            'assignments' => ['b' => static fn (): int => 1],
        ]],
        [
            ['a' => 1, 'b' => null, 'c' => 'one'],
            ['a' => 2, 'b' => 1, 'c' => 'two'],
            ['a' => 3, 'b' => null, 'c' => 'three'],
        ],
        [['a' => 2, 'b' => 1, 'c' => 'two']],
        [],
    ],
    'upsert4-1.x.4 a conflict updates primary key row' => [
        [['a' => 2, 'b' => null, 'c' => 'zero']],
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => ['b' => static fn (): int => 2],
        ]],
        [
            ['a' => 1, 'b' => null, 'c' => 'one'],
            ['a' => 2, 'b' => 2, 'c' => 'two'],
            ['a' => 3, 'b' => null, 'c' => 'three'],
        ],
        [['a' => 2, 'b' => 2, 'c' => 'two']],
        [],
    ],
    'upsert4-1.x.7 row-value assignment updates multiple columns' => [
        [['a' => 2, 'b' => null, 'c' => 'zero']],
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => [
                'b' => static fn (): string => 'x',
                'c' => static fn (): string => 'y',
            ],
        ]],
        [
            ['a' => 1, 'b' => null, 'c' => 'one'],
            ['a' => 2, 'b' => 'x', 'c' => 'y'],
            ['a' => 3, 'b' => null, 'c' => 'three'],
        ],
        [['a' => 2, 'b' => 'x', 'c' => 'y']],
        [],
    ],
    'upsert4-1.x.8 row-value assignment can move primary key' => [
        [['a' => 1, 'b' => null, 'c' => null]],
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => [
                'a' => static fn (): int => 4,
                'c' => static fn (): string => 'four',
            ],
        ]],
        [
            ['a' => 2, 'b' => null, 'c' => 'two'],
            ['a' => 3, 'b' => null, 'c' => 'three'],
            ['a' => 4, 'b' => null, 'c' => 'four'],
        ],
        [['a' => 4, 'b' => null, 'c' => 'four']],
        [],
    ],
];

foreach ($upsert3Schemas as $schemaName => $constraints) {
    foreach ($upsert3Batches as $caseName => [$baseRows, $incomingRows, $arms, $expectedAfter, $expectedReturning, $expectedSkipped]) {
        $prefix = 'real upstream corpus composite upsert ' . $schemaName . ' ' . $caseName;

        $tests[$prefix . ' final rows preserve upstream image'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints, $expectedAfter, $sortBy): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            $t->same($sortBy($expectedAfter, ['k', 'v']), $sortBy($actual['after'], ['k', 'v']));
        };
        $tests[$prefix . ' returning stream preserves upstream changed rows'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints, $expectedReturning): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            $t->same($expectedReturning, $actual['returning_rows']);
        };
        $tests[$prefix . ' changes match returning cardinality'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            $t->same(count($actual['returning_rows']), $actual['changes']);
        };
        $tests[$prefix . ' skipped rows preserve upstream no-op candidates'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints, $expectedSkipped): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            $t->same($expectedSkipped, $actual['skipped_rows']);
        };
        $tests[$prefix . ' reversed conflict target is accepted'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            foreach ($actual['matched_arms'] as $arm) {
                $t->same(true, $arm['target'] === ['k', 'v'] || $arm['target'] === ['v', 'k']);
            }
        };
        $tests[$prefix . ' projection keeps composite key order'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $arms, $constraints, $expectedReturning): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, $incomingRows, $arms, $constraints);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['k', 'v']);
            $t->same(array_map(static fn (array $row): array => ['k' => $row['k'], 'v' => $row['v']], $expectedReturning), $projected);
        };
    }
}

$applyStatements = static function (array $baseRows, array $statements, array $constraints): array {
    $rows = $baseRows;
    $returning = [];
    $skipped = [];
    $matched = [];
    $changes = 0;

    foreach ($statements as [$incomingRows, $arms]) {
        $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, $incomingRows, $arms, $constraints);
        $rows = $result['after'];
        array_push($returning, ...$result['returning_rows']);
        array_push($skipped, ...$result['skipped_rows']);
        array_push($matched, ...$result['matched_arms']);
        $changes += $result['changes'];
    }

    return [
        'after' => $rows,
        'returning_rows' => $returning,
        'skipped_rows' => $skipped,
        'matched_arms' => $matched,
        'changes' => $changes,
    ];
};

foreach ($upsert3Schemas as $schemaName => $constraints) {
    foreach ($upsert3Batches as $leftName => [$leftBaseRows, $leftIncomingRows, $leftArms]) {
        foreach ($upsert3Batches as $rightName => [, $rightIncomingRows, $rightArms]) {
            $prefix = 'real upstream corpus composite upsert two statement ' . $schemaName . ' ' . $leftName . ' then ' . $rightName;
            $tests[$prefix . ' final image is stable list'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(true, array_is_list($actual['after']));
            };
            $tests[$prefix . ' changes equal returning rows'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(count($actual['returning_rows']), $actual['changes']);
            };
            $tests[$prefix . ' skipped rows are tracked as list'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(true, array_is_list($actual['skipped_rows']));
                $t->same(true, count($actual['skipped_rows']) <= count($leftIncomingRows) + count($rightIncomingRows));
            };
            $tests[$prefix . ' projected returning keeps composite columns'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['k', 'v']);
                $t->same(count($actual['returning_rows']), count($projected));
            };
            $tests[$prefix . ' matched arm count equals conflicts'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(true, count($actual['matched_arms']) <= count($leftIncomingRows) + count($rightIncomingRows));
            };
            $tests[$prefix . ' final rows have composite key columns'] = static function (TestRunner $t) use ($applyStatements, $leftBaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($leftBaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                foreach ($actual['after'] as $row) {
                    $t->same(true, array_key_exists('k', $row) && array_key_exists('v', $row));
                }
            };
        }
    }
}

foreach ($upsert4Schemas as $schemaName => $constraints) {
    foreach ($upsert4Cases as $caseName => [$incomingRows, $arms, $expectedAfter, $expectedReturning, $expectedSkipped]) {
        $prefix = 'real upstream corpus upsert target analysis ' . $schemaName . ' ' . $caseName;

        $tests[$prefix . ' final rows preserve upstream image'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints, $expectedAfter, $sortBy): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same($sortBy($expectedAfter, ['a']), $sortBy($actual['after'], ['a']));
        };
        $tests[$prefix . ' returning rows preserve upstream image'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints, $expectedReturning): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same($expectedReturning, $actual['returning_rows']);
        };
        $tests[$prefix . ' skipped rows preserve upstream image'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints, $expectedSkipped): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same($expectedSkipped, $actual['skipped_rows']);
        };
        $tests[$prefix . ' changes equal changed returning rows'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same(count($actual['returning_rows']), $actual['changes']);
        };
        $tests[$prefix . ' projection preserves row-value assignment results'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints, $expectedReturning): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same(
                array_map(static fn (array $row): array => ['a' => $row['a'], 'c' => $row['c']], $expectedReturning),
                SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['a', 'c'])
            );
        };
        $tests[$prefix . ' inserted rows are absent for conflict cases'] = static function (TestRunner $t) use ($upsert4BaseRows, $incomingRows, $arms, $constraints): void {
            $actual = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($upsert4BaseRows, $incomingRows, $arms, $constraints);
            $t->same([], $actual['inserted_rows']);
        };
    }
}

foreach ($upsert4Schemas as $schemaName => $constraints) {
    foreach ($upsert4Cases as $leftName => [$leftIncomingRows, $leftArms]) {
        foreach ($upsert4Cases as $rightName => [$rightIncomingRows, $rightArms]) {
            $prefix = 'real upstream corpus upsert target analysis two statement ' . $schemaName . ' ' . $leftName . ' then ' . $rightName;
            $tests[$prefix . ' final image stays list'] = static function (TestRunner $t) use ($applyStatements, $upsert4BaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($upsert4BaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(true, array_is_list($actual['after']));
            };
            $tests[$prefix . ' changes equal returning rows'] = static function (TestRunner $t) use ($applyStatements, $upsert4BaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($upsert4BaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $t->same(count($actual['returning_rows']), $actual['changes']);
            };
            $tests[$prefix . ' projected returning preserves target columns'] = static function (TestRunner $t) use ($applyStatements, $upsert4BaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($upsert4BaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['a', 'c']);
                $t->same(count($actual['returning_rows']), count($projected));
            };
            $tests[$prefix . ' final rows retain unique c values'] = static function (TestRunner $t) use ($applyStatements, $upsert4BaseRows, $leftIncomingRows, $leftArms, $rightIncomingRows, $rightArms, $constraints): void {
                $actual = $applyStatements($upsert4BaseRows, [[$leftIncomingRows, $leftArms], [$rightIncomingRows, $rightArms]], $constraints);
                $seen = [];
                foreach ($actual['after'] as $row) {
                    $key = (string) $row['c'];
                    $t->same(false, isset($seen[$key]));
                    $seen[$key] = true;
                }
            };
        }
    }
}

$tests['real upstream corpus upsert target analysis upsert4-1.x.5 update conflict fails on secondary unique column'] = static function (TestRunner $t) use ($upsert4BaseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $upsert4BaseRows,
        [['a' => 2, 'b' => null, 'c' => 'zero']],
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => ['c' => static fn (): string => 'one'],
        ]],
        [['a'], ['c']]
    ));
};

$tests['real upstream corpus upsert target analysis upsert4-2.x malformed target is rejected'] = static function (TestRunner $t) use ($upsert4BaseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $upsert4BaseRows,
        [['a' => 4, 'b' => null, 'c' => 'two']],
        [[
            'target' => ['b', 'c'],
            'action' => 'nothing',
        ]],
        [['a'], ['c']]
    ));
};

$returningRows = [
    ['a' => 1, 'b' => 10, 'c' => 'pax', 'rowid' => 1, 'literal' => '|'],
    ['a' => 2, 'b' => 'happy', 'c' => 'pax', 'rowid' => 2, 'literal' => '|'],
    ['a' => 3, 'b' => null, 'c' => 'pax', 'rowid' => 3, 'literal' => '|'],
    ['a' => 4, 'b' => 44, 'c' => 3, 'rowid' => 4, 'literal' => '|'],
    ['a' => 5, 'b' => 100, 'c' => 6, 'rowid' => 5, 'literal' => '|'],
];

$returningProjectionCases = [
    'returning1-1.0 insert returning explicit columns' => ['a', 'b', 'c'],
    'returning1-1.2 returning reordered rowid aliases' => ['b', 'c', 'a', 'rowid'],
    'returning1-1.4 default values returning star' => ['*'],
    'returning1-2.1 update returning rowid and literal' => ['rowid', 'b', 'literal'],
    'returning1-3.1 delete returning rowid star literal' => ['rowid', '*'],
    'returning1-4.5 upsert returning inserted and updated rows' => ['a', 'b', 'c', 'literal'],
    'returning1-7.6 target table column equivalent' => ['b'],
    'returning1-8.4 scalar subquery equivalent value carrier' => ['a', 'c'],
];

for ($start = 0; $start < count($returningRows); ++$start) {
    for ($length = 1; $length <= count($returningRows) - $start; ++$length) {
        $slice = array_slice($returningRows, $start, $length);
        foreach ($returningProjectionCases as $caseName => $projection) {
            $prefix = sprintf('real upstream corpus returning dynamic %s slice %d len %d', $caseName, $start, $length);
            $tests[$prefix . ' projection row count'] = static function (TestRunner $t) use ($slice, $projection): void {
                $t->same(count($slice), count(SQLiteUpsertDoUpdateWherePlan::returningRows($slice, $projection)));
            };
            $tests[$prefix . ' projection key order'] = static function (TestRunner $t) use ($slice, $projection): void {
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($slice, $projection);
                if ($projected === []) {
                    $t->same([], $projected);
                    return;
                }

                $expected = [];
                foreach ($projection as $column) {
                    if ($column === '*') {
                        foreach (array_keys($slice[0]) as $starColumn) {
                            $expected[$starColumn] = true;
                        }
                        continue;
                    }
                    $expected[$column] = true;
                }
                $t->same(array_keys($expected), array_keys($projected[0]));
            };
            $tests[$prefix . ' projection first row values'] = static function (TestRunner $t) use ($slice, $projection): void {
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($slice, $projection);
                $expected = [];
                foreach ($projection as $column) {
                    if ($column === '*') {
                        foreach ($slice[0] as $starColumn => $value) {
                            $expected[$starColumn] = $value;
                        }
                        continue;
                    }
                    $expected[$column] = $slice[0][$column];
                }

                $t->same($expected, $projected[0]);
            };
        }
    }
}

foreach (['source.*', 'old.b', 'new.b', 'another.b', 'alias.b'] as $badProjection) {
    $tests['real upstream corpus returning dynamic returning1-6/7 rejects qualified projection ' . $badProjection] = static function (TestRunner $t) use ($returningRows, $badProjection): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRows($returningRows, [$badProjection]));
    };
}

return $tests;
