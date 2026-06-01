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

    foreach ([10, 20, 30] as $tenant) {
        foreach ($keys as $ordinal => $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => 'queued',
                'value_size' => 100 + $id,
            ];
            $targets[] = [
                'target_id' => 1000 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => 'status_batch',
                'priority' => 900 - ($id * 13) - $ordinal,
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

$statusExpression = static function (int $value, int $seed): string {
    if ($value === 0) {
        return match ($seed % 3) {
            0 => 'changes()',
            1 => 'total_changes()',
            default => 'last_insert_rowid()',
        };
    }

    return match ($seed % 7) {
        0 => 'changes() + ' . $value,
        1 => 'total_changes() + ' . $value,
        2 => 'last_insert_rowid() + ' . $value,
        3 => '(changes() IS 0) + ' . ($value - 1),
        4 => '(total_changes() = 0) + ' . ($value - 1),
        5 => '(last_insert_rowid() IS NOT 1) + ' . ($value - 1),
        default => '(changes() + total_changes() + last_insert_rowid()) + ' . $value,
    };
};

$tests = [];
$unique = [['tenant_id', 'key_name']];
$orderedIds = range(1, 12);

$tests['rowvalue update delete limit connection status dynamic cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/laststmtchanges.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/laststmtchanges.test');
    $t->contains('changes() remains constant within a statement', 'changes() remains constant within a statement and only updates when the statement finishes');
    $t->contains('/test/lastinsert.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/lastinsert.test');
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpression = $statusExpression($limit, $seed);
    $offsetExpression = $statusExpression($offset, $seed + 11);
    $sql = "UPDATE app_settings SET state = 'status_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY value_size ASC LIMIT {$limitExpression} OFFSET {$offsetExpression}";
    $selected = $window($orderedIds, $limit, $offset);
    $returning = $sourceOrder($selected);

    $tests[sprintf('rowvalue update delete limit connection status dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $unique, $sql, $limit, $offset, $selected, $returning): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', $unique);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'status_limited'), array_column($result['returning'], 'state'));
        };
}

for ($seed = 1; $seed <= 24; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpression = $statusExpression($limit, $seed + 5);
    $offsetExpression = $statusExpression($offset, $seed + 17);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'status_batch' ORDER BY priority DESC LIMIT {$offsetExpression}, {$limitExpression}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $selected = $sourceOrder($window($orderedIds, $limit, $offset));
    $remaining = $remainingIds($selected);

    $tests[sprintf('rowvalue update delete limit connection status dynamic delete tuple subquery seed %02d', $seed)] =
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
    'changes starts at zero' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes()')['limit'],
        0,
    ],
    'total changes starts at zero with arithmetic' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT total_changes() + 3')['limit'],
        3,
    ],
    'last insert rowid starts at zero with offset arithmetic' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 2 OFFSET last_insert_rowid() + 1')['offset'],
        1,
    ],
    'connection counters compose as arithmetic zeroes' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes() + total_changes() + last_insert_rowid()')['limit'],
        0,
    ],
    'changes predicate true for initial counter' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes() IS 0')['limit'],
        1,
    ],
    'total changes predicate false against one' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT total_changes() = 1')['limit'],
        0,
    ],
    'last insert rowid distinct from one' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT last_insert_rowid() IS DISTINCT FROM 1')['limit'],
        1,
    ],
    'changes arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT changes(1)'),
        InvalidArgumentException::class,
    ],
    'total changes arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT total_changes(1)'),
        InvalidArgumentException::class,
    ],
    'last insert rowid arity rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT last_insert_rowid(1)'),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit connection status dynamic ' . $name] =
        static function (TestRunner $t) use ($callback, $expected): void {
            if (is_string($expected) && is_a($expected, Throwable::class, true)) {
                $t->throws($expected, $callback);
                return;
            }

            $t->same($expected, $callback());
        };
}

return $tests;
