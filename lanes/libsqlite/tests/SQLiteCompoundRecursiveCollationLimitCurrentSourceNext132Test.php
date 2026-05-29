<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'HOME', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no'],
];
$nextOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'HOME', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'akismet', 'autoload' => 'no'],
    ['option_id' => 6, 'option_name' => 'Zebra_Plugin', 'autoload' => 'no'],
];
$currentEdges = [
    ['name' => 'siteurl', 'next_name' => 'Home', 'depth' => 1],
    ['name' => 'Home', 'next_name' => 'BlogName', 'depth' => 2],
    ['name' => 'BlogName', 'next_name' => 'active_plugins', 'depth' => 3],
];
$nextEdges = [
    ['name' => 'siteurl', 'next_name' => 'Home', 'depth' => 1],
    ['name' => 'Home', 'next_name' => 'BlogName', 'depth' => 2],
    ['name' => 'BlogName', 'next_name' => 'active_plugins', 'depth' => 3],
    ['name' => 'active_plugins', 'next_name' => 'Akismet', 'depth' => 4],
    ['name' => 'Akismet', 'next_name' => 'zebra_plugin', 'depth' => 5],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE wanted(name, depth) AS MATERIALIZED (
    VALUES ('siteurl', 0)
    UNION
    SELECT wp_option_edges.next_name, wp_option_edges.depth
      FROM wp_option_edges JOIN wanted ON wp_option_edges.name = wanted.name COLLATE NOCASE
     WHERE wanted.depth < 6
    UNION
    SELECT upper(name), depth
      FROM wanted
     WHERE depth = 0
)
SELECT name COLLATE NOCASE AS name, depth
  FROM wanted
UNION
SELECT option_name AS name, option_id AS depth
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY name COLLATE NOCASE
 LIMIT 4 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan::compareNext132($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound recursive collation limit current source next132 status and shape'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-recursive-collation-limit-current-source-next132-ready', $plan['status']);
    $t->same(['UNION'], $plan['compound']['operators']);
    $t->same(2, $plan['compound']['currentArms']);
    $t->same(2, $plan['compound']['nextArms']);
    $t->same(['name' => 'NOCASE'], $plan['compound']['setCollations']);
    $t->same(4, $plan['compound']['limit']);
    $t->same(1, $plan['compound']['offset']);
};

$tests['compound recursive collation limit current source next132 current names'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['active_plugins', 'BlogName', 'Home', 'siteurl'], $plan['currentNames']);
    $t->same(4, $plan['limitWindow']['currentReturned']);
    $t->same(1, $plan['limitWindow']['currentSuppressedByLimit']);
};

$tests['compound recursive collation limit current source next132 next names'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['active_plugins', 'Akismet', 'akismet', 'BlogName'], $plan['nextNames']);
    $t->same(4, $plan['limitWindow']['nextReturned']);
    $t->same(5, $plan['limitWindow']['nextSuppressedByLimit']);
};

$tests['compound recursive collation limit current source next132 recursive trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('wanted', $recursive['name']);
    $t->same(['name', 'depth'], $recursive['columns']);
    $t->true(in_array(['name' => 'SITEURL', 'depth' => 0], $recursive['currentSkipped'], true));
    $t->true(in_array(['name' => 'SITEURL', 'depth' => 0], $recursive['nextSkipped'], true));
    $t->true(in_array('sqlite-recursive-union-cycle-dedup', $recursive['dependencies'], true));
};

$tests['compound recursive collation limit current source next132 collation and replan diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['active_plugins', 'blogname', 'home', 'siteurl'], $plan['collation']['currentFoldedNames']);
    $t->same(['active_plugins', 'akismet', 'akismet', 'blogname'], $plan['collation']['nextFoldedNames']);
    $t->same([], $plan['collation']['currentDuplicateKeys']);
    $t->same(['akismet'], $plan['collation']['nextDuplicateKeys']);
    $t->true(in_array('compound-set-collation', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-rowset-changed', $plan['replanReasons'], true));
};

$tests['compound recursive collation limit current source next132 changed names record next admission'] = static function (TestRunner $t) use ($summary): void {
    $changed = $summary()['changedNames'];
    $t->true(in_array('"Akismet"', $changed, true));
    $t->true(in_array('"akismet"', $changed, true));
    $t->true(in_array('"siteurl"', $changed, true));
    $t->same(4, count($changed));
};

$tests['compound recursive collation limit current source next132 rejects non compound recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan::compareNext132(
        "WITH RECURSIVE wanted(name, depth) AS (VALUES ('siteurl', 0)) SELECT name FROM wanted",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 43) as $offset) {
    $tests['compound recursive collation limit current source next132 generated offset ' . $offset] = static function (TestRunner $t) use ($offset, $nextTables): void {
        $effectiveOffset = $offset % 3;
        $sql = "WITH RECURSIVE wanted(name, depth) AS (VALUES ('siteurl', 0) UNION SELECT wp_option_edges.next_name, wp_option_edges.depth FROM wp_option_edges JOIN wanted ON wp_option_edges.name = wanted.name COLLATE NOCASE WHERE wanted.depth < 5 UNION SELECT upper(name), depth FROM wanted WHERE depth = 0) SELECT name COLLATE NOCASE AS name, depth FROM wanted UNION SELECT option_name AS name, option_id AS depth FROM wp_options WHERE autoload = 'no' ORDER BY name COLLATE NOCASE LIMIT 3 OFFSET {$effectiveOffset}";
        $rows = SQLiteSelectSql::execute($sql, $nextTables);

        $t->true(count($rows) <= 3);
        $t->same(['name', 'depth'], array_keys($rows[0] ?? ['name' => null, 'depth' => null]));
        $t->same(array_map('strtolower', array_column($rows, 'name')), array_map('strtolower', array_column($rows, 'name')));
    };
}

return $tests;
