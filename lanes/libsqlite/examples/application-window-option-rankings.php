<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20],
    ['option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20],
    ['option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 64],
    ['option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'bytes' => 64],
    ['option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 128],
];

usort($rows, static fn (array $left, array $right): int => ($left['bytes'] <=> $right['bytes']) ?: strcmp($left['option_name'], $right['option_name']));

$bytes = array_column($rows, 'bytes');
$names = array_column($rows, 'option_name');
$summary = SQLiteWindowFunction::rankingSummary($bytes, 3);
$lagNames = SQLiteWindowFunction::lag($names, 1, null);
$leadNames = SQLiteWindowFunction::lead($names, 1, null);

$ranked = [];
foreach ($rows as $index => $row) {
    $ranked[] = $row + [
        'row_number' => $summary['rowNumber'][$index],
        'rank' => $summary['rank'][$index],
        'dense_rank' => $summary['denseRank'][$index],
        'percent_rank' => $summary['percentRank'][$index],
        'cume_dist' => $summary['cumeDist'][$index],
        'ntile_3' => $summary['ntile'][$index],
        'previous_option' => $lagNames[$index],
        'next_option' => $leadNames[$index],
    ];
}

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows with SQLite built-in window ranking and offset semantics for local import diagnostics without requiring the SQLite extension.',
    'orderedByBytes' => $ranked,
    'firstOptionByBytes' => SQLiteWindowFunction::firstValue($names)[0] ?? null,
    'lastOptionByBytes' => SQLiteWindowFunction::lastValue($names)[0] ?? null,
    'thirdOptionByBytes' => SQLiteWindowFunction::nthValue($names, 3)[0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
