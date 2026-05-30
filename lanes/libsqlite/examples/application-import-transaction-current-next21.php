<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteImportTransactionPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ['option_id' => 130, 'option_name' => 'transient_feed', 'option_value' => 'stale', 'autoload' => 'no'],
];

$stagedRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
    ['option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
];

$plan = SQLiteImportTransactionPlan::plan($currentRows, $stagedRows, [
    'database_path' => '/tmp/wp-current-import.sqlite',
    'delete_missing' => true,
    'journal_mode' => 'persist',
    'page_size' => 1024,
]);

echo json_encode([
    'application_path' => 'wp_options current import transaction',
    'begin_mode' => $plan['begin']['mode'],
    'updated' => count($plan['updated']),
    'inserted' => count($plan['inserted']),
    'deleted' => count($plan['deleted']),
    'dirty_pages' => $plan['dirty_pages'],
    'sync_targets' => array_column($plan['sync_sequence'], 'target'),
    'final_option_names' => array_column($plan['final_rows'], 'option_name'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
