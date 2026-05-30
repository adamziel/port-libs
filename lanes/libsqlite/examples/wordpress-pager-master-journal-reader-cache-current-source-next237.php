<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

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
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next237.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next237.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-schema-format-next237';
$publication = 237;
$counter = 77;
$userVersion = 607;
$applicationId = 0x575037;
$schemaFormat = 4;
$oldSchemaFormat = 3;
$pageCount = 4;
$masterDigest = hash('sha256', 'wp-next237-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2237:size=96:mtime=23700:generation=master-current';
$databaseToken = 'dev=8:ino=9237:size=2048:mtime=23799:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=237:change-counter=77:version-valid-for=77:schema-format=4');
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $format) use ($pageSize, $counter, $userVersion, $applicationId): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', $counter), 24, 4);
    $page = substr_replace($page, pack('N', $format), 44, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);
    $page = substr_replace($page, pack('N', $counter), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next237 wp_options schema before schema-format recovery', $oldSchemaFormat),
    2 => $page('next237 stale alloptions before schema-format recovery'),
    3 => $page('next237 stale active_plugins before schema-format recovery'),
    4 => $page('next237 stale rewrite_rules before schema-format recovery'),
];
$recovered = [
    1 => $formatPage('next237 current wp_options schema after schema-format recovery', $schemaFormat),
    2 => $page('next237 current alloptions after schema-format recovery'),
    3 => $page('next237 current active_plugins after schema-format recovery'),
    4 => $page('next237 current rewrite_rules after schema-format recovery'),
];
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$memberTokens = [
    $mainJournal => 'dev=8:ino=3337:size=4096:mtime=23701:generation=options-current',
    $usersJournal => 'dev=8:ino=4437:size=1024:mtime=23702:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-237'),
    $usersJournal => hash('sha256', 'users-rollback-header-237'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, $userVersion, $applicationId]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, int $format) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 237,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 237,
    'recovered_page_set_digest' => $recoveredPageSet,
    'member_journal_tokens' => $memberTokens,
    'member_journal_header_digests' => $memberHeaders,
    'master_member_order_digest' => $memberOrderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'database_header_digest' => $currentHeader,
    'database_page_count' => $pageCount,
    'database_change_counter' => $counter,
    'version_valid_for' => $counter,
    'user_version' => $userVersion,
    'application_id' => $applicationId,
    'schema_format_number' => $format,
];
$readTicket = static fn (int $pageNumber, int $format) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 237,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 237,
    'recovered_page_set_digest' => $recoveredPageSet,
    'member_journal_token_digest' => $memberTokenDigest,
    'member_journal_header_digest' => $memberHeaderDigest,
    'master_member_order_digest' => $memberOrderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'database_header_digest' => $currentHeader,
    'database_page_count' => $pageCount,
    'database_change_counter' => $counter,
    'version_valid_for' => $counter,
    'user_version' => $userVersion,
    'application_id' => $applicationId,
    'schema_format_number' => $format,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderCacheSourceMapFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $schemaFormat),
        2 => $cacheEntry('alloptions', $before[2], $schemaFormat),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldSchemaFormat),
        4 => $cacheEntry('rewrite-rules', $recovered[4], $oldSchemaFormat),
    ],
    [
        $readTicket(1, $schemaFormat),
        $readTicket(2, $schemaFormat),
        $readTicket(3, $oldSchemaFormat),
        $readTicket(4, $oldSchemaFormat),
    ],
    $sourceId,
    237,
    $publication,
    $masterDigest,
    237,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
    $currentHeader,
    $pageCount,
    $counter,
    $counter,
    $userVersion,
    $applicationId,
    $schemaFormat,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next237',
    'status' => $plan['status'],
    'invalidatedPages' => $plan['schema_format_number_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'schemaFormat' => $plan['current_schema_format_number'],
    'activePluginsSource' => $plan['next_reads'][2]['source'],
    'wordpressUse' => 'A copied wp_options import reopens stale reader-cache tickets when master-journal recovery upgrades the schema-format number before reading active_plugins and rewrite_rules pages.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache current-source primitives',
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next237'
        || $summary['invalidatedPages'] !== [3, 4]
        || $summary['reopenReaders'] !== ['wp-read-3', 'wp-read-4']
        || $summary['schemaFormat'] !== 4
    ) {
        fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next237 self-test failed\n");
        exit(1);
    }
    echo "wordpress-pager-master-journal-reader-cache-current-source-next237 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
