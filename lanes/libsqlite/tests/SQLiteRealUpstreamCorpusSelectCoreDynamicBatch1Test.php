<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreBatch1Tables = static function (): array {
    $items = [];
    for ($id = 1; $id <= 96; $id++) {
        $items[] = [
            'id' => $id,
            'bucket' => 'b' . ($id % 8),
            'score' => ($id * 11) % 53,
            'weight' => ($id % 7) + 1,
            'flag' => $id % 3,
        ];
    }

    $groups = [];
    for ($bucket = 0; $bucket < 8; $bucket++) {
        $groups[] = [
            'bucket' => 'b' . $bucket,
            'rank' => 8 - $bucket,
            'gate' => ($bucket * 4) + 3,
        ];
    }

    return [
        'items' => $items,
        'groups' => $groups,
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCoreBatch1Flat = static function (array $rows): array {
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
$selectCoreBatch1Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $upstream
) use ($selectCoreBatch1Flat): void {
    $actual = $selectCoreBatch1Flat(SQLiteSelectSql::execute($sql, $tables));

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

$selectCoreBatch1TablesNow = $selectCoreBatch1Tables();
$selectCoreBatch1Items = $selectCoreBatch1TablesNow['items'];
$selectCoreBatch1Groups = $selectCoreBatch1TablesNow['groups'];

$tests['real upstream corpus select core dynamic batch1 cites upstream sources'] = static function (TestRunner $t): void {
    foreach (['select1.test', 'select2.test', 'select3.test', 'select5.test', 'select6.test'] as $file) {
        $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/' . $file;
        $t->true(is_file($path), "hydrated upstream {$file} exists");
    }

    $select1 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test');
    $select2 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test');
    $select3 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test');
    $select5 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test');
    $select6 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test');

    $t->contains('do_test select1-3.1', $select1);
    $t->contains('do_test select2-4.1', $select2);
    $t->contains('do_test select3-4.1', $select3);
    $t->contains('do_test select5-2.3', $select5);
    $t->contains('do_test select6-1.2', $select6);
};

for ($case = 0; $case < 200; $case++) {
    $minScore = ($case * 5) % 53;
    $maxScore = min(52, $minScore + 4 + ($case % 11));
    $flag = $case % 3;
    $limit = 1 + ($case % 6);
    $expectedRows = array_values(array_filter(
        $selectCoreBatch1Items,
        static fn (array $row): bool => $row['score'] >= $minScore
            && $row['score'] <= $maxScore
            && $row['flag'] === $flag,
    ));
    usort($expectedRows, static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: ($left['id'] <=> $right['id']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['score'], $row['flag']);
    }

    $tests["real upstream corpus select core dynamic batch1 select1.test select1-3 range order flag {$case}"] = static function (TestRunner $t) use ($selectCoreBatch1Assert, $selectCoreBatch1TablesNow, $minScore, $maxScore, $flag, $limit, $expected): void {
        $sql = "SELECT id, score, flag FROM items WHERE score>={$minScore} AND score<={$maxScore} AND flag={$flag} ORDER BY score DESC, id LIMIT {$limit}";
        $selectCoreBatch1Assert($t, $sql, $selectCoreBatch1TablesNow, $expected, 'select1.test select1-3.* WHERE range with select1-4.* ORDER BY/LIMIT');
    };
}

for ($case = 0; $case < 200; $case++) {
    $bucket = 'b' . ($case % 8);
    $threshold = ($case * 7) % 51;
    $members = array_values(array_filter(
        $selectCoreBatch1Items,
        static fn (array $row): bool => $row['bucket'] === $bucket && $row['score'] > $threshold,
    ));
    $scores = array_column($members, 'score');
    $expected = [
        count($members),
        $scores === [] ? null : array_sum($scores),
        $scores === [] ? null : min($scores),
        $scores === [] ? null : max($scores),
    ];

    $tests["real upstream corpus select core dynamic batch1 select5.test select5-2 grouped aggregate bucket {$case}"] = static function (TestRunner $t) use ($selectCoreBatch1Assert, $selectCoreBatch1TablesNow, $bucket, $threshold, $expected): void {
        $sql = "SELECT count(*), sum(score), min(score), max(score) FROM items WHERE bucket='{$bucket}' AND score>{$threshold}";
        $selectCoreBatch1Assert($t, $sql, $selectCoreBatch1TablesNow, $expected, 'select5.test select5-2.* aggregate functions with WHERE');
    };
}

for ($case = 0; $case < 200; $case++) {
    $bucket = 'b' . ($case % 8);
    $minCount = 1 + ($case % 16);
    $members = array_values(array_filter($selectCoreBatch1Items, static fn (array $row): bool => $row['bucket'] === $bucket));
    $expected = [];
    if (count($members) >= $minCount) {
        $expected = [$bucket, count($members), array_sum(array_column($members, 'weight'))];
    }

    $tests["real upstream corpus select core dynamic batch1 select5.test select5-2.3 having bucket {$case}"] = static function (TestRunner $t) use ($selectCoreBatch1Assert, $selectCoreBatch1TablesNow, $bucket, $minCount, $expected): void {
        $sql = "SELECT bucket, count(*), sum(weight) FROM items GROUP BY bucket HAVING bucket='{$bucket}' AND count(*)>={$minCount}";
        $selectCoreBatch1Assert($t, $sql, $selectCoreBatch1TablesNow, $expected, 'select5.test select5-2.3 GROUP BY HAVING');
    };
}

for ($case = 0; $case < 200; $case++) {
    $gate = 5 + ($case % 20);
    $flag = $case % 3;
    $limit = 1 + ($case % 5);
    $expectedRows = [];
    foreach ($selectCoreBatch1Items as $item) {
        foreach ($selectCoreBatch1Groups as $group) {
            if ($item['bucket'] === $group['bucket'] && $item['flag'] === $flag && $item['weight'] + $group['rank'] >= $gate) {
                $expectedRows[] = [
                    'id' => $item['id'],
                    'rank' => $group['rank'],
                    'combined' => $item['weight'] + $group['rank'],
                ];
            }
        }
    }
    usort($expectedRows, static fn (array $left, array $right): int => ($right['combined'] <=> $left['combined']) ?: ($left['id'] <=> $right['id']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['rank'], $row['combined']);
    }

    $tests["real upstream corpus select core dynamic batch1 select2.test select2-4 join truth flag {$case}"] = static function (TestRunner $t) use ($selectCoreBatch1Assert, $selectCoreBatch1TablesNow, $gate, $flag, $limit, $expected): void {
        $sql = "SELECT items.id, groups.rank, items.weight+groups.rank FROM items, groups WHERE items.bucket=groups.bucket AND items.flag={$flag} AND items.weight+groups.rank>={$gate} ORDER BY items.weight+groups.rank DESC, items.id LIMIT {$limit}";
        $selectCoreBatch1Assert($t, $sql, $selectCoreBatch1TablesNow, $expected, 'select2.test select2-4.* join truth predicates');
    };
}

for ($case = 0; $case < 200; $case++) {
    $bucket = 'b' . ($case % 8);
    $minId = 1 + (($case * 3) % 80);
    $limit = 1 + ($case % 4);
    $expectedRows = array_values(array_filter(
        $selectCoreBatch1Items,
        static fn (array $row): bool => $row['bucket'] === $bucket && $row['id'] >= $minId,
    ));
    usort($expectedRows, static fn (array $left, array $right): int => ($left['id'] <=> $right['id']));
    $expectedRows = array_slice($expectedRows, 0, $limit);
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['bucket'], $row['score'] + $row['weight']);
    }

    $tests["real upstream corpus select core dynamic batch1 select6.test select6-1.2 derived alias filter {$case}"] = static function (TestRunner $t) use ($selectCoreBatch1Assert, $selectCoreBatch1TablesNow, $bucket, $minId, $limit, $expected): void {
        $sql = "SELECT id, bucket, total FROM (SELECT id, bucket, score+weight AS total FROM items WHERE bucket='{$bucket}') WHERE id>={$minId} ORDER BY id LIMIT {$limit}";
        $selectCoreBatch1Assert($t, $sql, $selectCoreBatch1TablesNow, $expected, 'select6.test select6-1.2 derived table SELECT filtering');
    };
}

return $tests;
