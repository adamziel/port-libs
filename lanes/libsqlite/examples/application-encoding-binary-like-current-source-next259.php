<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('Module_Cache', 1), 'text_encoding' => 1],
    ['setting_id' => 2, 'key_name_bytes' => $enc('module_cache', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name_bytes' => $enc('MODULE_CACHE', 3), 'text_encoding' => 3],
    ['setting_id' => 4, 'key_name' => 'Plugout_Cache'],
];
$next = [
    ['setting_id' => 1, 'key_name_bytes' => $enc('Module_Cache', 1), 'text_encoding' => 1],
    ['setting_id' => 2, 'key_name_bytes' => $enc('module_cache_v2', 2), 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name_bytes' => $enc('MODULE_CACHE', 3), 'text_encoding' => 3],
    ['setting_id' => 5, 'key_name' => 'module_new'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationBinaryCollationDefaultLikePlan($current, $next);
$summary = [
    'scenario' => 'application-encoding-binary-like-current-source-next259',
    'status' => $plan['status'],
    'binaryRangeUsable' => $plan['binaryRangeUsable'],
    'fullScanResidualRequired' => $plan['fullScanResidualRequired'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'encoding-collation-affinity-like-current-source-nexttwoFiveNine'
        || $summary['binaryRangeUsable'] !== false
        || $summary['fullScanResidualRequired'] !== true
        || $summary['currentMatchedRowids'] !== [3, 1, 2]
        || $summary['nextMatchedRowids'] !== [3, 1, 2, 5]
        || !in_array('binary-prefix-range-unsafe', $summary['invalidationReasons'], true)
    ) {
        fwrite(STDERR, "application-encoding-binary-like-current-source-next259 self-test failed\n");
        exit(1);
    }

    echo "application-encoding-binary-like-current-source-next259 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
