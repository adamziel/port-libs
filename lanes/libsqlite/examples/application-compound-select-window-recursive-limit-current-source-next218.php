<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$next = [
    ...$current,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS rn
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareUnionAllIntersectWindowLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-next218',
    'applicationUse' => 'Copied wp_options previews can detect when a next-source autoloaded option changes row_number ranks across UNION ALL/INTERSECT after a bounded recursive queue.',
    'status' => $plan['status'],
    'currentLabels' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextLabels' => $plan['sourceWindow']['nextAdmittedLabels'],
    'nextOnly' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next218-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-next218 self-test failed\n");
    exit(1);
}
if ($payload['nextOnly'] !== ['plugin_prime'] || $payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-next218 token/window guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
