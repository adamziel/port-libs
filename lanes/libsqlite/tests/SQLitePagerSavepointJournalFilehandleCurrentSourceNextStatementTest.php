<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");

$makeWalBytes = static function () use ($pageSize, $page): string {
    $salt1 = 0x99999999;
    $salt2 = 0x19191919;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 99, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, $page('wal retained option draft next statement'));
    $append(2, 3, $page('wal retained option commit next statement'));
    $append(3, 0, $page('wal discarded retry draft next statement'));
    $append(3, 3, $page('wal discarded retry commit next statement'));

    return $bytes;
};

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before schema page next statement'));
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 2, true);

    $stack->savepoint('plugin-settings');
    $stack->beginStatementJournal('update-plugin-option');
    $stack->recordStatementPageImageWrite('update-plugin-option', 2, $page('before plugin autoload next statement'));
    $stack->recordPageImageWrite(2, $page('before plugin autoload next statement'));
    $stack->recordStatementWalFrameWrite('update-plugin-option', 3, 2);

    $stack->savepoint('plugin-child');
    $stack->beginStatementJournal('insert-plugin-child');
    $stack->recordStatementPageImageWrite('insert-plugin-child', 3, $page('before plugin child next statement'));
    $stack->recordPageImageWrite(3, $page('before plugin child next statement'));
    $stack->recordStatementWalFrameWrite('insert-plugin-child', 4, 3, true);

    return $stack;
};

$withTemp = static function (callable $callback) use ($pageSize, $page, $makeWalBytes): mixed {
    $root = sys_get_temp_dir() . '/libsqlite-savepoint-journal-filehandle-current-source-next-statement-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temp root');
    }
    $dir = $root . '/wp-content/database';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/wp.sqlite', $page('schema dirty next statement') . $page('plugin dirty autoload next statement') . $page('plugin child dirty next statement'));
    file_put_contents($dir . '/wp.sqlite-wal', $makeWalBytes());
    file_put_contents($dir . '/outer.stmt', 'outer statement journal remains next statement');
    file_put_contents($dir . '/plugin.stmt', 'plugin statement journal is stale next statement');
    file_put_contents($dir . '/child.stmt', 'child statement journal is stale next statement');

    try {
        return $callback($root, 'wp-content/database/wp.sqlite', [
            'outer-import' => 'wp-content/database/outer.stmt',
            'update-plugin-option' => 'wp-content/database/plugin.stmt',
            'insert-plugin-child' => 'wp-content/database/child.stmt',
        ], 'wp-content/database/retry.stmt', $pageSize);
    } finally {
        foreach (['wp.sqlite-wal', 'wp.sqlite', 'outer.stmt', 'plugin.stmt', 'child.stmt', 'retry.stmt'] as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
        if (is_dir($root . '/wp-content')) {
            rmdir($root . '/wp-content');
        }
        if (is_dir($root)) {
            rmdir($root);
        }
    }
};

$run = static fn (): array => $withTemp(static function (string $root, string $databasePath, array $journals, string $nextJournal, int $pageSize) use ($makeStack): array {
    return (new SQLiteVfsFileWriter($root))->applySavepointRollbackAndBeginNextStatementFromCurrentSource(
        $makeStack(),
        'plugin-settings',
        $databasePath,
        $pageSize,
        'retry-plugin-option',
        $nextJournal,
        2,
        true,
        $journals
    );
});

$snapshot = static fn (): array => $withTemp(static function (string $root, string $databasePath, array $journals, string $nextJournal, int $pageSize) use ($makeStack): array {
    $result = (new SQLiteVfsFileWriter($root))->applySavepointRollbackAndBeginNextStatementFromCurrentSource(
        $makeStack(),
        'plugin-settings',
        $databasePath,
        $pageSize,
        'retry-plugin-option',
        $nextJournal,
        2,
        true,
        $journals
    );
    $base = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return [
        'result' => $result,
        'database' => (string) file_get_contents($base . $databasePath),
        'wal' => (string) file_get_contents($base . $databasePath . '-wal'),
        'retry_journal' => (string) file_get_contents($base . $nextJournal),
        'outer_exists' => is_file($base . $journals['outer-import']),
        'plugin_exists' => is_file($base . $journals['update-plugin-option']),
        'child_exists' => is_file($base . $journals['insert-plugin-child']),
    ];
});

