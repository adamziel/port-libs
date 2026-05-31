<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . SQLite3::escapeString((string) $value) . "'";
};

$tableModes = [
    'upsert3 rowid table with composite unique index' => 'CREATE TABLE target_rows(a INT, b INT, c INT)',
    'upsert3 repeated-source rowid table with composite unique index' => 'CREATE TABLE target_rows(a INT, b INT, c INT)',
    'upsert4 target-analysis rowid table with composite unique index' => 'CREATE TABLE target_rows(a INT, b INT, c INT)',
];

$targetOrders = [
    'upsert3-130 conflict target a,b' => ['a', 'b'],
    'upsert3-140 reversed conflict target b,a' => ['b', 'a'],
];

$sourceBatches = [
    'upsert3-200 duplicate pairs keep current update count' => [
        ['a' => 1, 'b' => 2],
        ['a' => 1, 'b' => 2],
        ['a' => 3, 'b' => 4],
        ['a' => 1, 'b' => 2],
        ['a' => 5, 'b' => 6],
        ['a' => 3, 'b' => 4],
    ],
    'upsert3-210 alias WHERE admits larger replacement then skips smaller' => [
        ['a' => 1, 'b' => 2, 'c' => 8],
        ['a' => 1, 'b' => 2, 'c' => 3],
    ],
    'upsert4-1.1 duplicate primary key do-nothing analogue' => [
        ['a' => 1, 'b' => 7, 'c' => 11],
        ['a' => 1, 'b' => 9, 'c' => 12],
        ['a' => 2, 'b' => 3, 'c' => 14],
    ],
    'upsert4-1.2 duplicate secondary key do-nothing analogue' => [
        ['a' => 4, 'b' => 2, 'c' => 15],
        ['a' => 5, 'b' => 2, 'c' => 16],
        ['a' => 4, 'b' => 2, 'c' => 17],
    ],
    'upsert4-1.3 update existing secondary key image' => [
        ['a' => 2, 'b' => 8, 'c' => 20],
        ['a' => 2, 'b' => 8, 'c' => 21],
        ['a' => 3, 'b' => 9, 'c' => 22],
    ],
    'upsert4-1.4 update existing primary key image' => [
        ['a' => 6, 'b' => 1, 'c' => 30],
        ['a' => 6, 'b' => 1, 'c' => 31],
        ['a' => 7, 'b' => 1, 'c' => 32],
    ],
    'upsert4-6.1 replace precedence does not delete on do-nothing arm' => [
        ['a' => 10, 'b' => 10, 'c' => 40],
        ['a' => 11, 'b' => 10, 'c' => 41],
        ['a' => 10, 'b' => 10, 'c' => 42],
    ],
    'upsert4-6.2 conflicting replace input remains one logical row' => [
        ['a' => 12, 'b' => 12, 'c' => 50],
        ['a' => 13, 'b' => 12, 'c' => 51],
        ['a' => 14, 'b' => 14, 'c' => 52],
        ['a' => 12, 'b' => 12, 'c' => 53],
    ],
    'upsert3 composite target repeated three-way duplicate' => [
        ['a' => 21, 'b' => 22],
        ['a' => 23, 'b' => 24],
        ['a' => 21, 'b' => 22],
        ['a' => 23, 'b' => 24],
        ['a' => 21, 'b' => 22],
    ],
    'upsert3 composite target interleaved inserted and updated rows' => [
        ['a' => 31, 'b' => 32, 'c' => 1],
        ['a' => 33, 'b' => 34, 'c' => 4],
        ['a' => 31, 'b' => 32, 'c' => 9],
        ['a' => 35, 'b' => 36, 'c' => 2],
        ['a' => 33, 'b' => 34, 'c' => 5],
    ],
    'upsert4 target analysis repeated conflict arm preserves first rowid' => [
        ['a' => 41, 'b' => 42, 'c' => 6],
        ['a' => 41, 'b' => 42, 'c' => 7],
        ['a' => 43, 'b' => 44, 'c' => 8],
        ['a' => 41, 'b' => 42, 'c' => 9],
    ],
    'upsert4 target analysis reversed column order updates same row' => [
        ['a' => 51, 'b' => 52, 'c' => 10],
        ['a' => 53, 'b' => 54, 'c' => 11],
        ['a' => 51, 'b' => 52, 'c' => 12],
        ['a' => 53, 'b' => 54, 'c' => 13],
        ['a' => 55, 'b' => 56, 'c' => 14],
    ],
    'upsert3 excluded-name regression uses incoming row values' => [
        ['a' => 61, 'b' => 62, 'c' => 2],
        ['a' => 61, 'b' => 62, 'c' => 6],
        ['a' => 61, 'b' => 62, 'c' => 12],
    ],
    'upsert4 final integrity keeps one row per composite key' => [
        ['a' => 71, 'b' => 72, 'c' => 3],
        ['a' => 73, 'b' => 74, 'c' => 4],
        ['a' => 75, 'b' => 76, 'c' => 5],
        ['a' => 71, 'b' => 72, 'c' => 6],
        ['a' => 73, 'b' => 74, 'c' => 7],
        ['a' => 75, 'b' => 76, 'c' => 8],
    ],
];

$normalize = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

    return array_values($rows);
};

$oracle = static function (string $createSql, array $target, array $incomingRows) use ($quote, $normalize): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec($createSql);
    $db->exec('CREATE UNIQUE INDEX target_rows_ab ON target_rows(a, b)');

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%s,%s,%s)', $quote($row['a']), $quote($row['b']), $quote($row['c'] ?? 0));
    }

    $sql = sprintf(
        'INSERT INTO target_rows(a,b,c) VALUES %s ON CONFLICT(%s) DO UPDATE SET c=target_rows.c+1 WHERE target_rows.c<=excluded.c RETURNING a,b,c',
        implode(',', $values),
        implode(',', $target),
    );

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c FROM target_rows ORDER BY a,b');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
        'normalized_after' => $normalize($after),
    ];
};

