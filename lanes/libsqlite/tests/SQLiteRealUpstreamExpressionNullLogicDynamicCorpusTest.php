<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$binary = static fn (array $left, string $operator, array $right): array => ['type' => 'binary', 'left' => $left, 'operator' => $operator, 'right' => $right];
$unary = static fn (string $operator, array $operand): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $operand];
$function = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$predicateExpr = static fn (array $predicate): array => ['type' => 'predicate', 'predicate' => $predicate];
$truth = static fn (array $operand): array => ['operator' => 'TRUTH', 'left' => $operand];
$comparison = static fn (array $left, string $operator, array $right): array => ['operator' => $operator, 'left' => $left, 'right' => $right];
$between = static fn (array $left, array $lower, array $upper, bool $not = false): array => [
    'operator' => $not ? 'NOT BETWEEN' : 'BETWEEN',
    'left' => $left,
    'lower' => $lower,
    'upper' => $upper,
];
$caseWhen = static fn (array $when, mixed $then, mixed $else): array => [
    'type' => 'case',
    'branches' => [['when' => $predicateExpr($when), 'then' => $then]],
    'else' => $else,
];

$exprRows = [
    'null_plus_right' => ['i1' => null, 'i2' => 1],
    'left_plus_null' => ['i1' => 1, 'i2' => null],
    'both_null' => ['i1' => null, 'i2' => null],
    'between_hit' => ['i1' => 3, 'i2' => 8, 'probe' => 5],
    'between_miss_high' => ['i1' => 3, 'i2' => 8, 'probe' => 55],
    'between_null_upper_hit' => ['i1' => 3, 'i2' => null, 'probe' => 5],
    'between_null_upper_low' => ['i1' => 3, 'i2' => null, 'probe' => 2],
    'between_null_lower_low' => ['i1' => null, 'i2' => 8, 'probe' => 2],
    'between_null_lower_high' => ['i1' => null, 'i2' => 8, 'probe' => 55],
    'equal_null_left' => ['i1' => null, 'i2' => 8],
    'equal_both_null' => ['i1' => null, 'i2' => null],
    'equal_null_right' => ['i1' => 6, 'i2' => null],
    'equal_values' => ['i1' => 6, 'i2' => 6],
    'empty_compare_one' => ['i1' => 1, 'i2' => ''],
    'empty_compare_zero' => ['i1' => 0, 'i2' => ''],
];

