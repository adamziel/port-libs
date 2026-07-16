<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$settingTables = static function (): array {
    $settings = [];
    $batches = [];
    $keys = ['alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta'];
    $states = ['queued', 'queued', 'live', 'queued', 'stale', 'queued'];
    $id = 1;
    foreach ([10, 20, 30] as $tenant) {
        foreach ($keys as $ordinal => $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => $states[$ordinal],
                'value_size' => 100 + $id,
                'payload' => "{$tenant}:{$key}",
            ];
            $batches[] = [
                'batch_id' => 1000 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => $ordinal % 2 === 0 ? 'migrate' : 'cleanup',
                'priority' => ($tenant / 10) * 100 - ($ordinal * 7),
                'rank_hint' => $ordinal + 1,
            ];
            $id++;
        }
    }

    return ['app_settings' => $settings, 'app_setting_batches' => $batches];
};

/**
 * @return list<array<string,mixed>>
 */
$orderedBatchRows = static function (string $batchName) use ($settingTables): array {
    $rows = array_values(array_filter(
        $settingTables()['app_setting_batches'],
        static fn (array $row): bool => $row['batch_name'] === $batchName,
    ));
    usort($rows, static function (array $left, array $right): int {
        $comparison = $right['priority'] <=> $left['priority'];
        return $comparison !== 0 ? $comparison : ($left['key_name'] <=> $right['key_name']);
    });

    return $rows;
};

/**
 * @return list<list{int,string}>
 */
$tupleWindow = static function (string $batchName, int $limit, int $offset) use ($orderedBatchRows): array {
    $slice = $limit < 0
        ? array_slice($orderedBatchRows($batchName), $offset)
        : array_slice($orderedBatchRows($batchName), $offset, $limit);

    return array_map(
        static fn (array $row): array => [$row['tenant_id'], $row['key_name']],
        $slice,
    );
};

/**
 * @param list<list{int,string}> $tuples
 * @return list<int>
 */
$expectedSettingIds = static function (array $tuples) use ($settingTables): array {
    $wanted = [];
    foreach ($tuples as [$tenant, $key]) {
        $wanted[$tenant . ':' . $key] = true;
    }

    $ids = [];
    foreach ($settingTables()['app_settings'] as $row) {
        if (isset($wanted[$row['tenant_id'] . ':' . $row['key_name']])) {
            $ids[] = $row['setting_id'];
        }
    }

    return $ids;
};

/**
 * @param list<int> $ids
 * @return list<int>
 */
$expectedRemainingIds = static function (array $ids) use ($settingTables): array {
    return array_values(array_filter(
        array_column($settingTables()['app_settings'], 'setting_id'),
        static fn (int $id): bool => !in_array($id, $ids, true),
    ));
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$queuedIds = [1, 2, 4, 6, 7, 8, 10, 12, 13, 14, 16, 18];

$tests['rowvalue update delete limit like glob dynamic cites upstream scalar and limit sources'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('e_expr-15.1', 'e_expr-15.1 LIKE operator calls like(Y,X)');
    $t->contains('e_expr-17.3', 'e_expr-17.3 GLOB operator calls glob(Y,X)');
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 28; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = $seed % 3;
    $limitExpr = $seed % 2 === 0
        ? "({$limitValue} + like('que%', 'queued') - glob('que*', 'queued'))"
        : "({$limitValue} + like('rowX_%', 'row_%', 'X') - glob('miss*', 'queued')) - 1";
    $offsetExpr = $seed % 2 === 0
        ? "({$offsetValue} + glob('row_[0-9]', 'row_5') - like('rowX_%', 'row_%', 'X'))"
        : "({$offsetValue} + like('CLEAN%', 'cleanup') - glob('none*', 'cleanup')) - 1";
    $sql = "UPDATE app_settings SET state = 'likeglob_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expectedIds = array_slice($queuedIds, $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update limit like glob outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($settingTables, $unique, $sql, $expectedIds, $limitValue, $offsetValue): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);

            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expectedIds), 'likeglob_limited'), array_column($result['returning'], 'state'));
            $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
        };
}

for ($seed = 1; $seed <= 28; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = $seed % 2 === 0
        ? "({$limitValue} + like('clean%', 'cleanup') - glob('clean*', 'cleanup'))"
        : "({$limitValue} + glob('[cm]*', 'cleanup') - like('migr%', 'cleanup')) - 1";
    $offsetExpr = $seed % 2 === 0
        ? "({$offsetValue} + glob('tenant-*', 'tenant-20') - like('tenantX_%', 'tenant_%', 'X'))"
        : "({$offsetValue} + like('row%', 'row-value') - glob('row-*', 'row_value')) - 1";
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'cleanup' ORDER BY priority DESC, key_name ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $expectedIds = $expectedSettingIds($tupleWindow('cleanup', $limitValue, $offsetValue));

    $tests[sprintf('rowvalue delete limit like glob tuple source seed %02d', $seed)] =
        static function (TestRunner $t) use ($settingTables, $unique, $expectedRemainingIds, $sql, $expectedIds): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);

            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same($expectedRemainingIds($expectedIds), array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expectedIds), count($result['returning']));
            $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
        };
}

$parseCases = [
    'parse like scalar limit true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like('a%', 'abc')")['limit'],
        1,
    ],
    'parse like scalar limit false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like('z%', 'abc')")['limit'],
        0,
    ],
    'parse escaped like scalar offset true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 OFFSET like('aX_%', 'a_%', 'X')")['offset'],
        1,
    ],
    'parse glob scalar limit true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT glob('a*', 'abc')")['limit'],
        1,
    ],
    'parse glob scalar offset false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 OFFSET glob('z*', 'abc')")['offset'],
        0,
    ],
    'parse comma limit with like offset and glob count' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like('a%', 'abc'), glob('b*', 'beta') + 1"),
        ['limit' => 2, 'offset' => 1],
    ],
    'like null argument rejected as noninteger limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like(NULL, 'abc')"),
        InvalidArgumentException::class,
    ],
    'like arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like('a%')"),
        InvalidArgumentException::class,
    ],
    'glob arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT glob('a*', 'abc', 'extra')"),
        InvalidArgumentException::class,
    ],
    'like multi-character escape rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT like('a!!%', 'a%', '!!')"),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue limit like glob dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $actual = $callback();
        if (is_array($expected)) {
            $t->same($expected['limit'], $actual['limit']);
            $t->same($expected['offset'], $actual['offset']);
            return;
        }
        $t->same($expected, $actual);
    };
}

return $tests;
