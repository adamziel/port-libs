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
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next158.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next158 clean schema before crashed import'),
    2 => $page('next158 clean wp_options before crashed import'),
    3 => $page('next158 clean plugin settings before crashed import'),
    4 => $page('next158 clean autoload index before crashed import'),
    5 => $page('next158 clean transient cache before crashed import'),
];
$dirtyDatabase = $page('next158 dirty schema after crashed import')
    . $page('next158 dirty wp_options after crashed import')
    . $page('next158 dirty plugin settings after crashed import')
    . $page('next158 dirty autoload index after crashed import')
    . $page('next158 dirty transient cache after crashed import');

$makeJournal = static function (array $pages, int $initialPageCount = 5, int $nonce = 0x15815815) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $sequence = 158, int $salt1 = 0x15815801, int $salt2 = 0x15815802) use ($pageSize, $page): string {
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
    [2, 0, 'next158 wal draft option before savepoint'],
    [3, 5, 'next158 wal committed plugin before savepoint'],
    [4, 0, 'next158 wal failed autoload savepoint'],
    [5, 5, 'next158 wal failed transient savepoint'],
]);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next158');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('autoload-retry-next158');
    $stack->recordWalFrameWrite(3, 4);
    $stack->recordWalFrameWrite(4, 5, true);

    return $stack;
};

$nextTransactions = [
    [
        'pages' => [
            4 => $page('next158 retry autoload option committed'),
            5 => $page('next158 retry transient cache committed'),
        ],
        'database_page_count' => 5,
        'commit' => true,
    ],
];

$setup = static function (string $journalInput = null, string $walInput = null) use ($databasePath, $dirtyDatabase, $journalBytes, $walBytes): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-hot-savepoint-next158-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create next158 fixture directory');
    }
    file_put_contents($localDatabase, $dirtyDatabase);
    file_put_contents($localDatabase . '-journal', $journalInput ?? $journalBytes);
    file_put_contents($localDatabase . '-wal', $walInput ?? $walBytes);

    return [$root, $localDatabase];
};

