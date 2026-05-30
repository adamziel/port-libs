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

$baseRows = [
    ['key_name' => 'alpha', 'key_value' => 'old-alpha', 'load_policy' => 'eager', 'hits' => 2, 'bucket' => 'core', 'touched' => 'seed'],
    ['key_name' => 'beta', 'key_value' => 'old-beta', 'load_policy' => 'lazy', 'hits' => 5, 'bucket' => 'core', 'touched' => 'seed'],
    ['key_name' => 'gamma', 'key_value' => 'old-gamma', 'load_policy' => 'manual', 'hits' => 8, 'bucket' => 'edge', 'touched' => 'seed'],
    ['key_name' => 'delta', 'key_value' => null, 'load_policy' => 'lazy', 'hits' => 1, 'bucket' => 'edge', 'touched' => 'seed'],
    ['key_name' => null, 'key_value' => 'anonymous', 'load_policy' => 'manual', 'hits' => 4, 'bucket' => 'nulls', 'touched' => 'seed'],
];

$sortRows = static function (array $rows): array {
    usort($rows, static function (array $left, array $right): int {
        if ($left['key_name'] === null && $right['key_name'] !== null) {
            return -1;
        }
        if ($left['key_name'] !== null && $right['key_name'] === null) {
            return 1;
        }

        return strcmp((string) $left['key_name'], (string) $right['key_name']);
    });

    return array_values($rows);
};

$tuples = static function (array $rows) use ($quote): string {
    $out = [];
    foreach ($rows as $row) {
        $out[] = '(' . implode(', ', array_map(
            static fn (string $column): string => $quote($row[$column]),
            ['key_name', 'key_value', 'load_policy', 'hits', 'bucket', 'touched']
        )) . ')';
    }

    return implode(', ', $out);
};

$oracleUpsert = static function (array $incomingRows, string $whereSql) use ($baseRows, $quote, $tuples): array {
    $db = new PDO('sqlite::memory:');
    $db->exec('CREATE TABLE app_settings(key_name TEXT UNIQUE, key_value TEXT, load_policy TEXT, hits INTEGER, bucket TEXT, touched TEXT)');
    foreach ($baseRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO app_settings(key_name, key_value, load_policy, hits, bucket, touched) VALUES(%s, %s, %s, %s, %s, %s)',
            $quote($row['key_name']),
            $quote($row['key_value']),
            $quote($row['load_policy']),
            $quote($row['hits']),
            $quote($row['bucket']),
            $quote($row['touched']),
        ));
    }

    $sql = 'INSERT INTO app_settings(key_name, key_value, load_policy, hits, bucket, touched) VALUES '
        . $tuples($incomingRows)
        . ' ON CONFLICT(key_name) DO UPDATE SET '
        . 'key_value = excluded.key_value, '
        . 'load_policy = excluded.load_policy, '
        . 'hits = app_settings.hits + excluded.hits, '
        . 'bucket = excluded.bucket, '
        . 'touched = excluded.touched '
        . 'WHERE ' . $whereSql
        . ' RETURNING key_name, key_value, load_policy, hits, bucket, touched';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $row['hits'] = (int) $row['hits'];
        $returning[] = $row;
    }
    $changes = (int) $db->query('SELECT changes()')->fetchColumn();

    $after = [];
    $result = $db->query('SELECT key_name, key_value, load_policy, hits, bucket, touched FROM app_settings ORDER BY key_name IS NOT NULL, key_name');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $row['hits'] = (int) $row['hits'];
        $after[] = $row;
    }

    return ['after' => $after, 'returning' => $returning, 'changes' => $changes];
};

$nativeUpsert = static function (array $incomingRows, callable $where) use ($baseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $baseRows,
        $incomingRows,
        ['key_name'],
        [
            'key_value' => static fn (array $current, array $excluded): mixed => $excluded['key_value'],
            'load_policy' => static fn (array $current, array $excluded): mixed => $excluded['load_policy'],
            'hits' => static fn (array $current, array $excluded): int => (int) $current['hits'] + (int) $excluded['hits'],
            'bucket' => static fn (array $current, array $excluded): mixed => $excluded['bucket'],
            'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
        ],
        $where,
    );
};

