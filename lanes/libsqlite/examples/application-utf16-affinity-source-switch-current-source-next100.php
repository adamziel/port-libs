<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinitySourceSwitchPlan;

$enc = static fn (string $text, int|string $encoding): string => SQLiteUtf16CollationAffinityCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $value, int|string $encoding, string $name): array => [
    'option_id' => $id,
    'option_name' => $name,
    'option_value_bytes' => $enc($value, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, '02', 'UTF-16LE', 'wp_plugin_priority'),
    $row(2, '2.0', 'UTF-16BE', 'wp_plugin_priority_real'),
    $row(3, '10', 'UTF-16LE', 'wp_plugin_priority_later'),
    $row(4, 'Plugin_Alpha ', 'UTF-16LE', 'wp_plugin_slug'),
];

$nextRows = [
    $row(1, '02', 'UTF-16BE', 'wp_plugin_priority'),
    $row(2, '2x', 'UTF-16BE', 'wp_plugin_priority_real'),
    $row(3, '10', 'UTF-16LE', 'wp_plugin_priority_later'),
    $row(4, 'Plugin_Alpha', 'UTF-16LE', 'wp_plugin_slug'),
    $row(5, '2', 'UTF-16LE', 'wp_plugin_priority_new'),
];

$plan = SQLiteUtf16CollationAffinitySourceSwitchPlan::optionRowValueSourceSwitch(
    $currentRows,
    $nextRows,
    ['valueBytes' => $enc('2', 'UTF-16LE'), 'textEncoding' => 2],
    'NUMERIC',
    'NONE',
    'BINARY',
    'wp-options-current',
    'wp-options-next',
);

echo json_encode(
    [
        'scenario' => 'application-utf16-affinity-source-switch-current-source-next100',
        'cursorInvalidated' => $plan['cursorInvalidated'],
        'invalidationReasons' => $plan['invalidationReasons'],
        'currentRowids' => $plan['currentRowids'],
        'nextRowids' => $plan['nextRowids'],
        'enteredRowids' => $plan['enteredRowids'],
        'changedEncodingRowids' => $plan['changedEncodingRowids'],
        'changedBytesRowids' => $plan['changedBytesRowids'],
        'changedCoercedStorageRowids' => $plan['changedCoercedStorageRowids'],
        'changedComparisonRowids' => $plan['changedComparisonRowids'],
        'dependencies' => $plan['dependencies'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
) . "\n";
