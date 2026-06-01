<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$current = [
    $row(1, 'Plugin_Cache   ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'plugin_cache' . "\t", 2),
    $row(4, 'theme_cache', 2),
];
$next = [
    $row(1, 'plugin_cache', 3),
    $row(2, 'PLUGIN_CACHE  ', 2),
    $row(3, 'plugin_cache  ', 3),
    $row(5, 'Plugin_Cache   ', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next183');
    assert($plan['usesPrefixRangeCursor'] === true);
    assert($plan['rangeLowerInclusive'] === 'PLUGIN_CACHE');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 5]);
    assert($plan['matchedEnteredRowids'] === [3, 5]);
    assert($plan['staleRangeCursorRisk'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next183 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'rangeLowerInclusive' => $plan['rangeLowerInclusive'],
    'rangeUpperBound' => $plan['rangeUpperBound'],
    'usesPrefixRangeCursor' => $plan['usesPrefixRangeCursor'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'staleRangeCursorRisk' => $plan['staleRangeCursorRisk'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
