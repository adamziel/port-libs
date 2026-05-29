<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options.sqlite';
$masterPath = '/srv/wp-content/database/wp-options.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-options-network.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-options-network.sqlite-journal");
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$header = static function (string $label, int $changeCounter, int $schemaCookie, int $validFor) use ($page, $pageSize): string {
    $bytes = $page($label);
    $bytes = substr_replace($bytes, pack('N', $changeCounter), 24, 4);
    $bytes = substr_replace($bytes, pack('N', $schemaCookie), 40, 4);
    $bytes = substr_replace($bytes, pack('N', $validFor), 92, 4);
    return str_pad(substr($bytes, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$before = [
    1 => $header('wp options stale header before master journal', 31, 44, 31),
    2 => $page('wp_options root before master journal'),
    3 => $page('active_plugins before master journal'),
    4 => $page('plugin settings before master journal'),
];
$recovered = [
    1 => $header('wp options recovered header after master journal', 32, 45, 32),
    2 => $page('wp_options recovered root after master journal'),
    3 => $page('active_plugins recovered after master journal'),
];
$source = 'wp-options-reader-cache-before-master-header';
$entry = static fn (string $label, string $image, int $changeCounter, int $schemaCookie, int $validFor, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $source,
    'epoch' => 12,
    'master_journal_digest' => $masterDigest,
    'change_counter' => $changeCounter,
    'schema_cookie' => $schemaCookie,
    'version_valid_for' => $validFor,
], $extra);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext164(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $entry('header-retained', $recovered[1], 32, 45, 32),
        2 => $entry('root-refreshable', $before[2], 32, 45, 32),
        3 => $entry('active-plugins-stale-header', $recovered[3], 31, 45, 32),
        4 => $entry('settings-dirty', $before[4], 32, 45, 32, ['dirty' => true]),
    ],
    [1, 2, 3, 4],
    [
        3 => $page('active_plugins rewritten after header fenced reader cache'),
    ],
    $source,
    12,
);

if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next164') {
    throw new RuntimeException('Unexpected pager reader cache status');
}
if ($plan['current_header'] !== ['change_counter' => 32, 'schema_cookie' => 45, 'version_valid_for' => 32]) {
    throw new RuntimeException('Recovered header metadata was not decoded');
}
if ($plan['invalidated_cache_page_numbers'] !== [3, 4]) {
    throw new RuntimeException('Stale WordPress reader cache pages were not invalidated');
}
if ($plan['refreshed_cache_page_numbers'] !== [2]) {
    throw new RuntimeException('Refreshable wp_options root cache page was not refreshed');
}
if (!str_contains($plan['final_database_bytes'], 'active_plugins rewritten after header fenced reader cache')) {
    throw new RuntimeException('Next write did not capture the recovered header source');
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next164 self-test passed\n";