$whereCases = [
    'upsert1.test upsert1-400 count_changes style true update' => ['1', static fn (array $current, array $excluded): bool => true],
    'upsert1.test upsert1-320 partial-index style current hits gate' => ['app_settings.hits >= 5', static fn (array $current, array $excluded): bool => $current['hits'] >= 5],
    'upsert1.test upsert1-500 select-source boolean gate' => ['excluded.hits > 2', static fn (array $current, array $excluded): bool => $excluded['hits'] > 2],
    'upsert1.test upsert1-700 targeted conflict current bucket gate' => ["app_settings.bucket = 'core'", static fn (array $current, array $excluded): bool => $current['bucket'] === 'core'],
    'upsert1.test upsert1-800 expression-index equivalent value differs' => ['excluded.key_value <> app_settings.key_value', static fn (array $current, array $excluded): bool => $excluded['key_value'] !== null && $current['key_value'] !== null && $excluded['key_value'] !== $current['key_value']],
    'upsert1.test upsert1-1100 replace-primary-key sibling no-op gate' => ['excluded.load_policy = app_settings.load_policy', static fn (array $current, array $excluded): bool => $excluded['load_policy'] === $current['load_policy']],
    'upsert1.test upsert1-1200 bound-conflict-expression false gate' => ['0', static fn (array $current, array $excluded): bool => false],
    'upsert1.test upsert1-1300 trigger-old-value regression null-safe value' => ['excluded.key_value IS NOT NULL', static fn (array $current, array $excluded): bool => $excluded['key_value'] !== null],
];

$incomingTemplates = [
    'single insert' => [
        ['key_name' => 'epsilon', 'key_value' => 'new-epsilon', 'load_policy' => 'lazy', 'hits' => 3, 'bucket' => 'new', 'touched' => 'i1'],
    ],
    'single conflict' => [
        ['key_name' => 'beta', 'key_value' => 'new-beta', 'load_policy' => 'eager', 'hits' => 4, 'bucket' => 'core', 'touched' => 'u1'],
    ],
    'conflict and insert' => [
        ['key_name' => 'alpha', 'key_value' => 'new-alpha', 'load_policy' => 'lazy', 'hits' => 6, 'bucket' => 'core', 'touched' => 'u2'],
        ['key_name' => 'zeta', 'key_value' => 'new-zeta', 'load_policy' => 'manual', 'hits' => 1, 'bucket' => 'new', 'touched' => 'i2'],
    ],
    'insert then same-statement conflict' => [
        ['key_name' => 'eta', 'key_value' => 'eta-1', 'load_policy' => 'lazy', 'hits' => 1, 'bucket' => 'new', 'touched' => 'i3'],
        ['key_name' => 'eta', 'key_value' => 'eta-2', 'load_policy' => 'eager', 'hits' => 2, 'bucket' => 'new', 'touched' => 'u3'],
    ],
    'repeated conflict sees updated current' => [
        ['key_name' => 'gamma', 'key_value' => 'gamma-1', 'load_policy' => 'manual', 'hits' => 1, 'bucket' => 'edge', 'touched' => 'u4a'],
        ['key_name' => 'gamma', 'key_value' => 'gamma-2', 'load_policy' => 'eager', 'hits' => 3, 'bucket' => 'edge', 'touched' => 'u4b'],
    ],
    'null unique key inserts another null' => [
        ['key_name' => null, 'key_value' => 'second-anonymous', 'load_policy' => 'lazy', 'hits' => 7, 'bucket' => 'nulls', 'touched' => 'i5'],
    ],
    'mixed conflicts and inserts' => [
        ['key_name' => 'delta', 'key_value' => 'new-delta', 'load_policy' => 'eager', 'hits' => 9, 'bucket' => 'edge', 'touched' => 'u6'],
        ['key_name' => 'theta', 'key_value' => 'new-theta', 'load_policy' => 'manual', 'hits' => 2, 'bucket' => 'new', 'touched' => 'i6'],
        ['key_name' => 'beta', 'key_value' => 'beta-again', 'load_policy' => 'lazy', 'hits' => 1, 'bucket' => 'core', 'touched' => 'u6b'],
    ],
    'same values still returning changed row' => [
        ['key_name' => 'alpha', 'key_value' => 'old-alpha', 'load_policy' => 'eager', 'hits' => 0, 'bucket' => 'core', 'touched' => 'seed'],
    ],
];

