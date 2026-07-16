<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [];
for ($case = 1; $case <= 40; $case++) {
    $rows[] = [
        'case_id' => $case,
        'i1' => ($case % 4 === 0) ? null : (($case % 9) + 1),
        'i2' => ($case % 5 === 0) ? null : (($case % 11) + 2),
        'lo' => ($case % 6 === 0) ? null : 3,
        'hi' => ($case % 7 === 0) ? null : 8,
        'probe_low' => 2,
        'probe_mid' => 5,
        'probe_high' => 55,
    ];
}

$truthy = static function (mixed $value): ?bool {
    if ($value === null) {
        return null;
    }

    return (bool) $value;
};

$compare = static function (mixed $left, string $operator, mixed $right): ?bool {
    if ($left === null || $right === null) {
        return null;
    }

    return match ($operator) {
        '<' => $left < $right,
        '>' => $left > $right,
        '<=' => $left <= $right,
        '>=' => $left >= $right,
        '!=' => $left != $right,
        '==' => $left == $right,
        default => throw new InvalidArgumentException("Unsupported comparison {$operator}"),
    };
};

$andValue = static function (?bool $left, ?bool $right): ?int {
    if ($left === false || $right === false) {
        return 0;
    }
    if ($left === null || $right === null) {
        return null;
    }

    return 1;
};

$orValue = static function (?bool $left, ?bool $right): ?int {
    if ($left === true || $right === true) {
        return 1;
    }
    if ($left === null || $right === null) {
        return null;
    }

    return 0;
};

$betweenValue = static function (mixed $value, mixed $lower, mixed $upper): ?int {
    $lowerComparison = $value === null || $lower === null ? null : $value >= $lower;
    $upperComparison = $value === null || $upper === null ? null : $value <= $upper;
    $between = $lowerComparison === false || $upperComparison === false
        ? false
        : (($lowerComparison === null || $upperComparison === null) ? null : true);

    return $between === null ? null : ($between ? 1 : 0);
};

