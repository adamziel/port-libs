<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'weight' => 1, 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'weight' => 1.0, 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'weight' => '1', 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'module_registry', 'weight' => 2, 'load_policy' => 'no'],
];
$nextOptions = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'weight' => 1, 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'weight' => 1.0, 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'weight' => '1', 'load_policy' => 'yes'],
    ['setting_id' => 4, 'key_name' => 'module_registry', 'weight' => 2, 'load_policy' => 'no'],
    ['setting_id' => 5, 'key_name' => 'new_module_flag', 'weight' => '2', 'load_policy' => 'no'],
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

$currentTables = ['app_settings' => $currentOptions, 'app_setting_edges' => $currentEdges];
$nextTables = ['app_settings' => $nextOptions, 'app_setting_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE wanted(node, weight) AS MATERIALIZED (
    VALUES (1, 1)
    UNION
    SELECT app_setting_edges.dst, app_setting_edges.weight
      FROM app_setting_edges JOIN wanted ON app_setting_edges.src = node
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
SELECT setting_id AS id,
       weight AS class_value,
       sum(CAST(weight AS REAL)) FILTER (WHERE load_policy = 'no') OVER (
           ORDER BY setting_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS weighted_total
  FROM app_settings
 WHERE setting_id IN (SELECT node FROM wanted)
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
    $t->same([null, 2.0, null, 1.0, null, null, 2.0], array_column($rows, 'weighted_total'));
};

$tests['compound recursive affinity window current source next rows include next source'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([1, 1, 2, 2, 3, 4, 4, 5, 5], array_column($rows, 'id'));
    $t->same([1, 1, 1.0, 1.0, '1', 2, 2, '2', '2'], array_column($rows, 'class_value'));
    $t->same([null, 2.0, null, 1.0, null, null, 2.0, null, 2.0], array_column($rows, 'weighted_total'));
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

$tests['compound recursive affinity window current source changed signatures name next module'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(2, count($plan['changedSignatures']));
    $t->true(str_contains(implode("\n", $plan['changedSignatures']), '"id":5'));
    $t->true(str_contains(implode("\n", $plan['changedSignatures']), '"class_value":"2"'));
};

foreach (range(1, 28) as $stop) {
    $tests['compound recursive affinity window current source generated recursive stop ' . $stop] = static function (TestRunner $t) use ($stop): void {
        $tables = [
            'app_settings' => [
                ['setting_id' => 1, 'weight' => 1, 'load_policy' => 'yes'],
                ['setting_id' => 2, 'weight' => 1.0, 'load_policy' => 'yes'],
                ['setting_id' => 3, 'weight' => '1', 'load_policy' => 'no'],
            ],
        ];
        $limit = min($stop, 3);
        $sql = "WITH RECURSIVE wanted(node, weight) AS (VALUES (1, 1) UNION SELECT node + 1, weight + 0.0 FROM wanted WHERE node < {$limit} UNION SELECT node, CAST(weight AS TEXT) FROM wanted WHERE node = {$limit}) SELECT node AS id, weight AS class_value, sum(CAST(weight AS REAL)) FILTER (WHERE weight = 1) OVER (ORDER BY node ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS weighted_total FROM wanted UNION SELECT setting_id AS id, weight AS class_value, sum(CAST(weight AS REAL)) OVER (ORDER BY setting_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS weighted_total FROM app_settings WHERE setting_id <= {$limit} ORDER BY id, class_value";
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
