<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next218.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next218-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next218-current-source';
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
    $page = substr_replace($page, pack('N', 218), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next218 stale schema before master cleanup'),
    2 => $page('wp next218 stale wp_options root before master cleanup'),
    3 => $page('wp next218 stale active_plugins cache before master cleanup'),
];
$recovered = [
    1 => $formatPage('wp next218 current schema after master cleanup'),
    2 => $page('wp next218 current wp_options root after master cleanup'),
    3 => $page('wp next218 current active_plugins after master cleanup'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2181:size=4096:mtime=21801:generation=main-current',
    $usersJournal => 'dev=8:ino=2182:size=1024:mtime=21802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next218'),
    $usersJournal => hash('sha256', 'wp users rollback header next218'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 218, 0x57503238]));
$masterDigest = hash('sha256', 'wp next218 master source');
$masterToken = 'dev=8:ino=2180:size=96:mtime=21800:generation=master-current';
$databaseToken = 'dev=8:ino=2189:size=1536:mtime=21899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=21900:dirsync=ok';
$oldCleanupToken = 'master-cleanup:exists:mtime=21890:dirsync=pending';
$base = [
    'source_id' => $sourceId,
    'epoch' => 218,
    'format_signature' => $formatSignature,
    'publication_generation' => 218,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 218,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-stale-cleanup', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'master_journal_cleanup_token' => $oldCleanupToken] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 218,
    'format_signature' => $formatSignature,
    'publication_generation' => 218,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 218,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMemberJournalHeaderFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    218,
    218,
    $masterDigest,
    218,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next218',
    'applicationUse' => 'A copied Application database keeps schema/options reader-cache pages only after master-journal cleanup has been durably observed; readers pinned before cleanup reopen before active_plugins is reused.',
    'status' => $plan['status'],
    'cleanupInvalidatedPages' => $plan['master_journal_cleanup_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and VFS cleanup-token evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next218'
    || $summary['cleanupInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next218 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-current-source-next218 self-test passed\n";
