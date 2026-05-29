<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next175.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('wp next175 dirty schema before hot rollback'),
    $page('wp next175 dirty options before hot rollback'),
    $page('wp next175 dirty plugin option before savepoint'),
    $page('wp next175 dirty autoload index before hot rollback'),
    $page('wp next175 dirty cron before savepoint'),
]);
$journalBytes = 'wp-next175-hot-journal';
$hot = [
    2 => $page('wp next175 hot rollback options root'),
    4 => $page('wp next175 hot rollback autoload index'),
];
$savepointBefore = [
    3 => $page('wp next175 before plugin option retry'),
    5 => $page('wp next175 before cron retry'),
];

$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
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

$currentWalBytes = $makeWal([
    [1, 0, 'wp next175 current schema draft'],
    [2, 5, 'wp next175 current options commit'],
    [4, 0, 'wp next175 current autoload draft'],
    [5, 5, 'wp next175 current cron commit'],
], 175, 0x17500101, 0x17500102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next175 next plugin retry draft'],
    [5, 5, 'wp next175 next cron commit'],
], 176, 0x17600101, 0x17600102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next175',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next175 current schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-old', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    null,
    null,
    null,
    'restart',
    4,
    175
);
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next175',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next175 current schema draft'), 'source_id' => $bootstrap['current_source_token']['id'], 'epoch' => $bootstrap['current_source_token']['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'wp-current-schema', 'source_id' => $bootstrap['current_source_token']['id'], 'epoch' => $bootstrap['current_source_token']['epoch']],
        ['name' => 'wp-reopen-plugin-import', 'source_id' => $bootstrap['next_source_token']['id'], 'epoch' => $bootstrap['next_source_token']['epoch']],
    ],
    $bootstrap['current_source_token'],
    $bootstrap['next_source_token'],
    null,
    'restart',
    4,
    175
);

$root = sys_get_temp_dir() . '/port-libs-sqlite-wp-next175-' . bin2hex(random_bytes(4));
$local = $root . '/' . ltrim($databasePath, '/');
mkdir(dirname($local), 0777, true);
file_put_contents($local, $databaseBytes);
file_put_contents($local . '-journal', $journalBytes);
file_put_contents($local . '-wal', $currentWalBytes);

try {
    $result = (new SQLiteVfsFileWriter($root))->applyWalHotJournalSavepointCheckpointCurrentSourceNext175($prepared);
    $summary = [
        'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next175',
        'wordpressUse' => 'A copied WordPress import publishes a guarded hot-journal/savepoint checkpoint only after live database, journal, and WAL bytes still match the prepared current source.',
        'status' => $result['status'],
        'applied' => $result['applied'],
        'journalRemoved' => !is_file($local . '-journal'),
        'databaseContainsCommittedOptions' => str_contains((string) file_get_contents($local), 'wp next175 current options commit'),
        'walFrameCount' => SQLiteWal::parse((string) file_get_contents($local . '-wal'), $pageSize, true)->frameCount(),
        'dependencyClosure' => $result['publication']['dependency_closure'],
    ];
} finally {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

if ($summary['status'] !== 'applied' || $summary['applied'] !== 8 || !$summary['journalRemoved'] || !$summary['databaseContainsCommittedOptions']) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next175 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
