<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding, mixed $value): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
    'key_value' => $value,
];

$current = [
    $row(1, '_site_transient_update_plugins', 1, 'transient'),
    $row(2, '9plugin_cache', 3, 'numeric-prefix'),
    $row(3, 'Äplugin_cache', 2, 'umlaut'),
];

$next = [
    $row(1, '_site_transient_update_plugins', 1, 'transient'),
    $row(2, '9plugin_cache', 3, 'numeric-prefix'),
    $row(3, 'äplugin_cache', 2, 'umlaut-lower'),
    $row(4, 'Éplugin_cache', 3, 'accented-new'),
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationGlobClassFallbackPlan($current, $next, '[^A-Za-z]plugin*');

$summary = [
    'scenario' => 'application-encoding-glob-class-current-source-next255',
    'applicationUse' => 'Copied app_settings key-name scans must fall back to residual GLOB matching for bracket classes with no fixed prefix while preserving UTF-8/UTF-16 decoded text, text-affinity coercion, and current-source invalidation.',
    'status' => $plan['status'],
    'rangeUsable' => $plan['rangeUsable'],
    'fullScanResidualRequired' => $plan['fullScanResidualRequired'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedTextRowids' => $plan['changedTextRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
];

if (
    $summary['status'] !== 'encoding-collation-affinity-like-current-source-nexttwoFiveFive'
    || $summary['rangeUsable'] !== false
    || $summary['fullScanResidualRequired'] !== true
    || $summary['currentRowids'] !== [2, 3]
    || $summary['nextRowids'] !== [2, 4, 3]
    || $summary['enteredRowids'] !== [4]
) {
    fwrite(STDERR, "application-encoding-glob-class-current-source-next255 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "application-encoding-glob-class-current-source-next255 self-test passed\n";
