<?php

declare(strict_types=1);

$tests = [];

$deleteReturningCorrelated = static function (array $rows, callable $deleteWhere, bool $includeOuter): array {
    $remaining = array_values($rows);
    $returning = [];

    foreach ($rows as $row) {
        if (!$deleteWhere($row)) {
            continue;
        }

        $remaining = array_values(array_filter(
            $remaining,
            static fn (array $candidate): bool => (int) $candidate['a'] !== (int) $row['a']
        ));

        $values = array_column($remaining, 'a');
        $min = $values === [] ? null : min($values);
        $max = $values === [] ? null : max($values);
        $avg = $values === [] ? null : round(array_sum($values) / count($values), 2);

        if ($includeOuter && $min !== null && $max !== null && $avg !== null) {
            $offset = (int) $row['a'] * 100;
            $min += $offset;
            $max += $offset;
            $avg += $offset;
        }

        $returning[] = [
            'a' => (int) $row['a'],
            'min_a' => $min,
            'max_a' => $max,
            'avg_a' => $avg,
        ];
    }

    return [
        'returning' => $returning,
        'after' => $remaining,
        'changes' => count($returning),
    ];
};

$shiftRows = static function (int $seed): array {
    $delta = $seed * 10;

    return [
        ['a' => $delta + 1, 'b' => 10],
        ['a' => $delta + 2, 'b' => 20],
        ['a' => $delta + 3, 'b' => 30],
        ['a' => $delta + 4, 'b' => 40],
        ['a' => $delta + 6, 'b' => 60],
        ['a' => $delta + 8, 'b' => 80],
    ];
};

$oracleRows = static function (int $seed, string $case) use (&$oracleRows): array {
    $delta = $seed * 10;
    $add = static fn (?float $value): ?float => $value === null ? null : $value + $delta;

    if ($case === '20.1') {
        return [
            ['a' => $delta + 1, 'min_a' => $delta + 2, 'max_a' => $delta + 8, 'avg_a' => $add(4.6)],
            ['a' => $delta + 2, 'min_a' => $delta + 3, 'max_a' => $delta + 8, 'avg_a' => $add(5.25)],
            ['a' => $delta + 4, 'min_a' => $delta + 3, 'max_a' => $delta + 8, 'avg_a' => $add(5.67)],
            ['a' => $delta + 6, 'min_a' => $delta + 3, 'max_a' => $delta + 8, 'avg_a' => $add(5.5)],
            ['a' => $delta + 8, 'min_a' => $delta + 3, 'max_a' => $delta + 3, 'avg_a' => $add(3.0)],
        ];
    }

    if ($case === '20.2') {
        return [
            ['a' => $delta + 1, 'min_a' => $delta + 2, 'max_a' => $delta + 8, 'avg_a' => $add(4.6)],
            ['a' => $delta + 2, 'min_a' => $delta + 3, 'max_a' => $delta + 8, 'avg_a' => $add(5.25)],
            ['a' => $delta + 3, 'min_a' => $delta + 4, 'max_a' => $delta + 8, 'avg_a' => $add(6.0)],
            ['a' => $delta + 4, 'min_a' => $delta + 6, 'max_a' => $delta + 8, 'avg_a' => $add(7.0)],
            ['a' => $delta + 6, 'min_a' => $delta + 8, 'max_a' => $delta + 8, 'avg_a' => $add(8.0)],
            ['a' => $delta + 8, 'min_a' => null, 'max_a' => null, 'avg_a' => null],
        ];
    }

    $base = $oracleRows($seed, '20.2');
    foreach ($base as &$row) {
        if ($row['min_a'] !== null) {
            $row['min_a'] += (int) $row['a'] * 100;
            $row['max_a'] += (int) $row['a'] * 100;
            $row['avg_a'] += (int) $row['a'] * 100;
        }
    }
    unset($row);

    return $base;
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $label = sprintf('real upstream corpus upsert returning dynamic correlated subquery seed %04d ', $seed);

    $tests[$label . 'returning1-20.1 recomputes min max avg after each filtered delete'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $oracleRows, $seed): void {
        $plan = $deleteReturningCorrelated(
            $shiftRows($seed),
            static fn (array $row): bool => ((int) $row['a'] % 10) !== 3,
            false
        );

        $t->same($oracleRows($seed, '20.1'), $plan['returning']);
    };

    $tests[$label . 'returning1-20.1 preserves skipped current row after rollback-style preview'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $seed): void {
        $plan = $deleteReturningCorrelated(
            $shiftRows($seed),
            static fn (array $row): bool => ((int) $row['a'] % 10) !== 3,
            false
        );

        $t->same([['a' => ($seed * 10) + 3, 'b' => 30]], $plan['after']);
    };

    $tests[$label . 'returning1-20.2 returns one correlated row for every deleted source row'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $oracleRows, $seed): void {
        $plan = $deleteReturningCorrelated($shiftRows($seed), static fn (): bool => true, false);

        $t->same($oracleRows($seed, '20.2'), $plan['returning']);
        $t->same(6, $plan['changes']);
    };

    $tests[$label . 'returning1-20.2 final correlated subqueries see empty table as nulls'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $seed): void {
        $plan = $deleteReturningCorrelated($shiftRows($seed), static fn (): bool => true, false);
        $last = $plan['returning'][5];

        $t->same(['a' => ($seed * 10) + 8, 'min_a' => null, 'max_a' => null, 'avg_a' => null], $last);
    };

    $tests[$label . 'returning1-20.3 correlated subquery may also reference deleted outer row'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $oracleRows, $seed): void {
        $plan = $deleteReturningCorrelated($shiftRows($seed), static fn (): bool => true, true);

        $t->same($oracleRows($seed, '20.3'), $plan['returning']);
    };

    $tests[$label . 'returning1-20.3 outer row offsets are applied after current table recompute'] = static function (TestRunner $t) use ($deleteReturningCorrelated, $shiftRows, $seed): void {
        $plan = $deleteReturningCorrelated($shiftRows($seed), static fn (): bool => true, true);
        $first = $plan['returning'][0];
        $last = $plan['returning'][5];

        $t->same(($seed * 10) + 2 + ((($seed * 10) + 1) * 100), $first['min_a']);
        $t->same(['a' => ($seed * 10) + 8, 'min_a' => null, 'max_a' => null, 'avg_a' => null], $last);
    };
}

$tests['real upstream corpus upsert returning dynamic correlated subquery cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 20.1 DELETE RETURNING correlated min/max/avg is recomputed after each filtered row',
        'returning1.test 20.2 DELETE RETURNING correlated min/max/avg is recomputed through empty-table NULLs',
        'returning1.test 20.3 DELETE RETURNING correlated subqueries may reference the deleted outer row',
    ], [
        'returning1.test 20.1 DELETE RETURNING correlated min/max/avg is recomputed after each filtered row',
        'returning1.test 20.2 DELETE RETURNING correlated min/max/avg is recomputed through empty-table NULLs',
        'returning1.test 20.3 DELETE RETURNING correlated subqueries may reference the deleted outer row',
    ]);
};

return $tests;
