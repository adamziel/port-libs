<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp_options schema before checkpoint')
    . $page('active_plugins option before checkpoint')
    . $page('autoload index before checkpoint')
    . $page('cron option before checkpoint');
$salt1 = 0x88001001;
$salt2 = 0x88001002;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 88, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(2, 0, 'active_plugins value pinned by old reader');
$append(3, 4, 'autoload index checkpointed before truncate');
$append(2, 0, 'active_plugins value visible to next reader');
$append(4, 4, 'cron option checkpointed before truncate');

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointTruncateReaderCurrentSourceNext(
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    2
);

echo json_encode([
    'scenario' => 'wordpress-wal-reader-checkpoint-truncate-current-source-next88',
    'status' => $plan['status'],
    'walActionWhilePinned' => $plan['wal_action'],
    'walActionAfterReaderRelease' => $plan['drained_wal_action'],
    'currentReaderSources' => $plan['current_source_names'],
    'nextReaderSources' => $plan['next_source_names'],
    'drainedReaderSources' => $plan['drained_source_names'],
    'readerPinBlocksTruncate' => $plan['reader_pin_blocks_truncate'],
    'drainedRetryTruncatesWal' => $plan['drained_retry_truncates_wal'],
    'nextMatchesDrainedImages' => $plan['next_drained_images_match'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
