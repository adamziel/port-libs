<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLikeCurrentNextCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$entries = [
    ['key' => 'siteurl', 'rowid' => 1, 'payload' => ['option_name' => 'siteurl', 'autoload' => 'yes']],
    ['key' => 'SiteURL', 'rowid' => 2, 'payload' => ['option_name' => 'SiteURL', 'autoload' => 'no']],
    ['key' => 'siteurl ', 'rowid' => 3, 'payload' => ['option_name' => 'siteurl ', 'autoload' => 'no']],
    ['key' => 'site_admin_email', 'rowid' => 4, 'payload' => ['option_name' => 'site_admin_email', 'autoload' => 'yes']],
    ['key' => 'Site_Admin_Email', 'rowid' => 5, 'payload' => ['option_name' => 'Site_Admin_Email', 'autoload' => 'yes']],
    ['key' => 'plugin_100%_enabled', 'rowid' => 7, 'payload' => ['option_name' => 'plugin_100%_enabled', 'autoload' => 'yes']],
    ['key' => 'Plugin_100%_Enabled', 'rowid' => 8, 'payload' => ['option_name' => 'Plugin_100%_Enabled', 'autoload' => 'no']],
    ['key' => 'plugin_100x_enabled', 'rowid' => 9, 'payload' => ['option_name' => 'plugin_100x_enabled', 'autoload' => 'yes']],
    ['key' => 'é_plugin', 'rowid' => 12, 'payload' => ['option_name' => 'é_plugin', 'autoload' => 'yes']],
    ['key' => 'É_plugin', 'rowid' => 13, 'payload' => ['option_name' => 'É_plugin', 'autoload' => 'no']],
];

$site = new SQLiteLikeCurrentNextCursor($entries, 'site%', 'NOCASE');
$plugin = new SQLiteLikeCurrentNextCursor($entries, 'plugin\_100\%%', 'NOCASE', '\\');
$unicode = new SQLiteLikeCurrentNextCursor($entries, 'é%', 'BINARY', null, true);

echo json_encode([
    'siteCurrentNext' => $site->currentNextPlan(),
    'siteMatchedRowids' => array_column($site->matchedRows(), 'rowid'),
    'pluginCurrentNext' => $plugin->currentNextPlan(),
    'pluginMatchedRowids' => array_column($plugin->matchedRows(), 'rowid'),
    'unicodeCurrentNext' => $unicode->currentNextPlan(),
    'unicodeMatchedRowids' => array_column($unicode->matchedRows(), 'rowid'),
], JSON_PRETTY_PRINT) . "\n";
