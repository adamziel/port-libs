<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    return [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'state' => 'queued', 'bytes' => 11],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'state' => 'queued', 'bytes' => 21],
            ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'state' => 'queued', 'bytes' => 31],
            ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'state' => 'live', 'bytes' => 41],
            ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'state' => 'queued', 'bytes' => 51],
            ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'state' => 'queued', 'bytes' => 61],
            ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'alpha', 'state' => 'queued', 'bytes' => 71],
            ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'beta', 'state' => 'stale', 'bytes' => 81],
        ],
        'app_setting_targets' => [
            ['target_id' => 101, 'tenant_id' => 1, 'key_name' => 'alpha', 'batch_name' => 'cleanup', 'priority' => 10],
            ['target_id' => 102, 'tenant_id' => 1, 'key_name' => 'beta', 'batch_name' => 'cleanup', 'priority' => 60],
            ['target_id' => 103, 'tenant_id' => 1, 'key_name' => 'gamma', 'batch_name' => 'cleanup', 'priority' => 50],
            ['target_id' => 104, 'tenant_id' => 2, 'key_name' => 'alpha', 'batch_name' => 'migrate', 'priority' => 70],
            ['target_id' => 105, 'tenant_id' => 2, 'key_name' => 'beta', 'batch_name' => 'cleanup', 'priority' => 40],
            ['target_id' => 106, 'tenant_id' => 2, 'key_name' => 'gamma', 'batch_name' => 'cleanup', 'priority' => 30],
            ['target_id' => 107, 'tenant_id' => 3, 'key_name' => 'alpha', 'batch_name' => 'cleanup', 'priority' => 20],
            ['target_id' => 108, 'tenant_id' => 3, 'key_name' => 'beta', 'batch_name' => 'migrate', 'priority' => 80],
        ],
    ];
};

/**
 * @return list<int>
 */
$queuedIds = static fn (): array => [1, 2, 3, 5, 6, 7];

/**
 * @return list<int>
 */
$targetWindowIds = static function (int $limit, int $offset) use ($tables): array {
    $targets = array_values(array_filter(
        $tables()['app_setting_targets'],
        static fn (array $row): bool => $row['batch_name'] === 'cleanup',
    ));
    usort($targets, static fn (array $left, array $right): int => ($right['priority'] <=> $left['priority']) ?: ($left['target_id'] <=> $right['target_id']));
    $window = array_slice($targets, max(0, $offset), $limit);
    $wanted = [];
    foreach ($window as $row) {
        $wanted[$row['tenant_id'] . ':' . $row['key_name']] = true;
    }

    $ids = [];
    foreach ($tables()['app_settings'] as $row) {
        if (isset($wanted[$row['tenant_id'] . ':' . $row['key_name']])) {
            $ids[] = $row['setting_id'];
        }
    }

    return $ids;
};

$timediffLimitExpression = static function (int $value, int $seed): string {
    $day = str_pad((string) ($value + 1), 2, '0', STR_PAD_LEFT);
    $left = "'2024-01-{$day}'";
    $right = "'2024-01-01'";

    return match ($seed % 4) {
        0 => "length(timediff({$left}, {$right})) - 24 + {$value}",
        1 => "unicode(substr(timediff({$left}, {$right}), 11, 1)) - 48",
        2 => "instr(timediff({$left}, {$right}), ' 00:') - 12 + {$value}",
        default => "length(datetime({$right}, timediff({$left}, {$right}))) - 19 + {$value}",
    };
};

$tests = [];

$tests['rowvalue update delete limit timediff dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $t->contains('/test/timediff1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test');
    $t->contains('timediff-3.1', 'timediff-3.1 exact calendar-difference strings');
    $t->contains('timediff-4', 'timediff-4 datetime(right,timediff(left,right)) roundtrip');
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpr = $timediffLimitExpression($limit, $seed);
    $offsetExpr = $timediffLimitExpression($offset, $seed + 7);
    $sql = "UPDATE app_settings SET state = 'timediff_window' WHERE state = 'queued' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expectedIds = array_slice($queuedIds(), $offset, $limit);

    $tests[sprintf('rowvalue update delete limit timediff dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $expectedIds, $limit, $offset): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id');

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($expectedIds), 'timediff_window'), array_column($result['returning'], 'state'));
            $t->contains('timediff1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test');
        };
}

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpr = $timediffLimitExpression($limit, $seed + 3);
    $offsetExpr = $timediffLimitExpression($offset, $seed + 11);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'cleanup' ORDER BY priority DESC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $expectedIds = $targetWindowIds($limit, $offset);
    $remainingIds = array_values(array_diff(array_column($tables()['app_settings'], 'setting_id'), $expectedIds));

    $tests[sprintf('rowvalue update delete limit timediff dynamic delete tuple subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $expectedIds, $remainingIds): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id');

            $t->same($expectedIds, $result['plan']->selectedIds);
            $t->same($expectedIds, array_column($result['returning'], 'setting_id'));
            $t->same($remainingIds, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($expectedIds), count($result['returning']));
            $t->contains('e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test');
            $t->contains('timediff1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test');
        };
}

$cases = [
    'parse timediff length limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT length(timediff('2024-01-05', '2024-01-01')) - 21")['limit'],
        3,
    ],
    'parse timediff substring offset' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET unicode(substr(timediff('2024-01-03', '2024-01-01'), 11, 1)) - 48")['offset'],
        2,
    ],
    'parse timediff modifier roundtrip limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT length(datetime('2024-01-01', timediff('2024-01-04', '2024-01-01'))) - 16")['limit'],
        3,
    ],
    'direct timediff text is rejected as noninteger' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT timediff('2024-01-05', '2024-01-01')"),
        InvalidArgumentException::class,
    ],
    'timediff null argument is rejected through integer coercion' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT length(timediff(NULL, '2024-01-01'))"),
        InvalidArgumentException::class,
    ],
    'timediff invalid date is rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT length(timediff('bogus', '2024-01-01'))"),
        InvalidArgumentException::class,
    ],
    'timediff arity is rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT timediff('2024-01-01')"),
        InvalidArgumentException::class,
    ],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit timediff dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            $t->contains('timediff1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test');
            return;
        }

        $t->same($expected, $callback());
        $t->contains('timediff1.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test');
    };
}

return $tests;
