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
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'plüg_cache', 'UTF-16LE'),
    $row(2, 'PLüG_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-8'),
];
$next = [
    $row(1, 'plüg_cache', 'UTF-16BE'),
    $row(2, 'PLüG_Cache', 'UTF-16LE'),
    $row(4, 'plüg_new', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameNonAsciiFullScanPlan($current, $next);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next205');
    assert($plan['prefix'] === 'plüg_');
    assert($plan['prefixIsAscii'] === false);
    assert($plan['currentIndexUsable'] === false);
    assert($plan['currentScanMode'] === 'full-residual-scan');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 4]);
    assert(in_array('non-ascii-nocase-prefix-full-scan', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next205 self-test passed\n";

    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
