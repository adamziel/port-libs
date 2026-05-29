<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'HOME', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no'],
    ],
    'wp_option_edges' => [
        ['name' => 'siteurl', 'next_name' => 'Home', 'depth' => 1],
        ['name' => 'Home', 'next_name' => 'BlogName', 'depth' => 2],
        ['name' => 'BlogName', 'next_name' => 'active_plugins', 'depth' => 3],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'akismet', 'autoload' => 'no'];
$nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'Zebra_Plugin', 'autoload' => 'no'];
$nextTables['wp_option_edges'][] = ['name' => 'active_plugins', 'next_name' => 'Akismet', 'depth' => 4];
$nextTables['wp_option_edges'][] = ['name' => 'Akismet', 'next_name' => 'zebra_plugin', 'depth' => 5];

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

$summary = SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan::compareRecursiveCollationLimit($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'compound-recursive-collation-limit-current-source-next132-ready');
    assert($summary['currentNames'] === ['active_plugins', 'BlogName', 'Home', 'siteurl']);
    assert($summary['nextNames'] === ['active_plugins', 'Akismet', 'akismet', 'BlogName']);
    assert($summary['compound']['setCollations'] === ['name' => 'NOCASE']);
    assert(in_array('compound-final-limit', $summary['replanReasons'], true));
    echo "wordpress-compound-recursive-collation-limit-current-source-next132 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
