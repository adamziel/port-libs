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
    $page = substr_replace($page, pack('N', 260), 60, 4);

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
    1 => $formatPage('stale schema before page current-source-reader-ticket'),
    2 => $page('stale wp_options root before page current-source-reader-ticket'),
    3 => $page('stale active_plugins cache before page current-source-reader-ticket'),
];
$recovered = [
    1 => $formatPage('current schema after page current-source-reader-ticket'),
    2 => $page('current wp_options root after page current-source-reader-ticket'),
    3 => $page('current active_plugins cache after page current-source-reader-ticket'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2601:size=4096:mtime=26001:generation=main-current',
    $usersJournal => 'dev=8:ino=2602:size=1024:mtime=26002:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next260'),
    $usersJournal => hash('sha256', 'users rollback header next260'),
];
$receipt = 'master-journal-recovery-receipt:epoch=260:members=main,users:cleanup=deleted:dirsync=ok';
$checksum = 'recovered-page-checksum-receipt:epoch=260:digest=' . $recoveredDigest($recovered);
$ticket = 'current-source-reader-ticket:epoch=260:digest=' . $recoveredDigest($recovered);
$oldTicket = 'current-source-reader-ticket:epoch=256:digest=' . $recoveredDigest($before);
$snapshot = 'reader-snapshot:epoch=260:source=master-current:lease=shared:pages=1,2,3';
$generation = 'pager-reader-cache-generation:epoch=260:master-current:reset=complete';
$source = 'current-source:master-journal:epoch=260:members=2:schema=116';
$base = [
    'source_id' => 'application-pager-current-source-reader-ticket-receipt-smoke',
    'epoch' => 260,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 260, 0])),
    'publication_generation' => 260,
    'master_source_digest' => hash('sha256', 'application next260 master source'),
    'recovery_sequence' => 260,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
    'master_journal_file_token' => 'dev=8:ino=2600:size=96:mtime=26000:generation=master-current',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'dev=8:ino=2609:size=1536:mtime=26099:generation=database-current',
    'master_journal_cleanup_token' => 'master-cleanup:deleted:mtime=25800:dirsync=ok',
    'reader_lease_token' => 'reader-lease:shared-cache:epoch=260:opened-after-master-cleanup',
    'pager_cache_source_token' => 'pager-cache-source:epoch=260:master-journal-recovery=complete',
    'read_transaction_token' => 'read-transaction:epoch=260:schema=116:change-counter=260:master-current',
    'schema_reparse_token' => 'schema-reparse:epoch=260:schema-cookie=116:ddl=master-current',
    'statement_schema_root_token' => 'statement-schema-root:epoch=260:root=1:cookie=116:sql=wp-options-current',
    'current_source_provenance_token' => $source,
    'pager_reader_cache_generation_token' => $generation,
    'reader_snapshot_token' => $snapshot,
    'master_journal_recovery_receipt_token' => $receipt,
    'recovered_page_checksum_receipt_token' => $checksum,
];
$cacheEntry = static fn (string $label, string $image, string $token): array => $base + [
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
    'current_source_reader_ticket_token' => $token,
];
$read = static fn (int $pageNumber, string $token): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 260,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 260,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 260,
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
    'recovered_page_checksum_receipt_token' => $checksum,
    'current_source_reader_ticket_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderTicketFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-current', $recovered[1], $ticket),
        2 => $cacheEntry('options-root-stale-current-source-reader-ticket', $recovered[2], $oldTicket),
        3 => $cacheEntry('active-plugins-current', $recovered[3], $ticket),
    ],
    [$read(1, $ticket), $read(2, $ticket), $read(3, $ticket)],
    $base['source_id'],
    260,
    260,
    $base['master_source_digest'],
    260,
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
    $ticket,
);

$summary = [
    'status' => $plan['status'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'current-source-reader-ticket_invalidated_cache_page_numbers' => $plan['current_source_reader_ticket_invalidated_cache_page_numbers'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'read_cache_hits' => $plan['read_cache_hits'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next260'
        || $summary['current-source-reader-ticket_invalidated_cache_page_numbers'] !== [2]
        || $summary['read_cache_hits']['read-2'] !== false
        || $summary['reopen_reader_ids'] !== ['read-2']
    ) {
        fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next260 self-test failed\n");
        exit(1);
    }

    echo "application-pager-master-journal-reader-cache-current-source-next260 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