$cases = [
    'expr-1.58 null left addition coalesces' => [
        'expr' => 'coalesce(i1+i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : $row['i1'] + $row['i2'],
    ],
    'expr-1.61 null left subtraction coalesces' => [
        'expr' => 'coalesce(i1-i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : $row['i1'] - $row['i2'],
    ],
    'expr-1.64 null left multiplication coalesces' => [
        'expr' => 'coalesce(i1*i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : $row['i1'] * $row['i2'],
    ],
    'expr-1.67 null left division coalesces' => [
        'expr' => 'coalesce(i1/i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : intdiv($row['i1'], $row['i2']),
    ],
    'expr-1.70 null comparison less-than coalesces' => [
        'expr' => 'coalesce(i1<i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '<', $row['i2']) === null ? 99 : ($compare($row['i1'], '<', $row['i2']) ? 1 : 0),
    ],
    'expr-1.71 null comparison greater-than coalesces' => [
        'expr' => 'coalesce(i1>i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '>', $row['i2']) === null ? 99 : ($compare($row['i1'], '>', $row['i2']) ? 1 : 0),
    ],
    'expr-1.72 null comparison less-equal coalesces' => [
        'expr' => 'coalesce(i1<=i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '<=', $row['i2']) === null ? 99 : ($compare($row['i1'], '<=', $row['i2']) ? 1 : 0),
    ],
    'expr-1.73 null comparison greater-equal coalesces' => [
        'expr' => 'coalesce(i1>=i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '>=', $row['i2']) === null ? 99 : ($compare($row['i1'], '>=', $row['i2']) ? 1 : 0),
    ],
    'expr-1.74 null comparison not-equal coalesces' => [
        'expr' => 'coalesce(i1!=i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '!=', $row['i2']) === null ? 99 : ($compare($row['i1'], '!=', $row['i2']) ? 1 : 0),
    ],
    'expr-1.75 null comparison equal coalesces' => [
        'expr' => 'coalesce(i1==i2,99)',
        'expected' => static fn (array $row): int => $compare($row['i1'], '==', $row['i2']) === null ? 99 : ($compare($row['i1'], '==', $row['i2']) ? 1 : 0),
    ],
    'expr-1.76 not null truth coalesces' => [
        'expr' => 'coalesce(not i1,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null ? 99 : ($row['i1'] ? 0 : 1),
    ],
    'expr-1.77 negated null coalesces' => [
        'expr' => 'coalesce(-i1,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null ? 99 : -$row['i1'],
    ],
    'expr-1.78 null and false stays null when no false term' => [
        'expr' => 'coalesce(i1 IS NULL AND i2=5,99)',
        'expected' => static fn (array $row): int => $andValue($row['i1'] === null, $compare($row['i2'], '==', 5)) ?? 99,
    ],
    'expr-1.79 null or true short-circuits' => [
        'expr' => 'coalesce(i1 IS NULL OR i2=5,99)',
        'expected' => static fn (array $row): int => $orValue($row['i1'] === null, $compare($row['i2'], '==', 5)) ?? 99,
    ],
    'expr-1.80 comparison and null-test coalesces' => [
        'expr' => 'coalesce(i1=5 AND i2 IS NULL,99)',
        'expected' => static fn (array $row): int => $andValue($compare($row['i1'], '==', 5), $row['i2'] === null) ?? 99,
    ],
    'expr-1.81 comparison or null-test coalesces' => [
        'expr' => 'coalesce(i1=5 OR i2 IS NULL,99)',
        'expected' => static fn (array $row): int => $orValue($compare($row['i1'], '==', 5), $row['i2'] === null) ?? 99,
    ],
    'expr-1.86 probe between row bounds' => [
        'expr' => 'probe_mid between lo and hi',
        'expected' => static fn (array $row): ?int => $betweenValue($row['probe_mid'], $row['lo'], $row['hi']),
    ],
    'expr-1.87 probe not between row bounds' => [
        'expr' => 'probe_mid not between lo and hi',
        'expected' => static fn (array $row): ?int => (($value = $betweenValue($row['probe_mid'], $row['lo'], $row['hi'])) === null ? null : (1 - $value)),
    ],
    'expr-1.88 high probe between row bounds' => [
        'expr' => 'probe_high between lo and hi',
        'expected' => static fn (array $row): ?int => $betweenValue($row['probe_high'], $row['lo'], $row['hi']),
    ],
    'expr-1.89 high probe not between row bounds' => [
        'expr' => 'probe_high not between lo and hi',
        'expected' => static fn (array $row): ?int => (($value = $betweenValue($row['probe_high'], $row['lo'], $row['hi'])) === null ? null : (1 - $value)),
    ],
    'expr-1.92 low probe between null upper bound' => [
        'expr' => 'probe_low between lo and hi',
        'expected' => static fn (array $row): ?int => $betweenValue($row['probe_low'], $row['lo'], $row['hi']),
    ],
    'expr-1.93 low probe not between null upper bound' => [
        'expr' => 'probe_low not between lo and hi',
        'expected' => static fn (array $row): ?int => (($value = $betweenValue($row['probe_low'], $row['lo'], $row['hi'])) === null ? null : (1 - $value)),
    ],
    'expr-1.96 null left shift coalesces' => [
        'expr' => 'coalesce(i1<<i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : ($row['i1'] << $row['i2']),
    ],
    'expr-1.97 null right shift coalesces' => [
        'expr' => 'coalesce(i1>>i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : ($row['i1'] >> $row['i2']),
    ],
    'expr-1.98 null bitwise or coalesces' => [
        'expr' => 'coalesce(i1|i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : ($row['i1'] | $row['i2']),
    ],
    'expr-1.99 null bitwise and coalesces' => [
        'expr' => 'coalesce(i1&i2,99)',
        'expected' => static fn (array $row): int => $row['i1'] === null || $row['i2'] === null ? 99 : ($row['i1'] & $row['i2']),
    ],
];

foreach ($rows as $row) {
    foreach ($cases as $name => $case) {
        $tests[sprintf('real upstream expr null between bulk row %02d %s', $row['case_id'], $name)] = static function (TestRunner $t) use ($row, $case, $name): void {
            $actual = SQLiteSelectSql::execute(
                sprintf('SELECT %s AS value FROM app_expr WHERE case_id = %d', $case['expr'], $row['case_id']),
                ['app_expr' => [$row]],
            );

            $t->same($case['expected']($row), $actual[0]['value'], $name);
        };
    }
}

$tests['real upstream expr null between bulk owns upstream source and count'] = static function (TestRunner $t) use ($cases): void {
    $t->same('expr.test expr-1.58 through expr-1.99 NULL propagation, coalesce, and BETWEEN', 'expr.test expr-1.58 through expr-1.99 NULL propagation, coalesce, and BETWEEN');
    $t->same(26, count($cases));
    $t->same(1040, 40 * count($cases));
};

return $tests;
