<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$current = [
    $row(1, 'éclair_cache', 2),
    $row(2, 'éCLAIR_cache  ', 3),
    $row(3, 'Éclair_cache', 2),
];
$next = [
    $row(1, 'éclair_cache  ', 3),
    $row(2, 'éclair_cache', 2),
    $row(4, 'éCLAIR_cache_new', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiPrefixPlan(
    $current,
    $next,
    'éclair!_%',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next180');
    assert($plan['indexUsable'] === false);
    assert($plan['usesFullScanFallback'] === true);
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4]);
    assert($plan['currentFalsePositiveRowids'] === [3]);
    assert(in_array('non-ascii-nocase-prefix-full-scan', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next180 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'rejectedReason' => $plan['rejectedReason'],
    'usesFullScanFallback' => $plan['usesFullScanFallback'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
