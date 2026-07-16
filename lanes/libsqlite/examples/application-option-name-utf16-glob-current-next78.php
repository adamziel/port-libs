<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16GlobCurrentNextCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'Plugin_Alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_éclair', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_😀_cache', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'theme_alpha', 'autoload' => 'yes'],
];

$entries = array_map(
    static fn (array $row): array => [
        'encodedKey' => SQLiteUtf16GlobCurrentNextCursor::encodeUtf16($row['option_name'], 'UTF-16le'),
        'rowid' => $row['option_id'],
        'payload' => $row,
    ],
    $rows,
);

$prefixCursor = new SQLiteUtf16GlobCurrentNextCursor($entries, 'plugin_*', 'UTF-16le', 'NOCASE');
$unicodeCursor = new SQLiteUtf16GlobCurrentNextCursor($entries, 'plugin_[À-ÿ]*', 'UTF-16le', 'BINARY');
$emojiCursor = new SQLiteUtf16GlobCurrentNextCursor($entries, 'plugin_😀*', 'UTF-16le', 'BINARY');

$result = [
    'scenario' => 'copied wp_options UTF-16 option_name GLOB current-next78',
    'prefixPlan' => $prefixCursor->currentNextPlan(),
    'prefixRowids' => array_column($prefixCursor->matchedRows(), 'rowid'),
    'latinRowids' => array_column($unicodeCursor->matchedRows(), 'rowid'),
    'emojiRowids' => array_column($emojiCursor->matchedRows(), 'rowid'),
    'dependencies' => ['sqlite-utf16-glob-current-next-cursor'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['prefixPlan']['textEncoding'] === 'UTF-16le');
    assert($result['prefixPlan']['currentRowid'] === 1);
    assert($result['prefixRowids'] === [2, 3, 4]);
    assert($result['latinRowids'] === [3]);
    assert($result['emojiRowids'] === [4]);
    echo "application-option-name-utf16-glob-current-next78 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
