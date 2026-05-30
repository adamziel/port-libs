<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next180.sqlite';
$master = '/srv/wp-content/database/wp-next180.sqlite-mj';
$sourceId = 'pager-reader-cache-format-ticket-next180';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next180-users.sqlite-journal\n";
$formatPage = static function (string $label, int $headerPageSize, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', $headerPageSize), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$signature = static fn (int $headerPageSize, int $reserved, int $encoding, int $userVersion, int $applicationId): string => hash('sha256', implode('|', [
    $headerPageSize,
    $reserved,
    $encoding,
    $userVersion,
    $applicationId,
]));

$oldFormatSignature = $signature(1024, 0, 1, 11, 0x57504f4c);
$currentFormatSignature = $signature(512, 4, 2, 12, 0x57504f50);
$before = [
    1 => $formatPage('next180 old wp header before format recovery', 1024, 0, 1, 11, 0x57504f4c),
    2 => $page('next180 stale wp_options root before format recovery'),
    3 => $page('next180 stale active_plugins before format recovery'),
    4 => $page('next180 unchanged comments page before format recovery'),
    5 => $page('next180 stale plugin settings before format recovery'),
    6 => $page('next180 old autoload index before format recovery'),
    7 => $page('next180 old reserved-bytes overflow before format recovery'),
    8 => $page('next180 unchanged optionmeta leaf before format recovery'),
    9 => $page('next180 new format-compatible page'),
];
$recovered = [
    1 => $formatPage('next180 current wp header after format recovery', 512, 4, 2, 12, 0x57504f50),
    2 => $page('next180 recovered wp_options root after format recovery'),
    3 => $page('next180 recovered active_plugins after format recovery'),
    5 => $page('next180 recovered plugin settings after format recovery'),
    7 => $page('next180 recovered reserved-bytes overflow after format recovery'),
    9 => $page('next180 recovered new format-compatible page'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 180,
    'reader_id' => $label . '-reader',
    'format_signature' => $currentFormatSignature,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('active-stale-format', $recovered[3], ['format_signature' => $oldFormatSignature]),
    4 => $cacheEntry('comments-source-mismatch', $before[4], ['source_id' => 'old-source-next180']),
    5 => $cacheEntry('settings-epoch-mismatch', $recovered[5], ['epoch' => 179]),
    6 => $cacheEntry('autoload-dirty', $before[6], ['dirty' => true]),
    7 => $cacheEntry('reserved-pinned-stale', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-current-format-stale-image', $before[8]),
    9 => $cacheEntry('new-page-retained', $recovered[9]),
];
$reads = static fn (string $format = null, string $source = null, int $epoch = 180): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'format_signature' => $format ?? $currentFormatSignature,
    ],
    range(1, 9),
);
$plan = static fn (
    ?array $recoveredPages = null,
    ?array $readerCache = null,
    ?array $readList = null,
    ?string $masterJournalBytes = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 180,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::formatSignatureReaderCachePlan(
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
);

$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$opExists = static function (string $op) use ($plan): bool {
    foreach ($plan()['operations'] as $operation) {
        if ($operation['op'] === $op) {
            return true;
        }
    }

    return false;
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next180'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_rechecks_page_one_format_ticket_before_reader_cache_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'members' => [static fn (): mixed => $plan()['current_members'], [$database . '-journal', '/srv/wp-content/database/wp-next180-users.sqlite-journal']],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 180],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-format-ticket:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 181],
    'format header page size' => [static fn (): mixed => $plan()['format_ticket']['header_page_size'], 512],
    'format reserved bytes' => [static fn (): mixed => $plan()['format_ticket']['reserved_bytes'], 4],
    'format text encoding' => [static fn (): mixed => $plan()['format_ticket']['text_encoding'], 2],
    'format user version' => [static fn (): mixed => $plan()['format_ticket']['user_version'], 12],
    'format application id' => [static fn (): mixed => $plan()['format_ticket']['application_id'], 0x57504f50],
    'format signature' => [static fn (): mixed => $plan()['format_ticket']['signature'], $currentFormatSignature],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 5, 7, 9]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 8, 9]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'invalidated count' => [static fn (): mixed => count($plan()['invalidated_entries']), 5],
    'format mismatch reason' => [static fn (): mixed => $row('active-stale-format')['reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'source mismatch reason' => [static fn (): mixed => $row('comments-source-mismatch')['reason'], 'reader_cache_source_id_mismatch_after_format_ticket'],
    'epoch mismatch reason' => [static fn (): mixed => $row('settings-epoch-mismatch')['reason'], 'reader_cache_epoch_mismatch_after_format_ticket'],
    'dirty reason' => [static fn (): mixed => $row('autoload-dirty')['reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'pinned reason' => [static fn (): mixed => $row('reserved-pinned-stale')['reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'retained reason' => [static fn (): mixed => $row('schema-retained')['reason'], 'reader_cache_matches_current_format_ticket_source'],
    'refreshed reason' => [static fn (): mixed => $row('root-refreshed')['reason'], 'reader_cache_refreshed_from_current_format_ticket_source'],
    'unchanged image retained reason' => [static fn (): mixed => $row('optionmeta-current-format-stale-image')['reason'], 'reader_cache_matches_current_format_ticket_source'],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 9],
    'row format mismatch flag' => [static fn (): mixed => $row('active-stale-format')['format_signature_matches'], false],
    'row format match flag' => [static fn (): mixed => $row('schema-retained')['format_signature_matches'], true],
    'row shared flag' => [static fn (): mixed => $row('schema-retained')['shared'], true],
    'row pinned flag' => [static fn (): mixed => $row('reserved-pinned-stale')['pinned'], true],
    'row dirty flag' => [static fn (): mixed => $row('autoload-dirty')['dirty'], true],
    'row cache prefix' => [static fn (): mixed => $row('root-refreshed')['cache_prefix'], 'next180 stale wp_options root before format recovery'],
    'row current prefix' => [static fn (): mixed => $row('root-refreshed')['current_prefix'], 'next180 recovered wp_options root after format recovery'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 9],
    'read hits' => [static fn (): mixed => $plan()['read_cache_hits'], [
        'read-1' => true,
        'read-2' => true,
        'read-3' => false,
        'read-4' => false,
        'read-5' => false,
        'read-6' => false,
        'read-7' => false,
        'read-8' => true,
        'read-9' => true,
    ]],
    'read root refreshed prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next180 recovered wp_options root after format recovery'],
    'read active recovered prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next180 recovered active_plugins after format recovery'],
    'read unchanged prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-4'], 'next180 unchanged comments page before format recovery'],
    'read optionmeta prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-8'], 'next180 unchanged optionmeta leaf before format recovery'],
    'read new page prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-9'], 'next180 recovered new format-compatible page'],
    'read source after miss' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-recovered-format-ticket-source-next180'],
    'read retained source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-retained-format-ticket-current-source-next180'],
    'read refreshed source' => [static fn (): mixed => $plan()['next_reads'][1]['source'], 'reader-cache-refreshed-format-ticket-current-source-next180'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_format_ticket_reader_cache_next180'],
    'operation restore present' => [static fn (): mixed => $opExists('restore_master_journal_page_before_format_ticket_reader_cache_next180'), true],
    'operation retain present' => [static fn (): mixed => $opExists('retain_reader_cache_after_format_ticket_current_source_next180'), true],
    'operation refresh present' => [static fn (): mixed => $opExists('refresh_reader_cache_from_format_ticket_current_source_next180'), true],
    'operation invalidate present' => [static fn (): mixed => $opExists('invalidate_reader_cache_after_format_ticket_recheck_next180'), true],
    'operation read hit present' => [static fn (): mixed => $opExists('next180_reader_cache_hit_after_format_ticket'), true],
    'operation read miss present' => [static fn (): mixed => $opExists('next180_reader_cache_miss_after_format_ticket'), true],
    'final source page one recovered' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-recovered-format-ticket-source-next180'],
    'final prefix page two recovered' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next180 recovered wp_options root after format recovery'],
    'final bytes contain recovered active' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'recovered active_plugins'), true],
    'final bytes exclude stale active' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale active_plugins'), false],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next180', $plan()['dependencies'], true), true],
    'dependency next177' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next177', $plan()['dependencies'], true), true],
    'dependency format ticket' => [static fn (): mixed => in_array('sqlite-page-one-format-ticket-reader-cache-fence', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next177'), true],
    'stale read format misses all cache' => [static fn (): mixed => $plan(null, null, $reads($oldFormatSignature))['read_cache_hits']['read-1'], false],
    'stale read source misses all cache' => [static fn (): mixed => $plan(null, null, $reads(null, 'old-source'))['read_cache_hits']['read-1'], false],
    'stale read epoch misses all cache' => [static fn (): mixed => $plan(null, null, $reads(null, null, 179))['read_cache_hits']['read-1'], false],
    'changed format invalidates otherwise matching page' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('schema-old-format', $recovered[1], ['format_signature' => $oldFormatSignature])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $currentFormatSignature]])['invalidated_cache_page_numbers'], [1]],
    'current format stale image refreshes' => [static fn (): mixed => $plan(null, [2 => $cacheEntry('root-refreshed', $before[2])], [['reader_id' => 'read-2', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $currentFormatSignature]])['refreshed_cache_page_numbers'], [2]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next180 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'blank master rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
    'empty recovered rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, 0),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered page rejected' => static fn () => $plan([1 => 'short']),
    'recovered outside rejected' => static fn () => $plan([10 => $page('outside')]),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cacheEntry('bad', $recovered[1])]),
    'short cache image rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['source_id' => ''])]),
    'empty cache reader rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['reader_id' => ''])]),
    'empty cache format rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['format_signature' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['epoch' => 0])]),
    'cache outside rejected' => static fn () => $plan(null, [10 => $cacheEntry('bad', $page('outside'))]),
    'empty read id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $currentFormatSignature]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $currentFormatSignature]]),
    'empty read source rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 180, 'format_signature' => $currentFormatSignature]]),
    'bad read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 0, 'format_signature' => $currentFormatSignature]]),
    'empty read format rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => '']]),
    'read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 10, 'source_id' => $sourceId, 'epoch' => 180, 'format_signature' => $currentFormatSignature]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next180 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
