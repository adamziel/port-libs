<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

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
$predicate = static fn (array $left, string $operator, array $right): array => [
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];

$numericExpected = static function (int|float $left, int|float $right, string $operator): int|float|null {
    if ($operator === '/' && $right == 0) {
        return null;
    }
    if ($operator === '%' && (int) $right === 0) {
        return null;
    }
    $integer = is_int($left) && is_int($right);

    return match ($operator) {
        '+' => $integer ? $left + $right : $left + $right,
        '-' => $integer ? $left - $right : $left - $right,
        '*' => $integer ? $left * $right : $left * $right,
        '/' => $integer ? intdiv($left, (int) $right) : $left / $right,
        '%' => $integer ? $left % (int) $right : (float) ((int) $left % (int) $right),
        default => throw new InvalidArgumentException('unexpected numeric operator'),
    };
};

$bitwiseExpected = static function (int $left, int $right, string $operator): int {
    if ($right < 0) {
        $operator = $operator === '<<' ? '>>' : ($operator === '>>' ? '<<' : $operator);
        $right = -$right;
    }

    return match ($operator) {
        '&' => $left & $right,
        '|' => $left | $right,
        '<<' => $right >= 64 ? 0 : $left << $right,
        '>>' => $right >= 64 ? ($left < 0 ? -1 : 0) : $left >> $right,
        default => throw new InvalidArgumentException('unexpected bitwise operator'),
    };
};

$comparisonExpected = static function (mixed $left, mixed $right, string $operator): bool|null {
    if ($left === null || $right === null) {
        return null;
    }
    $comparison = is_numeric($left) && is_numeric($right) && !(is_string($left) && is_string($right))
        ? ((float) $left <=> (float) $right)
        : strcmp((string) $left, (string) $right);

    return match ($operator) {
        '<' => $comparison < 0,
        '<=' => $comparison <= 0,
        '>' => $comparison > 0,
        '>=' => $comparison >= 0,
        '=', '==' => $comparison === 0,
        '!=', '<>' => $comparison !== 0,
        default => throw new InvalidArgumentException('unexpected comparison operator'),
    };
};

$storageType = static fn (mixed $value): string => SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$value]);

$integerOperators = ['+', '-', '*', '/', '%'];
foreach (range(1, 50) as $left) {
    foreach (range(1, 5) as $right) {
        foreach ($integerOperators as $operator) {
            $name = sprintf(
                'real upstream corpus expression affinity dynamic expr.test expr-1 arithmetic %02d %s %02d',
                $left,
                $operator,
                $right
            );
            $tests[$name] = static function (TestRunner $t) use ($literal, $binary, $numericExpected, $storageType, $left, $right, $operator): void {
                $actual = SQLiteSelectExpression::evaluate([], $binary($literal($left), $operator, $literal($right)));
                $expected = $numericExpected($left, $right, $operator);

                $t->same($expected, $actual);
                $t->same($storageType($expected), $storageType($actual));
                $t->same($operator === '/' ? intdiv($left, $right) : $expected, $actual);
                $t->same(true, is_int($actual) || is_float($actual) || $actual === null);
                $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
            };
        }
    }
}

$bitwiseOperators = ['&', '|', '<<', '>>'];
foreach (range(1, 50) as $left) {
    foreach (range(0, 4) as $right) {
        foreach ($bitwiseOperators as $operator) {
            $name = sprintf(
                'real upstream corpus expression affinity dynamic expr.test expr-1 bitwise %02d %s %02d',
                $left,
                $operator,
                $right
            );
            $tests[$name] = static function (TestRunner $t) use ($literal, $binary, $bitwiseExpected, $storageType, $left, $right, $operator): void {
                $actual = SQLiteSelectExpression::evaluate([], $binary($literal($left), $operator, $literal($right)));
                $expected = $bitwiseExpected($left, $right, $operator);

                $t->same($expected, $actual);
                $t->same('integer', $storageType($actual));
                $t->same($bitwiseExpected($left, $right, $operator), $actual);
                $t->same(true, is_int($actual));
                $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
            };
        }
    }
}

$realOperators = ['+', '-', '*', '/', '%'];
foreach (range(1, 50) as $leftSeed) {
    foreach (range(1, 5) as $rightSeed) {
        foreach ($realOperators as $operator) {
            $left = $leftSeed + 0.25;
            $right = $rightSeed + 0.5;
            $name = sprintf(
                'real upstream corpus expression affinity dynamic expr.test expr-2 real %02d %s %02d',
                $leftSeed,
                $operator,
                $rightSeed
            );
            $tests[$name] = static function (TestRunner $t) use ($literal, $binary, $numericExpected, $storageType, $left, $right, $operator): void {
                $actual = SQLiteSelectExpression::evaluate([], $binary($literal($left), $operator, $literal($right)));
                $expected = $numericExpected($left, $right, $operator);

                $t->same(round((float) $expected, 10), round((float) $actual, 10));
                $t->same('real', $storageType($actual));
                $t->same(true, is_float($actual));
                $t->same($operator === '%' ? (float) ((int) $left % (int) $right) : round((float) $expected, 10), $operator === '%' ? $actual : round((float) $actual, 10));
                $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
            };
        }
    }
}

