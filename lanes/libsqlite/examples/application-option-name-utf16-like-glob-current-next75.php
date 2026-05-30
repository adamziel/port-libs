<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobCurrentNextCursor;

$encode = static fn (string $text): string => SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($text, 'UTF-16LE');

$rows = [
    ['option_id' => 1, 'option_name_utf16' => $encode('SiteURL'), 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name_utf16' => $encode('site_admin_email'), 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name_utf16' => $encode('plugin_100%_enabled'), 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name_utf16' => $encode('Plugin_100%_Enabled'), 'autoload' => 'no'],
    ['option_id' => 5, 'option_name_utf16' => $encode('plugin_é_enabled'), 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name_utf16' => $encode('plugin_😀_enabled'), 'autoload' => 'yes'],
];

$likeRows = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan(
    $rows,
    'plugin\_100\%%',
    'LIKE',
    'UTF-16LE',
    'NOCASE',
    '\\',
);

$globRows = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan(
    $rows,
    'plugin_[😀-😀]*',
    'GLOB',
    'UTF-16LE',
    'BINARY',
);

echo json_encode(
    [
        'scenario' => 'application-option-name-utf16-like-glob-current-next75',
        'likeLiteralPercentRowids' => array_column($likeRows, 'rowid'),
        'globEmojiRowids' => array_column($globRows, 'rowid'),
        'firstLikeRow' => $likeRows[0] ?? null,
        'dependencies' => ['sqlite-utf16-like-glob-current-next75'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
) . "\n";