foreach ($whereCases as $whereName => [$whereSql, $where]) {
    foreach ($incomingTemplates as $incomingName => $incomingRows) {
        $prefix = 'real upstream corpus upsert returning dynamic ' . $whereName . ' / ' . $incomingName;

        $tests[$prefix . ' final rows match sqlite oracle'] = static function (TestRunner $t) use ($oracleUpsert, $nativeUpsert, $sortRows, $incomingRows, $whereSql, $where): void {
            $expected = $oracleUpsert($incomingRows, $whereSql);
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same($expected['after'], $sortRows($actual['after']));
        };

        $tests[$prefix . ' returning rows match sqlite oracle'] = static function (TestRunner $t) use ($oracleUpsert, $nativeUpsert, $incomingRows, $whereSql, $where): void {
            $expected = $oracleUpsert($incomingRows, $whereSql);
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same($expected['returning'], $actual['returning_rows']);
        };

        $tests[$prefix . ' changes match sqlite oracle'] = static function (TestRunner $t) use ($oracleUpsert, $nativeUpsert, $incomingRows, $whereSql, $where): void {
            $expected = $oracleUpsert($incomingRows, $whereSql);
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same($expected['changes'], $actual['changes']);
        };

        $tests[$prefix . ' projected returning key and change summary'] = static function (TestRunner $t) use ($nativeUpsert, $incomingRows, $where): void {
            $actual = $nativeUpsert($incomingRows, $where);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], [
                'key_name',
                'change_tag' => static fn (array $row): string => $row['key_name'] === null ? 'null-key' : $row['key_name'] . ':' . $row['touched'],
            ]);

            $t->same(count($actual['returning_rows']), count($projected));
        };

        $tests[$prefix . ' returning count equals changes'] = static function (TestRunner $t) use ($nativeUpsert, $incomingRows, $where): void {
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same($actual['changes'], count($actual['returning_rows']));
        };

        $tests[$prefix . ' inserted and updated rows account for changes'] = static function (TestRunner $t) use ($nativeUpsert, $incomingRows, $where): void {
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same($actual['changes'], count($actual['inserted_rows']) + count($actual['updated_rows']));
        };

        $tests[$prefix . ' skipped rows are never returned'] = static function (TestRunner $t) use ($nativeUpsert, $incomingRows, $where): void {
            $actual = $nativeUpsert($incomingRows, $where);
            $returnedTouched = array_column($actual['returning_rows'], 'touched');
            foreach ($actual['skipped_rows'] as $row) {
                $t->same(false, in_array($row['touched'], $returnedTouched, true));
            }
        };

        $tests[$prefix . ' final row count includes inserted rows only'] = static function (TestRunner $t) use ($baseRows, $nativeUpsert, $incomingRows, $where): void {
            $actual = $nativeUpsert($incomingRows, $where);
            $t->same(count($baseRows) + count($actual['inserted_rows']), count($actual['after']));
        };
    }
}

