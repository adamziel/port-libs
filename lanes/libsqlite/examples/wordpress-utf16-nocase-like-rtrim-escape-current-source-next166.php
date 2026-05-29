<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache_miss', 3),
    $row(3, 'plugin!cache', 1),
];
$nextRows = [
    $row(1, 'plugin_cache', 3),
    $row(2, 'plugin_cache_miss', 3),
    $row(4, 'plugin_cache_new  ', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
    $currentRows,
    $nextRows,
    $enc('plugin!_cache%   ', 2),
    2,
    $enc('plugin!_cache% ', 3),
    3,
    $enc('!   ', 2),
    2,
    $enc('! ', 3),
    3,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-escape-current-source-next166');
    assert($plan['currentTrimmedEscape'] === '!');
    assert($plan['nextTrimmedEscape'] === '!');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowidsWithNextEscape'] === [4, 1, 2]);
    assert(in_array('escape-bytes', $plan['escapeByteReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-escape-current-source-next166 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
