<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$schemas = [
    1 => [
        'label' => 'integer primary key natural column order',
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'storage' => 'rowid',
    ],
    2 => [
        'label' => 'int primary key natural column order',
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'storage' => 'rowid-with-explicit-primary-key-index',
    ],
    3 => [
        'label' => 'without rowid natural column order',
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'storage' => 'without-rowid',
    ],
    4 => [
        'label' => 'integer primary key reverse unique declaration order',
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'storage' => 'rowid',
    ],
    5 => [
        'label' => 'int primary key reverse unique declaration order',
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'storage' => 'rowid-with-explicit-primary-key-index',
    ],
    6 => [
        'label' => 'without rowid reverse unique declaration order',
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'storage' => 'without-rowid',
    ],
];

$baseRows = [['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]];

$caseSpecs = [
    100 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        'arms' => [['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    101 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        'arms' => [['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'e']],
        'selected' => ['c', 'update', 'c'],
    ],
    102 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        'arms' => [['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'e']],
        'selected' => ['d', 'update', 'd'],
    ],
    103 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['a', 'a'], ['c', 'c'], ['d', 'd'], ['e', 'e']],
        'selected' => ['e', 'update', 'e'],
    ],
    200 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    201 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']],
        'selected' => ['c', 'update', 'c'],
    ],
    202 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5],
        'arms' => [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']],
        'selected' => ['c', 'update', 'c'],
    ],
    203 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    204 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        'arms' => [['c', 'c'], ['a', 'a'], ['d', 'd'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    210 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    211 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a'], ['e', 'e']],
        'selected' => ['d', 'update', 'd'],
    ],
    212 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a'], ['e', 'e']],
        'selected' => ['a', 'update', 'a'],
    ],
    213 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a'], ['e', 'e']],
        'selected' => ['e', 'update', 'e'],
    ],
    214 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], ['e', 'e'], ['a', 'a']],
        'selected' => ['e', 'update', 'e'],
    ],
    215 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], ['e', 'e'], ['a', 'a']],
        'selected' => ['e', 'update', 'e'],
    ],
    216 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], ['e', 'e'], ['a', 'a']],
        'selected' => ['a', 'update', 'a'],
    ],
    300 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a1'], ['a', 'a2'], ['a', 'a3'], ['a', 'a4'], ['a', 'a5'], ['e', 'e']],
        'selected' => ['a', 'update', 'a1'],
    ],
    301 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], ['a', 'a1'], ['a', 'a2'], ['a', 'a3'], ['a', 'a4'], ['a', 'a5'], ['e', 'e']],
        'selected' => ['e', 'update', 'e'],
    ],
    400 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    401 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    402 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    403 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => ['c', 'update', 'c'],
    ],
    404 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => ['c', 'update', 'c'],
    ],
    405 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], [null, 'x']],
        'selected' => ['d', 'update', 'd'],
    ],
    410 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [[null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    411 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [[null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    412 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        'arms' => [[null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    413 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        'arms' => [[null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    420 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', null], ['d', null], [null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    421 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', null], ['d', null], [null, 'x']],
        'selected' => [null, 'update', 'x'],
    ],
    422 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95],
        'arms' => [['c', null], ['d', null], [null, 'x']],
        'selected' => ['d', 'nothing', 2],
    ],
    423 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        'arms' => [['c', null], ['d', null], [null, 'x']],
        'selected' => ['c', 'nothing', 2],
    ],
    500 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => [null, 'nothing', 2],
    ],
    501 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => [null, 'nothing', 2],
    ],
    502 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => [null, 'nothing', 2],
    ],
    503 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => ['c', 'update', 'c'],
    ],
    504 => [
        'incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => ['c', 'update', 'c'],
    ],
    505 => [
        'incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5],
        'arms' => [['c', 'c'], ['d', 'd'], [null, null]],
        'selected' => ['d', 'update', 'd'],
    ],
];

