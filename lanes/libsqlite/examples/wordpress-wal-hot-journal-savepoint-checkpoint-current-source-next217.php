<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-import-hot-journal-checkpoint:217', 'epoch' => 217];
$reader = static function (string $name, int $page, string $action, bool $admitted = true) use ($digest): array {
    return [
        'reader' => $name,
        'page' => $page,
        'expected_action' => $action,
        'acknowledged_image_sha256' => $digest($name . ':page'),
        'expected_image_sha256' => $digest($name . ':page'),
        'observed_image_sha256' => $digest($name . ':page'),
        'checkpoint_admitted' => $admitted,
    ];
};
$receipt = static function (string $name, string $action) use ($digest, $token): array {
    return [
        'source_id' => $token['id'],
        'epoch' => $token['epoch'],
        'checkpoint_frame' => 27,
        'checkpoint_cookie' => 618,
        'schema_cookie' => 44,
        'image_sha256' => $digest($name . ':page'),
        'acknowledged' => $action === 'retain-reader-cache',
        'reopen_fenced' => $action === 'reopen-reader-cache',
        'reopen_fence_token' => 'reopen:' . $name . ':' . $token['id'] . ':27',
        'journal_deleted' => true,
        'wal_synced' => true,
        'directory_synced' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next217Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
        'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
        'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
        'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
        'current_source_token' => $token,
        'checkpoint_frame' => 27,
        'checkpoint_cookie' => 618,
        'schema_cookie' => 44,
        'checkpoint_admitted' => true,
        'reader_admission_rows' => [
            $reader('wp-options-import-reader', 2, 'retain-reader-cache'),
            $reader('wp-cron-retry-reader', 5, 'retain-reader-cache'),
            $reader('old-plugin-settings-reader', 7, 'reopen-reader-cache', false),
        ],
        'operation_names' => ['acknowledge_reader_page_digest_next211'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211'],
    ],
    [
        'wp-options-import-reader' => $receipt('wp-options-import-reader', 'retain-reader-cache'),
        'wp-cron-retry-reader' => $receipt('wp-cron-retry-reader', 'retain-reader-cache'),
        'old-plugin-settings-reader' => $receipt('old-plugin-settings-reader', 'reopen-reader-cache'),
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next217',
    'wordpressUse' => 'A copied WordPress option import only publishes the post-hot-journal checkpoint source after retained readers acknowledge current page images and stale readers carry durable reopen fences.',
    'status' => $plan['status'],
    'checkpointAdmitted' => $plan['checkpoint_admitted'],
    'retainedReaders' => $plan['retained_reader_names'],
    'reopenedReaders' => $plan['reopened_reader_names'],
    'nextSourceEpoch' => $plan['next_source_epoch'],
    'receiptDigest' => $plan['receipt_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next217'
        || $summary['checkpointAdmitted'] !== true
        || $summary['retainedReaders'] !== ['wp-options-import-reader', 'wp-cron-retry-reader']
        || $summary['reopenedReaders'] !== ['old-plugin-settings-reader']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next217 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next217 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
