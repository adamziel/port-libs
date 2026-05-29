<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$main = '/srv/wp-content/database/wp-next172.sqlite';
$meta = '/srv/wp-content/database/wp-next172-usermeta.sqlite';
$master = '/srv/wp-content/database/wp-next172.sqlite-mj';
$masterBytes = $main . "-journal\n" . $meta . "-journal\n";
$digest = hash('sha256', $masterBytes);
$source = 'wordpress-import-master-next172';

$pages = [
    $main => [
        1 => $page('wp next172 main schema'),
        2 => $page('wp next172 active_plugins'),
    ],
    $meta => [
        1 => $page('wp next172 usermeta schema'),
        2 => $page('wp next172 capabilities'),
    ],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext172(
    $master,
    $masterBytes,
    $pageSize,
    $pages,
    [
        $main => [
            1 => ['image' => $pages[$main][1], 'source_id' => $source, 'epoch' => 172, 'master_digest' => $digest, 'reader_id' => 'main-schema'],
            2 => ['image' => $pages[$meta][2], 'database_path' => $meta, 'source_id' => $source, 'epoch' => 172, 'master_digest' => $digest, 'reader_id' => 'cross-db-slot'],
        ],
        $meta => [
            2 => ['image' => $pages[$meta][2], 'source_id' => $source, 'epoch' => 172, 'master_digest' => str_repeat('0', 64), 'reader_id' => 'stale-meta-digest'],
        ],
    ],
    [
        ['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1],
        ['reader_id' => 'meta-cap-read', 'database_path' => $meta, 'page_number' => 2],
    ],
    $source,
    172,
);

assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next172');
assert($plan['read_cache_hits'] === ['main-schema-read' => true, 'meta-cap-read' => false]);
assert($plan['invalidated_reasons'][$meta . '|' . $meta . '#2'] === 'reader_cache_master_digest_not_current');
assert(in_array('meta-cap-read', $plan['reopen_reader_ids'], true));

echo json_encode([
    'status' => $plan['status'],
    'retained' => $plan['retained'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'invalidated_reasons' => $plan['invalidated_reasons'],
], JSON_PRETTY_PRINT) . PHP_EOL;
