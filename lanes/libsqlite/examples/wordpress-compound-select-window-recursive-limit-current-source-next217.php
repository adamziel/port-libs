<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 96],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 94],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 146)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC) AS win_rank
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (
           PARTITION BY autoload
           ORDER BY score DESC
       ) AS win_rank
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       win_rank
  FROM (
        SELECT id, label, rank() OVER (ORDER BY score DESC) AS win_rank FROM q
        UNION ALL
        SELECT option_id AS id,
               option_name AS label,
               dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS win_rank
          FROM wp_options
         WHERE autoload = 'yes'
       )
 WHERE win_rank <= 6
 ORDER BY win_rank DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext217(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next217',
    'wordpressUse' => 'Copied wp_options previews can fence rank/dense_rank window rows after recursive queue exhaustion and before an INTERSECT plus final compound LIMIT boundary admits staged rows.',
    'status' => $plan['status'],
    'currentRows' => $plan['sourceWindow']['currentAdmittedLabels'],
    'nextRows' => $plan['sourceWindow']['nextAdmittedLabels'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'functions' => $plan['windows']['functions'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next217-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next217 self-test failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next217 token guard failed\n");
    exit(1);
}
if (!in_array('theme_mods_next', $payload['nextRows'], true)) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next217 next-source boundary failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
