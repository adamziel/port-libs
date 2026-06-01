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
                'batch_name' => 'exists_batch',
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

$existsLimitExpression = static function (int $value, int $seed) use ($truthAdjustment, $falseAdjustment): string {
    return match ($seed % 10) {
        0 => 'EXISTS(SELECT 1)' . $truthAdjustment($value),
        1 => 'EXISTS(SELECT NULL)' . $truthAdjustment($value),
        2 => 'EXISTS(SELECT 24, 25)' . $truthAdjustment($value),
        3 => 'EXISTS(SELECT 1 WHERE 1)' . $truthAdjustment($value),
        4 => "EXISTS(SELECT 1 WHERE '2')" . $truthAdjustment($value),
        5 => '(NOT EXISTS(SELECT 1 WHERE 0))' . $truthAdjustment($value),
        6 => 'EXISTS(SELECT 1 WHERE 0)' . $falseAdjustment($value),
        7 => 'EXISTS(SELECT NULL WHERE 1=2)' . $falseAdjustment($value),
        8 => 'EXISTS(SELECT 24, 46, 89 WHERE NULL)' . $falseAdjustment($value),
        default => '(NOT EXISTS(SELECT 1))' . $falseAdjustment($value),
    };
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$orderedBySize = [2, 9, 5, 7, 11, 4, 3, 12, 8, 1, 10, 6];
$targetPriorityIds = range(1, 12);

$tests['rowvalue update delete limit exists dynamic cites upstream expression sources'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('e_expr-19.2.1', 'e_expr-19.2.1 SELECT EXISTS ( SELECT 24 )');
    $t->contains('e_expr-19.2.2', 'e_expr-19.2.2 SELECT EXISTS ( SELECT NULL )');
    $t->contains('e_expr-19.2.5', 'e_expr-19.2.5 SELECT EXISTS ( SELECT 24, 25 )');
    $t->contains('e_expr-19.3.1', 'e_expr-19.3.1 SELECT EXISTS ( SELECT 24 WHERE 0)');
    $t->contains('/test/e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test UPDATE_DELETE_LIMIT expression admission');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test row-value tuple subquery LIMIT selection');
};

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpression = $existsLimitExpression($limit, $seed);
    $offsetExpression = $existsLimitExpression($offset, $seed + 13);
    $sql = "UPDATE app_settings SET state = 'exists_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitExpression} OFFSET {$offsetExpression}";
    $selected = $window($orderedBySize, $limit, $offset);
    $returning = $sourceOrder($selected);

    $tests[sprintf('rowvalue update delete limit exists dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $limit, $offset, $selected, $returning): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'exists_limited'), array_column($result['returning'], 'state'));
        };
}

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpression = $existsLimitExpression($limit, $seed + 7);
    $offsetExpression = $existsLimitExpression($offset, $seed + 19);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'exists_batch' ORDER BY priority DESC LIMIT {$offsetExpression}, {$limitExpression}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $selected = $sourceOrder($window($targetPriorityIds, $limit, $offset));
    $remaining = $remainingIds($selected);

    $tests[sprintf('rowvalue update delete limit exists dynamic delete tuple subquery seed %02d', $seed)] =
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
    'scalar exists select one true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1)')['limit'],
        1,
    ],
    'scalar exists select null true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT NULL)')['limit'],
        1,
    ],
    'scalar exists multi-column true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 24, 25)')['limit'],
        1,
    ],
    'scalar exists where one true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT NULL WHERE 1)')['limit'],
        1,
    ],
    'scalar exists where text numeric true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT NULL WHERE '2')")['limit'],
        1,
    ],
    'scalar exists where zero false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE 0)')['limit'],
        0,
    ],
    'scalar exists where null false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE NULL)')['limit'],
        0,
    ],
    'scalar exists where comparison false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE 1=2)')['limit'],
        0,
    ],
    'scalar exists where comparison true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE 2>1)')['limit'],
        1,
    ],
    'scalar not exists select one false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT NOT EXISTS(SELECT 1)')['limit'],
        0,
    ],
    'scalar not exists where zero true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT NOT EXISTS(SELECT 1 WHERE 0)')['limit'],
        1,
    ],
    'scalar exists arithmetic limit' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE 0) + 3')['limit'],
        3,
    ],
    'scalar exists offset arithmetic' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET (NOT EXISTS(SELECT 1 WHERE 0)) + 1')['offset'],
        2,
    ],
    'scalar exists parenthesized where false arithmetic' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT (EXISTS(SELECT 1 WHERE 0)) + 4')['limit'],
        4,
    ],
    'scalar nested not exists where true false' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT NOT (EXISTS(SELECT 1 WHERE 1))')['limit'],
        0,
    ],
    'scalar exists where collated equality true' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE 'Alpha' COLLATE nocase = 'alpha')")['limit'],
        1,
    ],
    'malformed empty select rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT)'),
        InvalidArgumentException::class,
    ],
    'malformed empty where rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 WHERE)'),
        InvalidArgumentException::class,
    ],
    'unsupported from select rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 FROM app_settings)'),
        InvalidArgumentException::class,
    ],
    'unsupported from where select rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT EXISTS(SELECT 1 FROM app_settings WHERE 0)'),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit exists dynamic ' . $name] =
        static function (TestRunner $t) use ($callback, $expected): void {
            if (is_string($expected) && is_a($expected, Throwable::class, true)) {
                $t->throws($expected, $callback);
                return;
            }

            $t->same($expected, $callback());
        };
}

return $tests;
