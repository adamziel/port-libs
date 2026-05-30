<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteGlobCursor;

$entries = [
    ['key' => 'Plugin_Alpha', 'rowid' => 1, 'payload' => ['option_name' => 'Plugin_Alpha', 'autoload' => 'yes']],
    ['key' => 'plugin_alpha', 'rowid' => 2, 'payload' => ['option_name' => 'plugin_alpha', 'autoload' => 'yes']],
    ['key' => 'plugin_é_enabled', 'rowid' => 3, 'payload' => ['option_name' => 'plugin_é_enabled', 'autoload' => 'yes']],
    ['key' => 'plugin_É_enabled', 'rowid' => 4, 'payload' => ['option_name' => 'plugin_É_enabled', 'autoload' => 'no']],
    ['key' => 'theme_alpha', 'rowid' => 5, 'payload' => ['option_name' => 'theme_alpha', 'autoload' => 'yes']],
];

$pluginCursor = new SQLiteGlobCursor($entries, 'plugin_*', 'NOCASE');
$unicodeCursor = new SQLiteGlobCursor($entries, 'plugin_[À-ÿ]*', 'BINARY');

echo json_encode(
    [
        'scenario' => 'wordpress-option-name-glob-current-next',
        'pluginPrefixPlan' => $pluginCursor->currentNextPlan(),
        'pluginPrefixRowids' => array_column($pluginCursor->matchedRows(), 'rowid'),
        'unicodeGlobRowids' => array_column($unicodeCursor->matchedRows(), 'rowid'),
        'dependencies' => ['sqlite-glob-current-next-cursor'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
) . "\n";
