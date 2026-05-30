<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'PLUGIN_CACHE_TRANSIENT  ', 2),
    $row(4, 'plugin_cache_shadow', 1),
];
$nextRows = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'PLUGIN_CACHE_TRANSIENT  ', 2),
    $row(4, 'plugin_cache_shadow', 1),
    $row(5, 'plugin_cache_new  ', 3),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameCaseSensitiveLikePlan(
    $currentRows,
    $nextRows,
    'plugin!_cache%',
    '!',
    'main.wp_options@before-case-sensitive-like',
    'main.wp_options@after-case-sensitive-like',
    167,
    168,
    false,
    true,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 2, 4, 3]);
    assert($plan['nextMatchedRowids'] === [2, 4, 5]);
    assert($plan['caseSensitiveDroppedRowids'] === [1, 3]);
    assert($plan['nextRequiresBinaryLikeScan'] === true);
    assert(in_array('case-sensitive-like', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next168 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next168',
    'applicationUse' => 'Copied wp_options prefix scans must not resume a UTF-16 NOCASE/RTRIM LIKE cursor after case_sensitive_like changes residual matching.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'caseSensitiveDroppedRowids' => $plan['caseSensitiveDroppedRowids'],
    'caseSensitiveRangeFalsePositiveRowids' => $plan['caseSensitiveRangeFalsePositiveRowids'],
    'nextRejectedReason' => $plan['nextRejectedReason'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
