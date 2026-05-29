<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$basePages = [
    1 => $page('pinned-reader clean schema page before crashed import'),
    2 => $page('pinned-reader clean wp_options page before checkpoint'),
    3 => $page('pinned-reader clean plugin page before savepoint'),
    4 => $page('pinned-reader clean transient page before savepoint'),
];
$dirtyDatabaseBytes = $page('pinned-reader dirty schema page from hot rollback journal')
    . $basePages[2]
    . $page('pinned-reader dirty plugin page from rolled back savepoint')
    . $page('pinned-reader dirty transient page from rolled back savepoint');

$makeJournalBytes = static function (array $pages) use ($pageSize): string {
    $nonce = 0x16316301;
    $sectorSize = 512;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 4, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function () use ($pageSize, $page): string {
    $salt1 = 0x16316311;
    $salt2 = 0x16316322;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 163, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, 'pinned-reader wal schema retained for pinned reader'],
        [2, 4, 'pinned-reader wal wp_options retained commit'],
        [3, 0, 'pinned-reader wal plugin draft rolled back to savepoint'],
        [4, 4, 'pinned-reader wal transient commit discarded by savepoint'],
    ] as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes([1 => $basePages[1], 3 => $basePages[3]]);
$walBytes = $makeWalBytes();
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('wordpress-import-pinned-reader');
    $savepoints->recordWalFrameWrite(1, 1, false);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin_batch_pinned-reader');
    $savepoints->recordWalFrameWrite(3, 3, false);
    $savepoints->recordWalFrameWrite(4, 4, true);

    return $savepoints;
};

$withFiles = static function (string $databaseBytes, string $journalInput, string $walInput, callable $callback) use ($databasePath): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-hot-savepoint-checkpoint-pinned-reader-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . $databasePath;
    $journalLocal = $databaseLocal . '-journal';
    $walLocal = $databaseLocal . '-wal';
    $directory = dirname($databaseLocal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL hot journal savepoint checkpoint pinned-reader test directory');
    }
    file_put_contents($databaseLocal, $databaseBytes);
    file_put_contents($journalLocal, $journalInput);
    file_put_contents($walLocal, $walInput);

    try {
        return $callback($root, $databaseLocal, $journalLocal, $walLocal);
    } finally {
        foreach ([$walLocal, $journalLocal, $databaseLocal] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        @rmdir($directory);
        @rmdir(dirname($directory));
        @rmdir($root);
    }
};

$apply = static fn (): array => $withFiles(
    $dirtyDatabaseBytes,
    $journalBytes,
    $walBytes,
    static fn (string $root, string $databaseLocal, string $journalLocal, string $walLocal): array => [
        'applied' => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpointPinnedReader(
            $makeStack(),
            'plugin_batch_pinned-reader',
            $databasePath,
            [1, 2, 3, 4],
            1
        ),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'journal_exists' => is_file($journalLocal),
        'wal_bytes' => is_file($walLocal) ? (string) file_get_contents($walLocal) : '',
    ]
);

$skipReserved = static fn (): array => $withFiles(
    $dirtyDatabaseBytes,
    $journalBytes,
    $walBytes,
    static fn (string $root): array => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpointPinnedReader(
        $makeStack(),
        'plugin_batch_pinned-reader',
        $databasePath,
        [1],
        1,
        true
    )
);