$apply = static function (
    ?SQLiteSavepointStack $stack = null,
    string $savepoint = 'autoload-retry-next158',
    array $pages = [1, 2, 3, 4, 5],
    ?array $transactions = null,
    int $readerEndFrame = 2,
    string $journalInput = null,
    string $walInput = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null
) use ($setup, $databasePath, $makeStack, $nextTransactions): array {
    [$root, $localDatabase] = $setup($journalInput, $walInput);
    $applied = (new SQLiteVfsFileWriter($root))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158(
        $stack ?? $makeStack(),
        $savepoint,
        $databasePath,
        $pages,
        $transactions ?? $nextTransactions,
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

$ok = static fn (): array => $apply();
$single = static fn (): array => $apply(null, 'autoload-retry-next158', [4]);
$reserved = static fn (): array => $apply(null, 'autoload-retry-next158', [1], null, 1, null, null, true);
$missingSuper = static fn (): array => $apply(null, 'autoload-retry-next158', [1], null, 1, null, null, false, true, false);

$cases = [
    'status' => [static fn (): mixed => $ok()['applied']['status'], 'applied'],
    'atomic' => [static fn (): mixed => $ok()['applied']['atomic'], true],
    'operation count' => [static fn (): mixed => $ok()['applied']['applied'], 11],
    'files deleted' => [static fn (): mixed => $ok()['applied']['files_deleted'], 1],
    'durable syncs' => [static fn (): mixed => $ok()['applied']['durable_syncs'], 3],
    'directory syncs' => [static fn (): mixed => $ok()['applied']['directory_syncs'], 1],
    'journal removed' => [static fn (): mixed => $ok()['journal_exists'], false],
    'database bytes' => [static fn (): mixed => strlen($ok()['database']), 5 * $pageSize],
    'database has clean schema' => [static fn (): mixed => str_contains($ok()['database'], 'next158 clean schema before crashed import'), true],
    'database has checkpointed option' => [static fn (): mixed => str_contains($ok()['database'], 'next158 wal draft option before savepoint'), true],
    'database has checkpointed plugin' => [static fn (): mixed => str_contains($ok()['database'], 'next158 wal committed plugin before savepoint'), true],
    'database excludes failed autoload' => [static fn (): mixed => str_contains($ok()['database'], 'next158 wal failed autoload savepoint'), false],
    'database excludes retry autoload' => [static fn (): mixed => str_contains($ok()['database'], 'next158 retry autoload option committed'), false],
    'wal has retry autoload' => [static fn (): mixed => str_contains($ok()['wal'], 'next158 retry autoload option committed'), true],
    'wal has retry transient' => [static fn (): mixed => str_contains($ok()['wal'], 'next158 retry transient cache committed'), true],
    'wal excludes failed savepoint' => [static fn (): mixed => str_contains($ok()['wal'], 'next158 wal failed transient savepoint'), false],
    'recovery status' => [static fn (): mixed => $ok()['applied']['recovery']['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next158'],
    'hot recovered' => [static fn (): mixed => $ok()['applied']['recovery']['hot_journal']['recovered'], true],
    'rollback retained' => [static fn (): mixed => $ok()['applied']['recovery']['rollback']['retained_frame_count'], 2],
    'rollback discarded' => [static fn (): mixed => $ok()['applied']['recovery']['rollback']['discarded_frame_count'], 2],
    'rollback bytes' => [static fn (): mixed => $ok()['applied']['recovery']['truncated_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'reader preserved' => [static fn (): mixed => $ok()['applied']['recovery']['reader_preserved_by_pinned_checkpoint'], true],
    'reader sources' => [static fn (): mixed => $ok()['applied']['recovery']['reader_sources'], ['database', 'wal', 'wal', 'database', 'database']],
    'reader frames' => [static fn (): mixed => $ok()['applied']['recovery']['reader_frame_indexes'], [null, 1, 2, null, null]],
    'pinned busy' => [static fn (): mixed => $ok()['applied']['recovery']['pinned_checkpoint']['busy'], true],
    'pinned action' => [static fn (): mixed => $ok()['applied']['recovery']['pinned_checkpoint']['wal_action'], 'preserve_wal'],
    'released ready' => [static fn (): mixed => $ok()['applied']['recovery']['released_checkpoint']['busy'], false],
    'released action' => [static fn (): mixed => $ok()['applied']['recovery']['released_checkpoint']['wal_action'], 'restart_wal'],
    'next append status' => [static fn (): mixed => $ok()['applied']['recovery']['next_append']['status'], 'planned'],
    'next append frames' => [static fn (): mixed => $ok()['applied']['recovery']['next_append']['appended_frame_count'], 2],
    'next append commit' => [static fn (): mixed => $ok()['applied']['recovery']['next_append']['last_commit_frame'], 2],
    'next wal bytes length' => [static fn (): mixed => $ok()['applied']['recovery']['next_generation_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'source database path' => [static fn (): mixed => $ok()['applied']['current_source']['database_path'], $databasePath],
    'source journal bytes' => [static fn (): mixed => $ok()['applied']['current_source']['journal_bytes'], strlen($journalBytes)],
    'source wal bytes' => [static fn (): mixed => $ok()['applied']['current_source']['wal_bytes'], strlen($walBytes)],
    'dependency next158' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next158', $ok()['applied']['dependencies'], true), true],
    'dependency truncation' => [static fn (): mixed => in_array('sqlite-wal-savepoint-byte-truncation', $ok()['applied']['dependencies'], true), true],
    'dependency append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $ok()['applied']['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['applied']['recovery']['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['applied']['recovery']['non_overlap'], 'combined hot-journal plus savepoint rollback'), true],
    'operation reasons' => [static fn (): mixed => array_column($ok()['applied']['operations'], 'reason'), [
        'restore_hot_journal_database_before_savepoint_checkpoint_next158',
        'trim_hot_journal_database_before_savepoint_checkpoint_next158',
        'sync_hot_journal_database_before_savepoint_checkpoint_next158',
        'delete_hot_journal_before_savepoint_checkpoint_next158',
        'apply_released_restart_checkpoint_database_next158',
        'trim_released_restart_checkpoint_database_next158',
        'sync_released_restart_checkpoint_database_next158',
        'write_next_generation_wal_after_savepoint_checkpoint_next158',
        'trim_next_generation_wal_after_savepoint_checkpoint_next158',
        'sync_next_generation_wal_after_savepoint_checkpoint_next158',
        'persist_hot_journal_savepoint_checkpoint_sidecars_next158',
    ]],
    'single reader source' => [static fn (): mixed => $single()['applied']['recovery']['reader_sources'], ['database']],
    'reserved skips' => [static fn (): mixed => $reserved()['applied']['status'], 'skipped'],
    'missing super skips' => [static fn (): mixed => $missingSuper()['applied']['status'], 'skipped'],
];

foreach ($cases as $name => [$actual, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next158 ' . $name] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
}

$throws = [
    'empty path rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', '', [1], $nextTransactions, 1),
    'empty pages rejected' => static fn () => (new SQLiteVfsFileWriter($setup()[0]))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', $databasePath, [], $nextTransactions, 1),
    'empty transactions rejected' => static fn () => (new SQLiteVfsFileWriter($setup()[0]))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', $databasePath, [1], [], 1),
    'missing database rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-next158-missing-' . bin2hex(random_bytes(4))))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', $databasePath, [1], $nextTransactions, 1),
    'zero page rejected' => static fn () => (new SQLiteVfsFileWriter($setup()[0]))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', $databasePath, [0], $nextTransactions, 1),
    'string page rejected' => static fn () => (new SQLiteVfsFileWriter($setup()[0]))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'sp', $databasePath, ['1'], $nextTransactions, 1),
    'past original reader rejected' => static fn () => $apply(null, 'autoload-retry-next158', [1], null, 5),
    'past rollback reader rejected' => static fn () => $apply(null, 'autoload-retry-next158', [1], null, 3),
    'missing savepoint rejected' => static fn () => $apply(null, 'missing-next158', [1], null, 1),
    'read only rejected' => static function () use ($setup, $makeStack, $databasePath, $nextTransactions): mixed {
        [$root] = $setup();

        return (new SQLiteVfsFileWriter($root, true))->applyWalHotJournalSavepointCheckpointCurrentSourceNext158($makeStack(), 'autoload-retry-next158', $databasePath, [1], $nextTransactions, 1);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next158 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
