<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CastGlobCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value_bytes' => $enc('module_cache ', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name' => 'feature_flags', 'key_value_bytes' => $enc('module_blob ', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['setting_id' => 4, 'key_name' => 'broken_payload', 'key_value_bytes' => "p\0x", 'text_encoding' => 2],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name' => 'feature_flags', 'key_value_bytes' => $enc('module_blob', 2), 'text_encoding' => 2, 'storage_class' => 'blob'],
    ['setting_id' => 5, 'key_name' => 'fresh_module', 'key_value_bytes' => $enc('module_cache_new', 3), 'text_encoding' => 3],
];

$plan = SQLiteUtf16CastGlobCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    'module_*',
);

$summary = [
    'scenario' => 'application-utf16-cast-glob-current-source-next135',
    'applicationUse' => 'Application setting imports can compare UTF-16 key_value payloads with CAST(key_value AS TEXT) GLOB while preserving binary GLOB residuals, malformed-row diagnostics, and current/next source invalidation.',
    'range' => $plan['range'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'currentMalformedRowids' => $plan['currentMalformedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['range'] === ['lowerInclusive' => 'module_', 'upperBound' => 'module`']);
    assert($summary['currentRowids'] === [1, 2, 3]);
    assert($summary['nextRowids'] === [1, 2, 3, 5]);
    assert($summary['enteredRowids'] === [5]);
    assert($summary['currentMalformedRowids'] === [4]);
    assert(in_array('malformed-text', $summary['invalidationReasons'], true));
    echo "application-utf16-cast-glob-current-source-next135 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
