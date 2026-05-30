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
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and wp_options root before import')
    . $page('current siteurl option before import')
    . $page('current active_plugins option before import')
    . $page('current autoload index before import');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 34, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('wal draft siteurl before import'));
$walBytes = $appendFrame($walBytes, $seed, 3, 4, $page('wal committed active_plugins before import'));

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = SQLiteKeyValueRowsWalImportPlan::currentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'no'],
    ],
    [
        ['option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"akismet/akismet.php";}', 'autoload' => 'yes'],
        ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true,"mode":"safe"}', 'autoload' => 'no'],
        ['option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
    ],
    [2, 3, 4, 5, 6],
);

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'updated' => $plan['updated_names'],
    'inserted' => $plan['inserted_names'],
    'autoload' => $plan['autoload_yes_names'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'append_frames' => $plan['append']['appended_frame_count'],
    'last_commit_frame' => $plan['append']['last_commit_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
