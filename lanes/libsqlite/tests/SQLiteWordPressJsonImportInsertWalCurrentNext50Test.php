<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWordPressJsonImportWalSavepointPlan;

$pageSize = 512;
$salt1 = 0x50505050;
$salt2 = 0x51515151;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header json import insert wal next50')
    . $page('current active plugins json import next50')
    . $page('current siteurl json import next50')
    . $page('current theme mods json import next50');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 50, $salt1, $salt2);
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
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('uncommitted active plugins before json import'));
    $bytes = $appendFrame($bytes, $seed, 3, 4, $page('committed siteurl before json import'));

    return $bytes;
};

$wal = static fn (): SQLiteWal => SQLiteWal::parse($baseWalBytes(), null, true);
$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];
$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);

$plan = static fn (array $imports = null, array $options = []): array => SQLiteWordPressJsonImportWalSavepointPlan::insertWalCurrentNext(
    $wal(),
    $databaseBytes,
    $currentRows(),
    $imports ?? [
        ['name' => 'plugin_batch', 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true,"rank":7}', 'autoload' => 'no'],
            ['option_name' => 'active_plugins', 'option_value' => '["akismet/akismet.php"]', 'autoload' => 'yes'],
        ]), 'path' => '$.rows'],
        ['name' => 'theme_batch', 'json' => new SQLiteJsonSubtypeValue('{"rows":[{"option_name":"theme_mods_twenty","option_value":"{\"color\":\"green\"}","autoload":"no"},{"option_name":"theme_palette","option_value":"{\"accent\":\"green\"}","autoload":"yes"}]}'), 'path' => '$.rows'],
        ['name' => 'bad_batch', 'json' => '{"rows":[', 'path' => '$.rows'],
    ],
    [2, 3, 4, 5, 6],
    $options + ['database_path' => '/tmp/wp-json-import-insert-wal-next50.sqlite', 'page_size' => $pageSize],
);

$decodePage = static function (array $result, int $readerOffset): array {
    $json = rtrim($result['wal_import']['next_reader'][$readerOffset]['image'], "\0");

    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
};

