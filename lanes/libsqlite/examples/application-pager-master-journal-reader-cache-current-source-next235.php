<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next235.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next235-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next235-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$changeCounter = 235001;
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $changeCounter): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $changeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 235), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503235), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next235 stale schema before change counter fence'),
    2 => $page('wp next235 stale wp_options root before change counter fence'),
    3 => $page('wp next235 stale active_plugins before change counter fence'),
];
$recovered = [
    1 => $formatPage('wp next235 current schema after change counter fence'),
    2 => $page('wp next235 current wp_options root after change counter fence'),
    3 => $page('wp next235 current active_plugins after change counter fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2351:size=4096:mtime=23501:generation=main-current',
    $usersJournal => 'dev=8:ino=2352:size=1024:mtime=23502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next235'),
    $usersJournal => hash('sha256', 'wp users rollback header next235'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 235, 0x57503235]));
$masterDigest = hash('sha256', 'wp next235 master source');
$masterToken = 'dev=8:ino=2350:size=96:mtime=23500:generation=master-current';
$databaseToken = 'dev=8:ino=2359:size=1536:mtime=23599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23600:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=235:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=235:master-journal-recovery=complete';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next235.sqlite';
$base = [
    'source_id' => $sourceId,
    'epoch' => 235,
    'format_signature' => $formatSignature,
    'publication_generation' => 235,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 235,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $changeCounter,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-old-counter-cache', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'database_change_counter' => $changeCounter - 1] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 235,
    'format_signature' => $formatSignature,
    'publication_generation' => 235,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 235,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $changeCounter,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderCacheRefreshPlan(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    235,
    235,
    $masterDigest,
    235,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $mainPathToken,
    $changeCounter,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next235',
    'applicationUse' => 'A copied Application main database keeps recovered schema/options reader-cache pages only when their database change counter matches the master-journal-recovered header; an image-identical active_plugins page from the previous change counter reopens before plugin import continues.',
    'status' => $plan['status'],
    'databaseChangeCounterInvalidatedPages' => $plan['database_change_counter_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache path/source/lease tickets and SQLite header change-counter evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next235'
    || $summary['databaseChangeCounterInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next235 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-current-source-next235 self-test passed\n";
