<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-hot-journal-reader-checkpoint-apply.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('hot-journal-reader-checkpoint-apply clean schema before interrupted import'),
    2 => $page('hot-journal-reader-checkpoint-apply clean wp_options before interrupted import'),
    3 => $page('hot-journal-reader-checkpoint-apply clean plugin settings before interrupted import'),
    4 => $page('hot-journal-reader-checkpoint-apply clean autoload index before interrupted import'),
];
$dirtyDatabase = $page('hot-journal-reader-checkpoint-apply dirty schema after interrupted import')
    . $page('hot-journal-reader-checkpoint-apply dirty wp_options after interrupted import')
    . $page('hot-journal-reader-checkpoint-apply dirty plugin settings after interrupted import')
    . $page('hot-journal-reader-checkpoint-apply dirty autoload index after interrupted import');

$makeJournal = static function (array $pages, int $initialPageCount = 4, int $nonce = 0x12501250) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $sequence = 125, int $salt1 = 0x12512501, int $salt2 = 0x12512502) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
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
    [2, 0, 'hot-journal-reader-checkpoint-apply wal draft option edit after hot recovery'],
    [2, 4, 'hot-journal-reader-checkpoint-apply wal committed siteurl after hot recovery'],
    [3, 0, 'hot-journal-reader-checkpoint-apply wal draft plugin settings reader tail'],
    [4, 4, 'hot-journal-reader-checkpoint-apply wal committed autoload cleanup reader tail'],
]);

$setup = static function (string $journalInput = null, string $walInput = null) use ($databasePath, $dirtyDatabase, $journalBytes, $walBytes): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-hot-reader-hot-journal-reader-checkpoint-apply-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create hot-journal-reader-checkpoint-apply WAL hot-reader fixture directory');
    }
    file_put_contents($localDatabase, $dirtyDatabase);
    file_put_contents($localDatabase . '-journal', $journalInput ?? $journalBytes);
    file_put_contents($localDatabase . '-wal', $walInput ?? $walBytes);

    return [$root, $localDatabase];
};

$apply = static function (
    string $mode = 'restart',
    ?int $readerEndFrame = 2,
    string $journalInput = null,
    string $walInput = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null
) use ($setup, $databasePath): array {
    [$root, $localDatabase] = $setup($journalInput, $walInput);
    $applied = (new SQLiteVfsFileWriter($root))->applyWalCheckpointHotJournalReader(
        $databasePath,
        [1, 2, 3, 4],
        $mode,
        $readerEndFrame,
        $reservedLock,
        $requiresSuper,
        $superExists
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'journal_exists' => is_file($localDatabase . '-journal'),
        'wal' => (string) file_get_contents($localDatabase . '-wal'),
        'applied' => $applied,
    ];
};

$restart = static fn (): array => $apply();
$released = static fn (): array => $apply('restart', null);
$truncate = static fn (): array => $apply('truncate', null);
$reserved = static fn (): array => $apply('restart', 2, null, null, true);
$missingSuper = static fn (): array => $apply('restart', 2, null, null, false, true, false);
$presentSuper = static fn (): array => $apply('restart', 2, null, null, false, true, true);

