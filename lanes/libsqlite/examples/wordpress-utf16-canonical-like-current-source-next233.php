<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encode = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $encode($name, $encoding),
    'text_encoding' => $encodingId($encoding),
];

$current = [
    $row(1, 'plugin_cafe', 'UTF-16LE'),
    $row(2, "plugin_caf\u{00e9}", 'UTF-16BE'),
    $row(3, "plugin_cafe\u{0301}", 'UTF-16LE'),
];
$next = [
    $row(1, 'plugin_cafe', 'UTF-16BE'),
    $row(2, "plugin_cafe\u{0301}", 'UTF-16LE'),
    $row(3, "plugin_caf\u{00e9}", 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 3]);
    assert($plan['currentSingleWildcardFalsePositiveRowids'] === [3]);
    assert($plan['nextSingleWildcardFalsePositiveRowids'] === [2]);
    assert($plan['unicodeNormalizationApplied'] === false);
    assert(in_array('single-wildcard-codepoint-boundary', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-canonical-like-current-source-next233 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentSingleWildcardFalsePositiveRowids' => $plan['currentSingleWildcardFalsePositiveRowids'],
    'nextSingleWildcardFalsePositiveRowids' => $plan['nextSingleWildcardFalsePositiveRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
