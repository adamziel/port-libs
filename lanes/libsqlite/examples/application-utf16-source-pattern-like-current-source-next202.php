<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$encode = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $encode($name, $encoding),
    'text_encoding' => $encoding,
];
$pattern = static fn (int $id, string $value, int $encoding): array => [
    'option_id' => $id,
    'option_value_bytes' => $encode($value, $encoding),
    'text_encoding' => $encoding,
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourcePatternPlan(
    [
        $row(1, 'Plugin_Cache', 2),
        $row(2, 'plugin_cache_alpha', 3),
        $row(3, 'plugin_setting', 2),
    ],
    [
        $row(1, 'Plugin_Cache', 3),
        $row(2, 'plugin_cache_alpha', 2),
        $row(4, 'plugin_cache_zeta', 2),
    ],
    $pattern(91, 'plugin!_cache%', 2),
    $pattern(92, 'plugin!_cache!_%', 3),
);

echo json_encode([
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'rhsPatternInvalidationReasons' => $plan['rhsPatternInvalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
