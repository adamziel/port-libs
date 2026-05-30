<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$fixture = static function (array $sealMutations = [], array $walMutations = []): array {
    $pageSize = 512;
    $databasePath = '/srv/www/wp-content/database/wp-next175.sqlite';
    $page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
    $database = [
        1 => $page('next175 dirty schema page after plugin import'),
        2 => $page('next175 dirty wp_options root page'),
        3 => $page('next175 dirty active_plugins payload'),
        4 => $page('next175 dirty autoload index page'),
        5 => $page('next175 dirty cron option page'),
        6 => $page('next175 dirty transient timeout page'),
    ];
    $hot = [
        2 => $page('next175 hot journal clean wp_options root'),
        4 => $page('next175 hot journal clean autoload index'),
    ];
    $before = [
        3 => $page('next175 savepoint before active_plugins retry'),
        5 => $page('next175 savepoint before cron retry'),
    ];
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
        [1, 0, 'next175 current wal schema draft'],
        [2, 6, 'next175 current wal wp_options commit'],
        [4, 0, 'next175 current wal autoload draft'],
        [5, 6, 'next175 current wal cron commit'],
        [6, 6, 'next175 current wal transient timeout commit'],
    ], 175, 0x17500101, 0x17500102);
    $nextWalBytes = $makeWalBytes([
        [3, 0, 'next175 next wal active_plugins retry draft'],
        [5, 6, 'next175 next wal cron commit'],
        [6, 6, 'next175 next wal transient timeout commit'],
    ], 176, 0x17600101, 0x17600102);
    $currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
    $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

    $rolledBack = $database;
    $rolledBack[2] = $hot[2];
    $rolledBack[4] = $hot[4];
    $rolledBack[3] = $before[3];
    $rolledBack[5] = $before[5];
    ksort($rolledBack, SORT_NUMERIC);
    $sourceId = 'wal-hot-journal-savepoint-checkpoint-next161:current:' . substr(hash('sha256', $databasePath . '|plugin-import-inner-next175|restart|5|' . $currentWalBytes . '|' . implode('', $rolledBack)), 0, 24);
    $cache = [
        1 => ['image' => $page('next175 current wal schema draft'), 'source_id' => $sourceId, 'epoch' => 176],
        2 => ['image' => $page('next175 current wal wp_options commit'), 'source_id' => 'old-source-token', 'epoch' => 176],
        3 => ['image' => $before[3], 'source_id' => $sourceId, 'epoch' => 175],
        4 => ['image' => $page('next175 stale autoload cache image'), 'source_id' => $sourceId, 'epoch' => 176],
        5 => ['image' => $page('next175 current wal cron commit'), 'source_id' => $sourceId, 'epoch' => 176, 'dirty' => true],
        6 => ['image' => $page('next175 current wal transient timeout commit'), 'source_id' => $sourceId, 'epoch' => 176],
    ];
    $checkpointPages = [1, 2, 3, 4, 5, 6];
    $release = ['plugin-import-inner-next175' => [3, 5]];
    $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planSourceTokenHandoff(
        $databasePath,
        implode('', $database),
        $pageSize,
        'plugin-import-inner-next175',
        'plugin-import-outer-next175',
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
        175,
    );

    $receipts = [];
    $seals = [];
    foreach ($base['rows'] as $row) {
        $pageNumber = (int) $row['page_number'];
        $digest = hash('sha256', (string) $row['checkpoint_label']);
        $receipts[] = [
            'page_number' => $pageNumber,
            'image' => $page((string) $row['checkpoint_label']),
            'source_id' => $base['current_source_token']['id'],
            'epoch' => $base['current_source_token']['epoch'],
            'synced' => true,
        ];
        $seals[] = [
            'page_number' => $pageNumber,
            'source_id' => $base['current_source_token']['id'],
            'epoch' => $base['current_source_token']['epoch'],
            'checkpoint_digest' => $digest,
            'sealed' => true,
            'dirty' => false,
        ];
    }
    foreach ($sealMutations as $index => $mutation) {
        if ($mutation === null) {
            unset($seals[$index]);
            continue;
        }
        $seals[$index] = array_merge($seals[$index], $mutation);
    }
    $seals = array_values($seals);
    $walReceipt = array_merge([
        'path' => $databasePath . '-wal',
        'source_id' => $base['next_source_token']['id'],
        'epoch' => $base['next_source_token']['epoch'],
        'wal_digest' => hash('sha256', $nextWalBytes),
        'synced' => true,
    ], $walMutations);

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planAtomicResumeAdmission(
        $databasePath,
        implode('', $database),
        $pageSize,
        'plugin-import-inner-next175',
        'plugin-import-outer-next175',
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
        $seals,
        'restart',
        5,
        175,
    );
};

$ready = static fn (): array => $fixture();
$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-cache-sealed-next175'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'checkpoint_page_cache_seals_admit_reader_cache_reuse_after_publish'],
    'inherits next172 publish' => [static fn (): mixed => $ready()['publish_ready_next172'], true],
    'seals admitted' => [static fn (): mixed => $ready()['page_cache_seals_admitted_next175'], true],
    'reader cache ready' => [static fn (): mixed => $ready()['reader_cache_reuse_ready_next175'], true],
    'sealed pages' => [static fn (): mixed => $ready()['page_cache_sealed_page_numbers_next175'], [1, 2, 3, 4, 5, 6]],
    'missing seals' => [static fn (): mixed => $ready()['missing_page_cache_seals_next175'], []],
    'blocked seals' => [static fn (): mixed => $ready()['blocked_page_cache_seals_next175'], []],
    'seal row count' => [static fn (): mixed => count($ready()['page_cache_seal_rows_next175']), 6],
    'seal row source matches' => [static fn (): mixed => $ready()['page_cache_seal_rows_next175'][0]['source_matches'], true],
    'seal row digest matches' => [static fn (): mixed => $ready()['page_cache_seal_rows_next175'][2]['digest_matches'], true],
    'operation names suffix' => [static fn (): mixed => array_slice($ready()['operation_names_next175'], -2), ['validate_checkpoint_page_cache_seals_before_reader_reuse_next175', 'admit_reopened_reader_cache_after_checkpoint_publish_next175']],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next175', $ready()['dependencies_next175'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($ready()['non_overlap_next175'], 'next176 hot-journal delete'), true],
    'blocked dirty page' => [static fn (): mixed => $fixture([1 => ['dirty' => true]])['blocked_page_cache_seals_next175'], [2]],
    'blocked unsealed page' => [static fn (): mixed => $fixture([2 => ['sealed' => false]])['page_cache_seal_rows_next175'][2]['admitted'], false],
    'blocked stale source' => [static fn (): mixed => $fixture([3 => ['source_id' => 'stale-source']])['page_cache_seal_rows_next175'][3]['source_matches'], false],
    'blocked digest' => [static fn (): mixed => $fixture([4 => ['checkpoint_digest' => str_repeat('0', 64)]])['page_cache_seal_rows_next175'][4]['digest_matches'], false],
    'missing page' => [static fn (): mixed => $fixture([5 => null])['missing_page_cache_seals_next175'], [6]],
    'base publish blocks cache ready' => [static fn (): mixed => $fixture([], ['synced' => false])['reader_cache_reuse_ready_next175'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal hot journal savepoint checkpoint current source next175 empty seals rejected'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(Throwable::class, static fn () => $fixture(array_fill(0, 6, null)));
};

$tests['wal hot journal savepoint checkpoint current source next175 bad seal page rejected'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(Throwable::class, static fn () => $fixture([0 => ['page_number' => 0]]));
};

return $tests;
