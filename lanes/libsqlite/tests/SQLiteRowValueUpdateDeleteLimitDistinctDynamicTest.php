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
    $sizes = [44, 12, 32, 28, 18, 50, 22, 40, 16, 46, 24, 36];
    $id = 1;

    foreach ([10, 20, 30] as $tenant) {
        foreach ($keys as $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => 'queued',
                'value_size' => $sizes[$id - 1],
            ];
            $targets[] = [
                'target_id' => 1000 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => 'distinct_batch',
                'priority' => 900 - ($id * 17),
            ];
            $id++;
        }
    }

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
 * @param list<int> $ids
 * @return list<int>
 */
$window = static function (array $ids, int $limit, int $offset): array {
    if ($limit < 0) {
        return array_values(array_slice($ids, max(0, $offset)));
    }

    return array_values(array_slice($ids, max(0, $offset), $limit));
};

$truthAdjustment = static fn (int $target): string => $target === 1 ? '' : ' + ' . ($target - 1);
$falseAdjustment = static fn (int $target): string => $target === 0 ? '' : ' + ' . $target;

$distinctLimitExpression = static function (int $value, int $seed) use ($truthAdjustment, $falseAdjustment): string {
    return match ($seed % 10) {
        0 => "('alpha' IS DISTINCT FROM 'beta')" . $truthAdjustment($value),
        1 => "('alpha' IS NOT DISTINCT FROM 'alpha')" . $truthAdjustment($value),
        2 => "(NULL IS DISTINCT FROM {$seed})" . $truthAdjustment($value),
        3 => "(NULL IS NOT DISTINCT FROM NULL)" . $truthAdjustment($value),
        4 => "((1, NULL) IS DISTINCT FROM (1, {$seed}))" . $truthAdjustment($value),
        5 => "((1, NULL) IS NOT DISTINCT FROM (1, NULL))" . $truthAdjustment($value),
        6 => "('alpha' == 'alpha')" . $truthAdjustment($value),
        7 => "('alpha' IS DISTINCT FROM 'alpha')" . $falseAdjustment($value),
        8 => "('alpha' IS NOT DISTINCT FROM 'beta')" . $falseAdjustment($value),
        default => "((1, NULL) IS DISTINCT FROM (1, NULL))" . $falseAdjustment($value),
    };
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$orderedBySize = [2, 9, 5, 7, 11, 4, 3, 12, 8, 1, 10, 6];
$targetPriorityIds = range(1, 12);

$tests['rowvalue update delete limit distinct dynamic cites upstream expression sources'] = static function (TestRunner $t): void {
    $t->contains('/test/expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->contains('expr-1.111b', 'expr-1.111b i1 IS NOT DISTINCT FROM i2');
    $t->contains('expr-1.119b', 'expr-1.119b i1 IS DISTINCT FROM i2');
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpression = $distinctLimitExpression($limit, $seed);
    $offsetExpression = $distinctLimitExpression($offset, $seed + 13);
    $sql = "UPDATE app_settings SET state = 'distinct_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitExpression} OFFSET {$offsetExpression}";
    $selected = $window($orderedBySize, $limit, $offset);
    $returning = $sourceOrder($selected);

    $tests[sprintf('rowvalue update delete limit distinct dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $limit, $offset, $selected, $returning): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'distinct_limited'), array_column($result['returning'], 'state'));
        };
}

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpression = $distinctLimitExpression($limit, $seed + 7);
    $offsetExpression = $distinctLimitExpression($offset, $seed + 19);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'distinct_batch' ORDER BY priority DESC LIMIT {$offsetExpression}, {$limitExpression}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $selected = $sourceOrder($window($targetPriorityIds, $limit, $offset));
    $remaining = $remainingIds($selected);

    $tests[sprintf('rowvalue update delete limit distinct dynamic delete tuple subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $selected, $remaining): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($selected, $result['plan']->selectedIds);
            $t->same($selected, array_column($result['returning'], 'setting_id'));
            $t->same($remaining, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($selected), count($result['returning']));
            $t->same('delete', $result['action']);
        };
}

$parseCases = [
    'scalar is distinct from true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' IS DISTINCT FROM 'beta'")['limit'],
        1,
    ],
    'scalar is distinct from false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' IS DISTINCT FROM 'alpha'")['limit'],
        0,
    ],
    'scalar is not distinct from true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' IS NOT DISTINCT FROM 'alpha'")['limit'],
        1,
    ],
    'scalar is not distinct from false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' IS NOT DISTINCT FROM 'beta'")['limit'],
        0,
    ],
    'null distinct from scalar true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT NULL IS DISTINCT FROM 1')['limit'],
        1,
    ],
    'null not distinct from null true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT NULL IS NOT DISTINCT FROM NULL')['limit'],
        1,
    ],
    'row value is distinct from true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT (1, NULL) IS DISTINCT FROM (1, 2)')['limit'],
        1,
    ],
    'row value is distinct from false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT (1, NULL) IS DISTINCT FROM (1, NULL)')['limit'],
        0,
    ],
    'row value is not distinct from true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('alpha', NULL) IS NOT DISTINCT FROM ('alpha', NULL)")['limit'],
        1,
    ],
    'equality alias true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' == 'alpha'")['limit'],
        1,
    ],
    'equality alias false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'alpha' == 'beta'")['limit'],
        0,
    ],
    'collated not distinct scalar true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'Alpha' COLLATE nocase IS NOT DISTINCT FROM 'alpha'")['limit'],
        1,
    ],
    'collated distinct scalar false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 'Alpha' IS DISTINCT FROM 'alpha' COLLATE nocase")['limit'],
        0,
    ],
    'row value arity mismatch rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT (1, 2) IS DISTINCT FROM (1)'),
        InvalidArgumentException::class,
    ],
    'row value scalar mismatch rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT (1, 2) IS NOT DISTINCT FROM 1'),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit distinct dynamic ' . $name] =
        static function (TestRunner $t) use ($callback, $expected): void {
            if (is_string($expected) && is_a($expected, Throwable::class, true)) {
                $t->throws($expected, $callback);
                return;
            }

            $t->same($expected, $callback());
        };
}

return $tests;
