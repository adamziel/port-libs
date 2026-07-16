<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteImportTransactionErrorYieldPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

$stagedRows = [
    ['option_name' => 'blogdescription', 'option_value' => 'Imported with Data Liberation', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'siteurl', 'option_value' => 'duplicate-name', 'autoload' => 'no'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
];

$plan = SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows,
    $stagedRows,
    ['database_path' => '/tmp/wp-import-error-current-next29.sqlite', 'page_size' => 1024]
);

echo json_encode([
    'status' => $plan['status'],
    'yieldedStatuses' => array_column($plan['yielded'], 'status'),
    'yieldedCurrentOptionIds' => array_column($plan['yielded'], 'current_option_id'),
    'yieldedNextOptionIds' => array_column($plan['yielded'], 'next_option_id'),
    'wpErrorCodes' => array_column($plan['errors'], 'code'),
    'rollback' => $plan['rollback'],
    'finalOptionNames' => array_column($plan['final_rows'], 'option_name'),
    'applicationUse' => 'Preview copied wp_options imports that yield current/next row state and WP_Error-shaped diagnostics for statement failures before rolling the import transaction back, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