$cases = [
    'apply status' => [static fn (): mixed => $restart()['applied']['status'], 'applied'],
    'apply atomic' => [static fn (): mixed => $restart()['applied']['atomic'], true],
    'operation count' => [static fn (): mixed => $restart()['applied']['applied'], 11],
    'bytes written pinned' => [static fn (): mixed => $restart()['applied']['bytes_written'], 2048 + 2048 + strlen($walBytes)],
    'bytes truncated pinned' => [static fn (): mixed => $restart()['applied']['bytes_truncated'], 2048 + 2048 + strlen($walBytes)],
    'files deleted' => [static fn (): mixed => $restart()['applied']['files_deleted'], 1],
    'durable syncs' => [static fn (): mixed => $restart()['applied']['durable_syncs'], 3],
    'directory syncs' => [static fn (): mixed => $restart()['applied']['directory_syncs'], 1],
    'journal removed' => [static fn (): mixed => $restart()['journal_exists'], false],
    'database has clean schema' => [static fn (): mixed => str_contains($restart()['database'], 'hot-journal-reader-checkpoint-apply clean schema before interrupted import'), true],
    'database has checkpointed siteurl' => [static fn (): mixed => str_contains($restart()['database'], 'hot-journal-reader-checkpoint-apply wal committed siteurl after hot recovery'), true],
    'database keeps clean plugin page while reader pins tail' => [static fn (): mixed => str_contains($restart()['database'], 'hot-journal-reader-checkpoint-apply clean plugin settings before interrupted import'), true],
    'database excludes dirty option page' => [static fn (): mixed => str_contains($restart()['database'], 'hot-journal-reader-checkpoint-apply dirty wp_options after interrupted import'), false],
    'database excludes reader tail plugin draft' => [static fn (): mixed => str_contains($restart()['database'], 'hot-journal-reader-checkpoint-apply wal draft plugin settings reader tail'), false],
    'wal preserved while reader pinned' => [static fn (): mixed => strlen($restart()['wal']), strlen($walBytes)],
    'wal keeps reader tail while pinned' => [static fn (): mixed => str_contains($restart()['wal'], 'hot-journal-reader-checkpoint-apply wal committed autoload cleanup reader tail'), true],
    'checkpoint status' => [static fn (): mixed => $restart()['applied']['checkpoint']['status'], 'wal-checkpoint-hot-journal-reader-hot-journal-reader'],
    'checkpoint reader end frame' => [static fn (): mixed => $restart()['applied']['checkpoint']['reader_end_frame'], 2],
    'checkpoint reader sources' => [static fn (): mixed => $restart()['applied']['checkpoint']['reader_sources'], ['database', 'wal', 'database', 'database']],
    'checkpoint pinned sources' => [static fn (): mixed => $restart()['applied']['checkpoint']['pinned_next_sources'], ['database', 'wal', 'wal', 'wal']],
    'checkpoint released sources' => [static fn (): mixed => $restart()['applied']['checkpoint']['released_next_sources'], ['database', 'database', 'database', 'database']],
    'checkpoint pinned busy' => [static fn (): mixed => $restart()['applied']['checkpoint']['pinned_checkpoint_busy'], true],
    'checkpoint released ready' => [static fn (): mixed => $restart()['applied']['checkpoint']['released_checkpoint_busy'], false],
    'checkpoint release unblocks' => [static fn (): mixed => $restart()['applied']['checkpoint']['reader_release_unblocked_checkpoint'], true],
    'checkpoint restored pages' => [static fn (): mixed => $restart()['applied']['checkpoint']['hot_restored_page_numbers'], [1, 2, 3, 4]],
    'checkpoint journal pages' => [static fn (): mixed => $restart()['applied']['checkpoint']['journal_page_numbers'], [1, 2, 3, 4]],
    'checkpoint source transitions' => [static fn (): mixed => $restart()['applied']['checkpoint']['source_transitions'], [
        'database>database>database>database>database',
        'database>database>wal>wal>database',
        'database>database>database>wal>database',
        'database>database>database>wal>database',
    ]],
    'checkpoint reader counts database' => [static fn (): mixed => $restart()['applied']['checkpoint']['reader_source_counts']['database'], 3],
    'checkpoint reader counts wal' => [static fn (): mixed => $restart()['applied']['checkpoint']['reader_source_counts']['wal'], 1],
    'checkpoint pinned counts wal' => [static fn (): mixed => $restart()['applied']['checkpoint']['pinned_next_source_counts']['wal'], 3],
    'checkpoint released counts database' => [static fn (): mixed => $restart()['applied']['checkpoint']['released_next_source_counts']['database'], 4],
    'checkpoint row two reader label' => [static fn (): mixed => $restart()['applied']['checkpoint']['rows'][1]['reader_label'], 'hot-journal-reader-checkpoint-apply wal committed siteurl after hot recovery'],
    'checkpoint row three pinned label' => [static fn (): mixed => $restart()['applied']['checkpoint']['rows'][2]['pinned_next_label'], 'hot-journal-reader-checkpoint-apply wal draft plugin settings reader tail'],
    'checkpoint row three released label' => [static fn (): mixed => $restart()['applied']['checkpoint']['rows'][2]['released_next_label'], 'hot-journal-reader-checkpoint-apply wal draft plugin settings reader tail'],
    'operation reasons' => [static fn (): mixed => array_column($restart()['applied']['operations'], 'reason'), [
        'restore_hot_journal_current_source_before_reader_checkpoint',
        'trim_hot_journal_current_source_before_reader_checkpoint',
        'sync_hot_journal_current_source_before_reader_checkpoint',
        'delete_hot_journal_before_reader_checkpoint',
        'apply_reader_pinned_checkpoint_database_after_hot_journal',
        'trim_reader_pinned_checkpoint_database_after_hot_journal',
        'sync_reader_pinned_checkpoint_database_after_hot_journal',
        'preserve_reader_pinned_wal_after_hot_journal_checkpoint',
        'trim_reader_pinned_wal_after_hot_journal_checkpoint',
        'sync_reader_pinned_wal_after_hot_journal_checkpoint',
        'persist_hot_journal_reader_checkpoint_sidecars',
    ]],
    'dependency hot-journal-reader-checkpoint-apply' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-checkpoint-vfs-apply-hot-journal-reader-checkpoint-apply', $restart()['applied']['dependencies'], true), true],
    'dependency hot-journal-reader' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-hot-journal-reader-hot-journal-reader', $restart()['applied']['dependencies'], true), true],
    'source database bytes' => [static fn (): mixed => $restart()['applied']['current_source']['database_bytes'], 2048],
    'source journal bytes' => [static fn (): mixed => $restart()['applied']['current_source']['journal_bytes'], strlen($journalBytes)],
    'source wal bytes' => [static fn (): mixed => $restart()['applied']['current_source']['wal_bytes'], strlen($walBytes)],
    'released wal restarted header only' => [static fn (): mixed => strlen($released()['wal']), 32],
    'released database has autoload cleanup' => [static fn (): mixed => str_contains($released()['database'], 'hot-journal-reader-checkpoint-apply wal committed autoload cleanup reader tail'), true],
    'released checkpoint wal action' => [static fn (): mixed => $released()['applied']['checkpoint']['released_wal_action'], 'restart_wal'],
    'truncate wal removed' => [static fn (): mixed => strlen($truncate()['wal']), 0],
    'truncate action' => [static fn (): mixed => $truncate()['applied']['checkpoint']['released_wal_action'], 'truncate_wal'],
    'reserved skipped status' => [static fn (): mixed => $reserved()['applied']['status'], 'skipped'],
    'reserved skipped applied zero' => [static fn (): mixed => $reserved()['applied']['applied'], 0],
    'reserved skipped keeps journal' => [static fn (): mixed => $reserved()['journal_exists'], true],
    'reserved skipped keeps dirty database' => [static fn (): mixed => str_contains($reserved()['database'], 'hot-journal-reader-checkpoint-apply dirty schema after interrupted import'), true],
    'reserved skipped reason' => [static fn (): mixed => $reserved()['applied']['checkpoint']['reason'], 'database_has_reserved_lock'],
    'missing super skipped reason' => [static fn (): mixed => $missingSuper()['applied']['checkpoint']['reason'], 'missing_super_journal'],
    'present super applies' => [static fn (): mixed => $presentSuper()['applied']['status'], 'applied'],
    'present super recovered' => [static fn (): mixed => $presentSuper()['applied']['checkpoint']['hot_recovered'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal reader checkpoint apply current source hot-journal-reader-checkpoint-apply ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyWalCheckpointHotJournalReader('', [1]),
    'missing database rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-hot-journal-reader-checkpoint-apply-missing-' . bin2hex(random_bytes(4))))->applyWalCheckpointHotJournalReader($databasePath, [1]),
    'read only rejected' => static function () use ($setup, $databasePath): mixed {
        [$root] = $setup();
        return (new SQLiteVfsFileWriter($root, true))->applyWalCheckpointHotJournalReader($databasePath, [1], 'restart', 1);
    },
    'immutable rejected' => static function () use ($setup, $databasePath): mixed {
        [$root] = $setup();
        return (new SQLiteVfsFileWriter($root, immutable: true))->applyWalCheckpointHotJournalReader($databasePath, [1], 'restart', 1);
    },
    'bad mode rejected' => static function () use ($setup, $databasePath): mixed {
        [$root] = $setup();
        return (new SQLiteVfsFileWriter($root))->applyWalCheckpointHotJournalReader($databasePath, [1], 'passive', 1);
    },
    'empty pages rejected' => static function () use ($setup, $databasePath): mixed {
        [$root] = $setup();
        return (new SQLiteVfsFileWriter($root))->applyWalCheckpointHotJournalReader($databasePath, []);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal reader checkpoint apply current source hot-journal-reader-checkpoint-apply ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
