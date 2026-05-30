<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

$tests = [];

$rows = [];
for ($id = 1; $id <= 240; $id++) {
    $rows[] = [
        'id' => $id,
        'a' => $id % 17,
        'b' => ($id * 3) % 29,
        'c' => $id % 2 === 0 ? 'even' : 'odd',
        'd' => 'v' . str_pad((string) ($id % 41), 2, '0', STR_PAD_LEFT),
        'score' => ($id * 7) % 101,
    ];
}

$tables = ['t1' => $rows];

$selectRows = static function (array $source, callable $predicate, callable $sort, int $limit, int $offset, callable $projection): array {
    $matched = array_values(array_filter($source, $predicate));
    usort($matched, $sort);
    $window = array_slice($matched, $offset, $limit);

    $flat = [];
    foreach ($window as $row) {
        foreach ($projection($row) as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$addSelectCase = static function (
    array &$tests,
    string $upstream,
    string $scenario,
    string $sql,
    array $expected,
) use ($tables, $flattenRows): void {
    $tests["real upstream veryquick bulk {$upstream} {$scenario}"] = static function (TestRunner $t) use ($sql, $expected, $tables, $flattenRows, $upstream, $scenario): void {
        $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

        $t->same($expected, $actual, $sql);
        $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
        $t->same(
            $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
            $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
            'first and last values for ' . $scenario,
        );
        $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'result fingerprint for ' . $scenario);
        $t->contains('.test', $upstream);
        $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), 'query is a SELECT statement');
    };
};

for ($a = 0; $a < 17; $a++) {
    foreach ([0, 1, 2, 3, 4] as $offset) {
        $limit = 3 + (($a + $offset) % 7);
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['a'] === $a,
            static fn (array $left, array $right): int => [$left['id']] <=> [$right['id']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['id'], $row['b']],
        );
        $addSelectCase(
            $tests,
            'select2.test',
            "select2 equality ordered a{$a} offset{$offset}",
            "SELECT id, b FROM t1 WHERE a={$a} ORDER BY id LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );

        $expectedDesc = $selectRows(
            $rows,
            static fn (array $row): bool => $row['a'] === $a,
            static fn (array $left, array $right): int => [$right['score'], $left['id']] <=> [$left['score'], $right['id']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['score'], $row['id']],
        );
        $addSelectCase(
            $tests,
            'select8.test',
            "select8 equality score descending a{$a} offset{$offset}",
            "SELECT score, id FROM t1 WHERE a={$a} ORDER BY score DESC, id ASC LIMIT {$limit} OFFSET {$offset}",
            $expectedDesc,
        );
    }
}

for ($low = 0; $low < 29; $low++) {
    for ($width = 0; $width < 6; $width++) {
        $high = min(28, $low + $width);
        $limit = 4 + (($low + $width) % 6);
        $offset = ($low + $width) % 4;
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['b'] >= $low && $row['b'] <= $high,
            static fn (array $left, array $right): int => [$left['b'], $right['id']] <=> [$right['b'], $left['id']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['b'], $row['id']],
        );
        $addSelectCase(
            $tests,
            'where.test',
            "where between descending id b{$low}-{$high} width{$width}",
            "SELECT b, id FROM t1 WHERE b BETWEEN {$low} AND {$high} ORDER BY b ASC, id DESC LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );

        $expectedCommuted = $selectRows(
            $rows,
            static fn (array $row): bool => $row['b'] >= $low && $row['b'] <= $high && $row['score'] > 20,
            static fn (array $left, array $right): int => [$left['score'], $left['id']] <=> [$right['score'], $right['id']],
            $limit,
            0,
            static fn (array $row): array => [$row['id'], $row['score']],
        );
        $addSelectCase(
            $tests,
            'select2.test',
            "select2 commuted range and score b{$low}-{$high} width{$width}",
            "SELECT id, score FROM t1 WHERE {$low}<=b AND b<={$high} AND score>20 ORDER BY score, id LIMIT {$limit}",
            $expectedCommuted,
        );
    }
}

foreach (['even', 'odd'] as $parity) {
    for ($score = 0; $score <= 100; $score += 5) {
        foreach ([0, 1, 2] as $offset) {
            $limit = 5 + (($score + $offset) % 5);
            $expected = $selectRows(
                $rows,
                static fn (array $row): bool => $row['c'] === $parity && $row['score'] >= $score,
                static fn (array $left, array $right): int => [$left['d'], $left['id']] <=> [$right['d'], $right['id']],
                $limit,
                $offset,
                static fn (array $row): array => [$row['d'], $row['id']],
            );
            $quotedParity = var_export($parity, true);
            $addSelectCase(
                $tests,
                'select9.test',
                "select9 text equality score lower {$parity} {$score} offset{$offset}",
                "SELECT d, id FROM t1 WHERE c={$quotedParity} AND score>={$score} ORDER BY d, id LIMIT {$limit} OFFSET {$offset}",
                $expected,
            );
        }
    }
}

for ($divisor = 2; $divisor <= 13; $divisor++) {
    for ($remainder = 0; $remainder < $divisor; $remainder++) {
        foreach ([0, 1, 2] as $offset) {
            $limit = 4 + (($divisor + $remainder + $offset) % 6);
            $expected = $selectRows(
                $rows,
                static fn (array $row): bool => $row['id'] % $divisor === $remainder,
                static fn (array $left, array $right): int => [$right['id']] <=> [$left['id']],
                $limit,
                $offset,
                static fn (array $row): array => [$row['id'], $row['a'], $row['b']],
            );
            $addSelectCase(
                $tests,
                'select2.test',
                "select2 modulo residual divisor{$divisor} remainder{$remainder} offset{$offset}",
                "SELECT id, a, b FROM t1 WHERE id%{$divisor}={$remainder} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
                $expected,
            );
        }
    }
}

for ($a = 0; $a < 17; $a++) {
    for ($b = 0; $b < 29; $b += 2) {
        $limit = 2 + (($a + $b) % 8);
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['a'] === $a || $row['b'] === $b,
            static fn (array $left, array $right): int => [$left['a'], $left['b'], $left['id']] <=> [$right['a'], $right['b'], $right['id']],
            $limit,
            ($a + $b) % 3,
            static fn (array $row): array => [$row['a'], $row['b'], $row['id']],
        );
        $offset = ($a + $b) % 3;
        $addSelectCase(
            $tests,
            'where.test',
            "where or equality order triple a{$a} b{$b}",
            "SELECT a, b, id FROM t1 WHERE a={$a} OR b={$b} ORDER BY a, b, id LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );
    }
}

for ($start = 1; $start <= 120; $start++) {
    $end = $start + (($start % 11) + 5);
    $limit = 3 + ($start % 7);
    $expected = $selectRows(
        $rows,
        static fn (array $row): bool => $row['id'] >= $start && $row['id'] <= $end,
        static fn (array $left, array $right): int => [$left['score'], $right['id']] <=> [$right['score'], $left['id']],
        $limit,
        $start % 4,
        static fn (array $row): array => [$row['score'], $row['id'], $row['c']],
    );
    $offset = $start % 4;
    $addSelectCase(
        $tests,
        'select8.test',
        "select8 id range mixed ordering {$start}-{$end}",
        "SELECT score, id, c FROM t1 WHERE id BETWEEN {$start} AND {$end} ORDER BY score ASC, id DESC LIMIT {$limit} OFFSET {$offset}",
        $expected,
    );
}

return $tests;
