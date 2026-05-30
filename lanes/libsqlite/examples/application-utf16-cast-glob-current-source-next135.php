<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CastGlobCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value_bytes' => $enc('plugin_cache ', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value_bytes' => $enc('plugin_blob ', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['option_id' => 4, 'option_name' => 'broken_import', 'option_value_bytes' => "p\0x", 'text_encoding' => 2],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'option_value_bytes' => $enc('plugin_blob', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['option_id' => 5, 'option_name' => 'fresh_plugin', 'option_value_bytes' => $enc('plugin_cache_new', 3), 'text_encoding' => 3],
];

$plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::optionRowValuePlan(
    $currentRows,
    $nextRows,
    'plugin_*',
);

$summary = [
    'scenario' => 'application-utf16-cast-glob-current-source-next135',
    'applicationUse' => 'Copied wp_options imports can compare UTF-16 option_value payloads with CAST(option_value AS TEXT) GLOB while preserving binary GLOB residuals, malformed-row diagnostics, and current/next source invalidation.',
    'range' => $plan['range'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'currentMalformedRowids' => $plan['currentMalformedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['range'] === ['lowerInclusive' => 'plugin_', 'upperBound' => 'plugin`']);
    assert($summary['currentRowids'] === [1, 2, 3]);
    assert($summary['nextRowids'] === [1, 2, 3, 5]);
    assert($summary['enteredRowids'] === [5]);
    assert($summary['currentMalformedRowids'] === [4]);
    assert(in_array('malformed-text', $summary['invalidationReasons'], true));
    echo "application-utf16-cast-glob-current-source-next135 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