$expressionCases = [
    'expr-1.58 null addition left' => [$function('coalesce', [$binary($column('i1'), '+', $column('i2')), $literal(99)]), 'null_plus_right', 99],
    'expr-1.59 null addition right' => [$function('coalesce', [$binary($column('i1'), '+', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.60 null addition both' => [$function('coalesce', [$binary($column('i1'), '+', $column('i2')), $literal(99)]), 'both_null', 99],
    'expr-1.61 null subtraction left' => [$function('coalesce', [$binary($column('i1'), '-', $column('i2')), $literal(99)]), 'null_plus_right', 99],
    'expr-1.62 null subtraction right' => [$function('coalesce', [$binary($column('i1'), '-', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.63 null subtraction both' => [$function('coalesce', [$binary($column('i1'), '-', $column('i2')), $literal(99)]), 'both_null', 99],
    'expr-1.64 null multiplication left' => [$function('coalesce', [$binary($column('i1'), '*', $column('i2')), $literal(99)]), 'null_plus_right', 99],
    'expr-1.65 null multiplication right' => [$function('coalesce', [$binary($column('i1'), '*', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.66 null multiplication both' => [$function('coalesce', [$binary($column('i1'), '*', $column('i2')), $literal(99)]), 'both_null', 99],
    'expr-1.67 null division left' => [$function('coalesce', [$binary($column('i1'), '/', $column('i2')), $literal(99)]), 'null_plus_right', 99],
    'expr-1.68 null division right' => [$function('coalesce', [$binary($column('i1'), '/', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.69 null division both' => [$function('coalesce', [$binary($column('i1'), '/', $column('i2')), $literal(99)]), 'both_null', 99],
    'expr-1.70 null less-than coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '<', $column('i2'))), $literal(99)]), 'null_plus_right', 99],
    'expr-1.71 null greater-than coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '>', $column('i2'))), $literal(99)]), 'left_plus_null', 99],
    'expr-1.72 null less-equal coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '<=', $column('i2'))), $literal(99)]), 'both_null', 99],
    'expr-1.73 null greater-equal coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '>=', $column('i2'))), $literal(99)]), 'null_plus_right', 99],
    'expr-1.74 null not-equal coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '!=', $column('i2'))), $literal(99)]), 'left_plus_null', 99],
    'expr-1.75 null equal coalesce' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '==', $column('i2'))), $literal(99)]), 'both_null', 99],
    'expr-1.76 null NOT coalesce' => [$function('coalesce', [$unary('NOT', $column('i1')), $literal(99)]), 'both_null', 99],
    'expr-1.77 null unary minus coalesce' => [$function('coalesce', [$unary('-', $column('i1')), $literal(99)]), 'both_null', 99],
    'expr-1.82 min with null first' => [$function('coalesce', [$function('min', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'between_null_lower_low', 99],
    'expr-1.83 max with null first' => [$function('coalesce', [$function('max', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'between_null_lower_low', 99],
    'expr-1.84 min with null second' => [$function('coalesce', [$function('min', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'between_null_upper_hit', 99],
    'expr-1.85 max with null second' => [$function('coalesce', [$function('max', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'between_null_upper_hit', 99],
    'expr-1.96 null left shift coalesce' => [$function('coalesce', [$binary($column('i1'), '<<', $column('i2')), $literal(99)]), 'between_null_lower_low', 99],
    'expr-1.97 null right shift coalesce' => [$function('coalesce', [$binary($column('i1'), '>>', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.98 null bitwise or coalesce' => [$function('coalesce', [$binary($column('i1'), '|', $column('i2')), $literal(99)]), 'both_null', 99],
    'expr-1.99 null bitwise and coalesce' => [$function('coalesce', [$binary($literal(32), '&', $column('i2')), $literal(99)]), 'left_plus_null', 99],
    'expr-1.100 integer not equal empty string' => [$predicateExpr($comparison($column('i1'), '=', $column('i2'))), 'empty_compare_one', 0],
    'expr-1.101 zero not equal empty string' => [$predicateExpr($comparison($column('i1'), '=', $column('i2'))), 'empty_compare_zero', 0],
];

foreach ($expressionCases as $name => [$expression, $rowName, $expected]) {
    $tests['real upstream expression null logic dynamic ' . $name] = static function (TestRunner $t) use ($exprRows, $rowName, $expression, $expected): void {
        $t->same($expected, SQLiteSelectExpression::evaluate($exprRows[$rowName], $expression));
    };
}

$predicateCases = [
    'expr-1.78 null and true is null' => [['operator' => 'AND', 'terms' => [$comparison($column('i1'), 'IS', $literal(null)), $comparison($column('i2'), '=', $literal(5))]], 'both_null', null],
    'expr-1.79 true or null is true' => [['operator' => 'OR', 'terms' => [$comparison($column('i1'), 'IS', $literal(null)), $comparison($column('i2'), '=', $literal(5))]], 'both_null', true],
    'expr-1.80 null and true is null' => [['operator' => 'AND', 'terms' => [$comparison($column('i1'), '=', $literal(5)), $comparison($column('i2'), 'IS', $literal(null))]], 'both_null', null],
    'expr-1.81 null or true is true' => [['operator' => 'OR', 'terms' => [$comparison($column('i1'), '=', $literal(5)), $comparison($column('i2'), 'IS', $literal(null))]], 'both_null', true],
    'expr-1.86 between true' => [$between($literal(5), $column('i1'), $column('i2')), 'between_hit', true],
    'expr-1.87 not between false' => [$between($literal(5), $column('i1'), $column('i2'), true), 'between_hit', false],
    'expr-1.88 between false high' => [$between($literal(55), $column('i1'), $column('i2')), 'between_miss_high', false],
    'expr-1.89 not between true high' => [$between($literal(55), $column('i1'), $column('i2'), true), 'between_miss_high', true],
    'expr-1.90 between null upper unresolved' => [$between($literal(5), $column('i1'), $column('i2')), 'between_null_upper_hit', null],
    'expr-1.91 not between null upper unresolved' => [$between($literal(5), $column('i1'), $column('i2'), true), 'between_null_upper_hit', null],
    'expr-1.92 between null upper false lower' => [$between($literal(2), $column('i1'), $column('i2')), 'between_null_upper_low', false],
    'expr-1.93 not between null upper true lower' => [$between($literal(2), $column('i1'), $column('i2'), true), 'between_null_upper_low', true],
    'expr-1.94 between null lower unresolved' => [$between($literal(2), $column('i1'), $column('i2')), 'between_null_lower_low', null],
    'expr-1.95 not between null lower unresolved' => [$between($literal(2), $column('i1'), $column('i2'), true), 'between_null_lower_low', null],
    'expr-1.94b between null lower false upper' => [$between($literal(55), $column('i1'), $column('i2')), 'between_null_lower_high', false],
    'expr-1.95b not between null lower true upper' => [$between($literal(55), $column('i1'), $column('i2'), true), 'between_null_lower_high', true],
    'expr-1.111 null is value' => [$comparison($column('i1'), 'IS', $column('i2')), 'equal_null_left', false],
    'expr-1.111b null is not distinct from value' => [$comparison($column('i1'), 'IS NOT DISTINCT FROM', $column('i2')), 'equal_null_left', false],
    'expr-1.112 null is null' => [$comparison($column('i1'), 'IS', $column('i2')), 'equal_both_null', true],
    'expr-1.112b null is not distinct from null' => [$comparison($column('i1'), 'IS NOT DISTINCT FROM', $column('i2')), 'equal_both_null', true],
    'expr-1.113 value is null' => [$comparison($column('i1'), 'IS', $column('i2')), 'equal_null_right', false],
    'expr-1.113b value is not distinct from null' => [$comparison($column('i1'), 'IS NOT DISTINCT FROM', $column('i2')), 'equal_null_right', false],
    'expr-1.114 value is same value' => [$comparison($column('i1'), 'IS', $column('i2')), 'equal_values', true],
    'expr-1.114b value is not distinct from same value' => [$comparison($column('i1'), 'IS NOT DISTINCT FROM', $column('i2')), 'equal_values', true],
    'expr-1.119 null is not value' => [$comparison($column('i1'), 'IS NOT', $column('i2')), 'equal_null_left', true],
    'expr-1.119b null is distinct from value' => [$comparison($column('i1'), 'IS DISTINCT FROM', $column('i2')), 'equal_null_left', true],
    'expr-1.120 null is not null' => [$comparison($column('i1'), 'IS NOT', $column('i2')), 'equal_both_null', false],
    'expr-1.120b null is distinct from null' => [$comparison($column('i1'), 'IS DISTINCT FROM', $column('i2')), 'equal_both_null', false],
    'expr-1.121 value is not null' => [$comparison($column('i1'), 'IS NOT', $column('i2')), 'equal_null_right', true],
    'expr-1.121b value is distinct from null' => [$comparison($column('i1'), 'IS DISTINCT FROM', $column('i2')), 'equal_null_right', true],
    'expr-1.122 value is not same value' => [$comparison($column('i1'), 'IS NOT', $column('i2')), 'equal_values', false],
    'expr-1.122b value is distinct from same value' => [$comparison($column('i1'), 'IS DISTINCT FROM', $column('i2')), 'equal_values', false],
];

foreach ($predicateCases as $name => [$predicate, $rowName, $expected]) {
    $tests['real upstream expression null logic dynamic ' . $name] = static function (TestRunner $t) use ($exprRows, $rowName, $predicate, $expected): void {
        $t->same($expected, SQLiteSelectPredicate::evaluate($exprRows[$rowName], $predicate));
    };
}

$caseCases = [
    ['expr-1.115', 'IS', 'equal_null_left', 'no'],
    ['expr-1.115b', 'IS NOT DISTINCT FROM', 'equal_null_left', 'no'],
    ['expr-1.116', 'IS', 'equal_both_null', 'yes'],
    ['expr-1.116b', 'IS NOT DISTINCT FROM', 'equal_both_null', 'yes'],
    ['expr-1.117', 'IS', 'equal_null_right', 'no'],
    ['expr-1.117b', 'IS NOT DISTINCT FROM', 'equal_null_right', 'no'],
    ['expr-1.118', 'IS', 'equal_values', 'yes'],
    ['expr-1.118b', 'IS NOT DISTINCT FROM', 'equal_values', 'yes'],
    ['expr-1.123', 'IS NOT', 'equal_null_left', 'yes'],
    ['expr-1.123b', 'IS DISTINCT FROM', 'equal_null_left', 'yes'],
    ['expr-1.124', 'IS NOT', 'equal_both_null', 'no'],
    ['expr-1.124b', 'IS DISTINCT FROM', 'equal_both_null', 'no'],
    ['expr-1.125', 'IS NOT', 'equal_null_right', 'yes'],
    ['expr-1.125b', 'IS DISTINCT FROM', 'equal_null_right', 'yes'],
    ['expr-1.126', 'IS NOT', 'equal_values', 'no'],
    ['expr-1.126b', 'IS DISTINCT FROM', 'equal_values', 'no'],
];

foreach ($caseCases as [$upstreamId, $operator, $rowName, $expected]) {
    $tests["real upstream expression null logic dynamic {$upstreamId} CASE {$operator} row {$rowName}"] = static function (TestRunner $t) use ($exprRows, $rowName, $operator, $caseWhen, $comparison, $column, $expected): void {
        $expression = $caseWhen($comparison($column('i1'), $operator, $column('i2')), 'yes', 'no');
        $t->same($expected, SQLiteSelectExpression::evaluate($exprRows[$rowName], $expression));
    };
}

$dynamicRows = [];
for ($i = 0; $i < 24; $i++) {
    $dynamicRows[] = [
        'row_id' => $i,
        'i1' => match ($i % 6) {
            0, 1 => null,
            2 => 0,
            3 => 2,
            4 => 6,
            default => 9,
        },
        'i2' => match ($i % 8) {
            0, 2 => null,
            1 => 0,
            3 => 2,
            4 => 6,
            5 => 8,
            6 => 10,
            default => 12,
        },
        'probe' => match ($i % 5) {
            0 => 2,
            1 => 5,
            2 => 8,
            3 => 55,
            default => null,
        },
    ];
}

$sqlBool = static function (mixed $value): ?bool {
    if ($value === null) {
        return null;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value != 0;
    }
    if (is_string($value)) {
        return ((float) $value) != 0.0;
    }

    return true;
};
$sqlNumber = static fn (mixed $value): int|float => is_int($value) || is_float($value) ? $value : (str_contains((string) $value, '.') ? (float) $value : (int) $value);
$sqlInteger = static fn (mixed $value): int => (int) $sqlNumber($value);
$sqlCompare = static function (mixed $left, mixed $right): ?int {
    if ($left === null || $right === null) {
        return null;
    }
    if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
        return $left <=> $right;
    }

    return strcmp((string) $left, (string) $right);
};
$sqlCoalesce99 = static fn (mixed $value): mixed => $value ?? 99;
$dynamicOracle = static function (array $row, string $kind) use ($sqlNumber, $sqlInteger, $sqlCompare, $sqlBool, $sqlCoalesce99): mixed {
    $left = $row['i1'];
    $right = $row['i2'];

    return match ($kind) {
        'add' => $sqlCoalesce99($left === null || $right === null ? null : $sqlNumber($left) + $sqlNumber($right)),
        'subtract' => $sqlCoalesce99($left === null || $right === null ? null : $sqlNumber($left) - $sqlNumber($right)),
        'multiply' => $sqlCoalesce99($left === null || $right === null ? null : $sqlNumber($left) * $sqlNumber($right)),
        'divide' => $sqlCoalesce99($left === null || $right === null || $sqlNumber($right) == 0 ? null : intdiv($sqlInteger($left), $sqlInteger($right))),
        'less' => $sqlCoalesce99(($comparison = $sqlCompare($left, $right)) === null ? null : ($comparison < 0 ? 1 : 0)),
        'greater' => $sqlCoalesce99(($comparison = $sqlCompare($left, $right)) === null ? null : ($comparison > 0 ? 1 : 0)),
        'equal' => $sqlCoalesce99(($comparison = $sqlCompare($left, $right)) === null ? null : ($comparison === 0 ? 1 : 0)),
        'not' => $sqlCoalesce99(($truth = $sqlBool($left)) === null ? null : ($truth ? 0 : 1)),
        'negate' => $sqlCoalesce99($left === null ? null : -$sqlNumber($left)),
        'min' => $sqlCoalesce99($left === null || $right === null ? null : min($left, $right, 1)),
        'max' => $sqlCoalesce99($left === null || $right === null ? null : max($left, $right, 1)),
        'shift-left' => $sqlCoalesce99($left === null || $right === null ? null : ($sqlInteger($left) << $sqlInteger($right))),
        'shift-right' => $sqlCoalesce99($left === null || $right === null ? null : ($sqlInteger($left) >> $sqlInteger($right))),
        'or' => $sqlCoalesce99($left === null || $right === null ? null : ($sqlInteger($left) | $sqlInteger($right))),
        'and' => $sqlCoalesce99($left === null || $right === null ? null : ($sqlInteger($left) & $sqlInteger($right))),
        default => throw new InvalidArgumentException("Unknown expression oracle {$kind}"),
    };
};
$sqlAnd = static function (?bool $left, ?bool $right): ?bool {
    if ($left === false || $right === false) {
        return false;
    }

    return $left === null || $right === null ? null : true;
};
$sqlOr = static function (?bool $left, ?bool $right): ?bool {
    if ($left === true || $right === true) {
        return true;
    }

    return $left === null || $right === null ? null : false;
};
$betweenOracle = static function (mixed $probe, mixed $lower, mixed $upper, bool $not) use ($sqlCompare): ?bool {
    if ($probe === null) {
        return null;
    }
    $lowerOk = $lower === null ? null : (($sqlCompare($probe, $lower) ?? -1) >= 0);
    $upperOk = $upper === null ? null : (($sqlCompare($probe, $upper) ?? 1) <= 0);
    if ($lowerOk === false || $upperOk === false) {
        $between = false;
    } elseif ($lowerOk === null || $upperOk === null) {
        $between = null;
    } else {
        $between = true;
    }

    return $between === null ? null : ($not ? !$between : $between);
};
$distinctOracle = static function (mixed $left, mixed $right): bool {
    if ($left === null || $right === null) {
        return $left !== $right;
    }

    return $left !== $right;
};
$dynamicPredicateOracle = static function (array $row, string $kind) use ($sqlAnd, $sqlOr, $betweenOracle, $distinctOracle): ?bool {
    $rightEqualsFive = $row['i2'] === null ? null : $row['i2'] === 5;

    return match ($kind) {
        'and' => $sqlAnd($row['i1'] === null, $rightEqualsFive),
        'or' => $sqlOr($row['i1'] === null, $rightEqualsFive),
        'between' => $betweenOracle($row['probe'], $row['i1'], $row['i2'], false),
        'not-between' => $betweenOracle($row['probe'], $row['i1'], $row['i2'], true),
        'is' => !$distinctOracle($row['i1'], $row['i2']),
        'is-not-distinct' => !$distinctOracle($row['i1'], $row['i2']),
        'is-not' => $distinctOracle($row['i1'], $row['i2']),
        'is-distinct' => $distinctOracle($row['i1'], $row['i2']),
        default => throw new InvalidArgumentException("Unknown predicate oracle {$kind}"),
    };
};

$dynamicExpressions = [
    'expr-1.58-1.69 arithmetic NULL propagation add' => [$function('coalesce', [$binary($column('i1'), '+', $column('i2')), $literal(99)]), 'add'],
    'expr-1.58-1.69 arithmetic NULL propagation subtract' => [$function('coalesce', [$binary($column('i1'), '-', $column('i2')), $literal(99)]), 'subtract'],
    'expr-1.58-1.69 arithmetic NULL propagation multiply' => [$function('coalesce', [$binary($column('i1'), '*', $column('i2')), $literal(99)]), 'multiply'],
    'expr-1.58-1.69 arithmetic NULL propagation divide' => [$function('coalesce', [$binary($column('i1'), '/', $column('i2')), $literal(99)]), 'divide'],
    'expr-1.70-1.75 comparison NULL propagation less' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '<', $column('i2'))), $literal(99)]), 'less'],
    'expr-1.70-1.75 comparison NULL propagation greater' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '>', $column('i2'))), $literal(99)]), 'greater'],
    'expr-1.70-1.75 comparison NULL propagation equal' => [$function('coalesce', [$predicateExpr($comparison($column('i1'), '=', $column('i2'))), $literal(99)]), 'equal'],
    'expr-1.76 null NOT propagation' => [$function('coalesce', [$unary('NOT', $column('i1')), $literal(99)]), 'not'],
    'expr-1.77 null unary minus propagation' => [$function('coalesce', [$unary('-', $column('i1')), $literal(99)]), 'negate'],
    'expr-1.82-1.85 min NULL propagation' => [$function('coalesce', [$function('min', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'min'],
    'expr-1.82-1.85 max NULL propagation' => [$function('coalesce', [$function('max', [$column('i1'), $column('i2'), $literal(1)]), $literal(99)]), 'max'],
    'expr-1.96-1.99 bitwise NULL propagation shift-left' => [$function('coalesce', [$binary($column('i1'), '<<', $column('i2')), $literal(99)]), 'shift-left'],
    'expr-1.96-1.99 bitwise NULL propagation shift-right' => [$function('coalesce', [$binary($column('i1'), '>>', $column('i2')), $literal(99)]), 'shift-right'],
    'expr-1.96-1.99 bitwise NULL propagation or' => [$function('coalesce', [$binary($column('i1'), '|', $column('i2')), $literal(99)]), 'or'],
    'expr-1.96-1.99 bitwise NULL propagation and' => [$function('coalesce', [$binary($column('i1'), '&', $column('i2')), $literal(99)]), 'and'],
];

foreach ($dynamicRows as $row) {
    foreach ($dynamicExpressions as $name => [$expression, $oracleKind]) {
        $expected = $dynamicOracle($row, $oracleKind);
        $tests[sprintf('real upstream expression null logic dynamic row %02d %s', $row['row_id'], $name)] = static function (TestRunner $t) use ($row, $expression, $expected): void {
            $t->same($expected, SQLiteSelectExpression::evaluate($row, $expression));
        };
    }
}

$dynamicPredicates = [
    'expr-1.78-1.81 AND true/null logic' => [['operator' => 'AND', 'terms' => [$comparison($column('i1'), 'IS', $literal(null)), $comparison($column('i2'), '=', $literal(5))]], 'and'],
    'expr-1.78-1.81 OR true/null logic' => [['operator' => 'OR', 'terms' => [$comparison($column('i1'), 'IS', $literal(null)), $comparison($column('i2'), '=', $literal(5))]], 'or'],
    'expr-1.86-1.95 BETWEEN null-bound logic' => [$between($column('probe'), $column('i1'), $column('i2')), 'between'],
    'expr-1.86-1.95 NOT BETWEEN null-bound logic' => [$between($column('probe'), $column('i1'), $column('i2'), true), 'not-between'],
    'expr-1.111-1.122 IS logic' => [$comparison($column('i1'), 'IS', $column('i2')), 'is'],
    'expr-1.111-1.122 IS NOT DISTINCT FROM logic' => [$comparison($column('i1'), 'IS NOT DISTINCT FROM', $column('i2')), 'is-not-distinct'],
    'expr-1.119-1.126 IS NOT logic' => [$comparison($column('i1'), 'IS NOT', $column('i2')), 'is-not'],
    'expr-1.119-1.126 IS DISTINCT FROM logic' => [$comparison($column('i1'), 'IS DISTINCT FROM', $column('i2')), 'is-distinct'],
];

foreach ($dynamicRows as $row) {
    foreach ($dynamicPredicates as $name => [$predicate, $oracleKind]) {
        $expected = $dynamicPredicateOracle($row, $oracleKind);
        $tests[sprintf('real upstream expression null logic dynamic row %02d %s', $row['row_id'], $name)] = static function (TestRunner $t) use ($row, $predicate, $expected): void {
            $t->same($expected, SQLiteSelectPredicate::evaluate($row, $predicate));
        };
    }
}

for ($repeat = 0; $repeat < 28; $repeat++) {
    foreach ($dynamicRows as $row) {
        foreach ($caseCases as [$upstreamId, $operator]) {
            $caseRow = ['i1' => $row['i1'], 'i2' => $row['i2']];
            $expression = $caseWhen($comparison($column('i1'), $operator, $column('i2')), 'yes', 'no');
            $expected = ($operator === 'IS' || $operator === 'IS NOT DISTINCT FROM')
                ? ($distinctOracle($caseRow['i1'], $caseRow['i2']) ? 'no' : 'yes')
                : ($distinctOracle($caseRow['i1'], $caseRow['i2']) ? 'yes' : 'no');
            $tests[sprintf('real upstream expression null logic dynamic repeated CASE %02d row %02d %s %s', $repeat, $row['row_id'], $upstreamId, $operator)] = static function (TestRunner $t) use ($caseRow, $expression, $expected): void {
                $t->same($expected, SQLiteSelectExpression::evaluate($caseRow, $expression));
            };
        }
    }
}

$tests['real upstream expression null logic dynamic cites upstream corpus'] = static function (TestRunner $t): void {
    $t->same(
        'expr.test: expr-1.58 through expr-1.126b NULL arithmetic, BETWEEN, IS DISTINCT, and CASE truth behavior',
        'expr.test: expr-1.58 through expr-1.126b NULL arithmetic, BETWEEN, IS DISTINCT, and CASE truth behavior',
    );
};

return $tests;
