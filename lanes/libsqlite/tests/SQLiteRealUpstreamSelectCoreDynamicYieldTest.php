<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectYieldFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectYieldAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($selectYieldFlat): void {
    $actual = $selectYieldFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $scenario,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream select4.test select4-15.1 cites coroutine yield source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';

    $t->true(is_file($source), 'hydrated upstream select4.test is available');
    $text = file_get_contents($source);
    $t->contains('do_execsql_test select4-15.1', $text);
    $t->contains('Incorrect answer due to two co-routines using the same registers and expecting', $text);
    $t->contains('those register values to be preserved across a Yield', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $group = 20 + ($seed % 37);
    $leftValue = 300 + ($seed * 2);
    $rightValue = $leftValue + 1 + ($seed % 5);
    $noiseGroup = $group + 1000;
    $noiseValue = 9000 + $seed;
    $leftFirst = $seed % 2 === 0;
    $operator = $seed % 5 === 0 ? 'UNION ALL' : 'UNION';
    $orderDirection = $seed % 7 === 0 ? 'DESC' : 'ASC';
    $selectColumns = $seed % 3 === 0 ? 's0.id, s0.group_id, s0.payload' : 's0.id, s0.group_id, s0.payload';

    $rows = [
        ['id' => 1, 'group_id' => $group, 'payload' => $leftValue],
        ['id' => 2, 'group_id' => $group, 'payload' => $rightValue],
        ['id' => 3, 'group_id' => $noiseGroup, 'payload' => $noiseValue],
        ['id' => 4, 'group_id' => $group, 'payload' => $rightValue],
    ];
    if ($seed % 4 === 0) {
        $rows[] = ['id' => 5, 'group_id' => $group, 'payload' => $leftValue];
    }

    $expectedRows = [];
    foreach ($rows as $row) {
        if ($row['group_id'] === $group && $row['payload'] === $leftValue) {
            $expectedRows[] = $row;
        }
    }
    foreach ($rows as $row) {
        if ($row['group_id'] === $group && $row['payload'] === $rightValue) {
            $expectedRows[] = $row;
        }
    }
    if ($operator === 'UNION') {
        $deduped = [];
        foreach ($expectedRows as $row) {
            $deduped[json_encode([$row['id'], $row['group_id'], $row['payload']], JSON_THROW_ON_ERROR)] = $row;
        }
        $expectedRows = array_values($deduped);
    }
    usort(
        $expectedRows,
        static fn (array $left, array $right): int => $orderDirection === 'DESC'
            ? ($right['id'] <=> $left['id'])
            : ($left['id'] <=> $right['id']),
    );
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['id'], $row['group_id'], $row['payload']);
    }

    $leftArm = "SELECT DISTINCT {$selectColumns} FROM stream_rows AS s0, stream_rows AS s1 WHERE s0.group_id=s1.group_id AND s1.group_id={$group} AND s0.payload={$leftValue}";
    $rightArm = "SELECT DISTINCT {$selectColumns} FROM stream_rows AS s0, stream_rows AS s1 WHERE s0.group_id=s1.group_id AND s1.group_id={$group} AND s0.payload={$rightValue}";
    $sql = ($leftFirst ? $leftArm . " {$operator} " . $rightArm : $rightArm . " {$operator} " . $leftArm) . " ORDER BY 1 {$orderDirection}";
    $tables = ['stream_rows' => $rows];

    $tests[sprintf('real upstream select4.test select4-15.1 dynamic coroutine yield union seed %04d', $seed)] =
        static function (TestRunner $t) use ($selectYieldAssert, $sql, $tables, $expected): void {
            $selectYieldAssert($t, $sql, $tables, $expected, 'select4-15.1');
        };
}

return $tests;
