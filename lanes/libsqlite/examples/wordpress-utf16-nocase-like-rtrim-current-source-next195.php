<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$currentRows = [
    $row(1, 'plugin_%_cache', 'UTF-16LE'),
    $row(2, 'Plugin_%_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin_%_cache_alpha', 'UTF-16LE'),
    $row(4, "plugin_%_cache\t", 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'plugin_%_cache', 'UTF-16BE'),
    $row(2, 'Plugin_%_Cache', 'UTF-16LE'),
    $row(3, 'plugin_%_cache', 'UTF-16LE'),
    $row(4, "plugin_%_cache\t", 'UTF-16LE'),
    $row(9, 'PLUGIN_%_CACHE', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameEscapedLiteralTailPlan($currentRows, $nextRows);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next195');
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 9]);
    assert($plan['currentRangeFalsePositiveRowids'] === [4, 3]);
    assert($plan['nextRangeFalsePositiveRowids'] === [4]);
    assert($plan['falsePositivePromotedRowids'] === [3]);
    assert(in_array('range-residual-false-positive-rowset', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next195 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'prefix' => $plan['prefix'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'nextCandidateRowids' => $plan['nextCandidateRowids'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentFalsePositiveRowids' => $plan['currentRangeFalsePositiveRowids'],
    'nextFalsePositiveRowids' => $plan['nextRangeFalsePositiveRowids'],
    'falsePositivePromotedRowids' => $plan['falsePositivePromotedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
