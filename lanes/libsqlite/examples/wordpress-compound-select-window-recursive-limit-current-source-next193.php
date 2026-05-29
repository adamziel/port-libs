<?php

declare(strict_types=1);

foreach (glob(dirname(__DIR__) . '/src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 126],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 114],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 89],
];
$next = [
    ...$current,
    ['option_id' => 4, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 118],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     LIMIT 7 OFFSET 2
)
SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id, label, score AS metric FROM q
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 2
SQL;

$plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$payload = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next193',
    'wordpressUse' => 'Copied wp_options preview queries can fence recursive dependency rows and window-ranked autoload rows with a current-source signature before staged next-source rows are admitted across the compound LIMIT boundary.',
    'status' => $plan['status'],
    'sourceSignatureLength' => strlen($plan['currentSourceNext193']['sourceSignature']),
    'nextSignatureLength' => strlen($plan['currentSourceNext193']['nextSourceSignature']),
    'admission' => $plan['currentSourceNext193']['admission'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($payload['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next193-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next193 self-test failed\n");
    exit(1);
}
if ($payload['sourceSignatureLength'] !== 64 || $payload['nextSignatureLength'] !== 64) {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next193 signature guard failed\n");
    exit(1);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
