<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveAffinityLimitPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'rank_value' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'rank_value' => '1'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'rank_value' => 2],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'no', 'rank_value' => '2'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_cache', 'autoload' => 'no', 'rank_value' => 3],
    ['option_id' => 6, 'option_name' => 'plugin_cache_text', 'autoload' => 'no', 'rank_value' => '3'],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '2'],
    ['src' => 3, 'dst' => 4, 'weight' => 2.0],
];
$nextEdges = [
    ...$currentEdges,
    ['src' => 4, 'dst' => 5, 'weight' => 3.0],
    ['src' => 5, 'dst' => 6, 'weight' => '3'],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];
$sql = <<<'SQL'
WITH RECURSIVE option_walk(item_id, key_value, source) AS MATERIALIZED (
    VALUES (1, 1, 'seed')
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge'
      FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id
     WHERE item_id < 8
    UNION
    SELECT item_id, key_value + 0.0, source
      FROM option_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source
  FROM option_walk
UNION
SELECT option_id AS id,
       rank_value AS key_value,
       option_name AS source
  FROM wp_options
 ORDER BY id, key_value, source
 LIMIT 5 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select recursive affinity limit status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-recursive-affinity-limit-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-union-affinity-dedup',
        'sqlite-compound-select-final-limit-after-union',
        'sqlite-compound-select-left-column-name-affinity',
    ], $plan['dependencies']);
};

$tests['compound select recursive affinity limit compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['id', 'key_value', 'source'], $compound['orderColumns']);
    $t->same(['id', 'key_value', 'source'], $compound['leftColumns']);
    $t->same(5, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select recursive affinity limit current final limit rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'edge', 'home', 'active_plugins', 'edge'], array_column($rows, 'source'));
    $t->same([1, 1.0, '1', 2, '2'], array_column($rows, 'key_value'));
};

$tests['compound select recursive affinity limit next final limit unchanged until boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 2, 2, 3, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'edge', 'home', 'active_plugins', 'edge'], array_column($rows, 'source'));
    $t->same([1, 1.0, '1', 2, '2'], array_column($rows, 'key_value'));
};

$tests['compound select recursive affinity limit unlimited next captures deferred boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextUnlimitedRows'];
    $t->same([5, 5, 6, 6], array_slice(array_column($rows, 'id'), -4));
    $t->same(['edge', 'plugin_cache', 'edge', 'plugin_cache_text'], array_slice(array_column($rows, 'source'), -4));
    $t->same([3.0, 3, '3', '3'], array_slice(array_column($rows, 'key_value'), -4));
};

$tests['compound select recursive affinity limit recursive trace deduplicates numeric seed'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_walk', $recursive['name']);
    $t->same(['item_id', 'key_value', 'source'], $recursive['columns']);
    $t->same('UNION', $recursive['operator']);
    $t->same([['item_id' => 1, 'key_value' => 1.0, 'source' => 'seed']], $recursive['currentSkipped']);
    $t->same([['item_id' => 1, 'key_value' => 1.0, 'source' => 'seed']], $recursive['nextSkipped']);
    $t->true(in_array('sqlite-recursive-union-cycle-dedup', $recursive['dependencies'], true));
};

$tests['compound select recursive affinity limit recursive current next source depth'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(6, $recursive['nextTraceCount']);
    $t->same([1, 2, 3, 4], array_column($recursive['currentRows'], 'item_id'));
    $t->same([1, 2, 3, 4, 5, 6], array_column($recursive['nextRows'], 'item_id'));
};

$tests['compound select recursive affinity limit affinity diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $affinity = $summary()['affinity'];
    $t->same(['numeric:1', 'string:1', 'numeric:2', 'string:2'], $affinity['currentKeyClasses']);
    $t->same(['numeric:1', 'string:1', 'numeric:2', 'string:2', 'numeric:3', 'string:3'], $affinity['nextKeyClasses']);
    $t->same(['numeric:1', 'numeric:2', 'string:2'], $affinity['currentDuplicateClasses']);
    $t->same(['numeric:1', 'numeric:2', 'string:2', 'numeric:3', 'string:3'], $affinity['nextDuplicateClasses']);
    $t->same(['numeric:3', 'string:3'], $affinity['changedKeyClasses']);
};

$tests['compound select recursive affinity limit limit trace applies after union'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace']['next'];
    $t->same(12, $trace['preLimitCount']);
    $t->same(5, $trace['acceptedCount']);
    $t->same([[1, 1, 'seed']], array_map(static fn (array $row): array => array_values($row), $trace['skippedBeforeOffset']));
    $t->same([[4, 2.0, 'edge'], [4, '2', 'theme_mods'], [5, 3.0, 'edge'], [5, 3, 'plugin_cache'], [6, '3', 'edge'], [6, '3', 'plugin_cache_text']], array_map(static fn (array $row): array => array_values($row), $trace['truncatedAfterLimit']));
};

$tests['compound select recursive affinity limit changed diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->same('', $changed);
    $t->true(in_array('compound-recursive-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('recursive-union-source-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('affinity-storage-classes-changed', $plan['replanReasons'], true));
};

$tests['compound select recursive affinity limit rejects non recursive'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit(
        'SELECT option_id AS id, rank_value AS key_value, option_name AS source FROM wp_options UNION SELECT option_id, rank_value, option_name FROM wp_options ORDER BY id LIMIT 2',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select recursive affinity limit rejects union all'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit(
        "WITH RECURSIVE option_walk(item_id, key_value, source) AS (VALUES (1, 1, 'seed')) SELECT item_id AS id, key_value, source FROM option_walk UNION ALL SELECT option_id AS id, rank_value AS key_value, option_name AS source FROM wp_options ORDER BY id LIMIT 2",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select recursive affinity limit rejects missing final limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveAffinityLimitPlan::compareRecursiveAffinityLimit(
        "WITH RECURSIVE option_walk(item_id, key_value, source) AS (VALUES (1, 1, 'seed')) SELECT item_id AS id, key_value, source FROM option_walk UNION SELECT option_id AS id, rank_value AS key_value, option_name AS source FROM wp_options ORDER BY id",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 67) as $case) {
    $tests['compound select recursive affinity limit generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'seed_' . $case, 'rank_value' => 1],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'rank_value' => '1'],
                ['option_id' => 3, 'option_name' => 'tail_' . $case, 'rank_value' => 2 + ($case % 3)],
            ],
            'wp_option_edges' => [
                ['src' => 1, 'dst' => 2, 'weight' => 1.0],
                ['src' => 2, 'dst' => 3, 'weight' => (string) (2 + ($case % 3))],
            ],
        ];
        $offset = $case % 2;
        $limit = 3 + ($case % 3);
        $sql = "WITH RECURSIVE option_walk(item_id, key_value, source) AS (VALUES (1, 1, 'seed') UNION SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge' FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id WHERE item_id < 4 UNION SELECT item_id, key_value + 0.0, source FROM option_walk WHERE item_id = 1) SELECT item_id AS id, key_value, source FROM option_walk UNION SELECT option_id AS id, rank_value AS key_value, option_name AS source FROM wp_options ORDER BY id, key_value, source LIMIT {$limit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->true(count($rows) <= $limit);
        $t->same(['id', 'key_value', 'source'], array_keys($rows[0] ?? ['id' => null, 'key_value' => null, 'source' => null]));
        $t->true(($rows[0]['id'] ?? 0) >= 1);
        $t->true(($rows[0]['id'] ?? 99) <= 2);
    };
}

return $tests;
