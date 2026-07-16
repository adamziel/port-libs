<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$encode = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    ['setting_id' => 1, 'key_name_bytes' => $encode('module_cache', 1), 'text_encoding' => 1],
    ['setting_id' => 2, 'key_name_bytes' => $encode('module_cache   ', 2), 'text_encoding' => 2],
];
$next = [
    ['setting_id' => 1, 'key_name_bytes' => $encode('module_cache ', 1), 'text_encoding' => 1],
    ['setting_id' => 2, 'key_name_bytes' => $encode('module_cache', 2), 'text_encoding' => 2],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::applicationRtrimCollationLikeResidualPlan($current, $next);

if ($plan['currentMatchedRowids'] !== [1] || $plan['nextMatchedRowids'] !== [2]) {
    fwrite(STDERR, "unexpected next260 RTRIM LIKE residual rowids\n");
    exit(1);
}

echo json_encode([
    'status' => $plan['status'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'nextResidualRejectedRowids' => $plan['nextResidualRejectedRowids'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT) . PHP_EOL;