$cases = [
    'status includes rolled back later batch' => [static fn (): mixed => $plan()['status'], 'partial_rollback'],
    'reason names next50 behavior' => [static fn (): mixed => $plan()['reason'], 'wordpress_json_import_insert_wal_current_next50'],
    'released batches preserved' => [static fn (): mixed => $plan()['released_batches'], ['plugin_batch', 'theme_batch']],
    'rolled back batch recorded' => [static fn (): mixed => $plan()['rolled_back_batches'], ['bad_batch']],
    'changed option names include inserts and updates' => [static fn (): mixed => $plan()['changed_option_names'], ['active_plugins', 'theme_mods_twenty', 'plugin_settings', 'theme_palette']],
    'inserted names are new options only' => [static fn (): mixed => $plan()['inserted_option_names'], ['plugin_settings', 'theme_palette']],
    'updated names are existing options only' => [static fn (): mixed => $plan()['updated_option_names'], ['active_plugins', 'theme_mods_twenty']],
    'append frame count covers option pages plus autoload index' => [static fn (): mixed => $plan()['append_frame_count'], 6],
    'last commit frame follows existing wal frames' => [static fn (): mixed => $plan()['last_commit_frame'], 8],
    'wal import status planned' => [static fn (): mixed => $plan()['wal_import']['status'], 'planned'],
    'wal import reason preserved' => [static fn (): mixed => $plan()['wal_import']['reason'], 'wordpress_options_import_wal_commit_current_next_visibility'],
    'wal import database path' => [static fn (): mixed => $plan()['wal_import']['database_path'], '/tmp/wp-json-import-insert-wal-next50.sqlite'],
    'wal import wal path' => [static fn (): mixed => $plan()['wal_import']['wal_path'], '/tmp/wp-json-import-insert-wal-next50.sqlite-wal'],
    'wal import inserted names sorted by option row order' => [static fn (): mixed => $plan()['wal_import']['inserted_names'], ['plugin_settings', 'theme_palette']],
    'wal import updated names keep staged order' => [static fn (): mixed => $plan()['wal_import']['updated_names'], ['active_plugins', 'theme_mods_twenty']],
    'wal import current reader sources' => [static fn (): mixed => $plan()['current_reader_sources'], ['wal', 'wal', 'database', 'error', 'error']],
    'wal import next reader sources' => [static fn (): mixed => $plan()['next_reader_sources'], ['wal', 'wal', 'wal', 'wal', 'wal']],
    'wal import next frame indexes' => [static fn (): mixed => $plan()['next_reader_frame_indexes'], [3, 4, 5, 6, 7]],
    'json import final rows retain rolled back exclusion' => [static fn (): mixed => $plan()['json_import']['final_option_names'], ['active_plugins', 'siteurl', 'theme_mods_twenty', 'plugin_settings', 'theme_palette']],
    'json import released rows exclude failed batch' => [static fn (): mixed => $plan()['json_import']['released_option_names'], ['active_plugins', 'siteurl', 'theme_mods_twenty', 'plugin_settings', 'theme_palette']],
    'json import dirty pages include import transaction page bucket' => [static fn (): mixed => $plan()['json_import']['dirty_pages'], [2]],
    'json wal staged frame count includes released batch page buckets' => [static fn (): mixed => $plan()['json_import']['wal']['frame_count'], 2],
    'json wal staged current frame excludes failed batch frame' => [static fn (): mixed => $plan()['json_import']['wal']['current_frame'], 2],
    'rolled back batch has no wal import rows' => [static fn (): mixed => $plan()['json_import']['batches'][2]['dirty_pages'], []],
    'rolled back batch starts after released wal frames' => [static fn (): mixed => $plan()['json_import']['batches'][2]['wal_start_frame'], 2],
    'plugin batch updates one and inserts one' => [static fn (): mixed => [$plan()['json_import']['batches'][0]['updated'], $plan()['json_import']['batches'][0]['inserted']], [1, 1]],
    'theme batch updates one and inserts one' => [static fn (): mixed => [$plan()['json_import']['batches'][1]['updated'], $plan()['json_import']['batches'][1]['inserted']], [1, 1]],
    'dependency includes next50' => [static fn (): mixed => in_array('sqlite-wordpress-json-import-insert-wal-current-next50', $plan()['dependencies'], true), true],
    'dependency includes WAL savepoint import planner' => [static fn (): mixed => in_array('sqlite-wordpress-json-import-wal-savepoint', $plan()['dependencies'], true), true],
    'dependency includes options wal import' => [static fn (): mixed => in_array('wordpress-options-wal-import-current-next', $plan()['dependencies'], true), true],
    'dependency includes wal append transaction' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $plan()['dependencies'], true), true],
    'active plugins next page name' => [static fn (): mixed => $decodePage($plan(), 0)['option_name'], 'active_plugins'],
    'active plugins next page value' => [static fn (): mixed => $decodePage($plan(), 0)['option_value'], '["akismet/akismet.php"]'],
    'active plugins next page id preserved' => [static fn (): mixed => $decodePage($plan(), 0)['option_id'], 1],
    'plugin settings inserted page value follows name order' => [static fn (): mixed => $decodePage($plan(), 1)['option_value'], '{"enabled":true,"rank":7}'],
    'siteurl retained page value' => [static fn (): mixed => $decodePage($plan(), 2)['option_value'], 'https://old.example'],
    'theme mods updated page value' => [static fn (): mixed => $decodePage($plan(), 3)['option_value'], '{"color":"green"}'],
    'plugin settings inserted id' => [static fn (): mixed => $decodePage($plan(), 1)['option_id'], 4],
    'theme palette inserted id' => [static fn (): mixed => $decodePage($plan(), 4)['option_id'], 5],
    'theme palette inserted autoload yes' => [static fn (): mixed => $decodePage($plan(), 4)['autoload'], 'yes'],
    'autoload yes names include inserted palette' => [static fn (): mixed => $plan()['wal_import']['autoload_yes_names'], ['active_plugins', 'siteurl', 'theme_palette']],
    'database page count includes autoload page' => [static fn (): mixed => $plan()['wal_import']['database_page_count'], 7],
    'wal append starts after existing two frames' => [static fn (): mixed => $plan()['wal_import']['append']['start_frame'], 3],
    'wal append last database page count' => [static fn (): mixed => $plan()['wal_import']['append']['last_database_page_count'], 7],
    'wal append commit transaction count' => [static fn (): mixed => $plan()['wal_import']['append']['committed_transaction_count'], 1],
    'wal append uncommitted transaction count' => [static fn (): mixed => $plan()['wal_import']['append']['uncommitted_transaction_count'], 0],
    'wal append writes at current wal size' => [static fn (): mixed => $plan()['wal_import']['append']['operations'][0]['offset'], strlen($baseWalBytes())],
    'wal append sync follows write' => [static fn (): mixed => $plan()['wal_import']['append']['operations'][1]['op'], 'sync'],
    'wal append directory sync follows wal sync' => [static fn (): mixed => $plan()['wal_import']['append']['operations'][2]['op'], 'sync_directory'],
    'wal import next row count' => [static fn (): mixed => count($plan()['wal_import']['next_rows']), 5],
    'wal import current row count' => [static fn (): mixed => count($plan()['wal_import']['current_rows']), 3],
    'no rollback case returns planned status' => [static fn (): mixed => $plan([
        ['name' => 'plugin_batch', 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'no'],
        ]), 'path' => '$.rows'],
    ])['status'], 'planned'],
    'no change released import skips wal import' => [static fn (): mixed => $plan([
        ['name' => 'same_batch', 'json' => $jsonRows([
            ['option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ]), 'path' => '$.rows'],
    ])['wal_import'], null],
    'no change released import has no changed names' => [static fn (): mixed => $plan([
        ['name' => 'same_batch', 'json' => $jsonRows([
            ['option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ]), 'path' => '$.rows'],
    ])['changed_option_names'], []],
    'jsonb input participates in insert wal import' => [static fn (): mixed => $plan([
        ['name' => 'jsonb_batch', 'json' => new SQLiteBlobValue(SQLiteJsonB::encode(['rows' => [
            ['option_name' => 'jsonb_insert', 'option_value' => ['mode' => 'blob'], 'autoload' => 'no'],
        ]])), 'path' => '$.rows'],
    ])['inserted_option_names'], ['jsonb_insert']],
    'json subtype object value is serialized for wal page' => [static fn (): mixed => $plan([
        ['name' => 'object_value', 'json' => $jsonRows([
            ['option_name' => 'object_insert', 'option_value' => ['nested' => true], 'autoload' => 'no'],
        ]), 'path' => '$.rows'],
    ])['wal_import']['next_rows'][1]['option_value'], '{"nested":true}'],
    'custom first option page shifts visible pages' => [static fn (): mixed => $plan(null, ['first_option_page_number' => 10])['wal_import']['option_page_numbers']['active_plugins'], 10],
    'custom autoload page is honored' => [static fn (): mixed => $plan(null, ['autoload_index_page_number' => 30])['wal_import']['database_page_count'], 30],
    'bad wal page list propagates validation' => [static function () use ($wal, $databaseBytes, $currentRows, $jsonRows): string {
        try {
            SQLiteWordPressJsonImportWalSavepointPlan::insertWalCurrentNext($wal(), $databaseBytes, $currentRows(), [
                ['name' => 'plugin_batch', 'json' => $jsonRows([
                    ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}'],
                ]), 'path' => '$.rows'],
            ], ['2'], ['database_path' => '/tmp/wp-json-import-insert-wal-next50.sqlite']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'unexpected';
    }, 'rejected'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['wordpress json import insert wal current next50 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
