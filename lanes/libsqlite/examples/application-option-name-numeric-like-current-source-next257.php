<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encode = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['setting_id' => 1, 'storage' => 'text', 'key_name_bytes' => $encode('2024_cache', 2), 'name_encoding' => 2],
    ['setting_id' => 2, 'storage' => 'integer', 'key_name' => 2024],
    ['setting_id' => 3, 'storage' => 'real', 'key_name' => 2024.5],
    ['setting_id' => 4, 'storage' => 'blob', 'key_name_bytes' => '2024_blob'],
];

$next = [
    ['setting_id' => 1, 'storage' => 'text', 'key_name_bytes' => $encode('2024_cache', 3), 'name_encoding' => 3],
    ['setting_id' => 2, 'storage' => 'text', 'key_name_bytes' => $encode('2024', 1), 'name_encoding' => 1],
    ['setting_id' => 3, 'storage' => 'real', 'key_name' => 2025.5],
    ['setting_id' => 5, 'storage' => 'integer', 'key_name' => 2024],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::keyValueRowKeyNumericAffinityLikePlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [2, 1, 3]);
    assert($plan['nextMatchedRowids'] === [2, 5, 1]);
    assert($plan['matchedExitedRowids'] === [3]);
    assert($plan['matchedEnteredRowids'] === [5]);
    assert($plan['changedStorageRowids'] === [2, 5]);
    assert($plan['cursorInvalidated'] === true);
    echo "application-key-name-numeric-like-current-source-next257 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'matchedExitedRowids' => $plan['matchedExitedRowids'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'changedStorageRowids' => $plan['changedStorageRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
