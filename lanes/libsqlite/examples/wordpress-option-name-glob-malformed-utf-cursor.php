<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteGlobCursor;

$entries = [
    ['key' => 'plugin_alpha', 'rowid' => 1, 'payload' => ['option_name' => 'plugin_alpha', 'autoload' => 'yes']],
    ['key' => "plugin_\xc3", 'rowid' => 2, 'payload' => ['option_name' => "plugin_\xc3", 'autoload' => 'yes']],
    ['key' => "plugin_\xc3A", 'rowid' => 3, 'payload' => ['option_name' => "plugin_\xc3A", 'autoload' => 'no']],
    ['key' => 'plugin_é', 'rowid' => 4, 'payload' => ['option_name' => 'plugin_é', 'autoload' => 'yes']],
    ['key' => "plugin_\xe2\x82", 'rowid' => 5, 'payload' => ['option_name' => "plugin_\xe2\x82", 'autoload' => 'yes']],
    ['key' => 'theme_alpha', 'rowid' => 6, 'payload' => ['option_name' => 'theme_alpha', 'autoload' => 'yes']],
];

$malformedPrefix = new SQLiteGlobCursor($entries, "plugin_\xc3*", 'BINARY');
$unicodeClass = new SQLiteGlobCursor($entries, 'plugin_[À-ÿ]*', 'BINARY');

echo json_encode(
    [
        'scenario' => 'wordpress-option-name-glob-malformed-utf-current-next74',
        'malformedPrefixPlan' => $malformedPrefix->currentNextPlan(),
        'malformedPrefixRowids' => array_column($malformedPrefix->matchedRows(), 'rowid'),
        'malformedPrefixDamagedRows' => array_column($malformedPrefix->matchedRows(), 'malformedUtf8'),
        'unicodeClassRowids' => array_column($unicodeClass->matchedRows(), 'rowid'),
        'dependencies' => ['sqlite-glob-current-next-cursor'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
) . "\n";
