<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'weight' => 1, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'weight' => 1.0, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'weight' => '1', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'weight' => 2, 'autoload' => 'no'],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'weight' => 1, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'weight' => 1.0, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'weight' => '1', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'weight' => 2, 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'weight' => '2', 'autoload' => 'no'],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '1'],
    ['src' => 3, 'dst' => 4, 'weight' => 2],
];
$nextEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '1'],
    ['src' => 3, 'dst' => 4, 'weight' => 2],
    ['src' => 4, 'dst' => 5, 'weight' => '2'],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE wanted(node, weight) AS MATERIALIZED (
    VALUES (1, 1)
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight
      FROM wp_option_edges JOIN wanted ON wp_option_edges.src = node
     WHERE node < 5
    UNION
    SELECT node, weight + 0.0
      FROM wanted
     WHERE node = 1
)
SELECT node AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE weight = 1) OVER (
           ORDER BY node
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS weighted_total
  FROM wanted
UNION
SELECT option_id AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE autoload = 'no') OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS weighted_total
  FROM wp_options
 WHERE option_id IN (SELECT node FROM wanted)
 ORDER BY id, class_value
SQL;

$summary = static fn (): array => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveAffinityWindow($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound recursive affinity window current source status and compound shape'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-recursive-affinity-window-current-source-ready', $plan['status']);
    $t->same(['UNION'], $plan['compound']['operators']);
    $t->same(2, $plan['compound']['currentArms']);
    $t->same(2, $plan['compound']['nextArms']);
    $t->same(['id', 'class_value'], $plan['compound']['orderColumns']);
};

$tests['compound recursive affinity window current source current rows preserve affinity representatives'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([1, 1, 2, 2, 3, 4, 4], array_column($rows, 'id'));
    $t->same([1, 1, 1.0, 1.0, '1', 2, 2], array_column($rows, 'class_value'));
    $t->same([2.0, null, 1.0, null, null, null, 2.0], array_column($rows, 'weighted_total'));
};

$tests['compound recursive affinity window current source next rows include next source'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 1, 2, 2, 3, 4, 4, 5, 5], array_column($rows, 'id'));
    $t->same([1, 1, 1.0, 1.0, '1', 2, 2, '2', '2'], array_column($rows, 'class_value'));
    $t->same([2.0, null, 1.0, null, null, null, 2.0, null, 2.0], array_column($rows, 'weighted_total'));
};

$tests['compound recursive affinity window current source recursive trace records numeric duplicate skip'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('wanted', $recursive['name']);
    $t->same(['node', 'weight'], $recursive['columns']);
    $t->same([[ 'node' => 1, 'weight' => 1.0 ]], $recursive['currentSkipped']);
    $t->same([[ 'node' => 1, 'weight' => 1.0 ]], $recursive['nextSkipped']);
    $t->true(in_array('sqlite-recursive-union-cycle-dedup', $recursive['dependencies'], true));
};

$tests['compound recursive affinity window current source diagnostics identify windows and affinity'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['sum', 'sum'], array_column($plan['windows']['current'], 'function'));
    $t->same([true, true], array_column($plan['windows']['current'], 'hasFilter'));
    $t->same(['ROWS', 'ROWS'], array_column($plan['windows']['current'], 'frameUnit'));
    $t->true(in_array('compound-window-source', $plan['replanReasons'], true));
    $t->true(in_array('compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('numeric:1', $plan['affinity']['currentDuplicateClasses'], true));
    $t->true(in_array('numeric:2', $plan['affinity']['currentDuplicateClasses'], true));
    $t->true(in_array('string:2', $plan['affinity']['changedClasses'], true));
};

$tests['compound recursive affinity window current source changed signatures name next plugin'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(2, count($plan['changedSignatures']));
    $t->true(str_contains(implode("\n", $plan['changedSignatures']), '"id":5'));
    $t->true(str_contains(implode("\n", $plan['changedSignatures']), '"class_value":"2"'));
};

foreach (range(1, 28) as $stop) {
    $tests['compound recursive affinity window current source generated recursive stop ' . $stop] = static function (TestRunner $t) use ($stop): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'weight' => 1, 'autoload' => 'yes'],
                ['option_id' => 2, 'weight' => 1.0, 'autoload' => 'yes'],
                ['option_id' => 3, 'weight' => '1', 'autoload' => 'no'],
            ],
        ];
        $limit = min($stop, 3);
        $sql = "WITH RECURSIVE wanted(node, weight) AS (VALUES (1, 1) UNION SELECT node + 1, weight + 0.0 FROM wanted WHERE node < {$limit} UNION SELECT node, CAST(weight AS TEXT) FROM wanted WHERE node = {$limit}) SELECT node AS id, weight AS class_value, sum(CAST(weight AS REAL)) FILTER (WHERE weight = 1) OVER (ORDER BY node ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS weighted_total FROM wanted UNION SELECT option_id AS id, weight AS class_value, sum(CAST(weight AS REAL)) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS weighted_total FROM wp_options WHERE option_id <= {$limit} ORDER BY id, class_value";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(1, $rows[0]['id'] ?? null);
        $t->true(count($rows) >= $limit);
        $t->true(in_array('class_value', array_keys($rows[0] ?? []), true));
    };
}

$tests['compound recursive affinity window current source rejects non compound recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveAffinityWindow(
        'WITH RECURSIVE wanted(node, weight) AS (VALUES (1, 1)) SELECT node FROM wanted',
        $currentTables,
        $currentTables,
    ));
};

return $tests;
