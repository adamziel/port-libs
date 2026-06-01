<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    return [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'A', 'bytes' => 8, 'state' => 'live'],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'B', 'bytes' => 5, 'state' => 'live'],
            ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'C', 'bytes' => 13, 'state' => 'stale'],
            ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'D', 'bytes' => 21, 'state' => 'stale'],
            ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'E', 'bytes' => 3, 'state' => 'queued'],
            ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'F', 'bytes' => 34, 'state' => 'queued'],
            ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'G', 'bytes' => 2, 'state' => null],
            ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'H', 'bytes' => 55, 'state' => 'stale'],
        ],
        'app_setting_targets' => [
            ['target_id' => 1, 'tenant_id' => 1, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 40],
            ['target_id' => 2, 'tenant_id' => 1, 'key_name' => 'gamma', 'action' => 'refresh', 'priority' => 20],
            ['target_id' => 3, 'tenant_id' => 2, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 30],
            ['target_id' => 4, 'tenant_id' => 2, 'key_name' => 'gamma', 'action' => 'cleanup', 'priority' => 10],
            ['target_id' => 5, 'tenant_id' => 3, 'key_name' => 'beta', 'action' => 'cleanup', 'priority' => 50],
        ],
    ];
};

$execute = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

/**
 * @return list<int>
 */
$window = static function (array $orderedIds, int $limit, int $offset): array {
    $offset = max(0, $offset);
    if ($limit < 0) {
        return array_values(array_slice($orderedIds, $offset));
    }

    return array_values(array_slice($orderedIds, $offset, $limit));
};

/**
 * @return list<int>
 */
$sourceOrder = static function (array $ids, array $sourceIds): array {
    $selected = array_fill_keys(array_map('strval', $ids), true);

    return array_values(array_filter(
        $sourceIds,
        static fn (int $id): bool => isset($selected[(string) $id])
    ));
};

$lazyByBytes = [5, 2, 3, 6, 8];
$lazySource = [2, 3, 5, 6, 8];
$targetPriorityToSetting = [6, 3, 5, 2, 8];
$targetSource = [2, 3, 5, 6, 8];

$castCases = [
    'decimal precision numeric affinity' => [
        'limitExpr' => "CAST('3.0' AS DECIMAL(10,2))",
        'offsetExpr' => "CAST('1.0' AS BOOLEAN)",
        'limit' => 3,
        'offset' => 1,
    ],
    'unsigned big int truncates toward zero' => [
        'limitExpr' => "CAST('3.9' AS UNSIGNED BIG INT)",
        'offsetExpr' => "CAST('1.9' AS BIGINT)",
        'limit' => 3,
        'offset' => 1,
    ],
    'varchar type keeps numeric text lossless' => [
        'limitExpr' => "CAST('2.0' AS VARCHAR(10))",
        'offsetExpr' => "CAST('1.0' AS CHARACTER VARYING(8))",
        'limit' => 2,
        'offset' => 1,
    ],
    'double precision real affinity' => [
        'limitExpr' => "CAST('2.0' AS DOUBLE PRECISION)",
        'offsetExpr' => "CAST('1.0' AS FLOAT)",
        'limit' => 2,
        'offset' => 1,
    ],
    'default numeric string affinity' => [
        'limitExpr' => "CAST('4.0' AS STRING)",
        'offsetExpr' => "CAST('0.0' AS DECIMAL)",
        'limit' => 4,
        'offset' => 0,
    ],
    'floating point name follows integer affinity rule' => [
        'limitExpr' => "CAST('2.9' AS FLOATING POINT)",
        'offsetExpr' => "CAST('2.1' AS POINT)",
        'limit' => 2,
        'offset' => 2,
    ],
    'native character text affinity' => [
        'limitExpr' => "CAST('3.0' AS NATIVE CHARACTER(12))",
        'offsetExpr' => "CAST('2' AS CLOB)",
        'limit' => 3,
        'offset' => 2,
    ],
    'numeric fallback over custom type name' => [
        'limitExpr' => "CAST('1.0' AS APP_SETTING_COUNT)",
        'offsetExpr' => "CAST('3.0' AS SETTING_OFFSET)",
        'limit' => 1,
        'offset' => 3,
    ],
];

