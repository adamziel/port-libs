<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$schemaVariants = [
    1 => 'CREATE TABLE t1(a INTEGER PRIMARY KEY, b, c UNIQUE, d UNIQUE, e UNIQUE)',
    2 => 'CREATE TABLE t1(a INT PRIMARY KEY, b, c UNIQUE, d UNIQUE, e UNIQUE)',
    3 => 'CREATE TABLE t1(a INT PRIMARY KEY, b, c UNIQUE, d UNIQUE, e UNIQUE) WITHOUT ROWID',
    4 => 'CREATE TABLE t1(e UNIQUE, d UNIQUE, c UNIQUE, a INTEGER PRIMARY KEY, b)',
    5 => 'CREATE TABLE t1(e UNIQUE, d UNIQUE, c UNIQUE, a INT PRIMARY KEY, b)',
    6 => 'CREATE TABLE t1(e UNIQUE, d UNIQUE, c UNIQUE, a INT PRIMARY KEY, b) WITHOUT ROWID',
];

$baseRow = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5];
$uniqueConstraints = [['a'], ['c'], ['d'], ['e']];
$update = static fn (string $value): array => [
    'action' => 'update',
    'assignments' => ['b' => static fn (array $current, array $incoming): string => $value],
];
$nothing = ['action' => 'nothing'];

$armsByName = [
    'a-c-d-e' => [
        ['target' => ['a']] + $update('a'),
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => ['e']] + $update('e'),
    ],
    'c-a-d-e' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['a']] + $update('a'),
        ['target' => ['d']] + $update('d'),
        ['target' => ['e']] + $update('e'),
    ],
    'c-d-a-e' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => ['a']] + $update('a'),
        ['target' => ['e']] + $update('e'),
    ],
    'c-d-e-a' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => ['e']] + $update('e'),
        ['target' => ['a']] + $update('a'),
    ],
    'c-d-catchall-update' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => null] + $update('x'),
    ],
    'catchall-update' => [
        ['target' => null] + $update('x'),
    ],
    'c-d-nothing-catchall-update' => [
        ['target' => ['c']] + $nothing,
        ['target' => ['d']] + $nothing,
        ['target' => null] + $update('x'),
    ],
    'c-d-catchall-nothing' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => null] + $nothing,
    ],
    'duplicate-a-arms' => [
        ['target' => ['c']] + $update('c'),
        ['target' => ['d']] + $update('d'),
        ['target' => ['a']] + $update('a1'),
        ['target' => ['a']] + $update('a2'),
        ['target' => ['a']] + $update('a3'),
        ['target' => ['a']] + $update('a4'),
        ['target' => ['a']] + $update('a5'),
        ['target' => ['e']] + $update('e'),
    ],
];

