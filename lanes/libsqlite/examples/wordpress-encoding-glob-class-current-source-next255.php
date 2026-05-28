<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Plan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding, mixed $value): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
    'option_value' => $value,
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

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Plan::wordpressGlobClassFallbackPlan($current, $next, '[^A-Za-z]plugin*');

$summary = [
    'scenario' => 'wordpress-encoding-glob-class-current-source-next255',
    'wordpressUse' => 'Copied wp_options option-name scans must fall back to residual GLOB matching for bracket classes with no fixed prefix while preserving UTF-8/UTF-16 decoded text, text-affinity coercion, and current-source invalidation.',
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
    $summary['status'] !== 'encoding-collation-affinity-like-current-source-next255'
    || $summary['rangeUsable'] !== false
    || $summary['fullScanResidualRequired'] !== true
    || $summary['currentRowids'] !== [2, 3]
    || $summary['nextRowids'] !== [2, 4, 3]
    || $summary['enteredRowids'] !== [4]
) {
    fwrite(STDERR, "wordpress-encoding-glob-class-current-source-next255 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "wordpress-encoding-glob-class-current-source-next255 self-test passed\n";
