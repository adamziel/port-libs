<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;

$enc = static fn (string $text, int|string $encoding): string => SQLiteUtf16CollationAffinityCursor::encodeText($text, $encoding);

$rows = [
    ['setting_id' => 1, 'key_value_bytes' => $enc('02', 'UTF-16LE'), 'text_encoding' => 2, 'key_name' => 'module_priority', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_value_bytes' => $enc('10', 'UTF-16BE'), 'text_encoding' => 3, 'key_name' => 'module_priority', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_value_bytes' => $enc('Module_Alpha ', 'UTF-16LE'), 'text_encoding' => 2, 'key_name' => 'module_slug', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_value' => 2, 'key_name' => 'module_priority_int', 'load_policy' => 'yes'],
];

$numeric = SQLiteUtf16CollationAffinityCursor::settingRowValueSeek(
    $rows,
    ['valueBytes' => $enc('2', 'UTF-16BE'), 'textEncoding' => 3],
    'NUMERIC',
    'NUMERIC',
);

$text = SQLiteUtf16CollationAffinityCursor::settingRowValueSeek(
    $rows,
    'Module_Alpha',
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
