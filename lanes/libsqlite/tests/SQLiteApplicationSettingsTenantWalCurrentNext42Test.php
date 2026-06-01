<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteTenantKeyValueWalPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x42004200;
$salt2 = 0x20260527;
$databasePath = 'app-data/database/tenant.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header tenant before import')
    . $page('app_settings current primary_url before import')
    . $page('app_tenant_2_settings current primary_url before import')
    . $page('app_tenant_3_settings current dashboard_url before import')
    . $page('app_tenant_settings current display_name before import');

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
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('draft tenant primary_url before import'));
    $bytes = $appendFrame($bytes, $seed, 3, 5, $page('committed tenant 2 primary_url before import'));

    return $bytes;
};

$baseWal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), $pageSize, true);
$currentRows = static fn (): array => [
    ['scope' => 'global', 'setting_id' => 1, 'key_name' => 'display_name', 'key_value' => 'Old Tenant Registry', 'load_policy' => 'yes'],
    ['scope' => 'global', 'setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'setting_id' => 1, 'key_name' => 'primary_url', 'key_value' => 'https://old.example/tenant-two', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'setting_id' => 2, 'key_name' => 'tenant_public', 'key_value' => '1', 'load_policy' => 'no'],
    ['scope' => 'tenant', 'tenant_id' => 3, 'setting_id' => 1, 'key_name' => 'dashboard_url', 'key_value' => 'https://old.example/tenant-three/dashboard', 'load_policy' => 'yes'],
];
$importRows = static fn (): array => [
    ['scope' => 'global', 'key_name' => 'enabled_modules', 'key_value' => '["search","cache"]', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'primary_url', 'key_value' => 'https://new.example/tenant-two', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'route_map', 'key_value' => '{"entry":"index.php?id="}', 'load_policy' => 'no'],
    ['scope' => 'tenant', 'tenant_id' => 3, 'key_name' => 'primary_url', 'key_value' => 'https://new.example/tenant-three', 'load_policy' => 'yes'],
    ['scope' => 'global', 'key_name' => 'registration', 'key_value' => 'none', 'load_policy' => 'no'],
];
$plan = static fn (): array => SQLiteTenantKeyValueWalPlan::currentNext(
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
    'reason' => [static fn (): mixed => $plan()['reason'], 'application_tenant_settings_wal_commit_current_next_visibility'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'current row count' => [static fn (): mixed => count($plan()['current_rows']), 5],
    'next row count' => [static fn (): mixed => count($plan()['next_rows']), 8],
    'tables ordered' => [static fn (): mixed => $plan()['tables'], ['app_tenant_2_settings', 'app_tenant_3_settings', 'app_tenant_settings']],
    'inserted keys ordered' => [static fn (): mixed => $plan()['inserted_keys'], ['app_tenant_2_settings:route_map', 'app_tenant_3_settings:primary_url', 'app_tenant_settings:registration']],
    'updated keys ordered' => [static fn (): mixed => $plan()['updated_keys'], ['app_tenant_2_settings:primary_url', 'app_tenant_settings:enabled_modules']],
    'tenant two primary url keeps setting id' => [static fn (): mixed => $plan()['next_rows'][0]['setting_id'], 1],
    'tenant two route map gets next table id' => [static fn (): mixed => $plan()['next_rows'][1]['setting_id'], 3],
    'tenant three primary url gets next table id' => [static fn (): mixed => $plan()['next_rows'][4]['setting_id'], 2],
    'tenant registration gets next table id' => [static fn (): mixed => $plan()['next_rows'][7]['setting_id'], 3],
    'tenant two page list' => [static fn (): mixed => $plan()['table_page_numbers']['app_tenant_2_settings'], [2, 3, 4]],
    'tenant three page list' => [static fn (): mixed => $plan()['table_page_numbers']['app_tenant_3_settings'], [5, 6]],
    'tenant page list' => [static fn (): mixed => $plan()['table_page_numbers']['app_tenant_settings'], [7, 8, 9]],
    'tenant two load_policy index page' => [static fn (): mixed => $plan()['load_policy_index_page_numbers']['app_tenant_2_settings'], 10],
    'tenant three load_policy index page' => [static fn (): mixed => $plan()['load_policy_index_page_numbers']['app_tenant_3_settings'], 11],
    'tenant load_policy index page' => [static fn (): mixed => $plan()['load_policy_index_page_numbers']['app_tenant_settings'], 12],
    'database page count' => [static fn (): mixed => $plan()['database_page_count'], 12],
    'tenant two load_policy names' => [static fn (): mixed => $plan()['load_policy_yes_by_table']['app_tenant_2_settings'], ['primary_url']],
    'tenant three load_policy names' => [static fn (): mixed => $plan()['load_policy_yes_by_table']['app_tenant_3_settings'], ['dashboard_url', 'primary_url']],
    'tenant load_policy names' => [static fn (): mixed => $plan()['load_policy_yes_by_table']['app_tenant_settings'], ['display_name', 'enabled_modules']],
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
    'tenant two primary url page contains table' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], '"table":"app_tenant_2_settings"'), true],
    'tenant two route map page inserted name' => [static fn (): mixed => str_contains($plan()['next_reader'][1]['image'], '"route_map"'), true],
    'tenant two primary url updated' => [static fn (): mixed => str_contains($plan()['next_reader'][0]['image'], 'https://new.example/tenant-two'), true],
    'tenant two tenant public retained' => [static fn (): mixed => str_contains($plan()['next_reader'][2]['image'], '"tenant_public"'), true],
    'tenant three dashboard retained' => [static fn (): mixed => str_contains($plan()['next_reader'][3]['image'], 'tenant-three'), true],
    'tenant three primary url inserted' => [static fn (): mixed => str_contains($plan()['next_reader'][4]['image'], '"primary_url"'), true],
    'tenant display name retained' => [static fn (): mixed => str_contains($plan()['next_reader'][5]['image'], 'Old Tenant Registry'), true],
    'tenant enabled modules updated' => [static fn (): mixed => str_contains($plan()['next_reader'][6]['image'], 'enabled_modules'), true],
    'tenant registration inserted' => [static fn (): mixed => str_contains($plan()['next_reader'][7]['image'], '"registration"'), true],
    'tenant two load_policy index excludes route map' => [static fn (): mixed => !str_contains($plan()['next_reader'][8]['image'], 'route_map'), true],
    'tenant three load_policy index includes primary url' => [static fn (): mixed => str_contains($plan()['next_reader'][9]['image'], '"primary_url"'), true],
    'tenant load_policy index excludes registration' => [static fn (): mixed => !str_contains($plan()['next_reader'][10]['image'], 'registration'), true],
    'dependency marker' => [static fn (): mixed => in_array('application-tenant-settings-wal-current-next42', $plan()['dependencies'], true), true],
    'dependency includes wal append' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'next wal frame count' => [static fn (): mixed => $nextWal()->frameCount(), 13],
    'next wal uncommitted count' => [static fn (): mixed => $nextWal()->uncommittedFrameCount(), 0],
    'next wal last commit frame' => [static fn (): mixed => $nextWal()->lastCommitFrame()?->index, 13],
    'next wal snapshot page count' => [static fn (): mixed => $nextWal()->readerSnapshot($databaseBytes, 13)['database_page_count'], 12],
    'next wal page map count' => [static fn (): mixed => count($nextWal()->readerSnapshotPageMap($databaseBytes, 13)), 12],
    'next wal page eleven image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 11, 13)['image'], 'app_tenant_3_settings_load_policy'), true],
    'next wal page twelve image' => [static fn (): mixed => str_contains($nextWal()->readerSnapshotPageImage($databaseBytes, 12, 13)['image'], 'app_tenant_settings_load_policy'), true],
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
    $tests['application settings tenant wal current next42 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['application settings tenant wal current next42 applies append through vfs writer'] = static function (TestRunner $t) use ($baseWalBytes, $baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-app-tenant-wal42-' . bin2hex(random_bytes(4));
    $localWal = $root . '/' . $databasePath . '-wal';
    $directory = dirname($localWal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create tenant WAL import test directory');
    }
    file_put_contents($localWal, $baseWalBytes());

    $plan = SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), range(2, 12));
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
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 2, 13)['image'], 'https://new.example/tenant-two'));
    $t->same(true, str_contains($afterWal->readerSnapshotPageImage($databaseBytes, 12, 13)['image'], 'app_tenant_settings_load_policy'));
};

$tests['application settings tenant wal current next42 rejects bad inputs'] = static function (TestRunner $t) use ($baseWal, $databaseBytes, $databasePath, $currentRows, $importRows): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, '', $currentRows(), $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, [['scope' => 'tenant', 'tenant_id' => 0, 'setting_id' => 1, 'key_name' => 'bad', 'key_value' => 'x']], $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, [['scope' => 'global', 'setting_id' => 0, 'key_name' => 'bad', 'key_value' => 'x']], $importRows(), [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [['scope' => 'user', 'key_name' => 'bad', 'key_value' => 'x']], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), [['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => '', 'key_value' => 'x']], [2]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTenantKeyValueWalPlan::currentNext($baseWal(), $databaseBytes, $databasePath, $currentRows(), $importRows(), ['2']));
};

return $tests;
