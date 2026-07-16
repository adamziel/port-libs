<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregate.php';
require_once __DIR__ . '/../src/SQLiteJsonAggregateState.php';

use PortLibs\LibSqlite\SQLiteJsonAggregateState;

$orderKey = static fn (mixed ...$terms): array => array_map(
    static fn (array $term): array => [
        'value' => $term[0],
        'direction' => $term[1] ?? 'ASC',
    ],
    $terms,
);

$rows = [
    ['name' => 'theme_mods', 'priority' => 20, 'tie' => 'b'],
    ['name' => 'siteurl', 'priority' => 30, 'tie' => 'a'],
    ['name' => 'theme_mods', 'priority' => 30, 'tie' => 'z'],
    ['name' => 'plugin_rules', 'priority' => 50, 'tie' => 'b'],
    ['name' => 'plugin_queue', 'priority' => 50, 'tie' => 'a'],
];

$state = new SQLiteJsonAggregateState();
foreach ($rows as $row) {
    $state->stepArrayDistinctOrderBy(
        $row['name'],
        $orderKey([$row['priority'], 'DESC'], [$row['tie'], 'ASC']),
    );
}

echo json_encode([
    'scenario' => 'Application option summaries keep DISTINCT admission after SQLite multi-term aggregate ORDER BY sorting.',
    'distinctOptionNamesByPriorityTie' => $state->finalizeDistinctOrderedArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
