<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'plugin_éclair  ', 'UTF-16LE'),
    $row(2, 'Plugin_éTAG', 'UTF-16BE'),
    $row(3, 'plugin_Éclair', 'UTF-16LE'),
];
$next = [
    $row(1, 'PLUGIN_éclair ', 'UTF-16BE'),
    $row(2, 'Plugin_ÉTAG', 'UTF-16BE'),
    $row(3, 'plugin_éclair', 'UTF-16LE'),
    $row(4, 'plugin_été', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan($current, $next);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nexttwoZeroFour');
    assert($plan['rejectedReason'] === 'nocase_like_prefix_must_be_ascii_for_range');
    assert($plan['usesPrefixRangeCursor'] === false);
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 3, 4]);
    assert($plan['matchedExitedRowids'] === [2]);
    assert($plan['matchedEnteredRowids'] === [3, 4]);
    echo "application-utf16-nocase-like-rtrim-current-source-nexttwoZeroFour self-test passed\n";

    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
