<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'parent_id' => 0, 'priority' => '8', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'parent_id' => 1, 'priority' => 2, 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'theme_variant', 'parent_id' => 1, 'priority' => '1', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'theme_variant_child', 'parent_id' => 2, 'priority' => 3, 'load_policy' => 'no'],
    ['setting_id' => 50, 'key_name' => 'direct_load_policy', 'parent_id' => -1, 'priority' => 1, 'load_policy' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 6, 'key_name' => 'module_rules', 'parent_id' => 1, 'priority' => 1.5, 'load_policy' => 'yes'],
    ['setting_id' => 7, 'key_name' => 'module_rules_child', 'parent_id' => 6, 'priority' => '2', 'load_policy' => 'no'],
];

$sql = <<<'SQL'
WITH RECURSIVE setting_walk(id, label, queue_key, depth) AS (
    SELECT setting_id, key_name, priority, 0
      FROM app_settings
     WHERE parent_id = 0
    UNION ALL
    SELECT child.setting_id, child.key_name, child.priority, setting_walk.depth + 1
      FROM app_settings AS child
      JOIN setting_walk ON child.parent_id = setting_walk.id
     WHERE setting_walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT id,
       label,
       depth,
       queue_key,
       row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank
  FROM setting_walk
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       0 AS depth,
       priority AS queue_key,
       row_number() OVER (ORDER BY priority ASC, setting_id ASC) AS visit_rank
  FROM app_settings
 WHERE parent_id = -1
 ORDER BY visit_rank ASC, queue_key ASC, label ASC
 LIMIT 7
SQL;

$summary = SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compare(
    $sql,
    ['app_settings' => $currentOptions],
    ['app_settings' => $nextOptions],
);

$result = [
    'scenario' => 'application-compound-recursive-window-order-current-source',
    'applicationUse' => 'Copied app_settings dependency trees can be walked with recursive queue ORDER BY, ranked in each compound arm with window functions, and then sorted by the final compound ORDER BY before import diagnostics are shown.',
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'replanReasons' => $summary['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE, window, and compound ORDER execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($result['nextLabels'][1] ?? null) !== 'module_rules') {
        fwrite(STDERR, "application-compound-recursive-window-order-current-source self-test failed\n");
        exit(1);
    }

    echo "application-compound-recursive-window-order-current-source self-test passed\n";
}

return $result;
