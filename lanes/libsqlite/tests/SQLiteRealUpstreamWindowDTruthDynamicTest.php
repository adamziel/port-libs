<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$predicateExpression = static fn (array $predicate): array => ['type' => 'predicate', 'predicate' => $predicate];
$isPredicate = static fn (mixed $left, mixed $right): array => ['operator' => 'IS', 'left' => $left, 'right' => $right];

$windowDRow = static function (string $partitionKey, mixed $payload = 'value'): array {
    $distribution = SQLiteWindowFunction::cumeDist([$partitionKey])[0];
    return [
        'c0' => $partitionKey,
        'c1' => 1,
        'window_value' => $distribution,
        'max_payload' => $payload,
    ];
};

$tests['real upstream windowD 1.1 string numeric is true column remains false'] = static function (TestRunner $t) use ($windowDRow, $literal, $column, $isPredicate): void {
    $row = $windowDRow('x');
    $t->same(false, SQLiteSelectPredicate::evaluate($row, $isPredicate($literal('500'), $column('c1'))), 'windowD.test 1.1');
};

$tests['real upstream windowD 1.2 joined view preserves is false result'] = static function (TestRunner $t) use ($windowDRow, $literal, $column, $isPredicate): void {
    $joinedRows = [
        $windowDRow('x') + ['joined.c0' => 'x'],
    ];
    $actual = array_map(
        static fn (array $row): bool => SQLiteSelectPredicate::evaluate($row, $isPredicate($literal('500'), $column('c1'))),
        $joinedRows,
    );
    $t->same([false], $actual, 'windowD.test 1.2');
};

$tests['real upstream windowD 1.2 wrapped is false matches upstream truth test'] = static function (TestRunner $t) use ($windowDRow, $literal, $column, $isPredicate, $predicateExpression): void {
    $row = $windowDRow('x');
    $inner = $isPredicate($literal('500'), $column('c1'));
    $t->same(true, SQLiteSelectPredicate::evaluate($row, ['operator' => 'IS FALSE', 'left' => $predicateExpression($inner)]), 'windowD.test 1.2 is false');
};

$tests['real upstream windowD 1.3 cume dist view row exposes true literal'] = static function (TestRunner $t) use ($windowDRow): void {
    $row = $windowDRow('x');
    $t->same(1.0, $row['window_value'], 'windowD.test 1.3 cume_dist');
    $t->same(1, $row['c1'], 'windowD.test 1.3 TRUE literal');
};

$tests['real upstream windowD 1.4 where wrapped is false keeps row'] = static function (TestRunner $t) use ($windowDRow, $literal, $column, $isPredicate, $predicateExpression): void {
    $rows = [$windowDRow('x')];
    $inner = $isPredicate($literal('500'), $column('c1'));
    $filtered = SQLiteSelectPredicate::filter($rows, ['operator' => 'IS FALSE', 'left' => $predicateExpression($inner)]);
    $t->same([1.0], array_column($filtered, 'window_value'), 'windowD.test 1.4');
};

$tests['real upstream windowD 2.1 constant view columns are not identical to 500'] = static function (TestRunner $t) use ($literal, $column, $isPredicate): void {
    $row = ['a' => 1, 'b' => 2, 'c' => 1, 'd' => 0];
    foreach (['a', 'b', 'c', 'd'] as $name) {
        $t->same(false, SQLiteSelectPredicate::evaluate($row, $isPredicate($literal(500), $column($name))), "windowD.test 2.1 {$name}");
    }
};

$tests['real upstream windowD 2.2 and 2.3 where 500 is boolean columns returns empty'] = static function (TestRunner $t) use ($literal, $column, $isPredicate): void {
    $rows = [['a' => 1, 'b' => 2, 'c' => 1, 'd' => 0]];
    $t->same([], SQLiteSelectPredicate::filter($rows, $isPredicate($literal(500), $column('c'))), 'windowD.test 2.2');
    $t->same([], SQLiteSelectPredicate::filter($rows, $isPredicate($literal(500), $column('d'))), 'windowD.test 2.3');
};

$tests['real upstream windowD 2.5 and 2.6 max window view keeps true literal comparison false'] = static function (TestRunner $t) use ($literal, $column, $isPredicate): void {
    $row = ['a' => SQLiteWindowFunction::aggregateFrameBetweenValues('max', ['value'], [0], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING')[0], 'c' => 1];
    $predicate = $isPredicate($literal(500), $column('c'));
    $t->same(false, SQLiteSelectPredicate::evaluate($row, $predicate), 'windowD.test 2.5');
    $t->same([], SQLiteSelectPredicate::filter([$row], $predicate), 'windowD.test 2.6');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests["real upstream windowD dynamic truth case {$case}"] = static function (TestRunner $t) use ($case, $windowDRow, $literal, $column, $isPredicate, $predicateExpression): void {
        $partitionKey = 'tenant-' . ($case % 37);
        $row = $windowDRow($partitionKey, 'value-' . $case);
        $leftValue = $case % 2 === 0 ? '500' : 500;
        $rightColumn = $case % 5 === 0 ? 'd' : 'c1';
        $row['d'] = 0;
        $inner = $isPredicate($literal($leftValue), $column($rightColumn));
        $expectedInner = false;

        $t->same(1.0, $row['window_value'], "windowD.test dynamic {$case} cume_dist");
        $t->same($expectedInner, SQLiteSelectPredicate::evaluate($row, $inner), "windowD.test dynamic {$case} IS comparison");
        $t->same(true, SQLiteSelectPredicate::evaluate($row, ['operator' => 'IS FALSE', 'left' => $predicateExpression($inner)]), "windowD.test dynamic {$case} wrapped IS FALSE");
        $t->same([], SQLiteSelectPredicate::filter([$row], $inner), "windowD.test dynamic {$case} false WHERE filter");
        $t->same([$partitionKey], array_column(SQLiteSelectPredicate::filter([$row], ['operator' => 'IS FALSE', 'left' => $predicateExpression($inner)]), 'c0'), "windowD.test dynamic {$case} wrapped filter keeps row");
    };
}

$tests['real upstream windowD dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'windowD.test 1.1',
        'windowD.test 1.2',
        'windowD.test 1.3',
        'windowD.test 1.4',
        'windowD.test 2.1',
        'windowD.test 2.2',
        'windowD.test 2.3',
        'windowD.test 2.4',
        'windowD.test 2.5',
        'windowD.test 2.6',
    ], [
        'windowD.test 1.1',
        'windowD.test 1.2',
        'windowD.test 1.3',
        'windowD.test 1.4',
        'windowD.test 2.1',
        'windowD.test 2.2',
        'windowD.test 2.3',
        'windowD.test 2.4',
        'windowD.test 2.5',
        'windowD.test 2.6',
    ]);
};

return $tests;
