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
$databasePath = '/wp-content/database/wp-next117.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$local = static fn (string $root, string $path): string => $root . '/' . ltrim($path, '/');

$clean = [
    1 => $page('next117 clean sqlite header before crash'),
    2 => $page('next117 clean wp_options root before crash'),
    3 => $page('next117 clean active_plugins before statement'),
    4 => $page('next117 clean transient before statement'),
];
$dirtyDatabase = $page('next117 dirty sqlite header after hot crash')
    . $page('next117 dirty wp_options root after hot crash')
    . $page('next117 dirty active_plugins failed statement')
    . $page('next117 dirty transient failed statement');
$statementBefore = $page('next117 statement before active_plugins');
$nextBefore = $page('next117 retry before plugin option');

$makeJournalBytes = static function (array $pages, int $nonce = 0x20261117) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 4, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 117) use ($pageSize): string {
    $salt1 = 0x20260528;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 117, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeStack = static function () use ($statementBefore): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-import-next117');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next117');
    $stack->beginStatementJournal('insert-active-plugin-next117');
    $stack->recordStatementPageImageWrite('insert-active-plugin-next117', 3, $statementBefore);
    $stack->recordStatementWalFrameWrite('insert-active-plugin-next117', 3, 3);
    $stack->recordStatementWalFrameWrite('insert-active-plugin-next117', 4, 4, true);

    return $stack;
};

$journalBytes = $makeJournalBytes($clean);
$walBytes = $makeWalBytes([
    [1, 0, 'next117 retained schema frame'],
    [2, 4, 'next117 retained wp_options root frame'],
    [3, 0, 'next117 failed active_plugins frame'],
    [4, 4, 'next117 failed transient commit'],
]);
$sourcePages = [
    1 => $page('next117 retained schema frame'),
    2 => $page('next117 retained wp_options root frame'),
    3 => $page('next117 failed active_plugins frame'),
    4 => $page('next117 failed transient commit'),
];

$setup = static function (bool $withJournal = true, bool $withWal = true) use ($databasePath, $dirtyDatabase, $journalBytes, $walBytes, $local): string {
    $root = sys_get_temp_dir() . '/port-libsqlite-next117-' . bin2hex(random_bytes(4));
    $directory = dirname($local($root, $databasePath));
    if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create next117 VFS test directory');
    }
    file_put_contents($local($root, $databasePath), $dirtyDatabase);
    if ($withJournal) {
        file_put_contents($local($root, $databasePath . '-journal'), $journalBytes);
    }
    if ($withWal) {
        file_put_contents($local($root, $databasePath . '-wal'), $walBytes);
    }

    return $root;
};

$apply = static function (string $root = null, bool $reserved = false, bool $requiresSuper = false, ?bool $superExists = null) use ($setup, $makeStack, $databasePath, $sourcePages, $nextBefore): array {
    $writer = new SQLiteVfsFileWriter($root ?? $setup());

    return $writer->applyWalHotJournalStatementRollback(
        $makeStack(),
        'plugin-batch-next117',
        'insert-active-plugin-next117',
        'retry-plugin-option-next117',
        5,
        $nextBefore,
        $databasePath,
        [1, 2, 3, 4],
        $sourcePages,
        true,
        $reserved,
        $requiresSuper,
        $superExists
    );
};

