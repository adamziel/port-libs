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
    $page = substr_replace($page, pack('N', 258), 60, 4);

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
    1 => $formatPage('stale schema before pager spill drain'),
    2 => $page('stale wp_options root before pager spill drain'),
    3 => $page('stale active_plugins before pager spill drain'),
];
$recovered = [
    1 => $formatPage('current schema after pager spill drain'),
    2 => $page('current wp_options root after pager spill drain'),
    3 => $page('current active_plugins after pager spill drain'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2581:size=4096:mtime=25801:generation=main-current',
    $usersJournal => 'dev=8:ino=2582:size=1024:mtime=25802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main rollback header next258'),
    $usersJournal => hash('sha256', 'users rollback header next258'),
];
$receipt = 'master-journal-recovery-receipt:epoch=258:members=main,users:cleanup=deleted:dirsync=ok';
$spill = 'pager-spill-drain:epoch=258:dirty-pages=2:journal-sync=ok:exclusive-lock=held';
$oldSpill = 'pager-spill-drain:epoch=257:dirty-pages=2:journal-sync=pending';
$snapshot = 'reader-snapshot:epoch=258:source=master-current:lease=shared:pages=1,2,3';
$generation = 'pager-reader-cache-generation:epoch=258:master-current:reset=complete';
$source = 'current-source:master-journal:epoch=258:members=2:schema=117';
$base = [
    'source_id' => 'wordpress-pager-spill-drain-smoke',
    'epoch' => 258,
    'format_signature' => hash('sha256', implode('|', [512, 4, 2, 258, 0])),
    'publication_generation' => 258,
    'master_source_digest' => hash('sha256', 'wordpress next258 master source'),
    'recovery_sequence' => 258,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", [$mainJournal, $usersJournal])),
    'master_journal_file_token' => 'dev=8:ino=2580:size=96:mtime=25800:generation=master-current',
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => 'dev=8:ino=2589:size=1536:mtime=25899:generation=database-current',
    'master_journal_cleanup_token' => 'master-cleanup:deleted:mtime=25900:dirsync=ok',
    'reader_lease_token' => 'reader-lease:shared-cache:epoch=258:opened-after-master-cleanup',
    'pager_cache_source_token' => 'pager-cache-source:epoch=258:master-journal-recovery=complete',
    'read_transaction_token' => 'read-transaction:epoch=258:schema=117:change-counter=258:master-current',
    'schema_reparse_token' => 'schema-reparse:epoch=258:schema-cookie=117:ddl=master-current',
    'statement_schema_root_token' => 'statement-schema-root:epoch=258:root=1:cookie=117:sql=wp-options-current',
    'current_source_provenance_token' => $source,
    'pager_reader_cache_generation_token' => $generation,
    'reader_snapshot_token' => $snapshot,
    'master_journal_recovery_receipt_token' => $receipt,
];
$cacheEntry = static fn (string $label, string $image, string $token): array => $base + [
    'label' => $label,
    'reader_id' => $label . '-reader',
    'image' => $image,
    'pager_spill_drain_token' => $token,
];
$read = static fn (int $pageNumber, string $token): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $base['source_id'],
    'epoch' => 258,
    'format_signature' => $base['format_signature'],
    'publication_generation' => 258,
    'master_source_digest' => $base['master_source_digest'],
    'recovery_sequence' => 258,
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
    'pager_spill_drain_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::pagerSpillDrainFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-current', $recovered[1], $spill),
        2 => $cacheEntry('options-root-stale-spill', $before[2], $oldSpill),
        3 => $cacheEntry('active-plugins-current', $recovered[3], $spill),
    ],
    [$read(1, $spill), $read(2, $spill), $read(3, $spill)],
    $base['source_id'],
    258,
    258,
    $base['master_source_digest'],
    258,
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
    $spill,
);

if (in_array('--self-test', $argv, true)) {
    if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next258') {
        fwrite(STDERR, "unexpected pager spill-drain status\n");
        exit(1);
    }
    if ($plan['pager_spill_drain_invalidated_cache_page_numbers'] !== [2]) {
        fwrite(STDERR, "unexpected pager spill-drain invalidation set\n");
        exit(1);
    }
    if (($plan['read_cache_hits']['read-2'] ?? true) !== false) {
        fwrite(STDERR, "expected options root reader to reopen after stale spill drain\n");
        exit(1);
    }
    echo "wordpress-pager-master-journal-reader-cache-current-source-next258 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pager_spill_drain_invalidated_cache_page_numbers' => $plan['pager_spill_drain_invalidated_cache_page_numbers'],
    'invalidated_cache_page_numbers' => $plan['invalidated_cache_page_numbers'],
    'reopen_reader_ids' => $plan['reopen_reader_ids'],
    'read_cache_hits' => $plan['read_cache_hits'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, recovery receipt, reader snapshot, and dirty-page spill-drain tokens',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
