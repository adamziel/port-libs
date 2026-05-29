<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next169.sqlite';
$masterPath = '/srv/wp-content/database/wp-next169.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next169-site-meta.sqlite-journal';
$pluginJournal = '/srv/wp-content/database/wp-next169-plugin.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n" . $pluginJournal . "\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$pages = [
    1 => $page('next169 recovered main schema after attached master journal'),
    2 => $page('next169 recovered wp_options root after attached master journal'),
    3 => $page('next169 recovered active_plugins after attached master journal'),
    4 => $page('next169 recovered site meta after attached master journal'),
    5 => $page('next169 recovered plugin settings after attached master journal'),
    6 => $page('next169 recovered transient after attached master journal'),
];
$states = [
    $mainJournal => ['generation' => 12, 'recovered' => true, 'hot' => false, 'deleted' => true],
    $metaJournal => ['generation' => 8, 'recovered' => true, 'hot' => false, 'deleted' => true],
    $pluginJournal => ['generation' => 4, 'recovered' => true, 'hot' => false, 'deleted' => true],
];
$sourceId = 'next169-current-attached-master-source';
$cache = [
    1 => ['reader_id' => 'schema-reader', 'image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 22, 'member_journal' => $mainJournal, 'member_generation' => 12],
    2 => ['reader_id' => 'options-reader', 'image' => $pages[2], 'source_id' => $sourceId, 'epoch' => 22, 'member_journal' => $mainJournal, 'member_generation' => 12],
    3 => ['reader_id' => 'active-reader', 'image' => $page('next169 stale active_plugins before attached recovery'), 'source_id' => $sourceId, 'epoch' => 22, 'member_journal' => $mainJournal, 'member_generation' => 12, 'pinned' => true],
    4 => ['reader_id' => 'meta-reader', 'image' => $pages[4], 'source_id' => $sourceId, 'epoch' => 22, 'member_journal' => $metaJournal, 'member_generation' => 7],
    5 => ['reader_id' => 'plugin-reader', 'image' => $pages[5], 'source_id' => $sourceId, 'epoch' => 21, 'member_journal' => $pluginJournal, 'member_generation' => 4],
    6 => ['reader_id' => 'transient-reader', 'image' => $pages[6], 'source_id' => $sourceId, 'epoch' => 22, 'member_journal' => $pluginJournal, 'member_generation' => 4, 'dirty' => true],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'member_journal' => $mainJournal],
    ['reader_id' => 'options-reader', 'page_number' => 2, 'member_journal' => $mainJournal],
    ['reader_id' => 'active-reader', 'page_number' => 3, 'member_journal' => $mainJournal],
    ['reader_id' => 'meta-reader', 'page_number' => 4, 'member_journal' => $metaJournal],
    ['reader_id' => 'plugin-reader', 'page_number' => 5, 'member_journal' => $pluginJournal],
    ['reader_id' => 'transient-reader', 'page_number' => 6, 'member_journal' => $pluginJournal],
];

