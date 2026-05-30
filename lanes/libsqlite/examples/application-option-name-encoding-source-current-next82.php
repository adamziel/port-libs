<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$rows = [
    ['option_id' => 1, 'name' => 'Plugin_100%_Enabled', 'encoding' => 'UTF-8', 'autoload' => 'no'],
    ['option_id' => 2, 'name' => 'plugin_100%_enabled', 'encoding' => 'UTF-16LE', 'autoload' => 'yes'],
    ['option_id' => 3, 'name' => 'plugin_100x_enabled', 'encoding' => 'UTF-16BE', 'autoload' => 'yes'],
    ['option_id' => 4, 'name' => 'plugin_😀_cache', 'encoding' => 'UTF-16LE', 'autoload' => 'yes'],
    ['option_id' => 5, 'name' => 'theme_alpha', 'encoding' => 'UTF-8', 'autoload' => 'yes'],
];

$sourceRows = array_map(
    static fn (array $row): array => [
        'option_id' => $row['option_id'],
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($row['name'], $row['encoding']),
        'text_encoding' => match ($row['encoding']) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'option_name' => $row['name'],
        'autoload' => $row['autoload'],
    ],
    $rows,
);

$literalPercent = SQLiteEncodingCollationSourceCursor::optionRowNameScan(
    $sourceRows,
    'plugin\_100\%%',
    'LIKE',
    'NOCASE',
    '\\',
);
$emoji = SQLiteEncodingCollationSourceCursor::optionRowNameScan(
    $sourceRows,
    'plugin_😀%',
    'LIKE',
    'BINARY',
    null,
    true,
);
$glob = SQLiteEncodingCollationSourceCursor::optionRowNameScan(
    $sourceRows,
    'plugin_*',
    'GLOB',
    'BINARY',
);

$result = [
    'scenario' => 'copied wp_options option_name current-source encoding LIKE/GLOB current-next82',
    'literalPercentRowids' => array_column($literalPercent, 'rowid'),
    'literalPercentEncodings' => array_column($literalPercent, 'textEncoding'),
    'emojiRowids' => array_column($emoji, 'rowid'),
    'globRowids' => array_column($glob, 'rowid'),
    'dependencies' => ['sqlite-encoding-source-cursor', 'sqlite-like-glob-collation'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['literalPercentRowids'] === [1, 2]);
    assert($result['literalPercentEncodings'] === ['UTF-8', 'UTF-16LE']);
    assert($result['emojiRowids'] === [4]);
    assert($result['globRowids'] === [2, 3, 4]);
    echo "application-option-name-encoding-source-current-next82 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
