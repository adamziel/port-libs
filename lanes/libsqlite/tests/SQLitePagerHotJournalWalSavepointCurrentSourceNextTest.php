<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next124.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next124 clean sqlite header before plugin import'),
    2 => $page('next124 clean wp_options root before plugin import'),
    3 => $page('next124 clean active_plugins before savepoint'),
    4 => $page('next124 clean autoload index before savepoint'),
    5 => $page('next124 clean transient future page before import'),
];
$dirtyDatabase = $page('next124 dirty sqlite header after crashed import')
    . $page('next124 dirty wp_options root after crashed import')
    . $page('next124 dirty active_plugins after crashed import')
    . $page('next124 dirty autoload index after crashed import')
    . $page('next124 dirty transient future page after crashed import');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026124) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 0x20261240) use ($pageSize, $page): string {
    $salt1 = 0x20260528;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 124, $salt1, $salt2);
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
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'next124 wal schema draft from recovered db'],
    [2, 5, 'next124 wal retained wp_options commit'],
    [3, 0, 'next124 wal active_plugins savepoint draft'],
    [4, 5, 'next124 wal autoload index savepoint commit'],
    [5, 0, 'next124 wal uncommitted transient tail ignored'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next124');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings-next124');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 4, true);

    return $stack;
};

$plan = static fn (
    array $pages = [1, 2, 3, 4, 5],
    ?SQLiteRollbackJournal $journalInput = null,
    ?string $journalBytesInput = null,
    ?string $walBytesInput = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null,
): array => SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan(
    $journalInput ?? $journal,
    $dirtyDatabase,
    $journalBytesInput ?? $journalBytes,
    $makeStack(),
    'plugin-settings-next124',
    SQLiteWal::parse($walBytesInput ?? $walBytes, $pageSize, true),
    $walBytesInput ?? $walBytes,
    $databasePath,
    $pages,
    $pageSize,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$restart = static fn (): array => $plan();
$single = static fn (): array => $plan([4]);
$blocked = static fn (): array => $plan([1, 2], null, null, null, false, true, false);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'pager-hot-journal-wal-savepoint-current-source-next124'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovered_before_wal_savepoint_rollback_current_source'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next124'],
    'page size' => [static fn (): mixed => $restart()['page_size'], $pageSize],
    'page numbers' => [static fn (): mixed => $restart()['page_numbers'], [1, 2, 3, 4, 5]],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $restart()['journal_action'], 'delete_journal_after_recovery'],
    'wal status recovered prefix' => [static fn (): mixed => $restart()['wal_status'], 'recovered_committed_prefix'],
    'committed frame count' => [static fn (): mixed => $restart()['committed_frame_count'], 4],
    'valid tail count' => [static fn (): mixed => $restart()['discarded_valid_tail_frame_count'], 1],
    'corrupt tail count' => [static fn (): mixed => $restart()['discarded_corrupt_tail_frame_count'], 0],
    'dirty reader end frame' => [static fn (): mixed => $restart()['dirty_reader_end_frame'], 5],
    'hot reader end frame' => [static fn (): mixed => $restart()['hot_reader_end_frame'], 4],
    'current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'retained frame count' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'savepoint discarded frames' => [static fn (): mixed => $restart()['savepoint_discarded_frame_count'], 2],
    'committed wal bytes length' => [static fn (): mixed => $restart()['committed_wal_bytes_length'], 32 + (4 * (24 + $pageSize))],
    'current wal bytes length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'truncate bytes' => [static fn (): mixed => $restart()['wal_truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'dirty sources' => [static fn (): mixed => $restart()['dirty_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'hot sources' => [static fn (): mixed => $restart()['hot_sources'], ['wal', 'wal', 'wal', 'wal', 'database']],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'database', 'database', 'database']],
    'dirty frames' => [static fn (): mixed => $restart()['dirty_frame_indexes'], [1, 2, 3, 4, null]],
    'hot frames' => [static fn (): mixed => $restart()['hot_frame_indexes'], [1, 2, 3, 4, null]],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, null, null, null]],
    'hot recovery changed images' => [static fn (): mixed => $restart()['hot_recovery_changed_images'], true],
    'savepoint rollback changed images' => [static fn (): mixed => $restart()['savepoint_rollback_changed_images'], true],
    'current uses recovered database' => [static fn (): mixed => $restart()['current_uses_recovered_hot_database'], true],
    'current uses wal prefix' => [static fn (): mixed => $restart()['current_uses_savepoint_wal_prefix'], true],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], ['wal>wal>wal', 'wal>wal>wal', 'wal>wal>database', 'wal>wal>database', 'database>database>database']],
    'row page numbers' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5]],
    'row page one current frame' => [static fn (): mixed => $restart()['rows'][0]['current_frame'], 1],
    'row page two retained label' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next124 wal retained wp_options commit'],
    'row page three rollback changed' => [static fn (): mixed => $restart()['rows'][2]['savepoint_rollback_changed_current'], true],
    'row page four rollback changed' => [static fn (): mixed => $restart()['rows'][3]['savepoint_rollback_changed_current'], true],
    'row page five hot journal restores database page' => [static fn (): mixed => $restart()['rows'][4]['hot_recovery_changed_current'], true],
    'row page three current clean label' => [static fn (): mixed => $restart()['rows'][2]['current_label'], 'next124 clean active_plugins before savepoint'],
    'row page four current clean label' => [static fn (): mixed => $restart()['rows'][3]['current_label'], 'next124 clean autoload index before savepoint'],
    'hot recovery payload exists' => [static fn (): mixed => isset($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal']), true],
    'hot recovery payload includes clean active plugins' => [static fn (): mixed => str_contains($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'clean active_plugins before savepoint'), true],
    'hot recovery payload excludes dirty active plugins' => [static fn (): mixed => str_contains($restart()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'dirty active_plugins after crashed'), false],
    'rollback discarded first frame' => [static fn (): mixed => $restart()['rollback']['discarded_wal_frames'][0]['frame_index'], 3],
    'rollback discarded second frame' => [static fn (): mixed => $restart()['rollback']['discarded_wal_frames'][1]['frame_index'], 4],
    'operation count' => [static fn (): mixed => count($restart()['operations']), 5],
    'operation zero restore hot' => [static fn (): mixed => $restart()['operations'][0]['reason'], 'restore_hot_journal_database_before_wal_savepoint_current_source_next124'],
    'operation one recover wal prefix' => [static fn (): mixed => $restart()['operations'][1]['reason'], 'recover_committed_wal_prefix_before_savepoint_rollback_current_source_next124'],
    'operation three savepoint truncate' => [static fn (): mixed => $restart()['operations'][3]['reason'], 'rollback_savepoint_to_hot_journal_recovered_wal_prefix_next124'],
    'operation four delete journal' => [static fn (): mixed => $restart()['operations'][4]['reason'], 'delete_hot_journal_after_current_source_recovery_next124'],
    'operation reasons include tail discard' => [static fn (): mixed => in_array('discard_wal_tail_before_savepoint_rollback_current_source_next124', $restart()['operation_reasons'], true), true],
    'current source verified' => [static fn (): mixed => $restart()['current_source_verified'], true],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'committed sha length' => [static fn (): mixed => strlen($restart()['committed_wal_sha256']), 64],
    'retained sha length' => [static fn (): mixed => strlen($restart()['retained_wal_sha256']), 64],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'dependency next124' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-savepoint-current-source-next124', $restart()['dependencies'], true), true],
    'dependency hot before wal savepoint' => [static fn (): mixed => in_array('sqlite-hot-journal-before-wal-savepoint-current-source', $restart()['dependencies'], true), true],
    'dependency recovered prefix' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-uses-recovered-wal-prefix', $restart()['dependencies'], true), true],
    'dependency wal recovery' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-recovery', $restart()['dependencies'], true), true],
    'single page current source' => [static fn (): mixed => $single()['current_sources'], ['database']],
    'single page label' => [static fn (): mixed => $single()['rows'][0]['current_label'], 'next124 clean autoload index before savepoint'],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'pager-hot-journal-wal-savepoint-current-source-skipped-next124'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'hot_journal_not_recovered_before_wal_savepoint_rollback_current_source'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked journal action preserve' => [static fn (): mixed => $blocked()['journal_action'], 'preserve_journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal wal savepoint current source next124 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), '', $wal, $walBytes, $databasePath, [1], $pageSize),
    'empty path rejected' => static fn () => SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next124', $wal, $walBytes, '', [1], $pageSize),
    'empty pages rejected' => static fn () => SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next124', $wal, $walBytes, $databasePath, [], $pageSize),
    'zero page rejected' => static fn () => $plan([0]),
    'string page rejected' => static fn () => $plan(['1']),
    'stale journal bytes rejected' => static fn () => $plan([1], null, substr($journalBytes, 0, -1) . 'x'),
    'stale parsed journal rejected' => static fn () => $plan([1], SQLiteRollbackJournal::parse($makeJournalBytes([1 => $page('next124 stale clean page')]), true)),
    'stale wal bytes rejected' => static fn () => SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan::plan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-settings-next124', $wal, substr($walBytes, 0, -1) . 'x', $databasePath, [1], $pageSize),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal wal savepoint current source next124 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
