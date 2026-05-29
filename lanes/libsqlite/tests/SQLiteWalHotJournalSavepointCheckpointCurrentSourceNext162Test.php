<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next162.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next162 clean schema page before failed import'),
    2 => $page('next162 clean wp_options root before failed import'),
    3 => $page('next162 clean plugin settings before failed import'),
    4 => $page('next162 clean transient timeout before failed import'),
];
$dirtyDatabase = $page('next162 dirty schema page from failed import')
    . $page('next162 dirty wp_options root from failed import')
    . $page('next162 dirty plugin settings from failed import')
    . $page('next162 dirty transient timeout from failed import');

$makeJournal = static function (array $pages, int $nonce = 0x16216201) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $checkpoint = 162) use ($pageSize, $page): string {
    $salt1 = 0x16216201;
    $salt2 = 0x16216202;
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

$journalBytes = $makeJournal($cleanPages);
$walBytes = $makeWal([
    [1, 0, 'next162 retained schema draft before checkpoint'],
    [2, 4, 'next162 retained wp_options commit before checkpoint'],
    [3, 0, 'next162 plugin settings draft discarded'],
    [4, 4, 'next162 transient timeout commit discarded'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next162');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next162');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$plan = static fn (string $mode = 'restart', ?int $reader = null, array $pages = [1, 2, 3, 4], bool $reserved = false, ?string $database = null, ?string $journal = null, ?string $walInput = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan(
    $databasePath,
    $database ?? $dirtyDatabase,
    $journal ?? $journalBytes,
    $makeStack(),
    'plugin-batch-next162',
    $wal,
    $walInput ?? $walBytes,
    $pages,
    $mode,
    $reader,
    $reserved
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$baseReader = static fn (): array => $plan('restart', 0);
$single = static fn (): array => $plan('restart', null, [2]);
$blocked = static fn (): array => $plan('restart', null, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next162'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_current_source_required_before_savepoint_checkpoint_publish'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-batch-next162'],
    'mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'page numbers' => [static fn (): mixed => $restart()['page_numbers'], [1, 2, 3, 4]],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'original frames' => [static fn (): mixed => $restart()['original_frame_count'], 4],
    'retained frames' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $restart()['discarded_frame_count'], 2],
    'discarded frame indexes' => [static fn (): mixed => $restart()['discarded_frame_indexes'], [3, 4]],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 2],
    'current checkpoint busy' => [static fn (): mixed => $restart()['current_checkpoint_busy'], true],
    'current checkpoint reason' => [static fn (): mixed => $restart()['current_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'current checkpoint wal action' => [static fn (): mixed => $restart()['current_checkpoint_wal_action'], 'preserve_wal'],
    'released checkpoint busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released checkpoint wal action' => [static fn (): mixed => $restart()['released_checkpoint_wal_action'], 'restart_wal'],
    'stale checkpoint rejected' => [static fn (): mixed => $restart()['stale_checkpoint_rejected'], true],
    'stale dirty pages' => [static fn (): mixed => $restart()['stale_checkpoint_dirty_page_numbers'], [3, 4]],
    'dirty sha length' => [static fn (): mixed => strlen($restart()['dirty_database_sha256']), 64],
    'hot sha length' => [static fn (): mixed => strlen($restart()['hot_database_sha256']), 64],
    'dirty differs hot' => [static fn (): mixed => $restart()['dirty_database_sha256'] !== $restart()['hot_database_sha256'], true],
    'retained wal sha length' => [static fn (): mixed => strlen($restart()['retained_wal_sha256']), 64],
    'current checkpoint wal length' => [static fn (): mixed => $restart()['current_checkpoint_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'released checkpoint wal length' => [static fn (): mixed => $restart()['released_checkpoint_wal_bytes_length'], 32],
    'retained sources' => [static fn (): mixed => $restart()['retained_sources'], ['wal', 'wal', 'database', 'database']],
    'current checkpoint sources' => [static fn (): mixed => $restart()['current_checkpoint_sources'], ['wal', 'wal', 'database', 'database']],
    'released checkpoint sources' => [static fn (): mixed => $restart()['released_checkpoint_sources'], ['database', 'database', 'database', 'database']],
    'retained frames' => [static fn (): mixed => $restart()['retained_frame_indexes'], [1, 2, null, null]],
    'current checkpoint frames' => [static fn (): mixed => $restart()['current_checkpoint_frame_indexes'], [1, 2, null, null]],
    'released checkpoint frames' => [static fn (): mixed => $restart()['released_checkpoint_frame_indexes'], [null, null, null, null]],
    'row count' => [static fn (): mixed => count($restart()['rows']), 4],
    'row one dirty label' => [static fn (): mixed => $restart()['rows'][0]['dirty_label'], 'next162 dirty schema page from failed import'],
    'row one hot label' => [static fn (): mixed => $restart()['rows'][0]['hot_label'], 'next162 clean schema page before failed import'],
    'row two retained label' => [static fn (): mixed => $restart()['rows'][1]['retained_label'], 'next162 retained wp_options commit before checkpoint'],
    'row three released label' => [static fn (): mixed => $restart()['rows'][2]['released_checkpoint_label'], 'next162 clean plugin settings before failed import'],
    'row three stale label' => [static fn (): mixed => $restart()['rows'][2]['stale_checkpoint_label'], 'next162 dirty plugin settings from failed import'],
    'row four stale dirty flag' => [static fn (): mixed => $restart()['rows'][3]['stale_checkpoint_would_publish_dirty'], true],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], [
        'database>database>wal>wal>database',
        'database>database>wal>wal>database',
        'database>database>database>database>database',
        'database>database>database>database>database',
    ]],
    'hot changed dirty' => [static fn (): mixed => $restart()['hot_recovery_replaced_dirty_images'], true],
    'current checkpoint preserved' => [static fn (): mixed => $restart()['current_checkpoint_preserved_retained_images'], true],
    'released checkpoint preserved' => [static fn (): mixed => $restart()['released_checkpoint_preserved_retained_images'], true],
    'current source admitted' => [static fn (): mixed => $restart()['current_source_admitted'], true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'operation recover' => [static fn (): mixed => in_array('recover_hot_journal_before_savepoint_checkpoint_next162', $restart()['operation_reasons'], true), true],
    'operation stale reject' => [static fn (): mixed => in_array('reject_stale_dirty_database_checkpoint_source_next162', $restart()['operation_reasons'], true), true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next162', $restart()['dependencies'], true), true],
    'dependency wordpress marker' => [static fn (): mixed => in_array('wordpress-import-current-source-checkpoint-admission', $restart()['dependencies'], true), true],
    'truncate wal action' => [static fn (): mixed => $truncate()['released_checkpoint_wal_action'], 'truncate_wal'],
    'truncate released wal length' => [static fn (): mixed => $truncate()['released_checkpoint_wal_bytes_length'], 0],
    'base reader retained sources' => [static fn (): mixed => $baseReader()['retained_sources'], ['database', 'database', 'database', 'database']],
    'base reader current checkpoint action' => [static fn (): mixed => $baseReader()['current_checkpoint_wal_action'], 'preserve_wal'],
    'single row transition' => [static fn (): mixed => $single()['source_transitions'], ['database>database>wal>wal>database']],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next162'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'database_has_reserved_lock'],
    'blocked admitted false' => [static fn (): mixed => $blocked()['current_source_admitted'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next162 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan('', $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next162', $wal, $walBytes, [1]),
    'empty database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, '', $journalBytes, $makeStack(), 'plugin-batch-next162', $wal, $walBytes, [1]),
    'empty journal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, $dirtyDatabase, '', $makeStack(), 'plugin-batch-next162', $wal, $walBytes, [1]),
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), '', $wal, $walBytes, [1]),
    'empty wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next162', $wal, '', [1]),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch-next162', $wal, $walBytes, []),
    'bad mode rejected' => static fn () => $plan('passive'),
    'zero page rejected' => static fn () => $plan('restart', null, [0]),
    'string page rejected' => static fn () => $plan('restart', null, ['1']),
    'unaligned database rejected' => static fn () => $plan('restart', null, [1], false, 'short'),
    'wal mismatch rejected' => static fn () => $plan('restart', null, [1], false, null, null, substr_replace($walBytes, 'x', 96, 1)),
    'reader past retained rejected' => static fn () => $plan('restart', 3),
    'missing savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next162Plan($databasePath, $dirtyDatabase, $journalBytes, $makeStack(), 'missing-next162', $wal, $walBytes, [1]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next162 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