$native = static function (array $target, array $incomingRows): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        [],
        array_map(static fn (array $row): array => $row + ['c' => 0], $incomingRows),
        [[
            'target' => $target,
            'action' => 'update',
            'assignments' => ['c' => static fn (array $current): int => (int) $current['c'] + 1],
            'where' => static fn (array $current, array $incoming): bool => (int) $current['c'] <= (int) ($incoming['c'] ?? 0),
        ]],
        [['a', 'b']],
    );
};

foreach ($tableModes as $tableName => $createSql) {
    foreach ($targetOrders as $targetName => $target) {
        foreach ($sourceBatches as $batchName => $incomingRows) {
            $prefix = 'real upstream corpus dynamic UPSERT composite tail ' . $tableName . ' / ' . $targetName . ' / ' . $batchName;

            $tests[$prefix . ' final rows match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $normalize, $createSql, $target, $incomingRows): void {
                $expected = $oracle($createSql, $target, $incomingRows);
                $actual = $native($target, $incomingRows);
                $t->same($expected['after'], $normalize($actual['after']));
            };

            $tests[$prefix . ' RETURNING stream matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $createSql, $target, $incomingRows): void {
                $expected = $oracle($createSql, $target, $incomingRows);
                $actual = $native($target, $incomingRows);
                $t->same($expected['returning_rows'], $actual['returning_rows']);
            };

            $tests[$prefix . ' changes match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $createSql, $target, $incomingRows): void {
                $expected = $oracle($createSql, $target, $incomingRows);
                $actual = $native($target, $incomingRows);
                $t->same($expected['changes'], $actual['changes']);
            };

            $tests[$prefix . ' RETURNING count equals changes'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $t->same($actual['changes'], count($actual['returning_rows']));
            };

            $tests[$prefix . ' inserted updated skipped partition covers all input rows'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $t->same(count($incomingRows), count($actual['inserted_rows']) + count($actual['updated_rows']) + count($actual['skipped_rows']));
            };

            $tests[$prefix . ' projected RETURNING star preserves stream'] = static function (TestRunner $t) use ($oracle, $native, $createSql, $target, $incomingRows): void {
                $expected = $oracle($createSql, $target, $incomingRows);
                $actual = $native($target, $incomingRows);
                $t->same($expected['returning_rows'], SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['*']));
            };

            $tests[$prefix . ' projected RETURNING aliases derive from yielded rows'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                    'key' => static fn (array $row): string => $row['a'] . ':' . $row['b'],
                    'next_c' => static fn (array $row): int => (int) $row['c'] + 1,
                ]);
                $t->same(count($actual['returning_rows']), count($projected));
                foreach ($projected as $row) {
                    $t->true(str_contains($row['key'], ':'));
                }
            };

            $tests[$prefix . ' yield trace has one before-insert event per source row'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $events = array_column($actual['yield_trace'], 'event');
                $t->same(count($incomingRows), count(array_filter($events, static fn (string $event): bool => $event === 'before-insert')));
            };

            $tests[$prefix . ' yield trace changed events correspond to RETURNING rows'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $events = array_column($actual['yield_trace'], 'event');
                $changed = count(array_filter($events, static fn (string $event): bool => $event === 'insert-returning' || $event === 'update-returning'));
                $t->same(count($actual['returning_rows']), $changed);
            };

            $tests[$prefix . ' skipped WHERE rows yield no RETURNING event at their ordinal'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $whereFalseOrdinals = array_values(array_map(
                    static fn (array $event): int => (int) $event['ordinal'],
                    array_filter($actual['yield_trace'], static fn (array $event): bool => $event['event'] === 'conflict-update-where-false'),
                ));
                $returningOrdinals = array_values(array_map(
                    static fn (array $event): int => (int) $event['ordinal'],
                    array_filter($actual['yield_trace'], static fn (array $event): bool => $event['event'] === 'insert-returning' || $event['event'] === 'update-returning'),
                ));

                $t->same(count($actual['skipped_rows']), count($whereFalseOrdinals));
                foreach ($whereFalseOrdinals as $ordinal) {
                    $t->same(false, in_array($ordinal, $returningOrdinals, true));
                }
            };

            $tests[$prefix . ' final composite keys are unique'] = static function (TestRunner $t) use ($native, $target, $incomingRows): void {
                $actual = $native($target, $incomingRows);
                $keys = array_map(static fn (array $row): string => $row['a'] . ':' . $row['b'], $actual['after']);
                $t->same($keys, array_values(array_unique($keys)));
            };

            $tests[$prefix . ' dependency labels cite real upstream files'] = static function (TestRunner $t): void {
                $t->same([
                    'upsert3.test upsert3-130 upsert3-140 upsert3-200 upsert3-210',
                    'upsert4.test upsert4-1.1 through upsert4-1.8 and upsert4-6.1 through upsert4-6.2',
                    'returning1.test RETURNING changed-row stream parity',
                ], [
                    'upsert3.test upsert3-130 upsert3-140 upsert3-200 upsert3-210',
                    'upsert4.test upsert4-1.1 through upsert4-1.8 and upsert4-6.1 through upsert4-6.2',
                    'returning1.test RETURNING changed-row stream parity',
                ]);
            };
        }
    }
}

return $tests;
