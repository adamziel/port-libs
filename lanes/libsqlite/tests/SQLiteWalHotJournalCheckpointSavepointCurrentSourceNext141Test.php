<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next141.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next141 clean sqlite header before import'),
    2 => $page('next141 clean wp_options root before import'),
    3 => $page('next141 clean autoload index before import'),
    4 => $page('next141 clean plugin settings before import'),
    5 => $page('next141 clean transient timeout before import'),
];
$dirtyDatabase = $page('next141 dirty sqlite header from failed import')
    . $page('next141 dirty wp_options root from failed import')
    . $page('next141 dirty autoload index from failed import')
    . $page('next141 dirty plugin settings from failed import')
    . $page('next141 dirty transient timeout from failed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x14114101) use ($sectorSize, $pageSize): string {
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
    [1, 0, 'next141 retained schema draft before checkpoint'],
    [2, 5, 'next141 retained wp_options commit before checkpoint'],
    [3, 0, 'next141 savepoint autoload draft discarded'],
    [4, 5, 'next141 savepoint plugin settings commit discarded'],
    [5, 5, 'next141 savepoint transient retry discarded'],
], 141, 0x14114101, 0x14114102);
$nextWalBytes = $makeWalBytes([
    [1, 0, 'next141 retained schema draft before checkpoint'],
    [2, 5, 'next141 next writer wp_options commit'],
    [3, 0, 'next141 next writer autoload draft'],
    [4, 5, 'next141 next writer plugin settings commit'],
    [5, 5, 'next141 next writer transient timeout commit'],
], 142, 0x14214201, 0x14214202);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next141');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next141');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);
    $stack->recordWalFrameWrite(5, 5, true);

    return $stack;
};

$plan = static function (
    ?int $readerEndFrame = null,
    array $pages = [1, 2, 3, 4, 5],
    ?string $nextBytes = null,
    bool $reserved = false,
    bool $requiresSuper = false,
    ?bool $superExists = null,
    ?string $databaseBytes = null,
    ?string $journalInput = null,
    ?string $walInput = null,
) use ($databasePath, $dirtyDatabase, $journalBytes, $makeStack, $currentWal, $currentWalBytes, $nextWalBytes): array {
    return SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan(
        $databasePath,
        $databaseBytes ?? $dirtyDatabase,
        $journalInput ?? $journalBytes,
        $makeStack(),
        'plugin-batch-next141',
        $currentWal,
        $walInput ?? $currentWalBytes,
        $nextBytes ?? $nextWalBytes,
        $pages,
        $readerEndFrame,
        $reserved,
        $requiresSuper,
        $superExists
    );
};

$ready = static fn (): array => $plan();
$baseReader = static fn (): array => $plan(0);
$single = static fn (): array => $plan(null, [2]);
$reserved = static fn (): array => $plan(null, [1, 2], null, true);
$missingSuper = static fn (): array => $plan(null, [1, 2], null, false, true, false);
$sameSource = static fn (): array => $plan(null, [1, 2], $currentWalBytes);

