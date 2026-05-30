<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, "plugin_cache\0disabled", 2),
    $row(3, "plugin_cache\0  ", 3),
];
$nextRows = [
    $row(1, 'plugin_cache', 3),
    $row(2, 'plugin_cache', 2),
    $row(3, "plugin_cache\0suffix", 3),
    $row(4, 'PLUGIN_CACHE', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::optionRowNameEmbeddedNulPlan(
    $currentRows,
    $nextRows,
    'plugin!_cache',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4]);
    assert($plan['currentCstringFalseMatchRowids'] === [2, 3]);
    assert($plan['nextCstringFalseMatchRowids'] === [3]);
    assert(in_array('embedded-nul-full-text-recheck', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-nul-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-utf16-nocase-like-rtrim-nul-current-source-next',
    'applicationUse' => 'Copied wp_options scans must compare full decoded UTF-16 text with embedded NUL bytes instead of truncating option_name at a C-string boundary before LIKE/RTRIM/NOCASE rechecks.',
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentCstringFalseMatchRowids' => $plan['currentCstringFalseMatchRowids'],
    'nextCstringFalseMatchRowids' => $plan['nextCstringFalseMatchRowids'],
    'currentEmbeddedNulRowids' => $plan['currentEmbeddedNulRowids'],
    'nextEmbeddedNulRowids' => $plan['nextEmbeddedNulRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
