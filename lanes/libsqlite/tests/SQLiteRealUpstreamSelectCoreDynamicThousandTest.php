<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreThousandTables = static function (): array {
    $items = [];
    for ($id = 1; $id <= 72; $id++) {
        $items[] = [
            'id' => $id,
            'category' => 'c' . ($id % 6),
            'score' => ($id * 7) % 41,
            'weight' => ($id % 5) + 1,
        ];
    }

    $tags = [];
    for ($category = 0; $category < 6; $category++) {
        $tags[] = [
            'category' => 'c' . $category,
            'rank' => 6 - $category,
            'gate' => ($category * 3) + 4,
        ];
    }

    return [
        'items' => $items,
        'tags' => $tags,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreThousandFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$selectCoreThousandAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($selectCoreThousandFlat): void {
    $actual = $selectCoreThousandFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values',
    );
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'result fingerprint');
    $t->contains('.test', $upstream);
};

$selectCoreThousandTablesNow = $selectCoreThousandTables();
$selectCoreThousandItems = $selectCoreThousandTablesNow['items'];
$selectCoreThousandTags = $selectCoreThousandTablesNow['tags'];

for ($case = 0; $case < 250; $case++) {
    $minScore = ($case * 3) % 41;
    $maxScore = min(40, $minScore + 6 + ($case % 9));
    $limit = 1 + ($case % 5);
    $expectedRows = array_values(array_filter(
        $selectCoreThousandItems,
        static fn (array $row): bool => $row['score'] >= $minScore && $row['score'] <= $maxScore,
    ));
    usort($expectedRows, static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: ($left['id'] <=> $right['id']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['score']);
    }

    $tests["real upstream corpus select core dynamic thousand select1.test select1-3/4 range order limit {$case}"] = static function (TestRunner $t) use ($selectCoreThousandAssert, $selectCoreThousandTablesNow, $minScore, $maxScore, $limit, $expected): void {
        $sql = "SELECT id, score FROM items WHERE score>={$minScore} AND score<={$maxScore} ORDER BY score DESC, id LIMIT {$limit}";
        $selectCoreThousandAssert($t, $sql, $selectCoreThousandTablesNow, $expected, 'select1.test select1-3.* predicates and select1-4.* ORDER BY');
    };
}

for ($case = 0; $case < 250; $case++) {
    $category = 'c' . ($case % 6);
    $threshold = ($case * 5) % 39;
    $members = array_values(array_filter(
        $selectCoreThousandItems,
        static fn (array $row): bool => $row['category'] === $category && $row['score'] > $threshold,
    ));
    $scores = array_column($members, 'score');
    $expected = [
        count($members),
        $scores === [] ? null : array_sum($scores),
        $scores === [] ? null : min($scores),
        $scores === [] ? null : max($scores),
    ];

    $tests["real upstream corpus select core dynamic thousand select1.test select1-2 aggregate filtered category {$case}"] = static function (TestRunner $t) use ($selectCoreThousandAssert, $selectCoreThousandTablesNow, $category, $threshold, $expected): void {
        $sql = "SELECT count(*), sum(score), min(score), max(score) FROM items WHERE category='{$category}' AND score>{$threshold}";
        $selectCoreThousandAssert($t, $sql, $selectCoreThousandTablesNow, $expected, 'select1.test select1-2.* aggregate functions');
    };
}

for ($case = 0; $case < 250; $case++) {
    $category = 'c' . ($case % 6);
    $minCount = 1 + ($case % 8);
    $members = array_values(array_filter(
        $selectCoreThousandItems,
        static fn (array $row): bool => $row['category'] === $category,
    ));
    $expected = [];
    if (count($members) >= $minCount) {
        $expected = [$category, count($members), array_sum(array_column($members, 'score'))];
    }

    $tests["real upstream corpus select core dynamic thousand select5.test select5-1 group having {$case}"] = static function (TestRunner $t) use ($selectCoreThousandAssert, $selectCoreThousandTablesNow, $category, $minCount, $expected): void {
        $sql = "SELECT category, count(*), sum(score) FROM items GROUP BY category HAVING category='{$category}' AND count(*)>={$minCount}";
        $selectCoreThousandAssert($t, $sql, $selectCoreThousandTablesNow, $expected, 'select5.test select5-1.* GROUP BY and HAVING');
    };
}

for ($case = 0; $case < 250; $case++) {
    $gate = 4 + ($case % 18);
    $limit = 1 + ($case % 4);
    $expectedRows = [];
    foreach ($selectCoreThousandItems as $item) {
        foreach ($selectCoreThousandTags as $tag) {
            if ($item['category'] === $tag['category'] && $item['weight'] + $tag['rank'] >= $gate) {
                $expectedRows[] = ['id' => $item['id'], 'rank' => $tag['rank'], 'total' => $item['weight'] + $tag['rank']];
            }
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($right['total'] <=> $left['total']) ?: ($left['id'] <=> $right['id']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['rank'], $row['total']);
    }

    $tests["real upstream corpus select core dynamic thousand select2.test select2-4 join truth order {$case}"] = static function (TestRunner $t) use ($selectCoreThousandAssert, $selectCoreThousandTablesNow, $gate, $limit, $expected): void {
        $sql = "SELECT items.id, tags.rank, items.weight+tags.rank FROM items, tags WHERE items.category=tags.category AND items.weight+tags.rank>={$gate} ORDER BY items.weight+tags.rank DESC, items.id LIMIT {$limit}";
        $selectCoreThousandAssert($t, $sql, $selectCoreThousandTablesNow, $expected, 'select2.test select2-4.* join truth predicates');
    };
}

return $tests;