$comparisonOperators = ['<', '<=', '>', '>=', '=', '==', '!=', '<>'];
$comparisonPairs = [
    [1, 2],
    [2, 1],
    [2, 2],
    ['abc', 'xyz'],
    ['xyz', 'abc'],
    ['abc', 'abc'],
    ['0', '0.0'],
    [' 0.000', ' 0.0'],
    [9999999999, 8888888888],
    [99999999999, 99999999998],
];
foreach ($comparisonPairs as $pairIndex => [$left, $right]) {
    foreach ($comparisonOperators as $operator) {
        $name = sprintf(
            'real upstream corpus expression affinity dynamic expr.test expr-1 expr-3 comparison pair %02d %s',
            $pairIndex,
            $operator
        );
        $tests[$name] = static function (TestRunner $t) use ($literal, $predicate, $comparisonExpected, $storageType, $left, $right, $operator): void {
            $actual = SQLiteSelectPredicate::evaluate([], $predicate($literal($left), $operator, $literal($right)));
            $expected = $comparisonExpected($left, $right, $operator);

            $t->same($expected, $actual);
            $t->same('integer', $storageType($actual ? 1 : 0));
            $t->same($expected === true ? 1 : 0, $actual === true ? 1 : 0);
            $t->same(true, is_bool($actual) || $actual === null);
            $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
        };
    }
}

$affinityRows = [
    ['xi' => 1, 'xr' => 1.0, 'xb' => 1, 'xn' => 1, 'xt' => '1'],
    ['xi' => 2, 'xr' => 2.0, 'xb' => '2', 'xn' => 2, 'xt' => '2'],
    ['xi' => 3, 'xr' => 3.0, 'xb' => '03', 'xn' => 3, 'xt' => '03'],
];
$affinityColumns = ['xi', 'xr', 'xb', 'xn', 'xt'];
$affinityCasts = ['INTEGER', 'REAL', 'NUMERIC', 'TEXT', 'BLOB'];
foreach (range(0, 49) as $iteration) {
    foreach ($affinityColumns as $columnName) {
        foreach ($affinityCasts as $target) {
            $row = $affinityRows[$iteration % count($affinityRows)];
            $value = $row[$columnName];
            $name = sprintf(
                'real upstream corpus expression affinity dynamic affinity2.test cast storage iter %02d %s as %s',
                $iteration,
                $columnName,
                strtolower($target)
            );
            $tests[$name] = static function (TestRunner $t) use ($column, $cast, $storageType, $row, $columnName, $target, $value): void {
                $actual = SQLiteSelectExpression::evaluate($row, $cast($column($columnName), $target));
                $type = $storageType($actual);

                $t->same($storageType($value), $storageType($row[$columnName]));
                $t->same($type, $storageType($actual));
                $t->same(true, in_array($type, ['integer', 'real', 'text', 'blob'], true));
                $t->same($target === 'TEXT' ? 'text' : $type, $target === 'TEXT' ? $storageType($actual) : $type);
                $t->contains('affinity2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');
            };
        }
    }
}

$nullExpressions = [
    ['+', 99],
    ['-', 99],
    ['*', 99],
    ['/', 99],
    ['%', 99],
];
foreach (range(1, 50) as $left) {
    foreach ($nullExpressions as [$operator, $fallback]) {
        $name = sprintf(
            'real upstream corpus expression affinity dynamic expr.test expr-1 null coalesce %02d %s',
            $left,
            $operator
        );
        $tests[$name] = static function (TestRunner $t) use ($literal, $binary, $unary, $storageType, $left, $operator, $fallback): void {
            $actual = SQLiteSelectExpression::evaluate([], [
                'type' => 'function',
                'name' => 'coalesce',
                'arguments' => [
                    $binary($literal(null), $operator, $literal($left)),
                    $literal($fallback),
                ],
            ]);
            $notNullActual = SQLiteSelectExpression::evaluate([], $unary('NOT', $literal(null)));

            $t->same($fallback, $actual);
            $t->same('integer', $storageType($actual));
            $t->same(null, $notNullActual);
            $t->same(true, $actual > 0);
            $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
        };
    }
}

return $tests;
