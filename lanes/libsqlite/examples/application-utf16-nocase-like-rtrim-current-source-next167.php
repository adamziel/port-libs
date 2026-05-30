<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameFallbackPlan(
    [
        $row(1, 'éclair_cache  ', 2),
        $row(2, 'Éclair_Cache', 3),
        $row(3, 'plugin_éclair_cache', 2),
    ],
    [
        $row(1, 'éclair_cache', 3),
        $row(2, 'Éclair_Cache', 3),
        $row(4, 'éclair_new  ', 2),
    ],
    'éclair%',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['scanMode'] === 'full-residual-scan');
    assert($plan['currentMatchedRowids'] === [1]);
    assert($plan['nextMatchedRowids'] === [1, 4]);
    assert($plan['currentFalsePositiveRowids'] === [3, 2]);
    assert(in_array('full-scan-like-residual', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next167 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'scanMode' => $plan['scanMode'],
    'indexUsable' => $plan['indexUsable'],
    'rejectedReason' => $plan['rejectedReason'],
    'matchedBefore' => $plan['currentMatchedRowids'],
    'matchedAfter' => $plan['nextMatchedRowids'],
    'falsePositiveBefore' => $plan['currentFalsePositiveRowids'],
    'reasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
