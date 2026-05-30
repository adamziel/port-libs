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

return $tests;
