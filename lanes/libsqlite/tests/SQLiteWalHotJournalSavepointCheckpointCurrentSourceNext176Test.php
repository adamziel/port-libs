<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$fixture = static function (array $journalMutation = [], array $ticketMutations = [], array $walReceiptMutations = [], array $receiptMutations = []): array {
    $pageSize = 512;
    $databasePath = '/srv/www/wp-content/database/wp-next176.sqlite';
    $page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
    $database = [
        1 => $page('next176 dirty schema page after plugin import'),
        2 => $page('next176 dirty wp_options root page'),
        3 => $page('next176 dirty active_plugins payload'),
        4 => $page('next176 dirty autoload index page'),
        5 => $page('next176 dirty cron option page'),
        6 => $page('next176 dirty transient timeout page'),
    ];
    $hot = [
        2 => $page('next176 hot journal clean wp_options root'),
        4 => $page('next176 hot journal clean autoload index'),
    ];
    $before = [
        3 => $page('next176 savepoint before active_plugins retry'),
        5 => $page('next176 savepoint before cron retry'),
    ];
    $databaseBytes = implode('', $database);

    $makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
        $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair($prefix, false);
        $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
        foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
            $image = $page($label);
            $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
            $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
            $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        }

        return $bytes;
    };

    $currentWalBytes = $makeWalBytes([
        [1, 0, 'next176 current wal schema draft'],
        [2, 6, 'next176 current wal wp_options commit'],
        [4, 0, 'next176 current wal autoload draft'],
        [5, 6, 'next176 current wal cron commit'],
        [6, 6, 'next176 current wal transient timeout commit'],
    ], 176, 0x17600101, 0x17600102);
    $nextWalBytes = $makeWalBytes([
        [3, 0, 'next176 next wal active_plugins retry draft'],
        [5, 6, 'next176 next wal cron commit'],
        [6, 6, 'next176 next wal transient timeout commit'],
    ], 177, 0x17700101, 0x17700102);
    $currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
    $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

    $rolledBack = $database;
    $rolledBack[2] = $hot[2];
    $rolledBack[4] = $hot[4];
    $rolledBack[3] = $before[3];
    $rolledBack[5] = $before[5];
    ksort($rolledBack, SORT_NUMERIC);
    $rolledBackBytes = implode('', $rolledBack);
    $sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next176|restart|5|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);
    $cache = [
        1 => ['image' => $page('next176 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 177, 'label' => 'schema cache current'],
        2 => ['image' => $page('next176 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 177, 'label' => 'wp_options stale token'],
        3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 176, 'label' => 'active_plugins stale epoch'],
        4 => ['image' => $page('next176 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 177, 'label' => 'autoload stale image'],
        5 => ['image' => $page('next176 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 177, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
        6 => ['image' => $page('next176 current wal transient timeout commit'), 'source_id' => $sourceId, 'epoch' => 177, 'label' => 'transient timeout current'],
    ];
    $checkpointPages = [1, 2, 3, 4, 5, 6];
    $release = ['plugin-import-inner-next176' => [3, 5]];

    $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next166Plan(
        $databasePath,
        $databaseBytes,
        $pageSize,
        'plugin-import-inner-next176',
        'plugin-import-outer-next176',
        $hot,
        $before,
        $currentWal,
        $currentWalBytes,
        $nextWal,
        $nextWalBytes,
        $cache,
        $checkpointPages,
        $release,
        'restart',
        5,
        176,
    );

    $receipts = [];
    foreach ($base['rows'] as $row) {
        $pageNumber = (int) $row['page_number'];
        $receipts[] = [
            'page_number' => $pageNumber,
            'image' => $page((string) $row['checkpoint_label']),
            'source_id' => $base['current_source_token']['id'],
            'epoch' => $base['current_source_token']['epoch'],
            'synced' => true,
            'label' => 'checkpoint write page ' . $pageNumber,
        ];
    }
    foreach ($receiptMutations as $index => $mutation) {
        $receipts[$index] = array_merge($receipts[$index], $mutation);
    }

    $walReceipt = array_merge([
        'path' => $databasePath . '-wal',
        'source_id' => $base['next_source_token']['id'],
        'epoch' => $base['next_source_token']['epoch'],
        'wal_digest' => hash('sha256', $nextWalBytes),
        'synced' => true,
        'label' => 'next WAL sidecar sync',
    ], $walReceiptMutations);

    $journalDigest = (static function (array $pages): string {
        ksort($pages, SORT_NUMERIC);
        $parts = [];
        foreach ($pages as $pageNumber => $image) {
            $parts[] = $pageNumber . ':' . hash('sha256', $image);
        }

        return hash('sha256', implode('|', $parts));
    })($hot);
    $journalReceipt = array_merge([
        'path' => $databasePath . '-journal',
        'journal_digest' => $journalDigest,
        'source_id' => $base['current_source_token']['id'],
        'epoch' => $base['current_source_token']['epoch'],
        'deleted' => true,
        'synced' => true,
        'label' => 'hot journal delete receipt',
    ], $journalMutation);

    $tickets = [
        [
            'reader_id' => 'wp-admin-options-reader',
            'source_id' => $base['next_source_token']['id'],
            'epoch' => $base['next_source_token']['epoch'],
            'wal_digest' => hash('sha256', $nextWalBytes),
            'hot_journal_digest' => null,
            'savepoint_closed' => true,
            'reopened' => true,
            'label' => 'admin options reopen',
        ],
        [
            'reader_id' => 'wp-cron-reader',
            'source_id' => $base['next_source_token']['id'],
            'epoch' => $base['next_source_token']['epoch'],
            'wal_digest' => hash('sha256', $nextWalBytes),
            'hot_journal_digest' => '',
            'savepoint_closed' => true,
            'reopened' => true,
            'label' => 'cron reopen',
        ],
    ];
    foreach ($ticketMutations as $index => $mutation) {
        $tickets[$index] = array_merge($tickets[$index], $mutation);
    }

    $plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next176Plan(
        $databasePath,
        $databaseBytes,
        $pageSize,
        'plugin-import-inner-next176',
        'plugin-import-outer-next176',
        $hot,
        $before,
        $currentWal,
        $currentWalBytes,
        $nextWal,
        $nextWalBytes,
        $cache,
        $checkpointPages,
        $release,
        $receipts,
        $walReceipt,
        $journalReceipt,
        $tickets,
        'restart',
        5,
        176,
    );

    return [$plan, $base, $journalReceipt, $tickets, $page];
};

$ready = static fn (): array => $fixture()[0];
$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-reader-admit-next176'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'hot_journal_delete_and_next_source_reader_tickets_admit_reopen_after_checkpoint'],
    'inherits publish ready' => [static fn (): mixed => $ready()['publish_ready_next172'], true],
    'journal delete admitted' => [static fn (): mixed => $ready()['hot_journal_delete_admitted_next176'], true],
    'reader reopen admitted' => [static fn (): mixed => $ready()['reader_reopen_admitted_next176'], true],
    'publish ready next176' => [static fn (): mixed => $ready()['publish_ready_next176'], true],
    'journal path' => [static fn (): mixed => $ready()['expected_hot_journal_path_next176'], '/srv/www/wp-content/database/wp-next176.sqlite-journal'],
    'journal digest length' => [static fn (): mixed => strlen($ready()['expected_hot_journal_digest_next176']), 64],
    'receipt label' => [static fn (): mixed => $ready()['journal_delete_receipt_next176']['label'], 'hot journal delete receipt'],
    'ticket count' => [static fn (): mixed => count($ready()['reader_ticket_rows_next176']), 2],
    'ticket ids' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'reader_id'), ['wp-admin-options-reader', 'wp-cron-reader']],
    'tickets admitted' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'admitted'), [true, true]],
    'tickets source match' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'source_matches'), [true, true]],
    'tickets wal match' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'wal_digest_matches'), [true, true]],
    'tickets journal cleared' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'hot_journal_cleared'), [true, true]],
    'tickets savepoint closed' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'savepoint_closed'), [true, true]],
    'tickets reopened' => [static fn (): mixed => array_column($ready()['reader_ticket_rows_next176'], 'reopened'), [true, true]],
    'blocked readers empty' => [static fn (): mixed => $ready()['blocked_reader_ids_next176'], []],
    'operation count' => [static fn (): mixed => count($ready()['operations_next176']), 2],
    'operation journal' => [static fn (): mixed => $ready()['operations_next176'][0]['op'], 'validate_hot_journal_delete_receipt_before_next_reader_reopen'],
    'operation reader' => [static fn (): mixed => $ready()['operations_next176'][1]['op'], 'validate_reopened_readers_use_next_wal_source_after_savepoint_checkpoint'],
    'operation deleted synced' => [static fn (): mixed => $ready()['operations_next176'][0]['deleted_and_synced'], true],
    'operation blocked readers empty' => [static fn (): mixed => $ready()['operations_next176'][1]['blocked_readers'], []],
    'operation names suffix' => [static fn (): mixed => array_slice($ready()['operation_names_next176'], -2), [
        'validate_hot_journal_delete_receipt_before_next_reader_reopen',
        'validate_reopened_readers_use_next_wal_source_after_savepoint_checkpoint',
    ]],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest_next176']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next176', $ready()['dependencies_next176'], true), true],
    'reader ticket dependency' => [static fn (): mixed => in_array('sqlite-next-wal-source-reader-ticket', $ready()['dependencies_next176'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure_next176'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap_next176'], 'hot-journal deletion'), true],
    'next source inherited' => [static fn (): mixed => $ready()['next_sources'], ['database', 'database', 'wal', 'database', 'wal', 'wal']],
    'cache inherited' => [static fn (): mixed => $ready()['retained_cache_page_numbers'], [1, 6]],
    'journal not deleted blocks' => [static fn (): mixed => $fixture(['deleted' => false])[0]['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-reader-blocked-next176'],
    'journal unsynced blocks' => [static fn (): mixed => $fixture(['synced' => false])[0]['hot_journal_delete_admitted_next176'], false],
    'journal path mismatch blocks' => [static fn (): mixed => $fixture(['path' => '/tmp/stale-journal'])[0]['hot_journal_delete_admitted_next176'], false],
    'journal digest mismatch blocks' => [static fn (): mixed => $fixture(['journal_digest' => str_repeat('0', 64)])[0]['hot_journal_delete_admitted_next176'], false],
    'journal source mismatch blocks' => [static fn (): mixed => $fixture(['source_id' => 'stale-current-source'])[0]['hot_journal_delete_admitted_next176'], false],
    'journal epoch mismatch blocks' => [static fn (): mixed => $fixture(['epoch' => 999])[0]['hot_journal_delete_admitted_next176'], false],
    'stale reader source blocks' => [static fn (): mixed => $fixture([], [0 => ['source_id' => 'stale-next-source']])[0]['blocked_reader_ids_next176'], ['wp-admin-options-reader']],
    'stale reader source row' => [static fn (): mixed => $fixture([], [0 => ['source_id' => 'stale-next-source']])[0]['reader_ticket_rows_next176'][0]['source_matches'], false],
    'stale reader epoch blocks' => [static fn (): mixed => $fixture([], [1 => ['epoch' => 999]])[0]['blocked_reader_ids_next176'], ['wp-cron-reader']],
    'stale reader wal blocks' => [static fn (): mixed => $fixture([], [0 => ['wal_digest' => str_repeat('f', 64)]])[0]['reader_ticket_rows_next176'][0]['wal_digest_matches'], false],
    'reader with hot journal digest blocks' => [static fn (): mixed => $fixture([], [1 => ['hot_journal_digest' => str_repeat('a', 64)]])[0]['reader_ticket_rows_next176'][1]['hot_journal_cleared'], false],
    'reader savepoint open blocks' => [static fn (): mixed => $fixture([], [0 => ['savepoint_closed' => false]])[0]['reader_ticket_rows_next176'][0]['savepoint_closed'], false],
    'reader not reopened blocks' => [static fn (): mixed => $fixture([], [1 => ['reopened' => false]])[0]['reader_ticket_rows_next176'][1]['reopened'], false],
    'multiple reader blockers' => [static fn (): mixed => $fixture([], [0 => ['source_id' => 'stale'], 1 => ['reopened' => false]])[0]['blocked_reader_ids_next176'], ['wp-admin-options-reader', 'wp-cron-reader']],
    'base wal receipt block inherited' => [static fn (): mixed => $fixture([], [], ['synced' => false])[0]['publish_ready_next176'], false],
    'base database receipt block inherited' => [static fn (): mixed => $fixture([], [], [], [2 => ['source_id' => 'stale']])[0]['publish_ready_next176'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next176 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal hot journal savepoint checkpoint current source next176 rejects empty reader tickets'] = static function (TestRunner $t) use ($fixture): void {
    [$plan, $base, $journalReceipt] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next176Plan(
        $plan['database_path'],
        str_repeat('x', 512),
        512,
        'plugin-import-inner-next176',
        'plugin-import-outer-next176',
        [],
        [],
        SQLiteWal::parse(str_repeat("\0", 32), 512, false),
        str_repeat("\0", 32),
        SQLiteWal::parse(str_repeat("\0", 32), 512, false),
        str_repeat("\0", 32),
        [],
        [1],
        ['plugin-import-inner-next176' => [1]],
        [['page_number' => 1, 'image' => str_repeat('x', 512), 'source_id' => 's', 'epoch' => 1]],
        ['source_id' => 'n', 'epoch' => 1, 'wal_digest' => str_repeat('0', 64), 'synced' => true],
        $journalReceipt,
        [],
    ));
};

$tests['wal hot journal savepoint checkpoint current source next176 rejects malformed reader ticket'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $fixture([], [0 => ['reader_id' => '']]));
};

$tests['wal hot journal savepoint checkpoint current source next176 rejects non integer reader epoch'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $fixture([], [0 => ['epoch' => '177']]));
};

return $tests;
