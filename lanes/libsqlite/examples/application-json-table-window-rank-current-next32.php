<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugins":[{"slug":"seo","priority":9},{"slug":"cache","priority":5},{"slug":"forms","priority":5},{"slug":"media","priority":3}]}';
$pairs = SQLiteJsonTablePlan::rankedAdjacentRows('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
], [
    ['column' => 'atom', 'direction' => 'DESC'],
], [], 2);

$summary = array_map(
    static fn (array $pair): array => [
        'priority' => $pair['current']['atom'],
        'rank' => $pair['currentRank'],
        'denseRank' => $pair['current']['window_dense_rank'],
        'nextPriority' => $pair['next']['atom'] ?? null,
        'nextRank' => $pair['nextRank'],
        'samePeer' => $pair['samePeer'],
    ],
    $pairs,
);

if (($argv[1] ?? null) === '--self-test') {
    if (array_column($summary, 'priority') !== [9, 5, 5, 3]) {
        throw new RuntimeException('Unexpected priority order');
    }
    if (array_column($summary, 'rank') !== [1, 2, 2, 4]) {
        throw new RuntimeException('Unexpected rank order');
    }
    if (array_column($summary, 'samePeer') !== [false, true, false, false]) {
        throw new RuntimeException('Unexpected peer flags');
    }
    echo "application-json-table-window-rank-current-next32 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-window-rank-current-next32',
    'rankedCurrentNext' => $summary,
    'applicationUse' => 'Copied wp_options plugin settings can be expanded through json_tree() and traversed as current/next ranked rows, preserving peer ties before import repair tools require ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
