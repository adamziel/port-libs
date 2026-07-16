<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next177.sqlite';
$master = '/srv/wp-content/database/wp-next177.sqlite-mj';
$sourceId = 'wp-next177-header-ticket';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$header = static function (string $label, int $change, int $size, int $trunk, int $free, int $schema) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    foreach ([24 => $change, 28 => $size, 32 => $trunk, 36 => $free, 40 => $schema] as $offset => $value) {
        $page = substr_replace($page, pack('N', $value), $offset, 4);
    }

    return substr_replace($page, $label, 100, strlen($label));
};
$signature = static fn (int $change, int $size, int $trunk, int $free, int $schema): string => hash('sha256', implode('|', [
    $change,
    $size,
    $trunk,
    $free,
    $schema,
]));

$oldSignature = $signature(7, 4, 0, 0, 18);
$currentSignature = $signature(8, 5, 4, 1, 19);
$before = [
    1 => $header('wp next177 old header', 7, 4, 0, 0, 18),
    2 => $page('wp next177 stale wp_options root'),
    3 => $page('wp next177 stale active_plugins option'),
    4 => $page('wp next177 stale plugin cache page'),
    5 => $page('wp next177 recovered extension page'),
];
$recovered = [
    1 => $header('wp next177 current header', 8, 5, 4, 1, 19),
    2 => $page('wp next177 recovered wp_options root'),
    3 => $page('wp next177 recovered active_plugins option'),
    5 => $page('wp next177 recovered extension page'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::headerTicketReaderCachePlan(
    $database,
    $master,
    $database . "-journal\n/srv/wp-content/database/wp-next177-users.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'schema-reader', 'header_signature' => $currentSignature],
        2 => ['image' => $before[2], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'options-reader', 'header_signature' => $currentSignature],
        3 => ['image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'active-reader', 'header_signature' => $oldSignature],
        4 => ['image' => $before[4], 'source_id' => $sourceId, 'epoch' => 4, 'reader_id' => 'plugin-cache-reader', 'header_signature' => $currentSignature, 'dirty' => true],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 4, 'header_signature' => $currentSignature],
        ['reader_id' => 'options-read', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 4, 'header_signature' => $currentSignature],
        ['reader_id' => 'active-read', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 4, 'header_signature' => $currentSignature],
        ['reader_id' => 'plugin-cache-read', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 4, 'header_signature' => $currentSignature],
    ],
    $sourceId,
    4,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-header-ticket',
    'status' => $plan['status'],
    'headerTicket' => $plan['header_ticket'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'nextReadPrefixes' => $plan['read_prefixes'],
    'applicationUse' => 'Copied wp_options reads after master-journal recovery fence reader-cache reuse with the recovered page-1 header change-counter/schema-cookie/freelist ticket before serving cached option pages.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager recovery, page-image, and page-1 header parsing primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next177'
    || $summary['headerTicket']['change_counter'] !== 8
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3, 4]
    || $summary['nextReadPrefixes']['active-read'] !== 'wp next177 recovered active_plugins option'
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-header-ticket self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-reader-cache-header-ticket self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
