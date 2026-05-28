<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Plan.php';

$digest = hash('sha256', 'wordpress next248 released reader checkpoint image');
$admissionPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next245',
    'readers_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'source_token' => 'wordpress-import-next248',
    'writer_generation' => 248,
    'next_source_generation' => 249,
    'database_digest' => $digest,
    'covered_page_numbers' => [1, 2, 3, 4, 5, 6, 8, 13],
    'accepted_reader_names' => ['wp_options-import-reader', 'wp_posts-preview-reader', 'wp_plugin-cache-reader'],
    'operation_names' => ['admit_reopened_reader_cache_current_source_next245'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next245'],
];

$release = static function (string $name, array $pages) use ($admissionPlan, $digest): array {
    return [
        'name' => $name,
        'database_path' => $admissionPlan['database_path'],
        'wal_path' => $admissionPlan['wal_path'],
        'journal_path' => $admissionPlan['journal_path'],
        'source_token' => $admissionPlan['source_token'],
        'writer_generation' => $admissionPlan['writer_generation'],
        'reader_generation' => $admissionPlan['next_source_generation'],
        'database_digest' => $digest,
        'last_visible_frame' => 5,
        'release_frame' => 6,
        'page_numbers' => $pages,
        'snapshot_closed' => true,
        'page_cache_clean' => true,
        'shared_lock_released' => true,
        'reserved_lock_held' => false,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Plan::planCheckpointTruncation($admissionPlan, [
    $release('wp_options-import-reader', [1, 2, 5]),
    $release('wp_posts-preview-reader', [1, 3, 6]),
    $release('wp_plugin-cache-reader', [4, 8, 13]),
]);

echo json_encode([
    'status' => $plan['status'],
    'checkpoint_truncation_admitted' => $plan['checkpoint_truncation_admitted'],
    'reader_action' => $plan['reader_action'],
    'wal_action' => $plan['wal_action'],
    'released_reader_names' => $plan['released_reader_names'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