$cases = [
    'status applied' => [static fn (): mixed => $run()['status'], 'applied'],
    'atomic true' => [static fn (): mixed => $run()['atomic'], true],
    'savepoint retained' => [static fn (): mixed => $run()['savepoint'], 'plugin-settings'],
    'operation count includes next statement journal' => [static fn (): mixed => $run()['applied'], 12],
    'bytes written includes db wal and next journal' => [static fn (): mixed => $run()['bytes_written'], (4 * $pageSize) + (32 + (2 * (24 + $pageSize)))],
    'truncated bytes includes db and wal prefix' => [static fn (): mixed => $run()['bytes_truncated'], (3 * $pageSize) + (32 + (2 * (24 + $pageSize)))],
    'deleted stale statement journals' => [static fn (): mixed => $run()['files_deleted'], 2],
    'durable syncs include next journal' => [static fn (): mixed => $run()['durable_syncs'], 3],
    'directory syncs include rollback and journal dirs' => [static fn (): mixed => $run()['directory_syncs'], 2],
    'next statement dependency marker' => [static fn (): mixed => in_array('sqlite-savepoint-journal-filehandle-current-source-next-statement', $run()['dependencies'], true), true],
    'next66 dependency marker retained' => [static fn (): mixed => in_array('sqlite-statement-journal-current-next66', $run()['dependencies'], true), true],
    'current source wal bytes after rollback' => [static fn (): mixed => $run()['current_source']['wal_bytes_after'], 32 + (2 * (24 + $pageSize))],
    'current source rollback transition depth' => [static fn (): mixed => $run()['current_source']['rollback_transition']['retained_depth'], 2],
    'database restored pages' => [static fn (): mixed => $run()['database_image']['restored_page_numbers'], [2, 3]],
    'wal discarded frames' => [static fn (): mixed => array_column($run()['wal_truncation']['discarded_wal_frames'], 'frame_index'), [3, 4]],
    'old statement journals discarded' => [static fn (): mixed => $run()['statement_journals']['discarded'], ['insert-plugin-child', 'update-plugin-option']],
    'outer statement journal preserved' => [static fn (): mixed => $run()['statement_journals']['preserved_paths'], ['outer-import' => 'wp-content/database/outer.stmt']],
    'next statement name' => [static fn (): mixed => $run()['next_statement']['name'], 'retry-plugin-option'],
    'next statement journal path' => [static fn (): mixed => $run()['next_statement']['journal_path'], 'wp-content/database/retry.stmt'],
    'next statement page number' => [static fn (): mixed => $run()['next_statement']['page_number'], 2],
    'next statement page offset' => [static fn (): mixed => $run()['next_statement']['page_offset'], $pageSize],
    'next statement bytes' => [static fn (): mixed => $run()['next_statement']['bytes'], $pageSize],
    'next statement source prefix' => [static fn (): mixed => $run()['next_statement']['source_prefix'], 'before plugin autoload next statement'],
    'next statement plan rollback frame' => [static fn (): mixed => $run()['next_statement']['plan']['rollback_to_frame'], 2],
    'next statement plan next frame' => [static fn (): mixed => $run()['next_statement']['plan']['next_wal_frame_index'], 3],
    'next statement plan next commit frame' => [static fn (): mixed => $run()['next_statement']['plan']['next_commit_frame'], true],
    'next statement plan discarded journals sorted' => [static fn (): mixed => $run()['next_statement']['plan']['discarded_statement_journals'], ['insert-plugin-child', 'update-plugin-option']],
    'next statement plan after rollback empty' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_rollback'], []],
    'next statement plan after next name' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_next'][0]['name'], 'retry-plugin-option'],
    'next statement plan after next savepoint' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_next'][0]['savepoint'], 'plugin-settings'],
    'next statement plan after next wal start' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_next'][0]['wal_start_frame'], 2],
    'next statement plan after next page' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_next'][0]['page_numbers'], [2]],
    'next statement plan after next wal frame' => [static fn (): mixed => $run()['next_statement']['plan']['statement_journals_after_next'][0]['wal_frame_indexes'], [3]],
    'next statement plan pending pages' => [static fn (): mixed => $run()['next_statement']['plan']['pending_page_numbers_after_next'], [1, 2]],
    'next statement plan pending wal frames' => [static fn (): mixed => $run()['next_statement']['plan']['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'next statement journal write status' => [static fn (): mixed => $run()['next_statement']['journal_apply']['status'], 'applied'],
    'next statement journal write operations' => [static fn (): mixed => $run()['next_statement']['journal_apply']['applied'], 3],
    'operation nine writes retry journal' => [static fn (): mixed => $run()['operations'][9]['path'], 'wp-content/database/retry.stmt'],
    'operation nine reason' => [static fn (): mixed => $run()['operations'][9]['reason'], 'write_next_statement_journal_after_current_source_savepoint_rollback'],
    'operation ten syncs retry journal' => [static fn (): mixed => $run()['operations'][10]['op'], 'sync'],
    'operation eleven syncs directory' => [static fn (): mixed => $run()['operations'][11]['op'], 'sync_directory'],
    'snapshot database restores page two' => [static fn (): mixed => str_contains($snapshot()['database'], 'before plugin autoload next statement'), true],
    'snapshot database restores child page' => [static fn (): mixed => str_contains($snapshot()['database'], 'before plugin child next statement'), true],
    'snapshot database removes dirty autoload' => [static fn (): mixed => str_contains($snapshot()['database'], 'plugin dirty autoload next statement'), false],
    'snapshot wal retains two frames' => [static fn (): mixed => SQLiteWal::parse($snapshot()['wal'], null, true)->frameCount(), 2],
    'snapshot wal drops discarded retry frame' => [static fn (): mixed => str_contains($snapshot()['wal'], 'wal discarded retry commit next statement'), false],
    'snapshot retry journal uses restored page image' => [static fn (): mixed => str_contains($snapshot()['retry_journal'], 'before plugin autoload next statement'), true],
    'snapshot retry journal not dirty page image' => [static fn (): mixed => str_contains($snapshot()['retry_journal'], 'plugin dirty autoload next statement'), false],
    'snapshot outer journal preserved' => [static fn (): mixed => $snapshot()['outer_exists'], true],
    'snapshot plugin journal deleted' => [static fn (): mixed => $snapshot()['plugin_exists'], false],
    'snapshot child journal deleted' => [static fn (): mixed => $snapshot()['child_exists'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint journal filehandle current source next statement ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager savepoint journal filehandle current source next statement rejects missing next statement name'] = static function (TestRunner $t) use ($withTemp, $makeStack): void {
    $withTemp(static function (string $root, string $databasePath, array $journals, string $nextJournal, int $pageSize) use ($makeStack, $t): void {
        $writer = new SQLiteVfsFileWriter($root);
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $writer->applySavepointRollbackAndBeginNextStatementFromCurrentSource($makeStack(), 'plugin-settings', $databasePath, $pageSize, '', $nextJournal, 2, false, $journals));
    });
};

$tests['pager savepoint journal filehandle current source next statement rejects missing next journal path'] = static function (TestRunner $t) use ($withTemp, $makeStack): void {
    $withTemp(static function (string $root, string $databasePath, array $journals, string $nextJournal, int $pageSize) use ($makeStack, $t): void {
        $writer = new SQLiteVfsFileWriter($root);
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $writer->applySavepointRollbackAndBeginNextStatementFromCurrentSource($makeStack(), 'plugin-settings', $databasePath, $pageSize, 'retry', '', 2, false, $journals));
    });
};

$tests['pager savepoint journal filehandle current source next statement rejects page outside current source'] = static function (TestRunner $t) use ($withTemp, $makeStack): void {
    $withTemp(static function (string $root, string $databasePath, array $journals, string $nextJournal, int $pageSize) use ($makeStack, $t): void {
        $writer = new SQLiteVfsFileWriter($root);
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $writer->applySavepointRollbackAndBeginNextStatementFromCurrentSource($makeStack(), 'plugin-settings', $databasePath, $pageSize, 'retry', $nextJournal, 9, false, $journals));
    });
};

return $tests;
