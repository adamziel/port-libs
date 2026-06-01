<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
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
    $row(1, 'plugin_cache  ', 'UTF-16LE'),
    $row(2, "Plugin_Cache\t", 'UTF-16BE'),
    $row(3, "plugin_cache\u{00a0}", 'UTF-8'),
];
$next = [
    $row(1, "plugin_cache\t", 'UTF-16BE'),
    $row(2, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(3, 'plugin_cache  ', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceTrimBoundaryPlan(
    $current,
    $next,
    'plugin%',
    null,
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-nextoneNineZero');
    assert($plan['currentMatchedRowids'] === [1, 2, 3]);
    assert($plan['nextMatchedRowids'] === [2, 3, 1]);
    assert($plan['changedTrailingWhitespaceClassRowids'] === [1, 2, 3]);
    assert($plan['retainedRangeRtrimKeyChangedRowids'] === [1, 2, 3]);
    assert($plan['nonBreakingSpaceNotTrimmed'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next190 self-test passed\n";

    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