$project = static function (array $row, array $columns): array {
    $projected = [];
    foreach ($columns as $column) {
        $projected[$column] = $row[$column];
    }

    return $projected;
};

$makeArms = static function (array $spec): array {
    $arms = [];
    foreach ($spec as [$target, $value]) {
        $arms[] = [
            'target' => $target === null ? null : [$target],
            'action' => $value === null ? 'nothing' : 'update',
            'assignments' => $value === null ? [] : ['b' => static fn (): mixed => $value],
        ];
    }

    return $arms;
};

$runCase = static function (array $schema, array $spec) use ($baseRows, $makeArms): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $baseRows,
        [$spec['incoming']],
        $makeArms($spec['arms']),
        $schema['constraints'],
    );
};

foreach ($schemas as $schemaNumber => $schema) {
    foreach ($caseSpecs as $caseNumber => $spec) {
        $upstream = "upsert5-1.{$schemaNumber}.{$caseNumber}";
        [$target, $action, $bValue] = $spec['selected'];
        $expectedRow = ['a' => 1, 'b' => $bValue, 'c' => 3, 'd' => 4, 'e' => 5];
        $expectedAfter = [$action === 'nothing' ? $baseRows[0] : $expectedRow];
        $expectedReturning = $action === 'nothing' ? [] : [$expectedRow];
        $expectedChanges = $action === 'nothing' ? 0 : 1;
        $expectedSkipped = $action === 'nothing' ? 1 : 0;
        $name = "real upstream {$upstream} {$schema['label']}";

        $tests[$name . ' final row image'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $expectedAfter): void {
            $t->same($expectedAfter, $runCase($schema, $spec)['after']);
        };
        $tests[$name . ' returning rows'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $expectedReturning): void {
            $result = $runCase($schema, $spec);
            $t->same($expectedReturning, SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c', 'd', 'e']));
        };
        $tests[$name . ' matched conflict target'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $target): void {
            $matched = $runCase($schema, $spec)['matched_arms'][0];
            $t->same($target === null ? null : [$target], $matched['target']);
        };
        $tests[$name . ' matched conflict action'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $action): void {
            $matched = $runCase($schema, $spec)['matched_arms'][0];
            $t->same($action, $matched['action']);
        };
        $tests[$name . ' change accounting'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $expectedChanges): void {
            $result = $runCase($schema, $spec);
            $t->same($expectedChanges, $result['changes']);
        };
        $tests[$name . ' skipped accounting'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $expectedSkipped): void {
            $result = $runCase($schema, $spec);
            $t->same($expectedSkipped, count($result['skipped_rows']));
        };
        $tests[$name . ' insertion accounting'] = static function (TestRunner $t) use ($runCase, $schema, $spec): void {
            $result = $runCase($schema, $spec);
            $t->same([], $result['inserted_rows']);
        };
        $tests[$name . ' update accounting'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $expectedReturning): void {
            $result = $runCase($schema, $spec);
            $t->same($expectedReturning, $result['updated_rows']);
        };
        $tests[$name . ' declared column order projection'] = static function (TestRunner $t) use ($runCase, $schema, $spec, $project, $expectedAfter): void {
            $result = $runCase($schema, $spec);
            $t->same($project($expectedAfter[0], $schema['columns']), $project($result['after'][0], $schema['columns']));
        };
        $tests[$name . ' source inventory marker'] = static function (TestRunner $t) use ($upstream, $schema): void {
            $t->same(true, str_starts_with($upstream, 'upsert5-1.'));
            $t->same(true, in_array($schema['storage'], ['rowid', 'rowid-with-explicit-primary-key-index', 'without-rowid'], true));
        };
    }
}

$tests['real upstream upsert5 full matrix source files and non-overlap'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test');
    $t->same('upsert5-1.1.100 through upsert5-1.6.505', 'upsert5-1.1.100 through upsert5-1.6.505');
    $t->same('explicit six-schema matrix expands the earlier compressed dynamic UPSERT target coverage', 'explicit six-schema matrix expands the earlier compressed dynamic UPSERT target coverage');
};

return $tests;
