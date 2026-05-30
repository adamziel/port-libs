<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options.sqlite';
$masterPath = '/srv/wp-content/database/wp-options.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-options-network.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-options-network.sqlite-journal");
$sourceDigest = hash('sha256', 'wordpress-next168-master-current-source');
$generation = 6;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$header = static function (string $label, int $changeCounter, int $schemaCookie, int $validFor) use ($page, $pageSize): string {
    $bytes = $page($label);
    $bytes = substr_replace($bytes, pack('N', $changeCounter), 24, 4);
    $bytes = substr_replace($bytes, pack('N', $schemaCookie), 40, 4);
    $bytes = substr_replace($bytes, pack('N', $validFor), 92, 4);

    return str_pad(substr($bytes, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};
$digest = static fn (int $pageNumber, string $image, int $sourceGeneration = null): string => hash(
    'sha256',
    $sourceDigest . '|' . ($sourceGeneration ?? $generation) . '|' . $pageNumber . '|' . hash('sha256', $image)
);

$before = [
    1 => $header('wp options stale header before master journal', 80, 91, 80),
    2 => $page('wp_options root before master journal'),
    3 => $page('active_plugins before master journal'),
    4 => $page('plugin settings before master journal'),
];
$recovered = [
    1 => $header('wp options recovered header after master journal', 81, 92, 81),
    2 => $page('wp_options recovered root after master journal'),
    3 => $page('active_plugins recovered after master journal'),
    4 => $page('plugin settings recovered after master journal'),
];
$source = 'wp-options-reader-cache-before-master-current-source';
$entry = static fn (string $label, string $image, int $pageNumber, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $source,
    'epoch' => 5,
    'master_journal_digest' => $masterDigest,
    'change_counter' => 81,
    'schema_cookie' => 92,
    'version_valid_for' => 81,
    'page_source_digest' => $digest($pageNumber, $image),
    'source_generation' => $generation,
], $extra);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheSourceDigestFence(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $entry('header-retained', $recovered[1], 1),
        2 => $entry('root-refreshable', $before[2], 2, ['page_source_digest' => $digest(2, $recovered[2])]),
        3 => $entry('active-plugins-stale-source-digest', $recovered[3], 3, ['page_source_digest' => $digest(3, $before[3])]),
        4 => $entry('settings-stale-generation', $recovered[4], 4, ['source_generation' => 5]),
    ],
    [1, 2, 3, 4],
    [
        3 => $page('active_plugins rewritten after master source fence'),
    ],
    $source,
    5,
    $sourceDigest,
    $generation,
);

if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next168') {
    throw new RuntimeException('Unexpected pager reader cache next168 status');
}
if ($plan['source_invalidated_cache_page_numbers'] !== [3, 4]) {
    throw new RuntimeException('Stale source-digest reader cache pages were not invalidated');
}
if ($plan['retained_cache_page_numbers'] !== [1] || $plan['refreshed_cache_page_numbers'] !== [2]) {
    throw new RuntimeException('Retained/refreshed WordPress cache pages changed unexpectedly');
}
if (!str_contains($plan['final_database_bytes'], 'active_plugins rewritten after master source fence')) {
    throw new RuntimeException('Next write did not use recovered source bytes');
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next168 self-test passed\n";
