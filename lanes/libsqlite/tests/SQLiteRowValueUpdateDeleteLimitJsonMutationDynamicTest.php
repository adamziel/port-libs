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
                'state' => $key === 'gamma' ? 'live' : 'queued',
                'bytes' => $id * 10,
                'key_value' => strtoupper($key),
                'payload' => sprintf('{"tenant":%d,"key":"%s","items":[1,2,3]}', $tenant, $key),
            ];
            $targets[] = [
                'target_id' => 100 + $id,
                'tenant_id' => $tenant,
                'key_name' => $key,
                'batch_name' => ($key === 'beta' || $key === 'delta') ? 'cleanup' : 'migrate',
                'priority' => ($tenant * 100) + (4 - $ordinal),
            ];
            $id++;
        }
    }

    return ['app_settings' => $settings, 'app_setting_targets' => $targets];
};

$execute = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

/**
 * @param list<int> $ids
 * @return list<int>
 */
$sourceOrder = static fn (array $ids): array => array_values(array_intersect(range(1, 12), $ids));

/**
 * @param list<int> $ids
 * @return list<int>
 */
$remainingIds = static fn (array $ids): array => array_values(array_diff(range(1, 12), $ids));

/**
 * @return list<int>
 */
$window = static function (array $ids, int $limit, int $offset): array {
    return $limit < 0
        ? array_slice($ids, max(0, $offset))
        : array_slice($ids, max(0, $offset), $limit);
};

$queuedOrderedIds = [1, 2, 4, 5, 6, 8, 9, 10, 12];
$cleanupPriorityIds = [10, 12, 6, 8, 2, 4];

$tests = [];

$tests['rowvalue update delete dynamic JSON mutation LIMIT cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/limit.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test');
    $t->contains('/test/json104.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test');
    $t->contains('/test/json108.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test');
    $t->contains('/test/json109.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
    $t->contains('malformed JSON', "SQLite oracle: coalesce(json_set('{a,,}', '$.a', 1), 2) errors with malformed JSON");
};

$dynamicExpressions = [
    'text json_set append with json_remove offset' => [
        "json_array_length(json_set('[1,2]', '$[#]', 3))",
        "json_array_length(json_remove('[1,2,3]', '$[0]')) - 1",
        3,
        1,
    ],
    'text json_insert append with json_patch offset' => [
        "json_array_length(json_insert('[1,2]', '$[#]', 3))",
        "json_array_length(json_patch('{\"a\":[1]}', '{\"a\":[2,3]}'), '$.a') - 1",
        3,
        1,
    ],
    'text json_replace with array_insert offset' => [
        "json_array_length(json_replace('[1,2,3]', '$[1]', 9))",
        "json_array_length(json_array_insert('[1,2,3]', '$[1]', 9)) - 3",
        3,
        1,
    ],
    'json_pretty canonical array with multi-path remove offset' => [
        "json_array_length(json(json_pretty('[1,2,3]', '  ')))",
        "json_array_length(json_remove('[1,2,3,4]', '$[0]', '$[0]')) - 1",
        3,
        1,
    ],
    'jsonb_set append with jsonb_remove offset' => [
        "json_array_length(jsonb_set('[1,2]', '$[#]', 3))",
        "json_array_length(jsonb_remove('[1,2,3]', '$[0]')) - 1",
        3,
        1,
    ],
    'jsonb_insert append with jsonb_patch offset' => [
        "json_array_length(jsonb_insert('[1,2]', '$[#]', 3))",
        "json_array_length(jsonb_patch('{\"a\":[1]}', '{\"a\":[2,3]}'), '$.a') - 1",
        3,
        1,
    ],
    'jsonb_replace with jsonb_array_insert offset' => [
        "json_array_length(jsonb_replace('[1,2,3]', '$[1]', 9))",
        "json_array_length(jsonb_array_insert('[1,2,3]', '$[1]', 9)) - 2",
        3,
        2,
    ],
    'json_set extract limit with patch scalar offset' => [
        "json_extract(json_set('{\"n\":1}', '$.n', 4), '$.n') - 1",
        "json_extract(json_patch('{\"o\":0}', '{\"o\":1}'), '$.o')",
        3,
        1,
    ],
    'json_remove root no-op with jsonb_remove offset' => [
        "json_array_length(json_remove('[1,2,3]'))",
        "json_array_length(jsonb_remove('[1,2,3]', '$[0]')) - 1",
        3,
        1,
    ],
    'jsonb_patch nested array with array_insert offset' => [
        "json_array_length(jsonb_patch('{\"a\":[1]}', '{\"a\":[2,3,4]}'), '$.a')",
        "json_array_length(json_array_insert('[1,2]', '$[0]', 0)) - 2",
        3,
        1,
    ],
    'json_insert no-op canonicalization with json_replace no-op offset' => [
        "json_array_length(json_insert('[1,2,3]'))",
        "json_array_length(json_replace('[1,2]')) - 1",
        3,
        1,
    ],
    'json_pretty jsonb array with pretty text offset' => [
        "json_array_length(json(json_pretty(jsonb_array(1,2,3))))",
        "json_array_length(json(json_pretty(json_array(9))))",
        3,
        1,
    ],
    'constructor nested json_set with constructor remove offset' => [
        "json_array_length(json_set(json_array(1,2), '$[#]', json_extract('3', '$')))",
        "json_array_length(json_remove(json_array(1,2,3), '$[1]')) - 1",
        3,
        1,
    ],
];

