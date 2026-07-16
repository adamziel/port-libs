<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    $settings = [];
    $sizes = [44, 12, 32, 28, 18, 50, 22, 40, 16, 46, 24, 36];
    $id = 1;

    foreach ([10, 20, 30] as $tenant) {
        foreach (['alpha', 'beta', 'gamma', 'delta'] as $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => 'queued',
                'value_size' => $sizes[$id - 1],
            ];
            $id++;
        }
    }

    $targets = [
        ['target_id' => 1, 'tenant_id' => 10, 'key_name' => 'beta', 'group_name' => 'refresh', 'priority' => 40],
        ['target_id' => 2, 'tenant_id' => 10, 'key_name' => 'gamma', 'group_name' => 'refresh', 'priority' => 20],
        ['target_id' => 3, 'tenant_id' => 20, 'key_name' => 'alpha', 'group_name' => 'refresh', 'priority' => 50],
        ['target_id' => 4, 'tenant_id' => 20, 'key_name' => 'delta', 'group_name' => 'refresh', 'priority' => 10],
        ['target_id' => 5, 'tenant_id' => 30, 'key_name' => 'beta', 'group_name' => 'refresh', 'priority' => 30],
        ['target_id' => 6, 'tenant_id' => 30, 'key_name' => 'gamma', 'group_name' => 'archival', 'priority' => 60],
        ['target_id' => 7, 'tenant_id' => 20, 'key_name' => 'beta', 'group_name' => 'archival', 'priority' => 70],
        ['target_id' => 8, 'tenant_id' => 10, 'key_name' => 'delta', 'group_name' => 'archival', 'priority' => 80],
    ];

    return ['app_settings' => $settings, 'app_setting_targets' => $targets];
};

/**
 * @param list<int> $ids
 * @return list<int>
 */
$sourceOrder = static function (array $ids): array {
    $selected = array_fill_keys(array_map('strval', $ids), true);

    return array_values(array_filter(
        range(1, 12),
        static fn (int $id): bool => isset($selected[(string) $id])
    ));
};

/**
 * @param list<int> $ids
 * @return list<int>
 */
$remainingIds = static fn (array $ids): array => array_values(array_diff(range(1, 12), $ids));

/**
 * @return array{name:string,predicate:string,ids:list<int>}
 */
$singleAggregateCase = static function (int $seed): array {
    $cases = [
        [
            'name' => 'max tenant min key refresh',
            'predicate' => "(tenant_id, key_name) = (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [9],
        ],
        [
            'name' => 'min tenant max key refresh',
            'predicate' => "(tenant_id, key_name) = (SELECT min(tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [3],
        ],
        [
            'name' => 'max tuple refresh in',
            'predicate' => "(tenant_id, key_name) IN (SELECT max(tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [11],
        ],
        [
            'name' => 'min tuple refresh is',
            'predicate' => "(tenant_id, key_name) IS (SELECT min(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [1],
        ],
        [
            'name' => 'count star min key refresh',
            'predicate' => "(setting_id, key_name) = (SELECT count(*), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [5],
        ],
        [
            'name' => 'count distinct tenant max key refresh',
            'predicate' => "(setting_id, key_name) IN (SELECT count(DISTINCT tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
            'ids' => [3],
        ],
        [
            'name' => 'max tenant min key archival',
            'predicate' => "(tenant_id, key_name) = (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
            'ids' => [10],
        ],
        [
            'name' => 'min tenant min key archival',
            'predicate' => "(tenant_id, key_name) = (SELECT min(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
            'ids' => [2],
        ],
        [
            'name' => 'count star min tenant archival',
            'predicate' => "(setting_id, tenant_id) = (SELECT count(*), min(tenant_id) FROM app_setting_targets WHERE group_name = 'archival')",
            'ids' => [3],
        ],
        [
            'name' => 'count distinct key min tenant archival',
            'predicate' => "(setting_id, tenant_id) IN (SELECT count(DISTINCT key_name), min(tenant_id) FROM app_setting_targets WHERE group_name = 'archival')",
            'ids' => [3],
        ],
    ];

    return $cases[$seed % count($cases)];
};

$limitExpression = static fn (int $seed): string => match ($seed % 4) {
    0 => '1',
    1 => 'abs(-1)',
    2 => 'coalesce(NULL, 1)',
    default => '(2 BETWEEN 1 AND 3)',
};

$offsetExpression = static fn (int $seed): string => match ($seed % 3) {
    0 => '0',
    1 => 'nullif(0, 1)',
    default => "'0'",
};

$tests = [];
$unique = [['tenant_id', 'key_name']];

$tests['rowvalue update delete limit aggregate tuple dynamic cites upstream rowvalue aggregate sources'] = static function (TestRunner $t): void {
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
    $t->contains('rowvalue4-2.2.11', 'rowvalue4-2.2.11 (3, 3) = (SELECT max(a), max(b) FROM t2)');
    $t->contains('rowvalue4-2.2.12', 'rowvalue4-2.2.12 (3, 1) = (SELECT max(a), min(b) FROM t2)');
    $t->contains('/test/e_update.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test');
    $t->contains('/test/e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test');
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
};

