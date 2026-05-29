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

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next234.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next234.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-application-metadata-next234';
$publication = 234;
$recoverySequence = 234;
$counter = 72;
$userVersion = 604;
$applicationId = 0x575034;
$oldUserVersion = 603;
$oldApplicationId = 0x575033;
$pageCount = 4;
$masterDigest = hash('sha256', 'wp-next234-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2234:size=96:mtime=23400:generation=master-current';
$databaseToken = 'dev=8:ino=9234:size=2048:mtime=23499:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=234:change-counter=72:version-valid-for=72:user-version=604;application-id=575034');
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $userVersion, $applicationId): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);
    $page = substr_replace($page, pack('N', 72), 24, 4);
    $page = substr_replace($page, pack('N', 72), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next234 wp_options schema before application metadata recovery'),
    2 => $page('next234 stale alloptions before application metadata recovery'),
    3 => $page('next234 stale active_plugins before application metadata recovery'),
    4 => $page('next234 stale rewrite_rules before application metadata recovery'),
];
$recovered = [
    1 => $formatPage('next234 current wp_options schema after application metadata recovery'),
    2 => $page('next234 current alloptions after application metadata recovery'),
    3 => $page('next234 current active_plugins after application metadata recovery'),
    4 => $page('next234 current rewrite_rules after application metadata recovery'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad fixture');
        }
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$memberTokens = [
    $mainJournal => 'dev=8:ino=3334:size=4096:mtime=23401:generation=options-current',
    $usersJournal => 'dev=8:ino=4434:size=1024:mtime=23402:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-234'),
    $usersJournal => hash('sha256', 'users-rollback-header-234'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, $userVersion, $applicationId]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, int $user, int $app) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 234,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
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
    'user_version' => $user,
    'application_id' => $app,
];
$readTicket = static fn (int $pageNumber, int $user, int $app) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 234,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
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
    'user_version' => $user,
    'application_id' => $app,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext234(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $userVersion, $applicationId),
        2 => $cacheEntry('alloptions', $before[2], $userVersion, $applicationId),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldUserVersion, $applicationId),
        4 => $cacheEntry('rewrite-rules', $recovered[4], $userVersion, $oldApplicationId),
    ],
    [
        $readTicket(1, $userVersion, $applicationId),
        $readTicket(2, $userVersion, $applicationId),
        $readTicket(3, $oldUserVersion, $applicationId),
        $readTicket(4, $userVersion, $oldApplicationId),
    ],
    $sourceId,
    234,
    $publication,
    $masterDigest,
    $recoverySequence,
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
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next234');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['application_metadata_invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    assert($plan['read_cache_hits']['wp-read-4'] === false);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next234 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'userVersion' => $plan['current_user_version'],
    'applicationId' => $plan['current_application_id'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'applicationMetadataInvalidated' => $plan['application_metadata_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
