<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'freshness' => 100],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'freshness' => 90],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'freshness' => 80],
        ['option_id' => 4, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'freshness' => 70],
    ],
    'network_current' => [
        ['option_name' => 'home', 'autoload' => 'yes', 'source_rank' => 2],
    ],
    'stale_option_audit' => [
        ['option_name' => 'rewrite_rules', 'autoload' => 'no', 'source_rank' => 1],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'freshness' => 110];
$nextTables['network_current'][] = ['option_name' => 'siteurl', 'autoload' => 'yes', 'source_rank' => 2];

$sql = <<<'SQL'
SELECT option_name AS name,
       autoload,
       row_number() OVER (
           PARTITION BY autoload
           ORDER BY freshness DESC, option_id
       ) AS source_rank
  FROM wp_options
EXCEPT
SELECT option_name AS name,
       autoload,
       source_rank
  FROM network_current
EXCEPT
SELECT option_name AS name,
       autoload,
       source_rank
  FROM stale_option_audit
 ORDER BY source_rank DESC, name
 LIMIT 1, 3
SQL;

$plan = SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan::compareExceptWindowLimitSources($sql, $currentTables, $nextTables);
$result = [
    'scenario' => 'application-compound-select-except-window-limit-current-source-next148',
    'sqlShape' => 'SELECT window(...) FROM wp_options EXCEPT SELECT audit rows EXCEPT SELECT stale rows ORDER BY output columns LIMIT offset,count',
    'applicationUse' => 'Copied wp_options import audits can subtract already-current and stale audit rows after per-arm window ranks are evaluated, then apply the final compound ORDER BY and comma LIMIT before showing the changed option boundary.',
    'currentNames' => array_column($plan['currentRows'], 'name'),
    'nextNames' => array_column($plan['nextRows'], 'name'),
    'removedNames' => $plan['exceptTrace']['nextRemovedNames'],
    'replanReasons' => $plan['replanReasons'],
    'dependency' => 'native PHP parser-level compound SELECT/window/LIMIT execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['currentNames'] !== ['siteurl']) {
        fwrite(STDERR, "unexpected current compound names\n");
        exit(1);
    }
    if ($result['nextNames'] !== ['home', 'plugin_alpha']) {
        fwrite(STDERR, "unexpected next compound names\n");
        exit(1);
    }
    if (!in_array('compound-final-comma-limit', $result['replanReasons'], true)) {
        fwrite(STDERR, "missing compound comma LIMIT reason\n");
        exit(1);
    }
    echo "application-compound-select-except-window-limit-current-source-next148 self-test passed\n";
}

return $result;
