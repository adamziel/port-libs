<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan;

require_once dirname(__DIR__) . '/src/SQLiteEncodingCollationSourceCursor.php';
require_once dirname(__DIR__) . '/src/SQLiteDatabase.php';
require_once dirname(__DIR__) . '/src/SQLiteLikeCollationPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan.php';

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, 'plugin_cache_alpha  ', 2),
    $row(3, 'plugin_cache_beta', 2),
    $row(4, "plugin_cache_tab\t", 3),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 3),
    $row(2, 'plugin_cache_alpha', 3),
    $row(3, 'plugin_cache_beta  ', 2),
    $row(7, 'PLUGIN_CACHE_GAMMA', 2),
    $row(4, "plugin_cache_tab\t", 3),
];

$plan = SQLiteUtf16NocaseLikeRtrimResumeTokenCurrentSourceNextPlan::keyValueRowKeyResumeTokenPlan(
    $currentRows,
    $nextRows,
    $enc('plugin\\_cache%', 2),
    2,
    $enc('plugin\\_cache%', 3),
    3,
    $enc('plugin_cache_alpha  ', 2),
    2,
    2,
    $enc('plugin_cache_alpha', 3),
    3,
    2,
    $enc('\\', 2),
    2,
    $enc('\\', 3),
    3,
    'stable',
    'stable',
    170,
    170,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-resume-token-current-source-nextoneSevenZero');
    assert($plan['sameDecodedToken'] === true);
    assert($plan['byteOnlyTokenReprepare'] === true);
    assert($plan['safeToResumeFromToken'] === true);
    assert($plan['resumePlanRowids'] === [3, 7, 4]);
    echo "application-utf16-nocase-like-rtrim-resume-token-current-source-next self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
