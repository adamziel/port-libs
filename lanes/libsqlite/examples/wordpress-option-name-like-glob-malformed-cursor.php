<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteGlobCursor;
use PortLibs\LibSqlite\SQLiteLikeCurrentNextCursor;

$entries = [
    ['key' => 'plugin_alpha', 'rowid' => 1, 'payload' => ['option_name' => 'plugin_alpha', 'autoload' => 'yes']],
    ['key' => "plugin_\xc3", 'rowid' => 2, 'payload' => ['option_name' => "plugin_\xc3", 'autoload' => 'yes']],
    ['key' => "plugin_\xc3A", 'rowid' => 3, 'payload' => ['option_name' => "plugin_\xc3A", 'autoload' => 'no']],
    ['key' => 'plugin_é', 'rowid' => 4, 'payload' => ['option_name' => 'plugin_é', 'autoload' => 'yes']],
    ['key' => "plugin_\xe2\x82", 'rowid' => 5, 'payload' => ['option_name' => "plugin_\xe2\x82", 'autoload' => 'yes']],
    ['key' => 'theme_alpha', 'rowid' => 6, 'payload' => ['option_name' => 'theme_alpha', 'autoload' => 'yes']],
];

$like = new SQLiteLikeCurrentNextCursor($entries, "plugin\_\xc3%", 'BINARY', '\\', true);
$glob = new SQLiteGlobCursor($entries, "plugin_\xc3*", 'BINARY');
$defaultLike = new SQLiteLikeCurrentNextCursor($entries, "plugin\_\xc3%", 'NOCASE', '\\');

echo json_encode(
    [
        'scenario' => 'wordpress-option-name-like-glob-malformed-current-next84',
        'likeBinaryPlan' => $like->currentNextPlan(),
        'likeBinaryRowids' => array_column($like->matchedRows(), 'rowid'),
        'likeBinaryMalformedRows' => array_column($like->matchedRows(), 'malformedUtf8'),
        'globBinaryRowids' => array_column($glob->matchedRows(), 'rowid'),
        'defaultLikeRejectedReason' => $defaultLike->currentNextPlan()['rejectedReason'],
        'dependencies' => ['sqlite-like-current-next-cursor', 'sqlite-glob-current-next-cursor'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
) . "\n";
