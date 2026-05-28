<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan;

require __DIR__ . '/../../../tools/bootstrap.php';

$current = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$next = [
    ...$current,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric FROM (
    SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q
    UNION ALL
    SELECT option_id AS id,
           option_name AS label,
           row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
      FROM wp_options
     WHERE autoload = 'yes'
)
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY metric, label
 LIMIT 4 OFFSET 1
SQL;

$payload = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan::compare(
    $sql,
    ['wp_options' => $current],
    ['wp_options' => $next],
);

$out = [
    'scenario' => 'wordpress-compound-select-window-recursive-limit-current-source-next258',
    'wordpressUse' => 'Copied wp_options preview queries keep next-source plugin rows hidden until the current-source compound/window/recursive LIMIT page high-water row is acknowledged.',
    'status' => $payload['status'],
    'currentHighWaterLabel' => $payload['compoundWindowRecursiveSourceHandoffNext258']['currentHighWaterLabel'],
    'nextCandidateLabel' => $payload['compoundWindowRecursiveSourceHandoffNext258']['nextCandidateLabel'],
    'requiredAckCount' => $payload['compoundWindowRecursiveSourceHandoffNext258']['requiredSourceHandoffAckCount'],
    'nextExposure' => $payload['compoundWindowRecursiveSourceHandoffNext258']['nextExposure'],
    'dependencyClosure' => $payload['dependency_closure'],
];

if (($out['status'] ?? null) !== 'compound-select-window-recursive-limit-current-source-next258-ready') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next258 self-test failed\n");
    exit(1);
}
if (($out['currentHighWaterLabel'] ?? null) !== 'seed:2:3:4' || ($out['nextCandidateLabel'] ?? null) !== 'plugin_prime') {
    fwrite(STDERR, "wordpress-compound-select-window-recursive-limit-current-source-next258 boundary guard failed\n");
    exit(1);
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
