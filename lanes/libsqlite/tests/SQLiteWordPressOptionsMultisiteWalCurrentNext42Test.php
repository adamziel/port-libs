<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteMultisiteOptionsWalPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x42004200;
$salt2 = 0x20260527;
$databasePath = 'wp-content/database/multisite.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header multisite before import')
    . $page('wp_options current siteurl before import')
    . $page('wp_2_options current siteurl before import')
    . $page('wp_3_options current home before import')
    . $page('wp_sitemeta current site_name before import');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 42, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $prefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $prefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$baseWalBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('draft network siteurl before import'));
    $bytes = $appendFrame($bytes, $seed, 3, 5, $page('committed blog 2 siteurl before import'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), $pageSize, true);
$currentRows = static fn (): array => [
    ['scope' => 'network', 'option_id' => 1, 'option_name' => 'site_name', 'option_value' => 'Old Network', 'autoload' => 'yes'],
    ['scope' => 'network', 'option_id' => 2, 'option_name' => 'active_sitewide_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example/site-two', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_id' => 2, 'option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'no'],
    ['scope' => 'blog', 'blog_id' => 3, 'option_id' => 1, 'option_name' => 'home', 'option_value' => 'https://old.example/site-three', 'autoload' => 'yes'],
];
$importRows = static fn (): array => [
    ['scope' => 'network', 'option_name' => 'active_sitewide_plugins', 'option_value' => 'a:1:{s:19:"akismet/akismet.php";b:1;}', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://new.example/site-two', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'option_value' => 'a:1:{s:4:"post";s:12:"index.php?p=";}', 'autoload' => 'no'],
    ['scope' => 'blog', 'blog_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://new.example/site-three', 'autoload' => 'yes'],
    ['scope' => 'network', 'option_name' => 'registration', 'option_value' => 'none', 'autoload' => 'no'],
];
$plan = static fn (): array => SQLiteMultisiteOptionsWalPlan::currentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $currentRows(),
    $importRows(),
    range(2, 13),
);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse($plan()['append']['wal_bytes'], $pageSize, true);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'application_multisite_options_wal_commit_current_next_visibility'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'current row count' => [static fn (): mixed => count($plan()['current_rows']), 5],
    'next row count' => [static fn (): mixed => count($plan()['next_rows']), 8],
    'tables ordered' => [static fn (): mixed => $plan()['tables'], ['wp_2_options', 'wp_3_options', 'wp_sitemeta']],
    'inserted keys ordered' => [static fn (): mixed => $plan()['inserted_keys'], ['wp_2_options:rewrite_rules', 'wp_3_options:siteurl', 'wp_sitemeta:registration']],
    'updated keys ordered' => [static fn (): mixed => $plan()['updated_keys'], ['wp_2_options:siteurl', 'wp_sitemeta:active_sitewide_plugins']],
    'blog two siteurl keeps option id' => [static fn (): mixed => $plan()['next_rows'][2]['option_id'], 1],
    'blog two rewrite gets next table id' => [static fn (): mixed => $plan()['next_rows'][1]['option_id'], 3],
    'blog three siteurl gets next table id' => [static fn (): mixed => $plan()['next_rows'][4]['option_id'], 2],
    'network registration gets next table id' => [static fn (): mixed => $plan()['next_rows'][6]['option_id'], 3],
    'blog two page list' => [static fn (): mixed => $plan()['table_page_numbers']['wp_2_options'], [2, 3, 4]],
    'blog three page list' => [static fn (): mixed => $plan()['table_page_numbers']['wp_3_options'], [5, 6]],
    'network page list' => [static fn (): mixed => $plan()['table_page_numbers']['wp_sitemeta'], [7, 8, 9]],
    'blog two autoload index page' => [static fn (): mixed => $plan()['autoload_index_page_numbers']['wp_2_options'], 10],
    'blog three autoload index page' => [static fn (): mixed => $plan()['autoload_index_page_numbers']['wp_3_options'], 11],
    'network autoload index page' => [static fn (): mixed => $plan()['autoload_index_page_numbers']['wp_sitemeta'], 12],
    'database page count' => [static fn (): mixed => $plan()['database_page_count'], 12],
    'blog two autoload names' => [static fn (): mixed => $plan()['autoload_yes_by_table']['wp_2_options'], ['siteurl']],
    'blog three autoload names' => [static fn (): mixed => $plan()['autoload_yes_by_table']['wp_3_options'], ['home', 'siteurl']],
    'network autoload names' => [static fn (): mixed => $plan()['autoload_yes_by_table']['wp_sitemeta'], ['active_sitewide_plugins', 'site_name']],
    'append start frame' => [static fn (): mixed => $plan()['append']['start_frame'], 3],
    'append end frame' => [static fn (): mixed => $plan()['append']['end_frame'], 13],
    'append frame count' => [static fn (): mixed => $plan()['append']['appended_frame_count'], 11],
    'append commit count' => [static fn (): mixed => $plan()['append']['committed_transaction_count'], 1],
    'append uncommitted count' => [static fn (): mixed => $plan()['append']['uncommitted_transaction_count'], 0],
    'append last commit frame' => [static fn (): mixed => $plan()['append']['last_commit_frame'], 13],
    'append last page count' => [static fn (): mixed => $plan()['append']['last_database_page_count'], 12],
    'append bytes length' => [static fn (): mixed => $plan()['append']['append_bytes_length'], 11 * (24 + $pageSize)],
    'append write offset' => [static fn (): mixed => $plan()['append']['operations'][0]['offset'], strlen($baseWalBytes())],
    'append sync op' => [static fn (): mixed => $plan()['append']['operations'][1]['op'], 'sync'],
    'append directory sync op' => [static fn (): mixed => $plan()['append']['operations'][2]['op'], 'sync_directory'],
    'current source page two' => [static fn (): mixed => $plan()['current_reader_sources'][0], 'wal'],
    'current source page three' => [static fn (): mixed => $plan()['current_reader_sources'][1], 'wal'],
    'current source page four' => [static fn (): mixed => $plan()['current_reader_sources'][2], 'database'],
    'current source page six future' => [static fn (): mixed => $plan()['current_reader_sources'][4], 'error'],
    'current error count' => [static fn (): mixed => count($plan()['current_reader_errors']), 8],
    'next sources all wal' => [static fn (): mixed => $plan()['next_reader_sources'], array_fill(0, 11, 'wal') + [11 => 'error']],
    'next error count' => [static fn (): mixed => count($plan()['next_reader_errors']), 1],
    'current frame indexes' => [static fn (): mixed => array_slice($plan()['current_reader_frame_indexes'], 0, 4), [1, 2, null, null]],
    'next frame indexes first four' => [static fn (): mixed => array_slice($plan()['next_reader_frame_indexes'], 0, 4), [3, 4, 5, 6]],
    'next frame indexes last committed page' => [static fn (): mixed => $plan()['next_reader_frame_indexes'][10], 13],
    'blog two rewrite page contains table' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], '"table":"wp_2_options"'), true],
    'blog two rewrite page inserted name' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], '"rewrite_rules"'), true],
    'blog two siteurl updated' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], 'https://new.example/site-two'), true],
    'blog two blog public retained' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], '"blog_public"'), true],
    'blog three home retained' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'site-three'), true],
    'blog three siteurl inserted' => [static fn (): mixed => str_contains($plan()['next_reader'][4]['image'], '"siteurl"'), true],
    'network active plugins updated' => [static fn (): mixed => str_contains($plan()['next_reader'][5]['image'], 'active_sitewide_plugins'), true],
    'network registration inserted' => [static fn (): mixed => str_contains($plan()['next_reader'][6]['image'], '"registration"'), true],
    'network site name retained' => [static fn (): mixed => str_contains($plan()['next_reader'][7]['image'], 'Old Network'), true],
    'blog two autoload index excludes rewrite' => [static fn (): mixed => !str_contains($plan()['next_reader'][8]['image'], 'rewrite_rules'), true],
    'blog three autoload index includes siteurl' => [static fn (): mixed => str_contains($plan()['next_reader'][9]['image'], '"siteurl"'), true],
    'network autoload index excludes registration' => [static fn (): mixed => !str_contains($plan()['next_reader'][10]['image'], 'registration'), true],
    'dependency marker' => [static fn (): mixed => in_array('application-multisite-options-wal-current-next42', $plan()['dependencies'], true), true],
    'dependency includes wal append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'next wal frame count' => [static fn (): mixed => $nextWal()->frameCount(), 13],
    'next wal uncommitted count' => [static fn (): mixed => $nextWal()->uncommittedFrameCount(), 0],
    'next wal last commit frame' => [static fn (): mixed => $nextWal()->lastCommitFrame()?->index, 13],
    'next wal snapshot page count' => [static fn (): mixed => $nextWal()->readerSnapshot($databaseBytes, 13)['database_page_count'], 12],
    'next wal page map count' => [static fn (): mixed => count($nextWal()->readerSnapshotPageMap($databaseBytes, 13)), 12],
    'next wal page eleven image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 11, 13)['image'], 'wp_3_options_autoload'), true],
    'next wal page twelve image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 12, 13)['image'], 'wp_sitemeta_autoload'), true],
    'current snapshot page six rejected' => [static function () use ($baseWal, $databaseBytes): mixed {
        try {
            $baseWal()->readerSnapshotPageImage($databaseBytes, 6, 2);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }

        return 'accepted';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['application options multisite wal current next42 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['application options multisite wal current next42 applies append through vfs writer'] = static function (TestRunner $t) use ($baseWalBytes, $baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-wp-multisite-wal42-' . bin2hex(random_bytes(4));
    $localWal = $root . '/' . $databasePath . '-wal';
    $directory = dirname($localWal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create multisite WAL import test directory');
    }
    file_put_contents($localWal, $baseWalBytes());

    $plan = SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), range(2, 12));
    $plannedWal = SQLiteWal::parse($plan['append']['wal_bytes'], null, true);
    $applied = (new SQLiteVfsFileWriter($root))->applyWalAppendTransactions($baseWal(), $databasePath, [[
        'pages' => array_combine(
            array_column($plan['append']['frames'], 'page_number'),
            array_map(static fn (array $frame): string => $plannedWal->frames[$frame['frame_index'] - 1]->pageImage, $plan['append']['frames'])
        ),
        'database_page_count' => $plan['database_page_count'],
        'commit' => true,
    ]]);
    $afterWal = SQLiteWal::parse((string) file_get_contents($localWal), null, true);

    $t->same('applied', $applied['status']);
    $t->same(11, $applied['append']['appended_frame_count']);
    $t->same(13, $afterWal->lastCommitFrame()?->index);
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 4, 13)['image'], 'https://new.example/site-two'));
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 12, 13)['image'], 'wp_sitemeta_autoload'));
};

$tests['application options multisite wal current next42 rejects bad inputs'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, '', $currentRows(), $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, [['scope' => 'blog', 'blog_id' => 0, 'option_id' => 1, 'option_name' => 'bad', 'option_value' => 'x']], $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, [['scope' => 'network', 'option_id' => 0, 'option_name' => 'bad', 'option_value' => 'x']], $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [['scope' => 'user', 'option_name' => 'bad', 'option_value' => 'x']], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [['scope' => 'blog', 'blog_id' => 2, 'option_name' => '', 'option_value' => 'x']], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteMultisiteOptionsWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), ['2']));
};

return $tests;
