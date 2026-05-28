<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$text = static fn (int $id, string $value, int $encoding): array => [
    'option_id' => $id,
    'storage' => 'text',
    'option_value_bytes' => $enc($value, $encoding),
    'value_encoding' => $encoding,
];
$scalar = static fn (int $id, int|float $value, string $storage): array => [
    'option_id' => $id,
    'storage' => $storage,
    'option_value' => $value,
];

$currentRows = [
    $text(101, 'yes', 2),
    $text(102, 'YES-cache', 3),
    $text(103, 'no', 2),
    $scalar(104, 1, 'integer'),
];
$nextRows = [
    $text(101, 'YES', 3),
    $text(102, 'no-cache', 2),
    $text(103, 'YES-new', 2),
    $text(105, 'yes-later', 1),
    $scalar(104, 1, 'integer'),
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Plan::wordpressAutoloadValuePlan($currentRows, $nextRows);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [101, 102]);
    assert($plan['nextMatchedRowids'] === [101, 103, 105]);
    assert($plan['matchedExitedRowids'] === [102]);
    assert($plan['matchedEnteredRowids'] === [103, 105]);
    assert(in_array('text-affinity', $plan['invalidationReasons'], true));
    assert($plan['cursorReusable'] === false);
    echo "wordpress-encoding-affinity-like-current-source-next253 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'expression' => $plan['expression'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'matchedExitedRowids' => $plan['matchedExitedRowids'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
