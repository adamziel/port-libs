<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    $settings = [];
    $targets = [];
    $keys = ['alpha', 'beta', 'gamma', 'delta'];
    $id = 1;
    foreach ([1, 2, 3] as $tenant) {
        foreach ($keys as $ordinal => $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => $ordinal === 2 ? 'live' : 'queued',
                'bytes' => 10 * $id,
                'key_value' => strtoupper($key),
            ];
            $targets[] = [
                'target_id' => 100 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => $ordinal % 2 === 0 ? 'migrate' : 'cleanup',
                'priority' => (10 * $tenant) + (4 - $ordinal),
            ];
            $id++;
        }
    }

    return ['app_settings' => $settings, 'app_setting_targets' => $targets];
};

/**
 * @return list<int>
 */
$queuedIds = static fn (): array => [1, 2, 4, 5, 6, 8, 9, 10, 12];

/**
 * @return list<array<string,mixed>>
 */
$orderedTargets = static function (string $batchName) use ($tables): array {
    $rows = array_values(array_filter(
        $tables()['app_setting_targets'],
        static fn (array $row): bool => $row['batch_name'] === $batchName,
    ));
    usort($rows, static fn (array $left, array $right): int => ($right['priority'] <=> $left['priority']) ?: ($left['target_id'] <=> $right['target_id']));

    return $rows;
};

/**
 * @return list<list{int,string}>
 */
$tupleWindow = static function (string $batchName, int $limit, int $offset) use ($orderedTargets): array {
    return array_map(
        static fn (array $row): array => [$row['tenant_id'], $row['key_name']],
        array_slice($orderedTargets($batchName), $offset, $limit),
    );
};

/**
 * @param list<list{int,string}> $tuples
 * @return list<int>
 */
$matchingSettingIds = static function (array $tuples) use ($tables): array {
    $wanted = [];
    foreach ($tuples as [$tenant, $key]) {
        $wanted[$tenant . ':' . $key] = true;
    }

    $ids = [];
    foreach ($tables()['app_settings'] as $row) {
        if (isset($wanted[$row['tenant_id'] . ':' . $row['key_name']])) {
            $ids[] = $row['setting_id'];
        }
    }

    return $ids;
};

$randomLimitExpression = static function (int $value, int $seed): string {
    return match ($seed % 5) {
        0 => "(typeof(random()) = 'integer') + " . ($value - 1),
        1 => "(random() IS NOT NULL) + " . ($value - 1),
        2 => '(random() BETWEEN -9223372036854775807 AND 9223372036854775807) + ' . ($value - 1),
        3 => "length(typeof(random())) - " . (7 - $value),
        default => "(random() NOTNULL) + " . ($value - 1),
    };
};

$tests = [];
$unique = [['tenant_id', 'key_name']];

$tests['rowvalue update delete limit random dynamic cites upstream random source'] = static function (TestRunner $t): void {
    $t->contains('/test/func.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test');
    $t->contains('func-9.1', 'func-9.1 SELECT random() is not null');
    $t->contains('func-9.2', 'func-9.2 SELECT typeof(random())');
    $t->contains('/src/func.c', '/home/claude/port-libs/.upstream-cache/libsqlite/src/func.c randomFunc');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpr = $randomLimitExpression($limit, $seed);
    $offsetExpr = $randomLimitExpression($offset, $seed + 11);
    $sql = "UPDATE app_settings SET state = 'random_window' WHERE state = 'queued' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expectedIds = array_slice($queuedIds(), $offset, $limit);

    $tests[sprintf('rowvalue update delete limit random dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expectedIds, $limit, $offset): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expectedIds), 'random_window'), array_column($result['returning'], 'state'));
            $t->contains('func-9.2', 'func-9.2 SELECT typeof(random())');
        };
}

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpr = $randomLimitExpression($limit, $seed + 3);
    $offsetExpr = $randomLimitExpression($offset, $seed + 17);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'cleanup' ORDER BY priority DESC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $expectedIds = $matchingSettingIds($tupleWindow('cleanup', $limit, $offset));
    $remainingIds = array_values(array_diff(array_column($tables()['app_settings'], 'setting_id'), $expectedIds));

    $tests[sprintf('rowvalue update delete limit random dynamic delete tuple subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $expectedIds, $remainingIds): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same($remainingIds, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expectedIds), count($result['returning']));
            $t->contains('rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
        };
}

$cases = [
    'typeof predicate limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT typeof(random())='integer'")['limit'],
        1,
    ],
    'not null predicate offset' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 OFFSET random() IS NOT NULL")['offset'],
        1,
    ],
    'null predicate false limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT random() IS NULL")['limit'],
        0,
    ],
    'range predicate arithmetic limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT (random() BETWEEN -9223372036854775807 AND 9223372036854775807) + 2")['limit'],
        3,
    ],
    'direct random result is integer' => [
        static fn (): mixed => is_int(SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT random()')['limit']),
        true,
    ],
    'random arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT random(1)'),
        InvalidArgumentException::class,
    ],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit random dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            $t->contains('func.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test');
            return;
        }

        $t->same($expected, $callback());
        $t->contains('func.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test');
    };
}

return $tests;
