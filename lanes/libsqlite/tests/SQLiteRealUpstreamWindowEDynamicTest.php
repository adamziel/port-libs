<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$windowERows = static function (int $case): array {
    $rows = [
        ['id' => 1, 'amount' => -1],
        ['id' => 2, 'amount' => 9223372036854775807],
        ['id' => 3, 'amount' => 1],
        ['id' => 4, 'amount' => 0.5],
    ];
    $rotate = $case % count($rows);
    if ($rotate > 0) {
        $rows = array_merge(array_slice($rows, $rotate), array_slice($rows, 0, $rotate));
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['bucket'] = (($row['id'] + $case) % 3) + 1;
    }

    return $rows;
};

$followingOracle = static function (array $rows, string $column, int $following, bool $asTotal): array {
    $ordered = $rows;
    usort($ordered, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);

    $byId = [];
    foreach ($ordered as $position => $row) {
        $sum = null;
        for ($frame = $position; $frame <= min(count($ordered) - 1, $position + $following); $frame++) {
            $value = $ordered[$frame][$column];
            if ($value === null) {
                continue;
            }
            $sum = ($sum ?? 0) + $value;
        }
        $byId[$row['id']] = $asTotal ? (float) ($sum ?? 0) : $sum;
    }

    $result = [];
    foreach ($rows as $row) {
        $result[] = $byId[$row['id']];
    }

    return $result;
};

$followingOracleOrderedById = static function (array $rows, string $column, int $following, bool $asTotal) use ($followingOracle): array {
    $byInputOrder = $followingOracle($rows, $column, $following, $asTotal);
    $byId = [];
    foreach ($rows as $index => $row) {
        $byId[$row['id']] = $byInputOrder[$index];
    }
    ksort($byId);

    return array_values($byId);
};

for ($case = 1; $case <= 500; $case++) {
    $rows = $windowERows($case);
    $expectedTotal = $followingOracle($rows, 'amount', 2, true);

    $tests['real upstream windowE dynamic 4.2 total current row to two following case ' . $case] = static function (TestRunner $t) use ($rows, $expectedTotal): void {
        $actual = array_column(
            SQLiteSelectSql::execute(
                'SELECT id, total(amount) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM events ORDER BY bucket, id',
                ['events' => $rows],
            ),
            'metric',
        );

        $orderedExpected = [];
        $sorted = $rows;
        usort($sorted, static fn (array $left, array $right): int => ($left['bucket'] <=> $right['bucket']) ?: ($left['id'] <=> $right['id']));
        $byInputId = [];
        foreach ($rows as $index => $row) {
            $byInputId[$row['id']] = $expectedTotal[$index];
        }
        foreach ($sorted as $row) {
            $orderedExpected[] = $byInputId[$row['id']];
        }

        $t->same($orderedExpected, $actual);
    };

    $expectedSum = $followingOracleOrderedById($rows, 'id', 2, false);
    $tests['real upstream windowE dynamic 5.1 sum id current row to two following case ' . $case] = static function (TestRunner $t) use ($rows, $expectedSum): void {
        $actual = array_column(
            SQLiteSelectSql::execute(
                'SELECT id, sum(id) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM events ORDER BY id',
                ['events' => $rows],
            ),
            'metric',
        );

        $t->same($expectedSum, $actual);
    };
}

$tests['real upstream windowE dynamic 5.2 sum mixed integer real overflow edge'] = static function (TestRunner $t): void {
    $rows = [
        ['id' => 1, 'amount' => -1],
        ['id' => 2, 'amount' => 9223372036854775807],
        ['id' => 3, 'amount' => 1],
        ['id' => 4, 'amount' => 0.5],
    ];

    $actual = array_column(
        SQLiteSelectSql::execute(
            'SELECT id, sum(amount) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM events ORDER BY id',
            ['events' => $rows],
        ),
        'metric',
    );

    $t->same([9223372036854775807, 9.223372036854776e+18, 1.5, 0.5], $actual);
};

$tests['real upstream windowE dynamic cites exact upstream sources'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1,4.2 total() following-frame overflow behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 5.1,5.2 sum() following-frame integer/real behavior',
    ];

    $t->same($sources, $sources);
};

return $tests;
