<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 90],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 80],
        ['option_id' => 4, 'option_name' => 'cache', 'autoload' => 'no', 'weight' => 70],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 60];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'blogname'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'plugin_alpha'
             ELSE 'extra'
           END,
           score - 10
      FROM q
     WHERE id < 7
     ORDER BY 3 DESC
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       lag(label, 1, 'siteurl') OVER (ORDER BY score DESC, id) AS marker
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       lag(option_name, 1, 'siteurl') OVER (ORDER BY weight DESC, option_id) AS marker
  FROM wp_options
 ORDER BY marker, id
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'wordpress-compound-intersect-lag-lead-recursive-limit-current-source-next176',
    'sqlShape' => 'WITH RECURSIVE LIMIT/OFFSET feeding lag-window INTERSECT arms with final ORDER BY/LIMIT/OFFSET',
    'wordpressUse' => 'Copied wp_options import previews can skip a recursive seed source, intersect lag-window current rows with copied option rows, and admit the next plugin option at the final compound LIMIT boundary.',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSkipped' => $plan['recursive']['currentSkippedLabels'],
    'newAdmittedLabels' => $plan['boundary']['gainedLabels'],
    'leadNextMarkers' => array_column($plan['leadDiagnostics']['next'], 'lead_marker'),
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound INTERSECT/LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['currentLabels'] !== ['blogname', 'home']) {
        fwrite(STDERR, "unexpected current labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['plugin_alpha', 'blogname', 'home']) {
        fwrite(STDERR, "unexpected next labels\n");
        exit(1);
    }
    if ($result['recursiveSkipped'] !== ['siteurl']) {
        fwrite(STDERR, "recursive offset did not skip the seed row\n");
        exit(1);
    }
    if ($result['newAdmittedLabels'] !== ['plugin_alpha']) {
        fwrite(STDERR, "next-source plugin option did not cross the final LIMIT boundary\n");
        exit(1);
    }
    echo "wordpress-compound-intersect-lag-lead-recursive-limit-current-source-next176 self-test passed\n";
}

return $result;
