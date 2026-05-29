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
    $salt1 = 0x94949494;
    $salt2 = 0x14141414;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 94, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, $page('wal retained autoload draft rollback'));
    $append(2, 3, $page('wal retained autoload commit rollback'));
    $append(3, 0, $page('wal discard option draft rollback'));
    $append(3, 3, $page('wal discard option commit rollback'));

    return $bytes;
};

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before schema page rollback'));
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 2, true);

    $stack->savepoint('plugin-settings');
    $stack->beginStatementJournal('update-plugin-option');
    $stack->recordStatementPageImageWrite('update-plugin-option', 2, $page('before plugin autoload rollback'));
    $stack->recordPageImageWrite(2, $page('before plugin autoload rollback'));
    $stack->recordStatementWalFrameWrite('update-plugin-option', 3, 2);

    $stack->savepoint('plugin-child');
    $stack->beginStatementJournal('insert-plugin-child');
    $stack->recordStatementPageImageWrite('insert-plugin-child', 3, $page('before plugin child rollback'));
    $stack->recordPageImageWrite(3, $page('before plugin child rollback'));
    $stack->recordStatementWalFrameWrite('insert-plugin-child', 4, 3, true);

    return $stack;
};

$withTemp = static function (callable $callback) use ($pageSize, $page, $makeWalBytes): mixed {
    $root = sys_get_temp_dir() . '/libsqlite-savepoint-journal-filehandle-current-source-rollback-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temp root');
    }
    $dir = $root . '/wp-content/database';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/wp.sqlite', $page('schema dirty rollback') . $page('plugin dirty autoload rollback') . $page('plugin child dirty rollback'));
    file_put_contents($dir . '/wp.sqlite-wal', $makeWalBytes());
    file_put_contents($dir . '/outer.stmt', 'outer statement journal remains rollback');
    file_put_contents($dir . '/plugin.stmt', 'plugin statement journal is stale rollback');
    file_put_contents($dir . '/child.stmt', 'child statement journal is stale rollback');

    try {
        return $callback($root, 'wp-content/database/wp.sqlite', [
            'outer-import' => 'wp-content/database/outer.stmt',
            'update-plugin-option' => 'wp-content/database/plugin.stmt',
            'insert-plugin-child' => 'wp-content/database/child.stmt',
        ], $pageSize);
    } finally {
        foreach (['wp.sqlite-wal', 'wp.sqlite', 'outer.stmt', 'plugin.stmt', 'child.stmt'] as $file) {
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

$run = static fn (): array => $withTemp(static function (string $root, string $databasePath, array $journals, int $pageSize) use ($makeStack): array {
    $writer = new SQLiteVfsFileWriter($root);
    return $writer->applySavepointRollbackFromCurrentSource(
        $makeStack(),
        'plugin-settings',
        $databasePath,
        $pageSize,
        $journals
    );
});

$snapshot = static fn (): array => $withTemp(static function (string $root, string $databasePath, array $journals, int $pageSize) use ($makeStack): array {
    $writer = new SQLiteVfsFileWriter($root);
    $result = $writer->applySavepointRollbackFromCurrentSource(
        $makeStack(),
        'plugin-settings',
        $databasePath,
        $pageSize,
        $journals
    );
    $base = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return [
        'result' => $result,
        'database' => (string) file_get_contents($base . $databasePath),
        'wal' => (string) file_get_contents($base . $databasePath . '-wal'),
        'outer_exists' => is_file($base . $journals['outer-import']),
        'plugin_exists' => is_file($base . $journals['update-plugin-option']),
        'child_exists' => is_file($base . $journals['insert-plugin-child']),
    ];
});

$cases = [
    'status applied' => [static fn (): mixed => $run()['status'], 'applied'],
    'atomic true' => [static fn (): mixed => $run()['atomic'], true],
    'savepoint retained' => [static fn (): mixed => $run()['savepoint'], 'plugin-settings'],
    'operation count includes db wal journals' => [static fn (): mixed => $run()['applied'], 9],
    'bytes written includes db and wal prefix' => [static fn (): mixed => $run()['bytes_written'], (3 * $pageSize) + (32 + (2 * (24 + $pageSize)))],
    'truncated bytes includes db and wal prefix' => [static fn (): mixed => $run()['bytes_truncated'], (3 * $pageSize) + (32 + (2 * (24 + $pageSize)))],
    'deleted two discarded statement journals' => [static fn (): mixed => $run()['files_deleted'], 2],
    'durable syncs database and wal' => [static fn (): mixed => $run()['durable_syncs'], 2],
    'directory sync once' => [static fn (): mixed => $run()['directory_syncs'], 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-savepoint-journal-filehandle-current-source', $run()['dependencies'], true), true],
    'vfs atomic dependency marker' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $run()['dependencies'], true), true],
    'current source database path' => [static fn (): mixed => $run()['current_source']['database_path'], 'wp-content/database/wp.sqlite'],
    'current source database bytes before' => [static fn (): mixed => $run()['current_source']['database_bytes_before'], 3 * $pageSize],
    'current source database bytes after' => [static fn (): mixed => $run()['current_source']['database_bytes_after'], 3 * $pageSize],
    'current source page count before' => [static fn (): mixed => $run()['current_source']['database_page_count_before'], 3],
    'current source wal path default' => [static fn (): mixed => $run()['current_source']['wal_path'], 'wp-content/database/wp.sqlite-wal'],
    'current source wal exists' => [static fn (): mixed => $run()['current_source']['wal_exists'], true],
    'current source wal bytes before' => [static fn (): mixed => $run()['current_source']['wal_bytes_before'], 32 + (4 * (24 + $pageSize))],
    'current source wal bytes after' => [static fn (): mixed => $run()['current_source']['wal_bytes_after'], 32 + (2 * (24 + $pageSize))],
    'rollback transition retained depth' => [static fn (): mixed => $run()['current_source']['rollback_transition']['retained_depth'], 2],
    'rollback transition discarded child' => [static fn (): mixed => $run()['current_source']['rollback_transition']['discarded_frame_names'], ['plugin-child']],
    'image restored pages' => [static fn (): mixed => $run()['database_image']['restored_page_numbers'], [2, 3]],
    'image source frames' => [static fn (): mixed => array_column($run()['database_image']['restore_pages'], 'source_frame'), ['plugin-settings', 'plugin-child']],
    'wal rollback retained frame count' => [static fn (): mixed => $run()['wal_truncation']['retained_frame_count'], 2],
    'wal rollback discarded frame count' => [static fn (): mixed => $run()['wal_truncation']['discarded_frame_count'], 2],
    'wal rollback truncate bytes' => [static fn (): mixed => $run()['wal_truncation']['truncate_to_bytes'], 32 + (2 * (24 + $pageSize))],
    'wal discarded frame indexes' => [static fn (): mixed => array_column($run()['wal_truncation']['discarded_wal_frames'], 'frame_index'), [3, 4]],
    'statement before list' => [static fn (): mixed => $run()['statement_journals']['before'], ['update-plugin-option', 'insert-plugin-child']],
    'statement after list empty' => [static fn (): mixed => $run()['statement_journals']['after'], []],
    'statement discarded sorted' => [static fn (): mixed => $run()['statement_journals']['discarded'], ['insert-plugin-child', 'update-plugin-option']],
    'statement discarded paths' => [static fn (): mixed => $run()['statement_journals']['discarded_paths'], [
        'update-plugin-option' => 'wp-content/database/plugin.stmt',
        'insert-plugin-child' => 'wp-content/database/child.stmt',
    ]],
    'statement preserved paths' => [static fn (): mixed => $run()['statement_journals']['preserved_paths'], [
        'outer-import' => 'wp-content/database/outer.stmt',
    ]],
    'operation zero write database' => [static fn (): mixed => $run()['operations'][0]['op'], 'write'],
    'operation zero reason' => [static fn (): mixed => $run()['operations'][0]['reason'], 'restore_current_source_savepoint_database_page_images'],
    'operation three write wal' => [static fn (): mixed => $run()['operations'][3]['path'], 'wp-content/database/wp.sqlite-wal'],
    'operation four truncates wal' => [static fn (): mixed => $run()['operations'][4]['reason'], 'truncate_current_source_savepoint_wal_frames'],
    'operation six deletes plugin journal' => [static fn (): mixed => $run()['operations'][6]['path'], 'wp-content/database/plugin.stmt'],
    'operation seven deletes child journal' => [static fn (): mixed => $run()['operations'][7]['path'], 'wp-content/database/child.stmt'],
    'operation eight syncs directory' => [static fn (): mixed => $run()['operations'][8]['op'], 'sync_directory'],
    'snapshot database restores plugin page' => [static fn (): mixed => str_contains($snapshot()['database'], 'before plugin autoload rollback'), true],
    'snapshot database restores child page' => [static fn (): mixed => str_contains($snapshot()['database'], 'before plugin child rollback'), true],
    'snapshot database keeps schema current' => [static fn (): mixed => str_contains($snapshot()['database'], 'schema dirty rollback'), true],
    'snapshot database removes dirty autoload' => [static fn (): mixed => str_contains($snapshot()['database'], 'plugin dirty autoload rollback'), false],
    'snapshot database removes dirty child' => [static fn (): mixed => str_contains($snapshot()['database'], 'plugin child dirty rollback'), false],
    'snapshot wal has two frames' => [static fn (): mixed => SQLiteWal::parse($snapshot()['wal'], null, true)->frameCount(), 2],
    'snapshot wal last commit retained' => [static fn (): mixed => SQLiteWal::parse($snapshot()['wal'], null, true)->lastCommitFrame()?->index, 2],
    'snapshot wal omits discarded option' => [static fn (): mixed => str_contains($snapshot()['wal'], 'wal discard option commit rollback'), false],
    'snapshot outer journal preserved' => [static fn (): mixed => $snapshot()['outer_exists'], true],
    'snapshot plugin journal deleted' => [static fn (): mixed => $snapshot()['plugin_exists'], false],
    'snapshot child journal deleted' => [static fn (): mixed => $snapshot()['child_exists'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint journal filehandle current source rollback ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager savepoint journal filehandle current source rollback rejects missing database'] = static function (TestRunner $t) use ($makeStack, $pageSize): void {
    $root = sys_get_temp_dir() . '/libsqlite-savepoint-missing-current-source-rollback-' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    try {
        $writer = new SQLiteVfsFileWriter($root);
        $t->throws(RuntimeException::class, static fn (): mixed => $writer->applySavepointRollbackFromCurrentSource($makeStack(), 'plugin-settings', 'missing.sqlite', $pageSize));
    } finally {
        rmdir($root);
    }
};

$tests['pager savepoint journal filehandle current source rollback rejects missing savepoint'] = static function (TestRunner $t) use ($withTemp, $makeStack): void {
    $withTemp(static function (string $root, string $databasePath, array $journals, int $pageSize) use ($makeStack, $t): void {
        $writer = new SQLiteVfsFileWriter($root);
        $t->throws(InvalidArgumentException::class, static fn (): mixed => $writer->applySavepointRollbackFromCurrentSource($makeStack(), 'missing', $databasePath, $pageSize, $journals));
    });
};

return $tests;
