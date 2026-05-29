<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next232.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next232-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next232-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 232), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503232), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next232 stale schema before path fence'),
    2 => $page('wp next232 stale wp_options root before path fence'),
    3 => $page('wp next232 stale active_plugins copied from users db'),
];
$recovered = [
    1 => $formatPage('wp next232 current schema after path fence'),
    2 => $page('wp next232 current wp_options root after path fence'),
    3 => $page('wp next232 current active_plugins after path fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2321:size=4096:mtime=23201:generation=main-current',
    $usersJournal => 'dev=8:ino=2322:size=1024:mtime=23202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next232'),
    $usersJournal => hash('sha256', 'wp users rollback header next232'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 232, 0x57503232]));
$masterDigest = hash('sha256', 'wp next232 master source');
$masterToken = 'dev=8:ino=2320:size=96:mtime=23200:generation=master-current';
$databaseToken = 'dev=8:ino=2329:size=1536:mtime=23299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23300:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=232:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=232:master-journal-recovery=complete';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next232.sqlite';
$usersPathToken = 'db-path-token:users:/srv/wp-content/database/wp-next232-users.sqlite';
$base = [
    'source_id' => $sourceId,
    'epoch' => 232,
    'format_signature' => $formatSignature,
    'publication_generation' => 232,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 232,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-attached-users-cache', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'database_path_token' => $usersPathToken] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 232,
    'format_signature' => $formatSignature,
    'publication_generation' => 232,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 232,
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
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext232(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    232,
    232,
    $masterDigest,
    232,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $mainPathToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next232',
    'wordpressUse' => 'A copied WordPress main database keeps schema/options reader-cache pages only when the cache row belongs to the main database path namespace; an image-identical active_plugins page from an attached users database reopens.',
    'status' => $plan['status'],
    'databasePathInvalidatedPages' => $plan['database_path_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache lease, source-token, and current-source ticket evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next232'
    || $summary['databasePathInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next232 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next232 self-test passed\n";
