<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Module_Cache  ', 2),
    $row(2, 'module_cache_miss', 3),
    $row(3, 'theme_cache', 2),
];
$nextRows = [
    $row(1, 'module_cache', 3),
    $row(2, 'module_cache_miss', 3),
    $row(4, 'module_cache_new  ', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan::keyValueRowKeyRtrimPatternPlan(
    $currentRows,
    $nextRows,
    $enc('module!_cache%   ', 2),
    2,
    $enc('module!_cache% ', 3),
    3,
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-rhs-current-source-next163');
    assert($plan['currentTrimmedPattern'] === 'module!_cache%');
    assert($plan['nextTrimmedPattern'] === 'module!_cache%');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [4, 1, 2]);
    assert(in_array('rtrim-pattern-bytes', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-rhs-current-source-next163 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
