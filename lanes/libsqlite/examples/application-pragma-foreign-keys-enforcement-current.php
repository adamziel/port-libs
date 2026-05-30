<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePragmaForeignKeysEnforcement.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';

use PortLibs\LibSqlite\SQLitePragmaForeignKeysEnforcement;

$tables = [
    'wp_sites' => [
        ['blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_options' => [
        ['option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl'],
    ],
];
$foreignKeys = [
    [
        'table' => 'wp_options',
        'parent' => 'wp_sites',
        'columns' => ['blog_id' => 'blog_id'],
        'id' => 0,
    ],
];

$valid = SQLitePragmaForeignKeysEnforcement::insertRows(
    $tables,
    $foreignKeys,
    'wp_options',
    [['option_id' => 11, 'blog_id' => 1, 'option_name' => 'home']],
    ['foreign_keys' => 'PRAGMA foreign_keys=ON'],
);

$offlineImport = SQLitePragmaForeignKeysEnforcement::insertRows(
    $tables,
    $foreignKeys,
    'wp_options',
    [['option_id' => 12, 'blog_id' => 404, 'option_name' => 'staged_missing_site']],
    ['foreign_keys' => 'PRAGMA foreign_keys=OFF'],
);

$deferred = SQLitePragmaForeignKeysEnforcement::insertRows(
    $tables,
    $foreignKeys,
    'wp_options',
    [['option_id' => 13, 'blog_id' => 404, 'option_name' => 'deferred_missing_site']],
    ['foreign_keys' => true, 'defer_foreign_keys' => 'PRAGMA defer_foreign_keys=ON'],
);

$summary = [
    'applicationUse' => 'Copied wp_options imports can disable FK enforcement while staging rows, then re-enable it and surface the same foreign_key_check rows before commit.',
    'valid_status' => $valid['status'],
    'offline_status' => $offlineImport['status'],
    'offline_violation_count' => count($offlineImport['violations']),
    'deferred_status' => $deferred['status'],
    'deferred_violation_count' => count($deferred['deferred_violations']),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['valid_status'] !== 'ok'
        || $summary['offline_status'] !== 'foreign_keys_disabled'
        || $summary['offline_violation_count'] !== 1
        || $summary['deferred_status'] !== 'deferred'
        || $summary['deferred_violation_count'] !== 1
    ) {
        fwrite(STDERR, "application-pragma-foreign-keys-enforcement-current self-test failed\n");
        exit(1);
    }

    echo "application-pragma-foreign-keys-enforcement-current self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
