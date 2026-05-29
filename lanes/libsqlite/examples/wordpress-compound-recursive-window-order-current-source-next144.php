<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'parent_id' => 0, 'priority' => '8', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'parent_id' => 1, 'priority' => 2, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'parent_id' => 1, 'priority' => '1', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'theme_mods_child', 'parent_id' => 2, 'priority' => 3, 'autoload' => 'no'],
    ['option_id' => 50, 'option_name' => 'direct_autoload', 'parent_id' => -1, 'priority' => 1, 'autoload' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_rules', 'parent_id' => 1, 'priority' => 1.5, 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_rules_child', 'parent_id' => 6, 'priority' => '2', 'autoload' => 'no'],
];

$sql = <<<'SQL'
WITH RECURSIVE option_walk(id, label, queue_key, depth) AS (
    SELECT option_id, option_name, priority, 0
      FROM wp_options
     WHERE parent_id = 0
    UNION ALL
    SELECT child.option_id, child.option_name, child.priority, option_walk.depth + 1
      FROM wp_options AS child
      JOIN option_walk ON child.parent_id = option_walk.id
     WHERE option_walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT id,
       label,
       depth,
       queue_key,
       row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank
  FROM option_walk
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       0 AS depth,
       priority AS queue_key,
       row_number() OVER (ORDER BY priority ASC, option_id ASC) AS visit_rank
  FROM wp_options
 WHERE parent_id = -1
 ORDER BY visit_rank ASC, queue_key ASC, label ASC
 LIMIT 7
SQL;

$summary = SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compareNext144(
    $sql,
    ['wp_options' => $currentOptions],
    ['wp_options' => $nextOptions],
);

$result = [
    'scenario' => 'wordpress-compound-recursive-window-order-current-source-next144',
    'wordpressUse' => 'Copied wp_options dependency trees can be walked with recursive queue ORDER BY, ranked in each compound arm with window functions, and then sorted by the final compound ORDER BY before import diagnostics are shown.',
    'currentLabels' => array_column($summary['currentRows'], 'label'),
    'nextLabels' => array_column($summary['nextRows'], 'label'),
    'replanReasons' => $summary['replanReasons'],
    'dependency' => 'native PHP SELECT SQL recursive CTE, window, and compound ORDER execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($result['nextLabels'][1] ?? null) !== 'plugin_rules') {
        fwrite(STDERR, "wordpress-compound-recursive-window-order-current-source-next144 self-test failed\n");
        exit(1);
    }

    echo "wordpress-compound-recursive-window-order-current-source-next144 self-test passed\n";
}

return $result;
