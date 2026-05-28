<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Plan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encode = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['option_id' => 1, 'storage' => 'text', 'option_name_bytes' => $encode('2024_cache', 2), 'name_encoding' => 2],
    ['option_id' => 2, 'storage' => 'integer', 'option_name' => 2024],
    ['option_id' => 3, 'storage' => 'real', 'option_name' => 2024.5],
    ['option_id' => 4, 'storage' => 'blob', 'option_name_bytes' => '2024_blob'],
];

$next = [
    ['option_id' => 1, 'storage' => 'text', 'option_name_bytes' => $encode('2024_cache', 3), 'name_encoding' => 3],
    ['option_id' => 2, 'storage' => 'text', 'option_name_bytes' => $encode('2024', 1), 'name_encoding' => 1],
    ['option_id' => 3, 'storage' => 'real', 'option_name' => 2025.5],
    ['option_id' => 5, 'storage' => 'integer', 'option_name' => 2024],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Plan::wordpressOptionNameNumericAffinityLikePlan($current, $next);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [2, 1, 3]);
    assert($plan['nextMatchedRowids'] === [2, 5, 1]);
    assert($plan['matchedExitedRowids'] === [3]);
    assert($plan['matchedEnteredRowids'] === [5]);
    assert($plan['changedStorageRowids'] === [2, 5]);
    assert($plan['cursorInvalidated'] === true);
    echo "wordpress-option-name-numeric-like-current-source-next257 self-test passed\n";
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
