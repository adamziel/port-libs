<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalMultiTransactionClusterPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x10242048;
$salt2 = 0x20481024;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0", STR_PAD_RIGHT);
$databaseBytes = $page('wp-options-page-1-before') . $page('wp-options-page-2-before') . $page('wp-options-page-3-before');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 24, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $append($walBytes, $seed, 2, 3, 'tx1-siteurl-autoload');
$walBytes = $append($walBytes, $seed, 3, 3, 'tx1-option-name-index');
$walBytes = $append($walBytes, $seed, 2, 0, 'tx2-plugin-option-draft');
$walBytes = $append($walBytes, $seed, 2, 3, 'tx2-plugin-option-commit');
$walBytes = $append($walBytes, $seed, 4, 4, 'tx3-new-overflow-page');
$walBytes = $append($walBytes, $seed, 3, 0, 'tail-uncommitted-index');

$plan = SQLiteWalMultiTransactionClusterPlan::currentNext(
    SQLiteWal::parse($walBytes, $pageSize, true),
    $databaseBytes,
    [2, 3, 4]
);

echo json_encode([
    'scenario' => 'application-wal-multi-transaction-cluster',
    'applicationUse' => 'Inspect copied wp_options WAL files as committed transaction clusters before checkpointing, so import repair tooling can distinguish current readers that still see WAL frames from the next checkpointed database image without ext/sqlite.',
    'status' => $plan['status'],
    'transactionCount' => $plan['transaction_count'],
    'uncommittedTailFrames' => $plan['uncommitted_tail_frame_count'],
    'databasePageCountAfter' => $plan['database_page_count_after'],
    'clusterFrameRanges' => array_map(
        static fn (array $cluster): array => [$cluster['first_frame'], $cluster['last_frame']],
        $plan['clusters']
    ),
    'clusterAppliedPages' => array_column($plan['clusters'], 'applied_page_numbers'),
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'imagesMatch' => $plan['images_match'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
