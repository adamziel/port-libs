<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteKeyValueRowsWalImportPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x34343434;
$salt2 = 0x56565656;
$databasePath = 'app-data/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and app_settings root before import')
    . $page('current siteurl option before import')
    . $page('current active_plugins option before import')
    . $page('current load_policy index before import');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 34, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$baseWalBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal draft siteurl before import'));
    $bytes = $appendFrame($bytes, $seed, 3, 4, $page('wal committed active_plugins before import'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'active_plugins', 'key_value' => 'a:0:{}', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'tenant_public', 'key_value' => '1', 'load_policy' => 'no'],
];
$importRows = static fn (): array => [
    ['key_name' => 'active_plugins', 'key_value' => 'a:1:{i:0;s:19:"akismet/akismet.php";}', 'load_policy' => 'yes'],
    ['key_name' => 'plugin_settings', 'key_value' => '{"enabled":true,"mode":"safe"}', 'load_policy' => 'no'],
    ['key_name' => 'siteurl', 'key_value' => 'https://new.example', 'load_policy' => 'yes'],
];
$plan = static fn (): array => SQLiteKeyValueRowsWalImportPlan::currentNext(
    $baseWal(),
    $databaseBytes,
    $databasePath,
    $currentRows(),
    $importRows(),
    [2, 3, 4, 5, 6],
);
$nextWal = static fn (): SQLiteWal => SQLiteWal::parse($plan()['append']['wal_bytes'], null, true);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'application_settings_import_wal_commit_current_next_visibility'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'current row count' => [static fn (): mixed => count($plan()['current_rows']), 3],
    'next row count' => [static fn (): mixed => count($plan()['next_rows']), 4],
    'inserted names' => [static fn (): mixed => $plan()['inserted_key_names'], ['plugin_settings']],
    'updated names preserve import order' => [static fn (): mixed => $plan()['updated_key_names'], ['active_plugins', 'siteurl']],
    'deleted names empty' => [static fn (): mixed => $plan()['deleted_key_names'], []],
    'load_policy names sorted' => [static fn (): mixed => $plan()['load_policy_yes_key_names'], ['active_plugins', 'siteurl']],
    'setting page active plugins' => [static fn (): mixed => $plan()['setting_page_numbers']['active_plugins'], 2],
    'setting page tenant public' => [static fn (): mixed => $plan()['setting_page_numbers']['tenant_public'], 5],
    'setting page plugin settings' => [static fn (): mixed => $plan()['setting_page_numbers']['plugin_settings'], 3],
    'setting page siteurl' => [static fn (): mixed => $plan()['setting_page_numbers']['siteurl'], 4],
    'database page count includes load_policy index' => [static fn (): mixed => $plan()['database_page_count'], 6],
    'append start frame' => [static fn (): mixed => $plan()['append']['start_frame'], 3],
    'append end frame' => [static fn (): mixed => $plan()['append']['end_frame'], 7],
    'append frame count' => [static fn (): mixed => $plan()['append']['appended_frame_count'], 5],
    'append commit count' => [static fn (): mixed => $plan()['append']['committed_transaction_count'], 1],
    'append uncommitted count' => [static fn (): mixed => $plan()['append']['uncommitted_transaction_count'], 0],
    'append last commit frame' => [static fn (): mixed => $plan()['append']['last_commit_frame'], 7],
    'append last page count' => [static fn (): mixed => $plan()['append']['last_database_page_count'], 6],
    'append bytes length' => [static fn (): mixed => $plan()['append']['append_bytes_length'], 5 * (24 + $pageSize)],
    'append write offset' => [static fn (): mixed => $plan()['append']['operations'][0]['offset'], strlen($baseWalBytes())],
    'append sync op' => [static fn (): mixed => $plan()['append']['operations'][1]['op'], 'sync'],
    'append dir sync op' => [static fn (): mixed => $plan()['append']['operations'][2]['op'], 'sync_directory'],
    'current reader sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['wal', 'wal', 'database', 'error', 'error']],
    'next reader sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'current reader frame indexes' => [static fn (): mixed => $plan()['current_reader_frame_indexes'], [1, 2, null, null, null]],
    'next reader frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [3, 4, 5, 6, 7]],
    'current errors count' => [static fn (): mixed => count($plan()['current_reader_errors']), 2],
    'next errors count' => [static fn (): mixed => count($plan()['next_reader_errors']), 0],
    'current page three contains old plugins' => [static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'active_plugins before import'), true],
    'next page active plugins value' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], 'akismet/akismet.php'), true],
    'next page active plugins id preserved' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], '"setting_id":2'), true],
    'next page tenant public retained' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], '"tenant_public"'), true],
    'next page plugin settings inserted' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], '"plugin_settings"'), true],
    'next page plugin settings new id' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], '"setting_id":4'), true],
    'next page siteurl updated' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], 'https://new.example'), true],
    'next load_policy index names' => [static fn (): mixed => str_contains($plan()['next_reader'][4]['image'], '"key_names":["active_plugins","siteurl"]'), true],
    'next load_policy excludes plugin settings' => [static fn (): mixed => !str_contains($plan()['next_reader'][4]['image'], 'plugin_settings'), true],
    'dependency includes wp import' => [static fn (): mixed => in_array('application-settings-wal-import-current-next', $plan()['dependencies'], true), true],
    'dependency includes wal append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'next wal frame count' => [static fn (): mixed => $nextWal()->frameCount(), 7],
    'next wal uncommitted count' => [static fn (): mixed => $nextWal()->uncommittedFrameCount(), 0],
    'next wal last commit frame' => [static fn (): mixed => $nextWal()->lastCommitFrame()?->index, 7],
    'next wal reader page count' => [static fn (): mixed => $nextWal()->readerSnapshot($databaseBytes, 7)['database_page_count'], 6],
    'next wal map count' => [static fn (): mixed => count($nextWal()->readerSnapshotPageMap($databaseBytes, 7)), 6],
    'next wal page four image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 4, 7)['image'], 'new.example'), true],
    'next wal page six load_policy image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 6, 7)['image'], 'app_settings_load_policy'), true],
    'current snapshot remains at frame two' => [static fn (): mixed => $baseWal()->readerSnapshot($databaseBytes, 2)['database_page_count'], 4],
    'current snapshot cannot see plugin settings page' => [static function () use ($baseWal, $databaseBytes): mixed {
        try {
            $baseWal()->readerSnapshotPageImage($databaseBytes, 5, 2);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }

        return 'unexpected';
    }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['application settings import wal current next34 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['application settings import wal current next34 applies append through vfs writer'] = static function (TestRunner $t) use ($baseWalBytes, $baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-app-settings-wal34-' . bin2hex(random_bytes(4));
    $localWal = $root . '/' . $databasePath . '-wal';
    $directory = dirname($localWal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL import test directory');
    }
    file_put_contents($localWal, $baseWalBytes());

    $plan = SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), [2, 5, 6]);
    $applied = (new SQLiteVfsFileWriter($root))->applyWalAppendTransactions($baseWal(), $databasePath, [[
        'pages' => array_combine(
            array_column($plan['append']['frames'], 'page_number'),
            array_map(static fn (array $frame): string => SQLiteWal::parse($plan['append']['wal_bytes'], null, true)->frames[$frame['frame_index'] - 1]->pageImage, $plan['append']['frames'])
        ),
        'database_page_count' => $plan['database_page_count'],
        'commit' => true,
    ]]);
    $afterWal = SQLiteWal::parse((string) file_get_contents($localWal), null, true);

    $t->same('applied', $applied['status']);
    $t->same(5, $applied['append']['appended_frame_count']);
    $t->same(7, $afterWal->lastCommitFrame()?->index);
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 4, 7)['image'], 'https://new.example'));
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 6, 7)['image'], 'app_settings_load_policy'));
};

$tests['application settings import wal current next34 rejects bad inputs'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, '', $currentRows(), $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, [['setting_id' => 0, 'key_name' => 'bad', 'key_value' => 'x']], $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [['key_name' => '', 'key_value' => 'x']], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteKeyValueRowsWalImportPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), ['2']));
};

return $tests;