$cases = [
    'status applied pinned reader' => [static fn (): mixed => $apply()['applied']['status'], 'applied-pinned-reader'],
    'atomic flag' => [static fn (): mixed => $apply()['applied']['atomic'], true],
    'operation count' => [static fn (): mixed => $apply()['applied']['applied'], 10],
    'files deleted includes hot journal' => [static fn (): mixed => $apply()['applied']['files_deleted'], 1],
    'durable sync count' => [static fn (): mixed => $apply()['applied']['durable_syncs'], 2],
    'directory sync count' => [static fn (): mixed => $apply()['applied']['directory_syncs'], 1],
    'hot recovered' => [static fn (): mixed => $apply()['applied']['hot_journal']['recovered'], true],
    'hot reason' => [static fn (): mixed => $apply()['applied']['hot_journal']['reason'], 'hot_journal_recovered'],
    'checkpoint status busy' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['status'], 'busy'],
    'checkpoint mode restart' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['mode'], 'restart'],
    'checkpoint reason reader pinned' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['reason'], 'reader_blocks_checkpoint_completion'],
    'checkpoint busy true' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['busy'], true],
    'checkpoint cannot reset' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['can_reset'], false],
    'checkpoint cannot truncate' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['can_truncate'], false],
    'original frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['original_frame_count'], 4],
    'retained frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['discarded_frame_count'], 2],
    'current wal length retained' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['current_wal_bytes_length'], 1104],
    'pinned status' => [static fn (): mixed => $apply()['applied']['pinned_reader']['status'], 'wal-hot-journal-savepoint-checkpoint-pinned-reader-pinned-reader'],
    'pinned reader frame' => [static fn (): mixed => $apply()['applied']['pinned_reader']['reader_end_frame'], 1],
    'pinned checkpoint busy' => [static fn (): mixed => $apply()['applied']['pinned_reader']['checkpoint_busy'], true],
    'pinned wal action preserve' => [static fn (): mixed => $apply()['applied']['pinned_reader']['wal_action'], 'preserve_wal'],
    'pinned retained wal bytes' => [static fn (): mixed => $apply()['applied']['pinned_reader']['retained_wal_bytes_length'], 1104],
    'pinned next wal bytes preserved' => [static fn (): mixed => $apply()['applied']['pinned_reader']['next_wal_bytes_length'], 1104],
    'pinned reader kept wal snapshot' => [static fn (): mixed => $apply()['applied']['pinned_reader']['reader_kept_wal_snapshot'], true],
    'pinned prefix preserved' => [static fn (): mixed => $apply()['applied']['pinned_reader']['wal_prefix_preserved_for_pinned_reader'], true],
    'reader boundary status busy' => [static fn (): mixed => $apply()['applied']['reader_boundary']['status'], 'busy'],
    'reader boundary action preserve' => [static fn (): mixed => $apply()['applied']['reader_boundary']['wal_action'], 'preserve_wal'],
    'reader boundary images differ after checkpoint' => [static fn (): mixed => $apply()['applied']['reader_boundary']['images_match'], false],
    'reader boundary current sources' => [static fn (): mixed => $apply()['applied']['reader_boundary']['current_reader_sources'], ['database', 'database', 'database', 'database']],
    'reader boundary next sources' => [static fn (): mixed => $apply()['applied']['reader_boundary']['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'reader boundary current frames' => [static fn (): mixed => $apply()['applied']['reader_boundary']['current_reader_frame_indexes'], [null, null, null, null]],
    'reader boundary next frames' => [static fn (): mixed => $apply()['applied']['reader_boundary']['next_reader_frame_indexes'], [null, null, null, null]],
    'first operation hot journal write' => [static fn (): mixed => $apply()['applied']['operations'][0]['reason'], 'restore_hot_journal_before_savepoint_checkpoint'],
    'third operation deletes journal' => [static fn (): mixed => $apply()['applied']['operations'][2]['reason'], 'delete_hot_journal_before_savepoint_checkpoint'],
    'fourth operation checkpoint write' => [static fn (): mixed => $apply()['applied']['operations'][3]['reason'], 'apply_savepoint_checkpoint_after_hot_journal'],
    'seventh operation wal write' => [static fn (): mixed => $apply()['applied']['operations'][6]['reason'], 'apply_savepoint_checkpoint_wal_after_hot_journal'],
    'journal removed on disk' => [static fn (): mixed => $apply()['journal_exists'], false],
    'wal preserved on disk length' => [static fn (): mixed => strlen($apply()['wal_bytes']), 1104],
    'wal preserved on disk parseable' => [static fn (): mixed => SQLiteWal::parse($apply()['wal_bytes'], $pageSize, true)->frameCount(), 2],
    'database has retained schema wal page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'pinned-reader wal schema retained for pinned reader'), true],
    'database restores clean plugin page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'pinned-reader clean plugin page before savepoint'), true],
    'database excludes dirty schema page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'pinned-reader dirty schema page from hot rollback journal'), false],
    'database excludes discarded plugin draft' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'pinned-reader wal plugin draft rolled back to savepoint'), false],
    'dependency pinned-reader marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-pinned-reader-pinned-reader', $apply()['applied']['dependencies'], true), true],
    'dependency vfs-apply marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-vfs-apply', $apply()['applied']['dependencies'], true), true],
    'dependency atomic marker' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $apply()['applied']['dependencies'], true), true],
    'dependency closure note' => [static fn (): mixed => str_contains($apply()['applied']['pinned_reader']['dependency_closure'], 'no new support component needed'), true],
    'non overlap note' => [static fn (): mixed => str_contains($apply()['applied']['pinned_reader']['non_overlap'], 'pinned current reader'), true],
    'reserved lock skips status' => [static fn (): mixed => $skipReserved()['status'], 'skipped'],
    'reserved lock pinned skipped' => [static fn (): mixed => $skipReserved()['pinned_reader']['status'], 'wal-hot-journal-savepoint-checkpoint-pinned-reader-skipped-pinned-reader'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source pinned-reader ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal hot journal savepoint checkpoint current source pinned-reader rejects empty path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyHotJournalSavepointCheckpointPinnedReader(new SQLiteSavepointStack(), 'x', '', [1], 1));
};

$tests['wal hot journal savepoint checkpoint current source pinned-reader rejects zero reader'] = static function (TestRunner $t) use ($databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyHotJournalSavepointCheckpointPinnedReader(new SQLiteSavepointStack(), 'x', $databasePath, [1], 0));
};

$tests['wal hot journal savepoint checkpoint current source pinned-reader rejects readonly writer'] = static function (TestRunner $t) use ($dirtyDatabaseBytes, $journalBytes, $walBytes, $withFiles, $databasePath, $makeStack): void {
    $withFiles($dirtyDatabaseBytes, $journalBytes, $walBytes, static function (string $root) use ($t, $databasePath, $makeStack): void {
        $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root, readOnly: true))->applyHotJournalSavepointCheckpointPinnedReader($makeStack(), 'plugin_batch_pinned-reader', $databasePath, [1], 1));
    });
};

$tests['wal hot journal savepoint checkpoint current source pinned-reader fixture wal is parseable'] = static function (TestRunner $t) use ($wal): void {
    $t->same(4, $wal->frameCount());
    $t->same(4, $wal->lastCommitFrame()?->index);
};

return $tests;
