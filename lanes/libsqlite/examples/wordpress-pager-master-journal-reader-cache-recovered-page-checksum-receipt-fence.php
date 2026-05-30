<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

foreach (glob(__DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext*Plan.php') ?: [] as $file) {
    require_once $file;
}

$pageSize = 512;
$database = '/srv/wp-content/database/wp-options.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 257), 60, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
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

$before = [
    1 => $formatPage('stale schema before page checksum receipt'),
    2 => $page('stale wp_options root before page checksum receipt'),
    3 => $page('stale active_plugins cache before page checksum receipt'),
];
$recovered = [
    1 => $formatPage('current schema after page checksum receipt'),
    2 => $page('current wp_options root after page checksum receipt'),
    3 => $page('current active_plugins cache after page checksum receipt'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2571:size=4096:mtime=25701:generation=main-current',
    $usersJournal => 'dev=8:ino=2572:size=1024:mtime=25702:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next257'),
    $usersJournal => hash('sha256', 'users rollback header next257'),
];
$receipt = 'master-journal-recovery-receipt:epoch=257:members=main,users:cleanup=deleted:dirsync=ok';
$checksum = 'recovered-page-checksum-receipt:epoch=257:digest=' . $recoveredDigest($recovered);
$oldChecksum = 'recovered-page-checksum-receipt:epoch=256:digest=' . $recoveredDigest($before);
$snapshot = 'reader-snapshot:epoch=257:source=master-current:lease=shared:pages=1,2,3';
$generation = 'pager-reader-cache-generation:epoch=257:master-current:reset=complete';
$source = 'current-source:master-journal:epoch=257:members=2:schema=116';
$base = [
    'source_id' => 'wordpress-pager-checksum-receipt-smoke',
    'epoch' => 257,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 257, 0])),
    'publication_generation' => 257,
    'master_source_digest' => hash('sha256', 'wordpress next257 master source'),
    'recovery_sequence' => 257,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
    'master_journal_file_token' => 'dev=8:ino=2570:size=96:mtime=25700:generation=master-current',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'dev=8:ino=2579:size=1536:mtime=25799:generation=database-current',
    'master_journal_cleanup_token' => 'master-cleanup:deleted:mtime=25800:dirsync=ok',
    'reader_lease_token' => 'reader-lease:shared-cache:epoch=257:opened-after-master-cleanup',
    'pager_cache_source_token' => 'pager-cache-source:epoch=257:master-journal-recovery=complete',
    'read_transaction_token' => 'read-transaction:epoch=257:schema=116:change-counter=257:master-current',
    'schema_reparse_token' => 'schema-reparse:epoch=257:schema-cookie=116:ddl=master-current',
    'statement_schema_root_token' => 'statement-schema-root:epoch=257:root=1:cookie=116:sql=wp-options-current',
    'current_source_provenance_token' => $source,
    'pager_reader_cache_generation_token' => $generation,
    'reader_snapshot_token' => $snapshot,
    'master_journal_recovery_receipt_token' => $receipt,
];
$cacheEntry = static fn (string $label, string $image, string $token): array => $base + [
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
    'recovered_page_checksum_receipt_token' => $token,
];
$read = static fn (int $pageNumber, string $token): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 257,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 257,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 257,
    'recovered_page_set_digest' => $base['recovered_page_set_digest'],
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => $base['master_member_order_digest'],
    'master_journal_file_token' => $base['master_journal_file_token'],
    'master_journal_bytes_digest' => $base['master_journal_bytes_digest'],
    'database_file_token' => $base['database_file_token'],
    'master_journal_cleanup_token' => $base['master_journal_cleanup_token'],
    'reader_lease_token' => $base['reader_lease_token'],
    'pager_cache_source_token' => $base['pager_cache_source_token'],
    'read_transaction_token' => $base['read_transaction_token'],
    'schema_reparse_token' => $base['schema_reparse_token'],
    'statement_schema_root_token' => $base['statement_schema_root_token'],
    'current_source_provenance_token' => $source,
    'pager_reader_cache_generation_token' => $generation,
    'reader_snapshot_token' => $snapshot,
    'master_journal_recovery_receipt_token' => $receipt,
    'recovered_page_checksum_receipt_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::recoveredPageChecksumReceiptFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-current', $recovered[1], $checksum),
        2 => $cacheEntry('options-root-stale-checksum', $recovered[2], $oldChecksum),
        3 => $cacheEntry('active-plugins-current', $recovered[3], $checksum),
    ],
    [$read(1, $checksum), $read(2, $checksum), $read(3, $checksum)],
    $base['source_id'],
    257,
    257,
    $base['master_source_digest'],
    257,
    $tokens,
    $headers,
    $base['master_journal_file_token'],
    $base['database_file_token'],
    $base['master_journal_cleanup_token'],
    $base['reader_lease_token'],
    $base['pager_cache_source_token'],
    $base['read_transaction_token'],
    $base['schema_reparse_token'],
    $base['statement_schema_root_token'],
    $source,
    $generation,
    $snapshot,
    $receipt,
    $checksum,
);

$summary = [
    'status' => $plan['status'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'checksum_invalidated_cache_page_numbers' => $plan['recovered_page_checksum_receipt_invalidated_cache_page_numbers'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'read_cache_hits' => $plan['read_cache_hits'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next257'
        || $summary['checksum_invalidated_cache_page_numbers'] !== [2]
        || $summary['read_cache_hits']['read-2'] !== false
        || $summary['reopen_reader_ids'] !== ['read-2']
    ) {
        fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next257 self-test failed\n");
        exit(1);
    }

    echo "wordpress-pager-master-journal-reader-cache-current-source-next257 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