for ($seed = 1; $seed <= 32; $seed++) {
    $case = $singleAggregateCase($seed);
    $limitSql = $limitExpression($seed);
    $offsetSql = $offsetExpression($seed);
    $sql = "UPDATE app_settings SET state = 'aggregate_tuple' WHERE {$case['predicate']} RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitSql} OFFSET {$offsetSql}";
    $expected = $sourceOrder($case['ids']);

    $tests[sprintf('rowvalue update delete limit aggregate tuple dynamic update %02d %s', $seed, $case['name'])] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expected): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same(1, $parsed['limit']);
            $t->same(0, $parsed['offset']);
            $t->same(1, $result['plan']->toArray()['limit']);
            $t->same(0, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expected), 'aggregate_tuple'), array_column($result['returning'], 'state'));
        };
}

for ($seed = 1; $seed <= 32; $seed++) {
    $case = $singleAggregateCase($seed + 4);
    $limitSql = $limitExpression($seed + 11);
    $offsetSql = $offsetExpression($seed + 17);
    $sql = "DELETE FROM app_settings WHERE {$case['predicate']} RETURNING setting_id, tenant_id, key_name ORDER BY value_size DESC LIMIT {$limitSql} OFFSET {$offsetSql}";
    $expected = $sourceOrder($case['ids']);
    $remaining = $remainingIds($expected);

    $tests[sprintf('rowvalue update delete limit aggregate tuple dynamic delete %02d %s', $seed, $case['name'])] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expected, $remaining): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same(1, $parsed['limit']);
            $t->same(0, $parsed['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same($remaining, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
            $t->same('delete', $result['action']);
        };
}

$compoundCases = [
    'union all aggregate refresh archival' => [
        "(tenant_id, key_name) IN (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh' UNION ALL SELECT min(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
        [2, 9],
    ],
    'union aggregate refresh archival' => [
        "(tenant_id, key_name) IN (SELECT min(tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh' UNION SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
        [3, 10],
    ],
    'intersect aggregate refresh archival' => [
        "(setting_id, tenant_id) IN (SELECT count(DISTINCT tenant_id), min(tenant_id) FROM app_setting_targets WHERE group_name = 'refresh' INTERSECT SELECT count(DISTINCT key_name), min(tenant_id) FROM app_setting_targets WHERE group_name = 'archival')",
        [3],
    ],
    'except aggregate refresh archival' => [
        "(tenant_id, key_name) IN (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh' EXCEPT SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
        [9],
    ],
];

foreach ($compoundCases as $name => [$predicate, $ids]) {
    $sql = "DELETE FROM app_settings WHERE {$predicate} RETURNING setting_id ORDER BY setting_id LIMIT 10 OFFSET 0";
    $expected = $sourceOrder($ids);

    $tests['rowvalue update delete limit aggregate tuple dynamic compound ' . $name] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expected): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$returningCases = [
    'equality match' => [
        'setting_id = 9',
        "(tenant_id, key_name) = (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
        1,
    ],
    'in match' => [
        'setting_id = 3',
        "(tenant_id, key_name) IN (SELECT min(tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
        1,
    ],
    'count star match' => [
        'setting_id = 5',
        "(setting_id, key_name) = (SELECT count(*), min(key_name) FROM app_setting_targets WHERE group_name = 'refresh')",
        1,
    ],
    'count distinct match' => [
        'setting_id = 3',
        "(setting_id, tenant_id) = (SELECT count(DISTINCT key_name), min(tenant_id) FROM app_setting_targets WHERE group_name = 'archival')",
        1,
    ],
    'empty aggregate equality unknown' => [
        'setting_id = 1',
        "(tenant_id, key_name) = (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'missing')",
        null,
    ],
    'empty aggregate is false' => [
        'setting_id = 1',
        "(tenant_id, key_name) IS (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'missing')",
        0,
    ],
    'empty aggregate is not true' => [
        'setting_id = 1',
        "(tenant_id, key_name) IS NOT (SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'missing')",
        1,
    ],
    'compound aggregate match' => [
        'setting_id = 10',
        "(tenant_id, key_name) IN (SELECT min(tenant_id), max(key_name) FROM app_setting_targets WHERE group_name = 'refresh' UNION SELECT max(tenant_id), min(key_name) FROM app_setting_targets WHERE group_name = 'archival')",
        1,
    ],
];

foreach ($returningCases as $name => [$where, $expression, $expected]) {
    $sql = "UPDATE app_settings SET state = 'checked' WHERE {$where} RETURNING setting_id, {$expression} AS aggregate_match LIMIT 1";

    $tests['rowvalue update delete limit aggregate tuple dynamic returning ' . $name] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expected): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same([$expected], array_column($result['returning'], 'aggregate_match'));
            $t->same(1, count($result['returning']));
        };
}

$malformedCases = [
    'single aggregate column rejected' => "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT max(tenant_id) FROM app_setting_targets) RETURNING setting_id LIMIT 1",
    'max star rejected' => "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT max(*), min(key_name) FROM app_setting_targets) RETURNING setting_id LIMIT 1",
    'count missing argument rejected' => "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT count(), min(key_name) FROM app_setting_targets) RETURNING setting_id LIMIT 1",
];

foreach ($malformedCases as $name => $sql) {
    $tests['rowvalue update delete limit aggregate tuple dynamic malformed ' . $name] =
        static function (TestRunner $t) use ($tables, $unique, $sql): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique)
            );
        };
}

return $tests;
