<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Plan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['option_id' => 1, 'option_name_bytes' => $enc('Plugin_Cache', 1), 'text_encoding' => 1],
    ['option_id' => 2, 'option_name_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name_bytes' => $enc('PLUGIN_CACHE', 3), 'text_encoding' => 3],
    ['option_id' => 4, 'option_name' => 'Plugout_Cache'],
];
$next = [
    ['option_id' => 1, 'option_name_bytes' => $enc('Plugin_Cache', 1), 'text_encoding' => 1],
    ['option_id' => 2, 'option_name_bytes' => $enc('plugin_cache_v2', 2), 'text_encoding' => 2],
    ['option_id' => 3, 'option_name_bytes' => $enc('PLUGIN_CACHE', 3), 'text_encoding' => 3],
    ['option_id' => 5, 'option_name' => 'plugin_new'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Plan::wordpressBinaryCollationDefaultLikePlan($current, $next);
$summary = [
    'scenario' => 'wordpress-encoding-binary-like-current-source-next259',
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
        $summary['status'] !== 'encoding-collation-affinity-like-current-source-next259'
        || $summary['binaryRangeUsable'] !== false
        || $summary['fullScanResidualRequired'] !== true
        || $summary['currentMatchedRowids'] !== [3, 1, 2]
        || $summary['nextMatchedRowids'] !== [3, 1, 2, 5]
        || !in_array('binary-prefix-range-unsafe', $summary['invalidationReasons'], true)
    ) {
        fwrite(STDERR, "wordpress-encoding-binary-like-current-source-next259 self-test failed\n");
        exit(1);
    }

    echo "wordpress-encoding-binary-like-current-source-next259 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
