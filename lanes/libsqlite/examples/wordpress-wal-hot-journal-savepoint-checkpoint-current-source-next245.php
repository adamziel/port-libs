<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = hash('sha256', 'wordpress next245 reopened reader checkpoint image');
$commitPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next242',
    'commit_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'source_token' => 'wordpress-import-next245',
    'writer_generation' => 245,
    'next_source_generation' => 246,
    'database_digest' => $digest,
    'expected_schema_cookie' => 24577,
    'expected_wal_salt' => '2450abcd2450dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5, 6, 9],
    'operation_names' => ['publish_post_checkpoint_writer_current_source_next242'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next242'],
];

$reader = static function (string $name, array $pages) use ($commitPlan, $digest): array {
    return [
        'name' => $name,
        'database_path' => $commitPlan['database_path'],
        'wal_path' => $commitPlan['wal_path'],
        'journal_path' => $commitPlan['journal_path'],
        'source_token' => $commitPlan['source_token'],
        'writer_generation' => $commitPlan['writer_generation'],
        'reader_generation' => $commitPlan['next_source_generation'],
        'database_digest' => $digest,
        'schema_cookie' => $commitPlan['expected_schema_cookie'],
        'wal_salt' => $commitPlan['expected_wal_salt'],
        'readmark_frame' => 2,
        'last_visible_frame' => 4,
        'page_numbers' => $pages,
        'page_cache_clean' => true,
        'snapshot_open' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'reserved_lock_held' => false,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next245AdmitReopenedReaders($commitPlan, [
    $reader('wp_options-import-reader', [1, 2, 5]),
    $reader('wp_posts-preview-reader', [1, 3, 6]),
    $reader('wp_plugin-cache-reader', [4, 5, 9]),
]);

echo json_encode([
    'status' => $plan['status'],
    'readers_admitted' => $plan['readers_admitted'],
    'reader_action' => $plan['reader_action'],
    'cache_action' => $plan['cache_action'],
    'accepted_reader_names' => $plan['accepted_reader_names'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
