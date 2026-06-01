<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteKeyValueRowsWalImportPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteKeyValueRowsWalImportPlan;

$pageSize = 512;
$salt1 = 0x34343434;
$salt2 = 0x56565656;
$databasePath = '/srv/app/data/application.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and app_settings root before import')
    . $page('current primary_url setting before import')
    . $page('current enabled_modules setting before import')
    . $page('current load_policy index before import');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 34, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('wal draft primary_url before import'));
$walBytes = $appendFrame($walBytes, $seed, 3, 4, $page('wal committed enabled_modules before import'));

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteKeyValueRowsWalImportPlan::currentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        ['setting_id' => 1, 'key_name' => 'primary_url', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
        ['setting_id' => 3, 'key_name' => 'tenant_public', 'key_value' => '1', 'load_policy' => 'no'],
    ],
    [
        ['key_name' => 'enabled_modules', 'key_value' => '["search","cache"]', 'load_policy' => 'yes'],
        ['key_name' => 'module_settings', 'key_value' => '{"enabled":true,"mode":"safe"}', 'load_policy' => 'no'],
        ['key_name' => 'primary_url', 'key_value' => 'https://new.example', 'load_policy' => 'yes'],
    ],
    [2, 3, 4, 5, 6],
);

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'updated' => $plan['updated_key_names'],
    'inserted' => $plan['inserted_key_names'],
    'load_policy' => $plan['load_policy_yes_key_names'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'append_frames' => $plan['append']['appended_frame_count'],
    'last_commit_frame' => $plan['append']['last_commit_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