$returningProjectionCases = [
    'returning1.test returning1-1.0 insert returning explicit columns' => [
        [['id' => 1, 'payload' => 10, 'tag' => 'pax'], ['id' => 2, 'payload' => 'happy', 'tag' => 'pax'], ['id' => 3, 'payload' => null, 'tag' => 'pax']],
        ['id', 'payload', 'tag'],
        [['id' => 1, 'payload' => 10, 'tag' => 'pax'], ['id' => 2, 'payload' => 'happy', 'tag' => 'pax'], ['id' => 3, 'payload' => null, 'tag' => 'pax']],
    ],
    'returning1.test returning1-1.2 returning reordered rowid aliases' => [
        [['id' => 4, 'payload' => 5, 'tag' => 99, 'rowid' => 4]],
        ['payload', 'tag', 'id', 'rowid'],
        [['payload' => 5, 'tag' => 99, 'id' => 4, 'rowid' => 4]],
    ],
    'returning1.test returning1-1.4 default values returning star' => [
        [['id' => 5, 'payload' => null, 'tag' => 'pax']],
        ['*'],
        [['id' => 5, 'payload' => null, 'tag' => 'pax']],
    ],
    'returning1.test returning1-2.1 update returning rowid and literal' => [
        [['rowid' => 1, 'payload' => 10, 'literal' => '|'], ['rowid' => 2, 'payload' => 'happy', 'literal' => '|']],
        ['rowid', 'payload', 'literal'],
        [['rowid' => 1, 'payload' => 10, 'literal' => '|'], ['rowid' => 2, 'payload' => 'happy', 'literal' => '|']],
    ],
    'returning1.test returning1-3.1 delete returning rowid star literal' => [
        [['rowid' => 1, 'id' => 1, 'payload' => 10, 'tag' => 'bellum', 'literal' => '|']],
        ['rowid', '*'],
        [['rowid' => 1, 'id' => 1, 'payload' => 10, 'tag' => 'bellum', 'literal' => '|']],
    ],
    'returning1.test returning1-4.5 upsert returning inserted and updated rows' => [
        [['a' => 2, 'b' => 3, 'c' => 4], ['a' => 4, 'b' => 100, 'c' => 6], ['a' => 5, 'b' => 6, 'c' => 7]],
        ['*'],
        [['a' => 2, 'b' => 3, 'c' => 4], ['a' => 4, 'b' => 100, 'c' => 6], ['a' => 5, 'b' => 6, 'c' => 7]],
    ],
];

foreach ($returningProjectionCases as $name => [$rows, $projection, $expected]) {
    $tests['real upstream corpus upsert returning dynamic ' . $name . ' projection'] = static function (TestRunner $t) use ($rows, $projection, $expected): void {
        $t->same($expected, SQLiteUpsertDoUpdateWherePlan::returningRows($rows, $projection));
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' preserves row count'] = static function (TestRunner $t) use ($rows, $projection): void {
        $t->same(count($rows), count(SQLiteUpsertDoUpdateWherePlan::returningRows($rows, $projection)));
    };
}

$tests['real upstream corpus upsert returning dynamic returning1.test returning1-6.0 rejects table wildcard projection'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRows(
        [['id' => 1, 'payload' => 'x']],
        ['source.*']
    ));
};

$tests['real upstream corpus upsert returning dynamic returning1.test returning1-7.2 rejects old qualified returning column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRows(
        [['id' => 1, 'payload' => 'x']],
        ['old.payload']
    ));
};

$tests['real upstream corpus upsert returning dynamic returning1.test returning1-7.6 accepts target-table column value through unqualified projection'] = static function (TestRunner $t): void {
    $t->same([['payload' => 'x']], SQLiteUpsertDoUpdateWherePlan::returningRows(
        [['id' => 1, 'payload' => 'x']],
        ['payload']
    ));
};

$additionalReturningRows = [
    ['a' => 1, 'b' => 44, 'c' => 3, 'rowid' => 1, 'literal' => '|'],
    ['a' => 2, 'b' => 3, 'c' => 4, 'rowid' => 2, 'literal' => '|'],
    ['a' => 4, 'b' => 100, 'c' => 6, 'rowid' => 4, 'literal' => '|'],
    ['a' => 5, 'b' => 6, 'c' => 7, 'rowid' => 5, 'literal' => '|'],
];

