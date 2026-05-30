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
    $row(1, 'Plugin_CacheA', 2),
    $row(2, 'plugin_cache😀', 2),
    $row(3, 'plugin_cacheAB', 3),
];
$next = [
    $row(1, 'plugin_cacheA', 3),
    $row(2, 'plugin_cache😀  ', 3),
    $row(4, 'PLUGIN_CACHEß', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameUnicodeWildcardPlan(
    $current,
    $next,
    'plugin!_cache_',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next177');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 4, 2]);
    assert($plan['currentByteWildcardMismatchRowids'] === [2]);
    assert($plan['nextByteWildcardMismatchRowids'] === [2, 4]);
    assert(in_array('unicode-wildcard-recheck', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next177 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'byteWildcardMismatchRowids' => $plan['nextByteWildcardMismatchRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