$cases = [
    'status' => [static fn (): mixed => $ready()['status'], 'wal-hot-journal-checkpoint-savepoint-current-source-next141'],
    'reason' => [static fn (): mixed => $ready()['reason'], 'current_reader_keeps_savepoint_wal_source_after_hot_journal_checkpoint'],
    'database path' => [static fn (): mixed => $ready()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ready()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ready()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $ready()['savepoint'], 'plugin-batch-next141'],
    'page size' => [static fn (): mixed => $ready()['page_size'], 512],
    'page numbers' => [static fn (): mixed => $ready()['page_numbers'], [1, 2, 3, 4, 5]],
    'hot recovered' => [static fn (): mixed => $ready()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $ready()['journal_action'], 'delete_journal_after_recovery'],
    'transaction reason' => [static fn (): mixed => $ready()['transaction_reason'], 'all_frames_valid'],
    'original frames' => [static fn (): mixed => $ready()['original_frame_count'], 5],
    'committed frames' => [static fn (): mixed => $ready()['committed_frame_count'], 5],
    'retained frames' => [static fn (): mixed => $ready()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $ready()['discarded_frame_count'], 3],
    'discarded frame indexes' => [static fn (): mixed => $ready()['discarded_frame_indexes'], [3, 4, 5]],
    'discarded pages' => [static fn (): mixed => $ready()['discarded_page_numbers'], [3, 4, 5]],
    'current reader end frame' => [static fn (): mixed => $ready()['current_reader_end_frame'], 2],
    'checkpoint reader end frame' => [static fn (): mixed => $ready()['checkpoint_reader_end_frame'], 2],
    'next reader end frame' => [static fn (): mixed => $ready()['next_reader_end_frame'], 5],
    'checkpoint allowed' => [static fn (): mixed => $ready()['checkpoint_allowed'], true],
    'checkpoint busy' => [static fn (): mixed => $ready()['checkpoint_busy'], true],
    'checkpoint reason' => [static fn (): mixed => $ready()['checkpoint_reason'], 'reader_blocks_wal_reset'],
    'checkpoint wal action' => [static fn (): mixed => $ready()['checkpoint_wal_action'], 'preserve_wal'],
    'checkpoint wal bytes length' => [static fn (): mixed => $ready()['checkpoint_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'checkpoint database bytes length' => [static fn (): mixed => $ready()['checkpoint_database_bytes_length'], strlen($dirtyDatabase)],
    'current source sequence' => [static fn (): mixed => $ready()['current_wal_source']['checkpoint_sequence'], 141],
    'next source sequence' => [static fn (): mixed => $ready()['next_wal_source']['checkpoint_sequence'], 142],
    'current salt one' => [static fn (): mixed => $ready()['current_wal_source']['salt_1'], 0x14114101],
    'next salt one' => [static fn (): mixed => $ready()['next_wal_source']['salt_1'], 0x14214201],
    'current sha length' => [static fn (): mixed => strlen($ready()['current_wal_source']['sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($ready()['next_wal_source']['sha256']), 64],
    'source separated' => [static fn (): mixed => $ready()['next_source_separated'], true],
    'same source blocked' => [static fn (): mixed => $sameSource()['status'], 'wal-hot-journal-checkpoint-savepoint-current-source-blocked-next141'],
    'current sources' => [static fn (): mixed => $ready()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'checkpoint sources' => [static fn (): mixed => $ready()['checkpoint_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $ready()['next_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'current frames' => [static fn (): mixed => $ready()['current_frame_indexes'], [1, 2, null, null, null]],
    'checkpoint frames' => [static fn (): mixed => $ready()['checkpoint_frame_indexes'], [1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => $ready()['next_frame_indexes'], [1, 2, 3, 4, 5]],
    'current wal count' => [static fn (): mixed => $ready()['current_source_counts']['wal'], 2],
    'checkpoint database count' => [static fn (): mixed => $ready()['checkpoint_source_counts']['database'], 3],
    'next wal count' => [static fn (): mixed => $ready()['next_source_counts']['wal'], 5],
    'row count' => [static fn (): mixed => count($ready()['rows']), 5],
    'row pages' => [static fn (): mixed => array_column($ready()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row one dirty label' => [static fn (): mixed => $ready()['rows'][0]['dirty_label'], 'next141 dirty sqlite header from failed import'],
    'row one hot label' => [static fn (): mixed => $ready()['rows'][0]['hot_label'], 'next141 clean sqlite header before import'],
    'row one current label' => [static fn (): mixed => $ready()['rows'][0]['current_label'], 'next141 retained schema draft before checkpoint'],
    'row two current label' => [static fn (): mixed => $ready()['rows'][1]['current_label'], 'next141 retained wp_options commit before checkpoint'],
    'row two next label' => [static fn (): mixed => $ready()['rows'][1]['next_label'], 'next141 next writer wp_options commit'],
    'row three checkpoint label' => [static fn (): mixed => $ready()['rows'][2]['checkpoint_label'], 'next141 clean autoload index before import'],
    'row five next changed' => [static fn (): mixed => $ready()['rows'][4]['next_generation_changed_current'], true],
    'source transitions' => [static fn (): mixed => $ready()['source_transitions'], [
        'database>database>wal>wal>wal',
        'database>database>wal>wal>wal',
        'database>database>database>database>wal',
        'database>database>database>database>wal',
        'database>database>database>database>wal',
    ]],
    'hot replaced dirty' => [static fn (): mixed => $ready()['hot_recovery_replaced_dirty_images'], true],
    'checkpoint preserved current' => [static fn (): mixed => $ready()['checkpoint_preserved_current_images'], true],
    'next changed pages' => [static fn (): mixed => $ready()['next_changed_page_numbers'], [2, 3, 4, 5]],
    'current source verified' => [static fn (): mixed => $ready()['current_source_verified'], true],
    'source digest length' => [static fn (): mixed => strlen($ready()['source_digest']), 64],
    'base reader current sources' => [static fn (): mixed => $baseReader()['current_sources'], ['database', 'database', 'database', 'database', 'database']],
    'base reader checkpoint action' => [static fn (): mixed => $baseReader()['checkpoint_wal_action'], 'preserve_wal'],
    'single row transition' => [static fn (): mixed => $single()['source_transitions'], ['database>database>wal>wal>wal']],
    'reserved blocked status' => [static fn (): mixed => $reserved()['status'], 'wal-hot-journal-checkpoint-savepoint-current-source-blocked-next141'],
    'reserved blocked reason' => [static fn (): mixed => $reserved()['reason'], 'database_has_reserved_lock'],
    'missing super blocked reason' => [static fn (): mixed => $missingSuper()['reason'], 'missing_super_journal'],
    'operation recover' => [static fn (): mixed => in_array('recover_hot_journal_before_savepoint_checkpoint_next141', $ready()['operation_reasons'], true), true],
    'operation rollback' => [static fn (): mixed => in_array('rollback_savepoint_to_current_wal_source_next141', $ready()['operation_reasons'], true), true],
    'operation preserve reader' => [static fn (): mixed => in_array('preserve_current_reader_during_restart_checkpoint_next141', $ready()['operation_reasons'], true), true],
    'operation next writer' => [static fn (): mixed => in_array('open_next_writer_on_separate_wal_source_next141', $ready()['operation_reasons'], true), true],
    'dependency next141' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-checkpoint-savepoint-current-source-next141', $ready()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $ready()['dependencies'], true), true],
    'dependency savepoint truncation' => [static fn (): mixed => in_array('sqlite-savepoint-wal-prefix-truncation', $ready()['dependencies'], true), true],
    'dependency source separation' => [static fn (): mixed => in_array('sqlite-wal-next-generation-source-separation', $ready()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal checkpoint savepoint current source next141 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan('', $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next141', $currentWal, $currentWalBytes, $nextWalBytes, [1]),
    'empty database rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, '', $journalBytes, $makeStack(), 'plugin-batch-next141', $currentWal, $currentWalBytes, $nextWalBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, '', $makeStack(), 'plugin-batch-next141', $currentWal, $currentWalBytes, $nextWalBytes, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), '', $currentWal, $currentWalBytes, $nextWalBytes, [1]),
    'empty current wal rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next141', $currentWal, '', $nextWalBytes, [1]),
    'empty next wal rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next141', $currentWal, $currentWalBytes, '', [1]),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next141', $currentWal, $currentWalBytes, $nextWalBytes, []),
    'zero page rejected' => static fn () => $plan(null, [0]),
    'string page rejected' => static fn () => $plan(null, ['1']),
    'unaligned database rejected' => static fn () => $plan(null, [1], null, false, false, null, 'short'),
    'current wal source mismatch rejected' => static fn () => $plan(null, [1], null, false, false, null, null, null, substr_replace($currentWalBytes, 'x', 96, 1)),
    'reader outside retained range rejected' => static fn () => $plan(3),
    'missing savepoint rejected' => static fn () => SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'missing-next141', $currentWal, $currentWalBytes, $nextWalBytes, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal checkpoint savepoint current source next141 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