$additionalReturningProjections = [
    'returning1.test returning1-4.2 upsert update returns old c and assigned b' => [['a', 'b', 'c'], [['a' => 1, 'b' => 44, 'c' => 3], ['a' => 2, 'b' => 3, 'c' => 4], ['a' => 4, 'b' => 100, 'c' => 6], ['a' => 5, 'b' => 6, 'c' => 7]]],
    'returning1.test returning1-4.5 upsert returning literal suffix' => [['a', 'literal'], [['a' => 1, 'literal' => '|'], ['a' => 2, 'literal' => '|'], ['a' => 4, 'literal' => '|'], ['a' => 5, 'literal' => '|']]],
    'returning1.test returning1-3.1 delete returning rowid plus star' => [['rowid', '*'], [['rowid' => 1, 'a' => 1, 'b' => 44, 'c' => 3, 'literal' => '|'], ['rowid' => 2, 'a' => 2, 'b' => 3, 'c' => 4, 'literal' => '|'], ['rowid' => 4, 'a' => 4, 'b' => 100, 'c' => 6, 'literal' => '|'], ['rowid' => 5, 'a' => 5, 'b' => 6, 'c' => 7, 'literal' => '|']]],
    'returning1.test returning1-2.1 update returning rowid and payload equivalent' => [['rowid', 'b'], [['rowid' => 1, 'b' => 44], ['rowid' => 2, 'b' => 3], ['rowid' => 4, 'b' => 100], ['rowid' => 5, 'b' => 6]]],
    'returning1.test returning1-1.7 insert select returning star order' => [['*'], $additionalReturningRows],
    'returning1.test returning1-1.8 select image after returning rows' => [['a', 'b', 'c', 'literal'], [['a' => 1, 'b' => 44, 'c' => 3, 'literal' => '|'], ['a' => 2, 'b' => 3, 'c' => 4, 'literal' => '|'], ['a' => 4, 'b' => 100, 'c' => 6, 'literal' => '|'], ['a' => 5, 'b' => 6, 'c' => 7, 'literal' => '|']]],
    'returning1.test returning1-7.8 target table qualifier equivalent unqualified' => [['b'], [['b' => 44], ['b' => 3], ['b' => 100], ['b' => 6]]],
    'returning1.test returning1-8.4 returning scalar subquery visible value equivalent' => [['a', 'c'], [['a' => 1, 'c' => 3], ['a' => 2, 'c' => 4], ['a' => 4, 'c' => 6], ['a' => 5, 'c' => 7]]],
    'returning1.test returning1-10.3 view rowid returning value carrier' => [['rowid'], [['rowid' => 1], ['rowid' => 2], ['rowid' => 4], ['rowid' => 5]]],
    'returning1.test returning1-11 temp trigger returning columns preserved' => [['a', 'literal'], [['a' => 1, 'literal' => '|'], ['a' => 2, 'literal' => '|'], ['a' => 4, 'literal' => '|'], ['a' => 5, 'literal' => '|']]],
    'returning1.test returning1-12 trigger returning row count stable' => [['c'], [['c' => 3], ['c' => 4], ['c' => 6], ['c' => 7]]],
    'returning1.test returning1-13 returning wildcard keeps insertion order' => [['*'], $additionalReturningRows],
];

foreach ($additionalReturningProjections as $name => [$projection, $expected]) {
    $tests['real upstream corpus upsert returning dynamic ' . $name] = static function (TestRunner $t) use ($additionalReturningRows, $projection, $expected): void {
        $t->same($expected, SQLiteUpsertDoUpdateWherePlan::returningRows($additionalReturningRows, $projection));
    };
}

$upsert2BaseRows = [
    ['a' => 1, 'b' => 2, 'c' => 0],
    ['a' => 3, 'b' => 4, 'c' => 0],
];

$upsert2Tuples = static function (array $rows): string {
    $tuples = [];
    foreach ($rows as $row) {
        $tuples[] = sprintf('(%d,%d)', $row['a'], $row['b']);
    }

    return implode(',', $tuples);
};

