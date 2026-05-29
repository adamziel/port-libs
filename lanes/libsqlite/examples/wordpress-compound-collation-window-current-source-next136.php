<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteCompoundCollationWindowCurrentSourceNextPlan;

$currentTables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'Home', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ],
    'network_options' => [
        ['option_id' => 10, 'option_name' => 'home', 'autoload' => 'yes'],
        ['option_id' => 11, 'option_name' => 'network_home', 'autoload' => 'yes'],
    ],
];
$nextTables = $currentTables;
$nextTables['wp_options'][] = ['option_id' => 3, 'option_name' => 'Theme_Mod', 'autoload' => 'yes'];
$nextTables['network_options'][] = ['option_id' => 12, 'option_name' => 'theme_mod', 'autoload' => 'yes'];

$sql = <<<'SQL'
SELECT option_name COLLATE NOCASE AS name,
       row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_name AS name,
       row_number() OVER (ORDER BY option_name COLLATE NOCASE) AS rn
  FROM network_options
 WHERE autoload = 'yes'
 ORDER BY name COLLATE NOCASE, rn
SQL;

$plan = SQLiteCompoundCollationWindowCurrentSourceNextPlan::compareNext136($sql, $currentTables, $nextTables);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'compound-collation-window-current-source-next136-ready');
    assert($plan['compound']['leftCollations'] === ['name' => 'NOCASE']);
    assert(in_array('window-before-compound-source', $plan['replanReasons'], true));
    assert($plan['nextNames'] === ['Home', 'network_home', 'siteurl', 'Theme_Mod']);
    echo "wordpress-compound-collation-window-current-source-next136 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options compound SELECT collation window current-source next136',
    'status' => $plan['status'],
    'currentNames' => $plan['currentNames'],
    'nextNames' => $plan['nextNames'],
    'leftCollations' => $plan['compound']['leftCollations'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
