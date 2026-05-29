<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next180.sqlite';
$master = '/srv/wp-content/database/wp-next180.sqlite-mj';
$sourceId = 'wp-next180-format-ticket-source';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next180-users.sqlite-journal\n";
$formatPage = static function (string $label, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 12, 0x57504f50]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 11, 0x57504f4c]));

$before = [
    1 => $formatPage('wp next180 old header before master recovery', 0, 1, 11, 0x57504f4c),
    2 => $page('wp next180 stale wp_options before master recovery'),
    3 => $page('wp next180 stale active_plugins before master recovery'),
];
$recovered = [
    1 => $formatPage('wp next180 current header after master recovery', 4, 2, 12, 0x57504f50),
    2 => $page('wp next180 recovered wp_options after master recovery'),
    3 => $page('wp next180 recovered active_plugins after master recovery'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext180(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => [
            'label' => 'wp-schema-format-cache',
            'image' => $recovered[1],
            'source_id' => $sourceId,
            'epoch' => 180,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'shared' => true,
        ],
        2 => [
            'label' => 'wp-options-format-refresh',
            'image' => $before[2],
            'source_id' => $sourceId,
            'epoch' => 180,
            'reader_id' => 'options-reader',
            'format_signature' => $formatSignature,
        ],
        3 => [
            'label' => 'wp-active-plugins-stale-format',
            'image' => $recovered[3],
            'source_id' => $sourceId,
            'epoch' => 180,
            'reader_id' => 'active-plugins-reader',
            'format_signature' => $oldFormatSignature,
        ],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $formatSignature],
        ['reader_id' => 'options-read', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $formatSignature],
        ['reader_id' => 'active-plugins-read', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $formatSignature],
    ],
    $sourceId,
    180,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next180',
    'status' => $plan['status'],
    'formatTicket' => $plan['format_ticket'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'optionsReadPrefix' => $plan['read_prefixes']['options-read'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-plugins-read'],
    'wordpressUse' => 'A copied wp_options database refreshes reader-cache pages after master-journal recovery when the page-1 format ticket changes reserved bytes, text encoding, user_version, or application_id.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal reader-cache page-ticket primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next180'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3]
    || $summary['optionsReadPrefix'] !== 'wp next180 recovered wp_options after master recovery'
    || $summary['activePluginsCacheHit'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next180 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next180 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
