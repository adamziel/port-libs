<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$encode = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $encode($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-8'),
    $row(4, 'plugin-cache', 'UTF-16LE'),
];
$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(4, 'plugin-cache', 'UTF-16LE'),
    $row(10, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
);

echo json_encode([
    'status' => $plan['status'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'matchedEnteredRowids' => $plan['matchedEnteredRowids'],
    'byteOrderOnlyRowids' => $plan['byteOrderOnlyRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