$cases = [
    'status applied' => [static fn (): mixed => $apply()['status'], 'applied'],
    'atomic true' => [static fn (): mixed => $apply()['atomic'], true],
    'applied operation count' => [static fn (): mixed => $apply()['applied'], 10],
    'bytes written includes hot checkpoint statement and wal' => [static fn (): mixed => $apply()['bytes_written'], (4 * $pageSize) + (4 * $pageSize) + (4 * $pageSize) + (32 + 2 * (24 + $pageSize))],
    'bytes truncated includes database and wal images' => [static fn (): mixed => $apply()['bytes_truncated'], (4 * $pageSize) + (4 * $pageSize) + (4 * $pageSize) + (32 + 2 * (24 + $pageSize))],
    'files deleted journal' => [static fn (): mixed => $apply()['files_deleted'], 1],
    'durable syncs' => [static fn (): mixed => $apply()['durable_syncs'], 1],
    'directory syncs none' => [static fn (): mixed => $apply()['directory_syncs'], 0],
    'recovery status' => [static fn (): mixed => $apply()['recovery']['status'], 'hot_journal_wal_statement_current_source_recovered_statement-rollback'],
    'recovery hot recovered' => [static fn (): mixed => $apply()['recovery']['hot_recovered'], true],
    'current source database path' => [static fn (): mixed => $apply()['current_source']['database_path'], $databasePath],
    'current source journal path' => [static fn (): mixed => $apply()['current_source']['journal_path'], $databasePath . '-journal'],
    'current source wal path' => [static fn (): mixed => $apply()['current_source']['wal_path'], $databasePath . '-wal'],
    'current source database bytes' => [static fn (): mixed => $apply()['current_source']['database_bytes'], 4 * $pageSize],
    'current source journal bytes' => [static fn (): mixed => $apply()['current_source']['journal_bytes'], strlen($journalBytes)],
    'current source wal bytes' => [static fn (): mixed => $apply()['current_source']['wal_bytes'], strlen($walBytes)],
    'operation first hot write' => [static fn (): mixed => $apply()['operations'][0]['reason'], 'restore_hot_journal_database_before_statement_wal_current_source'],
    'operation deletes hot journal' => [static fn (): mixed => $apply()['operations'][2]['reason'], 'delete_hot_journal_before_statement_wal_current_source'],
    'operation checkpoints wal' => [static fn (): mixed => $apply()['operations'][3]['reason'], 'checkpoint_current_wal_before_statement_rollback'],
    'operation restores statement' => [static fn (): mixed => $apply()['operations'][5]['reason'], 'restore_statement_subjournal_after_hot_journal_wal_current_source'],
    'operation trims statement database' => [static fn (): mixed => $apply()['operations'][6]['reason'], 'trim_statement_recovered_current_source_before_next_statement'],
    'operation restores wal prefix' => [static fn (): mixed => $apply()['operations'][7]['reason'], 'restore_statement_rollback_wal_prefix_before_next_statement'],
    'operation truncates wal prefix' => [static fn (): mixed => $apply()['operations'][8]['reason'], 'discard_statement_wal_frames_before_next_statement'],
    'operation final sync' => [static fn (): mixed => $apply()['operations'][9]['reason'], 'sync_statement_current_source_after_hot_journal_wal_replay'],
    'dependency next117 apply' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-statement-statement-rollback-vfs-apply', $apply()['dependencies'], true), true],
    'dependency atomic rollback' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $apply()['dependencies'], true), true],
    'dependency existing statement current source' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-statement-statement-rollback', $apply()['dependencies'], true), true],
    'rollback to retained wal frame two' => [static fn (): mixed => $apply()['recovery']['rollback_to_frame'], 2],
    'next wal frame index' => [static fn (): mixed => $apply()['recovery']['next_wal_frame_index'], 3],
    'next commit frame true' => [static fn (): mixed => $apply()['recovery']['next_commit_frame'], true],
    'statement wal bytes length' => [static fn (): mixed => $apply()['recovery']['statement_wal_bytes_length'], 32 + 2 * (24 + $pageSize)],
    'rollback restored page three' => [static fn (): mixed => $apply()['recovery']['rollback_restored_page_numbers'], [3]],
    'discarded wal frames' => [static fn (): mixed => array_column($apply()['recovery']['rollback_discarded_wal_frames'], 'frame_index'), [3, 4]],
    'pending frame after next' => [static fn (): mixed => $apply()['recovery']['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'pending page after next' => [static fn (): mixed => $apply()['recovery']['pending_page_numbers_after_next'], [1, 2, 5]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager wal statement hot rollback current source next117 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager wal statement hot rollback current source next117 applies database bytes'] = static function (TestRunner $t) use ($setup, $apply, $databasePath, $local): void {
    $root = $setup();
    $result = $apply($root);
    $databaseBytes = (string) file_get_contents($local($root, $databasePath));
    $t->same($result['recovery']['statement_database_bytes'], $databaseBytes);
    $t->true(str_contains($databaseBytes, 'next117 statement before active_plugins'));
    $t->true(str_contains($databaseBytes, 'next117 retained wp_options root frame'));
    $t->same(false, str_contains($databaseBytes, 'next117 dirty active_plugins failed statement'));
    $t->same(false, is_file($local($root, $databasePath . '-journal')));
};

$tests['pager wal statement hot rollback current source next117 applies wal prefix'] = static function (TestRunner $t) use ($setup, $apply, $databasePath, $local, $pageSize): void {
    $root = $setup();
    $result = $apply($root);
    $walBytes = (string) file_get_contents($local($root, $databasePath . '-wal'));
    $t->same($result['recovery']['statement_wal_bytes'], $walBytes);
    $t->same(32 + 2 * (24 + $pageSize), strlen($walBytes));
    $t->true(str_contains($walBytes, 'next117 retained wp_options root frame'));
    $t->same(false, str_contains($walBytes, 'next117 failed active_plugins frame'));
    $t->same(false, str_contains($walBytes, 'next117 failed transient commit'));
};

$tests['pager wal statement hot rollback current source next117 skips blocked hot journal'] = static function (TestRunner $t) use ($setup, $apply, $databasePath, $local, $dirtyDatabase, $journalBytes, $walBytes): void {
    $root = $setup();
    $result = $apply($root, false, true, false);
    $t->same('skipped', $result['status']);
    $t->same(0, $result['applied']);
    $t->same('hot_journal_wal_statement_current_source_skipped_statement-rollback', $result['recovery']['status']);
    $t->same($dirtyDatabase, (string) file_get_contents($local($root, $databasePath)));
    $t->same($journalBytes, (string) file_get_contents($local($root, $databasePath . '-journal')));
    $t->same($walBytes, (string) file_get_contents($local($root, $databasePath . '-wal')));
};

$throws = [
    'empty root rejected' => static fn () => new SQLiteVfsFileWriter(''),
    'empty path rejected' => static fn () => (new SQLiteVfsFileWriter($setup()))->applyWalHotJournalStatementRollback($makeStack(), 'sp', 'stmt', 'next', 5, $nextBefore, '', [1], $sourcePages),
    'missing database rejected' => static fn () => $apply(sys_get_temp_dir() . '/port-libsqlite-next117-missing-' . bin2hex(random_bytes(4))),
    'missing journal rejected' => static fn () => $apply($setup(false, true)),
    'missing wal rejected' => static fn () => $apply($setup(true, false)),
    'read only rejected' => static fn () => (new SQLiteVfsFileWriter($setup(), true))->applyWalHotJournalStatementRollback($makeStack(), 'plugin-batch-next117', 'insert-active-plugin-next117', 'retry-plugin-option-next117', 5, $nextBefore, $databasePath, [1, 2, 3, 4], $sourcePages, true),
    'bad current source rejected' => static fn () => (new SQLiteVfsFileWriter($setup()))->applyWalHotJournalStatementRollback($makeStack(), 'plugin-batch-next117', 'insert-active-plugin-next117', 'retry-plugin-option-next117', 5, $nextBefore, $databasePath, [1, 2, 3, 4], [3 => $page('stale current source')], true),
    'empty current statement rejected' => static fn () => (new SQLiteVfsFileWriter($setup()))->applyWalHotJournalStatementRollback($makeStack(), 'plugin-batch-next117', '', 'retry-plugin-option-next117', 5, $nextBefore, $databasePath, [1, 2, 3, 4], $sourcePages, true),
    'empty next statement rejected' => static fn () => (new SQLiteVfsFileWriter($setup()))->applyWalHotJournalStatementRollback($makeStack(), 'plugin-batch-next117', 'insert-active-plugin-next117', '', 5, $nextBefore, $databasePath, [1, 2, 3, 4], $sourcePages, true),
    'zero next page rejected' => static fn () => (new SQLiteVfsFileWriter($setup()))->applyWalHotJournalStatementRollback($makeStack(), 'plugin-batch-next117', 'insert-active-plugin-next117', 'retry-plugin-option-next117', 0, $nextBefore, $databasePath, [1, 2, 3, 4], $sourcePages, true),
];

foreach ($throws as $name => $callback) {
    $tests['pager wal statement hot rollback current source next117 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
