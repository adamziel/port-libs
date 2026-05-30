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
    $page = substr_replace($page, pack('N', 254), 60, 4);

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
    1 => $formatPage('stale schema before master journal receipt'),
    2 => $page('stale wp_options root before master journal receipt'),
    3 => $page('stale active_plugins cache before master journal receipt'),
];
$recovered = [
    1 => $formatPage('current schema after master journal receipt'),
    2 => $page('current wp_options root after master journal receipt'),
    3 => $page('current active_plugins cache after master journal receipt'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2541:size=4096:mtime=25401:generation=main-current',
    $usersJournal => 'dev=8:ino=2542:size=1024:mtime=25402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next254'),
    $usersJournal => hash('sha256', 'users rollback header next254'),
];
$receipt = 'master-journal-recovery-receipt:epoch=254:members=main,users:cleanup=deleted:dirsync=ok';
$oldReceipt = 'master-journal-recovery-receipt:epoch=253:members=main,users:cleanup=pending';
$snapshot = 'reader-snapshot:epoch=254:source=master-current:lease=shared:pages=1,2,3';
$generation = 'pager-reader-cache-generation:epoch=254:master-current:reset=complete';
$source = 'current-source:master-journal:epoch=254:members=2:schema=113';
$base = [
    'source_id' => 'wordpress-pager-receipt-smoke',
    'epoch' => 254,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 254, 0])),
    'publication_generation' => 254,
    'master_source_digest' => hash('sha256', 'wordpress next254 master source'),
    'recovery_sequence' => 254,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
    'master_journal_file_token' => 'dev=8:ino=2540:size=96:mtime=25400:generation=master-current',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'dev=8:ino=2549:size=1536:mtime=25499:generation=database-current',
    'master_journal_cleanup_token' => 'master-cleanup:deleted:mtime=25500:dirsync=ok',
    'reader_lease_token' => 'reader-lease:shared-cache:epoch=254:opened-after-master-cleanup',
    'pager_cache_source_token' => 'pager-cache-source:epoch=254:master-journal-recovery=complete',
    'read_transaction_token' => 'read-transaction:epoch=254:schema=113:change-counter=254:master-current',
    'schema_reparse_token' => 'schema-reparse:epoch=254:schema-cookie=113:ddl=master-current',
    'statement_schema_root_token' => 'statement-schema-root:epoch=254:root=1:cookie=113:sql=wp-options-current',
    'current_source_provenance_token' => $source,
    'pager_reader_cache_generation_token' => $generation,
    'reader_snapshot_token' => $snapshot,
];
$cacheEntry = static fn (string $label, string $image, string $token): array => $base + [
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
    'master_journal_recovery_receipt_token' => $token,
];
$read = static fn (int $pageNumber, string $token): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 254,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 254,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 254,
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
    'master_journal_recovery_receipt_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalRecoveryReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-current', $recovered[1], $receipt),
        2 => $cacheEntry('options-root-stale-receipt', $before[2], $oldReceipt),
        3 => $cacheEntry('active-plugins-current', $recovered[3], $receipt),
    ],
    [$read(1, $receipt), $read(2, $receipt), $read(3, $receipt)],
    $base['source_id'],
    254,
    254,
    $base['master_source_digest'],
    254,
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
);

echo json_encode([
    'status' => $plan['status'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'read_cache_hits' => $plan['read_cache_hits'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