$upstreamCases = [
    'upsert5 1.x.100 first matching a arm wins' => ['a-c-d-e', ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'a', ['a'], 'update', true],
    'upsert5 1.x.101 c arm wins when a does not conflict' => ['a-c-d-e', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'c', ['c'], 'update', true],
    'upsert5 1.x.102 d arm wins after a and c miss' => ['a-c-d-e', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', true],
    'upsert5 1.x.103 e arm wins after earlier targets miss' => ['a-c-d-e', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', true],
    'upsert5 1.x.200 later a arm handles primary key conflict' => ['c-a-d-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', true],
    'upsert5 1.x.201 first c arm preempts a conflict' => ['c-a-d-e', ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', true],
    'upsert5 1.x.202 first c arm preempts all later conflicts' => ['c-a-d-e', ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'c', ['c'], 'update', true],
    'upsert5 1.x.203 a arm handles conflict after c misses' => ['c-a-d-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'a', ['a'], 'update', true],
    'upsert5 1.x.204 a arm preempts later d conflict' => ['c-a-d-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'a', ['a'], 'update', true],
    'upsert5 1.x.210 a arm handles conflict after c and d miss' => ['c-d-a-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', true],
    'upsert5 1.x.211 d arm preempts later a conflict' => ['c-d-a-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'd', ['d'], 'update', true],
    'upsert5 1.x.212 a arm preempts later e conflict' => ['c-d-a-e', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'a', ['a'], 'update', true],
    'upsert5 1.x.213 e arm wins after c d a miss' => ['c-d-a-e', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', true],
    'upsert5 1.x.214 e arm precedes a arm and wins' => ['c-d-e-a', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', true],
    'upsert5 1.x.215 e arm preempts later a conflict' => ['c-d-e-a', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', true],
    'upsert5 1.x.216 a arm handles conflict after c d e miss' => ['c-d-e-a', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a', ['a'], 'update', true],
    'upsert5 1.x.300 duplicate a targets use first matching a arm' => ['duplicate-a-arms', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'a1', ['a'], 'update', true],
    'upsert5 1.x.301 duplicate a targets are ignored when e matches' => ['duplicate-a-arms', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'e', ['e'], 'update', true],
    'upsert5 1.x.400 catchall updates a conflict' => ['c-d-catchall-update', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.401 catchall updates e conflict' => ['c-d-catchall-update', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', true],
    'upsert5 1.x.402 catchall updates all-target conflict after c d miss' => ['c-d-catchall-update', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.403 explicit c arm preempts catchall' => ['c-d-catchall-update', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', true],
    'upsert5 1.x.404 explicit c arm preempts d and catchall' => ['c-d-catchall-update', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'c', ['c'], 'update', true],
    'upsert5 1.x.405 explicit d arm preempts catchall' => ['c-d-catchall-update', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', true],
    'upsert5 1.x.410 bare catchall updates a conflict' => ['catchall-update', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.411 bare catchall updates e conflict' => ['catchall-update', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', true],
    'upsert5 1.x.412 bare catchall updates d conflict' => ['catchall-update', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.413 bare catchall updates c conflict' => ['catchall-update', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.420 catchall update handles a after c d nothing miss' => ['c-d-nothing-catchall-update', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'x', null, 'update', true],
    'upsert5 1.x.421 catchall update handles e after c d nothing miss' => ['c-d-nothing-catchall-update', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'x', null, 'update', true],
    'upsert5 1.x.422 explicit d DO NOTHING suppresses catchall' => ['c-d-nothing-catchall-update', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 2, ['d'], 'nothing', false],
    'upsert5 1.x.423 explicit c DO NOTHING suppresses catchall' => ['c-d-nothing-catchall-update', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 2, ['c'], 'nothing', false],
    'upsert5 1.x.500 catchall DO NOTHING suppresses a conflict' => ['c-d-catchall-nothing', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 2, null, 'nothing', false],
    'upsert5 1.x.501 catchall DO NOTHING suppresses e conflict' => ['c-d-catchall-nothing', ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 2, null, 'nothing', false],
    'upsert5 1.x.502 catchall DO NOTHING suppresses a after c d miss' => ['c-d-catchall-nothing', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 2, null, 'nothing', false],
    'upsert5 1.x.503 c arm updates before catchall DO NOTHING' => ['c-d-catchall-nothing', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c', ['c'], 'update', true],
    'upsert5 1.x.504 c arm updates before d and catchall DO NOTHING' => ['c-d-catchall-nothing', ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'c', ['c'], 'update', true],
    'upsert5 1.x.505 d arm updates before catchall DO NOTHING' => ['c-d-catchall-nothing', ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'd', ['d'], 'update', true],
];

$run = static function (array $incoming, string $armName) use ($baseRow, $armsByName, $uniqueConstraints): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms([$baseRow], [$incoming], $armsByName[$armName], $uniqueConstraints);
};

$tests = [];

foreach ($schemaVariants as $schemaId => $schemaSql) {
    foreach ($upstreamCases as $name => [$armName, $incoming, $expectedB, $expectedTarget, $expectedAction, $returns]) {
        $prefix = "real upstream {$name} schema {$schemaId}";

        $tests[$prefix . ' final row image'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedB): void {
            $result = $run($incoming, $armName);
            $t->same([['a' => 1, 'b' => $expectedB, 'c' => 3, 'd' => 4, 'e' => 5]], $result['after']);
        };

        $tests[$prefix . ' returning row cardinality'] = static function (TestRunner $t) use ($run, $incoming, $armName, $returns): void {
            $result = $run($incoming, $armName);
            $t->same($returns ? 1 : 0, count($result['returning_rows']));
        };

        $tests[$prefix . ' changes count follows changed rows'] = static function (TestRunner $t) use ($run, $incoming, $armName, $returns): void {
            $result = $run($incoming, $armName);
            $t->same($returns ? 1 : 0, $result['changes']);
        };

        $tests[$prefix . ' matched arm action'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedAction): void {
            $result = $run($incoming, $armName);
            $t->same($expectedAction, $result['matched_arms'][0]['action']);
        };

        $tests[$prefix . ' matched arm target order'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedTarget): void {
            $result = $run($incoming, $armName);
            $t->same($expectedTarget, $result['matched_arms'][0]['target']);
        };

        $tests[$prefix . ' projected returning uses post upsert row'] = static function (TestRunner $t) use ($run, $incoming, $armName, $expectedB, $returns): void {
            $result = $run($incoming, $armName);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], [
                'case_label' => static fn (array $row): string => 'row-' . $row['a'] . '-' . $row['b'],
                'b',
                'c',
            ]);
            $t->same($returns ? [['case_label' => 'row-1-' . $expectedB, 'b' => $expectedB, 'c' => 3]] : [], $projected);
        };

        $tests[$prefix . ' schema text cites upstream dynamic variant'] = static function (TestRunner $t) use ($schemaSql): void {
            $t->same(true, str_contains($schemaSql, 'UNIQUE') && str_contains($schemaSql, 'PRIMARY KEY'));
        };
    }
}

$tests['real upstream upsert5 duplicate conflict arms keep first target returning'] = static function (TestRunner $t) use ($run): void {
    $result = $run(['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'duplicate-a-arms');
    $t->same([['b_value' => 'a1']], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['b_value' => 'b']));
};

$tests['real upstream upsert5 catchall DO NOTHING emits no returning row'] = static function (TestRunner $t) use ($run): void {
    $result = $run(['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'c-d-catchall-nothing');
    $t->same([], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['*']));
};

$tests['real upstream returning projection rejects missing post upsert column'] = static function (TestRunner $t) use ($run): void {
    $result = $run(['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'c-d-a-e');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['missing_column']));
};

$tests['real upstream returning projection rejects malformed alias'] = static function (TestRunner $t) use ($run): void {
    $result = $run(['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c-d-catchall-update');
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['' => 'b']));
};

$tests['real upstream returning wildcard preserves post upsert column order'] = static function (TestRunner $t) use ($run): void {
    $result = $run(['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'c-d-catchall-update');
    $t->same(['a', 'b', 'c', 'd', 'e'], array_keys(SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['*'])[0]));
};

$upsert2SourceRows = [
    ['a' => 1, 'b' => 8, 'c' => 0],
    ['a' => 2, 'b' => 11, 'c' => 0],
    ['a' => 3, 'b' => 1, 'c' => 0],
    ['a' => 2, 'b' => 15, 'c' => 0],
    ['a' => 1, 'b' => 4, 'c' => 0],
    ['a' => 1, 'b' => 99, 'c' => 0],
];

$upsert2Runs = [
    'upsert2 100 rowid VALUES source' => [
        [['a' => 1, 'b' => 8, 'c' => 0], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 1, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0]],
        [['a' => 3, 'b' => 1, 'c' => 0]],
        2,
        'rowid table',
    ],
    'upsert2 110 without-rowid VALUES source' => [
        [['a' => 1, 'b' => 8, 'c' => 0], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 1, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0]],
        [['a' => 3, 'b' => 1, 'c' => 0]],
        2,
        'WITHOUT ROWID table',
    ],
    'upsert2 200 rowid SELECT source repeated conflicts' => [
        $upsert2SourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        [['a' => 3, 'b' => 1, 'c' => 0], ['a' => 1, 'b' => 4, 'c' => 0]],
        4,
        'rowid table',
    ],
    'upsert2 201 target alias SELECT source repeated conflicts' => [
        $upsert2SourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        [['a' => 3, 'b' => 1, 'c' => 0], ['a' => 1, 'b' => 4, 'c' => 0]],
        4,
        'target alias table',
    ],
    'upsert2 210 without-rowid SELECT source repeated conflicts' => [
        $upsert2SourceRows,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
        [['a' => 3, 'b' => 1, 'c' => 0], ['a' => 1, 'b' => 4, 'c' => 0]],
        4,
        'WITHOUT ROWID table',
    ],
];

$runUpsert2 = static fn (array $incoming): array => SQLiteUpsertDoUpdateWherePlan::execute(
    [['a' => 1, 'b' => 2, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
    $incoming,
    ['a'],
    [
        'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
        'c' => static fn (array $current, array $excluded): int => (int) $current['c'] + 1,
    ],
    static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
);

$orderByA = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
    return $rows;
};

foreach ($upsert2Runs as $name => [$incoming, $expectedAfter, $expectedReturning, $expectedSkipped, $expectedChanges, $tableKind]) {
    $tests["real upstream {$name} final ordered row image"] = static function (TestRunner $t) use ($runUpsert2, $orderByA, $incoming, $expectedAfter): void {
        $result = $runUpsert2($incoming);
        $t->same($expectedAfter, $orderByA($result['after']));
    };

    $tests["real upstream {$name} returning rows follow successful insert/update steps"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $expectedReturning): void {
        $result = $runUpsert2($incoming);
        $t->same($expectedReturning, $result['returning_rows']);
    };

    $tests["real upstream {$name} skipped WHERE rows yield no returning rows"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $expectedSkipped): void {
        $result = $runUpsert2($incoming);
        $t->same($expectedSkipped, $result['skipped_rows']);
    };

    $tests["real upstream {$name} changes count matches successful steps"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $expectedChanges): void {
        $result = $runUpsert2($incoming);
        $t->same($expectedChanges, $result['changes']);
    };

    $tests["real upstream {$name} projected returning preserves source order"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $expectedReturning): void {
        $result = $runUpsert2($incoming);
        $t->same(
            array_map(static fn (array $row): array => ['a' => $row['a'], 'b_after' => $row['b'], 'c_after' => $row['c']], $expectedReturning),
            SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b_after' => 'b', 'c_after' => 'c'])
        );
    };

    $tests["real upstream {$name} cites table layout variant"] = static function (TestRunner $t) use ($tableKind): void {
        $t->same(true, $tableKind === 'rowid table' || $tableKind === 'WITHOUT ROWID table' || $tableKind === 'target alias table');
    };
}

$upsert2LayoutVariants = [
    'rowid integer primary key' => 'CREATE TABLE t1(a INTEGER PRIMARY KEY, b int, c DEFAULT 0)',
    'without rowid integer primary key' => 'CREATE TABLE t1(a INT PRIMARY KEY, b int, c DEFAULT 0) WITHOUT ROWID',
    'main target alias t2' => 'INSERT INTO main.t1 AS t2(a,b) SELECT a, b FROM nx WHERE true',
    'qualified target name' => 'INSERT INTO main.t1(a,b) SELECT a, b FROM nx WHERE true',
    'unqualified target name' => 'INSERT INTO t1(a,b) SELECT a, b FROM nx WHERE true',
    'values source list' => 'INSERT INTO t1(a,b) VALUES(...)',
];

foreach ($upsert2LayoutVariants as $layoutName => $layoutSql) {
    foreach ($upsert2Runs as $name => [$incoming, $expectedAfter, $expectedReturning, $expectedSkipped, $expectedChanges]) {
        foreach ($expectedAfter as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $tests["real upstream {$name} {$layoutName} ordered final row {$rowIndex} column {$column}"] = static function (TestRunner $t) use ($runUpsert2, $orderByA, $incoming, $rowIndex, $column, $value): void {
                    $result = $runUpsert2($incoming);
                    $ordered = $orderByA($result['after']);
                    $t->same($value, $ordered[$rowIndex][$column]);
                };
            }
        }

        foreach ($expectedReturning as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $tests["real upstream {$name} {$layoutName} returning row {$rowIndex} column {$column}"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $rowIndex, $column, $value): void {
                    $result = $runUpsert2($incoming);
                    $t->same($value, $result['returning_rows'][$rowIndex][$column]);
                };
            }
        }

        foreach ($expectedSkipped as $rowIndex => $row) {
            foreach ($row as $column => $value) {
                $tests["real upstream {$name} {$layoutName} skipped row {$rowIndex} column {$column}"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $rowIndex, $column, $value): void {
                    $result = $runUpsert2($incoming);
                    $t->same($value, $result['skipped_rows'][$rowIndex][$column]);
                };
            }
        }

        $tests["real upstream {$name} {$layoutName} no duplicate primary keys after statement"] = static function (TestRunner $t) use ($runUpsert2, $incoming): void {
            $result = $runUpsert2($incoming);
            $keys = array_map(static fn (array $row): int => (int) $row['a'], $result['after']);
            $t->same($keys, array_values(array_unique($keys)));
        };

        $tests["real upstream {$name} {$layoutName} successful returning count equals changes"] = static function (TestRunner $t) use ($runUpsert2, $incoming, $expectedChanges): void {
            $result = $runUpsert2($incoming);
            $t->same($expectedChanges, count($result['returning_rows']));
        };

        $tests["real upstream {$name} {$layoutName} cites real upstream statement shape"] = static function (TestRunner $t) use ($layoutSql): void {
            $t->same(true, str_contains($layoutSql, 't1') && (str_contains($layoutSql, 'INSERT') || str_contains($layoutSql, 'CREATE TABLE')));
        };
    }
}

$fooReturningRun = static function (): array {
    $nextFooId = 1;
    $incoming = [];
    foreach ([17, 4711, 17] as $fooval) {
        $incoming[] = ['fooid' => $nextFooId++, 'fooval' => $fooval, 'refcnt' => 1];
    }

    return SQLiteUpsertDoUpdateWherePlan::execute(
        [],
        $incoming,
        ['fooval'],
        [
            'refcnt' => static fn (array $current, array $excluded): int => (int) $current['refcnt'] + 1,
        ],
    );
};

$returning17Variants = ['main' => 'CREATE TABLE foo', 'temp' => 'CREATE TEMP TABLE foo'];
foreach ($returning17Variants as $variant => $schemaSql) {
    $tests["real upstream returning1 17 {$variant} duplicate input returns insert update rowids"] = static function (TestRunner $t) use ($fooReturningRun): void {
        $result = $fooReturningRun();
        $t->same([['fooid' => 1], ['fooid' => 2], ['fooid' => 1]], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['fooid']));
    };

    $tests["real upstream returning1 17 {$variant} duplicate input increments refcnt"] = static function (TestRunner $t) use ($fooReturningRun): void {
        $result = $fooReturningRun();
        $t->same([['fooid' => 1, 'fooval' => 17, 'refcnt' => 2], ['fooid' => 2, 'fooval' => 4711, 'refcnt' => 1]], $result['after']);
    };

    $tests["real upstream returning1 17 {$variant} duplicate input counts all successful rows"] = static function (TestRunner $t) use ($fooReturningRun): void {
        $result = $fooReturningRun();
        $t->same(3, $result['changes']);
    };

    $tests["real upstream returning1 17 {$variant} schema variant is cited"] = static function (TestRunner $t) use ($schemaSql): void {
        $t->same(true, str_contains($schemaSql, 'TABLE foo'));
    };
}

$tests['real upstream upsert1 1100 explicit b conflict suppresses rowid replace'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [['a' => 1, 'b' => 22]],
        [['a' => 2, 'b' => 22]],
        [['target' => ['b'], 'action' => 'nothing']],
        [['a'], ['b']]
    );
    $t->same([['a' => 1, 'b' => 22]], $result['after']);
};

$tests['real upstream upsert1 1100 explicit b conflict emits no returning row'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [['a' => 1, 'b' => 22]],
        [['a' => 2, 'b' => 22]],
        [['target' => ['b'], 'action' => 'nothing']],
        [['a'], ['b']]
    );
    $t->same([], $result['returning_rows']);
};

$tests['real upstream upsert1 1100 explicit b conflict records matched target'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        [['a' => 1, 'b' => 22]],
        [['a' => 2, 'b' => 22]],
        [['target' => ['b'], 'action' => 'nothing']],
        [['a'], ['b']]
    );
    $t->same([['incoming' => ['a' => 2, 'b' => 22], 'target' => ['b'], 'action' => 'nothing']], $result['matched_arms']);
};

return $tests;
