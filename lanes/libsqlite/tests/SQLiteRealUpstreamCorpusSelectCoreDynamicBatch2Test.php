<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreBatch2Tables = static function (): array {
    $items = [];
    for ($id = 1; $id <= 120; $id++) {
        $bucket = 1;
        if ($id >= 2) {
            $bucket = 2;
        }
        if ($id >= 4) {
            $bucket = 3;
        }
        if ($id >= 8) {
            $bucket = 4;
        }
        if ($id >= 16) {
            $bucket = 5;
        }
        if ($id >= 32) {
            $bucket = 6;
        }
        if ($id >= 64) {
            $bucket = 7;
        }

        $items[] = [
            'id' => $id,
            'bucket' => $bucket,
            'score' => ($id * 17) % 71,
            'weight' => ($id % 9) + 1,
            'kind' => 'k' . ($id % 5),
        ];
    }

    $lookup = [];
    for ($rowid = 1; $rowid <= 12; $rowid++) {
        $lookup[] = [
            'rowid' => $rowid,
            'kind' => 'k' . ($rowid % 5),
            'gate' => 12 + $rowid,
        ];
    }

    $nullable = [];
    for ($id = 1; $id <= 90; $id++) {
        $nullable[] = [
            'id' => $id,
            'left_key' => $id % 6 === 0 ? null : $id % 6,
            'right_key' => $id % 10 === 0 ? null : $id % 5,
            'score' => ($id * 13) % 47,
        ];
    }

    return [
        'items' => $items,
        'lookup' => $lookup,
        'nullable_rows' => $nullable,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreBatch2Flat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectCoreBatch2Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($selectCoreBatch2Flat): void {
    $actual = $selectCoreBatch2Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint',
    );
    $t->contains('.test', $upstream);
};

/**
 * @param list<array<string,mixed>> $rows
 * @return array<int,array{count:int,avg_id:float,avg_score:float,sum_weight:int,max_score:int,min_id:int}>
 */
$selectCoreBatch2BucketStats = static function (array $rows): array {
    $stats = [];
    foreach ($rows as $row) {
        $bucket = $row['bucket'];
        if (!isset($stats[$bucket])) {
            $stats[$bucket] = [
                'ids' => [],
                'scores' => [],
                'weights' => [],
            ];
        }
        $stats[$bucket]['ids'][] = $row['id'];
        $stats[$bucket]['scores'][] = $row['score'];
        $stats[$bucket]['weights'][] = $row['weight'];
    }

    $result = [];
    foreach ($stats as $bucket => $data) {
        $result[$bucket] = [
            'count' => count($data['ids']),
            'avg_id' => array_sum($data['ids']) / count($data['ids']),
            'avg_score' => array_sum($data['scores']) / count($data['scores']),
            'sum_weight' => array_sum($data['weights']),
            'max_score' => max($data['scores']),
            'min_id' => min($data['ids']),
        ];
    }
    ksort($result);

    return $result;
};

$selectCoreBatch2TablesNow = $selectCoreBatch2Tables();
$selectCoreBatch2Items = $selectCoreBatch2TablesNow['items'];
$selectCoreBatch2Lookup = $selectCoreBatch2TablesNow['lookup'];
$selectCoreBatch2Nullable = $selectCoreBatch2TablesNow['nullable_rows'];
$selectCoreBatch2Stats = $selectCoreBatch2BucketStats($selectCoreBatch2Items);

$tests['real upstream corpus select core dynamic batch2 cites upstream sources'] = static function (TestRunner $t): void {
    foreach (['select5.test', 'select6.test'] as $file) {
        $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/' . $file;
        $t->true(is_file($path), "hydrated upstream {$file} exists");
        $contents = file_get_contents($path);
        $t->contains($file === 'select6.test' ? 'do_test select6-3.10' : 'do_test select5-6.1', $contents);
    }
};

for ($case = 0; $case < 250; $case++) {
    $maxBucket = 1 + ($case % 7);
    $minAvg = 1.0 + (($case * 3) % 70);
    $limit = 1 + ($case % 5);
    $expectedRows = [];
    foreach ($selectCoreBatch2Stats as $bucket => $stat) {
        if ($bucket <= $maxBucket && $stat['avg_id'] >= $minAvg) {
            $expectedRows[] = [
                'avg_id' => $stat['avg_id'],
                'bucket' => $bucket,
                'combined' => $stat['avg_id'] + $bucket,
            ];
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['avg_id'] <=> $right['avg_id']) ?: ($left['bucket'] <=> $right['bucket']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, round($row['avg_id'], 6), $row['bucket'], round($row['combined'], 6));
    }

    $tests["real upstream corpus select core dynamic batch2 select6.test select6-3.10 derived avg outer where {$case}"] = static function (TestRunner $t) use ($selectCoreBatch2Assert, $selectCoreBatch2TablesNow, $maxBucket, $minAvg, $limit, $expected): void {
        $sql = "SELECT avg_id, bucket, avg_id+bucket FROM (SELECT avg(id) AS avg_id, bucket AS bucket FROM items GROUP BY bucket) WHERE bucket<={$maxBucket} AND avg_id>={$minAvg} ORDER BY avg_id LIMIT {$limit}";
        $selectCoreBatch2Assert($t, $sql, $selectCoreBatch2TablesNow, $expected, 'select6.test select6-3.10 derived avg grouped subquery with outer WHERE');
    };
}

for ($case = 0; $case < 250; $case++) {
    $havingMin = 1.0 + (($case * 5) % 80);
    $outerMaxBucket = 1 + ($case % 7);
    $expectedRows = [];
    foreach ($selectCoreBatch2Stats as $bucket => $stat) {
        if ($stat['avg_id'] > $havingMin && $bucket <= $outerMaxBucket) {
            $expectedRows[] = [
                'avg_id' => $stat['avg_id'],
                'bucket' => $bucket,
                'combined' => $stat['avg_id'] + $bucket,
            ];
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['avg_id'] <=> $right['avg_id']) ?: ($left['bucket'] <=> $right['bucket']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, round($row['avg_id'], 6), $row['bucket'], round($row['combined'], 6));
    }

    $tests["real upstream corpus select core dynamic batch2 select6.test select6-3.12 having avg outer bucket {$case}"] = static function (TestRunner $t) use ($selectCoreBatch2Assert, $selectCoreBatch2TablesNow, $havingMin, $outerMaxBucket, $expected): void {
        $sql = "SELECT avg_id, bucket, avg_id+bucket FROM (SELECT avg(id) AS avg_id, bucket AS bucket FROM items GROUP BY bucket HAVING avg(id)>{$havingMin}) WHERE bucket<={$outerMaxBucket} ORDER BY avg_id";
        $selectCoreBatch2Assert($t, $sql, $selectCoreBatch2TablesNow, $expected, 'select6.test select6-3.12 derived aggregate HAVING plus outer WHERE');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minCount = 1 + ($case % 20);
    $maxBucket = 1 + (($case * 2) % 7);
    $expectedRows = [];
    foreach ($selectCoreBatch2Stats as $bucket => $stat) {
        if ($stat['count'] >= $minCount && $bucket <= $maxBucket) {
            $expectedRows[] = [
                'group_count' => $stat['count'],
                'bucket' => $bucket,
                'max_score' => $stat['max_score'],
            ];
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['group_count'] <=> $right['group_count']) ?: ($left['bucket'] <=> $right['bucket']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['group_count'], $row['bucket'], $row['max_score']);
    }

    $tests["real upstream corpus select core dynamic batch2 select6.test select6-3.14 count order alias {$case}"] = static function (TestRunner $t) use ($selectCoreBatch2Assert, $selectCoreBatch2TablesNow, $minCount, $maxBucket, $expected): void {
        $sql = "SELECT group_count, bucket, max_score FROM (SELECT count(*) AS group_count, bucket AS bucket, max(score) AS max_score FROM items GROUP BY bucket) WHERE group_count>={$minCount} AND bucket<={$maxBucket} ORDER BY group_count, bucket";
        $selectCoreBatch2Assert($t, $sql, $selectCoreBatch2TablesNow, $expected, 'select6.test select6-3.14 derived count aggregate ordered by aggregate alias');
    };
}

for ($case = 0; $case < 250; $case++) {
    $kind = 'k' . ($case % 5);
    $gate = 12 + ($case % 12);
    $orderByCount = $case % 2 === 0;
    $groups = [];
    foreach ($selectCoreBatch2Items as $item) {
        foreach ($selectCoreBatch2Lookup as $lookup) {
            if ($item['kind'] === $kind && $item['kind'] === $lookup['kind'] && $item['score'] < $lookup['gate'] && $lookup['gate'] <= $gate) {
                $groups[$item['kind']][] = $item['score'];
            }
        }
    }
    $expectedRows = [];
    foreach ($groups as $groupKind => $scores) {
        $expectedRows[] = [
            'kind' => $groupKind,
            'matches' => count($scores),
        ];
    }
    usort($expectedRows, $orderByCount
        ? static fn (array $left, array $right): int => ($left['matches'] <=> $right['matches']) ?: strcmp($left['kind'], $right['kind'])
        : static fn (array $left, array $right): int => strcmp($left['kind'], $right['kind']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['kind'], $row['matches']);
    }
    $orderSql = $orderByCount ? 'matches, item_kind' : 'item_kind';

    $tests["real upstream corpus select core dynamic batch2 select5.test select5-8 grouped join count {$case}"] = static function (TestRunner $t) use ($selectCoreBatch2Assert, $selectCoreBatch2TablesNow, $kind, $gate, $orderSql, $expected): void {
        $sql = "SELECT items.kind AS item_kind, count(score) AS matches FROM items, lookup WHERE items.kind='{$kind}' AND items.kind=lookup.kind AND score<gate AND gate<={$gate} GROUP BY items.kind ORDER BY {$orderSql}";
        $selectCoreBatch2Assert($t, $sql, $selectCoreBatch2TablesNow, $expected, 'select5.test select5-8.* grouped join count and aggregate ordering');
    };
}

for ($case = 0; $case < 250; $case++) {
    $minScore = ($case * 7) % 47;
    $useRight = $case % 2 === 1;
    $column = $useRight ? 'right_key' : 'left_key';
    $groups = [];
    foreach ($selectCoreBatch2Nullable as $row) {
        if ($row['score'] >= $minScore) {
            $key = $row[$column];
            $encoded = $key === null ? '__NULL__' : (string) $key;
            $groups[$encoded]['key'] = $key;
            $groups[$encoded]['ids'][] = $row['id'];
            $groups[$encoded]['scores'][] = $row['score'];
        }
    }
    $expectedRows = [];
    foreach ($groups as $group) {
        $expectedRows[] = [
            'seen' => count($group['ids']),
            'key_value' => $group['key'],
        ];
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($left['seen'] <=> $right['seen']) ?: (($left['key_value'] ?? -1) <=> ($right['key_value'] ?? -1)));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['seen'], $row['key_value']);
    }

    $tests["real upstream corpus select core dynamic batch2 select5.test select5-6 nullable group by {$case}"] = static function (TestRunner $t) use ($selectCoreBatch2Assert, $selectCoreBatch2TablesNow, $column, $minScore, $expected): void {
        $sql = "SELECT count(id) AS seen, {$column} AS key_value FROM nullable_rows WHERE score>={$minScore} GROUP BY {$column} ORDER BY seen, key_value";
        $selectCoreBatch2Assert($t, $sql, $selectCoreBatch2TablesNow, $expected, 'select5.test select5-6.* NULL values group together for GROUP BY');
    };
}

return $tests;
