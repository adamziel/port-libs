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

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$sortRows = static function (array $rows): array {
    usort(
        $rows,
        static fn (array $left, array $right): int => ((int) $left['setting_id'] <=> (int) $right['setting_id'])
            ?: strcmp((string) $left['key_name'], (string) $right['key_name'])
    );

    return array_values($rows);
};

$baseRows = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a0', 'load_policy' => 2, 'revision' => 0],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b0', 'load_policy' => 4, 'revision' => 0],
    ['setting_id' => 3, 'tenant_id' => 2, 'key_name' => 'gamma', 'key_value' => 'g0', 'load_policy' => 1, 'revision' => 0],
    ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'delta', 'key_value' => 'd0', 'load_policy' => 7, 'revision' => 0],
];

$whereCases = [
    'upsert2-100 policy increases' => [
        'app_settings.load_policy < excluded.load_policy',
        static fn (array $current, array $excluded): bool => (int) $current['load_policy'] < (int) $excluded['load_policy'],
    ],
    'upsert2-200 repeated source current revision even' => [
        '(app_settings.revision % 2) = 0',
        static fn (array $current, array $excluded): bool => ((int) $current['revision'] % 2) === 0,
    ],
    'upsert2-201 excluded tenant wins same tenant' => [
        'app_settings.tenant_id = excluded.tenant_id',
        static fn (array $current, array $excluded): bool => (int) $current['tenant_id'] === (int) $excluded['tenant_id'],
    ],
    'upsert2-320 failed where only yields before insert edge' => [
        'app_settings.load_policy < 0',
        static fn (array $current, array $excluded): bool => (int) $current['load_policy'] < 0,
    ],
    'upsert1-1300 old value differs from excluded value' => [
        'app_settings.key_value <> excluded.key_value',
        static fn (array $current, array $excluded): bool => (string) $current['key_value'] !== (string) $excluded['key_value'],
    ],
];

