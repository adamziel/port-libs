<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 126],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 98],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 78],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 116],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 142)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       group_concat(label, '>') OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS trail
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS trail
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       '2' AS trail
  FROM wp_options
 WHERE option_name = 'home'
 ORDER BY trail DESC, id
 LIMIT 5 OFFSET 1
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next212',
    'wordpressUse' => 'Copied wp_options preview queries can fence string-window recursive dependency rows before staged next-source option rows cross an EXCEPT and final compound LIMIT boundary.',
    'status' => $plan['status'],
    'currentTokenLength' => strlen($plan['sourceWindow']['currentToken']),
    'nextTokenLength' => strlen($plan['sourceWindow']['nextToken']),
    'functions' => $plan['windows']['functions'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next212-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next212 self-test failed\n");
    exit(1);
}
if ($payload['currentTokenLength'] !== 64 || $payload['nextTokenLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next212 token guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
