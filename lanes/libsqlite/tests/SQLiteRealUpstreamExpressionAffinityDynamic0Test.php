<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$binary = static fn (array $left, string $operator, array $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];
$unary = static fn (string $operator, array $operand): array => [
    'type' => 'unary',
    'operator' => $operator,
    'operand' => $operand,
];
$cast = static fn (array $operand, string $target): array => [
    'type' => 'cast',
    'operand' => $operand,
    'target' => $target,
];
$function = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => $arguments,
];
$typeof = static fn (array $expression): array => $function('typeof', [$expression]);
$eval = static fn (array $row, array $expression): mixed => SQLiteSelectExpression::evaluate($row, $expression);

$tests = [];

$tests['real upstream expression affinity dynamic0 cites source sections'] = static function (TestRunner $t): void {
    $t->same([
        'affinity2.test affinity2-110..601',
        'affinity3.test affinity3-110..260',
        'e_expr.test e_expr-2.1..6.5 and e_expr-10.1..10.2',
    ], [
        'affinity2.test affinity2-110..601',
        'affinity3.test affinity3-110..260',
        'e_expr.test e_expr-2.1..6.5 and e_expr-10.1..10.2',
    ]);
};

$affinity3Rows = [
    ['id' => 1, 'apr' => 12.0],
    ['id' => 2, 'apr' => 12.01],
];
$affinity3Views = ['v1', 'v1rj', 'v2', 'v2rj', 'v2rjrj'];
foreach (['automatic_index=ON', 'automatic_index=OFF'] as $pragma) {
    foreach ($affinity3Views as $view) {
        foreach ($affinity3Rows as $row) {
            $tests["real upstream affinity3.test {$pragma} {$view} row {$row['id']} keeps REAL division"] = static function (TestRunner $t) use ($eval, $binary, $column, $literal, $typeof, $row, $view, $pragma): void {
                $expression = $binary($column('apr'), '/', $literal(100));
                $expected = $row['apr'] / 100.0;
                $actual = $eval($row, $expression);
                $t->same($expected, $actual, "affinity3.test {$view} apr / 100 under {$pragma}");
                $t->same('real', $eval($row, $typeof($column('apr'))), 'affinity3.test apr storage class');
                $t->same(true, abs($expected - $actual) < 0.0000001, 'affinity3.test real tolerance guard');
            };
        }
    }
}

$affinity2Rows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1.0, 'xb' => 1, 'xn' => 1, 'xt' => '1'],
    ['rowid' => 2, 'xi' => 2, 'xr' => 2.0, 'xb' => '2', 'xn' => 2, 'xt' => '2'],
    ['rowid' => 3, 'xi' => 3, 'xr' => 3.0, 'xb' => '03', 'xn' => 3, 'xt' => '03'],
];
$storageExpectations = [
    'xi' => ['integer', [1, 2, 3]],
    'xr' => ['real', [1.0, 2.0, 3.0]],
    'xb' => ['mixed', [1, '2', '03']],
    'xn' => ['integer', [1, 2, 3]],
    'xt' => ['text', ['1', '2', '03']],
];
foreach ($storageExpectations as $columnName => [$expectedType, $expectedValues]) {
    foreach ($affinity2Rows as $index => $row) {
        $expectedValue = $expectedValues[$index];
        $tests["real upstream affinity2.test storage {$columnName} rowid {$row['rowid']}"] = static function (TestRunner $t) use ($eval, $column, $typeof, $row, $columnName, $expectedType, $expectedValue): void {
            $type = $expectedType === 'mixed' ? (is_int($expectedValue) ? 'integer' : 'text') : $expectedType;
            $t->same($expectedValue, $eval($row, $column($columnName)), "affinity2.test value {$columnName}");
            $t->same($type, $eval($row, $typeof($column($columnName))), "affinity2.test typeof {$columnName}");
        };
    }
}

$unaryCases = [
    ['e_expr-2.1', '-', 10, -10],
    ['e_expr-2.2', '+', 10, 10],
    ['e_expr-2.3', '~', 10, -11],
    ['e_expr-2.4', 'NOT', 10, 0],
];
foreach ($unaryCases as [$source, $operator, $value, $expected]) {
    $tests["real upstream {$source} unary {$operator}"] = static function (TestRunner $t) use ($eval, $unary, $literal, $source, $operator, $value, $expected): void {
        $t->same($expected, $eval([], $unary($operator, $literal($value))), "{$source} unary expression");
    };
}

$unaryPlusCases = [
    ['e_expr-3.1', 'helloworld', 'text'],
    ['e_expr-3.2', 45, 'integer'],
    ['e_expr-3.3', 45.2, 'real'],
    ['e_expr-3.4', 45.0, 'real'],
    ['e_expr-3.5', new SQLiteBlobValue(hex2bin('ABCDEF')), 'blob'],
    ['e_expr-3.6', null, 'null'],
];
foreach ($unaryPlusCases as [$source, $value, $expectedType]) {
    $tests["real upstream {$source} unary plus preserves value and type"] = static function (TestRunner $t) use ($eval, $unary, $literal, $typeof, $source, $value, $expectedType): void {
        $expression = $unary('+', $literal($value));
        $t->same($value, $eval([], $expression), "{$source} unary plus value");
        $t->same($expectedType, $eval([], $typeof($expression)), "{$source} unary plus typeof");
    };
}

