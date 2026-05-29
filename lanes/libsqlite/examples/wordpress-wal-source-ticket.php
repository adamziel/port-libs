<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$next221 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next221',
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'shm_path' => '/srv/www/wp-content/database/wordpress.sqlite-shm',
    'next_source_token' => [
        'id' => 'wordpress-next222-sidecar-retired-source',
        'epoch' => 222,
        'checkpoint_frame' => 222,
        'checkpoint_cookie' => 222222,
    ],
    'checkpoint_frame' => 222,
    'checkpoint_cookie' => 222222,
    'checkpoint_admitted' => true,
    'operation_names' => ['verify_shm_read_mark_reset_receipt_next221'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next221'],
];

$ticket = static fn (string $name, string $kind): array => [
    'name' => $name,
    'kind' => $kind,
    'source_id' => 'wordpress-next222-sidecar-retired-source',
    'epoch' => 222,
    'checkpoint_frame' => 222,
    'checkpoint_cookie' => 222222,
    'ticket_sha256' => $digest($name . ':' . $kind),
    'sidecar_retired' => true,
    'sync_receipt' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::sourceTicketPlan($next221, [
    $ticket('database-current-source-ticket', 'database'),
    $ticket('wal-current-source-ticket', 'wal'),
    $ticket('journal-current-source-ticket', 'journal'),
    $ticket('shm-current-source-ticket', 'shm'),
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next222');
    assert($plan['source_ticket_sealed'] === true);
    assert($plan['current_source_ticket_action'] === 'seal_current_source_ticket_next222');
    echo "wordpress-wal-source-ticket self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-source-ticket',
    'status' => $plan['status'],
    'sourceTicketSealed' => $plan['source_ticket_sealed'],
    'currentSourceTicketAction' => $plan['current_source_ticket_action'],
    'wordpressUse' => 'A copied wp_options import seals the current-source ticket only after database, WAL, journal, and SHM sidecar receipts match the retired checkpoint source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
