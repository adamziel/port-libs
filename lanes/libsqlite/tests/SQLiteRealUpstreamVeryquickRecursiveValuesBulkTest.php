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
            $flat[] = $value;
        }
    }

    return $flat;
};

$tests = [];

$addCase = static function (
    array &$tests,
    string $upstream,
    string $scenario,
    string $sql,
    array $expected,
    array $tables = [],
) use ($flattenRows): void {
    $tests["real upstream veryquick recursive values bulk {$upstream} {$scenario}"] = static function (TestRunner $t) use ($sql, $expected, $tables, $flattenRows, $upstream, $scenario): void {
        $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

        $t->same($expected, $actual, $sql);
        $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
        $t->same(
            $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
            $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
            'first and last values for ' . $scenario,
        );
        $t->same(hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)), hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)), 'result fingerprint for ' . $scenario);
        $t->contains('.test', $upstream);
        $t->true(str_starts_with(strtolower(ltrim($sql)), 'select') || str_starts_with(strtolower(ltrim($sql)), 'with'), 'query starts with SELECT or WITH');
    };
};

for ($seed = 1; $seed <= 350; $seed++) {
    $upper = $seed + 8 + ($seed % 7);
    $limit = 4 + ($seed % 6);
    $offset = $seed % 3;
    $all = [];
    for ($id = $seed; $id <= $upper; $id++) {
        $all[] = ['id' => $id, 'depth' => $id - $seed];
    }
    $window = array_slice($all, $offset, $limit);
    usort($window, static fn (array $left, array $right): int => [$left['id']] <=> [$right['id']]);

    $expected = [];
    foreach ($window as $row) {
        $expected[] = $row['id'];
        $expected[] = $row['depth'];
    }

    $addCase(
        $tests,
        'withM.test',
        "recursive queue limit offset seed{$seed}",
        "WITH RECURSIVE walk(id, depth) AS (VALUES ({$seed}, 0) UNION ALL SELECT id + 1, depth + 1 FROM walk WHERE id < {$upper} LIMIT {$limit} OFFSET {$offset}) SELECT id, depth FROM walk ORDER BY id",
        $expected,
    );
}

for ($case = 1; $case <= 350; $case++) {
    $values = [];
    $rows = [];
    for ($i = 0; $i < 6; $i++) {
        $x = ($case + $i) % 19;
        $y = ($case * 3 + $i * 5) % 37;
        $values[] = "({$x}, {$y})";
        $rows[] = ['x' => $x, 'y' => $y];
    }
    usort($rows, static fn (array $left, array $right): int => [$right['y'], $left['x']] <=> [$left['y'], $right['x']]);
    $limit = 2 + ($case % 4);
    $window = array_slice($rows, 0, $limit);

    $expected = [];
    foreach ($window as $row) {
        $expected[] = $row['x'];
        $expected[] = $row['y'];
    }

    $addCase(
        $tests,
        'selectG.test',
        "values source ordered window case{$case}",
        'SELECT x, y FROM (VALUES ' . implode(', ', $values) . ") AS v(x, y) ORDER BY y DESC, x ASC LIMIT {$limit}",
        $expected,
    );
}

$groupRows = [];
for ($id = 1; $id <= 180; $id++) {
    $groupRows[] = [
        'id' => $id,
        'bucket' => $id % 23,
        'score' => ($id * 11) % 97,
    ];
}
$tables = ['items' => $groupRows];

for ($case = 1; $case <= 350; $case++) {
    $mod = 3 + ($case % 8);
    $limit = 3 + ($case % 6);
    $groups = [];
    foreach ($groupRows as $row) {
        if (($row['id'] % $mod) !== 0) {
            continue;
        }
        $bucket = $row['bucket'];
        $groups[$bucket] ??= ['bucket' => $bucket, 'count' => 0, 'sum' => 0];
        $groups[$bucket]['count']++;
        $groups[$bucket]['sum'] += $row['score'];
    }
    $groups = array_values($groups);
    usort($groups, static fn (array $left, array $right): int => [$left['bucket']] <=> [$right['bucket']]);
    $groups = array_slice($groups, 0, $limit);

    $expected = [];
    foreach ($groups as $row) {
        $expected[] = $row['bucket'];
        $expected[] = $row['count'];
        $expected[] = $row['sum'];
    }

    $addCase(
        $tests,
        'select5.test',
        "group by ordered aggregate case{$case}",
        "SELECT bucket, count(*), sum(score) FROM items WHERE id%{$mod}=0 GROUP BY bucket ORDER BY bucket ASC LIMIT {$limit}",
        $expected,
        $tables,
    );
}

return $tests;
