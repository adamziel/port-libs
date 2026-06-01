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
    $bytes = [120, 10, 90, 20, 80, 30, 70, 40, 60, 50, 110, 100];
    $id = 1;

    foreach ([1, 2, 3] as $tenant) {
        foreach ($keys as $key) {
            $settings[] = [
                'setting_id' => $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'state' => 'queued',
                'bytes' => $bytes[$id - 1],
            ];
            $targets[] = [
                'target_id' => 100 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => 'collate_batch',
                'priority' => 500 - ($id * 13),
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

$truthAdjustment = static function (int $target): string {
    if ($target === 1) {
        return '';
    }

    return $target > 1 ? ' + ' . ($target - 1) : ' - ' . (1 - $target);
};

$falseAdjustment = static fn (int $target): string => $target === 0 ? '' : ' + ' . $target;

$collatedLimitExpression = static function (int $value, int $seed) use ($truthAdjustment, $falseAdjustment): string {
    return match ($seed % 8) {
        0 => "('0' || '{$value}') COLLATE nocase",
        1 => "substr('x{$value}', 2) COLLATE rtrim",
        2 => "('ALPHA' = 'alpha' COLLATE nocase)" . $truthAdjustment($value),
        3 => "('ALPHA' = 'alpha' COLLATE binary)" . $falseAdjustment($value),
        4 => "('beta' COLLATE nocase IN ('ALPHA', 'BETA'))" . $truthAdjustment($value),
        5 => "('beta' IN ('ALPHA', 'BETA' COLLATE nocase))" . $truthAdjustment($value),
        6 => "('bravo  ' = 'bravo' COLLATE rtrim)" . $truthAdjustment($value),
        default => "('b' BETWEEN 'A' AND 'C' COLLATE nocase)" . $truthAdjustment($value),
    };
};

$tests = [];
$orderedByBytes = [2, 4, 6, 8, 10, 9, 7, 5, 3, 12, 11, 1];
$targetPriorityIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

$tests['rowvalue update delete limit collate dynamic cites upstream expression and rowvalue sources'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('e_expr-9.10', "e_expr-9.10 SELECT 'abcd' = 'ABCD' COLLATE nocase");
    $t->contains('e_expr-9.11', "e_expr-9.11 SELECT ('abcd' = 'ABCD') COLLATE nocase");
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 4) + 1;
    $offset = ($seed + 1) % 3;
    $limitExpression = $collatedLimitExpression($limit, $seed);
    $offsetExpression = $collatedLimitExpression($offset, $seed + 11);
    $sql = "UPDATE app_settings SET state = 'collated_limit' WHERE state = 'queued' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpression} OFFSET {$offsetExpression}";
    $selected = $window($orderedByBytes, $limit, $offset);
    $returning = $sourceOrder($selected);

    $tests[sprintf('rowvalue update delete limit collate dynamic update outer window seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $limit, $offset, $selected, $returning): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'collated_limit'), array_column($result['returning'], 'state'));
        };
}

for ($seed = 1; $seed <= 32; $seed++) {
    $limit = ($seed % 3) + 1;
    $offset = ($seed + 2) % 4;
    $limitExpression = $collatedLimitExpression($limit, $seed + 5);
    $offsetExpression = $collatedLimitExpression($offset, $seed + 17);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'collate_batch' ORDER BY priority DESC LIMIT {$offsetExpression}, {$limitExpression}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $selected = $sourceOrder($window($targetPriorityIds, $limit, $offset));
    $remaining = $remainingIds($selected);

    $tests[sprintf('rowvalue update delete limit collate dynamic delete tuple subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($tables, $sql, $selected, $remaining): void {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

            $t->same($selected, $result['plan']->selectedIds);
            $t->same($selected, array_column($result['returning'], 'setting_id'));
            $t->same($remaining, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($selected), count($result['returning']));
            $t->same('delete', $result['action']);
        };
}

$parseCases = [
    'value postfix collation parses as same integer' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 COLLATE nocase")['limit'],
        2,
    ],
    'unknown value collation is a no-op outside comparison' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 COLLATE app_custom")['limit'],
        2,
    ],
    'nocase equality predicate becomes integer one' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('alpha' = 'ALPHA' COLLATE nocase)")['limit'],
        1,
    ],
    'binary equality predicate remains integer zero' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('alpha' = 'ALPHA' COLLATE binary)")['limit'],
        0,
    ],
    'rtrim equality predicate trims trailing spaces' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('alpha   ' = 'alpha' COLLATE rtrim)")['limit'],
        1,
    ],
    'nocase between predicate becomes integer one' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('b' BETWEEN 'A' AND 'C' COLLATE nocase)")['limit'],
        1,
    ],
    'left operand collate controls in predicate' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('beta' COLLATE nocase IN ('ALPHA', 'BETA'))")['limit'],
        1,
    ],
    'right list collate controls in predicate' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('beta' IN ('ALPHA', 'BETA' COLLATE nocase))")['limit'],
        1,
    ],
    'parenthesized comparison collate does not change inner comparison' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT (('alpha' = 'ALPHA') COLLATE nocase)")['limit'],
        0,
    ],
    'unsupported comparison collation is rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT ('alpha' = 'ALPHA' COLLATE app_custom)"),
        InvalidArgumentException::class,
    ],
    'missing collation name is rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 2 COLLATE'),
        InvalidArgumentException::class,
    ],
    'compound collation name is rejected' => [
        static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 2 COLLATE no case'),
        InvalidArgumentException::class,
    ],
];

foreach ($parseCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete limit collate dynamic ' . $name] =
        static function (TestRunner $t) use ($callback, $expected): void {
            if (is_string($expected) && is_a($expected, Throwable::class, true)) {
                $t->throws($expected, $callback);
                return;
            }

            $t->same($expected, $callback());
        };
}

return $tests;
