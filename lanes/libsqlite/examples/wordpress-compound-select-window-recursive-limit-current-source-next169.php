<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 10],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'no', 'weight' => 18],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'weight' => 14],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 30],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 9],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 36)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 7
     ORDER BY 1
     LIMIT 1, 5
)
SELECT id,
       label,
       ntile(2) OVER (ORDER BY weight DESC, id) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       ntile(2) OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext169($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'compound-select-window-recursive-limit-current-source-next169-ready');
    assert(array_column($plan['nextRows'], 'label') === ['home', 'seed:2:3', 'seed:2:3:4', 'active_plugins', 'rewrite_rules', 'siteurl']);
    assert($plan['recursive']['currentSkippedLabels'] === ['seed']);
    assert($plan['bucketDelta']['next'] === [1 => 5, 2 => 1]);
    echo "wordpress-compound-select-window-recursive-limit-current-source-next169 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
