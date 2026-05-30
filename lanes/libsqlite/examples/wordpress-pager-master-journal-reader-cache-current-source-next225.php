<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-next225.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-next225-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterJournal = $database . '-mj';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$sourceId = 'wordpress-next225-master-source';
$publication = 225;
$changeCounter = 84;
$versionValidFor = 84;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$schema = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 225), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503235), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $schema('wp schema before next225 validity recovery'),
    2 => $page('wp_options root before next225 validity recovery'),
    3 => $page('active_plugins before next225 validity recovery'),
];
$recovered = [
    1 => $schema('wp schema after next225 validity recovery'),
    2 => $page('wp_options root after next225 validity recovery'),
    3 => $page('active_plugins after next225 validity recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 225, 0x57503235]));
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $key => $value) {
        $parts[] = $key . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$tokens = [
    $mainJournal => 'dev=8:ino=2251:size=4096:mtime=22501:generation=main-current',
    $usersJournal => 'dev=8:ino=2252:size=1024:mtime=22502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-225'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-225'),
];
$currentHeaderDigest = hash('sha256', 'schema-cookie=225;change-counter=84;version-valid-for=84;page-count=3');
$cacheEntry = static fn (string $label, string $image, int $counter, int $validFor): array => [
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 225,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => hash('sha256', 'wp-next225-master-current'),
    'recovery_sequence' => 225,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
    'master_journal_file_token' => 'dev=8:ino=2250:size=96:mtime=22500:generation=master-current',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'dev=8:ino=2259:size=1536:mtime=22599:generation=database-current',
    'database_header_digest' => $currentHeaderDigest,
    'database_page_count' => 3,
    'database_change_counter' => $counter,
    'database_version_valid_for' => $validFor,
];
$reads = array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 225,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => hash('sha256', 'wp-next225-master-current'),
        'recovery_sequence' => 225,
        'recovered_page_set_digest' => $recoveredDigest($recovered),
        'member_journal_token_digest' => $mapDigest($tokens),
        'member_journal_header_digest' => $mapDigest($headers),
        'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
        'master_journal_file_token' => 'dev=8:ino=2250:size=96:mtime=22500:generation=master-current',
        'master_journal_bytes_digest' => hash('sha256', $masterBytes),
        'database_file_token' => 'dev=8:ino=2259:size=1536:mtime=22599:generation=database-current',
        'database_header_digest' => $currentHeaderDigest,
        'database_page_count' => 3,
        'database_change_counter' => $changeCounter,
        'database_version_valid_for' => $versionValidFor,
    ],
    [1, 2, 3],
);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalPathFence(
    $database,
    $masterJournal,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-retained', $recovered[1], $changeCounter, $versionValidFor),
        2 => $cacheEntry('options-root-refreshed', $before[2], $changeCounter, $versionValidFor),
        3 => $cacheEntry('active-plugins-reopened', $recovered[3], 83, 83),
    ],
    $reads,
    $sourceId,
    225,
    $publication,
    hash('sha256', 'wp-next225-master-current'),
    225,
    $tokens,
    $headers,
    'dev=8:ino=2250:size=96:mtime=22500:generation=master-current',
    'dev=8:ino=2259:size=1536:mtime=22599:generation=database-current',
    $currentHeaderDigest,
    3,
    $changeCounter,
    $versionValidFor,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next225',
    'status' => $plan['status'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'invalidated' => $plan['database_cache_validity_invalidated_cache_page_numbers'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['read-3'],
    'validityToken' => $plan['current_database_cache_validity_token'],
    'wordpressUse' => 'A copied WordPress import that replays an attached rollback member under a master journal reuses schema/root pages only when SQLite page-1 change-counter and version-valid-for tickets still match the recovered current source.',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next225'
        || $summary['invalidated'] !== [3]
        || $summary['activePluginsCacheHit'] !== false
    ) {
        fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next225 self-test failed\n");
        exit(1);
    }
    echo "wordpress-pager-master-journal-reader-cache-current-source-next225 self-test passed\n";
}

return $summary;