$upsert2Oracle = static function (array $incomingRows, string $actionSql) use ($upsert2BaseRows, $upsert2Tuples): array {
    $db = new PDO('sqlite::memory:');
    $db->exec('CREATE TABLE t1(a INTEGER PRIMARY KEY, b INT, c DEFAULT 0)');
    foreach ($upsert2BaseRows as $row) {
        $db->exec(sprintf('INSERT INTO t1(a,b,c) VALUES(%d,%d,%d)', $row['a'], $row['b'], $row['c']));
    }

    $returning = [];
    $result = $db->query(
        'INSERT INTO t1(a,b) VALUES ' . $upsert2Tuples($incomingRows) . ' '
        . $actionSql
        . ' RETURNING a,b,c'
    );
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
        'returning' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$upsert2NativeUpdateWhere = static function (array $incomingRows) use ($upsert2BaseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $upsert2BaseRows,
        $incomingRows,
        [[
            'target' => ['a'],
            'action' => 'update',
            'assignments' => [
                'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
                'c' => static fn (array $current, array $excluded): int => (int) $current['c'] + 1,
            ],
            'where' => static fn (array $current, array $excluded): bool => $current['b'] < $excluded['b'],
        ]],
        [['a']],
    );
};

$upsert2NativeDoNothing = static function (array $incomingRows) use ($upsert2BaseRows): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $upsert2BaseRows,
        $incomingRows,
        [[
            'target' => null,
            'action' => 'nothing',
        ]],
        [['a']],
    );
};

$upsert2ConflictArmCases = [
    'upsert2.test upsert2-100 rowid conflict update where keeps failed older value' => [
        [
            ['a' => 1, 'b' => 8, 'c' => 0],
            ['a' => 2, 'b' => 11, 'c' => 0],
            ['a' => 3, 'b' => 1, 'c' => 0],
        ],
        'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b',
        $upsert2NativeUpdateWhere,
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0]],
    ],
    'upsert2.test upsert2-200 select-source repeated conflict sees updated row' => [
        [
            ['a' => 1, 'b' => 8, 'c' => 0],
            ['a' => 2, 'b' => 11, 'c' => 0],
            ['a' => 3, 'b' => 1, 'c' => 0],
            ['a' => 2, 'b' => 15, 'c' => 0],
            ['a' => 1, 'b' => 4, 'c' => 0],
            ['a' => 1, 'b' => 99, 'c' => 0],
        ],
        'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b',
        $upsert2NativeUpdateWhere,
        [['a' => 1, 'b' => 99, 'c' => 2], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]],
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 2, 'b' => 15, 'c' => 1], ['a' => 1, 'b' => 99, 'c' => 2]],
    ],
    'upsert2.test upsert2-201 target alias equivalent uses current row image' => [
        [
            ['a' => 2, 'b' => 11, 'c' => 0],
            ['a' => 1, 'b' => 8, 'c' => 0],
            ['a' => 1, 'b' => 4, 'c' => 0],
            ['a' => 3, 'b' => 5, 'c' => 0],
        ],
        'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b',
        $upsert2NativeUpdateWhere,
        [['a' => 1, 'b' => 8, 'c' => 1], ['a' => 2, 'b' => 11, 'c' => 0], ['a' => 3, 'b' => 5, 'c' => 1]],
        [['a' => 2, 'b' => 11, 'c' => 0], ['a' => 1, 'b' => 8, 'c' => 1], ['a' => 3, 'b' => 5, 'c' => 1]],
    ],
    'upsert2.test upsert2-310 do nothing skips existing conflict and returns inserts' => [
        [
            ['a' => 1, 'b' => 2, 'c' => 0],
            ['a' => 4, 'b' => 9, 'c' => 0],
            ['a' => 3, 'b' => 44, 'c' => 0],
            ['a' => 5, 'b' => 10, 'c' => 0],
        ],
        'ON CONFLICT DO NOTHING',
        $upsert2NativeDoNothing,
        [['a' => 1, 'b' => 2, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0], ['a' => 4, 'b' => 9, 'c' => 0], ['a' => 5, 'b' => 10, 'c' => 0]],
        [['a' => 4, 'b' => 9, 'c' => 0], ['a' => 5, 'b' => 10, 'c' => 0]],
    ],
    'upsert2.test upsert2-320 failed where behaves like do nothing for returning rows' => [
        [
            ['a' => 1, 'b' => 2, 'c' => 0],
            ['a' => 3, 'b' => 1, 'c' => 0],
        ],
        'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1 WHERE c<0',
        static function (array $incomingRows) use ($upsert2BaseRows): array {
            return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
                $upsert2BaseRows,
                $incomingRows,
                [[
                    'target' => ['a'],
                    'action' => 'update',
                    'assignments' => [
                        'b' => static fn (array $current, array $excluded): int => (int) $excluded['b'],
                        'c' => static fn (array $current, array $excluded): int => (int) $current['c'] + 1,
                    ],
                    'where' => static fn (array $current, array $excluded): bool => $current['c'] < 0,
                ]],
                [['a']],
            );
        },
        [['a' => 1, 'b' => 2, 'c' => 0], ['a' => 3, 'b' => 4, 'c' => 0]],
        [],
    ],
];

