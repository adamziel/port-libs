<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 18],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 16],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 12],
    ],
    'wp_option_edges' => [
        ['src' => 1, 'dst' => 2, 'weight' => 18],
        ['src' => 2, 'dst' => 3, 'weight' => 16],
        ['src' => 3, 'dst' => 4, 'weight' => 14],
        ['src' => 4, 'dst' => 5, 'weight' => 12],
    ],
];
$nextTables = [
    'wp_options' => [
        ...$currentTables['wp_options'],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 19],
        ['option_id' => 7, 'option_name' => 'plugin_queue', 'autoload' => 'no', 'weight' => 15],
    ],
    'wp_option_edges' => [
        ['src' => 1, 'dst' => 6, 'weight' => 19],
        ['src' => 6, 'dst' => 2, 'weight' => 18],
        ['src' => 2, 'dst' => 3, 'weight' => 16],
        ['src' => 3, 'dst' => 7, 'weight' => 15],
        ['src' => 7, 'dst' => 4, 'weight' => 14],
        ['src' => 4, 'dst' => 5, 'weight' => 12],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE wanted(id, label, depth, weight) AS MATERIALIZED (
    VALUES (1, 'siteurl', 0, 20)
    UNION ALL
    SELECT wp_option_edges.dst, wp_options.option_name, wanted.depth + 1, wp_option_edges.weight
      FROM wanted
      JOIN wp_option_edges ON wp_option_edges.src = wanted.id
      JOIN wp_options ON wp_options.option_id = wp_option_edges.dst
     WHERE wanted.depth < 8
     ORDER BY 4 DESC
     LIMIT 5
)
SELECT id,
       label,
       depth,
       last_value(label) OVER (
           ORDER BY weight DESC, id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, id ASC
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS frame_weight
  FROM wanted
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       99 AS depth,
       first_value(option_name) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS frame_tail,
       sum(weight) OVER (
           ORDER BY weight DESC, option_id ASC
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS frame_weight
  FROM wp_options
 WHERE autoload = 'no'
 ORDER BY frame_weight DESC, id ASC
 LIMIT 6 OFFSET 1
SQL;

$plan = SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan::compareNext135($sql, $currentTables, $nextTables);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['status'] !== 'compound-recursive-limit-window-current-source-next135-ready') {
        fwrite(STDERR, "Unexpected compound recursive limit window status\n");
        exit(1);
    }
    if (array_column($plan['nextRows'], 'id') !== [6, 2, 3, 7, 4, 7]) {
        fwrite(STDERR, "Unexpected next-source limited row ids\n");
        exit(1);
    }
    if (($plan['recursive']['nextLimitRemaining'] ?? null) !== 0) {
        fwrite(STDERR, "Unexpected recursive queue limit boundary\n");
        exit(1);
    }
    echo "wordpress-compound-recursive-limit-window-current-source-next135 self-test passed\n";
    exit(0);
}

echo json_encode([
    'wordpressUse' => 'Preview copied wp_options dependency walks where a recursive CTE queue LIMIT feeds compound SELECT rows with CURRENT ROW/FOLLOWING window frames before final LIMIT/OFFSET.',
    'sql' => $sql,
    'currentRows' => $plan['currentRows'],
    'nextRows' => $plan['nextRows'],
    'recursive' => $plan['recursive'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
