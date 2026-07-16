<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteIndexSkipScanPlan;

$rows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'A', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'B', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'option_value' => 'ta', 'kind' => 'transient'],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'option_value' => 'NA', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'NG', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'Example', 'kind' => 'core'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'YA', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'YD', 'kind' => 'plugin'],
];

$partialPredicate = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan = SQLiteIndexSkipScanPlan::betweenPartialRows(
    $rows,
    'idx_wp_options_autoload_plugin_name',
    'autoload',
    'option_name',
    'plugin_',
    'plugin_zzzz',
    $partialPredicate,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_alpha'],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    if (($plan['status'] ?? null) !== 'usable' || ($plan['partialPredicateImplied'] ?? null) !== true) {
        fwrite(STDERR, "expected usable implied partial skip-scan\n");
        exit(1);
    }
    if (($plan['rowids'] ?? null) !== [2, 3, 5, 6, 8, 9]) {
        fwrite(STDERR, "expected plugin rowids from partial index image\n");
        exit(1);
    }
    if (($plan['estimatedSeeks'] ?? null) !== 3 || ($plan['usesSkipScan'] ?? null) !== true) {
        fwrite(STDERR, "expected one current/next loop per autoload prefix\n");
        exit(1);
    }
    if (($plan['skippedPartialRows'] ?? null) !== 3) {
        fwrite(STDERR, "expected non-plugin rows skipped from partial index image\n");
        exit(1);
    }

    echo "application-planner-skipscan-partial-current-next28 self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
