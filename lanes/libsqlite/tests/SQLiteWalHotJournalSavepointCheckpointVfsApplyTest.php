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
    1 => $page('vfs-apply clean schema page before crashed import'),
    2 => $page('vfs-apply clean wp_options page before checkpoint'),
    3 => $page('vfs-apply clean plugin page before savepoint'),
    4 => $page('vfs-apply clean transient page before savepoint'),
];
$dirtyDatabaseBytes = $page('vfs-apply dirty schema page from hot rollback journal')
    . $basePages[2]
    . $page('vfs-apply dirty plugin page from rolled back savepoint')
    . $page('vfs-apply dirty transient page from rolled back savepoint');

$makeJournalBytes = static function (array $pages) use ($pageSize): string {
    $nonce = 0x16016001;
    $sectorSize = 512;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 4, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function () use ($pageSize, $page): string {
    $salt1 = 0x16016011;
    $salt2 = 0x16016022;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 160, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ([
        [1, 0, 'vfs-apply wal schema committed after hot journal'],
        [2, 4, 'vfs-apply wal wp_options committed after hot journal'],
        [3, 0, 'vfs-apply wal plugin draft rolled back to savepoint'],
        [4, 4, 'vfs-apply wal transient commit discarded by savepoint'],
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
    $savepoints->beginTransaction('application-import-vfs-apply');
    $savepoints->recordWalFrameWrite(1, 1, false);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin_batch_vfs-apply');
    $savepoints->recordWalFrameWrite(3, 3, false);
    $savepoints->recordWalFrameWrite(4, 4, true);

    return $savepoints;
};

$withFiles = static function (string $databaseBytes, string $journalInput, string $walInput, callable $callback) use ($databasePath): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-hot-savepoint-checkpoint-vfs-apply-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . $databasePath;
    $journalLocal = $databaseLocal . '-journal';
    $walLocal = $databaseLocal . '-wal';
    $directory = dirname($databaseLocal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL hot journal savepoint checkpoint test directory');
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
        'applied' => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpoint(
            $makeStack(),
            'plugin_batch_vfs-apply',
            $databasePath,
            [1, 2, 3, 4],
            'truncate'
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
    static fn (string $root): array => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpoint(
        $makeStack(),
        'plugin_batch_vfs-apply',
        $databasePath,
        [1],
        'truncate',
        null,
        null,
        true
    )
);

$cases = [
    'status applied' => [static fn (): mixed => $apply()['applied']['status'], 'applied'],
    'atomic flag' => [static fn (): mixed => $apply()['applied']['atomic'], true],
    'operation count' => [static fn (): mixed => $apply()['applied']['applied'], 10],
    'bytes written includes hot and checkpoint database' => [static fn (): mixed => $apply()['applied']['bytes_written'], 4096],
    'bytes truncated includes hot and checkpoint database' => [static fn (): mixed => $apply()['applied']['bytes_truncated'], 4096],
    'files deleted includes hot journal' => [static fn (): mixed => $apply()['applied']['files_deleted'], 1],
    'durable sync count' => [static fn (): mixed => $apply()['applied']['durable_syncs'], 2],
    'directory sync count' => [static fn (): mixed => $apply()['applied']['directory_syncs'], 1],
    'hot recovered' => [static fn (): mixed => $apply()['applied']['hot_journal']['recovered'], true],
    'hot reason' => [static fn (): mixed => $apply()['applied']['hot_journal']['reason'], 'hot_journal_recovered'],
    'hot journal action' => [static fn (): mixed => $apply()['applied']['hot_journal']['journal_action'], 'delete_journal_after_recovery'],
    'checkpoint status ready' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['status'], 'ready'],
    'checkpoint reason' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['reason'], 'truncate_checkpoint_can_reset_and_truncate_wal'],
    'checkpoint mode' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['mode'], 'truncate'],
    'checkpoint can truncate' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['can_truncate'], true],
    'checkpoint not busy' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['busy'], false],
    'original frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['original_frame_count'], 4],
    'retained frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['retained_frame_count'], 2],
    'discarded frame count' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['discarded_frame_count'], 2],
    'truncate to retained bytes' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['truncate_to_bytes'], 1104],
    'current wal length' => [static fn (): mixed => $apply()['applied']['savepoint_checkpoint']['current_wal_bytes_length'], 1104],
    'reader boundary status' => [static fn (): mixed => $apply()['applied']['reader_boundary']['status'], 'ready'],
    'reader boundary action' => [static fn (): mixed => $apply()['applied']['reader_boundary']['wal_action'], 'truncate_wal'],
    'reader boundary images match' => [static fn (): mixed => $apply()['applied']['reader_boundary']['images_match'], true],
    'reader uses checkpoint database' => [static fn (): mixed => $apply()['applied']['reader_boundary']['next_reader_uses_checkpoint_database'], true],
    'current reader kept wal snapshot' => [static fn (): mixed => $apply()['applied']['reader_boundary']['current_reader_kept_wal_snapshot'], true],
    'current reader sources' => [static fn (): mixed => $apply()['applied']['reader_boundary']['current_reader_sources'], ['wal', 'wal', 'database', 'database']],
    'next reader sources' => [static fn (): mixed => $apply()['applied']['reader_boundary']['next_reader_sources'], ['database', 'database', 'database', 'database']],
    'current reader frames' => [static fn (): mixed => $apply()['applied']['reader_boundary']['current_reader_frame_indexes'], [1, 2, null, null]],
    'next reader frames' => [static fn (): mixed => $apply()['applied']['reader_boundary']['next_reader_frame_indexes'], [null, null, null, null]],
    'first operation hot journal write' => [static fn (): mixed => $apply()['applied']['operations'][0]['reason'], 'restore_hot_journal_before_savepoint_checkpoint'],
    'third operation deletes journal' => [static fn (): mixed => $apply()['applied']['operations'][2]['reason'], 'delete_hot_journal_before_savepoint_checkpoint'],
    'fourth operation checkpoint write' => [static fn (): mixed => $apply()['applied']['operations'][3]['reason'], 'apply_savepoint_checkpoint_after_hot_journal'],
    'seventh operation wal write' => [static fn (): mixed => $apply()['applied']['operations'][6]['reason'], 'apply_savepoint_checkpoint_wal_after_hot_journal'],
    'journal removed on disk' => [static fn (): mixed => $apply()['journal_exists'], false],
    'wal truncated on disk' => [static fn (): mixed => $apply()['wal_bytes'], ''],
    'database has committed schema wal page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply wal schema committed after hot journal'), true],
    'database has committed option wal page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply wal wp_options committed after hot journal'), true],
    'database restores clean plugin page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply clean plugin page before savepoint'), true],
    'database excludes dirty schema page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply dirty schema page from hot rollback journal'), false],
    'database excludes dirty plugin page' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply dirty plugin page from rolled back savepoint'), false],
    'database excludes discarded wal plugin draft' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply wal plugin draft rolled back to savepoint'), false],
    'database excludes discarded wal transient commit' => [static fn (): mixed => str_contains($apply()['database_bytes'], 'vfs-apply wal transient commit discarded by savepoint'), false],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-rollback-journal-hot-recovery', $apply()['applied']['dependencies'], true), true],
    'dependency checkpoint current' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $apply()['applied']['dependencies'], true), true],
    'dependency reader boundary' => [static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-boundary-current-next', $apply()['applied']['dependencies'], true), true],
    'dependency vfs apply vfs-apply' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-vfs-apply', $apply()['applied']['dependencies'], true), true],
    'dependency atomic rollback' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $apply()['applied']['dependencies'], true), true],
    'reserved lock skips status' => [static fn (): mixed => $skipReserved()['status'], 'skipped'],
    'reserved lock skips applied operations' => [static fn (): mixed => $skipReserved()['applied'], 0],
    'reserved lock reason' => [static fn (): mixed => $skipReserved()['hot_journal']['reason'], 'database_has_reserved_lock'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source vfs-apply ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal hot journal savepoint checkpoint current source vfs-apply rejects empty path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyHotJournalSavepointCheckpoint(new SQLiteSavepointStack(), 'x', '', [1]));
};