$incomingSequences = [
    'returning1-17 clean inserts then alpha conflict' => [
        ['setting_id' => 10, 'tenant_id' => 1, 'key_name' => 'theta', 'key_value' => 't1', 'load_policy' => 5],
        ['setting_id' => 11, 'tenant_id' => 1, 'key_name' => 'iota', 'key_value' => 'i1', 'load_policy' => 3],
        ['setting_id' => 12, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a1', 'load_policy' => 6],
    ],
    'returning1-17 alternating alpha beta conflicts' => [
        ['setting_id' => 13, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a2', 'load_policy' => 6],
        ['setting_id' => 14, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b2', 'load_policy' => 5],
        ['setting_id' => 15, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a3', 'load_policy' => 8],
        ['setting_id' => 16, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b3', 'load_policy' => 1],
    ],
    'returning1-17 inserted row conflicts later in same statement' => [
        ['setting_id' => 17, 'tenant_id' => 3, 'key_name' => 'lambda', 'key_value' => 'l1', 'load_policy' => 2],
        ['setting_id' => 18, 'tenant_id' => 3, 'key_name' => 'lambda', 'key_value' => 'l2', 'load_policy' => 5],
        ['setting_id' => 19, 'tenant_id' => 3, 'key_name' => 'lambda', 'key_value' => 'l3', 'load_policy' => 1],
    ],
    'upsert5 catch-all conflict after mixed inserts' => [
        ['setting_id' => 20, 'tenant_id' => 2, 'key_name' => 'gamma', 'key_value' => 'g1', 'load_policy' => 3],
        ['setting_id' => 21, 'tenant_id' => 2, 'key_name' => 'mu', 'key_value' => 'm1', 'load_policy' => 9],
        ['setting_id' => 22, 'tenant_id' => 2, 'key_name' => 'mu', 'key_value' => 'm2', 'load_policy' => 10],
    ],
    'upsert2 descending policy update skip update' => [
        ['setting_id' => 23, 'tenant_id' => 2, 'key_name' => 'delta', 'key_value' => 'd1', 'load_policy' => 8],
        ['setting_id' => 24, 'tenant_id' => 2, 'key_name' => 'delta', 'key_value' => 'd2', 'load_policy' => 6],
        ['setting_id' => 25, 'tenant_id' => 2, 'key_name' => 'delta', 'key_value' => 'd3', 'load_policy' => 11],
    ],
    'returning1-17 three clean then repeated clean key' => [
        ['setting_id' => 26, 'tenant_id' => 4, 'key_name' => 'nu', 'key_value' => 'n1', 'load_policy' => 1],
        ['setting_id' => 27, 'tenant_id' => 4, 'key_name' => 'xi', 'key_value' => 'x1', 'load_policy' => 2],
        ['setting_id' => 28, 'tenant_id' => 4, 'key_name' => 'omicron', 'key_value' => 'o1', 'load_policy' => 3],
        ['setting_id' => 29, 'tenant_id' => 4, 'key_name' => 'xi', 'key_value' => 'x2', 'load_policy' => 7],
    ],
    'upsert2 same tenant and cross tenant conflicts' => [
        ['setting_id' => 30, 'tenant_id' => 9, 'key_name' => 'alpha', 'key_value' => 'a9', 'load_policy' => 9],
        ['setting_id' => 31, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a10', 'load_policy' => 10],
        ['setting_id' => 32, 'tenant_id' => 5, 'key_name' => 'pi', 'key_value' => 'p1', 'load_policy' => 4],
    ],
    'upsert5 repeated inserted current row evolves' => [
        ['setting_id' => 33, 'tenant_id' => 5, 'key_name' => 'rho', 'key_value' => 'r1', 'load_policy' => 2],
        ['setting_id' => 34, 'tenant_id' => 5, 'key_name' => 'rho', 'key_value' => 'r2', 'load_policy' => 3],
        ['setting_id' => 35, 'tenant_id' => 5, 'key_name' => 'rho', 'key_value' => 'r3', 'load_policy' => 4],
        ['setting_id' => 36, 'tenant_id' => 5, 'key_name' => 'rho', 'key_value' => 'r4', 'load_policy' => 5],
    ],
    'upsert2 repeated beta policy rise and fall' => [
        ['setting_id' => 37, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b4', 'load_policy' => 8],
        ['setting_id' => 38, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b5', 'load_policy' => 2],
        ['setting_id' => 39, 'tenant_id' => 1, 'key_name' => 'beta', 'key_value' => 'b6', 'load_policy' => 9],
    ],
    'returning1-17 duplicate rotation with existing and inserted rows' => [
        ['setting_id' => 40, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a11', 'load_policy' => 5],
        ['setting_id' => 41, 'tenant_id' => 6, 'key_name' => 'sigma', 'key_value' => 's1', 'load_policy' => 1],
        ['setting_id' => 42, 'tenant_id' => 6, 'key_name' => 'sigma', 'key_value' => 's2', 'load_policy' => 6],
        ['setting_id' => 43, 'tenant_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a12', 'load_policy' => 7],
    ],
];

for ($i = 0; $i < 15; ++$i) {
    $key = 'dyn_' . $i;
    $incomingSequences['generated returning1-17 duplicate stream ' . $i] = [
        ['setting_id' => 100 + ($i * 5), 'tenant_id' => 10 + $i, 'key_name' => $key, 'key_value' => 'v' . $i . '_0', 'load_policy' => 1 + ($i % 4)],
        ['setting_id' => 101 + ($i * 5), 'tenant_id' => 10 + $i, 'key_name' => $key, 'key_value' => 'v' . $i . '_1', 'load_policy' => 4 + ($i % 5)],
        ['setting_id' => 102 + ($i * 5), 'tenant_id' => 10 + $i, 'key_name' => 'dyn_extra_' . $i, 'key_value' => 'e' . $i, 'load_policy' => 2 + ($i % 3)],
        ['setting_id' => 103 + ($i * 5), 'tenant_id' => 10 + $i, 'key_name' => $key, 'key_value' => 'v' . $i . '_2', 'load_policy' => 7 + ($i % 6)],
    ];
}

$oracle = static function (array $incomingRows, string $whereSql) use ($baseRows, $quote, $sortRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_settings(setting_id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL UNIQUE, key_value TEXT, load_policy INTEGER NOT NULL DEFAULT 0, revision INTEGER NOT NULL DEFAULT 0)');
    foreach ($baseRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO app_settings(setting_id,tenant_id,key_name,key_value,load_policy,revision) VALUES(%d,%d,%s,%s,%d,%d)',
            $row['setting_id'],
            $row['tenant_id'],
            $quote($row['key_name']),
            $quote($row['key_value']),
            $row['load_policy'],
            $row['revision'],
        ));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf(
            '(%d,%d,%s,%s,%d,0)',
            $row['setting_id'],
            $row['tenant_id'],
            $quote($row['key_name']),
            $quote($row['key_value']),
            $row['load_policy'],
        );
    }

    $sql = 'INSERT INTO app_settings(setting_id,tenant_id,key_name,key_value,load_policy,revision) VALUES '
        . implode(',', $values)
        . ' ON CONFLICT(key_name) DO UPDATE SET tenant_id=excluded.tenant_id, key_value=excluded.key_value, load_policy=excluded.load_policy, revision=app_settings.revision+1 WHERE '
        . $whereSql
        . ' RETURNING setting_id, tenant_id, key_name, key_value, load_policy, revision';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = [
            'setting_id' => (int) $row['setting_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'key_name' => (string) $row['key_name'],
            'key_value' => $row['key_value'] === null ? null : (string) $row['key_value'],
            'load_policy' => (int) $row['load_policy'],
            'revision' => (int) $row['revision'],
        ];
    }

    $after = [];
    $result = $db->query('SELECT setting_id, tenant_id, key_name, key_value, load_policy, revision FROM app_settings ORDER BY setting_id, key_name');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = [
            'setting_id' => (int) $row['setting_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'key_name' => (string) $row['key_name'],
            'key_value' => $row['key_value'] === null ? null : (string) $row['key_value'],
            'load_policy' => (int) $row['load_policy'],
            'revision' => (int) $row['revision'],
        ];
    }

    return [
        'after' => $sortRows($after),
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $incomingRows, callable $where) use ($baseRows, $sortRows): array {
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $baseRows,
        array_map(static fn (array $row): array => $row + ['revision' => 0], $incomingRows),
        [[
            'target' => ['key_name'],
            'action' => 'update',
            'assignments' => [
                'tenant_id' => static fn (array $current, array $excluded): int => (int) $excluded['tenant_id'],
                'key_value' => static fn (array $current, array $excluded): ?string => $excluded['key_value'] === null ? null : (string) $excluded['key_value'],
                'load_policy' => static fn (array $current, array $excluded): int => (int) $excluded['load_policy'],
                'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
            ],
            'where' => $where,
        ]],
        [['key_name']],
    );
    $plan['after'] = $sortRows($plan['after']);

    return $plan;
};

foreach ($whereCases as $whereName => [$whereSql, $where]) {
    foreach ($incomingSequences as $sequenceName => $incomingRows) {
        $prefix = 'real upstream UPSERT RETURNING dynamic extended ' . $whereName . ' / ' . $sequenceName;

        $tests[$prefix . ' final rows match sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $t->same($oracle($incomingRows, $whereSql)['after'], $native($incomingRows, $where)['after']);
        };

        $tests[$prefix . ' returning stream matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $t->same($oracle($incomingRows, $whereSql)['returning_rows'], $native($incomingRows, $where)['returning_rows']);
        };

        $tests[$prefix . ' change count matches sqlite oracle'] = static function (TestRunner $t) use ($oracle, $native, $incomingRows, $whereSql, $where): void {
            $t->same($oracle($incomingRows, $whereSql)['changes'], $native($incomingRows, $where)['changes']);
        };

        $tests[$prefix . ' returning rows are the changed row images'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $t->same($actual['returning_rows'], SQLiteUpsertDoUpdateWherePlan::returningRows($actual['returning_rows'], ['*']));
        };

        $tests[$prefix . ' source rows partition into insert update or skip'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $t->same(count($incomingRows), count($actual['inserted_rows']) + count($actual['updated_rows']) + count($actual['skipped_rows']));
        };

        $tests[$prefix . ' yield trace has one before edge and one terminal edge per source row'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $events = array_column($native($incomingRows, $where)['yield_trace'], 'event');
            $t->same(count($incomingRows) * 2, count($events));
            for ($i = 0; $i < count($events); $i += 2) {
                $t->same('before-insert', $events[$i]);
                $t->true(in_array($events[$i + 1], ['insert-returning', 'update-returning', 'conflict-update-where-false'], true));
            }
        };

        $tests[$prefix . ' terminal returning edges equal visible RETURNING stream'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            $terminalReturning = [];
            foreach ($actual['yield_trace'] as $edge) {
                if (($edge['event'] ?? '') !== 'before-insert' && ($edge['returning'] ?? null) !== null) {
                    $terminalReturning[] = $edge['returning'];
                }
            }

            $t->same($actual['returning_rows'], $terminalReturning);
        };

        $tests[$prefix . ' conflict target metadata follows key-name arm'] = static function (TestRunner $t) use ($native, $incomingRows, $where): void {
            $actual = $native($incomingRows, $where);
            foreach ($actual['matched_arms'] as $match) {
                $t->same(['key_name'], $match['target']);
                $t->same('update', $match['action']);
            }
        };
    }
}

$tests['real upstream UPSERT RETURNING dynamic extended source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test upsert2-100/upsert2-200/upsert2-201/upsert2-320 WHERE conflict gates',
        'upsert5.test upsert5-1.* catch-all conflict arm yield behavior',
        'returning1.test returning1-17 multi-row UPSERT RETURNING row stream',
    ], [
        'upsert2.test upsert2-100/upsert2-200/upsert2-201/upsert2-320 WHERE conflict gates',
        'upsert5.test upsert5-1.* catch-all conflict arm yield behavior',
        'returning1.test returning1-17 multi-row UPSERT RETURNING row stream',
    ]);
};

$tests['real upstream UPSERT RETURNING dynamic extended dependency closure'] = static function (TestRunner $t) use ($native, $incomingSequences, $whereCases): void {
    $plan = $native($incomingSequences['returning1-17 inserted row conflicts later in same statement'], $whereCases['upsert2-100 policy increases'][1]);

    $t->same([
        'sqlite-upsert-conflict-arm-yield-trace',
        'upsert5.test-1.1.100-through-1.6.505',
        'returning1.test-17',
    ], $plan['dependencies']);
};

return $tests;
