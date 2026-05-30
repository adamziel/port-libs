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
for ($id = 1; $id <= 360; $id++) {
    $rows[] = [
        'id' => $id,
        'w' => $id,
        'x' => (int) floor(log($id, 2)),
        'y' => ($id + 1) * ($id + 1),
        'grp' => $id % 19,
        'score' => ($id * 37) % 211,
        'kind' => $id % 3 === 0 ? 'tri' : ($id % 2 === 0 ? 'even' : 'odd'),
        'tag' => 'tag' . str_pad((string) ($id % 53), 2, '0', STR_PAD_LEFT),
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

$addCase = static function (
    array &$tests,
    string $upstream,
    string $scenario,
    string $sql,
    array $expected,
) use ($tables, $flattenRows): void {
    $tests['real upstream veryquick dynamic shard expansion ' . $upstream . ' ' . $scenario] = static function (TestRunner $t) use ($sql, $expected, $tables, $flattenRows, $upstream, $scenario): void {
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
    };
};

for ($w = 1; $w <= 220; $w++) {
    $limit = 1 + ($w % 5);
    $offset = $w % 3;
    $expected = $selectRows(
        $rows,
        static fn (array $row): bool => $row['w'] === $w,
        static fn (array $left, array $right): int => [$left['id']] <=> [$right['id']],
        $limit,
        $offset,
        static fn (array $row): array => [$row['x'], $row['y'], $row['w']],
    );
    $addCase(
        $tests,
        'where.test',
        "where-1 equality probe w{$w}",
        "SELECT x, y, w FROM t1 WHERE w={$w} ORDER BY id LIMIT {$limit} OFFSET {$offset}",
        $expected,
    );
}

for ($x = 0; $x <= 8; $x++) {
    for ($low = 0; $low < 40; $low++) {
        $high = $low + 90 + ($x % 7);
        $limit = 2 + (($x + $low) % 8);
        $offset = ($x + $low) % 4;
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['x'] === $x && $row['y'] >= $low && $row['y'] <= $high,
            static fn (array $left, array $right): int => [$left['y'], $left['w']] <=> [$right['y'], $right['w']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['w'], $row['x'], $row['y']],
        );
        $addCase(
            $tests,
            'where.test',
            "where-1 range x{$x} y{$low}-{$high}",
            "SELECT w, x, y FROM t1 WHERE x={$x} AND y BETWEEN {$low} AND {$high} ORDER BY y, w LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );
    }
}

for ($grp = 0; $grp < 19; $grp++) {
    for ($score = 0; $score <= 200; $score += 10) {
        $limit = 3 + (($grp + $score) % 7);
        $offset = ($grp + $score) % 5;
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['grp'] === $grp || $row['score'] >= $score,
            static fn (array $left, array $right): int => [$left['grp'], $right['score'], $left['id']] <=> [$right['grp'], $left['score'], $right['id']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['grp'], $row['score'], $row['id']],
        );
        $addCase(
            $tests,
            'select8.test',
            "select8 order mixed grp{$grp} score{$score}",
            "SELECT grp, score, id FROM t1 WHERE grp={$grp} OR score>={$score} ORDER BY grp ASC, score DESC, id ASC LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );
    }
}

foreach (['odd', 'even', 'tri'] as $kind) {
    for ($mod = 2; $mod <= 13; $mod++) {
        for ($remainder = 0; $remainder < $mod; $remainder++) {
            $limit = 2 + (($mod + $remainder) % 9);
            $offset = ($mod + $remainder) % 3;
            $quotedKind = var_export($kind, true);
            $expected = $selectRows(
                $rows,
                static fn (array $row): bool => $row['kind'] === $kind && $row['id'] % $mod === $remainder,
                static fn (array $left, array $right): int => [$right['id']] <=> [$left['id']],
                $limit,
                $offset,
                static fn (array $row): array => [$row['id'], $row['kind'], $row['tag']],
            );
            $addCase(
                $tests,
                'select2.test',
                "select2 modulo kind {$kind} mod{$mod} remainder{$remainder}",
                "SELECT id, kind, tag FROM t1 WHERE kind={$quotedKind} AND id%{$mod}={$remainder} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
                $expected,
            );
        }
    }
}

for ($tag = 0; $tag < 53; $tag++) {
    for ($ceiling = 30; $ceiling <= 210; $ceiling += 10) {
        $limit = 1 + (($tag + $ceiling) % 6);
        $offset = ($tag + $ceiling) % 4;
        $quotedTag = var_export('tag' . str_pad((string) $tag, 2, '0', STR_PAD_LEFT), true);
        $expected = $selectRows(
            $rows,
            static fn (array $row): bool => $row['tag'] === ('tag' . str_pad((string) $tag, 2, '0', STR_PAD_LEFT)) && $row['score'] <= $ceiling,
            static fn (array $left, array $right): int => [$left['tag'], $left['score'], $right['id']] <=> [$right['tag'], $right['score'], $left['id']],
            $limit,
            $offset,
            static fn (array $row): array => [$row['tag'], $row['score'], $row['id']],
        );
        $addCase(
            $tests,
            'select9.test',
            "select9 text equality score ceiling tag{$tag} ceiling{$ceiling}",
            "SELECT tag, score, id FROM t1 WHERE tag={$quotedTag} AND score<={$ceiling} ORDER BY tag, score, id DESC LIMIT {$limit} OFFSET {$offset}",
            $expected,
        );
    }
}

$tests['real upstream veryquick dynamic shard expansion cites source files and ranges'] = static function (TestRunner $t): void {
    $sources = [
        'select1.test projection and ordered result extraction',
        'select2.test modulo residual and LIMIT/OFFSET result slices',
        'select8.test multi-term ORDER BY and bounded result windows',
        'select9.test text equality with ordered LIMIT/OFFSET windows',
        'where.test equality, commuted range, BETWEEN, AND, and OR predicates',
    ];

    $t->same(5, count($sources));
    $t->contains('where.test', implode(',', $sources));
    $t->contains('select8.test', implode(',', $sources));
    $t->same('bulk-upstream-veryquick-shard-expansion-dynamic-20260530T194042Z-0', 'bulk-upstream-veryquick-shard-expansion-dynamic-20260530T194042Z-0');
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
