<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next183.sqlite';
$master = '/srv/wp-content/database/wp-next183.sqlite-mj';
$sourceId = 'pager-reader-cache-publication-next183';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next183-users.sqlite-journal\n";
$currentPublication = 183;
$currentMasterDigest = hash('sha256', 'next183-current-master-source');
$oldMasterDigest = hash('sha256', 'next183-old-master-source');
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
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 13, 0x57504f50]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 12, 0x57504f4c]));
$before = [
    1 => $formatPage('next183 old wp header before publication recovery', 0, 1, 12, 0x57504f4c),
    2 => $page('next183 stale wp_options root before publication recovery'),
    3 => $page('next183 stale active_plugins before publication recovery'),
    4 => $page('next183 unchanged comments before publication recovery'),
    5 => $page('next183 stale plugin settings before publication recovery'),
    6 => $page('next183 stale transient before publication recovery'),
    7 => $page('next183 stale cron before publication recovery'),
    8 => $page('next183 unchanged optionmeta before publication recovery'),
];
$recovered = [
    1 => $formatPage('next183 current wp header after publication recovery', 4, 2, 13, 0x57504f50),
    2 => $page('next183 recovered wp_options root after publication recovery'),
    3 => $page('next183 recovered active_plugins after publication recovery'),
    5 => $page('next183 recovered plugin settings after publication recovery'),
    7 => $page('next183 recovered cron after publication recovery'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 183,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $currentPublication,
    'master_source_digest' => $currentMasterDigest,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-publication', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed-publication', $before[2]),
    3 => $cacheEntry('active-stale-master-digest', $recovered[3], ['master_source_digest' => $oldMasterDigest]),
    4 => $cacheEntry('comments-stale-publication-generation', $before[4], ['publication_generation' => 182]),
    5 => $cacheEntry('settings-stale-format', $recovered[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('transient-dirty-publication', $before[6], ['dirty' => true]),
    7 => $cacheEntry('cron-pinned-stale-publication', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-retained-publication', $before[8]),
];
$reads = static fn (int $publication = null, string $digest = null, string $source = null, int $epoch = 183): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication ?? $currentPublication,
        'master_source_digest' => $digest ?? $currentMasterDigest,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $recoveredPages = null,
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $publication = null,
    ?string $digest = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 183,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext183(
    $path ?? $database,
    $masterPath ?? $master,
    $masterJournalBytes ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $source ?? $sourceId,
    $epoch,
    $publication ?? $currentPublication,
    $digest ?? $currentMasterDigest,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next183'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_publication_generation_fences_current_source_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'current publication generation' => [static fn (): mixed => $plan()['current_publication_generation'], $currentPublication],
    'current master source digest' => [static fn (): mixed => $plan()['current_master_source_digest'], $currentMasterDigest],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 8]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'publication invalidated pages' => [static fn (): mixed => $plan()['publication_invalidated_cache_page_numbers'], [3, 4]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row retained publication admitted' => [static fn (): mixed => $row('schema-retained-publication')['publication_admitted'], true],
    'row retained publication reason' => [static fn (): mixed => $row('schema-retained-publication')['publication_reason'], 'reader_cache_publication_matches_current_source'],
    'row refreshed publication admitted' => [static fn (): mixed => $row('root-refreshed-publication')['publication_admitted'], true],
    'row stale digest rejected' => [static fn (): mixed => $row('active-stale-master-digest')['publication_reason'], 'reader_cache_master_source_digest_predates_publication_source'],
    'row stale generation rejected' => [static fn (): mixed => $row('comments-stale-publication-generation')['publication_reason'], 'reader_cache_publication_generation_predates_current_source'],
    'row stale format carries base reason' => [static fn (): mixed => $row('settings-stale-format')['publication_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'row dirty carries base reason' => [static fn (): mixed => $row('transient-dirty-publication')['publication_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'row pinned carries base reason' => [static fn (): mixed => $row('cron-pinned-stale-publication')['publication_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'row digest mismatch flag' => [static fn (): mixed => $row('active-stale-master-digest')['master_source_digest_matches'], false],
    'row generation before' => [static fn (): mixed => $row('comments-stale-publication-generation')['cache_publication_generation'], 182],
    'row generation current' => [static fn (): mixed => $row('comments-stale-publication-generation')['current_publication_generation'], $currentPublication],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read stale digest miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read stale generation miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read retained publication current' => [static fn (): mixed => $plan()['next_reads'][0]['publication_current'], true],
    'read stale digest source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-publication-fence-current-source-next183'],
    'read stale generation reason' => [static fn (): mixed => $plan()['next_reads'][3]['publication_reason'], 'reader_cache_publication_reopened_after_master_source_change'],
    'read root prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next183 recovered wp_options root after publication recovery'],
    'read active prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next183 recovered active_plugins after publication recovery'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation publication invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_publication_after_master_current_source_next183', array_column($plan()['operations'], 'op'), true), true],
    'operation publication invalidate count' => [static fn (): mixed => count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_publication_after_master_current_source_next183')), 2],
    'publication operation reader id' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_publication_after_master_current_source_next183'))[0]['reader_id'], 'read-3'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next183', $plan()['dependencies'], true), true],
    'dependency publication fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-publication-generation-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next180', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next180'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'stale read publication misses retained cache' => [static fn (): mixed => $plan(null, null, $reads(182))['read_cache_hits']['read-1'], false],
    'stale read digest misses retained cache' => [static fn (): mixed => $plan(null, null, $reads(null, $oldMasterDigest))['read_cache_hits']['read-1'], false],
    'all publication current has no publication invalidation' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('schema-retained-publication', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest]])['publication_invalidated_cache_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next183 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad publication rejected' => static fn () => $plan(null, null, null, 0),
    'empty current digest rejected' => static fn () => $plan(null, null, null, null, ''),
    'bad cache publication rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['publication_generation' => 0])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest]]),
    'empty cache master digest rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['master_source_digest' => ''])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest]]),
    'bad read publication rejected' => static fn () => $plan(null, null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => 0, 'master_source_digest' => $currentMasterDigest]]),
    'empty read digest rejected' => static fn () => $plan(null, null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => '']]),
    'base wrong master rejected' => static fn () => $plan(null, null, null, null, null, '/tmp/other.sqlite-journal'),
    'base read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 9, 'source_id' => $sourceId, 'epoch' => 183, 'format_signature' => $formatSignature, 'publication_generation' => $currentPublication, 'master_source_digest' => $currentMasterDigest]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next183 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