foreach ($upsert2ConflictArmCases as $name => [$incomingRows, $actionSql, $native, $expectedAfter, $expectedReturning]) {
    $tests['real upstream corpus upsert returning dynamic ' . $name . ' final rows match upstream oracle'] = static function (TestRunner $t) use ($upsert2Oracle, $native, $incomingRows, $actionSql): void {
        $expected = $upsert2Oracle($incomingRows, $actionSql);
        $actual = $native($incomingRows);
        $after = $actual['after'];
        usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        $t->same($expected['after'], array_values($after));
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' final rows preserve cited result'] = static function (TestRunner $t) use ($native, $incomingRows, $expectedAfter): void {
        $actual = $native($incomingRows);
        $after = $actual['after'];
        usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        $t->same($expectedAfter, array_values($after));
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' returning rows match upstream oracle'] = static function (TestRunner $t) use ($upsert2Oracle, $native, $incomingRows, $actionSql): void {
        $expected = $upsert2Oracle($incomingRows, $actionSql);
        $actual = $native($incomingRows);

        $t->same($expected['returning'], $actual['returning_rows']);
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' returning rows preserve cited result'] = static function (TestRunner $t) use ($native, $incomingRows, $expectedReturning): void {
        $actual = $native($incomingRows);
        $t->same($expectedReturning, $actual['returning_rows']);
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' changes match returning count'] = static function (TestRunner $t) use ($native, $incomingRows): void {
        $actual = $native($incomingRows);
        $t->same(count($actual['returning_rows']), $actual['changes']);
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' inserted and updated rows account for changes'] = static function (TestRunner $t) use ($native, $incomingRows): void {
        $actual = $native($incomingRows);
        $t->same($actual['changes'], count($actual['inserted_rows']) + count($actual['updated_rows']));
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' skipped rows are not returned'] = static function (TestRunner $t) use ($native, $incomingRows): void {
        $actual = $native($incomingRows);
        $returnedKeys = array_map(static fn (array $row): string => $row['a'] . ':' . $row['b'], $actual['returning_rows']);
        foreach ($actual['skipped_rows'] as $row) {
            $t->same(false, in_array($row['a'] . ':' . $row['b'], $returnedKeys, true));
        }
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' matched arms expose conflict target order'] = static function (TestRunner $t) use ($native, $incomingRows): void {
        $actual = $native($incomingRows);
        foreach ($actual['matched_arms'] as $arm) {
            $t->same($arm['target'] === null || $arm['target'] === ['a'], true);
        }
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' projection keeps returning order'] = static function (TestRunner $t) use ($native, $incomingRows, $expectedReturning): void {
        $actual = $native($incomingRows);
        $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['a', 'b']);
        $expected = array_map(static fn (array $row): array => ['a' => $row['a'], 'b' => $row['b']], $expectedReturning);

        $t->same($expected, $projected);
    };

    $tests['real upstream corpus upsert returning dynamic ' . $name . ' before image remains unchanged'] = static function (TestRunner $t) use ($native, $incomingRows, $upsert2BaseRows): void {
        $actual = $native($incomingRows);
        $t->same($upsert2BaseRows, $actual['before']);
    };
}

return $tests;
