<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16LikeGlobCurrentNextCursor;

$enc = static fn (string $text, string $encoding = 'UTF-16LE'): string => SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($text, $encoding);
$rows = [
    ['option_id' => 1, 'option_name' => 'plugin-cache', 'option_name_utf16' => $enc('plugin-cache'), 'encoding' => 'UTF-16LE', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin-cache ', 'option_name_utf16' => $enc('plugin-cache '), 'encoding' => 'UTF-16LE', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin-cache-hard', 'option_name_utf16' => $enc('plugin-cache-hard'), 'encoding' => 'UTF-16LE', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'Plugin-cache', 'option_name_utf16' => $enc('Plugin-cache'), 'encoding' => 'UTF-16LE', 'autoload' => 'no'],
];

$exact = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan($rows, 'plugin-cache', 'GLOB', 'UTF-16LE', 'RTRIM');
$wildcard = SQLiteUtf16LikeGlobCurrentNextCursor::optionRowNameScan($rows, 'plugin-cache*', 'GLOB', 'UTF-16LE', 'RTRIM');

$planCursor = new SQLiteUtf16LikeGlobCurrentNextCursor(
    array_map(static fn (array $row): array => [
        'keyBytes' => $row['option_name_utf16'],
        'rowid' => $row['option_id'],
        'payload' => $row,
    ], $rows),
    'plugin-cache',
    'GLOB',
    'UTF-16LE',
    'RTRIM',
);
$plan = $planCursor->currentNextPlan();

$summary = [
    'scenario' => 'application-utf16-rtrim-like-glob-current-source-next90',
    'exact_glob_option_ids' => array_column($exact, 'rowid'),
    'wildcard_glob_option_ids' => array_column($wildcard, 'rowid'),
    'current_comparison_key' => $plan['currentComparisonKey'],
    'next_comparison_key' => $plan['nextComparisonKey'],
    'next_residual_match' => $plan['nextResidualMatch'],
    'dependencies' => [
        'native UTF-16 decode',
        'native RTRIM collation cursor comparison',
        'native GLOB residual matching',
        'no ext/sqlite required',
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['exact_glob_option_ids'] !== [1]) {
        throw new RuntimeException('Unexpected exact RTRIM GLOB rows');
    }
    if ($summary['wildcard_glob_option_ids'] !== [1, 2, 3]) {
        throw new RuntimeException('Unexpected wildcard RTRIM GLOB rows');
    }
    if ($summary['current_comparison_key'] !== 'plugin-cache' || $summary['next_comparison_key'] !== 'plugin-cache') {
        throw new RuntimeException('Unexpected RTRIM comparison keys');
    }
    if ($summary['next_residual_match'] !== false) {
        throw new RuntimeException('Expected exact GLOB residual to reject padded peer');
    }
    echo "application-utf16-rtrim-like-glob-current-source-next90 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
