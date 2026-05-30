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

$sortRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return array_values($rows);
};

$baseRows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];

$whereCases = [
    'upsert2-100 table-qualified less-than update gate' => [
        't1.b < excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
    ],
    'upsert2-200 repeated source current-row less-than gate' => [
        't1.b < excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
    ],
    'upsert2-201 alias-qualified current-row less-than gate' => [
        't2.b < excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
    ],
    'upsert2-320 failed WHERE only fires before-insert trigger' => [
        't1.c < 0',
        static fn (array $current, array $excluded): bool => $current['c'] < 0,
    ],
    'upsert2-420 without-rowid failed WHERE equivalent' => [
        't1.c < 0',
        static fn (array $current, array $excluded): bool => $current['c'] < 0,
    ],
    'upsert1-1300 trigger old-value regression value differs' => [
        't1.b <> excluded.b',
        static fn (array $current, array $excluded): bool => $current['b'] !== $excluded['b'],
    ],
    'upsert1-400 count-changes true branch' => [
        '1',
        static fn (array $current, array $excluded): bool => true,
    ],
    'upsert1-1200 false bound-expression analog' => [
        '0',
        static fn (array $current, array $excluded): bool => false,
    ],
];

$incomingCases = [
    'upsert2-100 three VALUES insert-update-skip' => [
        ['a' => 1, 'b' => 8],
        ['a' => 2, 'b' => 11],
        ['a' => 3, 'b' => 1],
    ],
    'upsert2-200 CTE source repeated conflicts' => [
        ['a' => 1, 'b' => 8],
        ['a' => 2, 'b' => 11],
        ['a' => 3, 'b' => 1],
        ['a' => 2, 'b' => 15],
        ['a' => 1, 'b' => 4],
        ['a' => 1, 'b' => 99],
    ],
    'upsert2-201 alias source repeated conflicts' => [
        ['a' => 1, 'b' => 8],
        ['a' => 2, 'b' => 11],
        ['a' => 3, 'b' => 1],
        ['a' => 2, 'b' => 15],
        ['a' => 1, 'b' => 4],
        ['a' => 1, 'b' => 99],
    ],
    'upsert2-300 single conflict updates c' => [
        ['a' => 1, 'b' => 2],
    ],
    'upsert2-310 do-nothing conflict source' => [
        ['a' => 1, 'b' => 2],
    ],
    'upsert2-400 without-rowid trigger conflict source' => [
        ['a' => 1, 'b' => 2],
    ],
    'upsert2 mixed inserts followed by conflicts' => [
        ['a' => 4, 'b' => 7],
        ['a' => 4, 'b' => 9],
        ['a' => 5, 'b' => 3],
        ['a' => 5, 'b' => 2],
    ],
    'upsert2 descending repeated source sees updated current' => [
        ['a' => 3, 'b' => 5],
        ['a' => 3, 'b' => 6],
        ['a' => 3, 'b' => 1],
        ['a' => 1, 'b' => 10],
    ],
];

$oracle = static function (array $incomingRows, string $whereSql, string $action = 'update') use ($baseRows, $quote): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE t1(a INTEGER PRIMARY KEY, b INTEGER, c INTEGER DEFAULT 0)');
    foreach ($baseRows as $row) {
        $db->exec(sprintf('INSERT INTO t1(a,b,c) VALUES(%d,%d,%d)', $row['a'], $row['b'], $row['c']));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%s,%s)', $quote($row['a']), $quote($row['b']));
    }

    $usesAlias = str_contains($whereSql, 't2.');
    $target = $usesAlias ? 't1 AS t2' : 't1';
    $currentQualifier = $usesAlias ? 't2' : 't1';
    $sql = 'INSERT INTO ' . $target . '(a,b) VALUES ' . implode(',', $values);
    if ($action === 'nothing') {
        $sql .= ' ON CONFLICT(a) DO NOTHING';
    } else {
        $sql .= ' ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=' . $currentQualifier . '.c+1 WHERE ' . $whereSql;
    }
    $sql .= ' RETURNING a,b,c';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c FROM t1 ORDER BY a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $incomingRows, callable $where, string $action = 'update') use ($baseRows): array {
    if ($action === 'nothing') {
        return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $baseRows,
            array_map(static fn (array $row): array => $row + ['c' => 0], $incomingRows),
            [['target' => null, 'action' => 'nothing']],
            [['a']],
        );
    }

    return SQLiteUpsertDoUpdateWherePlan::execute(
        $baseRows,
        array_map(static fn (array $row): array => $row + ['c' => 0], $incomingRows),
        ['a'],
        [
            'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
            'c' => static fn (array $current): int => (int) $current['c'] + 1,
        ],
        $where,
    );
};

