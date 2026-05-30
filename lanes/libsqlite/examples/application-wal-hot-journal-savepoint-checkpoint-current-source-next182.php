<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next182.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next182 dirty schema')
    . $page('wp next182 dirty wp_options')
    . $page('wp next182 dirty active plugins')
    . $page('wp next182 dirty autoload')
    . $page('wp next182 dirty cron')
    . $page('wp next182 clean usermeta');
$hot = [2 => $page('wp next182 hot clean wp_options'), 4 => $page('wp next182 hot clean autoload')];
$savepointBefore = [3 => $page('wp next182 before active plugins'), 5 => $page('wp next182 before cron')];

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
    [1, 0, 'wp next182 current schema'],
    [2, 6, 'wp next182 current wp_options'],
    [4, 0, 'wp next182 current autoload'],
    [5, 6, 'wp next182 current cron'],
], 182, 0x18200101, 0x18200102);
$nextWalBytes = $makeWal([
    [3, 0, 'wp next182 next active plugins'],
    [5, 6, 'wp next182 next cron'],
], 183, 0x18300101, 0x18300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next182',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next182 current schema'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5, 6],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-old', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    [
        ['name' => 'bootstrap-statement', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 41],
        ['name' => 'bootstrap-old-statement', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'schema_cookie' => 40],
    ],
    41,
    42,
    null,
    null,
    null,
    'restart',
    4,
    182
);

$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next182',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('wp next182 current schema'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
        2 => ['image' => $page('wp next182 current wp_options'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    [1, 2, 3, 4, 5, 6],
    [
        ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'wp-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    [
        ['name' => 'select-usermeta-clean', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6]],
        ['name' => 'select-options-root-hot', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [2]],
        ['name' => 'select-next-source', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch'], 'schema_cookie' => 42, 'root_pages' => [6]],
    ],
    41,
    42,
    $currentToken,
    $nextToken,
    null,
    'restart',
    4,
    182
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next182',
    'applicationUse' => 'A copied Application import recovers a hot journal, rolls back a plugin savepoint, checkpoints the retained WAL current source, then keeps only prepared statements whose source token, schema cookie, and root pages are still current.',
    'status' => $plan['status'],
    'admittedStatements' => $plan['admitted_statement_names'],
    'reprepareStatements' => $plan['reprepare_statement_names'],
    'changedPages' => $plan['changed_page_numbers'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next182'
    || $summary['admittedStatements'] !== ['select-usermeta-clean']
    || $summary['reprepareStatements'] !== ['select-options-root-hot', 'select-next-source']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next182 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