$tests['wal hot journal savepoint checkpoint current source vfs-apply rejects readonly writer'] = static function (TestRunner $t) use ($dirtyDatabaseBytes, $journalBytes, $walBytes, $withFiles, $databasePath, $makeStack): void {
    $withFiles($dirtyDatabaseBytes, $journalBytes, $walBytes, static function (string $root) use ($t, $databasePath, $makeStack): void {
        $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root, readOnly: true))->applyHotJournalSavepointCheckpoint($makeStack(), 'plugin_batch_vfs-apply', $databasePath, [1]));
    });
};

$tests['wal hot journal savepoint checkpoint current source vfs-apply rejects missing savepoint'] = static function (TestRunner $t) use ($dirtyDatabaseBytes, $journalBytes, $walBytes, $withFiles, $databasePath, $makeStack): void {
    $withFiles($dirtyDatabaseBytes, $journalBytes, $walBytes, static function (string $root) use ($t, $databasePath, $makeStack): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpoint($makeStack(), 'missing_savepoint', $databasePath, [1]));
    });
};

$tests['wal hot journal savepoint checkpoint current source vfs-apply rejects bad wal checksum'] = static function (TestRunner $t) use ($dirtyDatabaseBytes, $journalBytes, $walBytes, $withFiles, $databasePath, $makeStack): void {
    $badWal = substr_replace($walBytes, 'x', 128, 1);
    $withFiles($dirtyDatabaseBytes, $journalBytes, $badWal, static function (string $root) use ($t, $databasePath, $makeStack): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpoint($makeStack(), 'plugin_batch_vfs-apply', $databasePath, [1]));
    });
};

$tests['wal hot journal savepoint checkpoint current source vfs-apply fixture wal is parseable'] = static function (TestRunner $t) use ($wal): void {
    $t->same(4, $wal->frameCount());
    $t->same(4, $wal->lastCommitFrame()?->index);
};

return $tests;