$tests = [];

$tests['rowvalue update delete limit cast affinity dynamic cites upstream cast and limit sources'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    $t->contains('e_expr-27.3', 'e_expr-27.3 type-name affinity determines CAST result affinity');
    $t->contains('/test/e_update.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test');
    $t->contains('/test/e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
};

foreach ($castCases as $label => $case) {
    $tests['rowvalue update delete limit cast affinity dynamic parse limit ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT ' . $case['limitExpr']);
            $t->same($case['limit'], $parsed['limit']);
        };

    $tests['rowvalue update delete limit cast affinity dynamic parse offset ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT 1 OFFSET ' . $case['offsetExpr']);
            $t->same($case['offset'], $parsed['offset']);
        };

    $tests['rowvalue update delete limit cast affinity dynamic update outer window ' . $label] =
        static function (TestRunner $t) use ($case, $execute, $window, $sourceOrder, $lazyByBytes, $lazySource): void {
            $sql = "UPDATE app_settings SET state = 'cast_affinity' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$case['limitExpr']} OFFSET {$case['offsetExpr']}";
            $selected = $window($lazyByBytes, $case['limit'], $case['offset']);
            $returning = $sourceOrder($selected, $lazySource);
            $result = $execute($sql);

            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($returning), 'cast_affinity'), array_column($result['returning'], 'state'));
        };

    $tests['rowvalue update delete limit cast affinity dynamic delete comma window ' . $label] =
        static function (TestRunner $t) use ($case, $execute, $window, $sourceOrder, $lazyByBytes, $lazySource): void {
            $sql = "DELETE FROM app_settings WHERE load_policy = 'lazy' RETURNING setting_id ORDER BY bytes ASC LIMIT {$case['offsetExpr']}, {$case['limitExpr']}";
            $selected = $window($lazyByBytes, $case['limit'], $case['offset']);
            $returning = $sourceOrder($selected, $lazySource);
            $result = $execute($sql);

            $t->same($selected, $result['plan']->selectedIds);
            $t->same($returning, array_column($result['returning'], 'setting_id'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $returning)), array_column($result['tables']['app_settings'], 'setting_id'));
        };

    $tests['rowvalue update delete limit cast affinity dynamic delete tuple subquery ' . $label] =
        static function (TestRunner $t) use ($case, $execute, $window, $sourceOrder, $targetPriorityToSetting, $targetSource): void {
            $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$case['limitExpr']} OFFSET {$case['offsetExpr']}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id LIMIT -1";
            $tupleIds = $window($targetPriorityToSetting, $case['limit'], $case['offset']);
            $expected = $sourceOrder($tupleIds, $targetSource);
            $result = $execute($sql);

            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(array_values(array_diff([1, 2, 3, 4, 5, 6, 7, 8], $expected)), array_column($result['tables']['app_settings'], 'setting_id'));
        };
}

$malformed = [
    'decimal nonintegral rejected' => "CAST('2.5' AS DECIMAL(10,2))",
    'double precision nonintegral rejected' => "CAST('2.5' AS DOUBLE PRECISION)",
    'varchar nonintegral numeric text rejected' => "CAST('2.5' AS VARCHAR(10))",
    'custom numeric nonintegral rejected' => "CAST('2.5' AS APP_SETTING_COUNT)",
    'blob affinity type rejected' => "CAST('2.0' AS shobblob_x)",
    'empty cast type rejected' => "CAST(2 AS)",
    'missing cast as rejected' => "CAST(2)",
];

foreach ($malformed as $label => $expression) {
    $tests['rowvalue update delete limit cast affinity dynamic malformed ' . $label] =
        static function (TestRunner $t) use ($expression): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => SQLiteUpdateDeleteReturningSql::parse('DELETE FROM app_settings RETURNING setting_id LIMIT ' . $expression)
            );
        };
}

return $tests;
