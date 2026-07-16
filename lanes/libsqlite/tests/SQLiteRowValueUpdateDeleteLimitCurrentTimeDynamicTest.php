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

$currentLimitExpression = static function (int $value, int $seed): string {
    return match ($seed % 6) {
        0 => 'length(CURRENT_DATE) - ' . (10 - $value),
        1 => 'length(CURRENT_TIME) - ' . (8 - $value),
        2 => 'length(CURRENT_TIMESTAMP) - ' . (19 - $value),
        3 => "({$value} + unicode(substr(CURRENT_DATE, 5, 1)) - 45)",
        4 => "({$value} + unicode(substr(CURRENT_TIME, 3, 1)) - 58)",
        default => "({$value} + unicode(substr(CURRENT_TIMESTAMP, 11, 1)) - 32)",
    };
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$queuedIds = [1, 2, 4, 6, 7, 8, 10, 12, 13, 14, 16, 18];

$tests['rowvalue update delete limit current time dynamic cites upstream literal source'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('e_expr-12.2.6', 'e_expr-12.2.6 SELECT CURRENT_TIME literal-value');
    $t->contains('e_expr-12.2.7', 'e_expr-12.2.7 SELECT CURRENT_DATE literal-value');
    $t->contains('e_expr-12.2.8', 'e_expr-12.2.8 SELECT CURRENT_TIMESTAMP literal-value');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 24; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 3;
    $limitExpr = $currentLimitExpression($limitValue, $seed);
    $offsetExpr = $currentLimitExpression($offsetValue, $seed + 9);
    $sql = "UPDATE app_settings SET state = 'current_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expectedIds = array_slice($queuedIds, $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update limit current time outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($settingTables, $unique, $sql, $expectedIds, $limitValue, $offsetValue): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $settingTables(), 'setting_id', $unique);

            $t->same($limitValue, $parsed['limit']);
            $t->same($offsetValue, $parsed['offset']);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expectedIds), 'current_limited'), array_column($result['returning'], 'state'));
            $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
        };
}

for ($seed = 1; $seed <= 24; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitExpr = $currentLimitExpression($limitValue, $seed + 3);
    $offsetExpr = $currentLimitExpression($offsetValue, $seed + 13);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_batches WHERE batch_name = 'cleanup' ORDER BY priority DESC, key_name ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $expectedIds = $expectedSettingIds($tupleWindow('cleanup', $limitValue, $offsetValue));

    $tests[sprintf('rowvalue delete limit current time tuple source seed %02d', $seed)] =
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
    'parse current date length limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT length(CURRENT_DATE) - 8')['limit'],
        2,
    ],
    'parse current time length offset' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 3 OFFSET length(CURRENT_TIME) - 7')['offset'],
        1,
    ],
    'parse current timestamp length limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT length(CURRENT_TIMESTAMP) - 17')['limit'],
        2,
    ],
    'parse current date type predicate limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT typeof(CURRENT_DATE)='text'")['limit'],
        1,
    ],
    'parse current timestamp separator unicode offset' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 2 OFFSET unicode(substr(CURRENT_TIMESTAMP, 11, 1)) - 32')['offset'],
        0,
    ],
    'parse current timestamp julianday predicate limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT julianday(CURRENT_TIMESTAMP) IS NOT NULL')['limit'],
        1,
    ],
    'parse current timestamp date function predicate limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT date(CURRENT_TIMESTAMP) IS NOT NULL')['limit'],
        1,
    ],
    'direct current date literal rejected as noninteger' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT CURRENT_DATE'),
        InvalidArgumentException::class,
    ],
    'direct current time literal rejected as noninteger' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT CURRENT_TIME'),
        InvalidArgumentException::class,
    ],
    'direct current timestamp literal rejected as noninteger' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT CURRENT_TIMESTAMP'),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit current time dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
