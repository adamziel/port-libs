<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next156.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('wp next156 dirty schema after plugin crash'),
    2 => $page('wp next156 dirty options root after plugin crash'),
    3 => $page('wp next156 dirty autoload index after plugin crash'),
    4 => $page('wp next156 dirty plugin option after plugin crash'),
];
$clean = [
    1 => $page('wp next156 clean schema before plugin crash'),
    2 => $page('wp next156 clean options root before plugin crash'),
    3 => $page('wp next156 clean autoload index before plugin crash'),
    4 => $page('wp next156 clean plugin option before plugin crash'),
];
$databaseBytes = implode('', $dirty);

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes([
    [1, 0, 'wp next156 current wal schema draft'],
    [2, 4, 'wp next156 current wal options commit'],
    [3, 0, 'wp next156 current wal autoload draft'],
], 156, 0x15610001, 0x15610002);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'wp next156 retry wal options draft'],
    [4, 4, 'wp next156 retry wal plugin commit'],
], 157, 0x15710001, 0x15710002);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCurrentWalSourceSwitch(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-settings-next156',
    [2 => $clean[2], 4 => $clean[4]],
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4]],
    [2 => $page('wp next156 current savepoint options draft'), 3 => $page('wp next156 current savepoint autoload draft')],
    [4 => $page('wp next156 retry plugin option')],
    SQLiteWal::parse($currentWalBytes, $pageSize, true),
    $currentWalBytes,
    SQLiteWal::parse($nextWalBytes, $pageSize, true),
    $nextWalBytes,
    [1, 2, 3, 4],
    3,
    156,
    false,
    true,
    true
);

$root = sys_get_temp_dir() . '/port-libsqlite-next156-example-' . bin2hex(random_bytes(4));
$local = static fn (string $path): string => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');
mkdir(dirname($local($databasePath)), 0777, true);
file_put_contents($local($databasePath), $databaseBytes);
file_put_contents($local($journalPath), 'wp-next156-hot-journal-placeholder');
file_put_contents($local($walPath), $currentWalBytes . 'stale-tail');

$applied = (new SQLiteVfsFileWriter($root))->applyAtomicOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);
$summary = [
    'status' => $plan['status'],
    'applied' => $applied['applied'],
    'filesDeleted' => $applied['files_deleted'],
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'finalWalBytes' => filesize($local($walPath)),
    'journalDeleted' => !file_exists($local($journalPath)),
    'nextWalInstalled' => file_get_contents($local($walPath)) === $nextWalBytes,
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next156') {
        fwrite(STDERR, "unexpected next156 status\n");
        exit(1);
    }
    if ($summary['applied'] !== 10 || $summary['filesDeleted'] !== 1 || $summary['durableSyncs'] !== 3) {
        fwrite(STDERR, "unexpected next156 VFS apply counts\n");
        exit(1);
    }
    if (!$summary['journalDeleted'] || !$summary['nextWalInstalled']) {
        fwrite(STDERR, "next156 VFS apply did not install the expected current-source files\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
