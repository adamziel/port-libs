<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1, 'priority' => 50],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 1.0, 'priority' => 40],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => '1', 'priority' => 30],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 2, 'priority' => 20],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => '2', 'priority' => 10],
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 3, 'priority' => 5],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '1'],
    ['src' => 3, 'dst' => 4, 'weight' => 2],
];
$nextEdges = [
    ...$currentEdges,
    ['src' => 4, 'dst' => 5, 'weight' => '2'],
    ['src' => 5, 'dst' => 6, 'weight' => 3],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE option_walk(item_id, key_value, source, score) AS MATERIALIZED (
    VALUES (1, 1, 'seed', 50)
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge', score - 7
      FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id
     WHERE item_id < 6
    UNION
    SELECT item_id, key_value + 0.0, source, score
      FROM option_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source,
       sum(score) FILTER (WHERE key_value = 1) OVER (
           ORDER BY item_id, source
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_score
  FROM option_walk
UNION
SELECT option_id AS id,
       weight AS key_value,
       option_name AS source,
       sum(priority) FILTER (WHERE autoload = 'no') OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS window_score
  FROM wp_options
 WHERE option_id IN (SELECT item_id FROM option_walk)
 ORDER BY id, key_value, source
SQL;

$summary = static fn (): array => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound recursive affinity window current source source-boundary status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-recursive-affinity-window-current-source-source-boundary-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-union-affinity-dedup',
        'sqlite-window-arm-before-compound-union',
        'sqlite-compound-left-column-name-retention',
        'sqlite-current-source-next-rowset-boundary',
    ], $plan['dependencies']);
};

$tests['compound recursive affinity window current source source-boundary compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['id', 'key_value', 'source'], $compound['orderColumns']);
    $t->same(['id', 'key_value', 'source', 'window_score'], $compound['leftColumns']);
};

$tests['compound recursive affinity window current source source-boundary current rows preserve left names'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([1, 1, 2, 2, 3, 3, 4, 4], array_column($rows, 'id'));
    $t->same(['seed', 'siteurl', 'edge', 'home', 'blogname', 'edge', 'active_plugins', 'edge'], array_column($rows, 'source'));
    $t->same([1, 1, 1.0, 1.0, '1', '1', 2, 2], array_column($rows, 'key_value'));
    $t->same([93, null, 43, null, null, null, 20, null], array_column($rows, 'window_score'));
};

$tests['compound recursive affinity window current source source-boundary next rows add source boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6], array_column($rows, 'id'));
    $t->same(['seed', 'siteurl', 'edge', 'home', 'blogname', 'edge', 'active_plugins', 'edge', 'edge', 'plugin_alpha', 'edge', 'plugin_beta'], array_column($rows, 'source'));
    $t->same([1, 1, 1.0, 1.0, '1', '1', 2, 2, '2', '2', 3, 3], array_column($rows, 'key_value'));
};

$tests['compound recursive affinity window current source source-boundary recursive queue captures affinity duplicate skip'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_walk', $recursive['name']);
    $t->same(['item_id', 'key_value', 'source', 'score'], $recursive['columns']);
    $t->same('UNION', $recursive['operator']);
    $t->same([[ 'item_id' => 1, 'key_value' => 1.0, 'source' => 'seed', 'score' => 50 ]], $recursive['currentSkipped']);
    $t->same([[ 'item_id' => 1, 'key_value' => 1.0, 'source' => 'seed', 'score' => 50 ]], $recursive['nextSkipped']);
    $t->true(in_array('sqlite-recursive-union-cycle-dedup', $recursive['dependencies'], true));
};

$tests['compound recursive affinity window current source source-boundary recursive current next trace counts'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(6, $recursive['nextTraceCount']);
    $t->same([1, 2, 3, 4], array_column($recursive['currentRows'], 'item_id'));
    $t->same([1, 2, 3, 4, 5, 6], array_column($recursive['nextRows'], 'item_id'));
};

