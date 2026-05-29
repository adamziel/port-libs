<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$main = '/srv/wp-content/database/wp-next172.sqlite';
$meta = '/srv/wp-content/database/wp-next172-meta.sqlite';
$comments = '/srv/wp-content/database/wp-next172-comments.sqlite';
$missing = '/srv/wp-content/database/wp-next172-old.sqlite';
$master = '/srv/wp-content/database/wp-next172.sqlite-mj';
$masterBytes = $main . "-journal\n" . $meta . "-journal\n" . $comments . "-journal\n";
$masterDigest = hash('sha256', $masterBytes);
$sourceId = 'pager-master-reader-cache-next172';
$epoch = 172;

$currentPages = [
    $main => [
        1 => $page('next172 main schema page'),
        2 => $page('next172 main active_plugins option'),
        3 => $page('next172 main transient timeout'),
    ],
    $meta => [
        1 => $page('next172 meta schema page'),
        2 => $page('next172 meta user capabilities'),
        3 => $page('next172 meta stale image current'),
    ],
    $comments => [
        1 => $page('next172 comments schema page'),
        2 => $page('next172 comments approved index'),
    ],
];

$cache = [
    $main => [
        1 => ['image' => $currentPages[$main][1], 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'main-schema'],
        2 => ['image' => $currentPages[$main][2], 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'main-active'],
        3 => ['image' => $currentPages[$meta][2], 'database_path' => $meta, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'wrong-slot'],
    ],
    $meta => [
        1 => ['image' => $currentPages[$meta][1], 'source_id' => $sourceId, 'epoch' => 171, 'master_digest' => $masterDigest, 'reader_id' => 'meta-stale-epoch'],
        2 => ['image' => $currentPages[$meta][2], 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => str_repeat('0', 64), 'reader_id' => 'meta-stale-digest'],
        3 => ['image' => $page('next172 stale meta image'), 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'meta-stale-image'],
    ],
    $comments => [
        1 => ['image' => $currentPages[$comments][1], 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'comments-dirty', 'dirty' => true],
        2 => ['image' => $currentPages[$comments][2], 'source_id' => 'old-source', 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'comments-old-source'],
    ],
    $missing => [
        1 => ['image' => $page('next172 removed database cache'), 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'removed-db'],
    ],
];

