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
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 5, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 70],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 120)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 5
      FROM q
     WHERE id < 7
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       sum(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC
           ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric
  FROM (
        SELECT id, label, max(score) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               sum(score) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS metric
          FROM wp_options
         WHERE autoload = 'yes'
       )
 WHERE metric >= 90
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       score AS metric
  FROM wp_options
 WHERE option_name = 'home'
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMaxSumIntersectLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-max-sum-intersect-limit',
    'wordpressUse' => 'Copied wp_options previews can fence aggregate window metrics after recursive queue exhaustion, then re-evaluate current versus staged rows through INTERSECT/EXCEPT and final LIMIT boundaries.',
    'status' => $plan['status'],
    'currentRows' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextRows' => $plan['sourceWindow']['nextAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'functions' => $plan['windows']['functions'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-max-sum-intersect-limit-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-max-sum-intersect-limit self-test failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-max-sum-intersect-limit token guard failed\n");
    exit(1);
}
if (!in_array('plugin_prime', $payload['nextRows'], true)) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-max-sum-intersect-limit next-source boundary failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
