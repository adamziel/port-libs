<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$fixture = static function (array $receiptMutations = [], array $walReceiptMutations = [], ?array $released = null): array {
    $pageSize = 512;
    $databasePath = '/srv/www/wp-content/database/wp-next172.sqlite';
    $page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
    $database = [
        1 => $page('next172 dirty schema page after plugin import'),
        2 => $page('next172 dirty wp_options root page'),
        3 => $page('next172 dirty active_plugins payload'),
        4 => $page('next172 dirty autoload index page'),
        5 => $page('next172 dirty cron option page'),
        6 => $page('next172 dirty transient timeout page'),
    ];
    $hot = [
        2 => $page('next172 hot journal clean wp_options root'),
        4 => $page('next172 hot journal clean autoload index'),
    ];
    $before = [
        3 => $page('next172 savepoint before active_plugins retry'),
        5 => $page('next172 savepoint before cron retry'),
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
        [1, 0, 'next172 current wal schema draft'],
        [2, 6, 'next172 current wal wp_options commit'],
        [4, 0, 'next172 current wal autoload draft'],
        [5, 6, 'next172 current wal cron commit'],
        [6, 6, 'next172 current wal transient timeout commit'],
    ], 172, 0x17200101, 0x17200102);
    $nextWalBytes = $makeWalBytes([
        [3, 0, 'next172 next wal active_plugins retry draft'],
        [5, 6, 'next172 next wal cron commit'],
        [6, 6, 'next172 next wal transient timeout commit'],
    ], 173, 0x17300101, 0x17300102);
    $currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
    $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

    $rolledBack = $database;
    $rolledBack[2] = $hot[2];
    $rolledBack[4] = $hot[4];
    $rolledBack[3] = $before[3];
    $rolledBack[5] = $before[5];
    ksort($rolledBack, SORT_NUMERIC);
    $rolledBackBytes = implode('', $rolledBack);
    $sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next172|restart|5|' . $currentWalBytes . '|' . $rolledBackBytes), 0, 24);
    $cache = [
        1 => ['image' => $page('next172 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'schema cache current'],
        2 => ['image' => $page('next172 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 173, 'label' => 'wp_options stale token'],
        3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 172, 'label' => 'active_plugins stale epoch'],
        4 => ['image' => $page('next172 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'autoload stale image'],
        5 => ['image' => $page('next172 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 173, 'dirty' => true, 'label' => 'cron dirty failed savepoint'],
        6 => ['image' => $page('next172 current wal transient timeout commit'), 'source_id' => $sourceId, 'epoch' => 173, 'label' => 'transient timeout current'],
    ];
    $checkpointPages = [1, 2, 3, 4, 5, 6];
    $release = $released ?? ['plugin-import-inner-next172' => [3, 5]];

    $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planSourceTokenHandoff(
        $databasePath,
        $databaseBytes,
        $pageSize,
        'plugin-import-inner-next172',
        'plugin-import-outer-next172',
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
        172,
    );

    $receipts = [];
    foreach ($base['rows'] as $row) {
        $label = (string) $row['checkpoint_label'];
        $pageNumber = (int) $row['page_number'];
        $receipts[] = [
            'page_number' => $pageNumber,
            'image' => $page($label),
            'source_id' => $base['current_source_token']['id'],
            'epoch' => $base['current_source_token']['epoch'],
            'synced' => true,
            'label' => 'checkpoint write page ' . $pageNumber,
        ];
    }
    foreach ($receiptMutations as $index => $mutation) {
        if ($mutation === null) {
            unset($receipts[$index]);
            continue;
        }
        $receipts[$index] = array_merge($receipts[$index], $mutation);
    }
    $receipts = array_values($receipts);

    $walReceipt = array_merge([
        'path' => $databasePath . '-wal',
        'source_id' => $base['next_source_token']['id'],
        'epoch' => $base['next_source_token']['epoch'],
        'wal_digest' => hash('sha256', $nextWalBytes),
        'synced' => true,
        'label' => 'next WAL sidecar sync',
    ], $walReceiptMutations);

    $plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicResumePreparation(
        $databasePath,
        $databaseBytes,
        $pageSize,
        'plugin-import-inner-next172',
        'plugin-import-outer-next172',
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
        'restart',
        5,
        172,
    );

    return [$plan, $base, $receipts, $walReceipt, $page];
};

$ready = static fn (): array => $fixture()[0];
$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-publish-next172'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'checkpoint_database_and_next_wal_sync_receipts_admit_current_source_publish'],
    'base release status preserved' => [static fn (): mixed => $ready()['release_complete'], true],
    'database admitted' => [static fn (): mixed => $ready()['database_write_admitted'], true],
    'wal admitted' => [static fn (): mixed => $ready()['wal_sidecar_admitted'], true],
    'publish ready' => [static fn (): mixed => $ready()['publish_ready_next172'], true],
    'receipt pages' => [static fn (): mixed => $ready()['database_write_receipt_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'missing pages' => [static fn (): mixed => $ready()['missing_database_write_pages'], []],
    'unsynced pages' => [static fn (): mixed => $ready()['unsynced_database_write_pages'], []],
    'stale pages' => [static fn (): mixed => $ready()['stale_database_write_source_pages'], []],
    'image mismatch pages' => [static fn (): mixed => $ready()['image_mismatch_database_write_pages'], []],
    'receipt row count' => [static fn (): mixed => count($ready()['database_write_receipt_rows']), 6],
    'receipt row one admitted' => [static fn (): mixed => $ready()['database_write_receipt_rows'][0]['admitted'], true],
    'receipt row one source matches' => [static fn (): mixed => $ready()['database_write_receipt_rows'][0]['source_matches'], true],
    'receipt row one image matches' => [static fn (): mixed => $ready()['database_write_receipt_rows'][0]['image_matches'], true],
    'receipt row three checkpoint label' => [static fn (): mixed => $ready()['database_write_receipt_rows'][2]['checkpoint_label'], 'next172 savepoint before active_plugins retry'],
    'receipt row five checkpoint label' => [static fn (): mixed => $ready()['database_write_receipt_rows'][4]['checkpoint_label'], 'next172 current wal cron commit'],
    'wal digest matches' => [static fn (): mixed => $ready()['wal_sync_digest_matches'], true],
    'wal source matches' => [static fn (): mixed => $ready()['wal_sync_source_matches'], true],
    'wal label' => [static fn (): mixed => $ready()['wal_sync_receipt']['label'], 'next WAL sidecar sync'],
    'expected wal digest length' => [static fn (): mixed => strlen($ready()['expected_next_wal_digest']), 64],
    'operation count' => [static fn (): mixed => count($ready()['publish_operations_next172']), 3],
    'operation database validate' => [static fn (): mixed => $ready()['publish_operations_next172'][0]['op'], 'validate_checkpoint_database_write_receipts_before_next_source_publish'],
    'operation wal validate' => [static fn (): mixed => $ready()['publish_operations_next172'][1]['op'], 'validate_next_wal_sidecar_sync_receipt_before_reader_reopen'],
    'operation publish' => [static fn (): mixed => $ready()['publish_operations_next172'][2]['op'], 'publish_checkpoint_current_source_after_database_and_wal_sync'],
    'operation required pages' => [static fn (): mixed => $ready()['publish_operations_next172'][0]['required_pages'], [1, 2, 3, 4, 5, 6]],
    'operation publish ready' => [static fn (): mixed => $ready()['publish_operations_next172'][2]['publish_ready'], true],
    'operation names suffix' => [static fn (): mixed => array_slice($ready()['operation_names_next172'], -3), [
        'validate_checkpoint_database_write_receipts_before_next_source_publish',
        'validate_next_wal_sidecar_sync_receipt_before_reader_reopen',
        'publish_checkpoint_current_source_after_database_and_wal_sync',
    ]],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest_next172']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next172', $ready()['dependencies_next172'], true), true],
    'sync dependency marker' => [static fn (): mixed => in_array('sqlite-checkpoint-current-source-publish-sync-receipts', $ready()['dependencies_next172'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ready()['dependency_closure_next172'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap_next172'], 'database/WAL sync-receipt admission'), true],
    'retains current cache' => [static fn (): mixed => $ready()['retained_cache_page_numbers'], [1, 6]],
    'invalidates stale cache' => [static fn (): mixed => $ready()['invalidated_cache_page_numbers'], [2, 3, 4, 5]],
    'next source pages' => [static fn (): mixed => $ready()['next_sources'], ['database', 'database', 'wal', 'database', 'wal', 'wal']],
    'writer barrier survives' => [static fn (): mixed => $ready()['writer_barrier_page_order'], [1, 2, 3, 4, 5, 6]],
    'release rows survive' => [static fn (): mixed => array_column($ready()['release_rows'], 'page_number'), [3, 5]],
    'blocked unsynced status' => [static fn (): mixed => $fixture([1 => ['synced' => false]])[0]['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-publish-blocked-next172'],
    'blocked unsynced reason' => [static fn (): mixed => $fixture([1 => ['synced' => false]])[0]['reason'], 'checkpoint_database_or_next_wal_sync_receipts_block_current_source_publish'],
    'blocked unsynced pages' => [static fn (): mixed => $fixture([1 => ['synced' => false]])[0]['unsynced_database_write_pages'], [2]],
    'blocked unsynced database admitted' => [static fn (): mixed => $fixture([1 => ['synced' => false]])[0]['database_write_admitted'], false],
    'blocked stale source pages' => [static fn (): mixed => $fixture([2 => ['source_id' => 'stale-source-token']])[0]['stale_database_write_source_pages'], [3]],
    'blocked stale row source matches' => [static fn (): mixed => $fixture([2 => ['source_id' => 'stale-source-token']])[0]['database_write_receipt_rows'][2]['source_matches'], false],
    'blocked image mismatch pages' => [static fn (): mixed => $fixture([3 => ['image' => str_pad('next172 wrong autoload image', 512, '.', STR_PAD_RIGHT)]])[0]['image_mismatch_database_write_pages'], [4]],
    'blocked image row matches' => [static fn (): mixed => $fixture([3 => ['image' => str_pad('next172 wrong autoload image', 512, '.', STR_PAD_RIGHT)]])[0]['database_write_receipt_rows'][3]['image_matches'], false],
    'blocked missing page' => [static fn (): mixed => $fixture([4 => null])[0]['missing_database_write_pages'], [5]],
    'blocked wal unsynced' => [static fn (): mixed => $fixture([], ['synced' => false])[0]['wal_sidecar_admitted'], false],
    'blocked wal digest' => [static fn (): mixed => $fixture([], ['wal_digest' => str_repeat('0', 64)])[0]['wal_sync_digest_matches'], false],
    'blocked wal source' => [static fn (): mixed => $fixture([], ['source_id' => 'old-next-source'])[0]['wal_sync_source_matches'], false],
    'blocked missing release inherited' => [static fn (): mixed => $fixture([], [], ['plugin-import-inner-next172' => [3]])[0]['missing_release_page_numbers'], [5]],
    'blocked missing release publish' => [static fn (): mixed => $fixture([], [], ['plugin-import-inner-next172' => [3]])[0]['publish_ready_next172'], false],
    'blocked operation missing pages' => [static fn (): mixed => $fixture([0 => null])[0]['publish_operations_next172'][0]['missing_pages'], [1]],
    'blocked operation unsynced pages' => [static fn (): mixed => $fixture([5 => ['synced' => false]])[0]['publish_operations_next172'][0]['unsynced_pages'], [6]],
    'blocked operation stale pages' => [static fn (): mixed => $fixture([5 => ['source_id' => 'stale']])[0]['publish_operations_next172'][0]['stale_source_pages'], [6]],
    'blocked operation mismatch pages' => [static fn (): mixed => $fixture([5 => ['image' => str_pad('next172 wrong transient image', 512, '.', STR_PAD_RIGHT)]])[0]['publish_operations_next172'][0]['image_mismatch_pages'], [6]],
    'blocked wal operation synced flag' => [static fn (): mixed => $fixture([], ['synced' => false])[0]['publish_operations_next172'][1]['wal_synced'], false],
    'blocked wal operation source matches' => [static fn (): mixed => $fixture([], ['epoch' => 999])[0]['publish_operations_next172'][1]['wal_source_matches'], false],
    'blocked publish operation ready' => [static fn (): mixed => $fixture([], ['wal_digest' => str_repeat('f', 64)])[0]['publish_operations_next172'][2]['publish_ready'], false],
    'base current source token reused' => [static fn (): mixed => $ready()['database_write_receipt_rows'][0]['expected_source_id'] === $ready()['publish_operations_next172'][2]['current_source_id'], true],
    'next source token reused' => [static fn (): mixed => $ready()['wal_sync_receipt']['source_id'] === $ready()['publish_operations_next172'][2]['next_source_id'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next172 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty receipts rejected' => static function () use ($fixture): void {
        [$plan, $base, $receipts, $walReceipt] = $fixture();
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicResumePreparation(
            $plan['database_path'],
            str_repeat('x', 512),
            512,
            'plugin-import-inner-next172',
            'plugin-import-outer-next172',
            [],
            [],
            SQLiteWal::parse(str_repeat("\0", 32), 512, false),
            str_repeat("\0", 32),
            SQLiteWal::parse(str_repeat("\0", 32), 512, false),
            str_repeat("\0", 32),
            [],
            [1],
            ['plugin-import-inner-next172' => [1]],
            [],
            $walReceipt,
        );
    },
    'bad receipt page rejected' => static function () use ($fixture): void {
        [$plan, $base, $receipts, $walReceipt] = $fixture([0 => ['page_number' => 0]]);
    },
    'bad receipt source rejected' => static function () use ($fixture): void {
        [$plan, $base, $receipts, $walReceipt] = $fixture([0 => ['source_id' => '']]);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next172 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
