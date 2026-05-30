<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;

$enc = static fn (string $text, int|string $encoding): string => SQLiteUtf16CollationAffinityCursor::encodeText($text, $encoding);

$rows = [
    ['option_id' => 1, 'option_value_bytes' => $enc('02', 'UTF-16LE'), 'text_encoding' => 2, 'option_name' => 'wp_plugin_priority', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_value_bytes' => $enc('10', 'UTF-16BE'), 'text_encoding' => 3, 'option_name' => 'wp_plugin_priority', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_value_bytes' => $enc('Plugin_Alpha ', 'UTF-16LE'), 'text_encoding' => 2, 'option_name' => 'wp_plugin_slug', 'autoload' => 'no'],
    ['option_id' => 4, 'option_value' => 2, 'option_name' => 'wp_plugin_priority_int', 'autoload' => 'yes'],
];

$numeric = SQLiteUtf16CollationAffinityCursor::optionRowValueSeek(
    $rows,
    ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3],
    'NUMERIC',
    'NUMERIC',
);

$text = SQLiteUtf16CollationAffinityCursor::optionRowValueSeek(
    $rows,
    'Plugin_Alpha',
    'TEXT',
    'TEXT',
    'RTRIM',
);

echo json_encode(
    [
        'scenario' => 'application-utf16-collation-affinity-current-source-next85',
        'numericRowidsFromTwo' => array_column($numeric, 'rowid'),
        'numericCoercions' => array_map(static fn (array $row): array => [
            'rowid' => $row['rowid'],
            'value' => $row['value'],
            'storage' => $row['storage'],
            'encoding' => $row['encoding'],
            'comparisonToProbe' => $row['comparisonToProbe'],
        ], $numeric),
        'rtrimTextRowids' => array_column($text, 'rowid'),
        'firstRtrimText' => $text[0] ?? null,
        'dependencies' => ['sqlite-utf16-decode', 'sqlite-affinity-comparison'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
) . "\n";
