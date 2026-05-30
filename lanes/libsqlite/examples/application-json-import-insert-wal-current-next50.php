<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonImportWalSavepointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x50505050;
$salt2 = 0x51515151;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header json import insert wal next50')
    . $page('current active plugins json import next50')
    . $page('current siteurl json import next50')
    . $page('current theme mods json import next50');
$walHeader = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 50, $salt1, $salt2);
$walHeader .= pack('N*', ...SQLiteWal::checksumPair($walHeader, false));
$seed = SQLiteWal::checksumPair(substr($walHeader, 0, 24), false);
$framePrefix = pack('N*', 3, 4, $salt1, $salt2);
$siteurlImage = $page('committed siteurl before json import');
$seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $siteurlImage, false, $seed[0], $seed[1]);
$wal = SQLiteWal::parse($walHeader . $framePrefix . pack('N*', $seed[0], $seed[1]) . $siteurlImage, null, true);
$jsonRows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);

$plan = SQLiteJsonImportWalSavepointPlan::insertWalCurrentNext(
    $wal,
    $databaseBytes,
    [
        ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'theme_mods_twenty', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
    ],
    [
        ['name' => 'plugin_batch', 'json' => $jsonRows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true,"rank":7}', 'autoload' => 'no'],
            ['option_name' => 'active_plugins', 'option_value' => '["akismet/akismet.php"]', 'autoload' => 'yes'],
        ]), 'path' => '$.rows'],
        ['name' => 'bad_batch', 'json' => '{"rows":[', 'path' => '$.rows'],
    ],
    [2, 3, 4, 5],
    ['database_path' => '/tmp/wp-json-import-insert-wal-next50.sqlite', 'page_size' => $pageSize],
);

echo json_encode([
    'scenario' => 'application-json-import-insert-wal-current-next50',
    'applicationUse' => 'Import copied wp_options JSON payloads under SQLite savepoints, then expose released inserts and updates through committed WAL current/next reader visibility while a malformed later batch rolls back.',
    'status' => $plan['status'],
    'releasedBatches' => $plan['released_batches'],
    'rolledBackBatches' => $plan['rolled_back_batches'],
    'changedOptionNames' => $plan['changed_option_names'],
    'insertedOptionNames' => $plan['inserted_option_names'],
    'updatedOptionNames' => $plan['updated_option_names'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'appendFrameCount' => $plan['append_frame_count'],
    'lastCommitFrame' => $plan['last_commit_frame'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