$reads = [
    ['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
    ['reader_id' => 'main-active-read', 'database_path' => $main, 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
    ['reader_id' => 'meta-cap-read', 'database_path' => $meta, 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
    ['reader_id' => 'meta-image-read', 'database_path' => $meta, 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
    ['reader_id' => 'comments-index-read', 'database_path' => $comments, 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
];

$plan = static fn (
    ?array $pages = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $masterBytesArg = null,
    ?int $size = null,
    ?string $masterPath = null,
    ?string $source = null,
    ?int $sourceEpoch = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext172(
    $masterPath ?? $master,
    $masterBytesArg ?? $masterBytes,
    $size ?? $pageSize,
    $pages ?? $currentPages,
    $readerCache ?? $cache,
    $nextReads ?? $reads,
    $source ?? $sourceId,
    $sourceEpoch ?? $epoch,
);

$row = static function (string $readerId) use ($plan): array {
    foreach ($plan()['cache_rows'] as $row) {
        if ($row['reader_id'] === $readerId) {
            return $row;
        }
    }

    throw new RuntimeException('missing cache row ' . $readerId);
};

$hasOperation = static function (string $op) use ($plan): bool {
    foreach ($plan()['operations'] as $operation) {
        if ($operation['op'] === $op) {
            return true;
        }
    }

    return false;
};

$singleCache = [
    $main => [
        1 => ['image' => $currentPages[$main][1], 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest, 'reader_id' => 'main-schema'],
    ],
];
$singleRead = [['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1]];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next172'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master-journal reader cache entries are scoped by attached database path before current-source reuse'],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'master members' => [static fn (): mixed => $plan()['master_members'], [$main . '-journal', $meta . '-journal', $comments . '-journal']],
    'member databases' => [static fn (): mixed => $plan()['member_databases'], [$main, $meta, $comments]],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 172],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 9],
    'retained main pages' => [static fn (): mixed => $plan()['retained'][$main], [1, 2]],
    'retained meta absent' => [static fn (): mixed => array_key_exists($meta, $plan()['retained']), false],
    'wrong slot reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$main . '|' . $meta . '#3'], 'reader_cache_database_path_mismatches_cache_slot'],
    'stale epoch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$meta . '|' . $meta . '#1'], 'reader_cache_epoch_not_current'],
    'stale digest reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$meta . '|' . $meta . '#2'], 'reader_cache_master_digest_not_current'],
    'stale image reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$meta . '|' . $meta . '#3'], 'reader_cache_image_not_current_database_source'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$comments . '|' . $comments . '#1'], 'dirty_reader_cache_page_after_master_journal_recovery'],
    'source reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$comments . '|' . $comments . '#2'], 'reader_cache_source_id_not_current'],
    'missing database reason' => [static fn (): mixed => $plan()['invalidated_reasons'][$missing . '|' . $missing . '#1'], 'reader_cache_database_not_in_current_master_journal'],
    'invalidated count' => [static fn (): mixed => count($plan()['invalidated']), 7],
    'main row admitted' => [static fn (): mixed => $row('main-schema')['reason'], 'reader_cache_admitted_for_current_database_master_journal_member'],
    'main row slot' => [static fn (): mixed => $row('main-schema')['cache_slot_database_path'], $main],
    'main row db' => [static fn (): mixed => $row('main-schema')['database_path'], $main],
    'main row digest matches' => [static fn (): mixed => $row('main-schema')['master_digest_matches'], true],
    'wrong slot row slot' => [static fn (): mixed => $row('wrong-slot')['cache_slot_database_path'], $main],
    'wrong slot row db' => [static fn (): mixed => $row('wrong-slot')['database_path'], $meta],
    'dirty row flag' => [static fn (): mixed => $row('comments-dirty')['dirty'], true],
    'old source row' => [static fn (): mixed => $row('comments-old-source')['source_id'], 'old-source'],
    'missing db current digest' => [static fn (): mixed => $row('removed-db')['current_digest'], null],
    'read count' => [static fn (): mixed => count($plan()['reads']), 5],
    'read cache hits' => [static fn (): mixed => $plan()['read_cache_hits'], [
        'main-schema-read' => true,
        'main-active-read' => true,
        'meta-cap-read' => false,
        'meta-image-read' => false,
        'comments-index-read' => false,
    ]],
    'main schema prefix' => [static fn (): mixed => $plan()['read_prefixes']['main-schema-read'], 'next172 main schema page'],
    'main active prefix' => [static fn (): mixed => $plan()['read_prefixes']['main-active-read'], 'next172 main active_plugins option'],
    'meta cap prefix' => [static fn (): mixed => $plan()['read_prefixes']['meta-cap-read'], 'next172 meta user capabilities'],
    'meta image prefix' => [static fn (): mixed => $plan()['read_prefixes']['meta-image-read'], 'next172 meta stale image current'],
    'comments prefix' => [static fn (): mixed => $plan()['read_prefixes']['comments-index-read'], 'next172 comments approved index'],
    'first read cache source' => [static fn (): mixed => $plan()['reads'][0]['source'], 'attached-reader-cache-current-master-member-next172'],
    'meta read current source' => [static fn (): mixed => $plan()['reads'][2]['source'], 'current-attached-database-master-member-next172'],
    'read ticket current' => [static fn (): mixed => $plan()['reads'][0]['ticket_current'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['meta-cap-read', 'meta-image-read', 'comments-index-read']],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_members_for_attached_reader_cache_next172'],
    'retain operation present' => [static fn (): mixed => $hasOperation('retain_attached_database_reader_cache_after_master_journal_next172'), true],
    'invalidate operation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_attached_database_reader_cache_after_master_journal_next172'],
    'last operation' => [static fn (): mixed => $plan()['operations'][14]['op'], 'next172_attached_reader_reopen_current_source'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 15],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next172', $plan()['dependencies'], true), true],
    'dependency scope' => [static fn (): mixed => in_array('sqlite-master-journal-attached-database-reader-cache-scope', $plan()['dependencies'], true), true],
    'dependency prior source' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next166', $plan()['dependencies'], true), true],
    'non overlap note' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next166'), true],
    'default read ticket' => [static fn (): mixed => $plan(null, $singleCache, $singleRead)['reads'][0]['ticket_current'], true],
    'default cache source' => [static fn (): mixed => $plan(null, $singleCache, $singleRead)['reads'][0]['source'], 'attached-reader-cache-current-master-member-next172'],
    'stale read source ticket' => [static fn (): mixed => $plan(null, $singleCache, [['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1, 'source_id' => 'old']])['reads'][0]['ticket_current'], false],
    'stale read source reopens' => [static fn (): mixed => $plan(null, $singleCache, [['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1, 'source_id' => 'old']])['reopen_reader_ids'], ['main-schema-read']],
    'stale read epoch reopens' => [static fn (): mixed => $plan(null, $singleCache, [['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1, 'epoch' => 171]])['reopen_reader_ids'], ['main-schema-read']],
    'stale read digest reopens' => [static fn (): mixed => $plan(null, $singleCache, [['reader_id' => 'main-schema-read', 'database_path' => $main, 'page_number' => 1, 'master_digest' => str_repeat('1', 64)]])['reopen_reader_ids'], ['main-schema-read']],
    'pinned invalidation reason' => [static fn (): mixed => $plan(null, [$main => [1 => array_replace($singleCache[$main][1], ['pinned' => true])]], $singleRead)['invalidated_reasons'][$main . '|' . $main . '#1'], 'pinned_reader_cache_page_requires_reopen_after_master_journal_recovery'],
    'page absent reason' => [static fn (): mixed => $plan(null, [$main => [4 => array_replace($singleCache[$main][1], ['image' => $page('next172 absent page')])]], $singleRead)['invalidated_reasons'][$main . '|' . $main . '#4'], 'reader_cache_page_absent_from_current_database'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next172 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'blank master bytes rejected' => static fn () => $plan(null, null, null, " \n"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, 0),
    'non power page size rejected' => static fn () => $plan(null, null, null, null, 768),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, 0),
    'empty pages rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'bad member rejected' => static fn () => $plan(null, null, null, $main),
    'current pages outside master rejected' => static fn () => $plan([$main => $currentPages[$main], '/tmp/other.sqlite' => [1 => $page('other')]]),
    'empty database page set rejected' => static fn () => $plan([$main => []], $singleCache, $singleRead),
    'zero current page rejected' => static fn () => $plan([$main => [0 => $currentPages[$main][1]]], $singleCache, $singleRead),
    'short current page rejected' => static fn () => $plan([$main => [1 => 'short']], $singleCache, $singleRead),
    'empty cache slot rejected' => static fn () => $plan(null, ['' => [1 => $singleCache[$main][1]]], $singleRead),
    'zero cache page rejected' => static fn () => $plan(null, [$main => [0 => $singleCache[$main][1]]], $singleRead),
    'short cache image rejected' => static fn () => $plan(null, [$main => [1 => ['image' => 'short']]], $singleRead),
    'negative cache epoch rejected' => static fn () => $plan(null, [$main => [1 => array_replace($singleCache[$main][1], ['epoch' => -1])]], $singleRead),
    'empty cache database path rejected' => static fn () => $plan(null, [$main => [1 => array_replace($singleCache[$main][1], ['database_path' => ''])]], $singleRead),
    'empty reader id rejected' => static fn () => $plan(null, [$main => [1 => array_replace($singleCache[$main][1], ['reader_id' => ''])]], $singleRead),
    'empty read id rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => '', 'database_path' => $main, 'page_number' => 1]]),
    'empty read database rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => 'bad', 'database_path' => '', 'page_number' => 1]]),
    'zero read page rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => 'bad', 'database_path' => $main, 'page_number' => 0]]),
    'negative read epoch rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => 'bad', 'database_path' => $main, 'page_number' => 1, 'epoch' => -1]]),
    'read database outside master rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => 'bad', 'database_path' => '/tmp/other.sqlite', 'page_number' => 1]]),
    'read page outside current rejected' => static fn () => $plan(null, $singleCache, [['reader_id' => 'bad', 'database_path' => $main, 'page_number' => 9]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next172 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
