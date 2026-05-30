<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexSkipScanPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['rowid' => 1, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'option_value' => 'alpha'],
    ['rowid' => 2, 'autoload' => 'no', 'option_name' => '_transient_timeout_alpha', 'option_value' => '1700000000'],
    ['rowid' => 3, 'autoload' => 'yes', 'option_name' => '_transient_beta', 'option_value' => 'beta'],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
    ['rowid' => 5, 'autoload' => 'auto', 'option_name' => '_transient_gamma', 'option_value' => 'gamma'],
];

$plan = SQLiteIndexSkipScanPlan::betweenRows(
    $rows,
    'wp_options_autoload_name',
    'autoload',
    'option_name',
    $argv[1] ?? '_transient_',
    $argv[2] ?? '_transient_timeout_zzzz',
    true,
);

echo json_encode([
    'scenario' => 'application copied wp_options composite index skip-scan between',
    'index' => $plan['indexName'],
    'usesSkipScan' => $plan['usesSkipScan'],
    'estimatedSeeks' => $plan['estimatedSeeks'],
    'loops' => $plan['loops'],
    'rowids' => $plan['rowids'],
    'options' => array_map(
        static fn (array $row): array => [
            'autoload' => $row['autoload'],
            'option_name' => $row['option_name'],
            'option_value' => $row['option_value'],
        ],
        $plan['rows'],
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
