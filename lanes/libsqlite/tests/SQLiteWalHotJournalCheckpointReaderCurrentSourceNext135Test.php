<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next135.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next135 clean sqlite header before hot journal'),
    2 => $page('next135 clean wp_options root before hot journal'),
    3 => $page('next135 clean active_plugins before hot journal'),
    4 => $page('next135 clean rewrite_rules before hot journal'),
];
$dirtyDatabase = $page('next135 dirty sqlite header from interrupted import')
    . $page('next135 dirty wp_options root from interrupted import')
    . $page('next135 dirty active_plugins from interrupted import')
    . $page('next135 dirty rewrite_rules from interrupted import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026135) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

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

$journalBytes = $makeJournalBytes($cleanPages);
$currentWalBytes = $makeWalBytes([
    [1, 0, 'next135 current reader schema draft'],
    [2, 4, 'next135 current reader wp_options commit'],
    [3, 0, 'next135 current reader active_plugins draft'],
    [4, 4, 'next135 current reader rewrite_rules commit'],
], 135, 0x13513501, 0x13513502);
$nextWalBytes = $makeWalBytes([
    [1, 0, 'next135 current reader schema draft'],
    [2, 4, 'next135 next generation wp_options commit'],
    [3, 0, 'next135 next generation active_plugins draft'],
    [4, 4, 'next135 next generation rewrite_rules commit'],
], 136, 0x13613601, 0x13613602);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$plan = static fn (string $nextBytes = null, bool $reservedLock = false): array => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $currentWal,
    $currentWalBytes,
    $nextBytes ?? $nextWalBytes,
    [1, 2, 3, 4],
    4,
    $reservedLock
);

$ready = static fn (): array => $plan();
$blocked = static fn (): array => $plan($nextWalBytes, true);
$sameSource = static fn (): array => $plan($currentWalBytes);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-checkpoint-reader-current-source-next135'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'current_reader_source_survives_hot_journal_checkpoint_before_next_wal_generation'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-checkpoint-reader-current-source-blocked-next135'],
    'same source blocked' => [static fn (): mixed => $sameSource()['status'], 'wal-hot-journal-checkpoint-reader-current-source-blocked-next135'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'reader end frame' => [static fn (): mixed => $ready()['reader_end_frame'], 4],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'blocked checkpoint disallowed' => [static fn (): mixed => $blocked()['checkpoint_allowed'], false],
    'reader source matches current' => [static fn (): mixed => $ready()['reader_source_matches_current'], true],
    'next source separated' => [static fn (): mixed => $ready()['next_source_separated'], true],
    'same source not separated' => [static fn (): mixed => $sameSource()['next_source_separated'], false],
    'current checkpoint sequence' => [static fn (): mixed => $ready()['current_wal_source']['checkpoint_sequence'], 135],
    'next checkpoint sequence' => [static fn (): mixed => $ready()['next_wal_source']['checkpoint_sequence'], 136],
    'current salt one' => [static fn (): mixed => $ready()['current_wal_source']['salt_1'], 0x13513501],
    'next salt one' => [static fn (): mixed => $ready()['next_wal_source']['salt_1'], 0x13613601],
    'current frame count' => [static fn (): mixed => $ready()['current_frame_count'], 4],
    'next frame count' => [static fn (): mixed => $ready()['next_frame_count'], 4],
    'current sha length' => [static fn (): mixed => strlen($ready()['current_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($ready()['next_wal_sha256']), 64],
    'different wal shas' => [static fn (): mixed => $ready()['current_wal_sha256'] !== $ready()['next_wal_sha256'], true],
    'current sources' => [static fn (): mixed => $ready()['current_sources'], ['wal', 'wal', 'wal', 'wal']],
    'next sources' => [static fn (): mixed => $ready()['next_sources'], ['wal', 'wal', 'wal', 'wal']],
    'current frame indexes' => [static fn (): mixed => $ready()['current_frame_indexes'], [1, 2, 3, 4]],
    'next frame indexes' => [static fn (): mixed => $ready()['next_frame_indexes'], [1, 2, 3, 4]],
    'changed pages' => [static fn (): mixed => $ready()['next_changed_page_numbers'], [2, 3, 4]],
    'changed page count' => [static fn (): mixed => $ready()['next_changed_page_count'], 3],
    'row count' => [static fn (): mixed => count($ready()['rows']), 4],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4]],
    'row one unchanged' => [static fn (): mixed => $ready()['rows'][0]['next_generation_changed_image'], false],
    'row two changed' => [static fn (): mixed => $ready()['rows'][1]['next_generation_changed_image'], true],
    'row three changed' => [static fn (): mixed => $ready()['rows'][2]['next_generation_changed_image'], true],
    'row four changed' => [static fn (): mixed => $ready()['rows'][3]['next_generation_changed_image'], true],
    'current label' => [static fn (): mixed => $ready()['rows'][1]['current_label'], 'next135 current reader wp_options commit'],
    'next label' => [static fn (): mixed => $ready()['rows'][1]['next_label'], 'next135 next generation wp_options commit'],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'wal>checkpoint-reader>wal',
        'wal>checkpoint-reader>wal',
        'wal>checkpoint-reader>wal',
        'wal>checkpoint-reader>wal',
    ]],
    'operation includes pin' => [static fn (): mixed => in_array('pin_current_reader_source_through_hot_journal_checkpoint_next135', $ready()['operation_reasons'], true), true],
    'operation includes next generation' => [static fn (): mixed => in_array('open_next_writer_on_separate_wal_generation_next135', $ready()['operation_reasons'], true), true],
    'base next132 status' => [static fn (): mixed => $ready()['base_plan']['status'], 'wal-checkpoint-reader-hot-journal-current-source-next132'],
    'base checkpoint allowed' => [static fn (): mixed => $ready()['base_plan']['checkpoint_allowed'], true],
    'base restart status' => [static fn (): mixed => $ready()['base_plan']['restart_plan']['status'], 'wal-hot-journal-checkpoint-restart-current-source-next129'],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'dependency next135' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-reader-current-source-next135', $ready()['dependencies'], true), true],
    'dependency next132' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-reader-hot-journal-current-source-next132', $ready()['dependencies'], true), true],
    'dependency source separation' => [static fn (): mixed => in_array('sqlite-wal-next-generation-source-separation', $ready()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $ready()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal checkpoint reader current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty next wal rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, '', [1], 1),
    'empty path rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan('', $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, [1], 1),
    'empty database rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, '', $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, [1], 1),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, '', $currentWal, $currentWalBytes, $nextWalBytes, [1], 1),
    'empty current wal rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, '', $nextWalBytes, [1], 1),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, [], 1),
    'source mismatch rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, substr_replace($currentWalBytes, 'x', 100, 1), $nextWalBytes, [1], 1),
    'zero page rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, [0], 1),
    'string page rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, ['1'], 1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan::next135Plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, [1], 5),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal checkpoint reader current source next135 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
