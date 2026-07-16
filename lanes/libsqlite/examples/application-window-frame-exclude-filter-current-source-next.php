<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteWindowFrameExcludeFilterCurrentSourceNext;

$currentRows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'bytes' => 20, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 20, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30, 'include' => 1],
    ['rowid' => 4, 'site' => 2, 'option_name' => 'network_active_plugins', 'autoload' => 'yes', 'bytes' => 25, 'include' => true],
    ['rowid' => 5, 'site' => 2, 'option_name' => 'network_cron', 'autoload' => 'no', 'bytes' => 25, 'include' => '0'],
    ['rowid' => 6, 'site' => 2, 'option_name' => 'network_options', 'autoload' => 'yes', 'bytes' => 35, 'include' => '0.5'],
];

$nextRows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'bytes' => 20, 'include' => 1],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30, 'include' => 1],
    ['rowid' => 7, 'site' => 1, 'option_name' => 'translation_updates', 'autoload' => 'no', 'bytes' => 40, 'include' => -1],
    ['rowid' => 4, 'site' => 2, 'option_name' => 'network_active_plugins', 'autoload' => 'yes', 'bytes' => 25, 'include' => true],
    ['rowid' => 6, 'site' => 2, 'option_name' => 'network_options', 'autoload' => 'yes', 'bytes' => 35, 'include' => '0.5'],
];

$plan = SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan($currentRows, $nextRows, [
    'valueColumn' => 'bytes',
    'partitionColumns' => ['site'],
    'orderColumns' => ['bytes', 'option_name'],
    'filterColumn' => 'include',
    'preceding' => 0,
    'following' => 1,
    'partitionAffinities' => ['INTEGER'],
    'orderAffinities' => ['NUMERIC', 'TEXT'],
    'orderCollations' => ['BINARY', 'NOCASE'],
    'frameUnit' => 'GROUPS',
    'exclude' => 'CURRENT ROW',
    'rowidColumn' => 'rowid',
    'separator' => '|',
]);

$summarize = static fn (array $rows): array => array_map(static fn (array $row): array => [
    'rowid' => $row['currentRowid'],
    'frameRowids' => $row['frameRowids'],
    'filteredRowids' => $row['filteredRowids'],
    'filteredBytes' => $row['filteredValues'],
    'sum' => $row['sum'],
], $rows);

$output = [
    'sqlShape' => "sum(bytes) FILTER (WHERE include) OVER (PARTITION BY site ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW)",
    'applicationUse' => 'Compare current and next wp_options import sources while keeping EXCLUDE CURRENT ROW and FILTER evaluation tied to each source snapshot.',
    'sourceChanged' => $plan['source_changed'],
    'current' => $summarize($plan['current']),
    'next' => $summarize($plan['next']),
];

if (($argv[1] ?? '') === '--self-test') {
    if ($output['sourceChanged'] !== true) {
        throw new RuntimeException('expected source change');
    }
    if (($output['current'][1]['filteredRowids'] ?? null) !== [3]) {
        throw new RuntimeException('expected current source row 2 to filter row 3 after excluding row 2');
    }
    if (($output['next'][1]['filteredRowids'] ?? null) !== [7]) {
        throw new RuntimeException('expected next source to include translation update row');
    }
    echo "application-window-frame-exclude-filter-current-source-next self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