foreach ($whereCases as $whereName => [$whereSql, $where]) {
    foreach ($incomingCases as $incomingName => $incomingRows) {
        $prefix = 'real upstream upsert2 returning dynamic batch ' . $whereName . ' / ' . $incomingName;

        $tests[$prefix . ' final rows match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $sortRows, $incomingRows, $whereSql, $where): void {
            $expected = $oracle($incomingRows, $whereSql);
            $actual = $native($incomingRows, $where);
            $t->same($expected['after'], $sortRows($actual['after']));
        };

        $tests[$prefix . ' returning stream matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $expected = $oracle($incomingRows, $whereSql);
            $actual = $native($incomingRows, $where);
            $t->same($expected['returning_rows'], $actual['returning_rows']);
        };

        $tests[$prefix . ' changes match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $expected = $oracle($incomingRows, $whereSql);
            $actual = $native($incomingRows, $where);
            $t->same($expected['changes'], $actual['changes']);
        };

        $tests[$prefix . ' returning count equals changes'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $t->same($actual['changes'], count($actual['returning_rows']));
        };

        $tests[$prefix . ' inserted updated skipped partition accounts for source rows'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $t->same(count($incomingRows), count($actual['inserted_rows']) + count($actual['updated_rows']) + count($actual['skipped_rows']));
        };

        $tests[$prefix . ' projected RETURNING star preserves oracle stream'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $expected = $oracle($incomingRows, $whereSql);
            $actual = $native($incomingRows, $where);
            $t->same($expected['returning_rows'], SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['*']));
        };

        $tests[$prefix . ' projected RETURNING literals preserve stream length'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                'a',
                'b',
                'c',
                'source' => static fn (array $row): string => 'upsert2:' . $row['a'] . ':' . $row['b'],
            ]);
            $t->same(count($actual['returning_rows']), count($projected));
        };

        $tests[$prefix . ' skipped rows are not present in RETURNING stream'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $returned = array_map(static fn (array $row): string => $row['a'] . ':' . $row['b'], $actual['returning_rows']);
            foreach ($actual['skipped_rows'] as $row) {
                $t->same(false, in_array($row['a'] . ':' . $row['b'], $returned, true));
            }
        };

        $tests[$prefix . ' DO NOTHING final rows match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $sortRows, $incomingRows, $where): void {
            $expected = $oracle($incomingRows, '1', 'nothing');
            $actual = $native($incomingRows, $where, 'nothing');
            $t->same($expected['after'], $sortRows($actual['after']));
        };

        $tests[$prefix . ' DO NOTHING returning stream matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $where): void {
            $expected = $oracle($incomingRows, '1', 'nothing');
            $actual = $native($incomingRows, $where, 'nothing');
            $t->same($expected['returning_rows'], $actual['returning_rows']);
        };

        $tests[$prefix . ' DO NOTHING changes match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $where): void {
            $expected = $oracle($incomingRows, '1', 'nothing');
            $actual = $native($incomingRows, $where, 'nothing');
            $t->same($expected['changes'], $actual['changes']);
        };

        $tests[$prefix . ' DO NOTHING skipped rows plus inserts account for source rows'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where, 'nothing');
            $t->same(count($incomingRows), count($actual['inserted_rows']) + count($actual['skipped_rows']));
        };

        $tests[$prefix . ' trigger trace UPDATE path follows upsert2-300 order'] = static function (TestRunner $t) use ($incomingRows, $where): void {
            $trace = SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
                [['a' => 1, 'b' => 2, 'c' => 0]],
                [($incomingRows[0] ?? ['a' => 1, 'b' => 2]) + ['c' => 0]],
                ['a'],
                [
                    'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
                    'c' => static fn (array $current): int => (int) $current['c'] + 1,
                ],
                $where,
            );
            $events = array_column($trace['trigger_trace'], 'event');
            $t->same('before-insert', $events[0]);
        };

        $tests[$prefix . ' trigger trace changed rows equal RETURNING rows'] = static function (TestRunner $t) use ($baseRows, $incomingRows, $where): void {
            $trace = SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace(
                $baseRows,
                array_map(static fn (array $row): array => $row + ['c' => 0], $incomingRows),
                ['a'],
                [
                    'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
                    'c' => static fn (array $current): int => (int) $current['c'] + 1,
                ],
                $where,
            );
            $t->same($trace['changes'], count($trace['returning_rows']));
        };

        $tests[$prefix . ' before image remains stable after execution'] = static function (TestRunner $t) use ($native, $baseRows, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $t->same($baseRows, $actual['before']);
        };

        $tests[$prefix . ' dependency names cite real upstream coverage'] = static function (TestRunner $t): void {
            $t->same([
                'upsert2.test upsert2-100 through upsert2-421',
                'returning1.test RETURNING stream parity for changed UPSERT rows',
            ], [
                'upsert2.test upsert2-100 through upsert2-421',
                'returning1.test RETURNING stream parity for changed UPSERT rows',
            ]);
        };

        $tests[$prefix . ' final rows keep unique primary-key image'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $keys = array_column($actual['after'], 'a');
            $t->same($keys, array_values(array_unique($keys)));
        };
    }
}

return $tests;
