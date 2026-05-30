<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next177.sqlite';
$master = '/srv/wp-content/database/wp-next177.sqlite-mj';
$sourceId = 'pager-reader-cache-header-ticket-next177';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next177-users.sqlite-journal\n";
$headerPage = static function (string $label, int $change, int $size, int $trunk, int $free, int $schema) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('N', $change), 24, 4);
    $page = substr_replace($page, pack('N', $size), 28, 4);
    $page = substr_replace($page, pack('N', $trunk), 32, 4);
    $page = substr_replace($page, pack('N', $free), 36, 4);
    $page = substr_replace($page, pack('N', $schema), 40, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$signature = static fn (int $change, int $size, int $trunk, int $free, int $schema): string => hash('sha256', implode('|', [
    $change,
    $size,
    $trunk,
    $free,
    $schema,
]));

$oldHeaderSignature = $signature(41, 8, 0, 0, 20);
$currentHeaderSignature = $signature(42, 9, 7, 2, 21);
$before = [
    1 => $headerPage('next177 old wp header before master recovery', 41, 8, 0, 0, 20),
    2 => $page('next177 stale wp_options root before header recovery'),
    3 => $page('next177 stale active_plugins before header recovery'),
    4 => $page('next177 unchanged comment index before header recovery'),
    5 => $page('next177 stale plugin settings before header recovery'),
    6 => $page('next177 old autoload index before header recovery'),
    7 => $page('next177 old freelist trunk before header recovery'),
    8 => $page('next177 old optionmeta leaf before header recovery'),
    9 => $page('next177 new recovered page extends db'),
];
$recovered = [
    1 => $headerPage('next177 current wp header after master recovery', 42, 9, 7, 2, 21),
    2 => $page('next177 recovered wp_options root after header recovery'),
    3 => $page('next177 recovered active_plugins after header recovery'),
    5 => $page('next177 recovered plugin settings after header recovery'),
    7 => $page('next177 recovered freelist trunk after header recovery'),
    9 => $page('next177 recovered new database page after header recovery'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 177,
    'reader_id' => $label . '-reader',
    'header_signature' => $currentHeaderSignature,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed', $before[2]),
    3 => $cacheEntry('active-stale-header', $recovered[3], ['header_signature' => $oldHeaderSignature]),
    4 => $cacheEntry('comments-source-mismatch', $before[4], ['source_id' => 'old-source-next177']),
    5 => $cacheEntry('settings-epoch-mismatch', $recovered[5], ['epoch' => 176]),
    6 => $cacheEntry('autoload-dirty', $before[6], ['dirty' => true]),
    7 => $cacheEntry('freelist-pinned-stale', $before[7], ['pinned' => true]),
    8 => $cacheEntry('optionmeta-current-header-stale-image', $before[8]),
    9 => $cacheEntry('new-page-retained', $recovered[9]),
];
$reads = static fn (string $header = null, string $source = null, int $epoch = 177): array => array_map(
    static fn (int $page): array => [
        'reader_id' => 'read-' . $page,
        'page_number' => $page,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'header_signature' => $header ?? $currentHeaderSignature,
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
    int $epoch = 177,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::headerTicketReaderCachePlan(
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next177'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_rechecks_page_one_header_ticket_before_reader_cache_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'members' => [static fn (): mixed => $plan()['current_members'], [$database . '-journal', '/srv/wp-content/database/wp-next177-users.sqlite-journal']],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 177],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-header-ticket:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 178],
    'header change counter' => [static fn (): mixed => $plan()['header_ticket']['change_counter'], 42],
    'header database size' => [static fn (): mixed => $plan()['header_ticket']['database_size'], 9],
    'header trunk' => [static fn (): mixed => $plan()['header_ticket']['first_freelist_trunk'], 7],
    'header freelist count' => [static fn (): mixed => $plan()['header_ticket']['freelist_count'], 2],
    'header schema cookie' => [static fn (): mixed => $plan()['header_ticket']['schema_cookie'], 21],
    'header signature' => [static fn (): mixed => $plan()['header_ticket']['signature'], $currentHeaderSignature],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 5, 7, 9]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 8, 9]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'invalidated count' => [static fn (): mixed => count($plan()['invalidated_entries']), 5],
    'header mismatch reason' => [static fn (): mixed => $row('active-stale-header')['reason'], 'reader_cache_header_signature_mismatch_after_master_recovery'],
    'source mismatch reason' => [static fn (): mixed => $row('comments-source-mismatch')['reason'], 'reader_cache_source_id_mismatch_after_header_ticket'],
    'epoch mismatch reason' => [static fn (): mixed => $row('settings-epoch-mismatch')['reason'], 'reader_cache_epoch_mismatch_after_header_ticket'],
    'dirty reason' => [static fn (): mixed => $row('autoload-dirty')['reason'], 'dirty_reader_cache_cannot_cross_recovered_header_ticket'],
    'pinned reason' => [static fn (): mixed => $row('freelist-pinned-stale')['reason'], 'pinned_reader_cache_image_predates_header_ticket'],
    'retained reason' => [static fn (): mixed => $row('schema-retained')['reason'], 'reader_cache_matches_current_header_ticket_source'],
    'refreshed reason' => [static fn (): mixed => $row('root-refreshed')['reason'], 'reader_cache_refreshed_from_current_header_ticket_source'],
    'unchanged image retained reason' => [static fn (): mixed => $row('optionmeta-current-header-stale-image')['reason'], 'reader_cache_matches_current_header_ticket_source'],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 9],
    'row header mismatch flag' => [static fn (): mixed => $row('active-stale-header')['header_signature_matches'], false],
    'row header match flag' => [static fn (): mixed => $row('schema-retained')['header_signature_matches'], true],
    'row shared flag' => [static fn (): mixed => $row('schema-retained')['shared'], true],
    'row pinned flag' => [static fn (): mixed => $row('freelist-pinned-stale')['pinned'], true],
    'row dirty flag' => [static fn (): mixed => $row('autoload-dirty')['dirty'], true],
    'row cache prefix' => [static fn (): mixed => $row('root-refreshed')['cache_prefix'], 'next177 stale wp_options root before header recovery'],
    'row current prefix' => [static fn (): mixed => $row('root-refreshed')['current_prefix'], 'next177 recovered wp_options root after header recovery'],
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
    'read root refreshed prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next177 recovered wp_options root after header recovery'],
    'read active recovered prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next177 recovered active_plugins after header recovery'],
    'read unchanged prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-4'], 'next177 unchanged comment index before header recovery'],
    'read optionmeta refreshed prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-8'], 'next177 old optionmeta leaf before header recovery'],
    'read new page prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-9'], 'next177 recovered new database page after header recovery'],
    'read source after miss' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-recovered-header-ticket-source-next177'],
    'read retained source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-retained-header-ticket-current-source-next177'],
    'read refreshed source' => [static fn (): mixed => $plan()['next_reads'][1]['source'], 'reader-cache-refreshed-header-ticket-current-source-next177'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'operation first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_header_ticket_reader_cache_next177'],
    'operation restore present' => [static fn (): mixed => $opExists('restore_master_journal_page_before_header_ticket_reader_cache_next177'), true],
    'operation retain present' => [static fn (): mixed => $opExists('retain_reader_cache_after_header_ticket_current_source_next177'), true],
    'operation refresh present' => [static fn (): mixed => $opExists('refresh_reader_cache_from_header_ticket_current_source_next177'), true],
    'operation invalidate present' => [static fn (): mixed => $opExists('invalidate_reader_cache_after_header_ticket_recheck_next177'), true],
    'operation read hit present' => [static fn (): mixed => $opExists('next177_reader_cache_hit_after_header_ticket'), true],
    'operation read miss present' => [static fn (): mixed => $opExists('next177_reader_cache_miss_after_header_ticket'), true],
    'final source page one recovered' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-recovered-header-ticket-source-next177'],
    'final prefix page two recovered' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next177 recovered wp_options root after header recovery'],
    'final bytes contain recovered active' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'recovered active_plugins'), true],
    'final bytes exclude stale active' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale active_plugins'), false],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next177', $plan()['dependencies'], true), true],
    'dependency next174' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next174', $plan()['dependencies'], true), true],
    'dependency header ticket' => [static fn (): mixed => in_array('sqlite-page-one-header-ticket-reader-cache-fence', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next174'), true],
    'stale read header misses all cache' => [static fn (): mixed => $plan(null, null, $reads($oldHeaderSignature))['read_cache_hits']['read-1'], false],
    'stale read source misses all cache' => [static fn (): mixed => $plan(null, null, $reads(null, 'old-source'))['read_cache_hits']['read-1'], false],
    'stale read epoch misses all cache' => [static fn (): mixed => $plan(null, null, $reads(null, null, 176))['read_cache_hits']['read-1'], false],
    'changed header invalidates otherwise matching page' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('schema-old-header', $recovered[1], ['header_signature' => $oldHeaderSignature])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => $currentHeaderSignature]])['invalidated_cache_page_numbers'], [1]],
    'current header stale image refreshes' => [static fn (): mixed => $plan(null, [2 => $cacheEntry('root-refreshed', $before[2])], [['reader_id' => 'read-2', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => $currentHeaderSignature]])['refreshed_cache_page_numbers'], [2]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next177 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
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
    'empty cache header rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['header_signature' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['epoch' => 0])]),
    'cache outside rejected' => static fn () => $plan(null, [10 => $cacheEntry('bad', $page('outside'))]),
    'empty read id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => $currentHeaderSignature]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => $currentHeaderSignature]]),
    'empty read source rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 177, 'header_signature' => $currentHeaderSignature]]),
    'bad read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 0, 'header_signature' => $currentHeaderSignature]]),
    'empty read header rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => '']]),
    'read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 10, 'source_id' => $sourceId, 'epoch' => 177, 'header_signature' => $currentHeaderSignature]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next177 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