$plan = static function (
    ?array $memberStates = null,
    ?array $currentPages = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $source = null,
    int $epoch = 22,
    ?string $path = null,
    ?string $masterJournalPath = null,
) use ($databasePath, $masterPath, $masterBytes, $pageSize, $states, $pages, $cache, $reads, $sourceId): array {
    return SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext169(
        $path ?? $databasePath,
        $masterJournalPath ?? $masterPath,
        $bytes ?? $masterBytes,
        $size ?? $pageSize,
        $memberStates ?? $states,
        $currentPages ?? $pages,
        $readerCache ?? $cache,
        $nextReads ?? $reads,
        $source ?? $sourceId,
        $epoch,
    );
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next169'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'attached_master_journal_member_recovery_state_fences_reader_cache_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'member count' => [static fn (): mixed => count($plan()['current_members']), 3],
    'main member' => [static fn (): mixed => $plan()['current_members'][0], $mainJournal],
    'meta member' => [static fn (): mixed => $plan()['current_members'][1], $metaJournal],
    'plugin member' => [static fn (): mixed => $plan()['current_members'][2], $pluginJournal],
    'main generation' => [static fn (): mixed => $plan()['member_states'][$mainJournal]['generation'], 12],
    'meta generation' => [static fn (): mixed => $plan()['member_states'][$metaJournal]['generation'], 8],
    'plugin deleted' => [static fn (): mixed => $plan()['member_states'][$pluginJournal]['deleted'], true],
    'unrecovered members empty' => [static fn (): mixed => $plan()['unrecovered_members'], []],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 22],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 6],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_member_recovered_and_current'],
    'options reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_member_recovered_and_current'],
    'active pinned reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'pinned_reader_cache_cross_member_image_mismatch'],
    'meta generation reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'reader_cache_member_generation_not_current'],
    'plugin epoch reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'reader_cache_epoch_not_current_member_source'],
    'dirty transient reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'dirty_reader_cache_after_attached_master_recovery'],
    'invalidated reason map active' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'pinned_reader_cache_cross_member_image_mismatch'],
    'invalidated reason map meta' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_member_generation_not_current'],
    'invalidated reason map plugin' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'reader_cache_epoch_not_current_member_source'],
    'read count' => [static fn (): mixed => count($plan()['reads']), 6],
    'schema cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'options cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['options-reader'], true],
    'active cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['active-reader'], false],
    'meta cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['meta-reader'], false],
    'plugin cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['plugin-reader'], false],
    'transient cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['transient-reader'], false],
    'schema prefix' => [static fn (): mixed => $plan()['read_prefixes']['schema-reader'], 'next169 recovered main schema after attached master journal'],
    'active fallback prefix' => [static fn (): mixed => $plan()['read_prefixes']['active-reader'], 'next169 recovered active_plugins after attached master journal'],
    'meta fallback prefix' => [static fn (): mixed => $plan()['read_prefixes']['meta-reader'], 'next169 recovered site meta after attached master journal'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['active-reader', 'meta-reader', 'plugin-reader', 'transient-reader']],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_member_states_before_reader_cache'],
    'retain operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_for_recovered_master_member'],
    'invalidate operation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_for_attached_master_member'],
    'read operation cache hit' => [static fn (): mixed => $plan()['operations'][7]['op'], 'next_reader_cache_hit_recovered_master_member'],
    'read operation reopen' => [static fn (): mixed => $plan()['operations'][9]['op'], 'next_reader_reopen_recovered_master_member_source'],
    'member state digest length' => [static fn (): mixed => strlen($plan()['member_state_digest']), 64],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next169', $plan()['dependencies'], true), true],
    'dependency member fence' => [static fn (): mixed => in_array('sqlite-master-journal-attached-member-recovery-fence', $plan()['dependencies'], true), true],
    'all retained no reopen' => [static fn (): mixed => $plan(null, null, array_slice($cache, 0, 2, true), array_slice($reads, 0, 2))['requires_reader_reopen'], false],
    'all retained readers' => [static fn (): mixed => $plan(null, null, array_slice($cache, 0, 2, true), array_slice($reads, 0, 2))['reopen_reader_ids'], []],
    'source mismatch reason' => [static fn (): mixed => $plan(null, null, [1 => array_merge($cache[1], ['source_id' => 'old-source'])], [array_merge($reads[0], ['source_id' => 'old-source'])])['cache_rows'][0]['reason'], 'reader_cache_source_id_not_current_member_source'],
    'ticket mismatch reopens' => [static fn (): mixed => $plan(null, null, [1 => $cache[1]], [array_merge($reads[0], ['epoch' => 21])])['reopen_reader_ids'], ['schema-reader']],
    'unrecovered member listed' => [static fn (): mixed => $plan(array_merge($states, [$pluginJournal => ['generation' => 4, 'recovered' => false, 'hot' => true, 'deleted' => false]]))['unrecovered_members'], [$pluginJournal]],
    'unrecovered member reason wins' => [static fn (): mixed => $plan(array_merge($states, [$pluginJournal => ['generation' => 4, 'recovered' => false, 'hot' => true, 'deleted' => false]]), null, [5 => $cache[5]], [array_merge($reads[4], ['source_id' => $sourceId])])['cache_rows'][0]['reason'], 'master_journal_members_not_fully_recovered'],
    'member not current reason' => [static fn (): mixed => $plan(null, null, [5 => array_merge($cache[5], ['member_journal' => '/tmp/missing.sqlite-journal', 'epoch' => 22])], [$reads[4]])['cache_rows'][0]['reason'], 'reader_cache_member_not_in_current_master_journal'],
    'image mismatch reason' => [static fn (): mixed => $plan(null, null, [2 => array_merge($cache[2], ['image' => $page('next169 stale but unpinned options')])], [$reads[1]])['cache_rows'][0]['reason'], 'reader_cache_image_digest_not_current_member_source'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next169 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, 22, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, 22, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, null, ''),
    'wrong master bytes rejected' => static fn () => $plan(null, null, null, null, '/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, 0),
    'empty states rejected' => static fn () => $plan([]),
    'empty pages rejected' => static fn () => $plan(null, []),
    'empty cache rejected' => static fn () => $plan(null, null, []),
    'empty reads rejected' => static fn () => $plan(null, null, null, []),
    'state outside master rejected' => static fn () => $plan(array_merge($states, ['/tmp/outside.sqlite-journal' => ['generation' => 1]])),
    'missing state rejected' => static fn () => $plan(array_slice($states, 0, 2, true)),
    'bad generation rejected' => static fn () => $plan(array_merge($states, [$mainJournal => ['generation' => 0]])),
    'zero page rejected' => static fn () => $plan(null, [0 => $pages[1]]),
    'short page rejected' => static fn () => $plan(null, [1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, null, [0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan(null, null, [1 => array_merge($cache[1], ['image' => 'short'])]),
    'cache missing member rejected' => static fn () => $plan(null, null, [1 => array_diff_key($cache[1], ['member_journal' => true])]),
    'cache bad generation rejected' => static fn () => $plan(null, null, [1 => array_merge($cache[1], ['member_generation' => 0])]),
    'cache bad epoch rejected' => static fn () => $plan(null, null, [1 => array_merge($cache[1], ['epoch' => 0])]),
    'read missing reader rejected' => static fn () => $plan(null, null, null, [['reader_id' => '', 'page_number' => 1, 'member_journal' => $mainJournal]]),
    'read bad page rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'x', 'page_number' => 0, 'member_journal' => $mainJournal]]),
    'read missing member rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'x', 'page_number' => 1]]),
    'read outside page rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'x', 'page_number' => 7, 'member_journal' => $mainJournal]]),
    'read outside member rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'x', 'page_number' => 1, 'member_journal' => '/tmp/missing.sqlite-journal']]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next169 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
