<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'app_settings' => [
        ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'weight' => 90],
        ['setting_id' => 3, 'key_name' => 'site_title', 'load_policy' => 'yes', 'weight' => 80],
        ['setting_id' => 4, 'key_name' => 'cache', 'load_policy' => 'no', 'weight' => 70],
    ],
];
$nextTables = $currentTables;
$nextTables['app_settings'][] = ['setting_id' => 5, 'key_name' => 'module_alpha', 'load_policy' => 'yes', 'weight' => 60];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'base_url', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'site_title'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'module_alpha'
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
       lag(label, 1, 'base_url') OVER (ORDER BY score DESC, id) AS marker
  FROM q
INTERSECT
SELECT setting_id AS id,
       key_name AS label,
       lag(key_name, 1, 'base_url') OVER (ORDER BY weight DESC, setting_id) AS marker
  FROM app_settings
 ORDER BY marker, id
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'application-compound-intersect-lag-lead-recursive-limit-current-source-recursive-limit',
    'sqlShape' => 'WITH RECURSIVE LIMIT/OFFSET feeding lag-window INTERSECT arms with final ORDER BY/LIMIT/OFFSET',
    'applicationUse' => 'Copied app_settings import previews can skip a recursive seed source, intersect lag-window current rows with copied setting rows, and admit the next module setting at the final compound LIMIT boundary.',
    'currentLabels' => array_column($plan['currentRows'], 'label'),
    'nextLabels' => array_column($plan['nextRows'], 'label'),
    'recursiveSkipped' => $plan['recursive']['currentSkippedLabels'],
    'newAdmittedLabels' => $plan['boundary']['gainedLabels'],
    'leadNextMarkers' => array_column($plan['leadDiagnostics']['next'], 'lead_marker'),
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE/window/compound INTERSECT/LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['currentLabels'] !== ['site_title', 'cache']) {
        fwrite(STDERR, "unexpected current labels\n");
        exit(1);
    }
    if ($result['nextLabels'] !== ['module_alpha', 'site_title', 'cache']) {
        fwrite(STDERR, "unexpected next labels\n");
        exit(1);
    }
    if ($result['recursiveSkipped'] !== ['base_url']) {
        fwrite(STDERR, "recursive offset did not skip the seed row\n");
        exit(1);
    }
    if ($result['newAdmittedLabels'] !== ['module_alpha']) {
        fwrite(STDERR, "next-source module setting did not cross the final LIMIT boundary\n");
        exit(1);
    }
    echo "application-compound-intersect-lag-lead-recursive-limit-current-source-recursive-limit self-test passed\n";
}

return $result;
