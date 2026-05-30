<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next186.sqlite';
$master = '/srv/wp-content/database/wp-next186.sqlite-mj';
$sourceId = 'pager-reader-cache-recovery-set-next186';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next186-users.sqlite-journal\n";
$currentPublication = 186;
$currentMasterDigest = hash('sha256', 'next186-current-master-source');
$recoverySequence = 42;
$oldRecoveryDigest = hash('sha256', 'next186-prior-recovered-page-set');
$formatPage = static function (string $label, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 16, 0x57504f53]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 15, 0x57504f52]));
$before = [
    1 => $formatPage('next186 old wp header before recovery set', 0, 1, 15, 0x57504f52),
    2 => $page('next186 stale wp_options root before recovery set'),
    3 => $page('next186 stale active_plugins before recovery set'),
    4 => $page('next186 stale rewrite rules before recovery set'),
    5 => $page('next186 stale plugin settings before recovery set'),
    6 => $page('next186 stale transient before recovery set'),
    7 => $page('next186 stale cron before recovery set'),
    8 => $page('next186 unchanged optionmeta before recovery set'),
];
$recovered = [
    1 => $formatPage('next186 current wp header after recovery set', 4, 2, 16, 0x57504f53),
    2 => $page('next186 recovered wp_options root after recovery set'),
    3 => $page('next186 recovered active_plugins after recovery set'),
    5 => $page('next186 recovered plugin settings after recovery set'),
    7 => $page('next186 recovered cron after recovery set'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $number => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad page fixture');
        }
        $parts[] = $number . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$currentRecoveredDigest = $recoveredDigest($recovered);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 186,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $currentPublication,
    'master_source_digest' => $currentMasterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-recovery-set', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed-recovery-set', $before[2]),
    3 => $cacheEntry('active-stale-recovery-sequence', $recovered[3], ['recovery_sequence' => 41]),
    4 => $cacheEntry('rewrite-stale-recovered-page-set', $before[4], ['recovered_page_set_digest' => $oldRecoveryDigest]),
    5 => $cacheEntry('settings-stale-format', $recovered[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('transient-dirty-recovery-set', $before[6], ['dirty' => true]),
    7 => $cacheEntry('cron-pinned-stale-recovery-set', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-retained-recovery-set', $before[8]),
];
$reads = static fn (int $sequence = null, string $digest = null, string $source = null, int $epoch = 186): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'format_signature' => $formatSignature,
        'publication_generation' => $currentPublication,
        'master_source_digest' => $currentMasterDigest,
        'recovery_sequence' => $sequence ?? $recoverySequence,
        'recovered_page_set_digest' => $digest ?? $currentRecoveredDigest,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $recoveredPages = null,
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $sequence = null,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planRecoverySequenceFence(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    186,
    $currentPublication,
    $currentMasterDigest,
    $sequence ?? $recoverySequence,
);

$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next186'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_recovered_page_set_sequence_fences_current_source_reuse'],
    'current recovery sequence' => [static fn (): mixed => $plan()['current_recovery_sequence'], $recoverySequence],
    'current recovered page set digest' => [static fn (): mixed => $plan()['current_recovered_page_set_digest'], $currentRecoveredDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 8]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'recovery invalidated pages' => [static fn (): mixed => $plan()['recovery_invalidated_cache_page_numbers'], [3, 4]],
    'publication invalidations unchanged' => [static fn (): mixed => $plan()['publication_invalidated_cache_page_numbers'], []],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row retained recovery admitted' => [static fn (): mixed => $row('schema-retained-recovery-set')['recovery_admitted'], true],
    'row retained recovery reason' => [static fn (): mixed => $row('schema-retained-recovery-set')['recovery_reason'], 'reader_cache_recovery_sequence_matches_current_source'],
    'row refreshed recovery admitted' => [static fn (): mixed => $row('root-refreshed-recovery-set')['recovery_admitted'], true],
    'row stale sequence rejected' => [static fn (): mixed => $row('active-stale-recovery-sequence')['recovery_reason'], 'reader_cache_recovery_sequence_predates_master_journal_source'],
    'row stale digest rejected' => [static fn (): mixed => $row('rewrite-stale-recovered-page-set')['recovery_reason'], 'reader_cache_recovered_page_set_digest_predates_current_source'],
    'row stale format carries base reason' => [static fn (): mixed => $row('settings-stale-format')['recovery_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row dirty carries base reason' => [static fn (): mixed => $row('transient-dirty-recovery-set')['recovery_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'row pinned carries base reason' => [static fn (): mixed => $row('cron-pinned-stale-recovery-set')['recovery_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'row recovered page digest mismatch flag' => [static fn (): mixed => $row('rewrite-stale-recovered-page-set')['recovered_page_set_digest_matches'], false],
    'row sequence before' => [static fn (): mixed => $row('active-stale-recovery-sequence')['cache_recovery_sequence'], 41],
    'row sequence current' => [static fn (): mixed => $row('active-stale-recovery-sequence')['current_recovery_sequence'], $recoverySequence],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read stale sequence miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read stale digest miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read retained recovery current' => [static fn (): mixed => $plan()['next_reads'][0]['recovery_current'], true],
    'read stale sequence source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-recovered-page-set-fence-current-source-next186'],
    'read stale digest reason' => [static fn (): mixed => $plan()['next_reads'][3]['recovery_reason'], 'reader_cache_reopened_after_recovered_page_set_change'],
    'read root prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next186 recovered wp_options root after recovery set'],
    'read active prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next186 recovered active_plugins after recovery set'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation recovery invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_recovered_page_set_after_master_current_source_next186', array_column($plan()['operations'], 'op'), true), true],
    'operation recovery invalidate count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_recovered_page_set_after_master_current_source_next186')), 2],
    'recovery operation reader id' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_recovered_page_set_after_master_current_source_next186'))[0]['reader_id'], 'read-3'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next186', $plan()['dependencies'], true), true],
    'dependency recovery fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-recovered-page-set-sequence-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next183', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next183'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'stale read sequence misses retained cache' => [static fn (): mixed => $plan(null, null, $reads(41))['read_cache_hits']['read-1'], false],
    'stale read recovered digest misses retained cache' => [static fn (): mixed => $plan(null, null, $reads(null, $oldRecoveryDigest))['read_cache_hits']['read-1'], false],
    'all recovery current has no recovery invalidation' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('schema-retained-recovery-set', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest]])['recovery_invalidated_cache_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next186 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad recovery sequence rejected' => static fn () => $plan(null, null, null, 0),
    'bad recovered page image rejected' => static fn () => $plan([1 => 'short']),
    'bad cache sequence rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['recovery_sequence' => 0])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest]]),
    'empty cache recovered page digest rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['recovered_page_set_digest' => ''])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest]]),
    'bad read sequence rejected' => static fn () => $plan(null, null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => 0, 'recovered_page_set_digest' => $currentRecoveredDigest]]),
    'empty read recovered digest rejected' => static fn () => $plan(null, null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => '']]),
    'base read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 9, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest]]),
    'base unaligned database rejected' => static fn () => $plan(null, null, null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next186 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
