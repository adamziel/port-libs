<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-savepoint-checkpoint-apply.sqlite';
$root = sys_get_temp_dir() . '/port-libsqlite-application-savepoint-checkpoint-apply-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create Application WAL savepoint-checkpoint-apply fixture directory');
}

$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('wp savepoint-checkpoint-apply clean schema before crashed import'),
    2 => $page('wp savepoint-checkpoint-apply clean options before crashed import'),
    3 => $page('wp savepoint-checkpoint-apply clean plugin settings before crashed import'),
    4 => $page('wp savepoint-checkpoint-apply clean autoload index before crashed import'),
];
$dirtyDatabase = $page('wp savepoint-checkpoint-apply dirty schema')
    . $page('wp savepoint-checkpoint-apply dirty options')
    . $page('wp savepoint-checkpoint-apply dirty plugin settings')
    . $page('wp savepoint-checkpoint-apply dirty autoload index');

$nonce = 0x15815815;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, 4, $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x15815801;
$salt2 = 0x15815802;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 158, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, 'wp savepoint-checkpoint-apply wal option draft before savepoint'],
    [3, 4, 'wp savepoint-checkpoint-apply wal plugin commit before savepoint'],
    [4, 4, 'wp savepoint-checkpoint-apply failed autoload savepoint tail'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

file_put_contents($localDatabase, $dirtyDatabase);
file_put_contents($localDatabase . '-journal', $journalBytes);
file_put_contents($localDatabase . '-wal', $walBytes);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-savepoint-checkpoint-apply');
$stack->recordWalFrameWrite(1, 2);
$stack->recordWalFrameWrite(2, 3, true);
$stack->savepoint('autoload-retry-savepoint-checkpoint-apply');
$stack->recordWalFrameWrite(3, 4, true);

$applied = (new SQLiteVfsFileWriter($root))->applyWalHotJournalSavepointCheckpoint(
    $stack,
    'autoload-retry-savepoint-checkpoint-apply',
    $databasePath,
    [1, 2, 3, 4],
    [[
        'pages' => [
            4 => $page('wp savepoint-checkpoint-apply retry autoload committed'),
        ],
        'database_page_count' => 4,
        'commit' => true,
    ]],
    2
);

$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localDatabase . '-wal');
$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-savepoint-checkpoint-apply',
    'applicationUse' => 'A copied Application options import recovers a hot rollback journal, drops the failed savepoint WAL tail, checkpoints the current source after reader release, and starts a separate retry WAL generation without requiring ext/sqlite.',
    'status' => $applied['recovery']['status'],
    'appliedOperations' => $applied['applied'],
    'journalDeleted' => !is_file($localDatabase . '-journal'),
    'readerSources' => $applied['recovery']['reader_sources'],
    'pinnedCheckpointBusy' => $applied['recovery']['pinned_checkpoint']['busy'],
    'releasedWalAction' => $applied['recovery']['released_checkpoint']['wal_action'],
    'databaseHasCommittedPlugin' => str_contains($databaseAfter, 'wp savepoint-checkpoint-apply wal plugin commit before savepoint'),
    'databaseExcludesFailedTail' => !str_contains($databaseAfter, 'wp savepoint-checkpoint-apply failed autoload savepoint tail'),
    'walHasRetry' => str_contains($walAfter, 'wp savepoint-checkpoint-apply retry autoload committed'),
    'dependencyClosure' => $applied['recovery']['dependency_closure'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-savepoint-checkpoint-apply'
        || $summary['journalDeleted'] !== true
        || $summary['pinnedCheckpointBusy'] !== true
        || $summary['releasedWalAction'] !== 'restart_wal'
        || $summary['databaseHasCommittedPlugin'] !== true
        || $summary['databaseExcludesFailedTail'] !== true
        || $summary['walHasRetry'] !== true
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-savepoint-checkpoint-apply self-test failed\n");
        exit(1);
    }
    echo "application-wal-hot-journal-savepoint-checkpoint-savepoint-checkpoint-apply self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
