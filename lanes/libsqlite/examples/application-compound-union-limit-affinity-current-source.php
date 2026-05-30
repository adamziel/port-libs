<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'rank_value' => 1, 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'rank_value' => '1', 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'rank_value' => 2, 'payload' => 'plugins', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'rank_value' => '2', 'payload' => 'theme', 'autoload' => 'yes'],
];
$currentStage = [
    ['option_id' => 101, 'option_name' => 'siteurl_copy', 'rank_value' => 1.0, 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'plugins_copy', 'rank_value' => 2.0, 'payload' => 'plugins', 'autoload' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'new_numeric_boundary', 'rank_value' => 3, 'payload' => 'new', 'autoload' => 'yes'],
];
$nextStage = [
    ...$currentStage,
    ['option_id' => 106, 'option_name' => 'new_numeric_duplicate', 'rank_value' => 3.0, 'payload' => 'new', 'autoload' => 'yes'],
    ['option_id' => 107, 'option_name' => 'new_text_boundary', 'rank_value' => '3', 'payload' => 'new', 'autoload' => 'yes'],
];

$sql = <<<'SQL'
SELECT rank_value AS rank_value, payload AS payload
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT rank_value AS rank_value, payload AS payload
  FROM wp_option_stage
 WHERE autoload = 'yes'
 ORDER BY rank_value ASC, payload ASC
 LIMIT 4 OFFSET 1
SQL;

$plan = SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan::compareUnionLimitAffinity(
    $sql,
    ['wp_options' => $currentOptions, 'wp_option_stage' => $currentStage],
    ['wp_options' => $nextOptions, 'wp_option_stage' => $nextStage],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options merge rows where UNION removes numeric duplicate ranks before a final LIMIT/OFFSET boundary, while text ranks with the same characters remain distinct SQLite values.',
    'status' => $plan['status'],
    'currentLimitedRows' => $plan['currentRows'],
    'nextLimitedRows' => $plan['nextRows'],
    'currentSkippedDuplicates' => $plan['affinity']['currentSkippedDuplicates'],
    'nextSkippedDuplicates' => $plan['affinity']['nextSkippedDuplicates'],
    'replanReasons' => $plan['replanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
