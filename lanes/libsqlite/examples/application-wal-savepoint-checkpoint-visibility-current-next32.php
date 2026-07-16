<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';
$databaseBytes = $page('wp db header before savepoint') . $page('wp autoload before savepoint') . $page('wp plugin draft before savepoint');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x32333435;
    $salt2 = 0x42434445;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 32, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'wp schema retained WAL frame'],
    [2, 3, 'wp autoload retained commit'],
    [3, 0, 'wp plugin settings draft'],
    [2, 3, 'wp rolled back plugin commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(1, $page('wp db header before savepoint'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordPageImageWrite(2, $page('wp autoload before savepoint'));
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordPageImageWrite(3, $page('wp plugin draft before savepoint'));
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$root = sys_get_temp_dir() . '/port-libsqlite-wp-wal-savepoint-checkpoint-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create Application WAL fixture directory');
}
file_put_contents($localDatabase, $databaseBytes);
file_put_contents($localDatabase . '-wal', $walBytes . 'stale-import-tail');

$result = (new SQLiteVfsFileWriter($root))->applySavepointCheckpointVisibility(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'truncate'
);

$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localDatabase . '-wal');
$summary = [
    'status' => $result['status'],
    'atomic' => $result['atomic'],
    'operations' => $result['applied'],
    'retainedFrames' => $result['savepoint_checkpoint']['retained_frame_count'],
    'discardedFrames' => $result['savepoint_checkpoint']['discarded_frame_count'],
    'walAction' => $result['savepoint_checkpoint']['current_durable']['wal_action'],
    'currentReaderSources' => $result['reader_boundary']['current_reader_sources'],
    'nextReaderSources' => $result['reader_boundary']['next_reader_sources'],
    'nextReaderUsesCheckpointDatabase' => $result['reader_boundary']['next_reader_uses_checkpoint_database'],
    'rolledBackPluginVisible' => str_contains($databaseAfter . $walAfter, 'rolled back plugin commit'),
    'staleWalTailVisible' => str_contains($walAfter, 'stale-import-tail'),
    'dependencies' => $result['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'applied');
    assert($summary['atomic'] === true);
    assert($summary['retainedFrames'] === 2);
    assert($summary['discardedFrames'] === 2);
    assert($summary['walAction'] === 'truncate_wal');
    assert($summary['currentReaderSources'] === ['wal', 'wal', 'database']);
    assert($summary['nextReaderSources'] === ['database', 'database', 'database']);
    assert($summary['rolledBackPluginVisible'] === false);
    assert($summary['staleWalTailVisible'] === false);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
