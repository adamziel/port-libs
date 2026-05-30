<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;

$options = [
    ['option_id' => 10, 'option_name' => 'plugin_seed_a', 'autoload' => 'yes', 'option_value' => '{"next":[20,30],"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}]}'],
    ['option_id' => 20, 'option_name' => 'plugin_seed_b', 'autoload' => 'yes', 'option_value' => '{"next":[40],"rules":[{"name":"forms","priority":4},{"name":"media","priority":1}]}'],
    ['option_id' => 30, 'option_name' => 'plugin_seed_c', 'autoload' => 'no', 'option_value' => '{"next":[40],"rules":[{"name":"gallery","priority":5},{"name":"seo","priority":3}]}'],
    ['option_id' => 40, 'option_name' => 'plugin_seed_d', 'autoload' => 'no', 'option_value' => '{"next":[50],"rules":[{"name":"cache","priority":9},{"name":"forms","priority":6}]}'],
    ['option_id' => 50, 'option_name' => 'plugin_seed_e', 'autoload' => 'yes', 'option_value' => '{"next":[],"rules":[{"name":"media","priority":8},{"name":"seo","priority":10}]}'],
];

$sql = "WITH RECURSIVE wanted(option_id) AS MATERIALIZED (
            VALUES (10), (20)
            UNION
            SELECT CAST(next.atom AS INTEGER)
              FROM wanted
              JOIN wp_options AS o ON o.option_id = wanted.option_id
              JOIN json_each(o.option_value, '$.next') AS next ON next.type = 'integer'
        )
        SELECT wanted.option_id AS option_id,
               o.option_name AS option_name,
               jt.key AS attr,
               jt.atom AS atom,
               jt.fullkey AS fullkey
          FROM wanted
          JOIN wp_options AS o ON o.option_id = wanted.option_id
          JOIN json_tree(o.option_value, '$.rules') AS jt ON jt.type IN ('text', 'integer')
         ORDER BY wanted.option_id, jt.fullkey";

$plan = SQLiteSelectRecursiveJsonMaterialization::materialize($sql, ['wp_options' => $options], ['option_id', 'attr'], ['fullkey']);
$boundaries = $plan['recursiveCurrentNext'];

$summary = [
    'scenario' => 'application-select-materialized-recursive-json-current-next51',
    'recursiveOptionIds' => array_column($plan['trace']['rows'], 'option_id'),
    'dedupedRecursiveSkips' => count($plan['trace']['skipped']),
    'boundaryCount' => count($boundaries),
    'firstBoundary' => [
        'current' => $boundaries[0]['current']['option_id'],
        'next' => $boundaries[0]['next']['option_id'] ?? null,
        'currentJsonRows' => count($boundaries[0]['currentJsonRows']),
        'nextJsonRows' => count($boundaries[0]['nextJsonRows']),
    ],
    'terminalBoundary' => [
        'current' => $boundaries[count($boundaries) - 1]['current']['option_id'],
        'next' => $boundaries[count($boundaries) - 1]['next']['option_id'] ?? null,
    ],
    'applicationUse' => 'Copied wp_options JSON imports can preserve recursive CTE current/next boundaries while materializing reachable json_tree() rows, including UNION duplicate skips, without ext/sqlite.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['recursiveOptionIds'] !== [10, 20, 30, 40, 50] || $summary['dedupedRecursiveSkips'] !== 2 || $summary['boundaryCount'] !== 5 || $summary['firstBoundary']['currentJsonRows'] !== 4) {
        fwrite(STDERR, "application-select-materialized-recursive-json-current-next51 self-test failed\n");
        exit(1);
    }

    echo "application-select-materialized-recursive-json-current-next51 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