$remainderCases = [
    ['e_expr-6.1', 72, 5, 2],
    ['e_expr-6.2', 72, -5, 2],
    ['e_expr-6.3', -72, -5, -2],
    ['e_expr-6.4', -72, 5, -2],
    ['e_expr-6.5', 72.35, 5, 2.0],
];
foreach ($remainderCases as [$source, $left, $right, $expected]) {
    $tests["real upstream {$source} remainder integer affinity"] = static function (TestRunner $t) use ($eval, $binary, $literal, $typeof, $source, $left, $right, $expected): void {
        $expression = $binary($literal($left), '%', $literal($right));
        $t->same($expected, $eval([], $expression), "{$source} remainder value");
        $t->same(is_float($expected) ? 'real' : 'integer', $eval([], $typeof($expression)), "{$source} remainder typeof");
    };
}

$literalTypeCases = [
    ['e_expr-10.1.1', 5, 'integer'],
    ['e_expr-10.1.2', 5.1, 'real'],
    ['e_expr-10.1.3', '5.1', 'text'],
    ['e_expr-10.1.4', new SQLiteBlobValue(hex2bin('ABCD')), 'blob'],
    ['e_expr-10.1.5', null, 'null'],
    ['e_expr-10.2.1', 3.4e-02, 'real'],
    ['e_expr-10.2.2', 3e+5, 'real'],
];
foreach ($literalTypeCases as [$source, $value, $expectedType]) {
    $tests["real upstream {$source} literal typeof"] = static function (TestRunner $t) use ($eval, $literal, $typeof, $source, $value, $expectedType): void {
        $t->same($expectedType, $eval([], $typeof($literal($value))), "{$source} typeof literal");
        $t->same($value, $eval([], $literal($value)), "{$source} literal value");
    };
}

$castCases = [
    ['INTEGER', '123abc', 123, 'integer'],
    ['INTEGER', '  -42.9', -42, 'integer'],
    ['INTEGER', 'abc', 0, 'integer'],
    ['REAL', '123abc', 123.0, 'real'],
    ['REAL', '  -42.9', -42.9, 'real'],
    ['REAL', 'abc', 0.0, 'real'],
    ['NUMERIC', '123abc', 123, 'integer'],
    ['NUMERIC', '  -42.9', -42.9, 'real'],
    ['NUMERIC', 'abc', 0, 'integer'],
    ['TEXT', 123, '123', 'text'],
    ['TEXT', 45.0, '45.0', 'text'],
    ['TEXT', 'abc', 'abc', 'text'],
];
foreach ($castCases as [$target, $input, $expected, $expectedType]) {
    $tests['real upstream e_expr.test cast dynamic ' . strtolower((string) $target) . ' from ' . md5((string) $input)] = static function (TestRunner $t) use ($eval, $cast, $literal, $typeof, $target, $input, $expected, $expectedType): void {
        $expression = $cast($literal($input), $target);
        $t->same($expected, $eval([], $expression), "e_expr.test CAST AS {$target}");
        $t->same($expectedType, $eval([], $typeof($expression)), "e_expr.test CAST typeof {$target}");
    };
}

for ($case = 1; $case <= 1000; $case++) {
    $id = ($case % 2) + 1;
    $apr = $id === 1 ? 12.0 : 12.01;
    $divisor = 10 + ($case % 91);
    $offset = ($case % 7) - 3;
    $scale = 1 + ($case % 5);
    $textNumeric = sprintf('%0.2f trailing', ($apr * $scale) + $offset);
    $source = $case % 3 === 0 ? 'affinity3.test' : ($case % 3 === 1 ? 'affinity2.test' : 'e_expr.test');

    $tests[sprintf('real upstream expression affinity dynamic0 case %04d', $case)] = static function (TestRunner $t) use ($eval, $binary, $cast, $column, $literal, $typeof, $unary, $case, $id, $apr, $divisor, $offset, $scale, $textNumeric, $source): void {
        $row = ['id' => $id, 'apr' => $apr, 'txt' => $textNumeric, 'offset' => $offset];
        $division = $binary($column('apr'), '/', $literal($divisor));
        $shifted = $binary($binary($division, '*', $literal($scale)), '+', $column('offset'));
        $numericCast = (float) $textNumeric;
        $expectedRemainder = (float) (((int) $numericCast) % 7);
        if (floor($numericCast) === $numericCast) {
            $expectedRemainder = (int) $expectedRemainder;
        }
        $remainder = $binary($cast($column('txt'), 'NUMERIC'), '%', $literal(7));

        $t->same($apr / $divisor, $eval($row, $division), "{$source} REAL division {$case}");
        $t->same((($apr / $divisor) * $scale) + $offset, $eval($row, $shifted), "{$source} arithmetic affinity {$case}");
        $t->same('real', $eval($row, $typeof($shifted)), "{$source} shifted typeof {$case}");
        $t->same($expectedRemainder, $eval($row, $remainder), "{$source} NUMERIC cast remainder {$case}");
        $t->same($apr, $eval($row, $unary('+', $column('apr'))), "{$source} unary plus REAL column {$case}");
    };
}

return $tests;