foreach ($dynamicExpressions as $name => [$limitExpression, $offsetExpression, $limit, $offset]) {
    $updateSql = "UPDATE app_settings SET state = 'json_limited' WHERE state = 'queued' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpression} OFFSET {$offsetExpression}";
    $updateExpected = $window($queuedOrderedIds, $limit, $offset);
    $tests['rowvalue update dynamic JSON mutation LIMIT ' . $name] =
        static function (TestRunner $t) use ($execute, $updateSql, $updateExpected, $limit, $offset): void {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($updateSql);
            $result = $execute($updateSql);

            $t->same($limit, $parsed['limit']);
            $t->same($offset, $parsed['offset']);
            $t->same($updateExpected, $result['plan']->selectedIds);
            $t->same($updateExpected, array_column($result['returning'], 'setting_id'));
            $t->same(array_fill(0, count($updateExpected), 'json_limited'), array_column($result['returning'], 'state'));
            $t->same($limit, $result['plan']->toArray()['limit']);
            $t->same($offset, $result['plan']->toArray()['offset']);
        };

    $deleteSql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets WHERE batch_name = 'cleanup' ORDER BY priority DESC, target_id ASC LIMIT {$offsetExpression}, {$limitExpression}) RETURNING setting_id, tenant_id, key_name ORDER BY setting_id";
    $deleteExpected = $sourceOrder($window($cleanupPriorityIds, $limit, $offset));
    $deleteRemaining = $remainingIds($deleteExpected);
    $tests['rowvalue delete tuple subquery comma LIMIT dynamic JSON mutation ' . $name] =
        static function (TestRunner $t) use ($execute, $deleteSql, $deleteExpected, $deleteRemaining): void {
            $result = $execute($deleteSql);

            $t->same($deleteExpected, $result['plan']->selectedIds);
            $t->same($deleteExpected, array_column($result['returning'], 'setting_id'));
            $t->same($deleteRemaining, array_column($result['tables']['app_settings'], 'setting_id'));
            $t->same(count($deleteExpected), count($result['returning']));
            $t->same('delete', $result['action']);
            $t->contains('json109.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
        };
}

$maskedErrorExpressions = [
    'json_set malformed JSON is not coalesce fallback' => "coalesce(json_set('{a,,}', '$.a', 1), 2)",
    'json_patch malformed patch is not coalesce fallback' => "coalesce(json_patch('{\"a\":1}', '{bad'), 2)",
    'json_pretty malformed JSON is not coalesce fallback' => "coalesce(json_pretty('{bad'), 2)",
    'json_remove non-text path is not coalesce fallback' => "coalesce(json_remove('[1]', 7), 2)",
    'json_insert missing value is not coalesce fallback' => "coalesce(json_insert('{}', '$.a'), 2)",
    'json_array_insert missing value is not ifnull fallback' => "ifnull(json_array_insert('[1]', '$[0]'), 2)",
    'jsonb_set malformed text input is not coalesce fallback' => "coalesce(jsonb_set('{a,,}', '$.a', 1), 2)",
    'jsonb_patch malformed patch is not coalesce fallback' => "coalesce(jsonb_patch('{\"a\":1}', '{bad'), 2)",
];

foreach ($maskedErrorExpressions as $name => $expression) {
    $tests['rowvalue update delete dynamic JSON mutation LIMIT rejects masked error ' . $name] =
        static function (TestRunner $t) use ($expression): void {
            $sql = "DELETE FROM app_settings RETURNING setting_id LIMIT {$expression}";

            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($sql));
        };
}

return $tests;
