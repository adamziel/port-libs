<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 98],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_fresh', 'autoload' => 'yes', 'score' => 112],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 150)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       percent_rank() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       cume_dist() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS metric
  FROM wp_options
 WHERE option_name = 'plugin_old'
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::comparePercentRankCumeDistExceptLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'application-compound-select-window-recursive-limit-current-source-next219',
    'applicationUse' => 'Copied wp_options previews can fence percent_rank and cume_dist window rows after recursive queue LIMIT/OFFSET and before EXCEPT plus final LIMIT admits next-source plugin rows.',
    'status' => $plan['status'],
    'currentRows' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextRows' => $plan['sourceWindow']['nextAdmittedLabels'],
    'nextOnlyRows' => $plan['sourceWindow']['nextOnlyAdmittedLabels'],
    'functions' => $plan['windows']['functions'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next219-ready') {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-next219 self-test failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-next219 token guard failed\n");
    exit(1);
}
if (!in_array('plugin_fresh', $payload['nextOnlyRows'], true)) {
    fwrite(STDERR, "application-compound-select-window-recursive-limit-current-source-next219 next-source boundary failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
