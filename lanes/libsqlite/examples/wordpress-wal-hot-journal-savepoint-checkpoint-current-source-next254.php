<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('wordpress next254 checkpoint database image');
$pageCacheDigest = $hash('wordpress next254 clean page cache image');
$cachePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next250',
    'cache_invalidation_admitted' => true,
    'source_token' => 'wp-next254-current-source',
    'commit_generation' => 254,
    'schema_cookie' => 954,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'checkpoint_frame' => 41,
    'dirty_pages' => [1, 2, 5, 8],
    'commit_frames' => [38, 40, 41],
    'reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'receipt_names' => ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index'],
    'operation_names' => ['admit_checkpoint_cache_invalidation_current_source_next250'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next250'],
];

$lease = static function (string $name, string $kind, array $pages, array $frames, array $readers, array $cacheReceipts) use ($cachePlan, $databaseDigest, $pageCacheDigest): array {
    return [
        'name' => $name,
        'kind' => $kind,
        'source_token' => $cachePlan['source_token'],
        'commit_generation' => $cachePlan['commit_generation'],
        'schema_cookie' => $cachePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'checkpoint_frame' => $cachePlan['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'cache_receipt_names' => $cacheReceipts,
        'statement_reprepared' => true,
        'root_page_digest_matched' => true,
        'read_transaction_open' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next254AdmitCurrentSourceLeases($cachePlan, [
    $lease('schema-statement-lease', 'schema-statement', [1], [38], ['schema-reader'], ['invalidate-schema-cache']),
    $lease('options-table-root-lease', 'table-root', [2, 5], [40], ['options-reader'], ['clear-options-readmark']),
    $lease('autoload-index-root-lease', 'index-root', [8], [41], ['autoload-reader'], ['refresh-schema-cookie']),
    $lease('read-transaction-lease', 'read-transaction', [1, 2, 5, 8], [38, 40, 41], ['schema-reader', 'options-reader', 'autoload-reader'], ['invalidate-schema-cache', 'clear-options-readmark', 'refresh-schema-cookie', 'refresh-wal-index']),
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254',
    'wordpressUse' => 'A copied WordPress options import reuses schema, table, index, and read-transaction leases only after the hot-journal checkpoint cache fence proves every reader, root page, committed WAL frame, and cache receipt belongs to the same current source.',
    'status' => $plan['status'],
    'statementAction' => $plan['statement_action'],
    'rootPageAction' => $plan['root_page_action'],
    'readerAction' => $plan['reader_action'],
    'acceptedLeases' => $plan['accepted_lease_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next254'
        || $summary['statementAction'] !== 'reuse_statements_on_checkpoint_current_source'
        || $summary['acceptedLeases'] !== ['schema-statement-lease', 'options-table-root-lease', 'autoload-index-root-lease', 'read-transaction-lease']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next254 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