$tests['compound recursive affinity window current source source-boundary window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['sum', 'sum'], array_column($windows, 'function'));
    $t->same(['window_score', 'window_score'], array_column($windows, 'alias'));
    $t->same([true, true], array_column($windows, 'hasFilter'));
    $t->same(['ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
    $t->same([0, 0], array_column($windows, 'preceding'));
    $t->same([1, 0], array_column($windows, 'following'));
};

$tests['compound recursive affinity window current source source-boundary affinity diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $affinity = $summary()['affinity'];
    $t->true(in_array('numeric:1', $affinity['currentDuplicateKeys'], true));
    $t->true(in_array('numeric:2', $affinity['currentDuplicateKeys'], true));
    $t->true(in_array('string:2', $affinity['nextDuplicateKeys'], true));
    $t->true(in_array('numeric:3', $affinity['changedKeyClasses'], true));
};

$tests['compound recursive affinity window current source source-boundary source delta'] = static function (TestRunner $t) use ($summary): void {
    $delta = $summary()['sourceDelta'];
    $t->same(3, $delta['currentSources']['edge']);
    $t->same(5, $delta['nextSources']['edge']);
    $t->same(['plugin_alpha', 'plugin_beta'], $delta['newSources']);
    $t->same([], $delta['removedSources']);
};

$tests['compound recursive affinity window current source source-boundary changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, '"source":"plugin_alpha"'));
    $t->true(str_contains($changed, '"source":"plugin_beta"'));
    $t->true(in_array('compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-union', $plan['replanReasons'], true));
    $t->true(in_array('affinity-key-class-changed', $plan['replanReasons'], true));
    $t->true(in_array('current-next-source-boundary-changed', $plan['replanReasons'], true));
};

$tests['compound recursive affinity window current source source-boundary rejects non compound recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary(
        'WITH RECURSIVE option_walk(item_id, key_value, source, score) AS (VALUES (1, 1, \'seed\', 1)) SELECT item_id FROM option_walk',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound recursive affinity window current source source-boundary rejects missing window'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary(
        'WITH RECURSIVE option_walk(item_id, key_value, source, score) AS (VALUES (1, 1, \'seed\', 1)) SELECT item_id AS id, key_value, source, score FROM option_walk UNION SELECT option_id AS id, weight AS key_value, option_name AS source, priority AS score FROM wp_options',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound recursive affinity window current source source-boundary rejects union all only'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary(
        'WITH RECURSIVE option_walk(item_id, key_value, source, score) AS (VALUES (1, 1, \'seed\', 1)) SELECT item_id AS id, key_value, source, sum(score) OVER (ORDER BY item_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS window_score FROM option_walk UNION ALL SELECT option_id AS id, weight AS key_value, option_name AS source, priority AS window_score FROM wp_options',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound recursive affinity window current source source-boundary generated affinity source boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 1, 'priority' => 30 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 1.0, 'priority' => 20 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'no', 'weight' => (string) (1 + ($case % 3)), 'priority' => 10 + $case],
            ],
            'wp_option_edges' => [
                ['src' => 1, 'dst' => 2, 'weight' => 1.0],
                ['src' => 2, 'dst' => 3, 'weight' => (string) (1 + ($case % 3))],
            ],
        ];
        $sql = "WITH RECURSIVE option_walk(item_id, key_value, source, score) AS (VALUES (1, 1, 'seed', {$case}) UNION SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge', score + 1 FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id WHERE item_id < 3 UNION SELECT item_id, key_value + 0.0, source, score FROM option_walk WHERE item_id = 1) SELECT item_id AS id, key_value, source, sum(score) FILTER (WHERE key_value = 1) OVER (ORDER BY item_id, source ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_score FROM option_walk UNION SELECT option_id AS id, weight AS key_value, option_name AS source, sum(priority) FILTER (WHERE autoload = 'no') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS window_score FROM wp_options WHERE option_id IN (SELECT item_id FROM option_walk) ORDER BY id, key_value, source";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(1, $rows[0]['id'] ?? null);
        $t->true(count($rows) >= 5);
        $t->true(in_array('source', array_keys($rows[0] ?? []), true));
        $t->true(in_array('window_score', array_keys($rows[0] ?? []), true));
    };
}

return $tests;
