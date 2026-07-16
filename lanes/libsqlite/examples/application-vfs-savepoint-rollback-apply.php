<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-savepoint-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$localDatabase = $root . $databasePath;
$localWal = $localDatabase . '-wal';

$pageOneClean = str_pad('wp-options-schema-before-import', $pageSize, "\0");
$pageTwoClean = str_pad('wp-options-table-before-import', $pageSize, "\0");
$pageThreeBeforePlugin = str_pad('plugin-settings-before-batch', $pageSize, "\0");
$pageFourBeforeRow = str_pad('single-option-before-row', $pageSize, "\0");
$dirtyDatabase = str_pad('wp-options-schema-dirty', $pageSize, "\0")
    . str_pad('wp-options-table-dirty', $pageSize, "\0")
    . str_pad('plugin-settings-dirty', $pageSize, "\0")
    . str_pad('single-option-dirty', $pageSize, "\0");

$salt1 = 0x51515151;
$salt2 = 0x61616161;
$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 21, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $pageImage;
};
$walBytes = $appendFrame($walBytes, $checksumSeed, 1, 0, str_pad('wal-schema-before-plugin', $pageSize, "\0"));
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 2, str_pad('wal-table-before-plugin', $pageSize, "\0"));
$walBytes = $appendFrame($walBytes, $checksumSeed, 3, 0, str_pad('wal-plugin-draft-discarded', $pageSize, "\0"));
$walBytes = $appendFrame($walBytes, $checksumSeed, 4, 4, str_pad('wal-row-commit-discarded', $pageSize, "\0"));
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_option_import');
$savepoints->recordPageImageWrite(1, $pageOneClean);
$savepoints->recordPageImageWrite(2, $pageTwoClean);
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings');
$savepoints->recordPageImageWrite(3, $pageThreeBeforePlugin);
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->savepoint('single_option_row');
$savepoints->recordPageImageWrite(4, $pageFourBeforeRow);
$savepoints->recordWalFrameWrite(4, 4, true);

$applied = (new SQLiteVfsFileWriter($root))->applySavepointRollback(
    $savepoints,
    'plugin_settings',
    $dirtyDatabase,
    $pageSize,
    $databasePath,
    $wal,
    $walBytes
);
$databaseBytes = file_get_contents($localDatabase);
$walAfter = file_get_contents($localWal);

echo json_encode([
    'scenario' => 'application-vfs-savepoint-rollback-apply',
    'applicationUse' => 'Apply accepted SAVEPOINT rollback page images and WAL frame truncation through bounded native PHP file handles for copied wp_options imports, so failed plugin settings batches restore database pages, discard WAL tail frames, sync database/WAL handles, and persist sidecar directory state without ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'status' => $applied['status'],
    'appliedOperations' => $applied['applied'],
    'bytesWritten' => $applied['bytes_written'],
    'bytesTruncated' => $applied['bytes_truncated'],
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'restoredPages' => $applied['database_image']['restored_page_numbers'],
    'discardedWalFrames' => $applied['wal_truncation']['discarded_frame_count'],
    'retainedWalFrames' => $applied['wal_truncation']['retained_frame_count'],
    'localDatabaseBytes' => strlen($databaseBytes),
    'localWalBytes' => strlen($walAfter),
    'pagePrefixes' => [
        'page1' => rtrim(substr($databaseBytes, 0, 64), "\0"),
        'page2' => rtrim(substr($databaseBytes, $pageSize, 64), "\0"),
        'page3' => rtrim(substr($databaseBytes, $pageSize * 2, 64), "\0"),
        'page4' => rtrim(substr($databaseBytes, $pageSize * 3, 64), "\0"),
    ],
    'walContainsDiscardedPluginDraft' => str_contains($walAfter, 'wal-plugin-draft-discarded'),
    'walContainsDiscardedRowCommit' => str_contains($walAfter, 'wal-row-commit-discarded'),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
